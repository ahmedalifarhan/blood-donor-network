<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The API stores no user files (BUSINESS_RULES BV-04: evidence is never
    | uploaded). Only a private local disk exists, for framework internals.
    | It is never served over HTTP, so no /storage routes are registered.
    |
    */

    'default' => 'local',

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [],

];
