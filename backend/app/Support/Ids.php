<?php

namespace App\Support;

use Symfony\Component\Uid\Ulid;

/** Random, time-sortable identifiers (FR-017). Never sequential numbers. */
final class Ids
{
    public static function new(): string
    {
        return Ulid::generate();
    }
}
