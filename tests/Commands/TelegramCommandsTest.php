<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orion\NotifyRules\Telegram\TelegramLinkManager;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

function ok(mixed $result): array
{
    return ['ok' => true, 'result' => $result];
}

it('sets the webhook with the secret', function (): void {
    Http::fake(['*/setWebhook' => Http::response(ok(true))]);

    $this->artisan('notify-rules:webhook', ['action' => 'set', '--url' => 'https://example.com/hook'])
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/setWebhook')
        && $request['url'] === 'https://example.com/hook'
        && $request['secret_token'] === 'test-secret');
});

it('defaults the webhook URL to the package route', function (): void {
    Http::fake(['*/setWebhook' => Http::response(ok(true))]);

    $this->artisan('notify-rules:webhook set')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request['url'] === route('notify-rules.telegram.webhook')
        && str_ends_with($request['url'], '/notify-rules/telegram/webhook'));
});

it('refuses to set a webhook without a secret', function (): void {
    config()->set('notify-rules.telegram.webhook_secret', null);
    Http::fake();

    $this->artisan('notify-rules:webhook set --url=https://example.com/hook')->assertFailed();

    Http::assertNothingSent();
});

it('deletes the webhook and shows its info', function (): void {
    Http::fake([
        '*/deleteWebhook' => Http::response(ok(true)),
        '*/getWebhookInfo' => Http::response(ok(['url' => 'https://example.com/hook', 'pending_update_count' => 3])),
    ]);

    $this->artisan('notify-rules:webhook delete')->assertSuccessful();
    $this->artisan('notify-rules:webhook info')
        ->expectsOutputToContain('https://example.com/hook')
        ->assertSuccessful();
});

it('reports Telegram errors', function (): void {
    Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)]);

    $this->artisan('notify-rules:webhook info')
        ->expectsOutputToContain('Unauthorized')
        ->assertFailed();
});

it('links a chat through long polling', function (): void {
    $user = User::factory()->create();
    $token = app(TelegramLinkManager::class)->createLinkToken($user);

    Http::fake([
        '*/getWebhookInfo' => Http::response(ok(['url' => ''])),
        '*/getUpdates' => Http::sequence()
            ->push(ok([[
                'update_id' => 41,
                'message' => [
                    'message_id' => 1,
                    'from' => ['id' => 777, 'username' => 'john'],
                    'chat' => ['id' => 777, 'type' => 'private'],
                    'text' => "/start {$token}",
                ],
            ]]))
            ->push(ok([])),
        '*/sendMessage' => Http::response(ok(['message_id' => 2])),
    ]);

    $this->artisan('notify-rules:poll --once --timeout=0')->assertSuccessful();

    expect(app(TelegramLinkManager::class)->linkFor($user)->chat_id)->toBe('777');

    // The processed update is confirmed so Telegram does not send it again.
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/getUpdates')
        && ($request->data()['offset'] ?? null) === 42);
});

it('refuses to poll while a webhook is set', function (): void {
    Http::fake(['*/getWebhookInfo' => Http::response(ok(['url' => 'https://example.com/hook']))]);

    $this->artisan('notify-rules:poll --once')
        ->expectsOutputToContain('notify-rules:webhook delete')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/getUpdates'));
});
