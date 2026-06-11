<?php

return [

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'auth_max_age' => (int) env('TELEGRAM_AUTH_MAX_AGE', 86400),

];
