<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\Deliveries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Orion\NotifyRules\Engine\TemplateRenderer;
use Orion\NotifyRules\Filament\Resources\Deliveries\Tables\DeliveriesTable;
use Orion\NotifyRules\Models\Delivery;

final class DeliveryInfolist
{
    private const T = 'notify-rules::notify-rules.deliveries.fields.';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('status')
                    ->label(__(self::T . 'status'))
                    ->badge(),
                TextEntry::make('rule.name')
                    ->label(__(self::T . 'rule'))
                    ->placeholder(__(self::T . 'no_rule')),
                TextEntry::make('chat_id')
                    ->label(__(self::T . 'recipient'))
                    ->formatStateUsing(fn (Delivery $record): string => DeliveriesTable::recipient($record)),
                TextEntry::make('attempts')
                    ->label(__(self::T . 'attempts'))
                    ->numeric(),
                TextEntry::make('created_at')
                    ->label(__(self::T . 'created_at'))
                    ->dateTime(),
                TextEntry::make('sent_at')
                    ->label(__(self::T . 'sent_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('message')
                    ->label(__(self::T . 'message'))
                    ->formatStateUsing(fn (string $state): HtmlString => app(TemplateRenderer::class)->toPanelHtml($state))
                    ->html()
                    ->columnSpanFull(),
                TextEntry::make('error')
                    ->label(__(self::T . 'error'))
                    ->placeholder('—')
                    ->color('danger')
                    ->columnSpanFull(),
            ]);
    }
}
