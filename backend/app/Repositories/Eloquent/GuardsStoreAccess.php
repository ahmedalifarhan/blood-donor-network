<?php

namespace App\Repositories\Eloquent;

use App\Support\StoreUnavailable;
use Illuminate\Database\QueryException;
use PDOException;

/**
 * Turns database failures into StoreUnavailable, so callers see the same
 * contract exception whatever the store is (storage-contracts.md).
 */
trait GuardsStoreAccess
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     *
     * @throws StoreUnavailable
     */
    private function guarded(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException|PDOException $e) {
            throw StoreUnavailable::after($e);
        }
    }
}
