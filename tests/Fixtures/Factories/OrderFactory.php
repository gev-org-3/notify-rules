<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OrionSoft\NotifyRules\Tests\Fixtures\Models\Order;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('ORD-#####'),
            'status' => 'new',
            'total' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
