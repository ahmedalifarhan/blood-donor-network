# 💾 Storage Contracts

**Feature**: 001-project-foundation · **Data model**: [data-model.md](../data-model.md)

Every store implementation (**Sheets**, **InMemory**, later **Eloquent**) implements these interfaces in `backend/app/Repositories/Contracts/` and must pass the **same** contract suite in `backend/tests/Contract/` (FR-014–FR-016, SC-004). Feature code depends on interfaces only. The driver is bound from `DATA_DRIVER` in a service provider.

---

## 🧩 Interfaces

### `SettingsRepository`

| Method | Returns | Behavior |
| --- | --- | --- |
| `allOverrides()` | `array<string, string>` | Key → raw value. Empty when none |
| `upsert(string $key, string $value, string $by)` | `void` | Insert or replace the row for `$key` under the write lock. Sets `UpdatedAt` and `UpdatedBy` |

> Typed reads, defaults, and validation live in `SettingsService` (not in the repository), so every driver behaves the same.

### `SchedulerStateRepository`

| Method | Returns | Behavior |
| --- | --- | --- |
| `find(string $step)` | `?StepState` | `null` when no row |
| `all()` | `list<StepState>` | Ordered by `StepName` |
| `save(StepState $state)` | `void` | Insert or update by `StepName` (unique) under the write lock |

### `OperationalLogRepository`

| Method | Returns | Behavior |
| --- | --- | --- |
| `append(LogEntry $entry)` | `string` (ULID) | Append-only. Masks context and IP before storing |
| `recent(int $limit = 50)` | `list<LogEntry>` | Newest first. Used by tests and the future admin view |

> ❌ No `update` or `delete` methods: append-only by construction.

### `StoreProbe`

| Method | Returns | Behavior |
| --- | --- | --- |
| `check()` | `ProbeResult{reachable: bool, structureComplete: bool, missing: list<string>}` | One cheap call (spreadsheet metadata). Never throws; failures map to `reachable=false` |

### `StoreSchemaManager` (used by `store:setup` only)

| Method | Returns | Behavior |
| --- | --- | --- |
| `plan()` | `list<SchemaChange>` | What would be created (tab or header). No writes |
| `apply()` | `list<SchemaChange>` | Creates missing tabs and appends missing headers. Never deletes, renames, or reorders. Idempotent |
| `title()` | `string` | Store title, used by `store:setup`'s sandbox guard |

### 🧱 How the drivers are built

Both current drivers are **table-shaped**, so they share one implementation of every contract above (`app/Repositories/Table/`). That layer does header mapping, `CellCodec`, caching, the write lock, and backoff. They differ only in a small `CellBackend` (raw cells in, raw cells out): `SheetsCellBackend` and `InMemoryCellBackend`. A future SQL driver implements the contracts directly with Eloquent and must still pass this suite.

---

## ✅ Shared contract suite (runs per driver)

| # | Case | Expectation |
| --- | --- | --- |
| C1 | Round-trip values: `201012345678`, `=SUM(A1)`, `+201012345678`, `-5`, `@user`, `'quoted`, `''double`, `""`, `عرب مطير`, `🩸`, a 5,000-char string | Read back **byte-identical** |
| C2 | `SettingsRepository::upsert` twice on one key | One row, latest value |
| C3 | `SchedulerStateRepository::save` for a new step, then an update | One row per `StepName` |
| C4 | 20 concurrent `upsert`s on different keys (process pool) | 20 rows, none lost or corrupted |
| C5 | 20 concurrent `append`s | 20 entries, 20 unique ULIDs, all readable |
| C6 | ULIDs | 26 chars, monotonic order by creation time |
| C7 | `OperationalLogRepository::append` with a phone in context | Stored masked (`2010****5678`) |
| C8 | Timestamps written then read | UTC `Z` strings, unchanged |
| C9 | Header order shuffled in the tab (simulated manual reorder) | Mapper still reads by header name |
| C10 | Extra unknown column present | Ignored, preserved on update |
| C11 | `StoreSchemaManager::apply` twice | Second run returns no changes |
| C12 | Store returns 429 twice, then OK (fake transport for Sheets; injected fault for memory) | Operation succeeds within the 8 s budget |
| C13 | Store keeps failing | Typed `StoreUnavailable` raised; no partial row |

Run with:

```bash
cd backend && ./vendor/bin/pest tests/Contract
```

and against the sandbox spreadsheet only:

```bash
cd backend && ./vendor/bin/pest tests/Contract --group=sheets
```

The `sheets` group aborts unless the spreadsheet title ends with `[sandbox]` (research R14).
