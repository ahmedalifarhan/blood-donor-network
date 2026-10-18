<?php

namespace App\Repositories\Table;

use App\Repositories\Contracts\StoreProbe;
use App\Repositories\Data\ProbeResult;
use Throwable;

/**
 * Two cheap calls (tab names, then all header rows in one batch), no retries,
 * never throws. Compared against config/store-schema.php.
 */
final class TableStoreProbe implements StoreProbe
{
    /** @param array<string, list<string>> $schema tab => headers */
    public function __construct(
        private readonly CellBackend $backend,
        private readonly array $schema,
    ) {}

    public function check(): ProbeResult
    {
        try {
            $existing = $this->backend->tabNames();
            $present = array_values(array_intersect(array_keys($this->schema), $existing));
            $headers = $present === [] ? [] : $this->backend->headerRows($present);
        } catch (Throwable) {
            return ProbeResult::unreachable();
        }

        $missing = [];

        foreach ($this->schema as $tab => $columns) {
            if (! in_array($tab, $existing, true)) {
                $missing[] = $tab;

                continue;
            }

            foreach ($columns as $column) {
                if (! in_array($column, $headers[$tab] ?? [], true)) {
                    $missing[] = "{$tab}.{$column}";
                }
            }
        }

        return new ProbeResult(reachable: true, structureComplete: $missing === [], missing: $missing);
    }
}
