<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Jobs;

use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Middleware\RateLimited;
use OrionSoft\NotifyRules\Enums\DeliveryStatus;
use OrionSoft\NotifyRules\Models\Delivery;
use OrionSoft\NotifyRules\Models\TelegramLink;
use OrionSoft\NotifyRules\Telegram\TelegramClient;
use OrionSoft\NotifyRules\Telegram\TelegramException;
use Throwable;

/**
 * Attempts are counted on the delivery (calls that actually reached Telegram or failed on the network),
 * not by the queue: rate-limit releases and 429 back-offs must not use up the retries.
 */
final class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public const RATE_LIMITER = 'notify-rules-telegram';

    public const MAX_ATTEMPTS = 5;

    /** Seconds to wait before attempt 2, 3, 4 and 5. */
    public const BACKOFF = [10, 60, 300, 900];

    /** How long the job may keep being released (rate limits, 429, back-off) before the queue gives up. */
    public const RETRY_WINDOW_HOURS = 6;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Delivery $delivery)
    {
        $this->onConnection(config('notify-rules.queue.connection'));
        $this->onQueue(config('notify-rules.queue.name'));
        $this->afterCommit();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // A rate-limited job is released for later, which the sync queue cannot do: the message would be lost.
        if ($this->runsSynchronously()) {
            return [];
        }

        return [new RateLimited(self::RATE_LIMITER)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(self::RETRY_WINDOW_HOURS);
    }

    public function handle(TelegramClient $telegram): void
    {
        $delivery = $this->delivery;

        if ($delivery->status !== DeliveryStatus::Pending) {
            return;
        }

        try {
            $message = $telegram->sendMessage($delivery->chat_id, $delivery->message);
        } catch (TelegramException $e) {
            $this->handleTelegramError($delivery, $e);

            return;
        }

        $delivery->increment('attempts');
        $delivery->markAsSent((string) ($message['message_id'] ?? ''));
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = $this->delivery->fresh();

        if ($delivery?->status === DeliveryStatus::Pending) {
            $delivery->markAsFailed($exception instanceof TelegramException
                ? $exception->getMessage()
                : 'The delivery job failed or timed out.');
        }
    }

    private function handleTelegramError(Delivery $delivery, TelegramException $e): void
    {
        if ($e->isRateLimited()) {
            // Telegram refused to process the request, so it does not count as an attempt.
            $this->retryLater($delivery, $e->getMessage(), $e->retryAfter ?? self::BACKOFF[0]);

            return;
        }

        $delivery->increment('attempts');

        if ($e->isForbidden()) {
            $delivery->markAsFailed($e->getMessage());
            TelegramLink::deactivateChat($delivery->chat_id);

            return;
        }

        if ($e->isTransient() && $delivery->attempts < self::MAX_ATTEMPTS) {
            $this->retryLater($delivery, $e->getMessage(), self::BACKOFF[$delivery->attempts - 1] ?? self::BACKOFF[array_key_last(self::BACKOFF)]);

            return;
        }

        $delivery->markAsFailed($e->getMessage());
    }

    private function retryLater(Delivery $delivery, string $error, int $delay): void
    {
        // The sync queue cannot retry later, so the delivery fails now and can be resent from the delivery log.
        if ($this->runsSynchronously()) {
            $delivery->markAsFailed($error);

            return;
        }

        $delivery->recordError($error);
        $this->release($delay);
    }

    private function runsSynchronously(): bool
    {
        return $this->job instanceof SyncJob;
    }
}
