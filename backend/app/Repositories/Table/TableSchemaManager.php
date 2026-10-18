<?php

namespace App\Repositories\Table;

use App\Repositories\Contracts\StoreSchemaManager;
use App\Repositories\Data\SchemaChange;

/**
 * Creates missing tabs and appends missing headers at the end (FR-033).
 * Never deletes, renames, or reorders anything.
 */
final class TableSchemaManager implements StoreSchemaManager
{
    /** @param array<string, list<string>> $schema tab => headers */
    public function __construct(
        private readonly CellBackend $backend,
        private readonly Table $table,
        private readonly array $schema,
    ) {}

    public function plan(): array
    {
        return $this->diff()['changes'];
    }

    public function apply(): array
    {
        return $this->table->withWriteLock(function (): array {
            $diff = $this->diff();

            if ($diff['newTabs'] !== []) {
                $this->table->retrying(fn () => $this->backend->addTabs($diff['newTabs']));
            }

            foreach ($diff['headerRows'] as $tab => $headers) {
                $this->table->retrying(fn () => $this->backend->writeRow($tab, 1, $headers));
                $this->table->forget($tab);
            }

            return $diff['changes'];
        });
    }

    public function title(): string
    {
        return $this->table->retrying(fn (): string => $this->backend->title());
    }

    /**
     * @return array{changes: list<SchemaChange>, newTabs: list<string>, headerRows: array<string, list<string>>}
     */
    private function diff(): array
    {
        $existing = $this->table->retrying(fn (): array => $this->backend->tabNames());
        $present = array_values(array_intersect(array_keys($this->schema), $existing));
        $currentHeaders = $present === []
            ? []
            : $this->table->retrying(fn (): array => $this->backend->headerRows($present));

        $changes = [];
        $newTabs = [];
        $headerRows = [];

        foreach ($this->schema as $tab => $columns) {
            if (! in_array($tab, $existing, true)) {
                $changes[] = new SchemaChange('tab', $tab);
                $newTabs[] = $tab;
                $headerRows[$tab] = $columns;

                continue;
            }

            $current = self::withoutTrailingBlanks($currentHeaders[$tab] ?? []);
            $missing = array_values(array_diff($columns, $current));

            foreach ($missing as $column) {
                $changes[] = new SchemaChange('header', "{$tab}.{$column}");
            }

            if ($missing !== []) {
                $headerRows[$tab] = [...$current, ...$missing];
            }
        }

        return ['changes' => $changes, 'newTabs' => $newTabs, 'headerRows' => $headerRows];
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private static function withoutTrailingBlanks(array $headers): array
    {
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        return $headers;
    }
}
