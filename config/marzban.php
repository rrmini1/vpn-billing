<?php

return [
    'base_url' => env('MARZBAN_BASE_URL', 'https://panel.cors-port.ru'),
    'username' => env('MARZBAN_USERNAME'),
    'password' => env('MARZBAN_PASSWORD'),
    'timeout' => (int) env('MARZBAN_TIMEOUT', 10),
    'inbound' => env('MARZBAN_INBOUND', 'VLESS TCP REALITY'),
    'proxy_type' => env('MARZBAN_PROXY_TYPE', 'vless'),
    'data_limit_reset_strategy' => env('MARZBAN_DATA_LIMIT_RESET_STRATEGY', 'no_reset'),
];
