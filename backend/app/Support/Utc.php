<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** All stored times are UTC ISO 8601 with a Z suffix (FR-021). */
final class Utc
{
    public const FORMAT = 'Y-m-d\TH:i:s\Z';

    public static function now(): string
    {
        return CarbonImmutable::now('UTC')->format(self::FORMAT);
    }
}
