<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Orion\NotifyRules\Filament\Pages\TelegramConnection;
use Orion\NotifyRules\Telegram\TelegramLinkManager;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('adds a Telegram item to the user menu', function (): void {
    $items = Filament::getPanel('admin')->getUserMenuItems();

    expect($items)->toHaveKey('notify-rules-telegram')
        ->and($items['notify-rules-telegram']->getUrl())->toBe(TelegramConnection::getUrl());
});

it('renders for a user without Telegram', function (): void {
    $this->get(TelegramConnection::getUrl())
        ->assertSuccessful()
        ->assertSee('Not connected');
});

it('generates a deep link on connect', function (): void {
    $component = Livewire::test(TelegramConnection::class)
        ->callAction('connect')
        ->assertHasNoActionErrors();

    $deepLink = $component->get('deepLink');

    expect($deepLink)->toStartWith('https://t.me/notify_rules_test_bot?start=');

    $component->assertSee('Open Telegram');

    $token = str($deepLink)->after('start=')->toString();
    expect(app(TelegramLinkManager::class)->complete($token, '777', 'john'))->not->toBeNull();

    $component->call('refreshStatus')
        ->assertSet('deepLink', null)
        ->assertSee('Connected as @john');
});

it('warns when the bot username is missing', function (): void {
    config()->set('notify-rules.telegram.bot_username', null);

    Livewire::test(TelegramConnection::class)
        ->callAction('connect')
        ->assertSet('deepLink', null)
        ->assertNotified();
});

it('disconnects Telegram', function (): void {
    $links = app(TelegramLinkManager::class);
    $links->complete($links->createLinkToken($this->user), '777', 'john');

    Livewire::test(TelegramConnection::class)
        ->assertActionVisible('disconnect')
        ->callAction('disconnect')
        ->assertNotified()
        ->assertSee('Not connected');

    expect($links->isConnected($this->user))->toBeFalse();
});

it('hides disconnect when not connected', function (): void {
    Livewire::test(TelegramConnection::class)->assertActionHidden('disconnect');
});
