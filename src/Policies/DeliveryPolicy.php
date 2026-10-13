<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Policies\Concerns\ChecksRuleManagers;

/**
 * The delivery log is read-only: deliveries are only created by rules and can only be resent.
 * Registered unless the app defines its own policy for Delivery.
 */
final class DeliveryPolicy
{
    use ChecksRuleManagers;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->managesRules($user);
    }

    public function view(Authenticatable $user, Delivery $delivery): bool
    {
        return $this->managesRecord($user, $delivery);
    }

    public function resend(Authenticatable $user, Delivery $delivery): bool
    {
        return $delivery->canBeResent() && $this->managesRecord($user, $delivery);
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, Delivery $delivery): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Delivery $delivery): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
