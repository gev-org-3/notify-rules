<?php

declare(strict_types=1);

return [

    'telegram' => [
        'user_menu' => 'Telegram',

        'page' => [
            'title' => 'Telegram notifications',
            'description' => 'Connect your Telegram account to receive notifications from this panel.',

            'connected' => [
                'heading' => 'Connected',
                'heading_with_username' => 'Connected as @:username',
                'description' => 'Connected on :date.',
            ],

            'not_connected' => [
                'heading' => 'Not connected',
                'description' => 'Press "Connect Telegram", then press "Start" in the bot chat that opens.',
            ],

            'pending' => [
                'heading' => 'Waiting for confirmation',
                'description' => 'Open the bot in Telegram and press "Start". The link is valid for :minutes minutes and works once.',
                'open' => 'Open Telegram',
            ],

            'actions' => [
                'connect' => 'Connect Telegram',
                'reconnect' => 'Connect another account',
                'disconnect' => 'Disconnect',
                'disconnect_confirm' => 'You will stop receiving notifications in Telegram.',
            ],

            'notifications' => [
                'disconnected' => 'Telegram disconnected.',
                'not_configured' => 'The Telegram bot is not configured. Ask an administrator to set the bot username.',
            ],
        ],
    ],

    'bot' => [
        'connected' => 'Connected ✅ You will receive notifications here.',
        'link_invalid' => 'This link is invalid or has expired. Create a new one in the admin panel.',
        'start_without_token' => 'To receive notifications, open the admin panel and press "Connect Telegram".',
        'chat_id' => 'Chat ID: <code>:id</code>',
    ],

    'events' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'field_changed' => 'Field changed',
    ],

    'operators' => [
        'Equals' => 'equals',
        'NotEquals' => 'does not equal',
        'GreaterThan' => 'greater than',
        'GreaterThanOrEqual' => 'greater than or equal to',
        'LessThan' => 'less than',
        'LessThanOrEqual' => 'less than or equal to',
        'Contains' => 'contains',
        'In' => 'is one of',
        'IsEmpty' => 'is empty',
        'IsNotEmpty' => 'is not empty',
        'Changed' => 'changed',
        'ChangedTo' => 'changed to',
        'ChangedFrom' => 'changed from',
    ],

    'template' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],

];
