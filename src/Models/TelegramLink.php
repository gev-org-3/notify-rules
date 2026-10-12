<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property string|null $chat_id
 * @property string|null $username
 * @property Carbon|null $linked_at
 * @property bool $is_active
 * @property string|null $link_token
 * @property Carbon|null $link_token_expires_at
 */
final class TelegramLink extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'link_token',
    ];

    public function getTable(): string
    {
        return config('notify-rules.tables.telegram_links', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'linked_at' => 'datetime',
            'link_token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Telegram told us this chat is unreachable (bot blocked, chat deleted).
     */
    public static function deactivateChat(string $chatId): void
    {
        self::query()
            ->where('chat_id', $chatId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
