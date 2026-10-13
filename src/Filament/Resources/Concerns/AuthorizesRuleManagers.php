<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Orion\NotifyRules\NotifyRulesPlugin;
use Orion\NotifyRules\Support\Tenancy;

/**
 * Access is decided by the model policies (NotificationRulePolicy and DeliveryPolicy unless the app
 * defines its own). Records are scoped to the current tenant through the column set in
 * notify-rules.tenancy.column, not through a Filament ownership relationship.
 */
trait AuthorizesRuleManagers
{
    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(Tenancy::class)->scopeToCurrent(parent::getEloquentQuery());
    }

    public static function getNavigationGroup(): ?string
    {
        return NotifyRulesPlugin::get()->getNavigationGroup();
    }
}
