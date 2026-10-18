<?php

namespace App\Repositories\Contracts;

use App\Support\StoreUnavailable;

/**
 * Stored setting overrides. Defaults, types, and validation live in
 * SettingsService so every driver behaves the same (storage-contracts.md).
 */
interface SettingsRepository
{
    /**
     * @return array<string, string> key → raw value
     *
     * @throws StoreUnavailable
     */
    public function allOverrides(): array;

    /**
     * Insert or replace the row for $key under the write lock.
     *
     * @throws StoreUnavailable
     */
    public function upsert(string $key, string $value, string $by): void;
}
