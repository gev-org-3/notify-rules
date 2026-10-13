<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Enums\RuleEvent;
use Orion\NotifyRules\Filament\Resources\NotificationRules\NotificationRuleResource;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\CreateNotificationRule;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\EditNotificationRule;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\Telegram\TelegramLinkManager;
use Orion\NotifyRules\Tests\Fixtures\Models\Order;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
    $this->actingAs($this->admin);

    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
});

function connectTelegram(User $user, string $chatId = '4242'): void
{
    $links = app(TelegramLinkManager::class);
    $links->complete($links->createLinkToken($user), $chatId, null);
}

function validRuleData(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Big orders',
        'is_active' => true,
        'model_type' => Order::class,
        'event' => RuleEvent::Created->value,
        'template' => '<b>Order {{ number }}</b>: {{ total }}',
        'recipients' => ['chat_ids' => ['-100200300']],
    ], $overrides);
}

it('lists rules', function (): void {
    $rules = collect([makeRule(['name' => 'First']), makeRule(['name' => 'Second'])]);

    Livewire::test(ListNotificationRules::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($rules)
        ->assertSee('Order');
});

it('creates a rule with conditions and recipients', function (): void {
    $manager = User::factory()->create();

    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData([
            'conditions' => ['match' => 'any'],
            'recipients' => ['users' => [$manager->id], 'roles' => ['manager']],
        ]))
        ->set('data.conditions.items', [
            ['field' => 'total', 'operator' => '>', 'value' => '1000'],
            ['field' => 'status', 'operator' => 'is_not_empty'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = NotificationRule::query()->sole();

    expect($rule->name)->toBe('Big orders')
        ->and($rule->event)->toBe(RuleEvent::Created)
        ->and($rule->watched_field)->toBeNull()
        ->and($rule->conditions)->toBe([
            'match' => 'any',
            'items' => [
                ['field' => 'total', 'operator' => '>', 'value' => '1000'],
                ['field' => 'status', 'operator' => 'is_not_empty'],
            ],
        ])
        ->and($rule->recipients)->toBe([
            'users' => [$manager->id],
            'roles' => ['manager'],
            'chat_ids' => ['-100200300'],
        ]);
});

it('creates a rule that the engine then runs', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData())
        ->set('data.conditions.items', [['field' => 'total', 'operator' => '>', 'value' => '1000']])
        ->call('create')
        ->assertHasNoFormErrors();

    Order::factory()->create(['number' => 'ORD-9', 'total' => 1200]);
    Order::factory()->create(['total' => 10]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['text'] === '<b>Order ORD-9</b>: 1200.00');
});

it('validates the rule', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData([
            'name' => '',
            'model_type' => User::class,
            'template' => '',
            'recipients' => ['chat_ids' => ['not-a-chat']],
        ]))
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'model_type',
            'template' => 'required',
            'recipients.chat_ids.0',
        ]);

    expect(NotificationRule::query()->count())->toBe(0);
});

it('requires the watched field for field_changed rules', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData(['event' => RuleEvent::FieldChanged->value]))
        ->assertFormFieldVisible('watched_field')
        ->call('create')
        ->assertHasFormErrors(['watched_field' => 'required'])
        ->fillForm(['watched_field' => 'status'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NotificationRule::query()->sole()->watched_field)->toBe('status');
});

it('offers only readable columns of the chosen model', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(['model_type' => Order::class, 'event' => RuleEvent::FieldChanged->value])
        ->assertFormFieldExists('watched_field', function ($field): bool {
            return array_keys($field->getOptions()) === ['id', 'number', 'status', 'total', 'customer_id', 'created_at', 'updated_at'];
        });
});

it('hides the condition value for operators without one', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(['model_type' => Order::class])
        ->set('data.conditions.items', ['a' => ['field' => 'status', 'operator' => 'is_empty']])
        ->assertFormFieldHidden('conditions.items.a.value')
        ->set('data.conditions.items.a.operator', '=')
        ->assertFormFieldVisible('conditions.items.a.value');
});

it('previews the template on the latest record', function (): void {
    Order::factory()->create(['number' => 'ORD-OLD']);
    Order::factory()->create(['number' => 'ORD-<NEW>']);

    Livewire::test(CreateNotificationRule::class)
        ->fillForm(['model_type' => Order::class, 'template' => '<b>Order {{ number }}</b><script>x</script>'])
        ->assertSeeHtml('<b>Order ORD-&lt;NEW&gt;</b>&lt;script&gt;x&lt;/script&gt;')
        ->assertDontSeeHtml('<script>x</script>');
});

