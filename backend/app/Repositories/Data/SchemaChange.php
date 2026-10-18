<?php

namespace App\Repositories\Data;

final readonly class SchemaChange
{
    /**
     * @param  'tab'|'header'  $kind
     * @param  string  $name  "OperationalLogs" for a tab, "SchedulerSteps.LastPeriod" for a header
     */
    public function __construct(
        public string $kind,
        public string $name,
    ) {}

    public function describe(): string
    {
        return "+ {$this->kind} {$this->name}";
    }
}
