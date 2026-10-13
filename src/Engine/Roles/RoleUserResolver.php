<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Engine\Roles;

/**
 * Finds the users that have any of the given roles. Bind your own implementation
 * in the container if roles are not managed by spatie/laravel-permission.
 */
interface RoleUserResolver
{
    /**
     * @param  array<int, string>  $roles
     * @return array<int, int|string> User keys
     */
    public function userIds(array $roles): array;
}
