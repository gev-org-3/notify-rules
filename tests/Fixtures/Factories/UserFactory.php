<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_admin' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }
}
