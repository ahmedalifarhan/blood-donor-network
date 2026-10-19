# Implementation Plan: Project Foundation

**Branch**: `001-project-foundation` | **Date**: 2026-10-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-project-foundation/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

> 🧱 Build the **walking skeleton**: a static, Arabic RTL **Next.js** site on Vercel that shows a live three-state service status from a **Laravel** API on Render. The API reaches storage only through **repository contracts**, implemented with **Eloquent** on **PostgreSQL** (deployed) and **SQLite** (local and tests), and proven by one shared contract suite. The schema changes only through **Laravel migrations**, which run automatically at container start. A secret-protected **tick endpoint**, called every minute by cron-job.org, runs idempotent steps and keeps the free host awake. **Settings** have code-defined defaults with database overrides. Operator settings commands run from GitHub Actions or the operator's machine because Render free has no shell. CI gates every change.

> 🐘 **Store change (2026-10-19):** the owner replaced Google Sheets with PostgreSQL (research R16). The Sheets-only design (quota budget, cell escaping, global write lock, backoff, sandbox spreadsheet) is superseded.

| Key design choice | Why | Research |
| --- | --- | --- |
| Static page + client-side status fetch | Instant render on a slow mobile connection, even during Render cold start | R10 |
| PostgreSQL via Eloquent repositories; SQLite locally and in tests | Transactions and constraints; one `DB_URL` secret, any host | R16 |
| Migrations run at container start | Schema always matches the code after a deploy; no manual setup step | R12, R16 |
| Unique constraints + atomic `INSERT … ON CONFLICT` | Concurrent writes never lost, without an application lock | R16 |
| Bound parameters only | Values round-trip exactly and can never run as code | R16 |
| Store probe and settings cached 60 s | Visitor traffic reaches the database at most about once a minute | R16, FR-003 |
| Period keys + per-step lock + state row | Double, late, or overlapping ticks never repeat effects | R6 |
| Refused-call aggregation | Attackers can't flood the log table or fill a small free database | R7 |
| Operator commands via `workflow_dispatch` | Render free has no shell or one-off jobs | R8 |

## Technical Context

| Item | Value |
| --- | --- |
| **Language/Version** | PHP **8.3** (Laravel **13.x**); TypeScript **strict** on Node **24 LTS** (Next.js **16.x**) |
| **Primary Dependencies** | Backend: `laravel/framework` (Eloquent, migrations), `symfony/uid` (ULID). Frontend: `next`, `react`, `next-intl` (no-routing mode), `tailwindcss` v4, shadcn/ui (Radix + `@radix-ui/react-direction`) |
| **Storage** | **PostgreSQL** when deployed (`DB_CONNECTION=pgsql`, one secret `DB_URL`, connection timezone UTC); **SQLite** for local development (`database/database.sqlite`) and tests (in memory; e2e uses `database/e2e.sqlite`). Laravel **file** cache for the 60 s probe and settings caches and the per-step scheduler lock |
| **Testing** | Backend: **Pest**, **Larastan** (level 8), **Pint**. Frontend: **ESLint** (+ `jsx-a11y`), Prettier, `tsc --noEmit`, **Vitest** + Testing Library, **Playwright** + **@axe-core/playwright**. Secrets: **gitleaks** |
| **Target Platform** | Backend: Docker (`serversideup/php:8.3-fpm-nginx`) on **Render free**. Frontend: **Vercel Hobby** (static). Scheduler: **cron-job.org** (1 min, 30 s timeout) |
| **Project Type** | Web application: frontend + backend API in one monorepo |
| **Performance Goals** | Status page readable ≤ 5 s at 150 ms latency / 1.6 Mbps with 4× CPU slowdown (SC-001); `/api/v1/health` responds ≤ 3 s at any hour (SC-002); tick completes ≤ 25 s |
| **Constraints** | $0/month (SC-007; at risk if a paid database host is chosen, see below); single backend instance (Render free; the per-step lock uses the file cache); Render ≤ 750 h/month; Vercel Hobby non-commercial; WCAG 2.1 AA; no secrets in repo |
| **Scale/Scope** | One page, 3 HTTP endpoints, 3 tables (3 migrations), 1 scheduler step, 3 operator commands, 19 settings; pilot traffic is a few hundred visits/day with bursts from shared links |

All former unknowns are resolved in [research.md](./research.md). **No `NEEDS CLARIFICATION` remain.**

### ⏳ Open operator decision: PostgreSQL host

`render.yaml` declares no database, so any host works through `DB_URL`. The choice is still open (research R16):

