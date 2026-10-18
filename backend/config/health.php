<?php

return [

    // Password for the operator health view (HTTP Basic, username "operator").
    'operator_secret' => env('OPERATOR_HEALTH_SECRET'),

    // Seconds the store probe result is reused (spec clarification Q2).
    'probe_cache_seconds' => 60,

];
