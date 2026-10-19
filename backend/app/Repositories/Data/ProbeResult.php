<?php

namespace App\Repositories\Data;

final readonly class ProbeResult
{
    /**
     * @param  list<string>  $missing  missing tables ("scheduler_steps") or columns ("scheduler_steps.last_period")
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
