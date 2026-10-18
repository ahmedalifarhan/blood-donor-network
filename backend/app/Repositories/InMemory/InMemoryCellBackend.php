<?php

namespace App\Repositories\InMemory;

use App\Repositories\Table\CellBackend;
use App\Support\StoreRequestFailed;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Spreadsheet-shaped store kept in the Laravel cache: the array store in
 * single-process tests, the file store for local serving and multi-process
 * contract tests (shared state and shared write lock). It mimics the Sheets
 * backend, including a 400 error for an unknown tab, so both drivers pass
 * the same contract suite.
 */
final class InMemoryCellBackend implements CellBackend
{
    private const STATE_KEY = 'memory-store:tabs';

    private int $failuresLeft = 0;

    private int $failureStatus = 503;

    public function __construct(
        private readonly Cache $cache,
        private readonly string $title = 'Blood Donor Network (in-memory) [sandbox]',
    ) {}

    /** Fault injection for tests: the next $times calls fail with HTTP $status. */
    public function failNext(int $times, int $status = 503): void
    {
        $this->failuresLeft = $times;
        $this->failureStatus = $status;
    }

    public function reset(): void
    {
        $this->cache->forget(self::STATE_KEY);
    }

    public function cacheKey(): string
    {
        return 'memory';
    }

    public function title(): string
    {
        $this->guard();

        return $this->title;
    }

    public function tabNames(): array
    {
        $this->guard();

        return array_keys($this->load());
    }

    public function headerRows(array $tabs): array
    {
        $this->guard();
        $state = $this->load();
        $headers = [];

        foreach ($tabs as $tab) {
            $headers[$tab] = $this->tab($state, $tab)[0] ?? [];
        }

        return $headers;
    }

    public function readTab(string $tab): array
    {
        $this->guard();

        return $this->tab($this->load(), $tab);
    }

    public function addTabs(array $tabs): void
    {
        $this->guard();
        $state = $this->load();

        foreach ($tabs as $tab) {
            $state[$tab] ??= [];
        }

        $this->persist($state);
    }

    public function writeRow(string $tab, int $rowNumber, array $cells): void
    {
        $this->guard();
        $state = $this->load();
        $rows = $this->tab($state, $tab);

        for ($i = count($rows); $i < $rowNumber - 1; $i++) {
            $rows[] = [];
        }

        $rows[$rowNumber - 1] = $cells;
        $state[$tab] = $rows;
        $this->persist($state);
    }

    public function appendRows(string $tab, array $rows): void
    {
        $this->guard();
        $state = $this->load();
        $state[$tab] = [...$this->tab($state, $tab), ...array_map('array_values', $rows)];
        $this->persist($state);
    }

    private function guard(): void
    {
        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            throw new StoreRequestFailed('Injected store failure.', $this->failureStatus);
        }
    }

    /**
     * @param  array<string, list<list<string>>>  $state
     * @return list<list<string>>
     */
    private function tab(array $state, string $tab): array
    {
        if (! array_key_exists($tab, $state)) {
            throw new StoreRequestFailed("Unable to parse range: {$tab}", 400);
        }

        return $state[$tab];
    }

    /** @return array<string, list<list<string>>> */
    private function load(): array
    {
        $state = $this->cache->get(self::STATE_KEY, []);

        /** @var array<string, list<list<string>>> */
        return is_array($state) ? $state : [];
    }

    /** @param array<string, list<list<string>>> $state */
    private function persist(array $state): void
    {
        $this->cache->forever(self::STATE_KEY, $state);
    }
}
