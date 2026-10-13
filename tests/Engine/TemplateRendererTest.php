<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Orion\NotifyRules\Engine\TemplateRenderer;
use Orion\NotifyRules\Models\NotificationRule;
use Orion\NotifyRules\Tests\Fixtures\Models\Order;
use Orion\NotifyRules\Tests\Fixtures\Models\User;

function renderTemplate(string $template, ?Order $order = null, string $ruleName = 'Big orders'): string
{
    return app(TemplateRenderer::class)->render(
        $template,
        $order ?? Order::factory()->create(['number' => 'ORD-1', 'status' => 'new', 'total' => 1500]),
        new NotificationRule(['name' => $ruleName]),
    );
}

it('replaces fields', function (): void {
    expect(renderTemplate('Order {{ number }} for {{total}}, status: {{  status  }}'))
        ->toBe('Order ORD-1 for 1500.00, status: new');
});

it('replaces relation fields', function (): void {
    $customer = User::factory()->create(['name' => 'Anna']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    expect(renderTemplate('Customer: {{ customer.name }}', $order))->toBe('Customer: Anna')
        ->and(renderTemplate('Customer: {{ customer.name }}', Order::factory()->create()))->toBe('Customer:');
});

it('never exposes hidden fields, also through relations', function (): void {
    $customer = User::factory()->create(['password' => 'super-secret-hash']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    expect(renderTemplate('[{{ customer.password }}][{{ customer.remember_token }}]', $order))->toBe('[][]');
});

it('only follows relations, not arbitrary methods', function (): void {
    expect(renderTemplate('[{{ delete.id }}][{{ newQuery.id }}]'))->toBe('[][]')
        ->and(Order::query()->count())->toBe(1);
});

it('replaces old values', function (): void {
    $order = Order::factory()->create(['status' => 'new']);
    $order->status = 'paid';
    $order->syncChanges();

    expect(renderTemplate('{{ old.status }} → {{ status }}', $order))->toBe('new → paid');
});

it('replaces rule name and now', function (): void {
    Carbon::setTestNow('2026-10-13 09:30:00');

    expect(renderTemplate('{{ rule.name }} at {{ now }}'))->toBe('Big orders at 2026-10-13 09:30');
});

it('renders unknown placeholders as empty', function (): void {
    expect(renderTemplate('[{{ unknown }}][{{ old.customer.name }}]'))->toBe('[][]');
});

it('formats dates', function (): void {
    $order = Order::factory()->create(['created_at' => '2026-01-02 03:04:05']);

    expect(renderTemplate('{{ created_at }}', $order))->toBe('2026-01-02 03:04');
});

it('escapes model values', function (): void {
    $order = Order::factory()->create(['number' => '<script>alert("x")</script> & <b>bold</b>']);

    expect(renderTemplate('{{ number }}', $order))
        ->toBe('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &lt;b&gt;bold&lt;/b&gt;');
});

it('keeps the allowed tags written in the template', function (): void {
    expect(renderTemplate('<b>New</b> <i>order</i> <code>{{ number }}</code> <a href="https://example.com/orders?a=1&b=2">open</a>'))
        ->toBe('<b>New</b> <i>order</i> <code>ORD-1</code> <a href="https://example.com/orders?a=1&amp;b=2">open</a>');
});

it('escapes any other markup in the template', function (): void {
    expect(renderTemplate('<script>x</script> <u>u</u> <b onclick="x">b</b> 1 < 2 & 3 > 2'))
        ->toBe('&lt;script&gt;x&lt;/script&gt; &lt;u&gt;u&lt;/u&gt; &lt;b onclick=&quot;x&quot;&gt;b&lt;/b&gt; 1 &lt; 2 &amp; 3 &gt; 2');
});

it('escapes all tags when they are not balanced', function (string $template): void {
    expect(renderTemplate($template))->not->toContain('<');
})->with([
    'unclosed' => ['<b>bold'],
    'wrong order' => ['<b><i>x</b></i>'],
    'stray closing tag' => ['x</b>'],
    'nested links' => ['<a href="https://a.test"><a href="https://b.test">x</a></a>'],
]);

it('escapes values inside links', function (): void {
    $order = Order::factory()->create(['number' => '" onclick="alert(1)']);

    expect(renderTemplate('<a href="https://shop.test/{{ number }}">open</a>', $order))
        ->toBe('<a href="https://shop.test/&quot; onclick=&quot;alert(1)">open</a>');
});

it('keeps only http, https and tg links', function (string $href, bool $kept): void {
    $result = renderTemplate("<a href=\"{$href}\">open</a>");

    expect($result)->toBe($kept ? "<a href=\"{$href}\">open</a>" : 'open');
})->with([
    'https' => ['https://shop.test', true],
    'http' => ['http://shop.test', true],
    'tg' => ['tg://resolve?domain=x', true],
    'javascript' => ['javascript:alert(1)', false],
    'data' => ['data:text/html,x', false],
    'relative' => ['/orders/1', false],
    'empty' => ['', false],
]);

it('unwraps links whose URL comes from an unsafe value', function (): void {
    $order = Order::factory()->create(['number' => 'javascript:alert(1)']);

    expect(renderTemplate('<a href="{{ number }}">open</a> <b>x</b>', $order))->toBe('open <b>x</b>');
});

it('makes stored messages safe to show in the panel', function (string $stored, string $shown): void {
    expect(app(TemplateRenderer::class)->toPanelHtml($stored)->toHtml())->toBe($shown);
})->with([
    'allowed tags and line breaks' => ["<b>A</b>\n<i>b</i> <code>c</code>", "<b>A</b><br>\n<i>b</i> <code>c</code>"],
    'safe link' => ['<a href="https://shop.test/1">open</a>', '<a href="https://shop.test/1">open</a>'],
    'script' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
    'attributes' => ['<b onclick="x">A</b>', '&lt;b onclick=&quot;x&quot;&gt;A</b>'],
    'unsafe link' => ['<a href="javascript:x">A</a>', '&lt;a href=&quot;javascript:x&quot;&gt;A</a>'],
    'unclosed tag' => ['<img src=x onerror=alert(1)', '&lt;img src=x onerror=alert(1)'],
    'entities stay' => ['1 &lt; 2 &amp; 3', '1 &lt; 2 &amp; 3'],
]);

it('normalizes tag case', function (): void {
    expect(renderTemplate('<B>x</B>'))->toBe('<b>x</b>');
});

it('does not truncate a message within the limit', function (): void {
    $text = str_repeat('a', TemplateRenderer::MAX_LENGTH);

    expect(app(TemplateRenderer::class)->truncate($text))->toBe($text);
});

it('truncates long messages to 4096 characters with an ellipsis', function (): void {
    $result = app(TemplateRenderer::class)->truncate(str_repeat('a', 5000));

    expect(mb_strlen($result))->toBe(TemplateRenderer::MAX_LENGTH)
        ->and($result)->toEndWith('a…');
});

it('counts visible characters only and closes open tags when truncating', function (): void {
    $html = '<b>' . str_repeat('&amp;', 3000) . '</b><i>' . str_repeat('b', 3000) . '</i>';

    $result = app(TemplateRenderer::class)->truncate($html);

    // 3000 entities count as 3000 characters, leaving 4096 - 3000 - 1 (ellipsis) = 1095 letters.
    expect($result)->toBe('<b>' . str_repeat('&amp;', 3000) . '</b><i>' . str_repeat('b', 1095) . '…</i>');
});

it('never cuts an entity or a tag in half', function (): void {
    $result = app(TemplateRenderer::class)->truncate(str_repeat('&lt;', 4100));

    expect($result)->toBe(str_repeat('&lt;', 4095) . '…');
});

it('counts characters outside the BMP as two, like Telegram', function (): void {
    $result = app(TemplateRenderer::class)->truncate(str_repeat('😀', 2100));

    expect(mb_strlen($result))->toBe(2047 + 1)
        ->and($result)->toEndWith('😀…');
});

it('truncates rendered templates', function (): void {
    $order = Order::factory()->create(['number' => str_repeat('x', 5000)]);

    expect(mb_strlen(renderTemplate('<b>{{ number }}</b>', $order)))->toBe(TemplateRenderer::MAX_LENGTH + strlen('<b></b>'));
});
