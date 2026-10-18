<?php

namespace App\Support;

/**
 * Reversible, formula-safe cell encoding for the spreadsheet store (research R4).
 *
 * Values starting with a character a spreadsheet could treat as a formula
 * get one leading apostrophe; decoding removes exactly one. A leading
 * apostrophe is escaped too, so every value round-trips byte for byte.
 */
final class CellCodec
{
    private const RISKY_FIRST_CHARACTERS = ['=', '+', '-', '@', "'", "\t", "\r"];

    public static function encode(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::RISKY_FIRST_CHARACTERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    public static function decode(string $value): string
    {
        return str_starts_with($value, "'") ? substr($value, 1) : $value;
    }
}
