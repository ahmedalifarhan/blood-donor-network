<?php

use App\Models\Setting;
use App\Repositories\Eloquent\EloquentSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

// storage-contracts.md C1, C2.

uses(RefreshDatabase::class);

it('returns no overrides for an empty store', function () {
    expect((new EloquentSettingsRepository)->allOverrides())->toBe([]);
});

it('keeps one row per key and the latest value (C2)', function () {
    $repo = new EloquentSettingsRepository;

    $repo->upsert('WaveSize', '6', 'owner');
    $firstId = Setting::query()->sole()->id;
    $repo->upsert('WaveSize', '7', 'co-admin');

    $row = Setting::query()->sole();
    expect($repo->allOverrides())->toBe(['WaveSize' => '7'])
        ->and($row->id)->toBe($firstId)
        ->and($row->updated_by)->toBe('co-admin');
});

it('stores keys with dots and plus signs exactly', function () {
    $repo = new EloquentSettingsRepository;

    $repo->upsert('WaveIntervalMinutes.Emergency', '5', 'owner');
    $repo->upsert('ShortageThreshold.A+', '9', 'owner');

    expect($repo->allOverrides())->toBe([
        'WaveIntervalMinutes.Emergency' => '5',
        'ShortageThreshold.A+' => '9',
    ]);
});

it('reads values back byte-identical (C1)', function (string $value) {
    $repo = new EloquentSettingsRepository;

    $repo->upsert('Probe', $value, 'owner');

    expect($repo->allOverrides()['Probe'])->toBe($value);
})->with([
    'long digits' => '201012345678',
    'formula' => '=SUM(A1)',
    'plus' => '+201012345678',
    'minus' => '-5',
    'at' => '@user',
    'quote' => "'quoted",
    'double quote' => "''double",
    'empty quotes' => '""',
    'arabic' => 'عرب مطير',
    'emoji' => '🩸',
    '5,000 chars' => str_repeat('x', 5000),
]);
