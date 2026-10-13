<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Orion\NotifyRules\Enums\DeliveryStatus;

/**
 * @property int $id
 * @property int|null $rule_id
 * @property string $channel
 * @property string $chat_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $message
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property string|null $telegram_message_id
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Delivery extends Model
{
    public const CHANNEL_TELEGRAM = 'telegram';

    protected $guarded = [];

    protected $attributes = [
        'channel' => self::CHANNEL_TELEGRAM,
        'status' => 'pending',
        'attempts' => 0,
    ];

    public function getTable(): string
    {
        return config('notify-rules.tables.deliveries', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NotificationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'rule_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function markAsSent(string $telegramMessageId): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Sent,
            'telegram_message_id' => $telegramMessageId,
            'error' => null,
            'sent_at' => now(),
        ])->save();
    }

    public function markAsFailed(string $error): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Failed,
            'error' => $error,
        ])->save();
    }

    public function recordError(string $error): void
    {
        $this->forceFill(['error' => $error])->save();
    }
}
