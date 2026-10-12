<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => 'password',
        ]);

        foreach ([['new', 250], ['paid', 1499.90], ['shipped', 80]] as $i => [$status, $total]) {
            Order::query()->create([
                'number' => sprintf('ORD-%05d', $i + 1),
                'status' => $status,
                'total' => $total,
                'customer_id' => $admin->getKey(),
            ]);
        }
    }
}
