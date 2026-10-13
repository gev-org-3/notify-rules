<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Orion\NotifyRules\Enums\RuleEvent;
use Orion\NotifyRules\Filament\Resources\Deliveries\DeliveryResource;
use Orion\NotifyRules\Filament\Resources\Deliveries\Pages\ListDeliveries;
use Orion\NotifyRules\Filament\Resources\NotificationRules\NotificationRuleResource;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\CreateNotificationRule;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\NotifyRulesPlugin;
use Orion\NotifyRules\Tests\Fixtures\Models\Order;
use Orion\NotifyRules\Tests\Fixtures\Models\Team;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $this->teamA = Team::query()->create(['name' => 'Team A']);
    $this->teamB = Team::query()->create(['name' => 'Team B']);

    $this->userA = User::factory()->create(['name' => 'Alice']);
    $this->userA->teams()->attach($this->teamA);

    $this->userB = User::factory()->create(['name' => 'Bob']);
    $this->userB->teams()->attach($this->teamB);
});

function teamRule(?Team $team, string $name, string $chatId): NotificationRule
{
    return makeRule([
        'name' => $name,
        'team_id' => $team?->id,
        'recipients' => ['chat_ids' => [$chatId]],
        'template' => "{$name}: {{ number }}",
    ]);
}

function actAsTeamMember(User $user, Team $team): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('team'));
    Filament::setTenant($team);
}

it('runs only the rules of the order\'s team and the global ones', function (): void {
    teamRule($this->teamA, 'A', '-1001');
    teamRule($this->teamB, 'B', '-1002');
    teamRule(null, 'Global', '-1003');

    Order::factory()->create(['number' => 'ORD-A', 'team_id' => $this->teamA->id]);

    expect(Delivery::query()->pluck('message')->sort()->values()->all())->toBe(['A: ORD-A', 'Global: ORD-A']);

    Http::assertNotSent(fn (Request $request): bool => $request['chat_id'] === '-1002');
});

it('stores the rule\'s tenant on the delivery', function (): void {
    teamRule($this->teamA, 'A', '-1001');
    teamRule(null, 'Global', '-1003');

    Order::factory()->create(['team_id' => $this->teamA->id]);

    expect(Delivery::query()->where('chat_id', '-1001')->sole()->team_id)->toBe($this->teamA->id)
        ->and(Delivery::query()->where('chat_id', '-1003')->sole()->team_id)->toBeNull();
});

it('runs only global rules for records without a team', function (): void {
    teamRule($this->teamA, 'A', '-1001');
    teamRule(null, 'Global', '-1003');

    Order::factory()->create(['number' => 'ORD-X', 'team_id' => null]);

    expect(Delivery::query()->pluck('message')->all())->toBe(['Global: ORD-X']);
});

it('lists only the current team\'s rules', function (): void {
    $ruleA = teamRule($this->teamA, 'A', '-1001');
    $ruleB = teamRule($this->teamB, 'B', '-1002');
    $global = teamRule(null, 'Global', '-1003');

    actAsTeamMember($this->userA, $this->teamA);

    Livewire::test(ListNotificationRules::class)
        ->assertCanSeeTableRecords([$ruleA])
        ->assertCanNotSeeTableRecords([$ruleB, $global]);
});

it('creates rules for the current team', function (): void {
    actAsTeamMember($this->userA, $this->teamA);

    Livewire::test(CreateNotificationRule::class)
        ->fillForm([
            'name' => 'Team A orders',
            'model_type' => Order::class,
            'event' => RuleEvent::Created->value,
            'template' => 'Order {{ number }}',
            'recipients' => ['users' => [$this->userA->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NotificationRule::query()->sole()->team_id)->toBe($this->teamA->id);
});

it('does not open another team\'s rule', function (): void {
    $ruleB = teamRule($this->teamB, 'B', '-1002');

    actAsTeamMember($this->userA, $this->teamA);

    $this->get(NotificationRuleResource::getUrl('edit', ['record' => $ruleB]))->assertNotFound();

    expect(NotificationRuleResource::canEdit($ruleB))->toBeFalse()
        ->and(NotificationRuleResource::canDelete($ruleB))->toBeFalse();
});

it('only offers and accepts the team\'s users as recipients', function (): void {
    actAsTeamMember($this->userA, $this->teamA);

    Livewire::test(CreateNotificationRule::class)
        ->assertFormFieldExists('recipients.users', fn ($field): bool => array_keys($field->getOptions()) === [$this->userA->id])
        ->fillForm([
            'name' => 'Sneaky',
            'model_type' => Order::class,
            'event' => RuleEvent::Created->value,
            'template' => 'Order {{ number }}',
            'recipients' => ['users' => [$this->userB->id]],
        ])
        ->call('create')
        ->assertHasFormErrors(['recipients.users']);

    expect(NotificationRule::query()->count())->toBe(0);
});

it('previews only the team\'s records', function (): void {
    Order::factory()->create(['number' => 'ORD-OF-A', 'team_id' => $this->teamA->id]);
    Order::factory()->create(['number' => 'ORD-OF-B', 'team_id' => $this->teamB->id]);

    actAsTeamMember($this->userA, $this->teamA);

    Livewire::test(CreateNotificationRule::class)
        ->fillForm(['model_type' => Order::class, 'template' => 'Order {{ number }}'])
        ->assertSee('Order ORD-OF-A')
        ->assertDontSee('ORD-OF-B');
});

it('lists only the current team\'s deliveries', function (): void {
    $ofA = Delivery::query()->create(['chat_id' => '1', 'message' => 'a', 'team_id' => $this->teamA->id]);
    $ofB = Delivery::query()->create(['chat_id' => '2', 'message' => 'b', 'team_id' => $this->teamB->id]);

    actAsTeamMember($this->userA, $this->teamA);

    Livewire::test(ListDeliveries::class)
        ->assertCanSeeTableRecords([$ofA])
        ->assertCanNotSeeTableRecords([$ofB]);

    expect(DeliveryResource::canView($ofB))->toBeFalse();
});

it('refuses a tenant panel without the tenancy column', function (): void {
    config()->set('notify-rules.tenancy.column', null);

    NotifyRulesPlugin::make()->tenantOwnership('team')->boot(Filament::getPanel('team'));
})->throws(LogicException::class, 'notify-rules.tenancy.column');

it('refuses a tenant panel without tenantOwnership()', function (): void {
    NotifyRulesPlugin::make()->boot(Filament::getPanel('team'));
})->throws(LogicException::class, 'tenantOwnership');
