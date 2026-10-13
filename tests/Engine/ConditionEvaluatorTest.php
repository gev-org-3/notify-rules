<?php

declare(strict_types=1);

use OrionSoft\NotifyRules\Engine\ConditionEvaluator;
use OrionSoft\NotifyRules\Models\NotificationRule;
use OrionSoft\NotifyRules\Tests\Fixtures\Models\Order;
use OrionSoft\NotifyRules\Tests\Fixtures\Models\User;

function condition(string $field, string $operator, mixed $value = null): array
{
    return ['field' => $field, 'operator' => $operator, 'value' => $value];
}

function checkCondition(array $condition, Order | User $model): bool
{
    return app(ConditionEvaluator::class)->check($condition, $model);
}

/**
 * The state Eloquent leaves the model in while the "updated" event runs:
 * getChanges() holds the saved changes, getOriginal() still holds the old values.
 */
function updatedOrder(array $before, array $after): Order
{
    $order = Order::factory()->create($before);
    $order->fill($after);
    $order->syncChanges();

    return $order;
}

beforeEach(function (): void {
    $this->order = Order::factory()->create(['number' => 'ORD-1', 'status' => 'new', 'total' => 1500]);
});

it('checks equality', function (string $operator, mixed $value, bool $expected): void {
    expect(checkCondition(condition('status', $operator, $value), $this->order))->toBe($expected);
})->with([
    '= match' => ['=', 'new', true],
    '= trims the value' => ['=', ' new ', true],
    '= mismatch' => ['=', 'paid', false],
    '!= match' => ['!=', 'paid', true],
    '!= mismatch' => ['!=', 'new', false],
]);

it('compares decimals as numbers', function (string $operator, mixed $value, bool $expected): void {
    expect(checkCondition(condition('total', $operator, $value), $this->order))->toBe($expected);
})->with([
    '= ignores the decimal format' => ['=', '1500', true],
    '>' => ['>', '1000', true],
    '> equal' => ['>', '1500', false],
    '>= equal' => ['>=', '1500.00', true],
    '>= less' => ['>=', '1500.01', false],
    '<' => ['<', '2000', true],
    '< greater' => ['<', '1000', false],
    '<= equal' => ['<=', 1500, true],
    '<= greater' => ['<=', '1499.99', false],
    'not numerically compared as strings' => ['>', '900', true],
]);

it('never matches comparisons with an empty value', function (string $operator): void {
    expect(checkCondition(condition('total', $operator, ''), $this->order))->toBeFalse()
        ->and(checkCondition(condition('total', $operator, null), $this->order))->toBeFalse();
})->with(['>', '>=', '<', '<=']);

it('compares dates', function (): void {
    $this->order->forceFill(['created_at' => '2026-05-10 12:00:00']);

    expect(checkCondition(condition('created_at', '>', '2026-05-01'), $this->order))->toBeTrue()
        ->and(checkCondition(condition('created_at', '<', '2026-05-01'), $this->order))->toBeFalse()
        ->and(checkCondition(condition('created_at', '<', 'not a date'), $this->order))->toBeFalse();
});

it('checks contains case-insensitively', function (): void {
    $this->order->number = 'ORD-Express-42';

    expect(checkCondition(condition('number', 'contains', 'express'), $this->order))->toBeTrue()
        ->and(checkCondition(condition('number', 'contains', 'slow'), $this->order))->toBeFalse()
        ->and(checkCondition(condition('number', 'contains', ''), $this->order))->toBeFalse();
});

it('checks in with a list or comma-separated values', function (mixed $value, bool $expected): void {
    expect(checkCondition(condition('status', 'in', $value), $this->order))->toBe($expected);
})->with([
    'comma-separated' => ['paid, new , shipped', true],
    'array' => [['paid', 'new'], true],
    'missing' => ['paid,shipped', false],
    'empty' => ['', false],
]);

it('checks emptiness', function (): void {
    $this->order->customer_id = null;

    expect(checkCondition(condition('customer_id', 'is_empty'), $this->order))->toBeTrue()
        ->and(checkCondition(condition('customer_id', 'is_not_empty'), $this->order))->toBeFalse()
        ->and(checkCondition(condition('status', 'is_empty'), $this->order))->toBeFalse()
        ->and(checkCondition(condition('status', 'is_not_empty'), $this->order))->toBeTrue();
});

it('detects changed fields', function (): void {
    $order = updatedOrder(['status' => 'new'], ['status' => 'paid']);

    expect(checkCondition(condition('status', 'changed'), $order))->toBeTrue()
        ->and(checkCondition(condition('total', 'changed'), $order))->toBeFalse();
});

it('checks changed_to', function (): void {
    $order = updatedOrder(['status' => 'new'], ['status' => 'paid']);

    expect(checkCondition(condition('status', 'changed_to', 'paid'), $order))->toBeTrue()
        ->and(checkCondition(condition('status', 'changed_to', 'new'), $order))->toBeFalse()
        ->and(checkCondition(condition('status', 'changed_to', 'shipped'), $order))->toBeFalse();
});

it('does not treat an unchanged value as changed_to', function (): void {
    $order = updatedOrder(['status' => 'paid', 'total' => 10], ['total' => 20]);

    expect(checkCondition(condition('status', 'changed_to', 'paid'), $order))->toBeFalse();
});

it('checks changed_from', function (): void {
    $order = updatedOrder(['status' => 'new'], ['status' => 'paid']);

    expect(checkCondition(condition('status', 'changed_from', 'new'), $order))->toBeTrue()
        ->and(checkCondition(condition('status', 'changed_from', 'paid'), $order))->toBeFalse();
});

it('does not match change operators on a created model', function (string $operator): void {
    $order = Order::factory()->create(['status' => 'paid']);

    expect(checkCondition(condition('status', $operator, 'paid'), $order))->toBeFalse();
})->with(['changed', 'changed_to', 'changed_from']);

it('never reads hidden or excluded fields', function (): void {
    $user = User::factory()->create(['password' => 'secret']);

    expect(checkCondition(condition('password', 'is_not_empty'), $user))->toBeFalse()
        ->and(checkCondition(condition('remember_token', 'is_empty'), $user))->toBeFalse();
});

it('ignores unknown operators and fields', function (): void {
    expect(checkCondition(condition('status', 'like', 'new'), $this->order))->toBeFalse()
        ->and(checkCondition(condition('missing', 'is_empty'), $this->order))->toBeTrue()
        ->and(checkCondition(condition('', 'is_empty'), $this->order))->toBeFalse();
});

it('matches all or any conditions', function (string $match, bool $expected): void {
    $rule = new NotificationRule([
        'conditions' => [
            'match' => $match,
            'items' => [
                condition('total', '>', 1000),
                condition('status', '=', 'paid'),
            ],
        ],
    ]);

    expect(app(ConditionEvaluator::class)->passes($rule, $this->order))->toBe($expected);
})->with([
    'all' => ['all', false],
    'any' => ['any', true],
]);

it('passes a rule without conditions', function (): void {
    expect(app(ConditionEvaluator::class)->passes(new NotificationRule, $this->order))->toBeTrue();
});
