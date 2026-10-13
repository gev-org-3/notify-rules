<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Engine\Roles;

use Illuminate\Database\Eloquent\Model;

/**
 * Uses spatie/laravel-permission when the user model has its HasRoles trait. It is not a dependency
 * of this package: without it, rules simply resolve no users by role.
 */
final class SpatieRoleUserResolver implements RoleUserResolver
{
    public function userIds(array $roles): array
    {
        $userModel = config('notify-rules.user_model');

        if ($roles === [] || ! is_string($userModel) || ! is_subclass_of($userModel, Model::class)) {
            return [];
        }

        if (! method_exists($userModel, 'scopeRole')) {
            return [];
        }

        /** @var Model $user */
        $user = new $userModel;

        return $userModel::query()
            ->scopes(['role' => [$roles]])
            ->pluck($user->getKeyName())
            ->all();
    }
}
