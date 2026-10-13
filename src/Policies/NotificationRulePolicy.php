<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use OrionSoft\NotifyRules\Models\NotificationRule;
use OrionSoft\NotifyRules\Policies\Concerns\ChecksRuleManagers;

/**
 * Registered unless the app defines its own policy for NotificationRule.
 */
final class NotificationRulePolicy
{
    use ChecksRuleManagers;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->managesRules($user);
    }

    public function view(Authenticatable $user, NotificationRule $rule): bool
    {
        return $this->managesRecord($user, $rule);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->managesRules($user);
    }

    public function update(Authenticatable $user, NotificationRule $rule): bool
    {
        return $this->managesRecord($user, $rule);
    }

    public function delete(Authenticatable $user, NotificationRule $rule): bool
    {
        return $this->managesRecord($user, $rule);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->managesRules($user);
    }
}
