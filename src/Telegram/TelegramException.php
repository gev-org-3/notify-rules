<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Telegram;

use RuntimeException;

/**
 * Messages never contain the bot token or the request URL, so they are safe to store and log.
 */
final class TelegramException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly int $errorCode,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function fromResponse(int $httpStatus, ?array $body): self
    {
        $code = is_int($body['error_code'] ?? null) ? $body['error_code'] : $httpStatus;
        $description = is_string($body['description'] ?? null) ? $body['description'] : "HTTP {$httpStatus}";
        $retryAfter = $body['parameters']['retry_after'] ?? null;

        return new self($description, $code, is_int($retryAfter) ? $retryAfter : null);
    }

    public static function connectionFailed(): self
    {
        return new self('Could not connect to the Telegram Bot API.', 0);
    }

    public static function missingToken(): self
    {
        return new self('The Telegram bot token is not configured (NOTIFY_RULES_TELEGRAM_BOT_TOKEN).', 0);
    }

    public function isRateLimited(): bool
    {
        return $this->errorCode === 429;
    }

    /**
     * The bot was blocked, kicked from the group, or the user deleted their account.
     */
    public function isForbidden(): bool
    {
        return $this->errorCode === 403;
    }

    /**
     * Network problems and Telegram server errors are worth retrying; everything else is permanent.
     */
    public function isTransient(): bool
    {
        return $this->errorCode === 0 || $this->errorCode >= 500;
    }
}