it('shows the template variables of the model', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(['model_type' => Order::class])
        ->assertSee('{{ number }}')
        ->assertSee('{{ customer.… }}')
        ->assertSee('{{ url }}');
});

it('sends a test message to the current user', function (): void {
    connectTelegram($this->admin, '4242');
    Order::factory()->create(['number' => 'ORD-5', 'total' => 50]);

    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData())
        ->callAction('sendTestMessage')
        ->assertNotified('Test message sent to your Telegram.');

    Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '4242'
        && $request['text'] === '<b>Order ORD-5</b>: 50.00');

    $delivery = Delivery::query()->sole();

    expect(NotificationRule::query()->count())->toBe(0)
        ->and($delivery->rule_id)->toBeNull()
        ->and($delivery->status)->toBe(DeliveryStatus::Sent);
});

it('sends a test message with the unsaved changes of an existing rule', function (): void {
    connectTelegram($this->admin);
    Order::factory()->create(['number' => 'ORD-5']);
    $rule = makeRule(['template' => 'Old {{ number }}']);

    Livewire::test(EditNotificationRule::class, ['record' => $rule->getKey()])
        ->fillForm(['template' => 'New {{ number }}'])
        ->callAction('sendTestMessage')
        ->assertNotified();

    Http::assertSent(fn (Request $request): bool => $request['text'] === 'New ORD-5');

    expect(Delivery::query()->sole()->rule_id)->toBe($rule->id)
        ->and($rule->fresh()->template)->toBe('Old {{ number }}');
});

it('asks to connect Telegram before sending a test message', function (): void {
    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData())
        ->callAction('sendTestMessage')
        ->assertNotified('Connect your Telegram to receive test messages.');

    Http::assertNothingSent();
});

it('reports a failed test message', function (): void {
    connectTelegram($this->admin);
    // Replaces the successful fake from beforeEach: earlier stubs would match first.
    Http::swap(new HttpFactory);
    Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400)]);

    Livewire::test(CreateNotificationRule::class)
        ->fillForm(validRuleData())
        ->callAction('sendTestMessage')
        ->assertNotified('The test message could not be sent.');
});

it('needs a model and a template to send a test message', function (): void {
    connectTelegram($this->admin);

    Livewire::test(CreateNotificationRule::class)
        ->callAction('sendTestMessage')
        ->assertNotified('Choose a record type and write a template first.');

    Http::assertNothingSent();
});

it('edits a rule', function (): void {
    $rule = makeRule(['event' => 'updated']);

    Livewire::test(EditNotificationRule::class, ['record' => $rule->getKey()])
        ->assertSchemaStateSet(['name' => 'Big orders', 'model_type' => Order::class])
        ->fillForm(['name' => 'Renamed', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rule->fresh())
        ->name->toBe('Renamed')
        ->is_active->toBeFalse();
});

it('toggles a rule from the table', function (): void {
    $rule = makeRule();

    Livewire::test(ListNotificationRules::class)
        ->call('updateTableColumnState', 'is_active', (string) $rule->getKey(), false);

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('counts messages sent in the last 7 days', function (): void {
    $rule = makeRule();
    Delivery::query()->create(['rule_id' => $rule->id, 'chat_id' => '1', 'message' => 'x', 'status' => 'sent']);
    Delivery::query()->create(['rule_id' => $rule->id, 'chat_id' => '1', 'message' => 'x', 'status' => 'failed']);
    Delivery::query()->create(['rule_id' => $rule->id, 'chat_id' => '1', 'message' => 'x', 'status' => 'sent', 'created_at' => now()->subDays(8)]);

    Livewire::test(ListNotificationRules::class)
        ->assertTableColumnStateSet('deliveries_count', 1, $rule);
});

it('is only available to users who can manage rules', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    $this->get(NotificationRuleResource::getUrl('index'))->assertForbidden();
    $this->get(NotificationRuleResource::getUrl('create'))->assertForbidden();

    expect(NotificationRuleResource::canViewAny())->toBeFalse();
});

it('is in the configured navigation group', function (): void {
    expect(NotificationRuleResource::getNavigationGroup())->toBe('Notifications');

    $this->get(NotificationRuleResource::getUrl('index'))->assertSuccessful();
});
