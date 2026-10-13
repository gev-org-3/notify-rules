<?php

declare(strict_types=1);

use OrionSoft\NotifyRules\Models\Delivery;

function oldDelivery(int $daysAgo, string $status = 'sent'): Delivery
{
    $delivery = Delivery::query()->create(['chat_id' => '1', 'message' => 'x', 'status' => $status]);
    $delivery->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

    return $delivery;
}

it('deletes deliveries older than the given days', function (): void {
    $old = oldDelivery(40);
    $failed = oldDelivery(31, 'failed');
    $recent = oldDelivery(5);

    $this->artisan('notify-rules:prune', ['--days' => 30])
        ->expectsOutputToContain('Deleted 2 deliveries older than 30 days.')
        ->assertSuccessful();

    expect(Delivery::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($old->fresh())->toBeNull()
        ->and($failed->fresh())->toBeNull();
});

it('keeps pending deliveries', function (): void {
    $pending = oldDelivery(90, 'pending');

    $this->artisan('notify-rules:prune', ['--days' => 30])->assertSuccessful();

    expect($pending->fresh())->not->toBeNull();
});

it('uses the configured retention by default', function (): void {
    config()->set('notify-rules.prune_after_days', 7);
    oldDelivery(8);
    $kept = oldDelivery(6);

    $this->artisan('notify-rules:prune')->assertSuccessful();

    expect(Delivery::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('deletes in chunks', function (): void {
    foreach (range(1, 1005) as $_) {
        oldDelivery(40);
    }

    $this->artisan('notify-rules:prune', ['--days' => 30])
        ->expectsOutputToContain('Deleted 1005 deliveries')
        ->assertSuccessful();

    expect(Delivery::query()->count())->toBe(0);
});

it('rejects an invalid number of days', function (string $days): void {
    $this->artisan('notify-rules:prune', ['--days' => $days])->assertExitCode(2);
})->with(['0', '-1', 'abc']);
