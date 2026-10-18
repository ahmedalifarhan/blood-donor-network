<?php

namespace App\Repositories\Contracts;

use App\Repositories\Data\LogEntry;
use App\Support\StoreUnavailable;

/**
 * Append-only by construction: there is deliberately no update or delete.
 */
interface OperationalLogRepository
{
    /**
     * Masks context and IP, assigns a ULID and UTC timestamp, and stores the entry.
     *
     * @return string the new entry's ULID
     *
     * @throws StoreUnavailable
     */
    public function append(LogEntry $entry): string;

    /**
     * @return list<LogEntry> newest first
     *
     * @throws StoreUnavailable
     */
    public function recent(int $limit = 50): array;
}
