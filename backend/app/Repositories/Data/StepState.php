<?php

namespace App\Repositories\Data;

/**
 * One row of the SchedulerSteps tab (data-model.md §2).
 */
final readonly class StepState
{
    public const MAX_ERROR_LENGTH = 500;

    /**
     * @param  'success'|'failure'|null  $lastOutcome
     * @param  string|null  $lastError  ≤ 500 chars; masked
     */
    public function __construct(
        public string $stepName,
        public ?string $id = null,
        public ?string $lastPeriod = null,
        public ?string $lastRunAt = null,
        public ?string $lastOutcome = null,
        public ?string $lastError = null,
        public ?string $lastSuccessAt = null,
        public int $consecutiveFailures = 0,
    ) {}
}
