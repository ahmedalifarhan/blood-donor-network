<?php

return [

    /*
    | Storage driver (constitution V): "sheets" for deployed environments,
    | "memory" for local development and tests.
    */
    'driver' => env('DATA_DRIVER', 'memory'),

    'sheets' => [
        'spreadsheet_id' => env('GOOGLE_SHEET_ID'),
        // Raw JSON or base64-encoded JSON of the service-account key.
        'credentials' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
    ],

    // Seconds a whole tab stays in the read cache (research R2).
    'read_cache_seconds' => 60,

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
        'sheets' => [
            'GOOGLE_SHEET_ID' => 'store.sheets.spreadsheet_id',
            'GOOGLE_SERVICE_ACCOUNT_JSON' => 'store.sheets.credentials',
        ],
    ],

];
