<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Orion\NotifyRules\Models\TelegramLink;

/**
 * Connects users to Telegram chats through one-time "/start {token}" deep links.
 * Only a hash of the token is stored, so a leaked database row cannot be used to hijack a link.
 */
final class TelegramLinkManager
{
    public function createLinkToken(Model $user): string
    {
        $token = Str::random(40);

        TelegramLink::query()->updateOrCreate(
            [
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
            ],
            [
                'link_token' => $this->hash($token),
                'link_token_expires_at' => now()->addMinutes((int) config('notify-rules.telegram.link_token_ttl', 15)),
            ],
        );

        return $token;
    }

    public function deepLink(string $token): string
    {
        $username = ltrim((string) config('notify-rules.telegram.bot_username'), '@');

        if ($username === '') {
            throw new LogicException('The Telegram bot username is not configured (NOTIFY_RULES_TELEGRAM_BOT_USERNAME).');
        }

        return "https://t.me/{$username}?start={$token}";
    }

    /**
     * Returns null when the token is unknown, already used or expired.
     */
    public function complete(string $token, string $chatId, ?string $username): ?TelegramLink
    {
        if ($token === '') {
            return null;
        }

        $link = TelegramLink::query()->where('link_token', $this->hash($token))->first();

        if ($link === null) {
            return null;
        }

        if ($link->link_token_expires_at === null || $link->link_token_expires_at->isPast()) {
            $link->forceFill(['link_token' => null, 'link_token_expires_at' => null])->save();

            return null;
        }

        $link->forceFill([
            'chat_id' => $chatId,
            'username' => $username,
            'linked_at' => now(),
            'is_active' => true,
            'link_token' => null,
            'link_token_expires_at' => null,
        ])->save();

        return $link;
    }

    public function linkFor(Model $user): ?TelegramLink
    {
        return TelegramLink::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->first();
    }

    public function isConnected(Model $user): bool
    {
        $link = $this->linkFor($user);

        return $link !== null && $link->is_active && $link->chat_id !== null;
    }

    public function disconnect(Model $user): void
    {
        $this->linkFor($user)?->forceFill([
            'chat_id' => null,
            'username' => null,
            'linked_at' => null,
            'is_active' => false,
            'link_token' => null,
            'link_token_expires_at' => null,
        ])->save();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
