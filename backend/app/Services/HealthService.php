<?php

namespace App\Services;

use App\Repositories\Contracts\StoreProbe;
use App\Support\Utc;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Service status for visitors (and, later, operators). The store probe is
 * reused for up to 60 seconds, so visitor traffic costs at most about one
 * store check per minute (spec clarification Q2, research R2).
 */
final class HealthService
{
    private const PROBE_CACHE_KEY = 'health:probe';

    public function __construct(
        private readonly ConfigCheck $config,
        private readonly StoreProbe $probe,
        private readonly Cache $cache,
    ) {}

    /**
     * Public three-state summary, minus "unavailable" (decided by the frontend).
     *
     * @return array{status: 'running'|'limited', checkedAt: string}
     */
    public function publicStatus(): array
    {
        $probe = $this->probe();
        $running = $this->config->isComplete() && $probe['reachable'] && $probe['structureComplete'];

        return [
            'status' => $running ? 'running' : 'limited',
            'checkedAt' => $probe['checkedAt'],
        ];
    }

    /**
     * @return array{reachable: bool, structureComplete: bool, missing: list<string>, checkedAt: string}
     */
    public function probe(): array
    {
        /** @var array{reachable: bool, structureComplete: bool, missing: list<string>, checkedAt: string} */
        return $this->cache->remember(
            self::PROBE_CACHE_KEY,
            (int) config('health.probe_cache_seconds', 60),
            function (): array {
                $result = $this->probe->check();

                return [
                    'reachable' => $result->reachable,
                    'structureComplete' => $result->structureComplete,
                    'missing' => $result->missing,
                    'checkedAt' => Utc::now(),
                ];
            },
        );
    }
}
