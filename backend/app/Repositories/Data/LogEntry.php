<?php

namespace App\Repositories\Data;

/**
 * One row of the append-only OperationalLogs tab (data-model.md §3).
 * `id` and `createdAt` are assigned by the repository on append.
 */
final readonly class LogEntry
{
    /**
     * @param  array<array-key, mixed>  $context  masked by the repository before storage
     */
    public function __construct(
        public LogLevel $level,
        public string $event,
        public ActorType $actorType,
        public ?string $actorRef = null,
        public array $context = [],
        public ?string $ip = null,
        public ?string $id = null,
        public ?string $createdAt = null,
    ) {}
}
