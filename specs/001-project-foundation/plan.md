# Implementation Plan: Project Foundation

**Branch**: `001-project-foundation` | **Date**: 2026-10-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-project-foundation/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

> 🧱 Build the **walking skeleton**: a static, Arabic RTL **Next.js** site on Vercel that shows a live three-state service status from a **Laravel** API on Render. The API reaches storage only through **repository contracts** with two drivers (**Google Sheets** and **InMemory**), both proven by one shared contract suite. A secret-protected **tick endpoint**, called every minute by cron-job.org, runs idempotent steps and keeps the free host awake. **Settings** have code-defined defaults with store overrides. Operator tooling (store setup, settings) runs from GitHub Actions because Render free has no shell. CI gates every change.

| Key design choice | Why | Research |
| --- | --- | --- |
| Static page + client-side status fetch | Instant render on a slow mobile connection, even during Render cold start | R10 |
| Store probe cached 60 s, settings and state cached 60 s | Stays far inside the **60 req/min** Sheets per-user quota | R2 |
| `RAW` writes + reversible `'` escaping | Formula-safe **and** exact round-trips | R4 |
| File-cache lock + single Render instance | Free serialization without Redis | R5 |
| Period keys + per-step lock + state row | Double, late, or overlapping ticks never repeat effects | R6 |
| Refused-call aggregation | Attackers can't burn the write quota | R7 |
| Operator commands via `workflow_dispatch` | Render free has no shell or one-off jobs | R8 |

## Technical Context

| Item | Value |
| --- | --- |
| **Language/Version** | PHP **8.3** (Laravel **13.x**); TypeScript **strict** on Node **24 LTS** (Next.js **16.x**) |
| **Primary Dependencies** | Backend: `laravel/framework`, `google/apiclient` (Sheets v4), `symfony/uid` (ULID). Frontend: `next`, `react`, `next-intl` (no-routing mode), `tailwindcss` v4, shadcn/ui (Radix + `@radix-ui/react-direction`) |
| **Storage** | Google Sheets via service account (`DATA_DRIVER=sheets`); InMemory driver for tests and local runs (`DATA_DRIVER=memory`). Laravel **file** cache for read cache and locks |
| **Testing** | Backend: **Pest**, **Larastan** (level 8), **Pint**. Frontend: **ESLint** (+ `jsx-a11y`), Prettier, `tsc --noEmit`, **Vitest** + Testing Library, **Playwright** + **@axe-core/playwright**. Secrets: **gitleaks** |
| **Target Platform** | Backend: Docker (`serversideup/php:8.3-fpm-nginx`) on **Render free**. Frontend: **Vercel Hobby** (static). Scheduler: **cron-job.org** (1 min, 30 s timeout) |
| **Project Type** | Web application: frontend + backend API in one monorepo |
| **Performance Goals** | Status page readable ≤ 5 s at 150 ms latency / 1.6 Mbps with 4× CPU slowdown (SC-001); `/api/v1/health` responds ≤ 3 s at any hour (SC-002); tick completes ≤ 25 s |
| **Constraints** | $0/month (SC-007); ≤ 60 Sheets reads + ≤ 60 writes/min (foundation budget ≤ 4 + ≤ 2); single backend instance; Render ≤ 750 h/month; Vercel Hobby non-commercial; WCAG 2.1 AA; no secrets in repo |
| **Scale/Scope** | One page, 3 HTTP endpoints, 3 tabs, 1 scheduler step, 4 operator commands, 19 settings; pilot traffic is a few hundred visits/day with bursts from shared links |

All former unknowns are resolved in [research.md](./research.md). **No `NEEDS CLARIFICATION` remain.**

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate for this feature | Pre-design | Post-design | Evidence |
| --- | --- | --- | --- | --- |
| **I. Not a Medical Authority** | Disclaimer on every page | ✅ | ✅ | FR-005; footer key `footer.disclaimer` ([ui-status.md](./contracts/ui-status.md)) |
| **II. Privacy by Design** | No personal data exposed; `noindex`; masking in logs | ✅ | ✅ | FR-004/006; `X-Robots-Tag`; log masking ([data-model.md](./data-model.md) §3); `/health` returns only `status` and `checkedAt` |
| **III. One Phone, One Verified Donor** | — | ➖ N/A | ➖ N/A | No phone handling in this feature (normalizer arrives with donor registration) |
| **IV. Location-Agnostic, Config over Code** | No pilot names; i18n; UTC; Arabic-first RTL | ✅ | ✅ | `messages/ar.json`; `APP_TIMEZONE=UTC`; logical CSS only (research R11) |
| **V. Storage-Agnostic** | Repositories only; driver by config; shared contract suite; ULIDs; RAW + injection-safe; one write lock; single instance; cache + backoff; private sheet; no manual edits | ✅ | ✅ | [storage-contracts.md](./contracts/storage-contracts.md) C1–C13; research R2–R5; `store:setup` replaces manual edits |
| **V. Scheduled backup copy** | Backup kept while Sheets is the store | ⚠️ | ⚠️ deferred | See Complexity Tracking: no personal data exists yet |
| **VI. Emergency Reliability** | Free path; one scheduler endpoint; idempotent steps; keep-alive; failures logged and visible | ✅ | ✅ | Tick contract ([http-api.yaml](./contracts/http-api.yaml)); research R6; operator health view (FR-013); cron failure emails |
| **VII. Accountability & Abuse Resistance** | Server-side validation; rate limits; secrets in env; append-only log | ✅ | ✅ | Rate limits on `/health`, `/health/details`, `/internal/tick` (R7, R9); `OperationalLogRepository` has no update/delete; gitleaks |
| **Tech constraints** | Next.js never touches storage; API Resources whitelist fields; `/api/v1` | ✅ | ✅ | FR-007; client fetch to Laravel only; resources for `PublicHealth`, `DetailedHealth`, `TickResult` |
| **Workflow gates** | Test-first for core rules; CI gates; public-endpoint leakage test | ✅ | ✅ | Test-first list (research R14); `GET /health` feature test asserts the response keys are exactly `status` and `checkedAt` |

