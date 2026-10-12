<?php

declare(strict_types=1);

use Orion\NotifyRules\Models\TelegramLink;
use Orion\NotifyRules\Telegram\TelegramLinkManager;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->links = app(TelegramLinkManager::class);
    $this->user = User::factory()->create();
});

it('creates a one-time token and stores only its hash', function (): void {
    $token = $this->links->createLinkToken($this->user);

    $link = $this->links->linkFor($this->user);

    expect($token)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and($link->link_token)->toBe(hash('sha256', $token))
        ->and($link->link_token_expires_at->isFuture())->toBeTrue()
        ->and($link->link_token_expires_at->lte(now()->addMinutes(15)))->toBeTrue()
        ->and($link->is_active)->toBeFalse();
});

it('builds a deep link to the bot', function (): void {
    config()->set('notify-rules.telegram.bot_username', '@my_bot');

    expect($this->links->deepLink('abc'))->toBe('https://t.me/my_bot?start=abc');
});

it('refuses to build a deep link without a bot username', function (): void {
    config()->set('notify-rules.telegram.bot_username', null);

    $this->links->deepLink('abc');
})->throws(LogicException::class);

it('links the chat with a valid token', function (): void {
    $token = $this->links->createLinkToken($this->user);

    $link = $this->links->complete($token, '777', 'john');

    expect($link)->not->toBeNull()
        ->and($link->chat_id)->toBe('777')
        ->and($link->username)->toBe('john')
        ->and($link->is_active)->toBeTrue()
        ->and($link->linked_at)->not->toBeNull()
        ->and($link->link_token)->toBeNull()
        ->and($this->links->isConnected($this->user))->toBeTrue();
});

it('accepts a token only once', function (): void {
    $token = $this->links->createLinkToken($this->user);

    expect($this->links->complete($token, '777', null))->not->toBeNull()
        ->and($this->links->complete($token, '888', null))->toBeNull()
        ->and($this->links->linkFor($this->user)->chat_id)->toBe('777');
});

it('rejects an expired token', function (): void {
    $token = $this->links->createLinkToken($this->user);

    $this->travel(16)->minutes();

    expect($this->links->complete($token, '777', null))->toBeNull()
        ->and($this->links->isConnected($this->user))->toBeFalse()
        ->and($this->links->linkFor($this->user)->link_token)->toBeNull();
});

it('rejects unknown tokens', function (): void {
    $this->links->createLinkToken($this->user);

    expect($this->links->complete('wrong', '777', null))->toBeNull()
        ->and($this->links->complete('', '777', null))->toBeNull();
});

it('replaces the previous token when a new one is created', function (): void {
    $old = $this->links->createLinkToken($this->user);
    $new = $this->links->createLinkToken($this->user);

    expect($this->links->complete($old, '777', null))->toBeNull()
        ->and($this->links->complete($new, '777', null))->not->toBeNull()
        ->and(TelegramLink::count())->toBe(1);
});

it('disconnects the user', function (): void {
    $this->links->complete($this->links->createLinkToken($this->user), '777', 'john');

    $this->links->disconnect($this->user);

    $link = $this->links->linkFor($this->user);
    expect($this->links->isConnected($this->user))->toBeFalse()
        ->and($link->chat_id)->toBeNull()
        ->and($link->is_active)->toBeFalse();
});
