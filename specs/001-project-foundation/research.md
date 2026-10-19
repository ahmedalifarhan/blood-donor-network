# 🔬 Research: Project Foundation

**Feature**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Date**: 2026-10-19

Every technical unknown from the plan's Technical Context is resolved below. Facts about external services were checked on 2026-10-19 and must be re-checked before launch, because free-tier terms change.

> 🐘 **Store change (2026-10-19):** the owner replaced Google Sheets with **PostgreSQL** (R16). Sheets-only decisions stay below for history, marked **⛔ Superseded (2026-10-19)** with what replaces them.

---

## 📌 Summary of decisions

| # | Topic | Decision |
| --- | --- | --- |
| R1 | Framework versions | **Laravel 13** on **PHP 8.3**; **Next.js 16** (Active LTS line); **Node 24 LTS**. Exact versions pinned in lockfiles at scaffold time |
| R2 | ~~Sheets quota budget~~ | ⛔ **Superseded (2026-10-19)**: PostgreSQL has no per-minute request quota. The 60 s store-probe and settings caches stay, to keep `/health` cheap (R16) |
| R3 | ~~Backoff~~ | ⛔ **Superseded (2026-10-19)**: **no retries**. A connection or query failure surfaces at once as `StoreUnavailable`; the next tick retries (R16) |
| R4 | ~~Cell safety~~ | ⛔ **Superseded (2026-10-19)**: values are **bound parameters**, stored and read back unchanged. No formulas exist, so nothing is escaped (R16) |
| R5 | ~~Write serialization~~ | ⛔ **Superseded (2026-10-19)**: **unique constraints + atomic `INSERT … ON CONFLICT` upserts** replace the global write lock (R16) |
| R6 | Scheduler idempotency | Per-step **period keys** plus a per-step non-blocking lock plus a state row. Missed periods are not replayed |
| R7 | Refused-call flood | Rate-limit **before** logging; aggregate refused attempts into **≤ 1 log write per minute** |
| R8 | Operator commands | Run as **GitHub Actions `workflow_dispatch`** jobs (or locally), **not on Render**: free instances have no shell or one-off jobs |
| R9 | Operator health auth | **HTTP Basic** with a dedicated secret. Works in a phone browser and in uptime monitors |
| R10 | Frontend rendering | **Static page** from Vercel's CDN; status fetched **client-side** with an 8 s timeout. No Vercel functions needed |
| R11 | i18n | **next-intl without locale routing** (single locale `ar`, no middleware); `dir="rtl"` on `<html>` |
| R12 | Backend container | Docker image **`serversideup/php:8.3-fpm-nginx`** on Render free |
| R13 | Accessibility | **@axe-core/playwright** in CI (WCAG 2.1 AA tags) + `eslint-plugin-jsx-a11y` |
| R14 | Test layout | Pest unit, feature, and contract tests on **SQLite in memory**; a PostgreSQL contract run in CI is planned (US5). The `[sandbox]` title guard is ⛔ **superseded (2026-10-19)**: tests never use a remote database |
| R15 | Hosting risks | Vercel Hobby is **non-commercial only**; Render free needs **≤ 750 h/month** in the workspace; the **PostgreSQL host** is not chosen yet (Render's free database expires). Fallbacks recorded |
| R16 | Store | **PostgreSQL** when deployed (`DB_CONNECTION=pgsql`, one secret `DB_URL`); **SQLite** locally and in tests. Schema changes only through **Laravel migrations**, run automatically at container start |

---

## R1 · 🧰 Framework versions

- **Decision**: Laravel **13.x** (supports PHP 8.3–8.5) with PHP **8.3**; Next.js **16.x** (Active LTS); React as bundled with Next 16; Node **24 LTS**; Tailwind CSS **v4**.
- **Rationale**: Laravel 13 (March 2026) is the current major release and its minimum is exactly PHP 8.3, matching the requested runtime. Laravel 12 is security-only since August 2026. Next.js 16 is the Active LTS line (15.5 is Maintenance LTS).
- **Alternatives considered**: Laravel 12 (shorter support window left); Next.js 15 (maintenance only).
- **Action**: run `npm show next version` and `composer show laravel/framework --available` at scaffold time and pin the results.

## R2 · 📊 Google Sheets quota budget

> ⛔ **Superseded (2026-10-19)** by R16. PostgreSQL has no per-minute request quota, so there is no request budget. The **60 s store-probe cache** (FR-003) and the **60 s settings cache** stay, so visitor traffic does not reach the database on every load. _Original decision, kept for history:_

- **Facts** (Google docs): 300 read and 300 write requests per minute **per project**, and **60 per minute per user per project**. A service account is one user, so **60/min** is the binding limit. A batch request counts as one request. Quotas refill every minute. Each request times out at 180 s; keep payloads ≤ 2 MB.
- **Decision**: per-minute budget for this feature:

  | Caller | Reads/min | Writes/min | How |
  | --- | --- | --- | --- |
  | Scheduler tick (heartbeat) | ≤ 1 | 1 | Step-state tab cached 60 s; one row update per run |
  | Public `/health` | ≤ 1 | 0 | Store probe cached 60 s (spec clarification) |
  | Operator `/health/details` | 0–1 | 0 | Reuses the same cached probe and state |
  | Settings reads | ≤ 1 | 0 | Settings tab cached 60 s |
  | Operational log | 0 | ≤ 1 | Failures only; refused calls aggregated (R7) |
  | **Total** | **≤ 4** | **≤ 2** | Leaves > 90% headroom for later features |

- **Rationale**: later features (registration, waves) need most of the quota. The foundation must be cheap by design.
- **Alternatives considered**: no caching (traffic-driven quota exhaustion); a longer cache (status too stale).

## R3 · 🔁 Retry and backoff

> ⛔ **Superseded (2026-10-19)** by R16. There is no rate-limit response to retry. A connection or query failure is mapped to `StoreUnavailable` **at once, with no retries** (trait `GuardsStoreAccess`), so a request stays far from the 30 s cron timeout; the next tick retries naturally (R6). The `Backoff` class was removed. _Original decision, kept for history:_

- **Decision**: retry on HTTP 429 and 5xx with delays of `2^n s + random(0–1000 ms)` for n = 0, 1, 2. Stop after **3 retries or 8 s cumulative**, whichever comes first, then surface a typed `StoreUnavailable` error.
- **Rationale**: Google recommends truncated exponential backoff with jitter and a 32–64 s maximum. But cron-job.org drops calls after **30 s**, and visitors shouldn't wait. A failed tick is safe to retry on the next minute (R6).
- **Alternatives considered**: Google's 64 s maximum (blows the cron timeout); no retries (brief 429s would surface as outages).

## R4 · 🧼 Cell safety and exact round-trips

> ⛔ **Superseded (2026-10-19)** by R16. Values are passed as **bound parameters** and stored in `varchar`/`text` columns, so nothing is evaluated and `201012345678` stays text. A database has no formulas, so there is **no escaping**, and `CellCodec` was removed. The round-trip table (`=SUM(A1)`, Arabic, emoji, 5,000 chars, …) still runs as contract case **C1**. _Original decision, kept for history:_

- **Decision**:
  - All writes use `valueInputOption=RAW`; reads use `valueRenderOption=UNFORMATTED_VALUE`.
  - **Encode on write**: if a value starts with `=`, `+`, `-`, `@`, `'`, tab, or CR, prefix it with one `'`.
  - **Decode on read**: if a value starts with `'`, remove exactly one leading `'`.
  - Every cell is written as a string; types are restored by the repository mapper.
- **Rationale**: `RAW` already stops Sheets from evaluating formulas or turning `201012345678` into a number. The prefix also neutralizes the value when someone exports the sheet to CSV or Excel (constitution V). Escaping a leading `'` too makes the encoding reversible, so values come back **exactly** as entered (spec, User Story 3).
- **Verification**: the shared contract suite includes a round-trip table (`201012345678`, `=SUM(A1)`, `+20…`, `-5`, `@x`, `'quoted`, `''double`, empty, Arabic text, emoji). It runs against the sandbox sheet to confirm Google keeps the apostrophe literally under `RAW`.
- **Alternatives considered**: `USER_ENTERED` with an apostrophe (Google hides the apostrophe, but parses dates and numbers unpredictably); a zero-width prefix (invisible and confusing for the operator).

## R5 · 🔒 Write serialization

> ⛔ **Superseded (2026-10-19)** by R16. **Unique constraints** (`settings.key`, `scheduler_steps.step_name`) enforce uniqueness, and every insert-or-update is one atomic **`INSERT … ON CONFLICT`** statement (an existing row keeps its ID). The global `sheets:write` / `store:write` lock and the 60 s whole-tab read cache were removed. The **per-step scheduler lock** (R6) is unrelated and stays. _Original decision, kept for history:_

- **Decision**: a single `sheets:write` lock via Laravel `Cache::lock` on the **file** cache store. Timeout 10 s, wait up to 5 s. Every read-modify-write (find row, then update; check, then append) happens inside it. Reads outside writes use the cache.
- **Rationale**: Sheets has no transactions or unique constraints (constitution V). Render free **cannot scale beyond one instance**, so a local file lock is sufficient and free. The ephemeral filesystem is fine because locks only need to live seconds.
- **Alternatives considered**: Redis or Render Key Value (extra service, uses the 750 h budget); a Google Apps Script lock (a second runtime to maintain).
- **Revisit when**: moving to SQL, or ever running more than one instance (constitution V migration trigger).

## R6 · ⏰ Scheduler idempotency

- **Decision**:
  - Each step declares a **period** (heartbeat: 1 minute) and returns a **period key** for "now" in UTC, e.g. `2026-10-19T10:15Z`.
  - For each due step, the tick runner:
    1. Tries a **non-blocking per-step lock** (`step:{name}` on the **file** cache, TTL = the step's max runtime). If the lock is busy, the step's outcome is `busy`.
    2. Reads the step's row in `scheduler_steps`. If `last_period ≥ currentPeriod`, the outcome is `skipped`.
    3. Runs the step, then writes `last_period`, `last_run_at`, and `last_outcome`.
  - Only the **current** period is evaluated, so a 10-minute outage leads to one catch-up run, not ten.
  - **Step rule**: any external effect a step produces must itself be keyed by `(step, period)`. A crash between "run" and "write state" then repeats nothing harmful.
- **Rationale**: this meets FR-010 and SC-003 under double, late, and overlapping calls without a queue worker or a system cron.
- **Alternatives considered**: Laravel's built-in scheduler (needs `schedule:run` from a system cron, which Render free doesn't offer); a queue worker (another always-on process).

## R7 · 🛡️ Protecting the store from refused calls

- **Decision**:
  - Tick: secret checked with `hash_equals`. Failures are rate-limited **per IP (10/min)**, with HTTP 429 beyond that.
  - Refused attempts are counted in the cache and flushed as **one** `scheduler.refused` log entry per minute, with a count and masked IPs.
  - Public `/health`: 60 requests/min per IP.
- **Rationale**: FR-008 requires recording refused calls, but writing each one would let an attacker flood `operational_logs` and fill a small free database (0.5 GB or less, R16). Aggregation caps it at one row per minute.
- **Alternatives considered**: logging every attempt (storage-exhaustion vector); host logs only (violates the clarification that refused calls go to the store).

## R8 · 🧑‍🔧 Where operator commands run

- **Facts** (Render docs): free web services have **no shell access** and **cannot run one-off jobs**; they can be restarted at any time.
- **Decision**: operator commands (`settings:list`, `settings:set`, `settings:reset`) are ordinary Artisan commands that talk to the **database directly** through `DB_URL`. They run from:
  - 🖥️ the **operator's machine** with the environment's `DB_URL`, or
  - ⚙️ a **GitHub Actions `workflow_dispatch`** workflow (`ops-settings-set.yml`) that uses GitHub **Environments** (`sandbox`, `production`) holding the `DB_URL` secret, with required reviewers on `production`.
- **Schema changes are not operator commands**: Laravel migrations run automatically at container start (R12, R16). The former `store:setup` command and its `ops-store-setup.yml` wrapper were removed on 2026-10-19.
- **Rationale**: the database is reachable from anywhere with credentials, so commands don't need to run on the web host. GitHub Actions is free for this volume and keeps a run log of who triggered what.
- **Network requirement**: running a command from GitHub Actions or a laptop needs the database to **accept TLS connections from outside the host's private network** (`sslmode=require`). GitHub-hosted runners have no fixed IP, so an IP allow-list does not work with them.
- **Effect on settings**: the running backend sees a new value after its 60 s settings cache expires. The quickstart documents "changes apply within 1 minute".
- **Alternatives considered**: an HTTP endpoint for settings changes (a new attack surface before admin auth exists); Render shell (unavailable on free).

## R9 · 🔑 Operator health access

- **Decision**: `GET /api/v1/health/details` is protected by **HTTP Basic** (username `operator`, password = `OPERATOR_HEALTH_SECRET`), compared in constant time and rate-limited at 10 failures/min per IP. Without valid credentials it responds **401** with no body. The public summary stays at `/api/v1/health`.
- **Rationale**: a phone browser shows a native login prompt, and free uptime monitors (cron-job.org included) support Basic auth or custom headers. It keeps a separate secret from the scheduler (clarification Q4).
- **Alternatives considered**: a secret in the query string (leaks into logs and browser history); a custom header (not usable from a phone browser).

## R10 · 🌐 Frontend rendering and status fetch

- **Decision**:
  - The home page is a **statically rendered** Next.js page served from Vercel's CDN.
  - A small client component calls `GET {API}/api/v1/health` with an **8 s timeout**. It shows a neutral "checking…" state first, then **running**, **limited**, or **unavailable** (timeout, network error, or 5xx). The result is announced via `aria-live="polite"`.
  - The backend allows CORS only from `FRONTEND_URL`.
- **Rationale**: the page is readable immediately on a slow mobile connection (SC-001: 150 ms latency, 1.6 Mbps down) even when Render is waking up, and it doesn't consume Vercel function quota. The backend stays the only gateway to data (FR-007).
- **Alternatives considered**: server-side fetch in a server component (the page would wait for the Render cold start, about 60 s); a Next.js route-handler proxy (uses Vercel functions and adds a hop).

## R11 · 🌍 i18n, RTL, and fonts

- **Decision**:
  - **next-intl in "without i18n routing" mode**: a single locale `ar`, messages in `messages/ar.json`, no middleware or proxy, so pages stay static.
  - Root layout sets `<html lang="ar" dir="rtl">`, and Radix's `DirectionProvider` is set to `rtl` for shadcn/ui components.
  - Tailwind **logical utilities** only (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`). A lint rule forbids physical `ml-/mr-/pl-/pr-/left-/right-`.
  - Latin text and digits inside Arabic sentences are wrapped in `<bdi>` (edge case "mixed direction").
  - Font: **IBM Plex Sans Arabic** via `next/font/google`. It is downloaded at build time and self-hosted, so visitors' browsers never contact Google at runtime (privacy).
- **Rationale**: FR-001 and FR-002 are met with the least machinery. Adding English later means switching next-intl to routing mode without touching components.
- **Alternatives considered**: next-intl with `[locale]` routing now (middleware on every request for one language); the system font only (poor Arabic rendering on cheap Android phones).

## R12 · 🐳 Backend container on Render

- **Decision**: a Dockerfile based on **`serversideup/php:8.3-fpm-nginx`** (production-tuned PHP-FPM + Nginx, Laravel-friendly), listening on port 8080. A `render.yaml` blueprint at the repo root sets `runtime: docker`, `plan: free`, `rootDir: backend`, and `healthCheckPath: /up` (Laravel's built-in route, which is not rate-limited, so Render's health checker can never be throttled by the 60/min public limiter on `/api/v1/health`), with `DB_CONNECTION=pgsql` and secrets marked `sync: false` (including `DB_URL`). `APP_TIMEZONE=UTC`. The image's automation flag **`AUTORUN_LARAVEL_MIGRATION=true`** runs the migrations at **container start**, so every deploy brings the schema up to date (R16). `render.yaml` declares **no database**, so any PostgreSQL host works.
- **Facts** (Render docs): free web services spin down after **15 min** without inbound traffic and take about **1 min** to spin up. The workspace gets **750 instance hours/month**, and services are suspended if that is exceeded. Free services can't scale beyond one instance.
- **Rationale**: the every-minute tick keeps the service awake. 24 × 31 = **744 h**, which fits only if **no other free service** shares the workspace. This is documented as an operator rule in the quickstart.
- **Alternatives considered**: `php artisan serve` (not production-grade); FrankenPHP/Octane (worker-mode state leaks are a risk for beginners; revisit later).
- **To confirm at deploy**: that the free instance type is offered for Docker runtime in the dashboard. Fallback: Render's native PHP is not offered, so use a Docker host such as Koyeb or Fly with equivalent rules.

## R13 · ♿ Accessibility checks

- **Decision**: Playwright e2e runs **@axe-core/playwright** with tags `wcag2a`, `wcag2aa`, `wcag21a`, and `wcag21aa` on every public page. It fails on any violation (SC-010). Also `eslint-plugin-jsx-a11y` (recommended config), and a Playwright check that every interactive element is at least 44×44 px. A manual TalkBack pass on Android runs before release.
- **Rationale**: matches clarification Q5 and is free.
- **Alternatives considered**: Lighthouse CI (heavier; covers performance more than WCAG); pa11y (duplicates axe).

## R14 · 🧪 Test layout and test databases

- **Decision**:
  - **Backend (Pest)**, all on **SQLite in memory** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `RefreshDatabase`):
    - `tests/Unit`: period keys, masking, config check, settings validation.
    - `tests/Feature`: HTTP endpoints and operator commands.
    - `tests/Contract`: the shared storage suite, one file per contract (`SettingsRepositoryTest`, `SchedulerStateRepositoryTest`, `OperationalLogRepositoryTest`, `DatabaseStoreProbeTest`).
  - **PostgreSQL contract run** (planned, US5): the same `tests/Contract` suite in CI against a **disposable PostgreSQL service container**, so dialect differences and concurrency (C4) are tested on the real engine.
  - **Frontend**: Vitest + Testing Library for components; Playwright for e2e and accessibility. The e2e backend uses a throwaway SQLite file, `backend/database/e2e.sqlite`, rebuilt with `migrate:fresh`.
  - **Test-first** (constitution workflow): value round-trip, period-key and idempotency, refused-call aggregation, and settings fallback tests are written before implementation.
- **Sandbox guard**: ⛔ **Superseded (2026-10-19)**. The Sheets test group required `TEST_GOOGLE_SHEET_ID` and aborted unless the spreadsheet title ended with `[sandbox]`. Tests now **never use a remote database**: SQLite in memory locally, a disposable container in CI. No deployed `DB_URL` is ever available to test jobs (FR-022).
- **Rationale**: one suite proves the SQLite and PostgreSQL paths behave the same (FR-016, SC-004). Throwaway databases make it impossible for a test to touch deployed data.

## R15 · ☁️ Hosting terms and risks

| Risk | Fact | Mitigation |
| --- | --- | --- |
| **Vercel Hobby is non-commercial only** | Hobby plan restricts use to personal, non-commercial projects | The pilot is a free community service. If it gains a commercial sponsor or company owner, move the static site to **Cloudflare Pages** (no code change: it's a static export candidate) |
| **Render 750 h/month** | Exceeding it suspends all free services in the workspace | Keep this as the **only** free service in its workspace; cron-job.org email alerts on failure |
| **Render restarts at any time** | About 1 min spin-up | Frontend shows **unavailable** gracefully; the tick catches up next minute (R6) |
| **Render outbound-traffic suspension** | "Unusually high outbound traffic" may suspend | The foundation's outbound traffic is tiny (a few database queries per minute) |
| **PostgreSQL host not chosen yet** | Render's free database **expires after 30 days** and is deleted 14 days later; Supabase free pauses after 7 idle days; Neon free has 100 compute-hours/month | Open operator decision, compared in R16. `render.yaml` declares no database, so changing host is only a `DB_URL` change |
| **cron-job.org 30 s timeout** | The connection is closed after 30 s | Tick budget ≤ 25 s; store failures fail fast with no retries (R16); slow steps must split work across periods |
| **Free-tier terms change** | All providers, including the PostgreSQL host | Re-check before launch (constitution VI); listed in the quickstart's launch checklist |

## R16 · 🐘 PostgreSQL as the store

- **Decision** (owner, 2026-10-19):

  | Where | Store | How it is selected and created |
  | --- | --- | --- |
  | **Deployed** | **PostgreSQL** | `DB_CONNECTION=pgsql`; one secret **`DB_URL`** (e.g. `postgresql://user:password@host:5432/dbname?sslmode=require`) entered in the Render dashboard (`sync: false`). The `pgsql` connection sets `timezone => 'UTC'` |
  | **Local development** | **SQLite** file `database/database.sqlite` | `DB_CONNECTION=sqlite`; created by `php artisan migrate` |
  | **Automated tests** | **SQLite in memory** | `RefreshDatabase`. The Playwright e2e backend uses `backend/database/e2e.sqlite`, rebuilt with `migrate:fresh` |
  | **CI contract run** (planned, US5) | **PostgreSQL** service container | Disposable, created per run |

  - **Code**: Eloquent repositories in `backend/app/Repositories/Eloquent/` (`EloquentSettingsRepository`, `EloquentSchedulerStateRepository`, `EloquentOperationalLogRepository`, `DatabaseStoreProbe`), models in `backend/app/Models/`, bound in `StorageServiceProvider`. The repository contracts did not change.
  - **Schema**: Laravel migrations `2026_10_19_000001…000003` ([data-model.md](./data-model.md)). They run **automatically at container start** (`AUTORUN_LARAVEL_MIGRATION=true`, R12) and are the **only** way the schema changes.
  - **Concurrency**: unique constraints plus atomic **`INSERT … ON CONFLICT`** upserts. No application-level write lock.
  - **Failures**: trait `GuardsStoreAccess` maps `QueryException` / `PDOException` to **`StoreUnavailable`**, with **no retries**.
  - **Structure check**: `DatabaseStoreProbe` compares each table's columns with `config/store-schema.php` and reports what is missing (`table` or `table.column`), so `/health` stays `limited` until the migrations have run. The result is cached 60 s.
- **Rationale**: the owner wants a **proper relational store with transactions and constraints**. It also removes every Sheets workaround: the 60 requests/min quota (R2), backoff (R3), formula escaping (R4), the global write lock and single-instance rule (R5), and the sandbox title guard (R14).
- **Alternatives considered**:
  - **Keep Google Sheets**: no transactions or unique constraints, a binding 60 requests/min quota, and formula-injection workarounds.
  - **MySQL**: equally well supported by Laravel. Not chosen because Render, Supabase, and Neon all offer managed **PostgreSQL**.
- **Hosting** (⏳ **open operator decision**; `render.yaml` declares no database, so any host works through `DB_URL`):

  | Option | Cost | Limits | Fit |
  | --- | --- | --- | --- |
  | **Render free PostgreSQL** | $0 | **Expires after 30 days**, deleted 14 days later | ❌ Not acceptable for real data; trial only |
  | **Render Basic** | ~US$6–7/month | Paid instance, same provider as the backend | ✅ Simplest, but breaks the $0 target (SC-007) and needs a written decision in the plan (constitution VI) |
  | **Supabase free** | $0 | 500 MB; pauses after **7 idle days** | ✅ Candidate: the every-minute tick prevents the pause |
  | **Neon free** | $0 | 0.5 GB; **100 compute-hours/month** | ⚠️ The every-minute tick keeps compute awake (~744 h/month), so the allowance may run out |

- **Risks and mitigations**:

  | Risk | Mitigation |
  | --- | --- |
  | **Dialect drift**: SQLite (local, tests) differs from PostgreSQL (types, `ON CONFLICT`, time zones, concurrency) | Planned PostgreSQL contract run in CI with a disposable service container (US5); C4 runs only there |
  | **Free database expires or pauses** | Never keep real data on Render free; the every-minute tick keeps Supabase awake; re-check terms before launch (R15) |
  | **Operator commands need outside access** | The database must accept TLS connections from the operator's machine or GitHub Actions (R8) |
  | **Masked log context may not be valid JSON** | `operational_logs.context` is `text`, not `jsonb`: masking can truncate it to 5,000 chars with a `…` marker |

---

## 🔗 Sources

- [Google Sheets API usage limits](https://developers.google.com/workspace/sheets/api/limits) (historical, R2)
- [Supabase pricing](https://supabase.com/pricing) · [Neon pricing](https://neon.com/pricing)
- [Render: Deploy for free](https://render.com/docs/free) (web services and PostgreSQL)
- [Vercel Hobby plan](https://vercel.com/docs/plans/hobby)
- [cron-job.org FAQ](https://cron-job.org/en/faq/)
- [Laravel 13 requirements (PHP 8.3+)](https://jonathansblog.co.uk/laravel-13-whats-new-php-8-3) · [Laravel versions overview](https://benjamincrozat.com/laravel-versions)
- [Next.js releases](https://releases.sh/vercel/nextjs.md) · [Next.js on Wikipedia](https://en.wikipedia.org/wiki/Next.js)
