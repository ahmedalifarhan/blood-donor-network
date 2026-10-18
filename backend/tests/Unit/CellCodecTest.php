<?php

use App\Support\CellCodec;

// FR-018, research R4: formula-safe and exactly reversible.

it('prefixes one apostrophe when a value starts with a risky character', function (string $raw) {
    expect(CellCodec::encode($raw))->toBe("'".$raw);
})->with([
    'equals' => '=SUM(A1)',
    'plus' => '+201012345678',
    'minus' => '-5',
    'at' => '@user',
    'apostrophe' => "'quoted",
    'tab' => "\tindented",
    'carriage return' => "\rline",
]);

it('leaves safe values unchanged when encoding', function (string $raw) {
    expect(CellCodec::encode($raw))->toBe($raw);
})->with([
    'digits' => '201012345678',
    'arabic' => 'عرب مطير',
    'empty' => '',
    'emoji' => '🩸',
    'inner equals' => 'a=b',
]);

it('removes exactly one leading apostrophe when decoding', function () {
    expect(CellCodec::decode("'=SUM(A1)"))->toBe('=SUM(A1)')
        ->and(CellCodec::decode("''double"))->toBe("'double")
        ->and(CellCodec::decode('plain'))->toBe('plain')
        ->and(CellCodec::decode(''))->toBe('');
});

it('round-trips every value byte for byte', function (string $raw) {
    expect(CellCodec::decode(CellCodec::encode($raw)))->toBe($raw);
})->with([
    '201012345678',
    '=SUM(A1)',
    '+201012345678',
    '-5',
    '@user',
    "'quoted",
    "''double",
    '',
    'عرب مطير',
    '🩸',
    'long' => str_repeat('ب', 5000),
]);
