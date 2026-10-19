<?php

return [
    /*
    | Required environment variables, mapped to the config key that holds each
    | value. ConfigCheck reads config (never env()), so it keeps working after
    | `php artisan config:cache`. Only the names are ever reported.
    */
    'required' => [
        'always' => [
            'SCHEDULER_SECRET' => 'scheduler.secret',
            'OPERATOR_HEALTH_SECRET' => 'health.operator_secret',
            'FRONTEND_URL' => 'app.frontend_url',
        ],
        // PostgreSQL (deployed): one connection URL from the database host.
        'pgsql' => [
            'DB_URL' => 'database.connections.pgsql.url',
        ],
    ],
];
