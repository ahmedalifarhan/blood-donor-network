<?php

namespace App\Support;

use RuntimeException;
use Throwable;

/**
 * A single failed request to the data store, carrying its HTTP status so
 * Backoff can decide whether to retry.
 */
final class StoreRequestFailed extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}
