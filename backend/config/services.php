<?php

return [
    'oranos' => [
        'url' => env('ORANOS_API_URL', 'https://api.oranosmarket.com'),
        'token' => env('ORANOS_API_TOKEN'),
        'markup' => env('ORANOS_MARKUP', 1.20),
    ],
    'binance_pay' => [
        'key' => env('BINANCE_PAY_KEY'),
        'secret' => env('BINANCE_PAY_SECRET'),
    ],
    'usdt' => [
        'wallet' => env('USDT_WALLET_ADDRESS'),
        'secret' => env('USDT_WEBHOOK_SECRET'),
    ],
    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
    ],
];
