<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Scheduler step state (data-model.md §2). Used only by the scheduler state repository.
 *
 * @property string $id
 * @property string $step_name
 * @property string|null $last_period
 * @property CarbonImmutable|null $last_run_at
 * @property string|null $last_outcome
 * @property string|null $last_error
 * @property CarbonImmutable|null $last_success_at
 * @property int $consecutive_failures
 */
#[Table('scheduler_steps', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
    'id', 'step_name', 'last_period', 'last_run_at', 'last_outcome',
    'last_error', 'last_success_at', 'consecutive_failures',
])]
final class SchedulerStep extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_run_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
        ];
    }
}
