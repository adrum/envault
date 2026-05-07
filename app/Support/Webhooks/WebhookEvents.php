<?php

namespace App\Support\Webhooks;

class WebhookEvents
{
    public const VARIABLE_CREATED = 'variable.created';

    public const VARIABLE_UPDATED = 'variable.updated';

    public const VARIABLE_DELETED = 'variable.deleted';

    public const VARIABLE_ROLLED_BACK = 'variable.rolled_back';

    public const VARIABLES_IMPORTED = 'variables.imported';

    public const ENVIRONMENT_CREATED = 'environment.created';

    public const ENVIRONMENT_DELETED = 'environment.deleted';

    public const APP_CREATED = 'app.created';

    public const APP_DELETED = 'app.deleted';

    public const TEST = 'webhook.test';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VARIABLE_CREATED,
            self::VARIABLE_UPDATED,
            self::VARIABLE_DELETED,
            self::VARIABLE_ROLLED_BACK,
            self::VARIABLES_IMPORTED,
            self::ENVIRONMENT_CREATED,
            self::ENVIRONMENT_DELETED,
            self::APP_CREATED,
            self::APP_DELETED,
        ];
    }
}
