<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Orders;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Orders\Pages\CreateOrder;
use Workbench\App\Filament\Resources\Orders\Pages\EditOrder;
use Workbench\App\Filament\Resources\Orders\Pages\ListOrders;
use Workbench\App\Models\Order;

/**
 * Lets you trigger rules by hand, and gives {{ url }} a page to link to.
 */
final class OrderResource extends Resource
{
    public const STATUSES = ['new' => 'New', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'];

    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = -1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('number')
                    ->required()
                    ->default(fn (): string => sprintf('ORD-%05d', (int) Order::query()->max('id') + 1)),
                Select::make('status')
                    ->options(self::STATUSES)
                    ->default('new')
                    ->required(),
                TextInput::make('total')
                    ->numeric()
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? $state),
                TextColumn::make('total')->numeric(2)->sortable(),
                TextColumn::make('customer.name'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
