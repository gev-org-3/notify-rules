<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\NotificationRules;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Orion\NotifyRules\Filament\Resources\Concerns\AuthorizesRuleManagers;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\CreateNotificationRule;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\EditNotificationRule;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Schemas\NotificationRuleForm;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Tables\NotificationRulesTable;
use Orion\NotifyRules\Models\NotificationRule;

final class NotificationRuleResource extends Resource
{
    use AuthorizesRuleManagers;

    protected static ?string $model = NotificationRule::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'notification-rules';

    public static function getModelLabel(): string
    {
        return __('notify-rules::notify-rules.rules.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notify-rules::notify-rules.rules.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return NotificationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationRules::route('/'),
            'create' => CreateNotificationRule::route('/create'),
            'edit' => EditNotificationRule::route('/{record}/edit'),
        ];
    }
}
