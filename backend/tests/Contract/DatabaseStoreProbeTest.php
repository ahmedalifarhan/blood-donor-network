<?php

use App\Repositories\Eloquent\DatabaseStoreProbe;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const PROBE_SCHEMA = [
    'settings' => ['id', 'key', 'value', 'updated_at', 'updated_by'],
    'scheduler_steps' => ['id', 'step_name', 'last_period'],
];

it('reports a migrated store as reachable and complete', function () {
    $result = (new DatabaseStoreProbe(app('db'), PROBE_SCHEMA))->check();

    expect($result->reachable)->toBeTrue()
        ->and($result->structureComplete)->toBeTrue()
        ->and($result->missing)->toBe([]);
});

it('names missing tables and columns', function () {
    Schema::drop('scheduler_steps');
    $schema = [...PROBE_SCHEMA, 'settings' => [...PROBE_SCHEMA['settings'], 'future_column']];

    $result = (new DatabaseStoreProbe(app('db'), $schema))->check();

    expect($result->reachable)->toBeTrue()
        ->and($result->structureComplete)->toBeFalse()
        ->and($result->missing)->toBe(['settings.future_column', 'scheduler_steps']);
});

it('reports an unreachable store without throwing', function () {
    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('connection')->andThrow(new PDOException('connection refused'));

    $result = (new DatabaseStoreProbe($db, PROBE_SCHEMA))->check();

    expect($result->reachable)->toBeFalse()
        ->and($result->structureComplete)->toBeFalse();
});