| Option | Cost | Catch |
| --- | --- | --- |
| **Render free PostgreSQL** | $0 | Expires after **30 days**, deleted 14 days later: ❌ not for real data |
| **Render Basic** | ~US$6–7/month | Paid: breaks SC-007 and needs a written decision here (constitution VI) |
| **Supabase free** | $0 | 500 MB; pauses after 7 idle days (prevented by the every-minute tick) |
| **Neon free** | $0 | 0.5 GB; 100 compute-hours/month, which an every-minute tick may use up |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate for this feature | Pre-design | Post-design | Evidence |
| --- | --- | --- | --- | --- |
| **I. Not a Medical Authority** | Disclaimer on every page | ✅ | ✅ | FR-005; footer key `footer.disclaimer` ([ui-status.md](./contracts/ui-status.md)) |
| **II. Privacy by Design** | No personal data exposed; `noindex`; masking in logs | ✅ | ✅ | FR-004/006; `X-Robots-Tag`; log masking ([data-model.md](./data-model.md) §3); `/health` returns only `status` and `checkedAt` |
| **III. One Phone, One Verified Donor** | — | ➖ N/A | ➖ N/A | No phone handling in this feature (normalizer arrives with donor registration) |
| **IV. Location-Agnostic, Config over Code** | No pilot names; i18n; UTC; Arabic-first RTL | ✅ | ✅ | `messages/ar.json`; `APP_TIMEZONE=UTC`; logical CSS only (research R11) |
| **V. Storage-Agnostic** | Repositories only (Eloquent models used only inside them); database by `DB_CONNECTION`; shared contract suite on SQLite and PostgreSQL; **migrations are the only schema change path**; **bound parameters** only; **unique constraints + atomic upserts** instead of a global lock; UTC timestamps; ULIDs; credentials only in `DB_URL`; no manual edits | ✅ | ✅ | [storage-contracts.md](./contracts/storage-contracts.md) C1–C13; [data-model.md](./data-model.md); research R16; migrations run at container start |
| **V. Scheduled backup** | `pg_dump` backup with a tested restore **before any personal data is stored** | ✅ | ✅ not yet due | No personal data in this feature; obligation carried to donor registration (DS-08, see Complexity Tracking) |
| **VI. Emergency Reliability** | Free path; one scheduler endpoint; idempotent steps; keep-alive; failures logged and visible | ✅ | ✅ | Tick contract ([http-api.yaml](./contracts/http-api.yaml)); research R6; operator health view (FR-013); cron failure emails |
| **VII. Accountability & Abuse Resistance** | Server-side validation; rate limits; secrets in env; append-only log | ✅ | ✅ | Rate limits on `/health`, `/health/details`, `/internal/tick` (R7, R9); `OperationalLogRepository` has no update/delete; gitleaks |
| **Tech constraints** | Next.js never touches storage; API Resources whitelist fields; `/api/v1` | ✅ | ✅ | FR-007; client fetch to Laravel only; resources for `PublicHealth`, `DetailedHealth`, `TickResult` |
| **Workflow gates** | Test-first for core rules; CI gates; public-endpoint leakage test | ✅ | ✅ | Test-first list (research R14); `GET /health` feature test asserts the response keys are exactly `status` and `checkedAt` |

**Result**: ✅ **PASS** against constitution **v1.1.0** (PostgreSQL rules in Principle V). The backup deferral below is no longer a violation, but stays recorded so the obligation is not lost.

## Project Structure

### Documentation (this feature)

```text
specs/001-project-foundation/
├── plan.md                         # This file
├── research.md                     # Phase 0: decisions R1–R16
├── data-model.md                   # Phase 1: settings, scheduler_steps, operational_logs, Service Status
├── quickstart.md                   # Phase 1: run, deploy, validation V1–V14
├── contracts/
│   ├── http-api.yaml               # OpenAPI: /health, /health/details, /internal/tick
│   ├── storage-contracts.md        # Repository interfaces + shared suite C1–C13
│   ├── operator-commands.md        # settings:* + GitHub workflow
│   └── ui-status.md                # Page shell, status states, message keys
├── checklists/requirements.md      # Spec quality checklist
└── tasks.md                        # Phase 2 (/speckit-tasks — not created here)
```

### Source Code (repository root)

