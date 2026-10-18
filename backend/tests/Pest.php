<?php

use App\Support\Backoff;
use Tests\TestCase;

/*
| Every test runs inside the Laravel test case so the container, config,
| and cache are available. The Sheets contract cases are tagged with the
| "sheets" group and excluded by default (composer test).
*/
pest()->extend(TestCase::class)->in('Unit', 'Feature', 'Contract');

/**
 * Keeps retry behavior but skips the real sleeps, so failure scenarios run instantly.
 * Call before the storage layer is first resolved in a test.
 */
function withoutBackoffDelays(): void
{
    app()->instance(Backoff::class, new Backoff(
        sleeper: static function (int $ms): void {},
        jitter: static fn (): int => 0,
    ));
}
