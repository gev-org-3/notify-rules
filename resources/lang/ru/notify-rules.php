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

    'rules' => [
        'model_label' => 'правило уведомлений',
        'plural_model_label' => 'правила уведомлений',

        'form' => [
            'general' => [
                'heading' => 'Правило',
                'name' => 'Название',
                'is_active' => 'Активно',
            ],
            'trigger' => [
                'heading' => 'Когда',
                'description' => 'Тип записи и событие, при котором срабатывает правило.',
                'model' => 'Тип записи',
                'event' => 'Событие',
                'watched_field' => 'Поле',
            ],
            'conditions' => [
                'heading' => 'Условия',
                'description' => 'Без условий правило срабатывает на каждое событие. Условия «изменилось» срабатывают только при изменении записи.',
                'match' => 'Совпадение',
                'match_all' => 'Все условия',
                'match_any' => 'Любое условие',
                'add' => 'Добавить условие',
                'field' => 'Поле',
                'operator' => 'Оператор',
                'value' => 'Значение',
                'in_help' => 'Значения через запятую.',
            ],
            'recipients' => [
                'heading' => 'Получатели',
                'description' => 'Пользователи получают сообщения, когда подключат Telegram. Группы и каналы добавляются по ID чата.',
                'users' => 'Пользователи',
                'roles' => 'Роли',
                'roles_help' => 'Пользователи с любой из этих ролей (spatie/laravel-permission).',
                'chat_ids' => 'ID чатов',
                'chat_ids_help' => 'ID группы или канала, например -1001234567890 или @channel. Сначала добавьте бота в группу; чтобы узнать ID, отправьте там /chatid.',
                'chat_id_invalid' => '«:value» — не ID чата Telegram.',
                'telegram_connected' => 'Telegram подключён',
                'telegram_not_connected' => 'без Telegram',
                'users_invalid' => 'Некоторым из выбранных пользователей нельзя отправлять уведомления отсюда.',
            ],
            'template' => [
                'heading' => 'Сообщение',
                'template' => 'Шаблон',
                'help' => 'Используйте переменные ниже; добавьте |money, чтобы вывести сумму с валютой, например {{ total|money }} или {{ total|money:EUR }}. Разрешённое форматирование: <b>, <i>, <code>, <a href="https://…">.',
                'variables' => 'Переменные',
                'choose_model' => 'Выберите тип записи, чтобы увидеть переменные.',
                'preview' => 'Превью',
                'preview_empty' => 'Выберите тип записи и напишите шаблон, чтобы увидеть превью.',
                'no_records' => 'Записей пока нет, поэтому в превью переменные пустые.',
            ],
        ],

        'table' => [
            'name' => 'Название',
            'model' => 'Тип записи',
            'event' => 'Событие',
            'is_active' => 'Активно',
            'sent_last_7_days' => 'Отправлено за 7 дней',
            'updated_at' => 'Изменено',
        ],

        'test_message' => [
            'label' => 'Отправить тест',
            'incomplete' => 'Сначала выберите тип записи и напишите шаблон.',
            'empty' => 'На последней записи сообщение получается пустым.',
            'not_connected' => 'Подключите Telegram, чтобы получать тестовые сообщения.',
            'sent' => 'Тестовое сообщение отправлено в ваш Telegram.',
            'failed' => 'Не удалось отправить тестовое сообщение.',
        ],
    ],

    'deliveries' => [
        'model_label' => 'доставка',
        'plural_model_label' => 'журнал доставки',

        'statuses' => [
            'pending' => 'В очереди',
            'sent' => 'Отправлено',
            'failed' => 'Ошибка',
        ],

        'fields' => [
            'created_at' => 'Время',
            'rule' => 'Правило',
            'no_rule' => 'Тестовое сообщение',
            'recipient' => 'Получатель',
            'status' => 'Статус',
            'attempts' => 'Попытки',
            'error' => 'Ошибка',
            'sent_at' => 'Отправлено',
            'message' => 'Сообщение',
        ],

        'filters' => [
            'from' => 'С',
            'until' => 'По',
        ],

        'actions' => [
            'resend' => 'Повторить',
            'resend_failed' => 'Повторить неудачные',
        ],

        'notifications' => [
            'resent' => '{0} Среди выбранных нет неудачных доставок.|{1} Доставка снова поставлена в очередь.|[2,*] Снова в очереди: :count.',
        ],
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
