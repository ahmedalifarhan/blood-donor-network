<?php

use App\Repositories\Contracts\StoreProbe;
use App\Repositories\InMemory\InMemoryCellBackend;
use App\Repositories\Table\CellBackend;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

// FR-033 and contracts/operator-commands.md. Memory driver (phpunit.xml).

beforeEach(fn () => withoutBackoffDelays());

it('creates every missing tab on the first run', function () {
    $this->artisan('store:setup')
        ->expectsOutputToContain('+ tab Settings')
        ->expectsOutputToContain('+ tab SchedulerSteps')
        ->expectsOutputToContain('+ tab OperationalLogs')
        ->assertExitCode(0);

    expect(app(StoreProbe::class)->check()->structureComplete)->toBeTrue();
});

it('reports no changes on the second run', function () {
    $this->artisan('store:setup')->assertExitCode(0);

    $this->artisan('store:setup')
        ->expectsOutputToContain('✔ no changes')
        ->assertExitCode(0);
});

it('writes nothing with --dry-run', function () {
    $this->artisan('store:setup --dry-run')
        ->expectsOutputToContain('+ tab Settings')
        ->assertExitCode(0);

    expect(app(StoreProbe::class)->check()->missing)->toContain('Settings');
});

it('prints a JSON change list with --json', function () {
    $this->artisan('store:setup --json')
        ->expectsOutputToContain('"kind":"tab","name":"Settings"')
        ->assertExitCode(0);
});

it('exits 1 when the store is unreachable', function () {
    app(CellBackend::class)->failNext(20, 503);

    $this->artisan('store:setup')->assertExitCode(1);
});

it('exits 2 and names the missing store configuration', function () {
    config([
        'store.driver' => 'sheets',
        'store.sheets.spreadsheet_id' => null,
        'store.sheets.credentials' => '',
    ]);

    $this->artisan('store:setup')
        ->expectsOutputToContain('GOOGLE_SHEET_ID')
        ->expectsOutputToContain('GOOGLE_SERVICE_ACCOUNT_JSON')
        ->assertExitCode(2);
});

it('asks for confirmation before changing a non-sandbox store', function () {
    app()->instance(CellBackend::class, new InMemoryCellBackend(
        app(CacheFactory::class)->store(),
        'Blood Donor Network [production]',
    ));

    $this->artisan('store:setup')
        ->expectsConfirmation('The spreadsheet "Blood Donor Network [production]" is not a sandbox store. Apply the changes?', 'no')
        ->assertExitCode(3);

    expect(app(StoreProbe::class)->check()->missing)->toContain('Settings');

    $this->artisan('store:setup --force')
        ->expectsOutputToContain('+ tab Settings')
        ->assertExitCode(0);
});
