<?php

return [
    'token' => env('TELEGRAM_TOKEN'),

    'admin_telegram_id' => env('TELEGRAM_ADMIN_ID'),

    'admin_bot_token' => env('TELEGRAM_ADMIN_BOT_TOKEN'),

    'safe_mode' => env('APP_ENV', 'local') === 'production',

    'config' => [],

    'routes' => true,

    'mixins' => false,

    'namespace' => app_path('Telegram'),

    'log_channel' => env('TELEGRAM_LOG_CHANNEL', 'null'),
];