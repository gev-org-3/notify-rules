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

    'rules' => [
        'model_label' => 'notification rule',
        'plural_model_label' => 'notification rules',

        'form' => [
            'general' => [
                'heading' => 'Rule',
                'name' => 'Name',
                'is_active' => 'Active',
            ],
            'trigger' => [
                'heading' => 'When',
                'description' => 'The record type and the event that trigger the rule.',
                'model' => 'Record type',
                'event' => 'Event',
                'watched_field' => 'Field',
            ],
            'conditions' => [
                'heading' => 'Conditions',
                'description' => 'Without conditions, the rule fires on every event. "Changed" conditions only match on updates.',
                'match' => 'Match',
                'match_all' => 'All conditions',
                'match_any' => 'Any condition',
                'add' => 'Add condition',
                'field' => 'Field',
                'operator' => 'Operator',
                'value' => 'Value',
                'in_help' => 'Separate values with commas.',
            ],
            'recipients' => [
                'heading' => 'Recipients',
                'description' => 'Users receive messages once they have connected Telegram. Groups and channels are added by chat ID.',
                'users' => 'Users',
                'roles' => 'Roles',
                'roles_help' => 'Users with any of these roles (spatie/laravel-permission).',
                'chat_ids' => 'Chat IDs',
                'chat_ids_help' => 'Group or channel IDs, e.g. -1001234567890 or @channel. Add the bot to the group first; send /chatid there to get the ID.',
                'chat_id_invalid' => '":value" is not a Telegram chat ID.',
                'telegram_connected' => 'Telegram connected',
                'telegram_not_connected' => 'no Telegram',
            ],
            'template' => [
                'heading' => 'Message',
                'template' => 'Template',
                'help' => 'Use the variables below. Allowed formatting: <b>, <i>, <code>, <a href="https://…">.',
                'variables' => 'Variables',
                'choose_model' => 'Choose a record type to see its variables.',
                'preview' => 'Preview',
                'preview_empty' => 'Choose a record type and write a template to see the preview.',
                'no_records' => 'There are no records yet, so the variables are empty in the preview.',
            ],
        ],

        'table' => [
            'name' => 'Name',
            'model' => 'Record type',
            'event' => 'Event',
            'is_active' => 'Active',
            'sent_last_7_days' => 'Sent, 7 days',
            'updated_at' => 'Updated',
        ],

        'test_message' => [
            'label' => 'Send test',
            'incomplete' => 'Choose a record type and write a template first.',
            'empty' => 'The message is empty on the latest record.',
            'not_connected' => 'Connect your Telegram to receive test messages.',
            'sent' => 'Test message sent to your Telegram.',
            'failed' => 'The test message could not be sent.',
        ],
    ],

    'deliveries' => [
        'model_label' => 'delivery',
        'plural_model_label' => 'delivery log',

        'statuses' => [
            'pending' => 'Pending',
            'sent' => 'Sent',
            'failed' => 'Failed',
        ],

        'fields' => [
            'created_at' => 'Time',
            'rule' => 'Rule',
            'no_rule' => 'Test message',
            'recipient' => 'Recipient',
            'status' => 'Status',
            'attempts' => 'Attempts',
            'error' => 'Error',
            'sent_at' => 'Sent at',
            'message' => 'Message',
        ],

        'filters' => [
            'from' => 'From',
            'until' => 'Until',
        ],

        'actions' => [
            'resend' => 'Resend',
            'resend_failed' => 'Resend failed',
        ],

        'notifications' => [
            'resent' => '{0} No failed deliveries selected.|{1} Delivery queued again.|[2,*] :count deliveries queued again.',
        ],
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
