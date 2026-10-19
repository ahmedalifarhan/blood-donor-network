<?php

use App\Services\ConfigCheck;

// FR-031: names of missing required variables, for the active connection.

it('is complete with the test configuration', function () {
    expect((new ConfigCheck)->missing())->toBe([]);
});

it('names the missing secrets, never their values', function () {
    config(['scheduler.secret' => '  ', 'health.operator_secret' => null]);

    expect((new ConfigCheck)->missing())->toBe(['SCHEDULER_SECRET', 'OPERATOR_HEALTH_SECRET']);
});

it('requires DB_URL when the connection is PostgreSQL', function () {
    config(['database.default' => 'pgsql', 'database.connections.pgsql.url' => null]);
    expect((new ConfigCheck)->missing())->toBe(['DB_URL']);

    config(['database.connections.pgsql.url' => 'postgresql://user:pass@db.example:5432/app']);
    expect((new ConfigCheck)->isComplete())->toBeTrue();
});

it('does not require DB_URL for SQLite', function () {
    config(['database.default' => 'sqlite', 'database.connections.pgsql.url' => null]);

    expect((new ConfigCheck)->isComplete())->toBeTrue();
});
