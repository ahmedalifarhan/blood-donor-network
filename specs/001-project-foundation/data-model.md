# 💾 Data Model: Project Foundation

**Feature**: [spec.md](./spec.md) · **Research**: [research.md](./research.md) · **Date**: 2026-10-19

Three stored entities and one derived view. Each stored entity is **one database table**, created by a Laravel **migration** (`backend/database/migrations/2026_10_19_000001…000003`). The store is **PostgreSQL** when deployed and **SQLite** locally and in tests (research R16). Later features add tables and columns only through **new migrations** (FR-033).

---

## 🧭 Global storage rules

| Rule | Detail | Source |
| --- | --- | --- |
| **IDs** | ULID strings (26 chars, time-sortable, random) | FR-017, DS-01 |
| **Times** | UTC, ISO 8601 with `Z` (e.g. `2026-10-19T10:15:00Z`) | FR-021, DS-07 |
| **Values** | Passed as **bound parameters**, never built into SQL. Stored and read back unchanged, so `201012345678` and `=SUM(A1)` round-trip exactly; nothing is escaped | FR-018, research R16 |
| **Uniqueness** | Enforced by **unique constraints** (`settings.key`, `scheduler_steps.step_name`) | FR-019, research R16 |
| **Writes** | Insert-or-update is one atomic **`INSERT … ON CONFLICT`** on the unique key; an existing row keeps its `id`. No application-level write lock | FR-019, research R16 |
| **Reads** | Straight from the database. Only the store probe (FR-003) and settings (`SettingsService`) are cached, each for 60 s | FR-003, research R2 |
| **Times in the database** | `timestamptz(0)` (seconds precision); the `pgsql` connection runs in UTC. Repositories return ISO 8601 `Z` strings | FR-021 |
| **Failures** | `QueryException` / `PDOException` become `StoreUnavailable` (trait `GuardsStoreAccess`), with no retries | FR-020, research R16 |
| **JSON** | Compact JSON string in a `text` column; max 5,000 chars (truncated with a `…` marker, so the result may not be valid JSON) | — |
| **Masking** | Any run of 9+ digits in log context is masked to first 4 + `****` + last 4 before storage | Constitution II, AU-05 |

---

## 1. ⚙️ Setting

A tunable value. **Definitions live in code** (`backend/config/settings.php`); the store holds **overrides only**.

> ⚠️ Keys contain dots and `+`/`-` (e.g. `WaveIntervalMinutes.Emergency`, `ShortageThreshold.A+`). Definitions are stored as a **list** with a `key` field and indexed by the exact string, never read through Laravel's dot-notation `config()` lookup.

### Definition (code, not stored)

| Field | Type | Notes |
| --- | --- | --- |
| `key` | string | Unique, dot-separated (e.g. `WaveIntervalMinutes.Emergency`) |
| `type` | enum | `int`, `string`, `timeRange`, `phoneOrEmpty` |
| `default` | typed | Used when no override exists or the override is invalid |
| `min` / `max` | int | For `int` types |
| `description` | string | Shown by `settings:list` |

### Stored override (table **`settings`**)

| Column | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | ulid (primary key) | ✅ | |
| `key` | varchar(100) | ✅ | **Unique constraint**; must match a known definition |
| `value` | text | ✅ | Stored exactly as given |
| `updated_at` | timestamptz(0) | ✅ | |
| `updated_by` | varchar(100) | ✅ | Operator name given to the command (`--by`) |

### Validation rules

| Type | Valid when | Example |
| --- | --- | --- |
| `int` | Integer string within `min`–`max` | `WaveSize` 1–50 |
| `timeRange` | `HH:MM-HH:MM`, 24 h, may cross midnight | `23:00-07:00` |
| `phoneOrEmpty` | Empty, or digits only, 8–15 chars (full normalization arrives with the phone normalizer feature) | `201012345678` |
| `string` | ≤ 50 chars | `1` |

**Read behavior** (FR-024):

```text
override missing                → default
override present + valid        → override
override present + invalid      → default  + OperationalLog(setting.invalid, warning)  [once per key per cache period]
override key not in definitions → ignored  + OperationalLog(setting.unknown, warning)
```

### Definitions shipped in this feature (FR-026)

| Key | Type | Default | Range |
| --- | --- | --- | --- |
| `WaveSize` | int | 5 | 1–50 |
| `WaveIntervalMinutes.Emergency` | int | 5 | 1–120 |
| `WaveIntervalMinutes.Urgent` | int | 10 | 1–240 |
| `WaveIntervalMinutes.Normal` | int | 30 | 5–1440 |
| `AcceptTargetMultiplier` | int | 2 | 1–5 |
| `RotationDays` | int | 7 | 0–60 |
| `PublicCountThreshold` | int | 3 | 1–20 |
| `QuietHours` | timeRange | `23:00-07:00` | — |
| `EmergencyNoAcceptAlertMinutes` | int | 15 | 1–240 |
| `ShortageThreshold.A+` … `ShortageThreshold.O-` (8 keys) | int | 5 | 0–1000 |
| `AdminWhatsAppNumber` | phoneOrEmpty | empty | — |
| `ConsentVersion` | string | `1` | — |

---

## 2. ⏰ Scheduler Step State

One row per registered step (table **`scheduler_steps`**). It guarantees one run per period (FR-010) and feeds operator health (FR-013).

| Column | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | ulid (primary key) | ✅ | |
| `step_name` | varchar(100) | ✅ | **Unique constraint**, e.g. `heartbeat` |
| `last_period` | varchar(32), null | — | Period key of the last **completed** run, e.g. `2026-10-19T10:15Z` |
| `last_run_at` | timestamptz(0), null | — | Start time of the last attempt |
| `last_outcome` | varchar(10), null | — | `success`, `failure` |
| `last_error` | varchar(500), null | — | Short reason; ≤ 500 chars; masked |
| `last_success_at` | timestamptz(0), null | — | |
| `consecutive_failures` | unsigned int, default 0 | ✅ | Reset to 0 on success |

