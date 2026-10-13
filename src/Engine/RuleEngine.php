<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

use Illuminate\Database\Eloquent\Model;
use Orion\NotifyRules\Enums\RuleEvent;
use Orion\NotifyRules\Jobs\SendTelegramMessage;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\Support\Tenancy;
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
        private readonly ModelEventRegistrar $registrar,
        private readonly Tenancy $tenancy,
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

        foreach ($this->rulesFor($model) as $rule) {
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

    /**
     * The active rules of the model's class. With tenancy, a record only runs its own tenant's rules and the
     * global ones. If the tenant of the record cannot be determined, only global rules run: a rule of one
     * tenant must never see another tenant's data.
     *
     * @return iterable<int, NotificationRule>
     */
    private function rulesFor(Model $model): iterable
    {
        $rules = $this->rules->activeFor($model::class);

        if (! $this->tenancy->enabled()) {
            return $rules;
        }

        $relationship = $this->registrar->tenantRelationship($model::class);
        $tenantKey = $relationship === null ? null : $this->tenancy->keyOfRecord($model, $relationship);

        return $rules->filter(function (NotificationRule $rule) use ($tenantKey): bool {
            $ruleTenant = $this->tenancy->keyOf($rule);

            return $ruleTenant === null || ($tenantKey !== null && (string) $ruleTenant === (string) $tenantKey);
        });
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
            $delivery = Delivery::query()->create($this->withTenant($rule, [
                'rule_id' => $rule->getKey(),
                'chat_id' => $chatId,
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'message' => $message,
            ]));

            dispatch(new SendTelegramMessage($delivery));

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withTenant(NotificationRule $rule, array $attributes): array
    {
        if ($this->tenancy->enabled()) {
            $attributes[(string) $this->tenancy->column()] = $this->tenancy->keyOf($rule);
        }

        return $attributes;
    }

    private function onlyTimestampsChanged(Model $model): bool
    {
        $changes = array_keys($model->getChanges());

        return $changes !== [] && array_diff($changes, array_filter([$model->getUpdatedAtColumn()])) === [];
    }
}
