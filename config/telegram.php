<?php

return [

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'CorsPortMain_bot'),

    'auth_max_age' => (int) env('TELEGRAM_AUTH_MAX_AGE', 86400),

];
