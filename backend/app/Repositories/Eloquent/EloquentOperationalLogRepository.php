<?php

namespace App\Repositories\Eloquent;

use App\Models\OperationalLog;
use App\Repositories\Contracts\OperationalLogRepository;
use App\Repositories\Data\ActorType;
use App\Repositories\Data\LogEntry;
use App\Repositories\Data\LogLevel;
use App\Support\Ids;
use App\Support\Masking;
use App\Support\Utc;
use Carbon\CarbonImmutable;

final class EloquentOperationalLogRepository implements OperationalLogRepository
{
    use GuardsStoreAccess;

    public function append(LogEntry $entry): string
    {
        $id = Ids::new();
        $actorRef = Masking::text($entry->actorRef ?? '');

        $this->guarded(static fn (): OperationalLog => OperationalLog::query()->create([
            'id' => $id,
            'level' => $entry->level->value,
            'event' => $entry->event,
            'actor_type' => $entry->actorType->value,
            'actor_ref' => $actorRef === '' ? null : $actorRef,
            'context' => $entry->context === [] ? null : Masking::json($entry->context),
            'ip' => $entry->ip === null ? null : Masking::ip($entry->ip),
            'created_at' => CarbonImmutable::now('UTC'),
        ]));

        return $id;
    }

    public function recent(int $limit = 50): array
    {
        if ($limit <= 0) {
            return [];
        }

        // ULIDs are time-ordered, so the ID breaks ties within the same second.
        $rows = $this->guarded(static fn (): array => array_values(OperationalLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all()));

        return array_map(static function (OperationalLog $row): LogEntry {
            $context = json_decode($row->context ?? '', true);

            return new LogEntry(
                level: LogLevel::tryFrom($row->level) ?? LogLevel::Warning,
                event: $row->event,
                actorType: ActorType::tryFrom($row->actor_type) ?? ActorType::System,
                actorRef: $row->actor_ref,
                context: is_array($context) ? $context : [],
                ip: $row->ip,
                id: $row->id,
                createdAt: $row->created_at->utc()->format(Utc::FORMAT),
            );
        }, $rows);
    }
}
