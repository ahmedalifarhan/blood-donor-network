<?php

namespace App\Repositories\Table;

use App\Support\Backoff;
use App\Support\CellCodec;
use App\Support\StoreUnavailable;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;
use LogicException;
use RuntimeException;

/**
 * Header-mapped, cached, write-serialized access to a CellBackend
 * (constitution V, research R2–R5). Shared by every table-shaped driver so
 * they cannot drift apart:
 *
 * - rows are mapped by header name, never by position;
 * - unknown columns are ignored on read and preserved on update;
 * - every cell goes through CellCodec;
 * - each tab is read whole, cached, and the cache is cleared after a write;
 * - every read-modify-write runs inside one write lock;
 * - every backend call goes through Backoff.
 */
final class Table
{
    private int $lockDepth = 0;

    public function __construct(
        private readonly CellBackend $backend,
        private readonly Cache $cache,
        private readonly Backoff $backoff,
        private readonly string $lockName,
        private readonly int $cacheSeconds = 60,
    ) {}

    /**
     * Decoded data rows keyed by header name.
     *
     * @return list<array<string, string>>
     */
    public function rows(string $tab): array
    {
        return array_map(
            static fn (array $row): array => $row['values'],
            $this->indexedRows($this->cachedTab($tab)),
        );
    }

    /**
     * Appends one row. Keys must be existing headers.
     *
     * @param  array<string, string>  $values
     */
    public function append(string $tab, array $values): void
    {
        $this->withWriteLock(function () use ($tab, $values): void {
            // Headers rarely change, so the cached copy is enough (saves a read per append).
            $this->appendWithHeaders($tab, $this->cachedTab($tab)[0] ?? [], $values);
        });
    }

    /**
     * Updates the row whose $keyColumn equals $values[$keyColumn], keeping
     * every other cell, or appends a new row with $insertExtras added.
     *
     * @param  array<string, string>  $values
     * @param  array<string, string>  $insertExtras  e.g. ['ID' => ulid], used only on insert
     */
    public function upsert(string $tab, string $keyColumn, array $values, array $insertExtras = []): void
    {
        $this->withWriteLock(function () use ($tab, $keyColumn, $values, $insertExtras): void {
            $raw = $this->freshTab($tab);
            $headers = $raw[0] ?? [];
            $this->assertColumns($tab, $headers, [...array_keys($values), ...array_keys($insertExtras)]);

            foreach ($this->indexedRows($raw) as $row) {
                if (($row['values'][$keyColumn] ?? null) === ($values[$keyColumn] ?? null)) {
                    $this->retrying(fn () => $this->backend->writeRow(
                        $tab,
                        $row['number'],
                        $this->mergeCells($headers, $row['cells'], $values),
                    ));
                    $this->forget($tab);

                    return;
                }
            }

            $this->appendWithHeaders($tab, $headers, [...$values, ...$insertExtras]);
        });
    }

    /**
     * Runs $callback inside the global write lock. Re-entrant within one process.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws StoreUnavailable
     */
    public function withWriteLock(callable $callback): mixed
    {
        if ($this->lockDepth > 0) {
            return $callback();
        }

        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            throw new LogicException('The cache store must support atomic locks.');
        }

        $lock = $store->lock($this->lockName, 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException $e) {
            throw StoreUnavailable::after($e);
        }

        $this->lockDepth++;

        try {
            return $callback();
        } finally {
            $this->lockDepth--;
            $lock->release();
        }
    }

    /**
     * Runs a backend call with Backoff, turning failures into StoreUnavailable.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function retrying(callable $operation): mixed
    {
        return $this->backoff->run($operation);
    }

    public function forget(string $tab): void
    {
        $this->cache->forget($this->cacheKey($tab));
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $values
     */
    private function appendWithHeaders(string $tab, array $headers, array $values): void
    {
        $this->assertColumns($tab, $headers, array_keys($values));

        $cells = array_map(
            static fn (string $header): string => CellCodec::encode($values[$header] ?? ''),
            $headers,
        );

        $this->retrying(fn () => $this->backend->appendRows($tab, [$cells]));
        $this->forget($tab);
    }

    /** @return list<list<string>> */
    private function cachedTab(string $tab): array
    {
        /** @var list<list<string>> */
        return $this->cache->remember(
            $this->cacheKey($tab),
            $this->cacheSeconds,
            fn (): array => $this->freshTab($tab),
        );
    }

    /** @return list<list<string>> */
    private function freshTab(string $tab): array
    {
        return $this->retrying(fn (): array => $this->backend->readTab($tab));
    }

    /**
     * @param  list<list<string>>  $raw
     * @return list<array{number: int, cells: list<string>, values: array<string, string>}>
     */
    private function indexedRows(array $raw): array
    {
        $headers = $raw[0] ?? [];
        $rows = [];

        foreach (array_slice($raw, 1) as $offset => $cells) {
            if (implode('', $cells) === '') {
                continue; // blank row
            }

            $values = [];

            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $values[$header] = CellCodec::decode($cells[$i] ?? '');
                }
            }

            $rows[] = ['number' => $offset + 2, 'cells' => $cells, 'values' => $values];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $existing
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function mergeCells(array $headers, array $existing, array $values): array
    {
        $cells = [];

        foreach ($headers as $i => $header) {
            $cells[] = array_key_exists($header, $values)
                ? CellCodec::encode($values[$header])
                : ($existing[$i] ?? '');
        }

        return $cells;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $columns
     */
    private function assertColumns(string $tab, array $headers, array $columns): void
    {
        $missing = array_diff($columns, $headers);

        if ($missing !== []) {
            throw StoreUnavailable::after(new RuntimeException(
                "Store structure incomplete: {$tab}.".implode(", {$tab}.", $missing),
            ));
        }
    }

    private function cacheKey(string $tab): string
    {
        return 'store:'.$this->backend->cacheKey().':'.$tab;
    }
}
