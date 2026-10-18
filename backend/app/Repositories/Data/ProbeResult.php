<?php

namespace App\Repositories\Data;

final readonly class ProbeResult
{
    /**
     * @param  list<string>  $missing  missing tabs ("SchedulerSteps") or headers ("SchedulerSteps.LastPeriod")
     */
    public function __construct(
        public bool $reachable,
        public bool $structureComplete,
        public array $missing = [],
    ) {}

    public static function unreachable(): self
    {
        return new self(reachable: false, structureComplete: false);
    }
}
