<?php

return [

    // Shared secret sent by the external cron in the X-Scheduler-Secret header.
    'secret' => env('SCHEDULER_SECRET'),

    // Ordered list of step classes run by each tick (filled in by User Story 2).
    'steps' => [],

];
