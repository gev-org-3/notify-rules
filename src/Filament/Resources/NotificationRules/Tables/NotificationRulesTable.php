<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\NotificationRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Enums\RuleEvent;
use Orion\NotifyRules\Filament\Resources\NotificationRules\RuleFormOptions;
use Orion\NotifyRules\Models\NotificationRule;

final class NotificationRulesTable
{
    private const T = 'notify-rules::notify-rules.rules.table.';

    public static function configure(Table $table): Table
    {
        $options = app(RuleFormOptions::class);

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__(self::T . 'name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label(__(self::T . 'model'))
                    ->formatStateUsing(fn (string $state): string => $options->modelLabel($state)),
                TextColumn::make('event')
                    ->label(__(self::T . 'event'))
                    ->badge()
                    ->color('gray'),
                ToggleColumn::make('is_active')
                    ->label(__(self::T . 'is_active'))
                    // Inline columns skip the policy checks that actions get.
                    ->disabled(fn (NotificationRule $record): bool => Gate::denies('update', $record)),
                TextColumn::make('deliveries_count')
                    ->label(__(self::T . 'sent_last_7_days'))
                    ->counts([
                        'deliveries' => fn (Builder $query): Builder => $query
                            ->where('status', DeliveryStatus::Sent)
                            ->where('created_at', '>=', now()->subDays(7)),
                    ])
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__(self::T . 'updated_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('model_type')
                    ->label(__(self::T . 'model'))
                    ->options(fn (): array => $options->models()),
                SelectFilter::make('event')
                    ->label(__(self::T . 'event'))
                    ->options(RuleEvent::class),
                TernaryFilter::make('is_active')
                    ->label(__(self::T . 'is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
