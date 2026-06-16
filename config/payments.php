<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'mock'),

    'mock' => [
        'expires_in_minutes' => env('MOCK_PAYMENT_EXPIRES_IN_MINUTES', 30),
    ],

    'yookassa' => [
        'base_url' => env('YOOKASSA_BASE_URL', 'https://api.yookassa.ru/v3'),
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'return_url' => env('YOOKASSA_RETURN_URL', env('FRONTEND_URL', env('APP_URL')).'/app/payments'),
        'timeout' => env('YOOKASSA_TIMEOUT', 10),
    ],
];
