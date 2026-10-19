---

description: "Task list for 001-project-foundation"
---

# Tasks: Project Foundation

**Input**: Design documents from `/specs/001-project-foundation/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ (http-api.yaml, storage-contracts.md, operator-commands.md, ui-status.md), quickstart.md

**Tests**: ✅ **Included.** The constitution makes test-first mandatory for core rules, and the spec requires a shared storage contract suite (FR-016), idempotency proof (SC-003), and automated accessibility checks (SC-010). Write each test task first and confirm it **fails** before implementing.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

| Area | Root | Notes |
| --- | --- | --- |
| 🐘 Backend | `backend/` | Laravel 13, PHP 8.3; tests in `backend/tests/{Unit,Feature,Contract}` |
| ⚛️ Frontend | `frontend/` | Next.js 16 App Router, **no `src/` dir**; tests in `frontend/tests` (Vitest) and `frontend/e2e` (Playwright) |
| ⚙️ Infra | repo root | `render.yaml`, `.github/workflows/` |

> 🔑 **Operator tasks** need the owner's accounts (Render, Vercel, cron-job.org, GitHub settings) and are marked **(operator)**. An AI agent prepares everything else.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [X] T001 Create root files: `.gitignore` (ignore `backend/vendor/`, `frontend/node_modules/`, `**/.env`, `**/.env.local`, `frontend/.next/`, `frontend/playwright-report/`, `frontend/test-results/`, `backend/storage/*.key`; keep `**/.env.example`) and `.editorconfig` (UTF-8, LF, 4 spaces PHP, 2 spaces TS/JSON/YAML/MD) at repository root
- [X] T002 Scaffold Laravel 13 in `backend/` (`composer create-project laravel/laravel backend "^13.0"`). In `backend/bootstrap/app.php` register `api: __DIR__.'/../routes/api.php'` with `apiPrefix: 'api/v1'` and keep `health: '/up'` (used as Render's health check); create empty `backend/routes/api.php`; delete `backend/resources/views/welcome.blade.php` and the `/` route in `backend/routes/web.php`
- [X] T003 [P] Scaffold Next.js 16 in `frontend/` (`npx create-next-app@16 frontend --ts --eslint --tailwind --app --no-src-dir --import-alias "@/*"`), set `"strict": true` and `"noUncheckedIndexedAccess": true` in `frontend/tsconfig.json`, delete the starter page content in `frontend/app/page.tsx`
- [X] T004 [P] Add backend dev tooling: `composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan`; create `backend/phpstan.neon` (level **8**, paths `app`, `config`, include larastan extension), `backend/pint.json` (`{"preset": "laravel"}`), and composer scripts `lint` (`pint --test`), `analyse` (`phpstan analyse`), `test` (`pest --exclude-group=sheets`) in `backend/composer.json`
- [X] T005 [P] Add frontend dev tooling: `npm i -D prettier eslint-plugin-jsx-a11y vitest @vitejs/plugin-react jsdom @testing-library/react @testing-library/jest-dom @playwright/test @axe-core/playwright`; add scripts `lint`, `format:check` (`prettier --check .`), `typecheck` (`tsc --noEmit`), `test` (`vitest run`), `e2e` (`playwright test`) to `frontend/package.json`; create `frontend/vitest.config.ts` (jsdom, setup file `frontend/tests/setup.ts` importing jest-dom) and `frontend/.prettierrc`
- [X] T006 [P] Add backend runtime deps: `composer require google/apiclient symfony/uid`; in `backend/composer.json` add `"extra": {"google/apiclient-services": ["Sheets"]}` and the `pre-autoload-dump` script `Google\\Task\\Composer::cleanup` to keep only the Sheets service
- [X] T007 Create `backend/.env.example` with empty values for `APP_NAME=BloodDonorNetwork`, `APP_ENV`, `APP_KEY`, `APP_DEBUG=false`, `APP_URL`, `APP_VERSION`, `LOG_CHANNEL=stderr`, `CACHE_STORE=file`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `DATA_DRIVER=memory`, `GOOGLE_SHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON`, `SCHEDULER_SECRET`, `OPERATOR_HEALTH_SECRET`, `FRONTEND_URL`; set `'timezone' => 'UTC'` in `backend/config/app.php`; create `backend/config/store.php` reading `DATA_DRIVER`, `GOOGLE_SHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON` (raw JSON **or** base64), and listing required env names per driver; create `frontend/.env.example` with `NEXT_PUBLIC_API_BASE_URL=`. No database is used: remove default migrations from `backend/database/migrations/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented: storage contracts, both drivers, cell safety, masking, backoff, schema setup, routing and headers.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Tests first (must fail before T011–T013)

- [X] T008 [P] Write `backend/tests/Unit/CellCodecTest.php`: encode adds exactly one `'` when a value starts with `=`, `+`, `-`, `@`, `'`, tab or CR; decode removes exactly one leading `'`; round-trip is byte-identical for `201012345678`, `=SUM(A1)`, `+201012345678`, `-5`, `@user`, `'quoted`, `''double`, `""`, `عرب مطير`, `🩸`, and a 5,000-char string
- [X] T009 [P] Write `backend/tests/Unit/MaskingTest.php`: any run of **9+ digits** becomes first 4 + `****` + last 4 (`201012345678` → `2010****5678`) inside strings, nested arrays and JSON; IPv4 masked to `/24` (`41.234.12.7` → `41.234.12.0/24`); IPv6 masked to `/48`; JSON longer than **5,000 chars** truncated with a `…` marker
- [X] T010 [P] Write `backend/tests/Unit/BackoffTest.php` with an injected fake sleeper: retries on HTTP 429 and 5xx with delay `2^n s + random(0–1000 ms)` for n = 0, 1, 2; stops after **3 retries or 8 s cumulative**, whichever comes first, then throws `StoreUnavailable`; no retry on 4xx other than 429

### Core support

- [X] T011 [P] Implement `backend/app/Support/CellCodec.php` (`encode(string): string`, `decode(string): string`) to pass T008
- [X] T012 [P] Implement `backend/app/Support/Masking.php` (`text()`, `context(array): array`, `ip(string): string`, `json(array): string` with 5,000-char cap) to pass T009
- [X] T013 [P] Implement `backend/app/Support/Backoff.php` (injectable sleeper, `run(callable)`) and `backend/app/Support/StoreUnavailable.php` (runtime exception) to pass T010

### Storage contracts and drivers

