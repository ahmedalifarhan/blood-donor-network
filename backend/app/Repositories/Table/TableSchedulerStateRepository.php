<?php

namespace App\Repositories\Table;

use App\Repositories\Contracts\SchedulerStateRepository;
use App\Repositories\Data\StepState;
use App\Support\Ids;
use App\Support\Masking;

final class TableSchedulerStateRepository implements SchedulerStateRepository
{
    private const TAB = 'SchedulerSteps';

    public function __construct(private readonly Table $table) {}

    public function find(string $step): ?StepState
    {
        foreach ($this->table->rows(self::TAB) as $row) {
            if (($row['StepName'] ?? '') === $step) {
                return $this->toState($row);
            }
        }

        return null;
    }

    public function all(): array
    {
        $states = array_map(
            fn (array $row): StepState => $this->toState($row),
            array_values(array_filter(
                $this->table->rows(self::TAB),
                static fn (array $row): bool => ($row['StepName'] ?? '') !== '',
            )),
        );

        usort($states, static fn (StepState $a, StepState $b): int => strcmp($a->stepName, $b->stepName));

        return $states;
    }

    public function save(StepState $state): void
    {
        $error = $state->lastError === null
            ? ''
            : Masking::text(mb_substr($state->lastError, 0, StepState::MAX_ERROR_LENGTH));

        $this->table->upsert(
            self::TAB,
            'StepName',
            [
                'StepName' => $state->stepName,
                'LastPeriod' => $state->lastPeriod ?? '',
                'LastRunAt' => $state->lastRunAt ?? '',
                'LastOutcome' => $state->lastOutcome ?? '',
                'LastError' => $error,
                'LastSuccessAt' => $state->lastSuccessAt ?? '',
                'ConsecutiveFailures' => (string) $state->consecutiveFailures,
            ],
            ['ID' => Ids::new()],
        );
    }

    /** @param array<string, string> $row */
    private function toState(array $row): StepState
    {
        $outcome = $row['LastOutcome'] ?? '';

        return new StepState(
            stepName: $row['StepName'] ?? '',
            id: self::nullable($row['ID'] ?? ''),
            lastPeriod: self::nullable($row['LastPeriod'] ?? ''),
            lastRunAt: self::nullable($row['LastRunAt'] ?? ''),
            lastOutcome: in_array($outcome, ['success', 'failure'], true) ? $outcome : null,
            lastError: self::nullable($row['LastError'] ?? ''),
            lastSuccessAt: self::nullable($row['LastSuccessAt'] ?? ''),
            consecutiveFailures: max(0, (int) ($row['ConsecutiveFailures'] ?? '0')),
        );
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
