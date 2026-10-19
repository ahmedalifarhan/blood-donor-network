<?php

use App\Models\OperationalLog;
use App\Repositories\Data\ActorType;
use App\Repositories\Data\LogEntry;
use App\Repositories\Data\LogLevel;
use App\Repositories\Eloquent\EloquentOperationalLogRepository;
use App\Support\StoreUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// storage-contracts.md C5, C6, C7, C8, C13.

uses(RefreshDatabase::class);

function logEntry(string $event = 'setting.changed', array $context = []): LogEntry
{
    return new LogEntry(LogLevel::Warning, $event, ActorType::Operator, 'owner', $context, '41.32.10.7');
}

it('assigns unique, time-ordered 26-char ULIDs and returns newest first (C5, C6)', function () {
    $repo = new EloquentOperationalLogRepository;

    $ids = [];
    for ($i = 0; $i < 20; $i++) {
        $ids[] = $repo->append(logEntry("event.{$i}"));
    }

    $sorted = $ids;
    sort($sorted);
    expect(array_unique($ids))->toHaveCount(20)
        ->and($ids)->each->toHaveLength(26)
        ->and($ids)->toBe($sorted)
        ->and(array_map(static fn (LogEntry $e): ?string => $e->id, $repo->recent(20)))->toBe(array_reverse($ids));
});

it('limits recent() and returns nothing for a zero limit', function () {
    $repo = new EloquentOperationalLogRepository;
    foreach (range(1, 3) as $i) {
        $repo->append(logEntry("event.{$i}"));
    }

    expect($repo->recent(2))->toHaveCount(2)
        ->and($repo->recent(0))->toBe([]);
});

it('stores phone numbers and IPs masked (C7)', function () {
    $repo = new EloquentOperationalLogRepository;

    $repo->append(logEntry(context: ['phone' => '201012345678']));

    $entry = $repo->recent(1)[0];
    expect($entry->context)->toBe(['phone' => '2010****5678'])
        ->and($entry->ip)->toBe('41.32.10.0/24')
        ->and($entry->level)->toBe(LogLevel::Warning)
        ->and($entry->actorType)->toBe(ActorType::Operator)
        ->and($entry->actorRef)->toBe('owner')
        ->and($entry->createdAt)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

it('refuses to update or delete a stored entry', function () {
    (new EloquentOperationalLogRepository)->append(logEntry());
    $row = OperationalLog::query()->sole();

    expect(fn () => $row->update(['event' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class)
        ->and(OperationalLog::query()->sole()->event)->toBe('setting.changed');
});

it('raises StoreUnavailable when the store fails (C13)', function () {
    Schema::drop('operational_logs');

    expect(fn () => (new EloquentOperationalLogRepository)->append(logEntry()))
        ->toThrow(StoreUnavailable::class);
});