**Result**: ✅ **PASS**, with one time-boxed deferral recorded below.

## Project Structure

### Documentation (this feature)

```text
specs/001-project-foundation/
├── plan.md                         # This file
├── research.md                     # Phase 0: decisions R1–R15
├── data-model.md                   # Phase 1: Settings, SchedulerSteps, OperationalLogs, Service Status
├── quickstart.md                   # Phase 1: run, deploy, validation V1–V14
├── contracts/
│   ├── http-api.yaml               # OpenAPI: /health, /health/details, /internal/tick
│   ├── storage-contracts.md        # Repository interfaces + shared suite C1–C13
│   ├── operator-commands.md        # store:setup, settings:* + GitHub workflows
│   └── ui-status.md                # Page shell, status states, message keys
├── checklists/requirements.md      # Spec quality checklist
└── tasks.md                        # Phase 2 (/speckit-tasks — not created here)
```

### Source Code (repository root)

```text
.github/workflows/
├── ci.yml                          # backend · frontend · e2e · secrets-scan (required checks)
├── contract-sheets.yml             # Sheets contract suite on sandbox (push to main, PRs touching Sheets driver, manual)
├── ops-store-setup.yml             # workflow_dispatch → php artisan store:setup
└── ops-settings-set.yml            # workflow_dispatch → php artisan settings:set
render.yaml                         # Render blueprint (docker, free, rootDir backend)

backend/                            # Laravel 13, PHP 8.3
├── Dockerfile                      # serversideup/php:8.3-fpm-nginx
├── .env.example
├── app/
│   ├── Console/Commands/           # StoreSetup, SettingsList, SettingsSet, SettingsReset
│   ├── Http/
│   │   ├── Controllers/Api/V1/     # HealthController, HealthDetailsController, TickController
│   │   ├── Middleware/             # SchedulerSecret, OperatorBasicAuth, NoIndexHeaders
│   │   └── Resources/              # PublicHealthResource, DetailedHealthResource, TickResultResource
│   ├── Repositories/
│   │   ├── Contracts/              # SettingsRepository, SchedulerStateRepository, OperationalLogRepository, StoreProbe, StoreSchemaManager
│   │   ├── Data/                   # StepState, LogEntry, ProbeResult, SchemaChange, LogLevel, ActorType
│   │   ├── Table/                  # CellBackend (interface), Table (mapping, codec, cache, lock, backoff), Table* repositories, probe, schema manager
│   │   ├── Sheets/                 # SheetsCellBackend (Google Sheets API, RAW writes, lazy client)
│   │   └── InMemory/               # InMemoryCellBackend (cache-backed, mimics Sheets, fault injection)
│   ├── Scheduler/                  # Step (interface), StepRegistry, TickRunner, Steps/HeartbeatStep
│   ├── Services/                   # SettingsService, HealthService, RefusedCallAggregator
│   ├── Support/                    # CellCodec, Masking, Backoff, StoreRequestFailed, StoreUnavailable, Utc, Ids
│   └── Providers/StorageServiceProvider.php   # binds DATA_DRIVER
├── config/
│   ├── settings.php                # setting definitions + defaults (FR-026)
│   └── store-schema.php            # tabs + headers registry (FR-033)
├── routes/api.php                  # /api/v1/*
└── tests/
    ├── Unit/                       # CellCodec, period keys, Masking, SettingsService, Backoff
    ├── Feature/                    # Health, HealthDetails, Tick (incl. concurrency + refusal aggregation), commands
    └── Contract/                   # shared suite C1–C13; dataset: memory | sheets (group)

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
| ⚠️ **Principle V: scheduled backup copy deferred** | The foundation stores only settings, step state, and operational logs, with no personal data. Building backup now adds Drive API scope and a weekly step with nothing worth restoring | Building it now costs effort with no protective value. **Time-box**: a `weekly-backup` tick step MUST ship **before or with the first feature that stores personal data** (donor registration), and `/speckit-analyze` on that feature must flag its absence. **Tracked** as a deferred obligation in `docs/BUSINESS_RULES.md` DS-08, so the next spec picks it up |
