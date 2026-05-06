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

    /*
    |--------------------------------------------------------------------------
    | Save-time warnings
    |--------------------------------------------------------------------------
    |
    | Rules evaluated against the proposed key/value state of an environment
    | when the user attempts to save. Warnings do not block the save — the
    | user must explicitly acknowledge them via the confirmation modal.
    |
    | Rule types:
    |   - value_warning: warns when `key` has any of the listed `values`,
    |     scoped optionally by `only_in` / `except_in` env identifiers
    |     (matched case-insensitively against the environment's slug,
    |     type name, or label).
    |   - unknown_value: warns when `key` is set to a value not in `allowed`.
    |   - requires_companions: warns when `key` equals `value` but one or
    |     more of the `requires` companion keys is missing or empty.
    |   - empty_value: warns when `key` is missing or set to an empty string,
    |     scoped optionally by `only_in` / `except_in`.
    |   - placeholder_value: warns when a value matches one of the listed
    |     `placeholders` (case-insensitive). If `key` is set, only that key
    |     is checked. If `key` is null/omitted, every value in the env is
    |     scanned and a warning is produced for each match.
    |
    */
    'warnings' => [
        [
            'type' => 'value_warning',
            'key' => 'APP_DEBUG',
            'values' => ['true', '1'],
            'except_in' => ['local', 'development', 'dev'],
            'message' => 'APP_DEBUG is enabled outside of a local environment. This exposes stack traces and other sensitive debug information to end users.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'APP_ENV',
            'values' => ['local'],
            'except_in' => ['local', 'development', 'dev'],
            'message' => 'APP_ENV is set to "local" outside of a local environment. Many Laravel features (debug pages, asset bundling) gate on this value.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'LOG_LEVEL',
            'values' => ['debug'],
            'except_in' => ['local', 'development', 'dev', 'testing', 'test'],
            'message' => 'LOG_LEVEL=debug outside of local environments can flood logs with sensitive payloads.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'SESSION_SECURE_COOKIE',
            'values' => ['false', '0'],
            'only_in' => ['production', 'prod', 'staging'],
            'message' => 'SESSION_SECURE_COOKIE should be true in production so session cookies require HTTPS.',
        ],
        [
            'type' => 'unknown_value',
            'key' => 'MAIL_MAILER',
            'allowed' => ['smtp', 'sendmail', 'mailgun', 'ses', 'postmark', 'resend', 'cloudflare', 'log', 'array', 'failover', 'roundrobin'],
            'message' => 'MAIL_MAILER has an unrecognized value. Common drivers: smtp, mailgun, ses, postmark, resend, log.',
        ],
        [
            'type' => 'unknown_value',
            'key' => 'QUEUE_CONNECTION',
            'allowed' => ['sync', 'database', 'redis', 'beanstalkd', 'sqs', 'null'],
            'message' => 'QUEUE_CONNECTION has an unrecognized value. Common values: sync, database, redis, sqs.',
        ],
        [
            'type' => 'unknown_value',
            'key' => 'CACHE_STORE',
            'allowed' => ['array', 'database', 'file', 'memcached', 'redis', 'dynamodb', 'octane', 'apc', 'null'],
            'message' => 'CACHE_STORE has an unrecognized value.',
        ],
        [
            'type' => 'unknown_value',
            'key' => 'BROADCAST_CONNECTION',
            'allowed' => ['reverb', 'pusher', 'ably', 'log', 'null'],
            'message' => 'BROADCAST_CONNECTION has an unrecognized value.',
        ],
        [
            'type' => 'requires_companions',
            'key' => 'MAIL_MAILER',
            'value' => 'mailgun',
            'requires' => ['MAILGUN_DOMAIN', 'MAILGUN_SECRET'],
            'message' => 'mailgun mail driver requires MAILGUN_DOMAIN and MAILGUN_SECRET.',
        ],
        [
            'type' => 'requires_companions',
            'key' => 'MAIL_MAILER',
            'value' => 'ses',
            'requires' => ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION'],
            'message' => 'ses mail driver requires AWS credentials (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION).',
        ],
        [
            'type' => 'requires_companions',
            'key' => 'MAIL_MAILER',
            'value' => 'postmark',
            'requires' => ['POSTMARK_TOKEN'],
            'message' => 'postmark mail driver requires POSTMARK_TOKEN.',
        ],
        [
            'type' => 'requires_companions',
            'key' => 'MAIL_MAILER',
            'value' => 'resend',
            'requires' => ['RESEND_KEY'],
            'message' => 'resend mail driver requires RESEND_KEY.',
        ],
        [
            'type' => 'requires_companions',
            'key' => 'BROADCAST_CONNECTION',
            'value' => 'pusher',
            'requires' => ['PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'PUSHER_APP_CLUSTER'],
            'message' => 'pusher broadcast connection requires PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET, and PUSHER_APP_CLUSTER.',
        ],
        [
            'type' => 'requires_companions',
            'key' => 'BROADCAST_CONNECTION',
            'value' => 'reverb',
            'requires' => ['REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET'],
            'message' => 'reverb broadcast connection requires REVERB_APP_ID, REVERB_APP_KEY, and REVERB_APP_SECRET.',
        ],
        [
            'type' => 'empty_value',
            'key' => 'APP_KEY',
            'message' => 'APP_KEY is empty. Laravel uses this for cookie/session encryption — leaving it blank breaks signed URLs, password resets, and anything encrypted.',
        ],
        [
            'type' => 'empty_value',
            'key' => 'DB_PASSWORD',
            'only_in' => ['production', 'prod', 'staging'],
            'message' => 'DB_PASSWORD is empty in a non-local environment. Make sure your database does not accept passwordless connections.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'APP_URL',
            'values' => ['http://localhost', 'http://127.0.0.1', 'https://localhost', 'https://127.0.0.1', 'http://localhost/', 'http://127.0.0.1/'],
            'except_in' => ['local', 'development', 'dev'],
            'message' => 'APP_URL points at localhost outside of a local environment. Mailable links, signed URLs, and OAuth callbacks will be unreachable.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'SESSION_DRIVER',
            'values' => ['array'],
            'except_in' => ['local', 'development', 'dev', 'testing', 'test'],
            'message' => 'SESSION_DRIVER=array discards sessions on every request. Use database, redis, or file in non-local environments.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'CACHE_STORE',
            'values' => ['array'],
            'except_in' => ['local', 'development', 'dev', 'testing', 'test'],
            'message' => 'CACHE_STORE=array does not persist between requests. Use database, redis, or memcached in non-local environments.',
        ],
        [
            'type' => 'value_warning',
            'key' => 'LOG_CHANNEL',
            'values' => ['single'],
            'only_in' => ['production', 'prod', 'staging'],
            'message' => 'LOG_CHANNEL=single writes to one unrotated file. Use stack/daily in production to keep log volume manageable.',
        ],
        [
            'type' => 'placeholder_value',
            'key' => 'MAIL_FROM_ADDRESS',
            'placeholders' => ['hello@example.com', 'noreply@example.com', 'you@example.com', 'test@example.com'],
            'message' => 'MAIL_FROM_ADDRESS is still a placeholder. Outgoing mail will appear to come from example.com.',
        ],
        [
            'type' => 'placeholder_value',
            'placeholders' => ['changeme', 'change-me', 'your-key-here', 'your-secret-here', 'your-token-here', 'xxx', 'xxxx', 'xxxxx', 'todo', 'tbd', 'replace-me', 'placeholder'],
            'message' => 'Value looks like a placeholder. Replace with a real value before saving.',
        ],
    ],
];
