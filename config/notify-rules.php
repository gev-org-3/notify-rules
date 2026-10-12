<?php

declare(strict_types=1);

return [

    'telegram' => [
        'bot_token' => env('NOTIFY_RULES_TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('NOTIFY_RULES_TELEGRAM_BOT_USERNAME'),

        // Sent by Telegram in the X-Telegram-Bot-Api-Secret-Token header.
        'webhook_secret' => env('NOTIFY_RULES_TELEGRAM_WEBHOOK_SECRET'),

        'api_url' => env('NOTIFY_RULES_TELEGRAM_API_URL', 'https://api.telegram.org'),

        'webhook_path' => 'notify-rules/telegram/webhook',

        // Minutes a "Connect Telegram" link stays valid.
        'link_token_ttl' => 15,
    ],

    'queue' => [
        'connection' => env('NOTIFY_RULES_QUEUE_CONNECTION'),
        'name' => env('NOTIFY_RULES_QUEUE'),
    ],

    'tables' => [
        'rules' => 'notify_rules',
        'telegram_links' => 'notify_telegram_links',
        'deliveries' => 'notify_deliveries',
    ],

    'tenancy' => [
        // Column on the rules table that stores the tenant key. Null when the panel has no tenancy.
        'column' => null,
    ],

    // The model Telegram chats are linked to.
    'user_model' => env('NOTIFY_RULES_USER_MODEL', 'App\\Models\\User'),

    // Attributes that are never offered in conditions or templates, in addition to each model's $hidden.
    'excluded_fields' => [
        'password',
        'remember_token',
    ],

];
