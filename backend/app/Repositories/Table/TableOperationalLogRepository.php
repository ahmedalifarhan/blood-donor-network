<?php

namespace App\Repositories\Table;

use App\Repositories\Contracts\OperationalLogRepository;
use App\Repositories\Data\ActorType;
use App\Repositories\Data\LogEntry;
use App\Repositories\Data\LogLevel;
use App\Support\Ids;
use App\Support\Masking;
use App\Support\Utc;

final class TableOperationalLogRepository implements OperationalLogRepository
{
    private const TAB = 'OperationalLogs';

    public function __construct(private readonly Table $table) {}

    public function append(LogEntry $entry): string
    {
        $id = Ids::new();

        $this->table->append(self::TAB, [
            'ID' => $id,
            'Level' => $entry->level->value,
            'Event' => $entry->event,
            'ActorType' => $entry->actorType->value,
            'ActorRef' => Masking::text($entry->actorRef ?? ''),
            'Context' => $entry->context === [] ? '' : Masking::json($entry->context),
            'IP' => $entry->ip === null ? '' : Masking::ip($entry->ip),
            'CreatedAt' => Utc::now(),
        ]);

        return $id;
    }

    public function recent(int $limit = 50): array
    {
        $rows = array_slice(array_reverse($this->table->rows(self::TAB)), 0, max(0, $limit));

        return array_map(static function (array $row): LogEntry {
            $context = json_decode($row['Context'] ?? '', true);

            return new LogEntry(
                level: LogLevel::tryFrom($row['Level'] ?? '') ?? LogLevel::Warning,
                event: $row['Event'] ?? '',
                actorType: ActorType::tryFrom($row['ActorType'] ?? '') ?? ActorType::System,
                actorRef: ($row['ActorRef'] ?? '') === '' ? null : $row['ActorRef'],
                context: is_array($context) ? $context : [],
                ip: ($row['IP'] ?? '') === '' ? null : $row['IP'],
                id: $row['ID'] ?? null,
                createdAt: $row['CreatedAt'] ?? null,
            );
        }, $rows);
    }
}
