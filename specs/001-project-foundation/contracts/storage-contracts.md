# 💾 Storage Contracts

**Feature**: 001-project-foundation · **Data model**: [data-model.md](../data-model.md)

The store implementation (**Eloquent**, in `backend/app/Repositories/Eloquent/`) implements these interfaces in `backend/app/Repositories/Contracts/` and must pass the **same** contract suite in `backend/tests/Contract/` on every supported database: **SQLite** and **PostgreSQL** (FR-014–FR-016, SC-004). Feature code depends on interfaces only. `StorageServiceProvider` binds the contracts; the database is chosen by Laravel's `DB_CONNECTION` (`pgsql` deployed, `sqlite` local and tests).

---

## 🧩 Interfaces

### `SettingsRepository`

| Method | Returns | Behavior |
| --- | --- | --- |
| `allOverrides()` | `array<string, string>` | Key → raw value. Empty when none |
| `upsert(string $key, string $value, string $by)` | `void` | Insert or replace the row for `$key` in one atomic `INSERT … ON CONFLICT (key)`; an existing row keeps its `id`. Sets `updated_at` and `updated_by` |

> Typed reads, defaults, and validation live in `SettingsService` (not in the repository), so every database behaves the same.

### `SchedulerStateRepository`

| Method | Returns | Behavior |
| --- | --- | --- |
| `find(string $step)` | `?StepState` | `null` when no row |
| `all()` | `list<StepState>` | Ordered by `step_name` |
| `save(StepState $state)` | `void` | Insert or update by `step_name` (unique constraint) in one atomic `INSERT … ON CONFLICT (step_name)` |

### `OperationalLogRepository`

| Method | Returns | Behavior |
| --- | --- | --- |
| `append(LogEntry $entry)` | `string` (ULID) | Append-only. Masks context and IP before storing |
| `recent(int $limit = 50)` | `list<LogEntry>` | Newest first. Used by tests and the future admin view |

> ❌ No `update` or `delete` methods: append-only by construction. The `OperationalLog` Eloquent model also **throws** on update or delete.

### `StoreProbe`

| Method | Returns | Behavior |
| --- | --- | --- |
| `check()` | `ProbeResult{reachable: bool, structureComplete: bool, missing: list<string>}` | Lists each table's columns and compares them with `config/store-schema.php`. `missing` holds `table` or `table.column` names (e.g. `scheduler_steps.last_period`). No retries; never throws; failures map to `reachable=false`. Cached 60 s by the caller |

> 🗂️ **Schema**: there is no schema-manager contract. Tables are created only by **Laravel migrations**, run automatically at container start (FR-033). The former `StoreSchemaManager` and `store:setup` were removed on 2026-10-19.

### 🧱 How the implementation is built

| Part | Location | Role |
| --- | --- | --- |
| **Repositories** | `app/Repositories/Eloquent/` | `EloquentSettingsRepository`, `EloquentSchedulerStateRepository`, `EloquentOperationalLogRepository`, `DatabaseStoreProbe` |
| **Models** | `app/Models/` | `Setting`, `SchedulerStep`, `OperationalLog`; used **only** inside repositories |
| **Error mapping** | trait `GuardsStoreAccess` | `QueryException` / `PDOException` → `StoreUnavailable`, no retries |
| **Binding** | `StorageServiceProvider` | Binds each contract to its Eloquent implementation |

- **Values** are bound parameters, so nothing is escaped or evaluated.
- **Times** are stored as UTC timestamps and returned as ISO 8601 `Z` strings.

---

## ✅ Shared contract suite (runs on SQLite and PostgreSQL)

| # | Case | Expectation | Status |
| --- | --- | --- | --- |
| C1 | Round-trip values: `201012345678`, `=SUM(A1)`, `+201012345678`, `-5`, `@user`, `'quoted`, `''double`, `""`, `عرب مطير`, `🩸`, a 5,000-char string | Read back **byte-identical** | ✅ `SettingsRepositoryTest` |
| C2 | `SettingsRepository::upsert` twice on one key | One row, latest value, same `id` | ✅ `SettingsRepositoryTest` |
| C3 | `SchedulerStateRepository::save` for a new step, then an update | One row per `step_name` | ✅ `SchedulerStateRepositoryTest` |
| C4 | 20 concurrent `upsert`s on different keys (process pool) | 20 rows, none lost or corrupted | 🐘 PostgreSQL CI run only (SQLite in memory has no real concurrency) |
| C5 | 20 concurrent `append`s | 20 entries, 20 unique ULIDs, all readable | 🟡 Sequential on SQLite in `OperationalLogRepositoryTest`; truly concurrent only in the PostgreSQL CI run (T054) |
| C6 | ULIDs | 26 chars, monotonic order by creation time | ✅ `OperationalLogRepositoryTest` |
| C7 | `OperationalLogRepository::append` with a phone in context | Stored masked (`2010****5678`) | ✅ `OperationalLogRepositoryTest` |
| C8 | Timestamps written then read | UTC `Z` strings, unchanged | ✅ `SchedulerStateRepositoryTest`, `OperationalLogRepositoryTest` |
| C9 | ~~Header order shuffled in the tab~~ | ⛔ **Retired (2026-10-19)**: Sheets-only. Columns are addressed by name in SQL, so order never matters | — |
| C10 | Extra column added by a **later migration** | Ignored by code that doesn't know it; the repository still reads and writes its own columns | ⏳ To write |
| C11 | `php artisan migrate` twice | Second run reports **"Nothing to migrate"**; no data changed | ⏳ To write |
| C12 | Database unreachable (bad host or refused connection) | `StoreUnavailable` raised **quickly, with no retries**; the probe reports `reachable=false` | ⏳ To write (the probe half is covered by `DatabaseStoreProbeTest`) |
| C13 | Store keeps failing | Typed `StoreUnavailable` raised; no partial row | ✅ `OperationalLogRepositoryTest` |

> 📝 **IDs are stable.** C9 is retired, and C10–C12 were redefined for PostgreSQL on 2026-10-19 (formerly: unknown spreadsheet column, `StoreSchemaManager::apply` twice, 429 backoff). They are not renumbered.

### ▶️ Running the suite

| Database | How | When |
| --- | --- | --- |
| **SQLite in memory** | `cd backend && ./vendor/bin/pest tests/Contract` (default from `phpunit.xml`) | Locally and in every CI run |
| **PostgreSQL** | Same command with `DB_CONNECTION=pgsql` and `DB_URL` pointing at a **disposable** Postgres service container | Planned CI job (US5) |

- 🧪 Tests **never** use a deployed database; no deployed `DB_URL` is available to test jobs (FR-022, research R14).
