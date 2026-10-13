<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Filament\Resources\NotificationRules\Pages\Concerns;

use OrionSoft\NotifyRules\Enums\RuleEvent;
use OrionSoft\NotifyRules\Models\NotificationRule;

trait NormalizesRuleData
{
    /**
     * Stores conditions and recipients in the shape the engine reads, without empty leftovers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeRuleData(array $data): array
    {
        $event = $data['event'] ?? null;
        $event = $event instanceof RuleEvent ? $event : RuleEvent::tryFrom((string) $event);

        if ($event !== RuleEvent::FieldChanged) {
            $data['watched_field'] = null;
        }

        $conditions = is_array($data['conditions'] ?? null) ? $data['conditions'] : [];
        $items = array_values(array_filter(
            is_array($conditions['items'] ?? null) ? $conditions['items'] : [],
            static fn (mixed $item): bool => is_array($item) && filled($item['field'] ?? null) && filled($item['operator'] ?? null),
        ));

        $data['conditions'] = [
            'match' => ($conditions['match'] ?? null) === NotificationRule::MATCH_ANY ? NotificationRule::MATCH_ANY : NotificationRule::MATCH_ALL,
            'items' => $items,
        ];

        $recipients = is_array($data['recipients'] ?? null) ? $data['recipients'] : [];

        $data['recipients'] = [
            // Keys are kept as they come: user models may use UUIDs.
            'users' => array_values(array_filter((array) ($recipients['users'] ?? []), static fn (mixed $id): bool => is_scalar($id) && $id !== '')),
            'roles' => array_values(array_filter(array_map('trim', (array) ($recipients['roles'] ?? [])))),
            'chat_ids' => array_values(array_unique(array_filter(array_map('trim', (array) ($recipients['chat_ids'] ?? []))))),
        ];

        return $data;
    }
}
