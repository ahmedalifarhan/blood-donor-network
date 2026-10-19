<?php

/*
| Tables and the columns the app relies on (data-model.md, FR-033). The
| migrations create them; the health probe reports any that are missing,
| which means the migrations have not run. Later features add tables here.
*/

return [
    'settings' => ['id', 'key', 'value', 'updated_at', 'updated_by'],
    'scheduler_steps' => [
        'id', 'step_name', 'last_period', 'last_run_at', 'last_outcome',
        'last_error', 'last_success_at', 'consecutive_failures',
    ],
    'operational_logs' => [
        'id', 'level', 'event', 'actor_type', 'actor_ref', 'context', 'ip', 'created_at',
    ],
];