- [X] T014 Create interfaces in `backend/app/Repositories/Contracts/`: `SettingsRepository`, `SchedulerStateRepository`, `OperationalLogRepository`, `StoreProbe`, `StoreSchemaManager` with the exact methods in `specs/001-project-foundation/contracts/storage-contracts.md` (**no update/delete on OperationalLogRepository**); create readonly value objects in `backend/app/Repositories/Data/`: `StepState` (`id`, `stepName`, `lastPeriod?`, `lastRunAt?`, `lastOutcome?` ∈ `success|failure`, `lastError?` "≤ 500 chars; masked", `lastSuccessAt?`, `consecutiveFailures` int ≥ 0), `LogEntry` (`id`, `level` ∈ `warning|error`, `event`, `actorType` ∈ `System|Scheduler|Operator|Anonymous`, `actorRef?`, `context` array, `ip?`, `createdAt`), `ProbeResult` (`reachable`, `structureComplete`, `missing` list), `SchemaChange` (`kind` ∈ `tab|header`, `name`)
- [X] T015 Create `backend/config/store-schema.php` defining tabs and headers **in this order**: `Settings` = `ID, Key, Value, UpdatedAt, UpdatedBy`; `SchedulerSteps` = `ID, StepName, LastPeriod, LastRunAt, LastOutcome, LastError, LastSuccessAt, ConsecutiveFailures`; `OperationalLogs` = `ID, Level, Event, ActorType, ActorRef, Context, IP, CreatedAt`
- [X] T016 [P] Implement the in-memory driver in `backend/app/Repositories/InMemory/` (`InMemorySettingsRepository`, `InMemorySchedulerStateRepository`, `InMemoryOperationalLogRepository`, `InMemoryStoreProbe`, `InMemorySchemaManager`). Persist state through an injected Laravel cache repository (array store for single-process unit/feature tests; **file** store when served locally and in multi-process contract tests) so data survives across requests. Wrap **every read-modify-write** in `Cache::lock('store:write', 10)->block(5)` on that same cache store (same semantics as T018) so concurrent processes never lose writes. Store every cell through `CellCodec` like Sheets does. IDs via `Symfony\Component\Uid\Ulid`; timestamps UTC `Y-m-d\TH:i:s\Z`. Support fault injection: `failNext(int $times, int $status)` raising through `Backoff`
- [X] T017 Implement `backend/app/Repositories/Sheets/SheetsClient.php` wrapping `Google\Service\Sheets`: service-account auth from `config('store.credentials')` (scope `https://www.googleapis.com/auth/spreadsheets`), `getValues(tab)` with `valueRenderOption=UNFORMATTED_VALUE`, `append(tab, rows)` and `update(range, rows)` with `valueInputOption=RAW`, `addTabs(names)` via one `batchUpdate`, `title()` from spreadsheet metadata (`fields=properties.title,sheets.properties.title`). Every call goes through `Backoff`. Accept an injectable Guzzle handler for tests
- [X] T018 Implement `backend/app/Repositories/Sheets/SheetTable.php`: maps rows **by header name, not position**; ignores and **preserves** unknown columns on update; reads the whole tab, cached **60 s** under key `sheets:{spreadsheetId}:{tab}`, invalidated after any write to that tab; `withWriteLock(callable)` uses `Cache::lock('sheets:write', 10)->block(5)` around every read-modify-write; all values encoded/decoded with `CellCodec`, written as strings
- [X] T019 Implement Sheets repositories in `backend/app/Repositories/Sheets/`: `SheetsSettingsRepository` (upsert by unique `Key`), `SheetsSchedulerStateRepository` (save by unique `StepName`), `SheetsOperationalLogRepository` (append-only; masks `Context` and `IP` with `Masking`), `SheetsStoreProbe` (single metadata call, never throws, compares tabs and headers against `config/store-schema.php`), `SheetsSchemaManager` (`plan()`; `apply()` creates missing tabs, appends missing headers **at the end**, freezes row 1, never deletes/renames/reorders)
- [X] T020 Create `backend/app/Providers/StorageServiceProvider.php` binding all five contracts from `config('store.driver')` (`sheets` | `memory`; any other value throws on boot) and register it in `backend/bootstrap/providers.php`
- [X] T021 [P] Implement `backend/app/Services/ConfigCheck.php` returning the **names** (never values) of missing required env vars: always `SCHEDULER_SECRET`, `OPERATOR_HEALTH_SECRET`, `FRONTEND_URL`; plus `GOOGLE_SHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON` when driver is `sheets`

### Store setup command (FR-033)

- [X] T022 Write `backend/tests/Feature/StoreSetupCommandTest.php` (memory driver): first `php artisan store:setup` prints `+ tab Settings`, `+ tab SchedulerSteps`, `+ tab OperationalLogs` and exits `0`; second run prints `✔ no changes`; `--dry-run` writes nothing; unreachable store (fault injection) exits `1`; missing config exits `2`; `--json` outputs a change list
- [X] T023 Implement `backend/app/Console/Commands/StoreSetup.php` per `specs/001-project-foundation/contracts/operator-commands.md`: if the spreadsheet title does not end with `[sandbox]`, require interactive confirmation or `--force`; never print secrets
- [X] T024 [P] Create `.github/workflows/ops-store-setup.yml`: `workflow_dispatch` inputs `environment` (choice `sandbox`/`production`) and `dry_run` (boolean); job uses GitHub Environment `${{ inputs.environment }}`, PHP 8.3, `composer install --no-dev`, writes secrets `GOOGLE_SERVICE_ACCOUNT_JSON` and `GOOGLE_SHEET_ID` to env, runs `php artisan store:setup --force` (plus `--dry-run` when set)

### HTTP baseline

