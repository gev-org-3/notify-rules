<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use OrionSoft\NotifyRules\Enums\ConditionOperator;
use OrionSoft\NotifyRules\Enums\DeliveryStatus;
use OrionSoft\NotifyRules\Enums\RuleEvent;
use OrionSoft\NotifyRules\Filament\Pages\TelegramConnection;
use OrionSoft\NotifyRules\Filament\Resources\Deliveries\Pages\ListDeliveries;
use OrionSoft\NotifyRules\Filament\Resources\NotificationRules\Pages\CreateNotificationRule;
use OrionSoft\NotifyRules\Filament\Resources\NotificationRules\Pages\EditNotificationRule;
use OrionSoft\NotifyRules\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use OrionSoft\NotifyRules\Models\Delivery;
use OrionSoft\NotifyRules\Tests\Fixtures\Models\Order;
use OrionSoft\NotifyRules\Tests\Fixtures\Models\User;

function flattenKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $keys = is_array($value)
            ? [...$keys, ...flattenKeys($value, "{$prefix}{$key}.")]
            : [...$keys, "{$prefix}{$key}"];
    }

    return $keys;
}

it('has the same keys in every language', function (): void {
    $en = flattenKeys(require __DIR__ . '/../../resources/lang/en/notify-rules.php');
    $ru = flattenKeys(require __DIR__ . '/../../resources/lang/ru/notify-rules.php');

    expect($ru)->toEqualCanonicalizing($en);
});

it('renders every page without untranslated keys', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    Order::factory()->create();
    $rule = makeRule(['event' => 'field_changed', 'watched_field' => 'status']);
    Delivery::query()->create(['rule_id' => $rule->id, 'chat_id' => '1', 'message' => 'x', 'status' => 'failed', 'error' => 'oops']);

    $pages = [
        Livewire::test(ListNotificationRules::class),
        Livewire::test(CreateNotificationRule::class)
            ->fillForm(['model_type' => Order::class, 'event' => 'field_changed', 'template' => '{{ number }}'])
            ->set('data.conditions.items', [['field' => 'status', 'operator' => 'in']]),
        Livewire::test(EditNotificationRule::class, ['record' => $rule->getKey()]),
        Livewire::test(ListDeliveries::class),
        Livewire::test(TelegramConnection::class),
    ];

    foreach ($pages as $page) {
        expect($page->html())->not->toContain('notify-rules::');
    }
})->with(['en', 'ru']);

it('translates every enum label', function (string $locale): void {
    app()->setLocale($locale);

    foreach ([...RuleEvent::cases(), ...ConditionOperator::cases(), ...DeliveryStatus::cases()] as $case) {
        expect($case->getLabel())->not->toContain('notify-rules::');
    }
})->with(['en', 'ru']);
