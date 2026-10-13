<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Filament\Resources\Deliveries\DeliveryResource;
use Orion\NotifyRules\Filament\Resources\Deliveries\Pages\ListDeliveries;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Telegram\TelegramLinkManager;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->admin()->create());

    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 31]])]);
});

function delivery(array $attributes = []): Delivery
{
    return Delivery::query()->create(array_merge([
        'chat_id' => '100',
        'message' => '<b>Hello</b>',
    ], $attributes));
}

it('lists deliveries with their recipients', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $links = app(TelegramLinkManager::class);
    $links->complete($links->createLinkToken($anna), '555', 'anna_tg');

    $rule = makeRule(['name' => 'Big orders']);
    $toAnna = delivery(['chat_id' => '555', 'rule_id' => $rule->id, 'status' => 'sent']);
    $toGroup = delivery(['chat_id' => '-100200300', 'status' => 'failed', 'error' => 'Bad Request: chat not found']);

    Livewire::test(ListDeliveries::class)
        ->assertCanSeeTableRecords([$toAnna, $toGroup])
        ->assertSee('Anna (555)')
        ->assertSee('-100200300')
        ->assertSee('Big orders')
        ->assertSee('Bad Request: chat not found');
});

it('filters by status', function (): void {
    $sent = delivery(['status' => 'sent']);
    $failed = delivery(['status' => 'failed']);

    Livewire::test(ListDeliveries::class)
        ->filterTable('status', 'failed')
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$sent]);
});

it('filters by period', function (): void {
    $old = delivery(['created_at' => now()->subDays(10)]);
    $recent = delivery();

    Livewire::test(ListDeliveries::class)
        ->filterTable('period', ['from' => now()->subDay()->toDateString()])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('resends a failed delivery', function (): void {
    $failed = delivery(['status' => 'failed', 'attempts' => 5, 'error' => 'Timeout']);

    Livewire::test(ListDeliveries::class)
        ->assertTableActionVisible('resend', $failed)
        ->callTableAction('resend', $failed)
        ->assertNotified();

    $failed->refresh();
    expect($failed->status)->toBe(DeliveryStatus::Sent)
        ->and($failed->attempts)->toBe(1)
        ->and($failed->error)->toBeNull()
        ->and($failed->telegram_message_id)->toBe('31');
});

it('only offers resending failed deliveries', function (): void {
    Livewire::test(ListDeliveries::class)
        ->assertTableActionHidden('resend', delivery(['status' => 'sent']))
        ->assertTableActionHidden('resend', delivery(['status' => 'pending']));
});

it('resends the failed deliveries among the selected ones', function (): void {
    $failed = [delivery(['status' => 'failed']), delivery(['status' => 'failed'])];
    $sent = delivery(['status' => 'sent', 'telegram_message_id' => '1']);

    Livewire::test(ListDeliveries::class)
        ->callTableBulkAction('resend', [...$failed, $sent])
        ->assertNotified('2 deliveries queued again.');

    Http::assertSentCount(2);
    expect($sent->fresh()->telegram_message_id)->toBe('1');
});

it('opens a delivery', function (): void {
    $record = delivery();

    Livewire::test(ListDeliveries::class)
        ->assertTableActionVisible('view', $record)
        ->mountAction(TestAction::make('view')->table($record))
        ->assertActionMounted(TestAction::make('view')->table($record));
});

it('is read-only', function (): void {
    $record = delivery();

    expect(DeliveryResource::canCreate())->toBeFalse()
        ->and(DeliveryResource::canEdit($record))->toBeFalse()
        ->and(DeliveryResource::canDelete($record))->toBeFalse()
        ->and(DeliveryResource::hasPage('create'))->toBeFalse();
});

it('is only available to users who can manage rules', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(DeliveryResource::getUrl('index'))->assertForbidden();
});
