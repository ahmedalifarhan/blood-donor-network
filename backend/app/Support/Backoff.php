<?php

namespace App\Support;

use Closure;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\ResponseException;
use Throwable;

/**
 * Truncated exponential backoff for store calls (research R3).
 *
 * Retries HTTP 429 and 5xx with a delay of 2^n seconds plus up to 1 s of
 * jitter, at most 3 times and at most 8 s of total waiting, because the
 * external cron closes each scheduler call after 30 s.
 */
final class Backoff
{
    public const MAX_RETRIES = 3;

    public const BUDGET_MS = 8000;

    /** @var Closure(int): void */
    private Closure $sleeper;

    /** @var Closure(): int */
    private Closure $jitter;

    /**
     * @param  (callable(int): void)|null  $sleeper  receives milliseconds
     * @param  (callable(): int)|null  $jitter  returns 0–1000 milliseconds
     */
    public function __construct(?callable $sleeper = null, ?callable $jitter = null)
    {
        $this->sleeper = $sleeper !== null
            ? Closure::fromCallable($sleeper)
            : static function (int $ms): void {
                usleep($ms * 1000);
            };

        $this->jitter = $jitter !== null
            ? Closure::fromCallable($jitter)
            : static fn (): int => random_int(0, 1000);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     *
     * @throws StoreUnavailable
     */
    public function run(callable $operation): mixed
    {
        $slept = 0;

        for ($attempt = 0; ; $attempt++) {
            try {
                return $operation();
            } catch (StoreUnavailable $e) {
                throw $e;
            } catch (Throwable $e) {
                if (! self::isRetryable(self::statusOf($e)) || $attempt >= self::MAX_RETRIES) {
                    throw StoreUnavailable::after($e);
                }

                $delay = (2 ** $attempt) * 1000 + ($this->jitter)();

                if ($slept + $delay > self::BUDGET_MS) {
                    throw StoreUnavailable::after($e);
                }

                ($this->sleeper)($delay);
                $slept += $delay;
            }
        }
    }

    public static function statusOf(Throwable $e): int
    {
        return match (true) {
            $e instanceof StoreRequestFailed => $e->status,
            $e instanceof GoogleServiceException => (int) $e->getCode(),
            $e instanceof ResponseException => $e->getResponse()->getStatusCode(),
            default => 0,
        };
    }

    private static function isRetryable(int $status): bool
    {
        return $status === 429 || ($status >= 500 && $status <= 599);
    }
}