- [X] T025 Create `backend/app/Http/Middleware/NoIndexHeaders.php` adding `X-Robots-Tag: noindex`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff` to every API response; append it to the `api` middleware group in `backend/bootstrap/app.php`; set `backend/config/cors.php` to `paths: ['api/v1/health']`, `allowed_methods: ['GET']`, `allowed_origins: [env('FRONTEND_URL')]`

**Checkpoint**: Foundation ready. `./vendor/bin/pest` is green for T008–T010 and T022, and both drivers resolve from config.

> 🛠️ **Implementation notes (Phase 2, 2026-10-19)**
>
> | Topic | As built |
> | --- | --- |
> | **Driver structure (T016–T019)** | One shared `Table` layer (`app/Repositories/Table/Table.php`) does header mapping, `CellCodec`, the 60 s cache, the write lock, and `Backoff`. Shared repositories `Table{Settings,SchedulerState,OperationalLog}Repository`, `TableStoreProbe`, and `TableSchemaManager` sit on it. Drivers differ only in a small `CellBackend`: `Sheets/SheetsCellBackend` and `InMemory/InMemoryCellBackend`. Same contracts, but the drivers cannot drift apart |
> | **Extra contract method** | `StoreSchemaManager::title()` (store title for the sandbox guard) |
> | **Status-carrying error** | `App\Support\StoreRequestFailed` (HTTP status), used by Backoff and fault injection |
> | **`store:setup`** | Checks only store variables (`ConfigCheck::missingForStore()`), because it runs without the web secrets; exit code `3` = aborted at the confirmation prompt |
> | **Hardening** | `config/filesystems.php` reduced to one private, unserved disk (removed public `GET`/`PUT /storage/{path}` routes); cache/session/queue defaults changed from `database` to `file`/`array`/`sync` |
> | **Toolchain** | Composer needs `COMPOSER_MAX_PARALLEL_HTTP=1` on this Windows machine (parallel zip downloads hit file locks) |

> 🔄 **Superseded on 2026-10-19 by [Phase 3b](#phase-3b-storage-migration-to-postgresql-2026-10-19)**: storage moved from Google Sheets to **PostgreSQL**. The Sheets and in-memory drivers, the `Table` layer, `CellCodec`, `Backoff`, `StoreRequestFailed`, `StoreSchemaManager`, `store:setup`, and `ops-store-setup.yml` were removed. **T006** (Google client), **T008/T011** (cell encoding), **T010/T013** (backoff; `StoreUnavailable` kept), **T015–T020** (tabs, drivers, provider), and **T022–T024** (`store:setup`) are historical. **T014** (contracts, minus `StoreSchemaManager`) and **T021** (`ConfigCheck`, now `DB_URL` for PostgreSQL) still apply.

---

## Phase 3: User Story 1 - Visitor sees a live, Arabic service status (Priority: P1) 🎯 MVP

**Goal**: A deployed Arabic RTL page shows **running / limited / unavailable** from a live backend + store check, with the disclaimer footer and `noindex` on every page.

**Independent Test**: Open the deployed site at 360 px width; check language, direction, status, and disclaimer; make the store unreachable and see **limited**; point at a dead backend and see **unavailable** (quickstart V1–V4).

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T026 [P] [US1] Write `backend/tests/Feature/HealthTest.php`: `GET /api/v1/health` returns `{"status":"running","checkedAt":…}` with memory driver; returns `limited` when the probe is unreachable (fault injection), when structure is incomplete, or when `ConfigCheck` reports missing names; JSON keys are **exactly** `status` and `checkedAt` (leak guard); `X-Robots-Tag: noindex` present; across 50 requests within 60 s the probe is called **once**; request 61 from one IP within a minute returns `429` with `Retry-After`
- [X] T027 [P] [US1] Write `frontend/tests/service-status.test.tsx` (Vitest, fake timers, mocked `fetchHealth`): renders `status.checking` first; then `running`, `limited`, or `unavailable` text and icon; `role="status"` + `aria-live="polite"`; Retry button only in `unavailable` and it re-runs the check; times out to `unavailable` after **8 s**
- [X] T028 [P] [US1] Write `frontend/e2e/home.spec.ts` (Playwright, route interception on `**/api/v1/health`): four states (`running`, `limited`, HTTP 500 → `unavailable`, aborted request → `unavailable`); `<html lang="ar" dir="rtl">`; robots meta `noindex,nofollow`; footer contains the disclaimer text; no horizontal scroll at **360 px** width; every button ≥ **44×44 px**; `AxeBuilder` with tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` reports **zero** violations. **SC-001**: in a fresh context with Chromium network emulation through CDP `Network.emulateNetworkConditions` (**150 ms latency, 1.6 Mbps down, 750 Kbps up**, cache disabled) and a 4× CPU slowdown (`Emulation.setCPUThrottlingRate`), the `site.name` heading and the disclaimer are visible within **5 s** of navigation start. **SC-005**: visit a non-existent route and assert the 404 page also has `lang="ar"`, `dir="rtl"`, robots `noindex,nofollow`, the disclaimer, `notFound.title` text, and zero axe violations

### Implementation for User Story 1

