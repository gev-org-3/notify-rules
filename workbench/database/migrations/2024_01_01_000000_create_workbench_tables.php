<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Testbench already creates the users, cache and jobs tables.
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->string('status');
            $table->decimal('total', 10, 2);
            $table->foreignId('customer_id')->nullable();
            $table->timestamps();
        });

        // The package ships its migration as a publishable stub.
        (include __DIR__ . '/../../../database/migrations/create_notify_rules_tables.php.stub')->up();
    }
};
