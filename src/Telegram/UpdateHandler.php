<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Telegram;

/**
 * Handles incoming bot updates, whether they arrive through the webhook or long polling.
 *
 *  - "/start {token}" in a private chat links the chat to the user who generated the token.
 *  - "/chatid" replies with the chat id, so a group can be added to a rule.
 */
final readonly class UpdateHandler
{
    public function __construct(
        private TelegramClient $telegram,
        private TelegramLinkManager $links,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! is_array($message) || ! is_string($message['text'] ?? null) || ! isset($message['chat']['id'])) {
            return;
        }

        [$command, $argument] = $this->parseCommand($message['text']);
        $chatId = (string) $message['chat']['id'];
        $locale = $this->locale($message['from']['language_code'] ?? null);

        match ($command) {
            '/start' => $this->start($message, $chatId, $argument, $locale),
            '/chatid' => $this->reply($chatId, __('notify-rules::notify-rules.bot.chat_id', [
                'id' => e($chatId),
            ], $locale)),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function start(array $message, string $chatId, string $token, string $locale): void
    {
        if (($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        if ($token === '') {
            $this->reply($chatId, __('notify-rules::notify-rules.bot.start_without_token', [], $locale));

            return;
        }

        $username = $message['from']['username'] ?? null;
        $link = $this->links->complete($token, $chatId, is_string($username) ? $username : null);

        $this->reply($chatId, __(
            $link === null ? 'notify-rules::notify-rules.bot.link_invalid' : 'notify-rules::notify-rules.bot.connected',
            [],
            $locale,
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseCommand(string $text): array
    {
        if (! str_starts_with($text, '/')) {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', trim($text), 2) ?: [''];

        // In groups commands may be addressed to a bot: "/chatid@my_bot".
        $command = strtolower(explode('@', $parts[0], 2)[0]);

        return [$command, trim($parts[1] ?? '')];
    }

    private function locale(mixed $languageCode): string
    {
        if (is_string($languageCode) && str_starts_with(strtolower($languageCode), 'ru')) {
            return 'ru';
        }

        return app()->getLocale() === 'ru' ? 'ru' : 'en';
    }

    private function reply(string $chatId, string $text): void
    {
        try {
            $this->telegram->sendMessage($chatId, $text);
        } catch (TelegramException $e) {
            // The update itself was handled; a failed reply must not make Telegram resend it.
            report($e);
        }
    }
}
