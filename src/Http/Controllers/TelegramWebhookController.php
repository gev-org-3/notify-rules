<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OrionSoft\NotifyRules\Telegram\UpdateHandler;

final class TelegramWebhookController
{
    public function __invoke(Request $request, UpdateHandler $handler): Response
    {
        $secret = config('notify-rules.telegram.webhook_secret');
        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // Without a configured secret the webhook is closed: anyone could post fake updates.
        if (! is_string($secret) || $secret === '' || ! is_string($header) || ! hash_equals($secret, $header)) {
            abort(403);
        }

        /** @var array<string, mixed> $update */
        $update = $request->json()->all();

        $handler->handle($update);

        return response()->noContent();
    }
}
