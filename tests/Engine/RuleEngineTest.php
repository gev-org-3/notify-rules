<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Orion\NotifyRules\Engine\ModelEventRegistrar;
use Orion\NotifyRules\Engine\Roles\RoleUserResolver;
use Orion\NotifyRules\Engine\RuleRepository;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Models\Delivery;
use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\NotifyRulesPlugin;
use Orion\NotifyRules\Tests\Fixtures\Models\Order;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
});

function bigOrderRule(array $attributes = []): void
{
    makeRule(array_merge([
        'conditions' => ['match' => 'all', 'items' => [['field' => 'total', 'operator' => '>', 'value' => '1000']]],
        'template' => '<b>Big order</b> {{ number }}: {{ total }}',
    ], $attributes));
}

it('sends a Telegram message when an order over 1000 is created', function (): void {
    bigOrderRule();

    $order = Order::factory()->create(['number' => 'ORD-7', 'total' => 1500]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
        && $request['chat_id'] === '-100200300'
        && $request['text'] === '<b>Big order</b> ORD-7: 1500.00'
        && $request['parse_mode'] === 'HTML');

    $delivery = Delivery::query()->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->rule_id)->not->toBeNull()
        ->and($delivery->subject_type)->toBe($order->getMorphClass())
        ->and($delivery->subject_id)->toBe($order->id)
        ->and($delivery->telegram_message_id)->toBe('77');
});

it('sends nothing for an order of 1000 or less', function (): void {
    bigOrderRule();

    Order::factory()->create(['total' => 1000]);

    Http::assertNothingSent();
    expect(Delivery::query()->count())->toBe(0);
});

it('creates one delivery per recipient', function (): void {
    bigOrderRule(['recipients' => ['chat_ids' => ['1', '2', '3']]]);

    Order::factory()->create(['total' => 2000]);

    expect(Delivery::query()->pluck('chat_id')->all())->toEqualCanonicalizing(['1', '2', '3']);
    Http::assertSentCount(3);
});

it('only runs rules of the matching event', function (): void {
    makeRule(['event' => 'updated', 'template' => 'updated {{ number }}']);
    makeRule(['event' => 'deleted', 'template' => 'deleted {{ number }}']);

    $order = Order::factory()->create(['number' => 'ORD-1']);
    expect(Delivery::query()->count())->toBe(0);

    $order->update(['status' => 'paid']);
    expect(Delivery::query()->pluck('message')->all())->toBe(['updated ORD-1']);

    $order->delete();
    expect(Delivery::query()->pluck('message')->all())->toBe(['updated ORD-1', 'deleted ORD-1']);
});

it('runs field_changed rules only when the watched field changes', function (): void {
    makeRule([
        'event' => 'field_changed',
        'watched_field' => 'status',
        'template' => 'Order {{ number }}: {{ old.status }} → {{ status }}',
    ]);

    $order = Order::factory()->create(['number' => 'ORD-1', 'status' => 'new']);
    $order->update(['total' => 99]);
    expect(Delivery::query()->count())->toBe(0);

    $order->update(['status' => 'paid']);
    expect(Delivery::query()->sole()->message)->toBe('Order ORD-1: new → paid');
});

it('sends on changed_to', function (): void {
    makeRule([
        'event' => 'updated',
        'conditions' => ['items' => [['field' => 'status', 'operator' => 'changed_to', 'value' => 'paid']]],
        'template' => 'Paid: {{ number }}',
    ]);

    $order = Order::factory()->create(['number' => 'ORD-1', 'status' => 'new']);
    $order->update(['status' => 'shipped']);
    $order->update(['status' => 'paid']);
    $order->update(['total' => 5]);

    expect(Delivery::query()->pluck('message')->all())->toBe(['Paid: ORD-1']);
});

it('ignores updates that only touch timestamps', function (): void {
    makeRule(['event' => 'updated']);

    $order = Order::factory()->create();
    $this->travel(1)->minute();
    $order->touch();

    expect(Delivery::query()->count())->toBe(0);
});

it('skips inactive rules', function (): void {
    bigOrderRule(['is_active' => false]);

    Order::factory()->create(['total' => 5000]);

    expect(Delivery::query()->count())->toBe(0);
});

it('picks up rule changes without a stale cache', function (): void {
    Order::factory()->create(['total' => 5000]);
    expect(Delivery::query()->count())->toBe(0);

    bigOrderRule();
    Order::factory()->create(['total' => 5000]);
    expect(Delivery::query()->count())->toBe(1);

    NotificationRule::query()->sole()->update(['is_active' => false]);
    Order::factory()->create(['total' => 5000]);
    expect(Delivery::query()->count())->toBe(1);
});

it('caches the active rules', function (): void {
    bigOrderRule();
    app(RuleRepository::class)->activeFor(Order::class);

    DB::enableQueryLog();
    app(RuleRepository::class)->activeFor(Order::class);

    expect(DB::getQueryLog())->toBe([]);
});

it('dispatches the jobs only after the transaction commits', function (): void {
    bigOrderRule();

    DB::transaction(function (): void {
        Order::factory()->create(['total' => 5000]);

        expect(Delivery::query()->sole()->status)->toBe(DeliveryStatus::Pending);
        Http::assertNothingSent();
    });

    expect(Delivery::query()->sole()->status)->toBe(DeliveryStatus::Sent);
    Http::assertSentCount(1);
});

it('sends nothing when the transaction rolls back', function (): void {
    bigOrderRule();

    try {
        DB::transaction(function (): void {
            Order::factory()->create(['total' => 5000]);

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }

    Http::assertNothingSent();
    expect(Delivery::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0);
});

it('does not break saving the model when a rule fails', function (): void {
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): void {
        $reported[] = $e->getMessage();
    });

    app()->instance(RoleUserResolver::class, new class implements RoleUserResolver
    {
        public function userIds(array $roles): array
        {
            throw new RuntimeException('Roles are down');
        }
    });

    bigOrderRule(['name' => 'Broken', 'recipients' => ['roles' => ['manager']]]);
    bigOrderRule(['name' => 'Working', 'recipients' => ['chat_ids' => ['1']]]);

    $order = Order::factory()->create(['total' => 5000]);

    expect($order->exists)->toBeTrue()
        ->and($reported)->toBe(['Roles are down'])
        ->and(Delivery::query()->pluck('chat_id')->all())->toBe(['1']);
});

it('skips a rule without recipients', function (): void {
    bigOrderRule(['recipients' => ['users' => [12345]]]);

    Order::factory()->create(['total' => 5000]);

    expect(Delivery::query()->count())->toBe(0);
});

it('only listens to allowlisted models', function (): void {
    makeRule(['model_type' => User::class, 'template' => 'user']);

    User::factory()->create();

    expect(app(ModelEventRegistrar::class)->registered())->toBe([Order::class])
        ->and(Delivery::query()->count())->toBe(0);
});

it('refuses rules on the package models', function (): void {
    NotifyRulesPlugin::make()->models([Delivery::class]);
})->throws(InvalidArgumentException::class);
