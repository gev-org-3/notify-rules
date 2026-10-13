<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

use Illuminate\Database\Eloquent\Model;
use Orion\NotifyRules\Jobs\SendTelegramMessage;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\Telegram\TelegramLinkManager;

/**
 * Previews a rule on the latest record of its model and sends test messages to the current user.
 */
final class RuleTester
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly TelegramLinkManager $links,
    ) {}

    /**
     * @param  class-string<Model>  $modelType
     */
    public function sampleRecord(string $modelType): ?Model
    {
        /** @var Model $model */
        $model = new $modelType;

        return $modelType::query()->latest($model->getKeyName())->first();
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

        $delivery = Delivery::query()->create([
            'rule_id' => $rule->exists ? $rule->getKey() : null,
            'chat_id' => $link->chat_id,
            'subject_type' => $record?->getMorphClass(),
            'subject_id' => $record?->getKey(),
            'message' => $this->preview($rule),
        ]);

        SendTelegramMessage::dispatchSync($delivery);

        return $delivery->refresh();
    }
}
