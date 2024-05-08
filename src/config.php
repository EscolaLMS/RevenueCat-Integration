<?php

return [
    'webhooks' => [
        'auth' => [
            'enabled' => env('REVENUECAT_AUTH_ENABLED', false),
            'key' => env('REVENUECAT_AUTH_KEY'),
        ]
    ]
];
