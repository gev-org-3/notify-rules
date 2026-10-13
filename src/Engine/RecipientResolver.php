<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Engine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use OrionSoft\NotifyRules\Engine\Roles\RoleUserResolver;
use OrionSoft\NotifyRules\Models\NotificationRule;
use OrionSoft\NotifyRules\Models\TelegramLink;

final class RecipientResolver
{
    public function __construct(private readonly RoleUserResolver $roles) {}

    /**
     * Unique Telegram chat IDs to notify: the active links of the rule's users and of users with
     * the rule's roles, plus the chat IDs entered directly (groups, channels).
     *
     * @return array<int, string>
     */
    public function resolve(NotificationRule $rule): array
    {
        $recipients = $rule->recipients ?? [];

        $roles = $this->list($recipients['roles'] ?? []);

        $userIds = array_merge(
            $this->list($recipients['users'] ?? []),
            $roles === [] ? [] : $this->roles->userIds($roles),
        );

        $chatIds = array_merge(
            $this->linkedChatIds($userIds),
            array_filter($this->list($recipients['chat_ids'] ?? []), $this->isChatId(...)),
        );

        return array_values(array_unique(array_map('strval', $chatIds)));
    }

    public function isChatId(string $chatId): bool
    {
        // Numeric IDs (negative for groups) or a public channel username.
        return (bool) preg_match('/^(-?\d{1,20}|@[A-Za-z][A-Za-z0-9_]{3,31})$/', $chatId);
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return array<int, string>
     */
    private function linkedChatIds(array $userIds): array
    {
        $userModel = config('notify-rules.user_model');

        if ($userIds === [] || ! is_string($userModel) || ! is_subclass_of($userModel, Model::class)) {
            return [];
        }

        /** @var Model $user */
        $user = new $userModel;

        return TelegramLink::query()
            ->whereIn('notifiable_type', array_unique([$user->getMorphClass(), Relation::getMorphAlias($userModel), $userModel]))
            ->whereIn('notifiable_id', array_unique($userIds))
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->pluck('chat_id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function list(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $values = array_map(static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $values);

        return array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
    }
}
