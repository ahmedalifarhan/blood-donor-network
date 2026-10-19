<?php

use App\Repositories\Contracts\StoreProbe;
use App\Repositories\Data\ProbeResult;
use App\Repositories\Eloquent\DatabaseStoreProbe;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// US1: FR-003, FR-004, FR-006, FR-031, FR-033; spec clarification Q2 (60 s probe cache).
// RefreshDatabase runs the migrations, so every test starts with a ready store.

uses(RefreshDatabase::class);

it('reports running when config is complete and the store is ready', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'running')
        ->assertJsonStructure(['status', 'checkedAt']);
});

it('returns exactly status and checkedAt, nothing else', function () {

    $json = $this->getJson('/api/v1/health')->json();

    expect(array_keys($json))->toBe(['status', 'checkedAt'])
        ->and($json['checkedAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

it('reports limited when the store is unreachable', function () {
    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('connection')->andThrow(new PDOException('connection refused'));
    app()->instance(StoreProbe::class, new DatabaseStoreProbe($db, config('store-schema')));

    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'limited');
});

it('reports limited when the store structure is incomplete', function () {
    // As if the latest migration had not run yet.
    Schema::drop('operational_logs');

    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'limited');
});

it('reports limited when required configuration is missing', function () {
    config(['scheduler.secret' => null]);

    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'limited');
});

it('never reveals which configuration is missing', function () {
    config(['scheduler.secret' => null]);

    $body = $this->getJson('/api/v1/health')->getContent();

    expect($body)->not->toContain('SCHEDULER_SECRET')
        ->and($body)->not->toContain('missing');
});

it('tells search engines not to index it', function () {

    $this->getJson('/api/v1/health')->assertHeader('X-Robots-Tag', 'noindex');
});

it('allows only the frontend origin through CORS', function () {

    $this->getJson('/api/v1/health', ['Origin' => 'http://localhost:3000'])
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');

    // With a single allowed origin the header is fixed to it, so a browser on
    // any other origin sees a mismatch and blocks the response.
    $foreign = $this->getJson('/api/v1/health', ['Origin' => 'https://evil.example']);
    expect($foreign->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example')
        ->not->toBe('*');
});

it('checks the store at most once per 60 seconds', function () {

    $real = app(StoreProbe::class);
    $counting = new class($real) implements StoreProbe
    {
        public int $calls = 0;

        public function __construct(private readonly StoreProbe $inner) {}

        public function check(): ProbeResult
        {
            $this->calls++;

            return $this->inner->check();
        }
    };
    app()->instance(StoreProbe::class, $counting);

    for ($i = 0; $i < 50; $i++) {
        $this->getJson('/api/v1/health')->assertOk();
    }

    expect($counting->calls)->toBe(1);

    $this->travel(61)->seconds();
    $this->getJson('/api/v1/health')->assertOk();

    expect($counting->calls)->toBe(2);
});

it('rate-limits each IP to 60 requests per minute', function () {

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/health')->assertOk();
    }

    $this->getJson('/api/v1/health')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
