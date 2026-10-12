<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final readonly class HttpTelegramClient implements TelegramClient
{
    private const REQUEST_TIMEOUT = 15;

    public function __construct(
        #[\SensitiveParameter]
        private ?string $token,
        private string $apiUrl = 'https://api.telegram.org',
    ) {}

    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        /** @var array<string, mixed> */
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            ...$options,
        ]);
    }

    public function setWebhook(string $url, ?string $secretToken = null, array $allowedUpdates = ['message']): bool
    {
        return (bool) $this->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => $allowedUpdates,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return (bool) $this->call('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    public function getWebhookInfo(): array
    {
        /** @var array<string, mixed> */
        return $this->call('getWebhookInfo');
    }

    public function getUpdates(?int $offset = null, int $timeout = 30, array $allowedUpdates = ['message']): array
    {
        /** @var array<int, array<string, mixed>> */
        return $this->call('getUpdates', array_filter([
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => $allowedUpdates,
        ], fn (mixed $value): bool => $value !== null), httpTimeout: $timeout + self::REQUEST_TIMEOUT);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws TelegramException
     */
    private function call(string $method, array $parameters = [], int $httpTimeout = self::REQUEST_TIMEOUT): mixed
    {
        if ($this->token === null || $this->token === '') {
            throw TelegramException::missingToken();
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($httpTimeout)
                ->post(rtrim($this->apiUrl, '/') . "/bot{$this->token}/{$method}", $parameters);
        } catch (ConnectionException) {
            // The original message contains the request URL, and with it the bot token.
            throw TelegramException::connectionFailed();
        }

        $body = $response->json();
        $body = is_array($body) ? $body : null;

        if (! $response->successful() || ($body['ok'] ?? false) !== true) {
            throw TelegramException::fromResponse($response->status(), $body);
        }

        return $body['result'] ?? null;
    }
}
