<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Filament\Resources\Deliveries\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use OrionSoft\NotifyRules\Enums\DeliveryStatus;
use OrionSoft\NotifyRules\Models\Delivery;
use OrionSoft\NotifyRules\Support\Tenancy;

final class DeliveriesTable
{
    private const T = 'notify-rules::notify-rules.deliveries.';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['rule', 'telegramLink.notifiable']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__(self::T . 'fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('rule.name')
                    ->label(__(self::T . 'fields.rule'))
                    ->placeholder(__(self::T . 'fields.no_rule'))
                    ->searchable(),
                TextColumn::make('chat_id')
                    ->label(__(self::T . 'fields.recipient'))
                    ->formatStateUsing(fn (Delivery $record): string => self::recipient($record))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__(self::T . 'fields.status'))
                    ->badge(),
                TextColumn::make('attempts')
                    ->label(__(self::T . 'fields.attempts'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('error')
                    ->label(__(self::T . 'fields.error'))
                    ->limit(60)
                    ->tooltip(fn (Delivery $record): ?string => $record->error)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__(self::T . 'fields.status'))
                    ->options(DeliveryStatus::class),
                SelectFilter::make('rule')
                    ->label(__(self::T . 'fields.rule'))
                    ->relationship('rule', 'name', fn (Builder $query): Builder => app(Tenancy::class)->scopeToCurrent($query))
                    ->searchable()
                    ->preload(),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__(self::T . 'filters.from')),
                        DatePicker::make('until')->label(__(self::T . 'filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['from'] ?? null) ? __(self::T . 'filters.from') . ': ' . $data['from'] : null,
                        filled($data['until'] ?? null) ? __(self::T . 'filters.until') . ': ' . $data['until'] : null,
                    ])),
            ])
            ->recordActions([
                ViewAction::make(),
                self::resendAction(),
            ])
            ->toolbarActions([
                self::resendBulkAction(),
            ]);
    }

    /**
     * The linked user's name or Telegram username, with the chat ID.
     */
    public static function recipient(Delivery $record): string
    {
        $link = $record->telegramLink;
        $user = $link?->notifiable;
        $title = (string) config('notify-rules.user_title_attribute', 'name');

        $name = match (true) {
            $user instanceof Model && filled($user->getAttribute($title)) => (string) $user->getAttribute($title),
            filled($link?->username) => '@' . $link->username,
            default => null,
        };

        return $name === null ? $record->chat_id : "{$name} ({$record->chat_id})";
    }

    private static function resendAction(): Action
    {
        return Action::make('resend')
            ->label(__(self::T . 'actions.resend'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Delivery $record): bool => $record->canBeResent())
            ->authorize('resend')
            ->requiresConfirmation()
            ->action(function (Delivery $record): void {
                $record->resend();

                Notification::make()
                    ->success()
                    ->title(trans_choice(self::T . 'notifications.resent', 1))
                    ->send();
            });
    }

    private static function resendBulkAction(): BulkAction
    {
        return BulkAction::make('resend')
            ->label(__(self::T . 'actions.resend_failed'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $failed = $records->filter(fn (Delivery $record): bool => Gate::allows('resend', $record));

                $failed->each(fn (Delivery $record) => $record->resend());

                Notification::make()
                    ->success()
                    ->title(trans_choice(self::T . 'notifications.resent', $failed->count()))
                    ->send();
            });
    }
}
