<?php

namespace App\Repositories\Table;

/**
 * Raw cell access to a spreadsheet-shaped store: tabs of rows of string cells,
 * row 1 holding the headers. Implemented by the Google Sheets backend and by
 * an in-memory backend for tests and local development.
 *
 * Cells are stored exactly as given (already encoded by Table). Methods may
 * throw any exception; Table wraps calls in Backoff and turns failures into
 * StoreUnavailable.
 */
interface CellBackend
{
    /** Identifies this store in cache keys, e.g. "sheets:<id>" or "memory". */
    public function cacheKey(): string;

    public function title(): string;

    /** @return list<string> */
    public function tabNames(): array;

    /**
     * Header row of each given (existing) tab, fetched in a single request.
     *
     * @param  list<string>  $tabs
     * @return array<string, list<string>>
     */
    public function headerRows(array $tabs): array;

    /**
     * Every row of the tab, header row first. Fails if the tab does not exist.
     *
     * @return list<list<string>>
     */
    public function readTab(string $tab): array;

    /**
     * Creates empty tabs with row 1 frozen.
     *
     * @param  list<string>  $tabs
     */
    public function addTabs(array $tabs): void;

    /**
     * Overwrites one row (1-based row number).
     *
     * @param  list<string>  $cells
     */
    public function writeRow(string $tab, int $rowNumber, array $cells): void;

    /**
     * Appends rows after the last row.
     *
     * @param  list<list<string>>  $rows
     */
    public function appendRows(string $tab, array $rows): void;
}
