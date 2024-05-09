<?php

return [
    'api_v1_key' => env('REVENUECAT_API_V1_KEY'),
    'webhooks' => [
        'auth' => [
            'enabled' => env('REVENUECAT_AUTH_ENABLED', false),
            'key' => env('REVENUECAT_AUTH_KEY'),
        ]
    ]
];
