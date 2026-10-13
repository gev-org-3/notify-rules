<?php

declare(strict_types=1);

return [

    'telegram' => [
        'user_menu' => 'Telegram',

        'page' => [
            'title' => 'Уведомления в Telegram',
            'description' => 'Подключите Telegram, чтобы получать уведомления из этой панели.',

            'connected' => [
                'heading' => 'Подключено',
                'heading_with_username' => 'Подключено: @:username',
                'description' => 'Подключено :date.',
            ],

            'not_connected' => [
                'heading' => 'Не подключено',
                'description' => 'Нажмите «Подключить Telegram», затем «Старт» в открывшемся чате с ботом.',
            ],

            'pending' => [
                'heading' => 'Ожидаем подтверждения',
                'description' => 'Откройте бота в Telegram и нажмите «Старт». Ссылка действует :minutes минут и только один раз.',
                'open' => 'Открыть Telegram',
            ],

            'actions' => [
                'connect' => 'Подключить Telegram',
                'reconnect' => 'Подключить другой аккаунт',
                'disconnect' => 'Отключить',
                'disconnect_confirm' => 'Вы перестанете получать уведомления в Telegram.',
            ],

            'notifications' => [
                'disconnected' => 'Telegram отключён.',
                'not_configured' => 'Бот Telegram не настроен. Попросите администратора указать имя бота.',
            ],
        ],
    ],

    'bot' => [
        'connected' => 'Подключено ✅ Уведомления будут приходить сюда.',
        'link_invalid' => 'Ссылка недействительна или устарела. Создайте новую в админке.',
        'start_without_token' => 'Чтобы получать уведомления, откройте админку и нажмите «Подключить Telegram».',
        'chat_id' => 'ID чата: <code>:id</code>',
    ],

    'events' => [
        'created' => 'Создание',
        'updated' => 'Изменение',
        'deleted' => 'Удаление',
        'field_changed' => 'Изменение поля',
    ],

    'operators' => [
        'Equals' => 'равно',
        'NotEquals' => 'не равно',
        'GreaterThan' => 'больше',
        'GreaterThanOrEqual' => 'больше или равно',
        'LessThan' => 'меньше',
        'LessThanOrEqual' => 'меньше или равно',
        'Contains' => 'содержит',
        'In' => 'одно из',
        'IsEmpty' => 'пусто',
        'IsNotEmpty' => 'не пусто',
        'Changed' => 'изменилось',
        'ChangedTo' => 'изменилось на',
        'ChangedFrom' => 'изменилось с',
    ],

    'template' => [
        'yes' => 'Да',
        'no' => 'Нет',
    ],

];
