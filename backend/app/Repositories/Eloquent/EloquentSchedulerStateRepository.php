<?php

namespace App\Repositories\Eloquent;

use App\Models\SchedulerStep;
use App\Repositories\Contracts\SchedulerStateRepository;
use App\Repositories\Data\StepState;
use App\Support\Ids;
use App\Support\Masking;
use App\Support\Utc;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class EloquentSchedulerStateRepository implements SchedulerStateRepository
{
    use GuardsStoreAccess;

    public function find(string $step): ?StepState
    {
        $row = $this->guarded(static fn (): ?SchedulerStep => SchedulerStep::query()->where('step_name', $step)->first());

        return $row === null ? null : self::toState($row);
    }

    public function all(): array
    {
        $states = $this->guarded(static fn (): array => SchedulerStep::query()->get()->map(self::toState(...))->all());

        // Byte order, independent of the database collation.
        usort($states, static fn (StepState $a, StepState $b): int => strcmp($a->stepName, $b->stepName));

        return $states;
    }

    public function save(StepState $state): void
    {
        // Mask first, then cut: masking can lengthen a digit run (9 digits → 12 chars),
        // and the column holds at most 500 characters.
        $error = $state->lastError === null
            ? null
            : mb_substr(Masking::text($state->lastError), 0, StepState::MAX_ERROR_LENGTH);

        $values = [
            'last_period' => $state->lastPeriod,
            'last_run_at' => self::toTime($state->lastRunAt),
            'last_outcome' => $state->lastOutcome,
            'last_error' => $error,
            'last_success_at' => self::toTime($state->lastSuccessAt),
            'consecutive_failures' => max(0, $state->consecutiveFailures),
        ];

        // Atomic INSERT … ON CONFLICT (step_name): an existing row keeps its ID.
        $this->guarded(static fn (): int => SchedulerStep::query()->upsert(
            [['id' => Ids::new(), 'step_name' => $state->stepName, ...$values]],
            ['step_name'],
            array_keys($values),
        ));
    }

    private static function toState(SchedulerStep $row): StepState
    {
        $outcome = $row->last_outcome;

        return new StepState(
            stepName: $row->step_name,
            id: $row->id,
            lastPeriod: self::nullable($row->last_period),
            lastRunAt: self::fromTime($row->last_run_at),
            lastOutcome: $outcome === 'success' || $outcome === 'failure' ? $outcome : null,
            lastError: self::nullable($row->last_error),
            lastSuccessAt: self::fromTime($row->last_success_at),
            consecutiveFailures: max(0, $row->consecutive_failures),
        );
    }

    private static function toTime(?string $iso): ?CarbonImmutable
    {
        return self::nullable($iso) === null ? null : CarbonImmutable::parse($iso)->utc();
    }

    private static function fromTime(?DateTimeInterface $time): ?string
    {
        return $time === null ? null : CarbonImmutable::instance($time)->utc()->format(Utc::FORMAT);
    }

    private static function nullable(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
