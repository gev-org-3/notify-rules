<?php

declare(strict_types=1);

use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Jobs\SendTelegramMessage;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\TelegramLink;
use Orion\NotifyRules\Telegram\TelegramClient;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

function makeDelivery(string $chatId = '100'): Delivery
{
    return Delivery::create([
        'chat_id' => $chatId,
        'message' => '<b>New order</b>',
    ]);
}

function runJob(Delivery $delivery): SendTelegramMessage
{
    $job = (new SendTelegramMessage($delivery->fresh()))->withFakeQueueInteractions();
    $job->handle(app(TelegramClient::class));

    return $job;
}

function telegramError(int $code, string $description, array $parameters = []): array
{
    return array_filter([
        'ok' => false,
        'error_code' => $code,
        'description' => $description,
        'parameters' => $parameters ?: null,
    ]);
}

it('marks the delivery as sent', function (): void {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]])]);
    $delivery = makeDelivery();

    runJob($delivery)->assertNotReleased();

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->telegram_message_id)->toBe('555')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->error)->toBeNull();
});

it('does not send a delivery twice', function (): void {
    Http::fake();
    $delivery = makeDelivery();
    $delivery->markAsSent('1');

    runJob($delivery);

    Http::assertNothingSent();
});

it('waits for retry_after on 429 without using up an attempt', function (): void {
    Http::fake(['*' => Http::response(telegramError(429, 'Too Many Requests: retry after 7', ['retry_after' => 7]), 429)]);
    $delivery = makeDelivery();

    runJob($delivery)->assertReleased(delay: 7);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(0)
        ->and($delivery->error)->toBe('Too Many Requests: retry after 7');
});

it('fails without retrying and deactivates the chat on 403', function (): void {
    Http::fake(['*' => Http::response(telegramError(403, 'Forbidden: bot was blocked by the user'), 403)]);
    $user = User::factory()->create();
    $link = TelegramLink::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'chat_id' => '100',
        'is_active' => true,
    ]);
    $delivery = makeDelivery('100');

    runJob($delivery)->assertNotReleased();

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->error)->toBe('Forbidden: bot was blocked by the user')
        ->and($link->refresh()->is_active)->toBeFalse();
});

it('fails without retrying on other client errors', function (): void {
    Http::fake(['*' => Http::response(telegramError(400, 'Bad Request: chat not found'), 400)]);
    $delivery = makeDelivery();

    runJob($delivery)->assertNotReleased();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error)->toBe('Bad Request: chat not found');
});

it('retries server errors with exponential back-off and gives up after 5 attempts', function (): void {
    Http::fake(['*' => Http::response(telegramError(502, 'Bad Gateway'), 502)]);
    $delivery = makeDelivery();

    foreach (SendTelegramMessage::BACKOFF as $attempt => $delay) {
        runJob($delivery)->assertReleased(delay: $delay);

        expect($delivery->refresh()->status)->toBe(DeliveryStatus::Pending)
            ->and($delivery->attempts)->toBe($attempt + 1);
    }

    runJob($delivery)->assertNotReleased();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe(SendTelegramMessage::MAX_ATTEMPTS)
        ->and($delivery->error)->toBe('Bad Gateway');

    expect(SendTelegramMessage::BACKOFF)->toBe([10, 60, 300, 900]);
    Http::assertSentCount(SendTelegramMessage::MAX_ATTEMPTS);
});

it('succeeds on a retry after a server error', function (): void {
    Http::fakeSequence()
        ->push(telegramError(500, 'Internal Server Error'), 500)
        ->push(['ok' => true, 'result' => ['message_id' => 9]]);
    $delivery = makeDelivery();

    runJob($delivery)->assertReleased(delay: 10);
    runJob($delivery)->assertNotReleased();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->attempts)->toBe(2)
        ->and($delivery->error)->toBeNull();
});

it('marks the delivery as failed when the job fails', function (): void {
    $delivery = makeDelivery();

    (new SendTelegramMessage($delivery))->failed(new RuntimeException('Timed out'));

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error)->toBe('The delivery job failed or timed out.');
});

it('limits the rate globally and per chat', function (): void {
    $job = new SendTelegramMessage(makeDelivery('100'));

    expect($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);

    $limits = RateLimiter::limiter(SendTelegramMessage::RATE_LIMITER)($job);

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(25)
        ->and($limits[0]->decaySeconds)->toBe(1)
        ->and($limits[1]->maxAttempts)->toBe(1)
        ->and($limits[1]->key)->toBe('chat:100');
});

it('releases a second message to the same chat within one second', function (): void {
    $middleware = new RateLimited(SendTelegramMessage::RATE_LIMITER);
    $sent = 0;

    $first = (new SendTelegramMessage(makeDelivery('100')))->withFakeQueueInteractions();
    $second = (new SendTelegramMessage(makeDelivery('100')))->withFakeQueueInteractions();
    $otherChat = (new SendTelegramMessage(makeDelivery('200')))->withFakeQueueInteractions();

    foreach ([$first, $second, $otherChat] as $job) {
        $middleware->handle($job, function () use (&$sent): void {
            $sent++;
        });
    }

    expect($sent)->toBe(2);
    $first->assertNotReleased();
    $second->assertReleased();
    $otherChat->assertNotReleased();
});

it('is queued on the configured connection after commit', function (): void {
    config()->set('notify-rules.queue.connection', 'redis');
    config()->set('notify-rules.queue.name', 'telegram');

    $job = new SendTelegramMessage(makeDelivery());

    expect($job->connection)->toBe('redis')
        ->and($job->queue)->toBe('telegram')
        ->and($job->afterCommit)->toBeTrue()
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addHours(5)->getTimestamp());
});

it('sends every message on the sync queue, which cannot release rate-limited jobs', function (): void {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    $first = makeDelivery('100');
    $second = makeDelivery('100');

    SendTelegramMessage::dispatchSync($first);
    SendTelegramMessage::dispatchSync($second);

    expect($first->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($second->fresh()->status)->toBe(DeliveryStatus::Sent);
});

it('fails instead of retrying later on the sync queue', function (array $response, int $status): void {
    Http::fake(['*' => Http::response($response, $status)]);
    $delivery = makeDelivery();

    SendTelegramMessage::dispatchSync($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error)->not->toBeEmpty();
})->with([
    '429' => [telegramError(429, 'Too Many Requests: retry after 5', ['retry_after' => 5]), 429],
    '500' => [telegramError(500, 'Internal Server Error'), 500],
]);