```text
.github/workflows/
├── ci.yml                          # backend · frontend · e2e · secrets-scan · docker-image (PostgreSQL run included); not required for merging while T066 is skipped
└── ops-settings-set.yml            # workflow_dispatch → php artisan settings:set (DB_URL from the GitHub Environment)
render.yaml                         # Render blueprint (docker, free, rootDir backend, DB_CONNECTION=pgsql, DB_URL secret; no database declared)

backend/                            # Laravel 13, PHP 8.3
├── Dockerfile                      # serversideup/php:8.3-fpm-nginx, AUTORUN_LARAVEL_MIGRATION=true
├── .env.example
├── app/
│   ├── Console/Commands/           # SettingsList, SettingsSet, SettingsReset
│   ├── Http/
│   │   ├── Controllers/Api/V1/     # HealthController, HealthDetailsController, TickController
│   │   ├── Middleware/             # SchedulerSecret, OperatorBasicAuth, NoIndexHeaders
│   │   └── Resources/              # PublicHealthResource, DetailedHealthResource, TickResultResource
│   ├── Models/                     # Setting, SchedulerStep, OperationalLog (throws on update/delete); used only by repositories
│   ├── Repositories/
│   │   ├── Contracts/              # SettingsRepository, SchedulerStateRepository, OperationalLogRepository, StoreProbe
│   │   ├── Data/                   # StepState, LogEntry, ProbeResult, LogLevel, ActorType
│   │   └── Eloquent/               # Eloquent*Repository, DatabaseStoreProbe, GuardsStoreAccess (DB errors → StoreUnavailable)
│   ├── Scheduler/                  # Step (interface), StepRegistry, TickRunner, Steps/HeartbeatStep
│   ├── Services/                   # SettingsService, HealthService, RefusedCallAggregator
│   ├── Support/                    # Masking, StoreUnavailable, Utc, Ids
│   └── Providers/StorageServiceProvider.php   # binds the contracts to the Eloquent implementations
├── config/
│   ├── settings.php                # setting definitions + defaults (FR-026)
│   └── store-schema.php            # tables → columns the probe checks (FR-033)
├── database/migrations/            # 2026_10_19_000001…000003: settings, scheduler_steps, operational_logs
├── routes/api.php                  # /api/v1/*
└── tests/
    ├── Unit/                       # period keys, Masking, ConfigCheck, SettingsService
    ├── Feature/                    # Health, HealthDetails, Tick (incl. concurrency + refusal aggregation), commands
    └── Contract/                   # shared suite C1–C13 on SQLite in memory (PostgreSQL in CI, planned)

frontend/                           # Next.js 16 App Router, TS strict
├── app/
│   ├── layout.tsx                  # <html lang="ar" dir="rtl">, font, robots noindex, providers, footer
│   ├── page.tsx                    # static home with <ServiceStatus />
│   └── not-found.tsx
├── components/
│   ├── service-status.tsx          # client: fetch /health, 8 s timeout, 4 states, aria-live
│   ├── site-footer.tsx             # medical disclaimer
│   └── ui/                         # shadcn/ui primitives
├── i18n/request.ts                 # next-intl, no-routing mode, locale "ar"
├── messages/ar.json
├── lib/api/health.ts               # typed client for PublicHealth
├── next.config.ts                  # X-Robots-Tag header
├── e2e/                            # Playwright: states, shell, axe, 360 px, touch targets
└── tests/                          # Vitest component tests
```

**Structure Decision**: a **web application monorepo** with `backend/` (Laravel API) and `frontend/` (Next.js), matching README §16. Infrastructure files (`render.yaml`, `.github/workflows/`) live at the repo root. There is no shared package: the frontend consumes the API only through the OpenAPI contract in [http-api.yaml](./contracts/http-api.yaml).

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| ⚠️ **Principle V: scheduled backup deferred** (no longer a violation under constitution v1.1.0, which requires the backup only **before any personal data is stored**; kept here so the obligation stays visible) | The foundation stores only settings, step state, and operational logs, with no personal data. Building a `pg_dump` backup now adds private storage and a weekly step with nothing worth restoring | Building it now costs effort with no protective value. **Time-box**: a scheduled `pg_dump` backup with a tested restore MUST ship **before or with the first feature that stores personal data** (donor registration), and `/speckit-analyze` on that feature must flag its absence. **Tracked** as a deferred obligation in `docs/BUSINESS_RULES.md` DS-08, so the next spec picks it up |
| ⚠️ **Quality gate: merges to `main` are not technically blocked** (constitution "CI MUST pass … before merge"; spec FR-028 "a change with a failing check MUST NOT be mergeable"; SC-006). **T066 (branch protection) skipped by owner decision, 2026-10-19** | The project is maintained by **one person**, who prefers direct pushes and merges to `main` without required pull requests, required status checks, or protection rules at this stage | Branch protection would force a pull request and a green CI run for every change, which the sole maintainer does not want yet. **Mitigation**: `ci` still runs on every push to `main`, every pull request, and on demand, and the maintainer checks its result before relying on a change. **Time-box**: revisit T066 when a **second person gets write access** or **before the pilot stores real donor data**, whichever comes first |
