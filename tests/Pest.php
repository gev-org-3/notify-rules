<?php

use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\Tests\Fixtures\Models\Order;
use Orion\NotifyRules\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * @param  array<string, mixed>  $attributes
 */
function makeRule(array $attributes = []): NotificationRule
{
    return NotificationRule::query()->create(array_merge([
        'name' => 'Big orders',
        'model_type' => Order::class,
        'event' => 'created',
        'conditions' => null,
        'recipients' => ['chat_ids' => ['-100200300']],
        'template' => 'Order {{ number }}',
    ], $attributes));
}
