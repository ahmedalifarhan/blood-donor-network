<?php

/*
| Tabs and their header rows, in order (data-model.md, FR-033).
| `php artisan store:setup` creates missing tabs and appends missing headers
| at the end. Later features add their tabs here.
*/
return [

    'Settings' => ['ID', 'Key', 'Value', 'UpdatedAt', 'UpdatedBy'],

    'SchedulerSteps' => [
        'ID', 'StepName', 'LastPeriod', 'LastRunAt', 'LastOutcome',
        'LastError', 'LastSuccessAt', 'ConsecutiveFailures',
    ],

    'OperationalLogs' => [
        'ID', 'Level', 'Event', 'ActorType', 'ActorRef', 'Context', 'IP', 'CreatedAt',
    ],

];
