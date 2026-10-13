<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OrionSoft\NotifyRules\Engine\RuleRepository;
use OrionSoft\NotifyRules\Enums\RuleEvent;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property class-string<Model> $model_type
 * @property RuleEvent $event
 * @property string|null $watched_field
 * @property array{match?: string, items?: array<int, array{field?: string, operator?: string, value?: mixed}>}|null $conditions
 * @property array{users?: array<int, int|string>, roles?: array<int, string>, chat_ids?: array<int, string>}|null $recipients
 * @property string $template
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NotificationRule extends Model
{
    public const MATCH_ALL = 'all';

    public const MATCH_ANY = 'any';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];

    public function getTable(): string
    {
        return config('notify-rules.tables.rules', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'event' => RuleEvent::class,
            'conditions' => 'array',
            'recipients' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $forget = static function (self $rule): void {
            $repository = app(RuleRepository::class);
            $repository->forget($rule->model_type);

            $original = $rule->getOriginal('model_type');

            if (is_string($original) && $original !== $rule->model_type) {
                $repository->forget($original);
            }
        };

        self::saved($forget);
        self::deleted($forget);
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'rule_id');
    }

    public function matchesAll(): bool
    {
        return ($this->conditions['match'] ?? self::MATCH_ALL) !== self::MATCH_ANY;
    }

    /**
     * @return array<int, array{field?: string, operator?: string, value?: mixed}>
     */
    public function conditionItems(): array
    {
        return array_values($this->conditions['items'] ?? []);
    }
}
