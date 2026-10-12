<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orion\NotifyRules\Telegram\TelegramLinkManager;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

function telegramUpdate(string $text, string $chatType = 'private', ?string $languageCode = 'en'): array
{
    return [
        'update_id' => 1000,
        'message' => [
            'message_id' => 1,
            'from' => ['id' => 777, 'is_bot' => false, 'first_name' => 'John', 'username' => 'john', 'language_code' => $languageCode],
            'chat' => ['id' => $chatType === 'private' ? 777 : -100123, 'type' => $chatType],
            'date' => time(),
            'text' => $text,
        ],
    ];
}

function postUpdate(array $update, ?string $secret = 'test-secret')
{
    return test()->postJson(
        route('notify-rules.telegram.webhook'),
        $update,
        $secret === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $secret],
    );
}

beforeEach(function (): void {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
});

it('is served at the documented path', function (): void {
    expect(route('notify-rules.telegram.webhook', absolute: false))->toBe('/notify-rules/telegram/webhook');
});

it('links the chat on /start with a valid token', function (): void {
    $user = User::factory()->create();
    $token = app(TelegramLinkManager::class)->createLinkToken($user);

    postUpdate(telegramUpdate("/start {$token}"))->assertNoContent();

    $link = app(TelegramLinkManager::class)->linkFor($user);
    expect($link->chat_id)->toBe('777')
        ->and($link->username)->toBe('john')
        ->and($link->is_active)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
        && $request['chat_id'] === '777'
        && str_starts_with($request['text'], 'Connected ✅'));
});

it('replies in Russian to Russian-speaking users', function (): void {
    $token = app(TelegramLinkManager::class)->createLinkToken(User::factory()->create());

    postUpdate(telegramUpdate("/start {$token}", languageCode: 'ru'))->assertNoContent();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request['text'], 'Подключено ✅'));
});

it('reports an expired token to the user', function (): void {
    $user = User::factory()->create();
    $token = app(TelegramLinkManager::class)->createLinkToken($user);
    $this->travel(16)->minutes();

    postUpdate(telegramUpdate("/start {$token}"))->assertNoContent();

    expect(app(TelegramLinkManager::class)->isConnected($user))->toBeFalse();
    Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'invalid or has expired'));
});

it('does not link group chats', function (): void {
    $user = User::factory()->create();
    $token = app(TelegramLinkManager::class)->createLinkToken($user);

    postUpdate(telegramUpdate("/start {$token}", chatType: 'supergroup'))->assertNoContent();

    expect(app(TelegramLinkManager::class)->isConnected($user))->toBeFalse();
    Http::assertNothingSent();
});

it('tells a group its chat id', function (): void {
    postUpdate(telegramUpdate('/chatid@notify_rules_test_bot', chatType: 'supergroup'))->assertNoContent();

    Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '-100123'
        && $request['text'] === 'Chat ID: <code>-100123</code>');
});

it('ignores other messages and updates', function (): void {
    postUpdate(telegramUpdate('hello'))->assertNoContent();
    postUpdate(['update_id' => 1, 'edited_message' => []])->assertNoContent();

    Http::assertNothingSent();
});

it('rejects requests with a wrong secret', function (?string $secret): void {
    $user = User::factory()->create();
    $token = app(TelegramLinkManager::class)->createLinkToken($user);

    postUpdate(telegramUpdate("/start {$token}"), $secret)->assertForbidden();

    expect(app(TelegramLinkManager::class)->isConnected($user))->toBeFalse();
    Http::assertNothingSent();
})->with([
    'wrong secret' => 'nope',
    'missing header' => null,
]);

it('rejects everything when no secret is configured', function (): void {
    config()->set('notify-rules.telegram.webhook_secret', null);

    postUpdate(telegramUpdate('/chatid'), '')->assertForbidden();

    Http::assertNothingSent();
});

it('does not require a CSRF token or session', function (): void {
    $route = app('router')->getRoutes()->getByName('notify-rules.telegram.webhook');

    expect($route->gatherMiddleware())->not->toContain('web');
});