### Per-call outcome (returned by the tick, not stored)

| Outcome | Meaning | State written? |
| --- | --- | --- |
| `success` | Ran for the current period | ✅ `last_period`, `last_run_at`, `last_outcome`, `last_success_at` |
| `failure` | Threw an error | ✅ `last_run_at`, `last_outcome`, `last_error`, `consecutive_failures+1` (+ operational log) |
| `skipped` | `last_period ≥ current period` | ❌ |
| `busy` | Another run holds the step lock | ❌ |

### State transitions

```text
          (row created by the step's first run)
                         │
                         ▼
   ┌──────────── no completed period ────────────┐
   │                                              │
   ▼ run OK                              run fails▼
 success(P) ──── next period, run OK ──▶ success(P+1)
   │                                              ▲
   └──── run fails ──▶ failure(retry same P next tick) ──┘
```

A failed period is **retried** on the next tick, because `last_period` was not advanced. Missed periods are never replayed one by one (research R6).

---

## 3. 📝 Operational Log Entry

Durable, append-only record of notable events (table **`operational_logs`**). Routine successful runs are **not** stored here (FR-032).

| Column | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | ulid (primary key) | ✅ | |
| `level` | varchar(10) | ✅ | `warning`, `error` |
| `event` | varchar(100) | ✅ | See table below |
| `actor_type` | varchar(20) | ✅ | `System`, `Scheduler`, `Operator`, `Anonymous` |
| `actor_ref` | varchar(255), null | — | e.g. operator name, step name |
| `context` | **text**, null | — | JSON, masked; ≤ 5,000 chars. **Not `jsonb`**: truncation with a `…` marker can leave invalid JSON |
| `ip` | varchar(64), null | — | Masked to `/24` (IPv4) or `/48` (IPv6) |
| `created_at` | timestamptz(0), **indexed** | ✅ | |

### Events in this feature

| Event | Level | Actor | Context example |
| --- | --- | --- | --- |
| `scheduler.refused` | warning | Anonymous | `{"count": 7, "windowStart": "…", "ips": ["41.x.x.0/24"]}` (aggregated per minute, research R7) |
| `scheduler.step_failed` | error | Scheduler | `{"step": "heartbeat", "period": "…", "error": "StoreUnavailable"}` |
| `setting.changed` | warning | Operator | `{"key": "WaveSize", "old": "5", "new": "6"}` |
| `setting.invalid` | warning | System | `{"key": "WaveSize", "stored": "abc", "usedDefault": "5"}` |
| `setting.unknown` | warning | System | `{"key": "Foo"}` |
| `config.missing` | error | System | `{"missing": ["SCHEDULER_SECRET"]}` (logged when the store is reachable, **at most once per hour**) |

**Rules**: append-only. No update or delete method exists on the repository contract, and the `OperationalLog` Eloquent model **throws** on update or delete. The `setting.changed` level is `warning` so it stands out in operator review.

---

## 4. 🩺 Service Status (derived, not stored)

| Field | Source | Public? |
| --- | --- | --- |
| `status` | `running` if config OK **and** store probe OK; otherwise `limited` | ✅ (two values; the frontend adds `unavailable` when the backend can't be reached) |
| `checkedAt` | Time of the cached store probe | ✅ |
| `store.reachable` | Store probe (cached ≤ 60 s) | ❌ operator only |
| `store.structureComplete` | Every table and column in `config/store-schema.php` exists (that is, migrations have run) | ❌ |
| `store.missing` | Missing `table` or `table.column` names, e.g. `scheduler_steps.last_period` | ❌ |
| `config.missing` | Names (never values) of missing required env vars | ❌ |
| `scheduler.lastSuccessAt` | Max `last_success_at` across steps | ❌ |
| `scheduler.steps[]` | Each step's state row | ❌ |
| `driver` | Active database connection: `pgsql` / `sqlite` | ❌ |
| `version` | Build commit SHA | ❌ |

```text
Public mapping (FR-003, FR-031, FR-033):
  config missing          → limited      (details: config.missing)
  structure incomplete    → limited      (details: store.structureComplete=false, "not configured")
  store unreachable       → limited
  all OK                  → running
  backend unreachable     → unavailable  (decided by the frontend: timeout / network / 5xx)
```

---

## 🗂️ Tables created by migrations

| Migration | Table | Columns | Constraints |
| --- | --- | --- | --- |
| `2026_10_19_000001` | `settings` | `id`, `key`, `value`, `updated_at`, `updated_by` | PK `id`; **unique** `key` |
| `2026_10_19_000002` | `scheduler_steps` | `id`, `step_name`, `last_period`, `last_run_at`, `last_outcome`, `last_error`, `last_success_at`, `consecutive_failures` | PK `id`; **unique** `step_name` |
| `2026_10_19_000003` | `operational_logs` | `id`, `level`, `event`, `actor_type`, `actor_ref`, `context`, `ip`, `created_at` | PK `id`; index on `created_at` |

- 🚀 **When they run**: automatically at **container start** on every deploy (`AUTORUN_LARAVEL_MIGRATION=true`); locally with `php artisan migrate`. A second run reports **"Nothing to migrate"**.
- 🧱 **Changing the schema**: only through a **new** migration. Existing migrations are never edited (FR-033).
- 🩺 **Structure check**: `backend/config/store-schema.php` lists each table and the columns the app relies on. `DatabaseStoreProbe` compares it with the database and reports anything missing, so `/health` stays `limited` until migrations have run. Later features add their tables to the same registry.
