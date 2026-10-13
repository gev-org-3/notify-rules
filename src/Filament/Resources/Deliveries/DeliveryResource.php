<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\Deliveries;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Orion\NotifyRules\Filament\Resources\Concerns\AuthorizesRuleManagers;
use Orion\NotifyRules\Filament\Resources\Deliveries\Pages\ListDeliveries;
use Orion\NotifyRules\Filament\Resources\Deliveries\Schemas\DeliveryInfolist;
use Orion\NotifyRules\Filament\Resources\Deliveries\Tables\DeliveriesTable;
use Orion\NotifyRules\Models\Delivery;

/**
 * Read-only log of sent messages. Failed deliveries can be resent.
 */
final class DeliveryResource extends Resource
{
    use AuthorizesRuleManagers;

    protected static ?string $model = Delivery::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'notification-deliveries';

    public static function getModelLabel(): string
    {
        return __('notify-rules::notify-rules.deliveries.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notify-rules::notify-rules.deliveries.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeliveryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveries::route('/'),
        ];
    }
}
