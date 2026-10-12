<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Telegram;

interface TelegramClient
{
    /**
     * @param  array<string, mixed>  $options  Extra sendMessage parameters.
     * @return array<string, mixed> The sent Message object.
     *
     * @throws TelegramException
     */
    public function sendMessage(string $chatId, string $text, array $options = []): array;

    /**
     * @param  array<int, string>  $allowedUpdates
     *
     * @throws TelegramException
     */
    public function setWebhook(string $url, ?string $secretToken = null, array $allowedUpdates = ['message']): bool;

    /**
     * @throws TelegramException
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): bool;

    /**
     * @return array<string, mixed>
     *
     * @throws TelegramException
     */
    public function getWebhookInfo(): array;

    /**
     * @param  array<int, string>  $allowedUpdates
     * @return array<int, array<string, mixed>>
     *
     * @throws TelegramException
     */
    public function getUpdates(?int $offset = null, int $timeout = 30, array $allowedUpdates = ['message']): array;
}
