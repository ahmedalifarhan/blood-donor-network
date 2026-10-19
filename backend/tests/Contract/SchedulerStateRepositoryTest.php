<?php

use App\Models\SchedulerStep;
use App\Repositories\Data\StepState;
use App\Repositories\Eloquent\EloquentSchedulerStateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

// storage-contracts.md C3, C8.

uses(RefreshDatabase::class);

it('returns null for an unknown step', function () {
    expect((new EloquentSchedulerStateRepository)->find('heartbeat'))->toBeNull();
});

it('keeps one row per step across insert and update (C3)', function () {
    $repo = new EloquentSchedulerStateRepository;

    $repo->save(new StepState('heartbeat', lastOutcome: 'failure', lastError: 'boom', consecutiveFailures: 1));
    $firstId = SchedulerStep::query()->sole()->id;
    $repo->save(new StepState('heartbeat', lastPeriod: '2026-10-19T10:15Z', lastOutcome: 'success'));

    $state = $repo->find('heartbeat');
    expect(SchedulerStep::query()->count())->toBe(1)
        ->and($state?->id)->toBe($firstId)
        ->and($state?->lastPeriod)->toBe('2026-10-19T10:15Z')
        ->and($state?->lastOutcome)->toBe('success')
        ->and($state?->lastError)->toBeNull()
        ->and($state?->consecutiveFailures)->toBe(0);
});

it('reads timestamps back as unchanged UTC Z strings (C8)', function () {
    $repo = new EloquentSchedulerStateRepository;

    $repo->save(new StepState(
        'heartbeat',
        lastRunAt: '2026-10-19T10:15:00Z',
        lastSuccessAt: '2026-10-19T10:15:02Z',
    ));

    $state = $repo->find('heartbeat');
    expect($state?->lastRunAt)->toBe('2026-10-19T10:15:00Z')
        ->and($state?->lastSuccessAt)->toBe('2026-10-19T10:15:02Z');
});

it('masks and truncates the last error to 500 characters', function () {
    $repo = new EloquentSchedulerStateRepository;

    $repo->save(new StepState('heartbeat', lastError: 'call 201012345678 failed '.str_repeat('x', 600)));

    $error = $repo->find('heartbeat')?->lastError ?? '';
    expect($error)->toContain('2010****5678')
        ->not->toContain('201012345678')
        ->and(mb_strlen($error))->toBeLessThanOrEqual(StepState::MAX_ERROR_LENGTH);
});

it('keeps the last error within 500 characters when masking lengthens it', function () {
    $repo = new EloquentSchedulerStateRepository;

    // Exactly 500 chars ending in a 9-digit run; masking turns the 9 digits into 12 (503 chars).
    $repo->save(new StepState('heartbeat', lastError: str_repeat('x', 491).'123456789'));

    $error = $repo->find('heartbeat')?->lastError ?? '';
    expect(mb_strlen($error))->toBe(StepState::MAX_ERROR_LENGTH)
        ->and($error)->not->toContain('123456789');
});

it('lists every step ordered by name', function () {
    $repo = new EloquentSchedulerStateRepository;

    foreach (['zeta', 'alpha', 'heartbeat'] as $name) {
        $repo->save(new StepState($name));
    }

    expect(array_map(static fn (StepState $s): string => $s->stepName, $repo->all()))
        ->toBe(['alpha', 'heartbeat', 'zeta']);
});