- [X] T029 [P] [US1] Implement `backend/app/Services/HealthService.php` `publicStatus(): array` combining `ConfigCheck` and `StoreProbe::check()` cached with `Cache::remember('health:probe', 60, …)`; `running` only when config is complete **and** probe is reachable **and** structure is complete, otherwise `limited`; `checkedAt` = probe time in UTC
- [X] T030 [US1] Implement `backend/app/Http/Resources/PublicHealthResource.php` (only `status`, `checkedAt`), `backend/app/Http/Controllers/Api/V1/HealthController.php`, route `GET /health` in `backend/routes/api.php` with a named rate limiter `public-health` (60/min per IP) defined in `backend/app/Providers/AppServiceProvider.php`
- [X] T031 [P] [US1] Create `backend/Dockerfile` from `serversideup/php:8.3-fpm-nginx` (copy app, `composer install --no-dev --optimize-autoloader`, expose 8080, enable the image's Laravel automations for config/route cache at start) and `backend/.dockerignore` (`vendor`, `tests`, `.env`, `storage/logs/*`)
- [X] T032 [P] [US1] Create `render.yaml` at repo root: one web service `blood-donor-network-api`, `runtime: docker`, `plan: free`, `rootDir: backend`, `dockerfilePath: ./Dockerfile`, `healthCheckPath: /up` (Laravel's built-in, **unthrottled** health route; never the rate-limited `/api/v1/health`), env vars `APP_ENV=production`, `APP_DEBUG=false`, `DATA_DRIVER=sheets`, `CACHE_STORE=file`, `LOG_CHANNEL=stderr`, and `sync: false` for `APP_KEY`, `GOOGLE_SHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON`, `SCHEDULER_SECRET`, `OPERATOR_HEALTH_SECRET`, `FRONTEND_URL`
- [X] T033 [P] [US1] Create `frontend/messages/ar.json` with keys and texts **verbatim** from `specs/001-project-foundation/contracts/ui-status.md` (`site.name`, `status.checking`, `status.running`, `status.limited`, `status.unavailable`, `status.retry`, `notFound.title`, `notFound.body`, `notFound.home`, `footer.disclaimer`); create `frontend/i18n/request.ts` (next-intl **without i18n routing**, locale `ar`, timeZone `UTC`); wire the next-intl plugin and a global `X-Robots-Tag: noindex` header in `frontend/next.config.ts`
- [X] T034 [P] [US1] Set up RTL UI base: run `npx shadcn@latest init` in `frontend/` and add `button` and `badge` to `frontend/components/ui/`; define color tokens in `frontend/app/globals.css` meeting **≥ 4.5:1** text contrast and **≥ 3:1** for badge borders/icons; in `frontend/eslint.config.mjs` enable `jsx-a11y` recommended and a `no-restricted-syntax` rule rejecting `className` values containing physical `ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-` utilities (use `ms-/me-/ps-/pe-/start-/end-`)
- [X] T035 [P] [US1] Implement `frontend/lib/api/health.ts`: `fetchHealth(signal?: AbortSignal): Promise<'running' | 'limited' | 'unavailable'>` calling `${process.env.NEXT_PUBLIC_API_BASE_URL}/api/v1/health` with an **8,000 ms** `AbortController` timeout; any non-200, network error, abort, or invalid body → `unavailable`
- [X] T036 [P] [US1] Implement `frontend/components/site-footer.tsx` rendering `footer.disclaimer` in a `<footer>` landmark
- [X] T037 [US1] Implement `frontend/app/layout.tsx`: `<html lang="ar" dir="rtl">`, `IBM_Plex_Sans_Arabic` from `next/font/google` (subsets `arabic`, weights 400 and 600, `display: 'swap'`), `metadata.robots = { index: false, follow: false }`, title from `site.name`, `NextIntlClientProvider`, Radix `DirectionProvider dir="rtl"`, and `<SiteFooter />`
- [X] T038 [US1] Implement `frontend/components/service-status.tsx` (client component, states and visuals exactly per `ui-status.md`; icons + text so color is never the only signal; spinner respects `prefers-reduced-motion`; Retry `Button` ≥ 44×44 px), statically rendered `frontend/app/page.tsx` showing `site.name` heading + `<ServiceStatus />`, and `frontend/app/not-found.tsx` rendering **only** `notFound.title`, `notFound.body`, and a home link labelled `notFound.home` (no hard-coded text anywhere: constitution IV); wrap any Latin text or digits inside Arabic sentences in `<bdi>`
- [X] T039 [US1] Configure `frontend/playwright.config.ts`: Chromium mobile viewport 360×740, `webServer` entries starting the backend (`php artisan serve --port=8000` with `DATA_DRIVER=memory`, test secrets, `FRONTEND_URL=http://localhost:3000`) and the frontend (`npm run build && npm run start`, `NEXT_PUBLIC_API_BASE_URL=http://localhost:8000`)
- [ ] T040 [US1] (operator) Deploy per `specs/001-project-foundation/quickstart.md` §5 steps 0–2: choose the PostgreSQL host (§1; **not** Render's expiring free database for real data) and record the choice in `plan.md` (a paid host needs the written decision of constitution VI); Render blueprint with `APP_KEY`, `DB_URL`, `FRONTEND_URL`, and check that the deploy log shows the three migrations; Vercel with root `frontend`. Then run validation scenarios **V1–V4**

**Checkpoint**: User Story 1 is fully functional, deployed, and testable on its own.

> 🛠️ **Implementation notes (US1, 2026-10-19)**
>
> | Topic | As built |
> | --- | --- |
> | **Static rendering** | `next build` prerenders `/` and the 404 page as static with `cacheComponents` on; next-intl uses `setRequestLocale("ar")` and no request APIs |
> | **shadcn/ui** | Initialized with `--rtl` (radix-nova style). It brings the `cn` package (published by shadcn on npm; name re-published 2026-10, worth a later look) and `shadcn/tailwind.css`. RTL provider = `radix-ui`'s `Direction` (separate `@radix-ui/react-direction` removed) |
> | **Lint** | `jsx-a11y` recommended rules + a rule rejecting physical `ml-/mr-/pl-/pr-/left-/right-` classes |
> | **CORS** | One allowed origin → the header is always that origin; foreign origins never echoed |
> | **Results** | Backend 56 tests; frontend 15 unit + 7 e2e (axe: 0 violations; SC-001 page readable in ~2.2 s under throttling) |
> | **Not verified locally** | Docker image (no Docker on this machine): first build happens on Render (T040) |

> ℹ️ T026, T032, and T039 were later adapted to PostgreSQL / SQLite by T075–T077 (Phase 3b).

---

## Phase 3b: Storage migration to PostgreSQL (2026-10-19)

**Goal**: Replace Google Sheets with **PostgreSQL** (deployed) and **SQLite** (local development and tests) behind the same repository contracts, with no change to the API or user-visible behavior.

**Why**: owner decision on 2026-10-19 (constitution v1.1.0, Principle V). No Sheets store had been created yet, so no data had to be moved.

**Independent Test**: `./vendor/bin/pest` and `npx playwright test` are green; `/api/v1/health` reports `limited` before `php artisan migrate` and `running` after it.

- [X] T073 Create migrations `backend/database/migrations/2026_10_19_000001_create_settings_table.php`, `…000002_create_scheduler_steps_table.php`, `…000003_create_operational_logs_table.php` (ULID primary keys; unique `settings.key` and `scheduler_steps.step_name`; UTC `timestamptz(0)`; `operational_logs.context` as **text**, because masking may truncate it) and models `backend/app/Models/{Setting,SchedulerStep,OperationalLog}.php` (`OperationalLog` refuses update and delete)
- [X] T074 Implement `backend/app/Repositories/Eloquent/` (`EloquentSettingsRepository`, `EloquentSchedulerStateRepository`, `EloquentOperationalLogRepository` with atomic `INSERT … ON CONFLICT` upserts; `DatabaseStoreProbe` checking the tables and columns in `backend/config/store-schema.php`; trait `GuardsStoreAccess` turning database errors into `StoreUnavailable`) with tests in `backend/tests/Contract/` covering **C1, C2, C3, C6, C7, C8, C13** on SQLite (C5 sequentially only)
- [X] T075 Rewire `backend/app/Providers/StorageServiceProvider.php` to the Eloquent implementations; `ConfigCheck` requires `DB_URL` when `DB_CONNECTION=pgsql`; the `pgsql` connection runs in UTC; remove `store:setup`; adapt `backend/tests/Feature/HealthTest.php` to `RefreshDatabase` (same 10 behaviors) and add `backend/tests/Unit/ConfigCheckTest.php`
- [X] T076 Remove the Sheets driver, in-memory driver, `Table` layer, `CellCodec`, `Backoff`, `StoreRequestFailed`, `SchemaChange`, `StoreSchemaManager`, their tests, `.github/workflows/ops-store-setup.yml`, `DATA_DRIVER`, and the `google/apiclient` package
- [X] T077 Deploy configuration: `backend/Dockerfile` runs migrations at container start (`AUTORUN_LARAVEL_MIGRATION=true`); `render.yaml` sets `DB_CONNECTION=pgsql` and asks for `DB_URL` (`sync: false`, no database declared); `backend/.env.example` documents `DB_CONNECTION` and `DB_URL`; `frontend/playwright.config.ts` starts the backend on a fresh, migrated SQLite file
- [X] T078 Update docs: constitution v1.1.0, `README.md`, `docs/BUSINESS_RULES.md`, `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`, and this file
- [X] T079 Verify on local PostgreSQL 17: migrations (up, re-run, rollback, up), the full backend suite on `blood_test`, a 20-process parallel-writer check, and the frontend → API → PostgreSQL health path. Add `docker-compose.yml` + `docker/postgres/init/` for local PostgreSQL, a `_test` database-name guard in `backend/tests/TestCase.php`, and exclude local SQLite files in `backend/.dockerignore`
- [ ] T080 Build the Docker image locally (`docker build backend`) and run it against local PostgreSQL with the production environment (`APP_ENV=production`, `DB_CONNECTION=pgsql`, `DB_URL`, secrets): container start runs the migrations and `/api/v1/health` reports `running`. Docker Desktop is not installed on the development machine (WSL install failed), so this now runs in CI as the `docker-image` job (see the US5 notes); tick it after that job is green on GitHub

**Checkpoint**: The app runs on PostgreSQL or SQLite with only `DB_CONNECTION` / `DB_URL` changed, verified on a real PostgreSQL 17 server (T079). The Docker image is still unverified (T080).

> 🛠️ **Implementation notes (Phase 3b, 2026-10-19)**
>
> | Topic | As built |
> | --- | --- |
> | **Results on SQLite** | Backend 48 tests (Pint, Larastan level 8 clean); frontend 15 unit + 7 e2e |
> | **Results on PostgreSQL 17.11** | Migrations up / no-op re-run / rollback / up; column types as designed (`char(26)`, `timestamptz(0)`, unique indexes, `text` context); backend **48/48** on `blood_test`; session timezone UTC (server default was Africa/Cairo); 20 parallel processes → 1 `WaveSize` row, 21 settings, 20 unique log IDs; browser → `/api/v1/health` → `running` |
> | **Test count** | 80 → 48 after removing the Sheets-only `BackoffTest` and `CellCodecTest` (32 cases) |
> | **Local PostgreSQL** | Portable EnterpriseDB binaries (not code-signed) under the user profile, started with `pg_ctl` on `127.0.0.1` only; Docker alternative in `docker-compose.yml` (untested) |
> | **Local PHP** | `pdo_pgsql` ships with PHP but is off in `php.ini`; `php -d extension=pdo_pgsql` works for one-off commands, `php artisan serve` needs it enabled in `php.ini` (quickstart §2.2) |
> | **Display quirk** | `php artisan db:show` prints the unused `DB_DATABASE` default (`laravel`) when `DB_URL` is set; the real connection is the URL's database (checked with `current_database()`) |
> | **Review fixes (pre-commit)** | `DB_URL` now applies only to the `pgsql` connection (a `sqlite`-named connection with a PostgreSQL URL had silently become PostgreSQL, bypassing the test guard and letting e2e `migrate:fresh` hit the dev database); the test guard checks the resolved driver + database name (SQLite `:memory:` or `*_test` only); e2e blanks `DB_URL`; `last_error` is masked **before** the 500-char cut (masking lengthens digit runs); 5 s PostgreSQL connect timeout |
> | **Not verified** | Docker image build and container start (T080); the first Render deploy (T040) |

---

## Phase 4: User Story 2 - Scheduler keeps the platform awake and runs timed work safely (Priority: P2)

**Goal**: `POST /api/v1/internal/tick`, called every minute with a secret, runs due steps exactly once per period, survives failures and overlaps, keeps Render awake, and is visible through the operator health view.

**Independent Test**: Call tick twice in a row and twice simultaneously; one effect per minute; wrong secret runs nothing; `/health/details` shows a recent `lastSuccessAt` (quickstart V5–V8).

### Tests for User Story 2 ⚠️

- [ ] T041 [P] [US2] Write `backend/tests/Unit/PeriodKeyTest.php`: a 1-minute step at `2026-10-19 10:15:42 UTC` has key `2026-10-19T10:15Z`; keys always UTC; comparison `LastPeriod ≥ current` works lexically for the format
- [ ] T042 [P] [US2] Write `backend/tests/Feature/TickTest.php` (`RefreshDatabase` on SQLite, test steps registered via config): correct `X-Scheduler-Secret` → `200` with heartbeat `success`; second call in same minute → `skipped`; step lock held → `busy`; a throwing test step yields `failure` while other steps still run, appends one `scheduler.step_failed` log (`level=error`, `LastError` ≤ **500 chars**, masked) and increments `ConsecutiveFailures`; after a 10-minute gap exactly **one** run happens; missing/wrong secret → `401` with empty body and no step runs; the 11th refused call within a minute from one IP → `429`; refused calls in one minute produce **one** `scheduler.refused` entry with `count`; missing required config → `503` plus exactly **one** `config.missing` entry across repeated calls within an hour; routine successes create **no** `operational_logs` rows; response matches `TickResult` in `contracts/http-api.yaml`
- [ ] T043 [P] [US2] Write `backend/tests/Feature/HealthDetailsTest.php`: no credentials → `401` + `WWW-Authenticate: Basic realm="operator"`; wrong password → `401`; 11th failure in a minute → `429`; `operator` + correct secret → body matching `DetailedHealth` (driver, version, store.reachable/structureComplete/missing, `config.missing` names only and never values, `scheduler.lastSuccessAt`, steps list)

### Implementation for User Story 2

- [ ] T044 [P] [US2] Create `backend/app/Scheduler/Step.php` interface (`name(): string`, `periodMinutes(): int`, `maxRuntimeSeconds(): int`, `run(string $periodKey): void`) and `backend/app/Scheduler/PeriodKey.php` (`for(CarbonImmutable $nowUtc, int $minutes): string`, format `Y-m-d\TH:i\Z` floored to the period) to pass T041
- [ ] T045 [US2] Create `backend/config/scheduler.php` (ordered list of step classes), `backend/app/Scheduler/StepRegistry.php`, and `backend/app/Scheduler/Steps/HeartbeatStep.php` (period 1 min, max runtime 20 s; its only effect is the state update done by the runner)
- [ ] T046 [US2] Implement `backend/app/Scheduler/TickRunner.php` per research R6: for each step, non-blocking `Cache::lock("step:{name}", maxRuntime)` → `busy` if held; read state → `skipped` if `LastPeriod ≥ current`; run; on success save `LastPeriod`, `LastRunAt`, `LastOutcome=success`, `LastSuccessAt`, `ConsecutiveFailures=0`; on exception save `LastRunAt`, `LastOutcome=failure`, `LastError` (masked, ≤ 500 chars), `ConsecutiveFailures+1` and append `scheduler.step_failed`; stop starting new steps after a **25 s** total budget (remaining → `skipped`); write **one** `Log::info` line per step per tick to the host log (`stderr`) with step, period, and outcome. Routine successes go **only** there, never to `operational_logs` (FR-032); return outcomes
- [ ] T047 [P] [US2] Implement `backend/app/Services/RefusedCallAggregator.php`: per-minute cache counter plus a set of masked IPs (`/24` or `/48`); when a new minute begins (on the next refused call or the next successful tick) flush the previous minute as **one** `OperationalLogRepository::append` with event `scheduler.refused`, level `warning`, actor `Anonymous`, context `{count, windowStart, ips}`
- [ ] T048 [US2] Implement `backend/app/Http/Middleware/SchedulerSecret.php` (`hash_equals` against `config('scheduler.secret')`; on failure record via `RefusedCallAggregator`, apply rate limiter `tick-refused` 10/min per IP, return empty `401` or `429`), `backend/app/Http/Resources/TickResultResource.php`, `backend/app/Http/Controllers/Api/V1/TickController.php` (`503` when `ConfigCheck` reports missing names; in that case, if the store is reachable, append a `config.missing` entry (level `error`, actor `System`, context `{"missing": [names]}`) **at most once per hour**, tracked with a cache flag), and route `POST /internal/tick` in `backend/routes/api.php`
- [ ] T049 [US2] Implement `backend/app/Http/Middleware/OperatorBasicAuth.php` (username `operator`, password `OPERATOR_HEALTH_SECRET`, `hash_equals`, `WWW-Authenticate: Basic realm="operator"`, 10 failures/min per IP → `429`), add `HealthService::details()` in `backend/app/Services/HealthService.php` (fields per `DetailedHealth`; `version` from `APP_VERSION`), `backend/app/Http/Resources/DetailedHealthResource.php`, `backend/app/Http/Controllers/Api/V1/HealthDetailsController.php`, and route `GET /health/details`
- [ ] T050 [US2] (operator) Create the cron-job.org job per `quickstart.md` §5 step 3 (POST every 1 min, header `X-Scheduler-Secret`, 30 s timeout, failure + recovery emails), then run validation scenarios **V5–V8**

**Checkpoint**: User Stories 1 and 2 both work independently.

---

## Phase 5: User Story 3 - Contributors can swap or extend storage safely (Priority: P3)

**Goal**: One shared contract suite (C1–C13) proves the Eloquent repositories behave identically on **SQLite** and **PostgreSQL**, and it never touches production.

**Independent Test**: `./vendor/bin/pest tests/Contract` (SQLite in memory) and the same suite with `TEST_DB_URL` pointing at a disposable PostgreSQL database are both 100% green with only configuration changed (quickstart V9).

> ℹ️ T074 already covers **C1, C2, C3, C6, C7, C8, C13** on SQLite (C5 only sequentially) in `backend/tests/Contract/*Test.php`. This phase adds the PostgreSQL run and the remaining cases.

### Tests for User Story 3 ⚠️

- [ ] T051 [US3] Create the contract harness: a Pest dataset `connections` in `backend/tests/Datasets/Connections.php` (`sqlite` always; `pgsql` only when `TEST_DB_URL` is set) applied to every test in `backend/tests/Contract/`, each running on a freshly migrated database; a guard in `backend/tests/Contract/DatabaseGuard.php` that aborts unless the PostgreSQL database name ends with **`_test`**, and that reads only `TEST_DB_URL` (never `DB_URL`); tag PostgreSQL cases with group `pgsql`
- [ ] T052 [P] [US3] Extend the round-trip tests for **C10** (a column added by a later migration is ignored by repositories that do not know it, and kept on update)
- [ ] T053 [P] [US3] Move the existing C2, C3, C6, C7 tests onto the `connections` dataset so they run on both databases
- [ ] T054 [P] [US3] Write `backend/tests/Contract/ConcurrencyTest.php` covering **C4** (20 concurrent upserts on different keys → 20 rows) and **C5** (20 concurrent appends → 20 unique ULIDs, all readable) using a `Symfony\Component\Process` pool that runs a testing-only command `backend/tests/Support/ContractWriteCommand.php` (registered only when `APP_ENV=testing`). Runs on **PostgreSQL only** (group `pgsql`), because SQLite in memory is not shared between processes
- [ ] T055 [P] [US3] Write `backend/tests/Contract/ResilienceTest.php` covering **C11** (`php artisan migrate` twice → second prints `Nothing to migrate`), **C12** (an unreachable database raises `StoreUnavailable` within the 8 s budget, without retries), **C13** (failure in the middle of a write → `StoreUnavailable`, no partial row)

### Implementation for User Story 3

- [ ] T056 [US3] Run the suite on both databases and fix any divergence in `backend/app/Repositories/Eloquent/` until **C1–C8, C10–C13** pass on SQLite and PostgreSQL (C9 was Sheets-only and is retired)
- [ ] T057 [US3] Add the PostgreSQL contract run to the `backend` job of `.github/workflows/ci.yml` (T064): a `postgres:17` service container (same major version as local development), database `contract_test`, `TEST_DB_URL` set from the service, `./vendor/bin/pest tests/Contract --group=pgsql`. No secrets are needed

**Checkpoint**: Storage is swappable by configuration, proven by one suite on both databases.

---

## Phase 6: User Story 4 - Operator tunes behavior through settings, not code (Priority: P4)

**Goal**: 19 settings with documented defaults; invalid or missing values fall back safely; the operator changes them through commands and logs every change.

**Independent Test**: Empty store → every setting returns its default; `settings:set` changes one and logs it; an invalid stored value falls back to the default with a warning (quickstart V10).

### Tests for User Story 4 ⚠️

- [ ] T058 [P] [US4] Write `backend/tests/Unit/SettingsServiceTest.php`: missing → default; valid override → override; invalid override → default **plus one** `setting.invalid` warning per key per 60 s cache period; unknown stored key → ignored plus `setting.unknown`; validation rules **verbatim** from `data-model.md`: `int` = integer string within `min`–`max`; `timeRange` = `HH:MM-HH:MM`, 24 h, may cross midnight; `phoneOrEmpty` = empty or digits only, **8–15 chars**; `string` = **≤ 50 chars**
- [ ] T059 [P] [US4] Write `backend/tests/Feature/SettingsCommandsTest.php`: `settings:list` shows key, effective value, default, source (`default` / `override` / `invalid→default`) and supports `--json`; `settings:set WaveSize 6 --by=owner` exits `0` and appends `setting.changed` with `{"key":"WaveSize","old":"5","new":"6"}`; unknown key exits `3`; invalid value exits `4`; missing `--by` fails; unreachable store exits `1`; `settings:reset` stores the default and logs the change

### Implementation for User Story 4

- [ ] T060 [P] [US4] Create `backend/config/settings.php` returning `['definitions' => [ … ]]`, a **list** of arrays each with a `key` field (never nested by key, because keys contain dots and `+`/`-`), holding exactly these 19 definitions (key, type, default, min, max, description): `WaveSize` int 5 (1–50); `WaveIntervalMinutes.Emergency` int 5 (1–120); `WaveIntervalMinutes.Urgent` int 10 (1–240); `WaveIntervalMinutes.Normal` int 30 (5–1440); `AcceptTargetMultiplier` int 2 (1–5); `RotationDays` int 7 (0–60); `PublicCountThreshold` int 3 (1–20); `QuietHours` timeRange `23:00-07:00`; `EmergencyNoAcceptAlertMinutes` int 15 (1–240); `ShortageThreshold.A+`, `.A-`, `.B+`, `.B-`, `.AB+`, `.AB-`, `.O+`, `.O-` int 5 (0–1000); `AdminWhatsAppNumber` phoneOrEmpty empty; `ConsentVersion` string `1`
- [ ] T061 [US4] Implement `backend/app/Services/SettingsService.php`, indexing `config('settings.definitions')` into a map by the exact `key` string. **Never** look settings up with Laravel dot notation (`config('settings.WaveIntervalMinutes.Emergency')` would split the key). Methods: `get(string $key): int|string`, `all(): array` (with source), `set(string $key, string $value, string $by): void` (validate → `SettingsRepository::upsert` → append `setting.changed` with old/new, level `warning`, actor `Operator`, `actorRef = $by`); overrides cached 60 s and cache cleared on `set`
- [ ] T062 [US4] Implement `backend/app/Console/Commands/SettingsList.php`, `SettingsSet.php`, `SettingsReset.php` with exit codes and output per `contracts/operator-commands.md` (`--by` required for writes; never print secrets)
- [ ] T063 [P] [US4] Create `.github/workflows/ops-settings-set.yml`: `workflow_dispatch` inputs `environment` (`sandbox`/`production`), `key`, `value`, `by`; GitHub Environment secrets `DB_URL` (with `DB_CONNECTION=pgsql`); runs `php artisan settings:set "$KEY" "$VALUE" --by="$BY"` with inputs passed via `env:` (never interpolated directly into the shell script). ⚠️ The database must accept connections from GitHub Actions runners; if the host restricts by IP, document the alternative (run the command from the operator's machine)

**Checkpoint**: Settings work with defaults and operator changes.

---

## Phase 7: User Story 5 - Every change is checked automatically (Priority: P5)

**Goal**: Every PR runs formatting, static analysis, tests, accessibility, builds, and a secret scan; failures block merging.

**Independent Test**: A PR that breaks one test cannot be merged; fixing it unblocks the merge (quickstart V11–V13).

### Implementation for User Story 5

- [ ] T064 [US5] Create `.github/workflows/ci.yml` (triggers `pull_request` and `push` to `main`; `concurrency` cancel-in-progress) with jobs named exactly **`backend`** (PHP 8.3 via `shivammathur/setup-php` with `pdo_pgsql` and `pdo_sqlite`, composer cache, `composer lint`, `composer analyse`, `composer test`, plus the PostgreSQL contract run from T057), **`frontend`** (Node 24, `npm ci`, `lint`, `format:check`, `typecheck`, `test`, `build`), **`e2e`** (PHP 8.3 + Node 24, `composer install`, `npm ci`, `npx playwright install --with-deps chromium`, `npm run e2e`, upload `frontend/playwright-report` as artifact), **`secrets-scan`** (`gitleaks/gitleaks-action@v2` on full history)
- [ ] T065 [P] [US5] Create `.github/pull_request_template.md` with a constitution checklist (Principles I–VII, tests-first for core rules, no secrets, docs updated) and `.gitleaks.toml` extending the default rules
- [ ] T066 [US5] (operator) Following `specs/001-project-foundation/quickstart.md` §5 step 4, enable branch protection on `main` requiring checks `backend`, `frontend`, `e2e`, `secrets-scan`; then run validation **V11** with a deliberately failing PR and **V12–V13** from the CI results

**Checkpoint**: All user stories are independently functional and gated by CI.

> 🛠️ **Implementation notes (US5 + T057, 2026-10-19): written, not yet run on GitHub**
>
> T057, T064, and T065 stay unchecked until the first green run of `ci` on GitHub.
>
> | Topic | As built |
> | --- | --- |
> | **Files** | `.github/workflows/ci.yml`, `.github/pull_request_template.md`, `.gitleaks.toml` |
> | **Jobs** | `backend`, `frontend`, `e2e`, `secrets-scan` (the four required checks of T066) plus **`docker-image`** for T080 |
> | **Triggers** | `pull_request`, `push` to `main`, and **`workflow_dispatch`** (added): GitHub has no `main` branch yet and the default branch is `feat/s0-foundation`, so a manual run is the only way to start CI before the first pull request. A manual run also gives `secrets-scan` the **full history** (push and pull-request runs scan only their own commits) |
> | **T057 deviation** | `--group=pgsql` and `TEST_DB_URL` come from the T051 harness, which does not exist yet, so that command would run zero tests. Until T051, the `backend` job runs the **whole suite** on a `postgres:17` service with `DB_CONNECTION=pgsql` and `DB_URL=…/contract_test` (allowed by the `*_test` guard in `tests/TestCase.php`). T051 should switch it to the dataset form |
> | **gitleaks** | `gitleaks/gitleaks-action@v3` instead of `@v2`: v3 only moves from Node 20 (deprecated by GitHub) to Node 24, same inputs. Personal-account repo, so no `GITLEAKS_LICENSE`. PR comments off (read-only token). Allowlist = three public test values only: the e2e `APP_KEY`, `blood_local_only`, `ci_only` |
> | **`docker-image` (T080)** | Builds `backend/` exactly like Render, checks `pdo_pgsql`, starts the container with the production configuration against a `postgres:17` service, waits for `/up`, then requires 3 migrations **Ran** and `/api/v1/health` = `running`. Container logs are always printed |
> | **Versions** | `actions/checkout@v7`, `actions/setup-node@v7`, `actions/cache@v6`, `actions/upload-artifact@v7`, `shivammathur/setup-php@v2` (all Node 24), from the actions' release tags on 2026-10-19 |
> | **Checked locally** | Workflow YAML parses (5 jobs, pinned actions, `contents: read`); `.gitleaks.toml` parses; `composer lint` / `analyse` / `test` pass; 49/49 on local PostgreSQL; frontend `lint`, `format:check`, `typecheck`, `test` (15), `build` pass. **Not checkable locally**: the GitHub run itself, gitleaks, the Docker build (no Docker here) |
> | **No secrets used** | Every credential in `ci.yml` is a throwaway value for disposable CI containers; the API container's `APP_KEY` is generated per run and masked |

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T067 [P] Write `backend/tests/Feature/NoLeakTest.php`: for every public route (`/health`, refused `/internal/tick`, unauthenticated `/health/details`) assert the response body contains no run of 9+ digits, no env var values, and no host names (constitution workflow gate)
- [ ] T068 [P] Add security headers in `frontend/next.config.ts` (`Referrer-Policy: strict-origin-when-cross-origin`, `X-Content-Type-Options: nosniff`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`)
- [ ] T069 Remove scaffold leftovers (example tests in `backend/tests/Feature/ExampleTest.php` and `backend/tests/Unit/ExampleTest.php`, unused `backend/resources/` assets, default `frontend/public/*.svg`), then make `composer analyse` (Larastan level 8) and `npm run typecheck` pass with zero errors
- [ ] T070 [P] Add a "Run locally" section to `README.md` linking `specs/001-project-foundation/quickstart.md` §2–§3, and confirm a fresh clone runs both apps on SQLite in under 30 minutes (SC-009)
- [ ] T071 (operator) Manual TalkBack screen-reader pass on an Android phone; record findings in `specs/001-project-foundation/checklists/a11y-manual.md`
- [ ] T072 Run every validation scenario **V1–V14** and the launch checklist in `quickstart.md`; record pass/fail with date in `specs/001-project-foundation/checklists/validation.md`

---

## Dependencies & Execution Order

### Phase Dependencies

| Phase | Depends on | Blocks |
| --- | --- | --- |
| **1 Setup** | — | Everything |
| **2 Foundational** | Setup | All user stories |
| **3 US1 (P1)** 🎯 | Foundational | — |
| **3b PostgreSQL migration** | Foundational, US1 | US2–US5 (they build on the Eloquent repositories) |
| **4 US2 (P2)** | Foundational; **T049 extends `HealthService` from T029 (US1)** | — |
| **5 US3 (P3)** | Foundational | — |
| **6 US4 (P4)** | Foundational | — |
| **7 US5 (P5)** | Setup (its `e2e` job is only meaningful once US1 tests exist) | — |
| **8 Polish** | All desired stories | — |

### User Story Dependencies

- **US1**: independent after Foundational. The MVP.
- **US2**: independent, except that the operator health view (T049) adds `details()` to `HealthService` created in T029. If US2 runs before US1, T049 creates `HealthService` and T029 adds `publicStatus()`.
- **US3**: independent. It hardens what Foundational built.
- **US4**: independent.
- **US5**: independent. Starting it early is useful, since CI then protects every later story.

### Within Each User Story

- Tests are written first and must FAIL before implementation
- Support → repositories → services → endpoints → UI → deploy/validate
- Operator tasks come last in each story

### Parallel Opportunities

| Phase | Parallel group |
| --- | --- |
| Setup | T003, T004, T005, T006 |
| Foundational | T008, T009, T010 → then T011, T012, T013; T016 alongside T017–T019; T021 and T024 anytime in phase |
| US1 | Tests T026, T027, T028; then T029, T031, T032, T033, T034, T035, T036 |
| US2 | Tests T041, T042, T043; then T044, T047 |
| US3 | T052, T053, T054, T055 after T051 |
| US4 | T058, T059, T060, T063 |
| Across stories | After Foundational: US1, US3, US4, US5 can run in parallel; US2 alongside them with the `HealthService` note above |

---

## Parallel Example: User Story 1

```bash
# Tests first (all fail initially):
Task: "Write backend/tests/Feature/HealthTest.php"
Task: "Write frontend/tests/service-status.test.tsx"
Task: "Write frontend/e2e/home.spec.ts"

# Then independent implementation files together:
Task: "Implement backend/app/Services/HealthService.php"
Task: "Create backend/Dockerfile and backend/.dockerignore"
Task: "Create render.yaml"
Task: "Create frontend/messages/ar.json + frontend/i18n/request.ts"
Task: "Implement frontend/lib/api/health.ts"
Task: "Implement frontend/components/site-footer.tsx"
```

## Parallel Example: User Story 3

```bash
# After the harness (T051):
Task: "Write backend/tests/Contract/RoundTripTest.php (C1, C8, C9, C10)"
Task: "Write backend/tests/Contract/RepositoryBehaviorTest.php (C2, C3, C6, C7)"
Task: "Write backend/tests/Contract/ConcurrencyTest.php (C4, C5)"
Task: "Write backend/tests/Contract/ResilienceTest.php (C11, C12, C13)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL: blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: quickstart V1–V4 on the deployed site
5. Demo the live Arabic status page

### Incremental Delivery

| Step | Adds | Validate with |
| --- | --- | --- |
| 1 | Setup + Foundational | Unit tests T008–T010, store setup T022 |
| 2 | **US1**: live status page (MVP) | V1–V4 |
| 3 | **US5**: CI gates (recommended early) | V11–V13 |
| 4 | **US2**: scheduler + operator health + keep-alive | V5–V8 |
| 5 | **US3**: full contract suite on both drivers | V9 |
| 6 | **US4**: settings | V10 |
| 7 | Polish | V14 + full V1–V14 run |

### Parallel Team Strategy

With several AI agents or developers after Foundational:

- Agent A: US1 (frontend + health endpoint)
- Agent B: US2 (scheduler + operator health)
- Agent C: US3 + US4 (backend-only, separate files)
- Owner: operator tasks T040, T050, T066, T071

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- 🧪 Never point any test at the production database. Tests use SQLite in memory or a disposable PostgreSQL database; the database guard (T051) enforces this
