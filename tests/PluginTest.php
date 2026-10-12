<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Orion\NotifyRules\NotifyRulesPlugin;
use Orion\NotifyRules\Tests\Fixtures\Models\Order;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

it('is registered in the panel', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $plugin = NotifyRulesPlugin::get();

    expect($plugin)->toBeInstanceOf(NotifyRulesPlugin::class)
        ->and($plugin->getId())->toBe('notify-rules')
        ->and($plugin->getModels())->toBe([Order::class])
        ->and($plugin->getNavigationGroup())->toBe('Notifications')
        ->and($plugin->hasDeliveryLog())->toBeTrue()
        ->and($plugin->hasConnectTelegramInUserMenu())->toBeTrue();
});

it('checks who can manage rules', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $plugin = NotifyRulesPlugin::get();

    expect($plugin->userCanManageRules(User::factory()->admin()->create()))->toBeTrue()
        ->and($plugin->userCanManageRules(User::factory()->create()))->toBeFalse()
        ->and($plugin->userCanManageRules(null))->toBeFalse();
});

it('allows only Eloquent models in the allowlist', function (): void {
    NotifyRulesPlugin::make()->models([stdClass::class]);
})->throws(InvalidArgumentException::class);

it('loads the package config', function (): void {
    expect(config('notify-rules.tables'))->toBe([
        'rules' => 'notify_rules',
        'telegram_links' => 'notify_telegram_links',
        'deliveries' => 'notify_deliveries',
    ]);
});

it('creates the package tables', function (): void {
    expect(Schema::hasTable('notify_rules'))->toBeTrue()
        ->and(Schema::hasTable('notify_telegram_links'))->toBeTrue()
        ->and(Schema::hasTable('notify_deliveries'))->toBeTrue();
});

it('serves the panel with the plugin installed', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful();
});
