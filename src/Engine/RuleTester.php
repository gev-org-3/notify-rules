<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Engine;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OrionSoft\NotifyRules\Jobs\SendTelegramMessage;
use OrionSoft\NotifyRules\Models\Delivery;
use OrionSoft\NotifyRules\Models\NotificationRule;
use OrionSoft\NotifyRules\Support\Tenancy;
use OrionSoft\NotifyRules\Telegram\TelegramLinkManager;

/**
 * Previews a rule on the latest record of its model and sends test messages to the current user.
 */
final class RuleTester
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly TelegramLinkManager $links,
        private readonly Tenancy $tenancy,
        private readonly ModelEventRegistrar $registrar,
    ) {}

    /**
     * @param  class-string<Model>  $modelType
     */
    public function sampleRecord(string $modelType): ?Model
    {
        /** @var Model $model */
        $model = new $modelType;
        $query = $modelType::query()->latest($model->qualifyColumn($model->getKeyName()));

        // In a tenant panel the preview must only ever show the current tenant's records.
        $tenant = $this->tenancy->enabled() ? Filament::getTenant() : null;

        if ($tenant !== null) {
            $relationship = $this->registrar->tenantRelationship($modelType);

            if ($relationship === null) {
                return null;
            }

            $query->whereHas($relationship, fn (Builder $query): Builder => $query->whereKey($tenant->getKey()));
        }

        return $query->first();
    }

    /**
     * Renders the template on the latest record, or on an empty one when the table has no records yet.
     */
    public function preview(NotificationRule $rule): string
    {
        return $this->renderer->render(
            $rule->template,
            $this->sampleRecord($rule->model_type) ?? new $rule->model_type,
            $rule,
        );
    }

    /**
     * Sends the preview to the user's Telegram right away, bypassing the queue so the result can be shown.
     * Returns null when the user has not connected Telegram.
     */
    public function sendTo(NotificationRule $rule, Model $user): ?Delivery
    {
        $link = $this->links->linkFor($user);

        if ($link === null || ! $link->is_active || $link->chat_id === null) {
            return null;
        }

        $record = $this->sampleRecord($rule->model_type);

        $delivery = Delivery::query()->create($this->tenancy->withCurrentKey([
            'rule_id' => $rule->exists ? $rule->getKey() : null,
            'chat_id' => $link->chat_id,
            'subject_type' => $record?->getMorphClass(),
            'subject_id' => $record?->getKey(),
            'message' => $this->preview($rule),
        ]));

        SendTelegramMessage::dispatchSync($delivery);

        return $delivery->refresh();
    }
}
