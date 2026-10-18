<?php

use App\Support\Backoff;
use App\Support\StoreRequestFailed;
use App\Support\StoreUnavailable;

// Research R3: truncated exponential backoff, capped for the 30 s cron timeout.
// Note: arrow functions capture by value, so counters are bound with `use (&...)`.

function backoffWith(array &$sleeps, int $jitter = 500): Backoff
{
    return new Backoff(
        sleeper: function (int $ms) use (&$sleeps): void {
            $sleeps[] = $ms;
        },
        jitter: fn (): int => $jitter,
    );
}

function alwaysFailing(int &$calls, int $status): Closure
{
    return function () use (&$calls, $status): never {
        $calls++;
        throw new StoreRequestFailed('failed', $status);
    };
}

it('returns the result without sleeping when the first call succeeds', function () {
    $sleeps = [];
    expect(backoffWith($sleeps)->run(fn () => 'ok'))->toBe('ok')
        ->and($sleeps)->toBe([]);
});

it('retries 429 and 5xx with 2^n seconds plus jitter', function (int $status) {
    $sleeps = [];
    $calls = 0;

    $result = backoffWith($sleeps, jitter: 250)->run(function () use (&$calls, $status) {
        if (++$calls < 3) {
            throw new StoreRequestFailed('busy', $status);
        }

        return 'ok';
    });

    expect($result)->toBe('ok')
        ->and($calls)->toBe(3)
        ->and($sleeps)->toBe([1250, 2250]);
})->with([429, 500, 503]);

it('gives up with StoreUnavailable once the 8 second budget would be exceeded', function () {
    $sleeps = [];
    $calls = 0;
    $backoff = backoffWith($sleeps, jitter: 1000);
    $operation = alwaysFailing($calls, 429);

    expect(fn () => $backoff->run($operation))->toThrow(StoreUnavailable::class);

    // 2000 + 3000 = 5000 ms; the next delay (5000 ms) would pass 8000 ms, so it stops.
    expect($sleeps)->toBe([2000, 3000])
        ->and($calls)->toBe(3);
});

it('never retries more than 3 times', function () {
    $sleeps = [];
    $calls = 0;
    $backoff = backoffWith($sleeps, jitter: 0);
    $operation = alwaysFailing($calls, 503);

    expect(fn () => $backoff->run($operation))->toThrow(StoreUnavailable::class);

    expect($calls)->toBe(4)
        ->and($sleeps)->toBe([1000, 2000, 4000]);
});

it('does not retry other 4xx errors', function () {
    $sleeps = [];
    $calls = 0;
    $backoff = backoffWith($sleeps);
    $operation = alwaysFailing($calls, 403);

    expect(fn () => $backoff->run($operation))->toThrow(StoreUnavailable::class);

    expect($calls)->toBe(1)
        ->and($sleeps)->toBe([]);
});

it('keeps the original error as the previous exception', function () {
    $sleeps = [];
    $original = new StoreRequestFailed('forbidden', 403);

    try {
        backoffWith($sleeps)->run(fn () => throw $original);
    } catch (StoreUnavailable $e) {
        expect($e->getPrevious())->toBe($original);

        return;
    }

    $this->fail('StoreUnavailable was not thrown');
});
