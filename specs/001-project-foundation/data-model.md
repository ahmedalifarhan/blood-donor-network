# 💾 Data Model: Project Foundation

**Feature**: [spec.md](./spec.md) · **Research**: [research.md](./research.md) · **Date**: 2026-10-19

Three stored entities and one derived view. In the spreadsheet store each stored entity is **one tab**, and row 1 holds the header names. Columns are mapped **by header name, not position**, so later features can append columns safely (FR-033).

---

## 🧭 Global storage rules

| Rule | Detail | Source |
| --- | --- | --- |
| **IDs** | ULID strings (26 chars, time-sortable, random) | FR-017, DS-01 |
| **Times** | UTC, ISO 8601 with `Z` (e.g. `2026-10-19T10:15:00Z`) | FR-021, DS-07 |
| **Cell encoding** | Every cell is written as a string with `RAW`. Leading `= + - @ ' \t \r` gets one `'` prefix on write, and exactly one is stripped on read | FR-018, research R4 |
| **Writes** | Every read-modify-write runs inside one global lock per driver: `sheets:write` (Sheets driver, T018) or `store:write` (in-memory driver on the shared file cache, T016). Both are `Cache::lock(…, 10)->block(5)` | FR-019, research R5 |
| **Reads** | Whole tab per request, cached 60 s, cache cleared for that tab after any write to it | FR-020, research R2 |
| **Booleans / numbers** | Stored as `true`/`false` and decimal strings; parsed by the repository mapper | R4 |
| **JSON** | Compact JSON string in one cell; max 5,000 chars (truncated with `…` marker) | — |
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

### Stored override (tab **`Settings`**)

| Column | Type | Required | Notes |
| --- | --- | --- | --- |
| `ID` | ulid | ✅ | |
| `Key` | string | ✅ | **Unique**; must match a known definition |
| `Value` | string | ✅ | Encoded per R4 |
| `UpdatedAt` | datetime | ✅ | |
| `UpdatedBy` | string | ✅ | Operator name given to the command (`--by`) |

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
override Key not in definitions → ignored  + OperationalLog(setting.unknown, warning)
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

One row per registered step (tab **`SchedulerSteps`**). It guarantees one run per period (FR-010) and feeds operator health (FR-013).

| Column | Type | Required | Notes |
| --- | --- | --- | --- |
| `ID` | ulid | ✅ | |
| `StepName` | string | ✅ | **Unique**, e.g. `heartbeat` |
| `LastPeriod` | string | — | Period key of the last **completed** run, e.g. `2026-10-19T10:15Z` |
| `LastRunAt` | datetime | — | Start time of the last attempt |
| `LastOutcome` | enum | — | `success`, `failure` |
| `LastError` | string | — | Short reason; ≤ 500 chars; masked |
| `LastSuccessAt` | datetime | — | |
| `ConsecutiveFailures` | int | ✅ | Reset to 0 on success |

### Per-call outcome (returned by the tick, not stored)

| Outcome | Meaning | State written? |
| --- | --- | --- |
| `success` | Ran for the current period | ✅ `LastPeriod`, `LastRunAt`, `LastOutcome`, `LastSuccessAt` |
| `failure` | Threw an error | ✅ `LastRunAt`, `LastOutcome`, `LastError`, `ConsecutiveFailures+1` (+ operational log) |
| `skipped` | `LastPeriod ≥ current period` | ❌ |
| `busy` | Another run holds the step lock | ❌ |

### State transitions

```text
          (row created by store:setup or first run)
                         │
                         ▼
   ┌──────────── no completed period ────────────┐
   │                                              │
   ▼ run OK                              run fails▼
 success(P) ──── next period, run OK ──▶ success(P+1)
   │                                              ▲
   └──── run fails ──▶ failure(retry same P next tick) ──┘
```

A failed period is **retried** on the next tick, because `LastPeriod` was not advanced. Missed periods are never replayed one by one (research R6).

---

## 3. 📝 Operational Log Entry

Durable, append-only record of notable events (tab **`OperationalLogs`**). Routine successful runs are **not** stored here (FR-032).

| Column | Type | Required | Notes |
| --- | --- | --- | --- |
| `ID` | ulid | ✅ | |
| `Level` | enum | ✅ | `warning`, `error` |
| `Event` | enum | ✅ | See table below |
| `ActorType` | enum | ✅ | `System`, `Scheduler`, `Operator`, `Anonymous` |
| `ActorRef` | string | — | e.g. operator name, step name |
| `Context` | json | — | Masked; ≤ 5,000 chars |
| `IP` | string | — | Masked to `/24` (IPv4) or `/48` (IPv6) |
| `CreatedAt` | datetime | ✅ | |

### Events in this feature

| Event | Level | Actor | Context example |
| --- | --- | --- | --- |
| `scheduler.refused` | warning | Anonymous | `{"count": 7, "windowStart": "…", "ips": ["41.x.x.0/24"]}` (aggregated per minute, research R7) |
| `scheduler.step_failed` | error | Scheduler | `{"step": "heartbeat", "period": "…", "error": "StoreUnavailable"}` |
| `setting.changed` | warning | Operator | `{"key": "WaveSize", "old": "5", "new": "6"}` |
| `setting.invalid` | warning | System | `{"key": "WaveSize", "stored": "abc", "usedDefault": "5"}` |
| `setting.unknown` | warning | System | `{"key": "Foo"}` |
| `config.missing` | error | System | `{"missing": ["SCHEDULER_SECRET"]}` (logged when the store is reachable, **at most once per hour**) |

**Rules**: append-only. No update or delete method exists on the repository contract. The `setting.changed` level is `warning` so it stands out in operator review.

---

## 4. 🩺 Service Status (derived, not stored)

| Field | Source | Public? |
| --- | --- | --- |
| `status` | `running` if config OK **and** store probe OK; otherwise `limited` | ✅ (two values; the frontend adds `unavailable` when the backend can't be reached) |
| `checkedAt` | Time of the cached store probe | ✅ |
| `store.reachable` | Store probe (cached ≤ 60 s) | ❌ operator only |
| `store.structureComplete` | All required tabs and headers present | ❌ |
| `config.missing` | Names (never values) of missing required env vars | ❌ |
| `scheduler.lastSuccessAt` | Max `LastSuccessAt` across steps | ❌ |
| `scheduler.steps[]` | Each step's state row | ❌ |
| `driver` | `sheets` / `memory` | ❌ |
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

## 🗂️ Tab layout created by `store:setup`

| Tab | Headers (in order) |
| --- | --- |
| `Settings` | `ID`, `Key`, `Value`, `UpdatedAt`, `UpdatedBy` |
| `SchedulerSteps` | `ID`, `StepName`, `LastPeriod`, `LastRunAt`, `LastOutcome`, `LastError`, `LastSuccessAt`, `ConsecutiveFailures` |
| `OperationalLogs` | `ID`, `Level`, `Event`, `ActorType`, `ActorRef`, `Context`, `IP`, `CreatedAt` |

`store:setup` creates missing tabs, appends missing headers **at the end**, and freezes row 1. It never deletes, renames, or reorders anything (FR-033). Later features register their tabs in the same schema registry (`backend/config/store-schema.php`).
