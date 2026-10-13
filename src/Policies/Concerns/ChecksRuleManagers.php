<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Policies\Concerns;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use OrionSoft\NotifyRules\NotifyRulesPlugin;
use OrionSoft\NotifyRules\Support\Tenancy;

trait ChecksRuleManagers
{
    /**
     * Uses the canManageRules() callback of the current panel's plugin. Outside a panel with the plugin
     * nobody manages rules.
     */
    protected function managesRules(Authenticatable $user): bool
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin('notify-rules')) {
            return false;
        }

        $plugin = $panel->getPlugin('notify-rules');

        return $plugin instanceof NotifyRulesPlugin && $plugin->userCanManageRules($user);
    }

    protected function managesRecord(Authenticatable $user, Model $record): bool
    {
        return $this->managesRules($user) && app(Tenancy::class)->belongsToCurrent($record);
    }
}
