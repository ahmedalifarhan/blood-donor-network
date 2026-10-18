<?php

namespace App\Repositories\Contracts;

use App\Repositories\Data\SchemaChange;
use App\Support\StoreUnavailable;

/**
 * Creates the store structure declared in config/store-schema.php (FR-033).
 * Never deletes, renames, or reorders existing tabs or columns.
 */
interface StoreSchemaManager
{
    /**
     * What apply() would change. No writes.
     *
     * @return list<SchemaChange>
     *
     * @throws StoreUnavailable
     */
    public function plan(): array;

    /**
     * Creates missing tabs and appends missing headers at the end. Idempotent.
     *
     * @return list<SchemaChange> what was changed
     *
     * @throws StoreUnavailable
     */
    public function apply(): array;

    /**
     * Store title, used for the sandbox/production guard.
     *
     * @throws StoreUnavailable
     */
    public function title(): string;
}
