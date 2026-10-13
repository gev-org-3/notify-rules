<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\NotificationRules\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Orion\NotifyRules\Engine\RuleTester;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Filament\Pages\TelegramConnection;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Schemas\NotificationRuleForm;
use Orion\NotifyRules\Models\NotificationRule;

/**
 * Renders the form's template on the latest record and sends it to the current user's Telegram,
 * without saving the rule.
 */
final class SendTestMessageAction
{
    private const T = 'notify-rules::notify-rules.rules.test_message.';

    public static function make(): Action
    {
        return Action::make('sendTestMessage')
            ->label(__(self::T . 'label'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->action(function (CreateRecord | EditRecord $livewire): void {
                $rule = NotificationRuleForm::ruleFromState($livewire->data ?? []);

                if ($rule === null) {
                    self::notify('warning', 'incomplete');

                    return;
                }

                if ($livewire instanceof EditRecord) {
                    $rule = self::onSavedRule($livewire->getRecord(), $rule);
                }

                $tester = app(RuleTester::class);

                if (trim(strip_tags($tester->preview($rule))) === '') {
                    self::notify('warning', 'empty');

                    return;
                }

                $user = Filament::auth()->user();
                $delivery = $user instanceof Model ? $tester->sendTo($rule, $user) : null;

                if ($delivery === null) {
                    Notification::make()
                        ->warning()
                        ->title(__(self::T . 'not_connected'))
                        ->actions([
                            Action::make('connect')
                                ->label(__('notify-rules::notify-rules.telegram.page.actions.connect'))
                                ->url(TelegramConnection::getUrl()),
                        ])
                        ->send();

                    return;
                }

                if ($delivery->status === DeliveryStatus::Sent) {
                    self::notify('success', 'sent');

                    return;
                }

                Notification::make()
                    ->danger()
                    ->title(__(self::T . 'failed'))
                    ->body($delivery->error)
                    ->send();
            });
    }

    /**
     * Keeps the saved rule's ID on the delivery, with the unsaved values from the form.
     */
    private static function onSavedRule(Model $record, NotificationRule $rule): NotificationRule
    {
        if (! $record instanceof NotificationRule) {
            return $rule;
        }

        return (clone $record)->fill($rule->only(['name', 'model_type', 'template']));
    }

    private static function notify(string $status, string $key): void
    {
        Notification::make()
            ->status($status)
            ->title(__(self::T . $key))
            ->send();
    }
}
