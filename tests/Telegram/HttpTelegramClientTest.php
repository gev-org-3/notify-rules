<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OrionSoft\NotifyRules\Telegram\HttpTelegramClient;
use OrionSoft\NotifyRules\Telegram\TelegramClient;
use OrionSoft\NotifyRules\Telegram\TelegramException;

it('is bound in the container', function (): void {
    expect(app(TelegramClient::class))->toBeInstanceOf(HttpTelegramClient::class);
});

it('sends an HTML message', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]]),
    ]);

    $message = app(TelegramClient::class)->sendMessage('100', '<b>Hi</b>');

    expect($message)->toBe(['message_id' => 42]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
        && $request['chat_id'] === '100'
        && $request['text'] === '<b>Hi</b>'
        && $request['parse_mode'] === 'HTML'
        && $request['link_preview_options'] === ['is_disabled' => true]);
});

it('calls the webhook and polling methods', function (): void {
    Http::fake([
        '*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => 'https://example.com/hook']]),
        '*/getUpdates' => Http::response(['ok' => true, 'result' => [['update_id' => 1]]]),
    ]);

    $client = app(TelegramClient::class);

    expect($client->setWebhook('https://example.com/hook', 'secret'))->toBeTrue()
        ->and($client->deleteWebhook())->toBeTrue()
        ->and($client->getWebhookInfo())->toBe(['url' => 'https://example.com/hook'])
        ->and($client->getUpdates(offset: 5, timeout: 0))->toBe([['update_id' => 1]]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/setWebhook')
        && $request['secret_token'] === 'secret'
        && $request['allowed_updates'] === ['message']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/getUpdates')
        && $request['offset'] === 5);
});

it('turns API errors into exceptions', function (): void {
    Http::fake([
        '*' => Http::response([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests: retry after 7',
            'parameters' => ['retry_after' => 7],
        ], 429),
    ]);

    try {
        app(TelegramClient::class)->sendMessage('100', 'Hi');
        $this->fail('Expected a TelegramException.');
    } catch (TelegramException $e) {
        expect($e->errorCode)->toBe(429)
            ->and($e->retryAfter)->toBe(7)
            ->and($e->isRateLimited())->toBeTrue()
            ->and($e->getMessage())->toBe('Too Many Requests: retry after 7');
    }
});

it('handles a non-JSON error response', function (): void {
    Http::fake(['*' => Http::response('Bad Gateway', 502)]);

    expect(fn () => app(TelegramClient::class)->sendMessage('100', 'Hi'))
        ->toThrow(fn (TelegramException $e) => expect($e->errorCode)->toBe(502)->and($e->isTransient())->toBeTrue());
});

it('never leaks the bot token in connection errors', function (): void {
    Http::fake(fn () => throw new ConnectionException(
        'cURL error 28: timed out for https://api.telegram.org/bottest-token/sendMessage'
    ));

    try {
        app(TelegramClient::class)->sendMessage('100', 'Hi');
        $this->fail('Expected a TelegramException.');
    } catch (TelegramException $e) {
        expect($e->getMessage())->not->toContain('test-token')
            ->and($e->getPrevious())->toBeNull()
            ->and($e->isTransient())->toBeTrue();
    }
});

it('requires a bot token', function (): void {
    config()->set('notify-rules.telegram.bot_token', null);
    Http::fake();

    expect(fn () => app(TelegramClient::class)->sendMessage('100', 'Hi'))
        ->toThrow(TelegramException::class);

    Http::assertNothingSent();
});
