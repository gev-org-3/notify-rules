<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

use Illuminate\Database\Eloquent\Model;
use Orion\NotifyRules\Enums\RuleEvent;
use Orion\NotifyRules\Jobs\SendTelegramMessage;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\NotificationRule;
use Throwable;

/**
 * Runs the rules of a model for one Eloquent event: picks the matching rules, renders the message and
 * queues one delivery per recipient. The jobs are dispatched after the surrounding transaction commits.
 */
final class RuleEngine
{
    public function __construct(
        private readonly RuleRepository $rules,
        private readonly ConditionEvaluator $conditions,
        private readonly RecipientResolver $recipients,
        private readonly TemplateRenderer $renderer,
        private readonly ModelFields $fields,
    ) {}

    /**
     * @return array<int, Delivery>
     */
    public function handle(string $eloquentEvent, Model $model): array
    {
        if ($eloquentEvent === 'updated' && $this->onlyTimestampsChanged($model)) {
            return [];
        }

        $deliveries = [];

        foreach ($this->rules->activeFor($model::class) as $rule) {
            // A broken rule must never break saving the model it watches.
            try {
                if ($this->matches($rule, $eloquentEvent, $model)) {
                    array_push($deliveries, ...$this->dispatch($rule, $model));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $deliveries;
    }

    public function matches(NotificationRule $rule, string $eloquentEvent, Model $model): bool
    {
        if ($rule->event->eloquentEvent() !== $eloquentEvent) {
            return false;
        }

        if ($rule->event === RuleEvent::FieldChanged
            && ! $this->fields->wasChanged($model, (string) $rule->watched_field)) {
            return false;
        }

        return $this->conditions->passes($rule, $model);
    }

    /**
     * @return array<int, Delivery>
     */
    private function dispatch(NotificationRule $rule, Model $model): array
    {
        $chatIds = $this->recipients->resolve($rule);

        if ($chatIds === []) {
            return [];
        }

        $message = $this->renderer->render($rule->template, $model, $rule);

        if (trim(strip_tags($message)) === '') {
            return [];
        }

        $deliveries = [];

        foreach ($chatIds as $chatId) {
            $delivery = Delivery::query()->create([
                'rule_id' => $rule->getKey(),
                'chat_id' => $chatId,
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'message' => $message,
            ]);

            dispatch(new SendTelegramMessage($delivery));

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    private function onlyTimestampsChanged(Model $model): bool
    {
        $changes = array_keys($model->getChanges());

        return $changes !== [] && array_diff($changes, array_filter([$model->getUpdatedAtColumn()])) === [];
    }
}
