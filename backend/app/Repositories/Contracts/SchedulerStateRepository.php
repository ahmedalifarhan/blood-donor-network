<?php

namespace App\Repositories\Contracts;

use App\Repositories\Data\StepState;
use App\Support\StoreUnavailable;

interface SchedulerStateRepository
{
    /** @throws StoreUnavailable */
    public function find(string $step): ?StepState;

    /**
     * @return list<StepState> ordered by step name
     *
     * @throws StoreUnavailable
     */
    public function all(): array;

    /**
     * Insert or update by unique step name, under the write lock.
     *
     * @throws StoreUnavailable
     */
    public function save(StepState $state): void;
}
