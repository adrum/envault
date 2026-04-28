<?php

return [
    'bootstrap_user' => [
        'email' => env('USER_EMAIL'),
        'first_name' => env('USER_FIRST_NAME'),
        'last_name' => env('USER_LAST_NAME'),
        'role' => env('USER_ROLE', 'user'),
    ],

    'features' => [
        'json_mode' => env('FEATURE_JSON_MODE', false),
    ],
];
