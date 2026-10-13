<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Orion\NotifyRules\NotifyRulesPlugin;

/**
 * Access requires the plugin's canManageRules() callback and, when the app defines one, the model policy.
 */
trait AuthorizesRuleManagers
{
    public static function canViewAny(): bool
    {
        return static::userCanManageRules() && parent::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::userCanManageRules() && parent::canView($record);
    }

    public static function canCreate(): bool
    {
        return static::userCanManageRules() && parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManageRules() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanManageRules() && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanManageRules() && parent::canDeleteAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return NotifyRulesPlugin::get()->getNavigationGroup();
    }

    protected static function userCanManageRules(): bool
    {
        return NotifyRulesPlugin::get()->userCanManageRules(Filament::auth()->user());
    }
}
