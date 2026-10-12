<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Orion\NotifyRules\Http\Controllers\TelegramWebhookController;

// Registered without the "web" middleware group: Telegram sends no CSRF token or session.
Route::post(config('notify-rules.telegram.webhook_path', 'notify-rules/telegram/webhook'), TelegramWebhookController::class)
    ->name('notify-rules.telegram.webhook');
