<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\StoreProbe;
use App\Repositories\Data\ProbeResult;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Lists each expected table's columns, no retries, never throws. Compared
 * against config/store-schema.php: a missing table or column means the
 * migrations have not run (FR-033).
 */
final class DatabaseStoreProbe implements StoreProbe
{
    /** @param array<string, list<string>> $schema table => columns */
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly array $schema,
    ) {}

    public function check(): ProbeResult
    {
        try {
            $builder = $this->db->connection()->getSchemaBuilder();
            $existing = [];

            foreach (array_keys($this->schema) as $table) {
                $existing[$table] = $builder->getColumnListing($table);
            }
        } catch (Throwable) {
            return ProbeResult::unreachable();
        }

        $missing = [];

        foreach ($this->schema as $table => $columns) {
            if ($existing[$table] === []) {
                $missing[] = $table;

                continue;
            }

            foreach ($columns as $column) {
                if (! in_array($column, $existing[$table], true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        return new ProbeResult(reachable: true, structureComplete: $missing === [], missing: $missing);
    }
}
