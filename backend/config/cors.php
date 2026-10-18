<?php

/*
| Only the public status endpoint is called from browsers, and only by the
| frontend origin (FR-029). The scheduler and operator endpoints are called
| server-to-server or directly, so they get no CORS headers at all.
*/
return [

    'paths' => ['api/v1/health'],

    'allowed_methods' => ['GET'],

    'allowed_origins' => array_filter([env('FRONTEND_URL')]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
