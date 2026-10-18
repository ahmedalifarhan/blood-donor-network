<?php

namespace App\Support;

use RuntimeException;
use Throwable;

/**
 * The data store could not be used, even after retries. The message never
 * carries store details; the cause is kept as the previous exception.
 */
final class StoreUnavailable extends RuntimeException
{
    public static function after(Throwable $cause): self
    {
        return new self('The data store is unavailable.', 0, $cause);
    }
}
