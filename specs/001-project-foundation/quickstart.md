# 🚀 Quickstart: Project Foundation

**Feature**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

How to run, test, deploy, and **prove** this feature works. Contracts are linked rather than repeated.

> 🗄️ **Storage (since 2026-10-19)**: **PostgreSQL** when deployed, **SQLite** for local development and tests. The schema is created by **Laravel migrations**; no Google account is needed.

---

## 1. 🧰 Prerequisites

| Tool | Version | Check |
| --- | --- | --- |
| PHP (with `pdo_sqlite`) | 8.3 | `php -v` · `php -m` |
| Composer | 2.x | `composer -V` |
| Node.js | 24 LTS | `node -v` |
| Git + GitHub repo | — | `git remote -v` |

| Account / resource | Needed for |
| --- | --- |
| A **PostgreSQL** database and its connection URL (`DB_URL`) | Deployed store. ⚠️ Render's free PostgreSQL is **deleted 44 days after creation** (30 days + 14-day grace), so use it only for a throwaway trial. See the host options below |
| Vercel (Hobby), Render (free, **no other free services in that workspace**), cron-job.org | Deploy (research R12, R15) |

### 🗄️ PostgreSQL host options (operator decision)

| Option | Cost | Limits to know |
| --- | --- | --- |
| **Render Basic** | ~US$6–7 / month | Same region as the API; a paid service needs a written decision in the plan (constitution VI) |
| **Supabase Free** | Free | 500 MB; pauses after 7 days without activity (the every-minute tick keeps it active) |
| **Neon Free** | Free | 0.5 GB; 100 compute-hours / month, and the every-minute tick keeps compute awake, so it may run out |
| **Render Free** | Free | Expires after 30 days; trial only, never real data |

> ⚠️ Use the host's **direct** (or session-mode) connection URL, not a **transaction pooler** (e.g. Supabase port `6543`, Neon `-pooler` hosts). Transaction poolers can drop the per-session UTC setting and break the prepared statements the backend uses.

---

## 2. 🖥️ Run locally

All commands are for **Windows PowerShell 5.1** (no `&&`; one command per line), run from the repo root `C:\Blood`.

### 2.1 🐘 Local PostgreSQL (recommended: same database as production)

Pick **one** way to run PostgreSQL 17 on `127.0.0.1:5432`. Both use the same **local-only** credentials (user `blood`, password `blood_local_only`, databases `blood` and `blood_test`). Never reuse them for a deployed database.

| Way | Needs | Verified |
| --- | --- | --- |
| **A. Portable binaries** (no admin, no service) | ~370 MB under your user folder | ✅ 2026-10-19 (PostgreSQL 17.11) |
| **B. Docker** (`docker-compose.yml`) | Docker Desktop + WSL 2 | ⏳ Not yet (Docker not installed) |

**A. Portable binaries: one-time setup.** Download `postgresql-17.11-5-windows-x64-binaries.zip` (381,531,864 bytes) from EnterpriseDB's binaries page (`https://www.enterprisedb.com/download-postgresql-binaries`) and extract it to `C:\Users\<you>\Tools\pgsql-17`. ⚠️ The programs in this zip are **not code-signed**; trust rests on the official HTTPS source and the exact size.

```bash
Set-Content -Path $env:USERPROFILE\Tools\pgpw.txt -Value blood_local_only -NoNewline -Encoding ascii
```

```bash
& $env:USERPROFILE\Tools\pgsql-17\pgsql\bin\initdb.exe -D $env:USERPROFILE\Tools\pgsql-17-data -U blood --pwfile=$env:USERPROFILE\Tools\pgpw.txt --auth=scram-sha-256 -E UTF8 --no-locale
```

```bash
Remove-Item $env:USERPROFILE\Tools\pgpw.txt
```

**A. Start PostgreSQL** (after every reboot), listening on localhost only:

```bash
& $env:USERPROFILE\Tools\pgsql-17\pgsql\bin\pg_ctl.exe -D $env:USERPROFILE\Tools\pgsql-17-data -l $env:USERPROFILE\Tools\pgsql-17.log -o "-c listen_addresses=127.0.0.1 -p 5432" -w start
```

**A. Create the two databases** (one time, after the first start):

```bash
$env:PGPASSWORD = 'blood_local_only'
```

```bash
& $env:USERPROFILE\Tools\pgsql-17\pgsql\bin\createdb.exe -h 127.0.0.1 -U blood blood
```

```bash
& $env:USERPROFILE\Tools\pgsql-17\pgsql\bin\createdb.exe -h 127.0.0.1 -U blood blood_test
```

**A. Stop PostgreSQL:**

```bash
& $env:USERPROFILE\Tools\pgsql-17\pgsql\bin\pg_ctl.exe -D $env:USERPROFILE\Tools\pgsql-17-data stop
```

**B. Docker** (creates both databases automatically on first start):

```bash
docker compose up -d db
```

### 2.2 🔌 Enable PHP's PostgreSQL driver (one time)

`php artisan serve` starts a separate PHP process, so the driver must be enabled in `php.ini`, not just on the command line. Find the file:

```bash
php --ini
```

In the "Loaded Configuration File", remove the leading `;` from `;extension=pdo_pgsql` (and `;extension=pgsql`), save, then check:

```bash
php -m
```

The list must include `pdo_pgsql`. (Without editing `php.ini`, one-off commands also work with `php -d extension=pdo_pgsql artisan …`.)

### 2.3 🧩 Backend

```bash
cd C:\Blood\backend
```

```bash
composer install
```

```bash
Copy-Item .env.example .env
```

```bash
php artisan key:generate
```

In `backend/.env` set:

```dotenv
DB_CONNECTION=pgsql
DB_URL=postgresql://blood:blood_local_only@127.0.0.1:5432/blood
SCHEDULER_SECRET=<any long random value>
OPERATOR_HEALTH_SECRET=<any long random value>
FRONTEND_URL=http://localhost:3000
```

```bash
php artisan migrate
```

```bash
php artisan serve --port=8000
```

**Check**: http://localhost:8000/api/v1/health returns `{"status":"running",…}`. Before `migrate` it returns `limited`.

> 🪶 **No PostgreSQL?** Keep `DB_CONNECTION=sqlite` (and no `DB_URL`): `php artisan migrate` offers to create `database/database.sqlite` (git-ignored). Everything works the same, but it isn't the production database engine.

### 2.4 🎨 Frontend (second terminal)

```bash
cd C:\Blood\frontend
```

```bash
npm ci
```

```bash
Set-Content -Path .env.local -Value "NEXT_PUBLIC_API_BASE_URL=http://localhost:8000"
```

```bash
npm run dev
```

**Expected**: http://localhost:3000 shows an Arabic, right-to-left page with "الخدمة تعمل" and the disclaimer footer, fetched live from the backend.

---

## 3. 🧪 Run the checks (same as CI)

| Area | Command |
| --- | --- |
| Backend style | `cd backend && ./vendor/bin/pint --test` |
| Backend static analysis | `cd backend && composer analyse` (Larastan level 8, 1 GB memory limit) |
| Backend tests (unit + feature + contract, SQLite in memory) | `cd backend && ./vendor/bin/pest` |
| Frontend lint / format / types | `cd frontend && npm run lint && npm run format:check && npm run typecheck` |
| Frontend unit tests | `cd frontend && npm test` |
| Frontend build | `cd frontend && npm run build` |
| E2E + accessibility | `cd frontend && npx playwright test` (starts the backend on a fresh, migrated SQLite file `backend/database/e2e.sqlite`; `php` must be on PATH). First time only: `npx playwright install chromium` |
| Backend tests on local PostgreSQL | In `backend`: `$env:DB_CONNECTION = 'pgsql'`, then `$env:DB_URL = 'postgresql://blood:blood_local_only@127.0.0.1:5432/blood_test'`, then `./vendor/bin/pest`; afterwards `Remove-Item Env:DB_CONNECTION, Env:DB_URL`. Tests **refuse** any PostgreSQL database whose name doesn't end in `_test` (`tests/TestCase.php`), because they wipe it |
| **CI on GitHub** (`.github/workflows/ci.yml`) | Runs all of the above on every pull request and push to `main`, plus the backend suite on a disposable **PostgreSQL 17** container and a **Docker image** build-and-start check (`docker-image`). Start it by hand: GitHub → **Actions** → **ci** → **Run workflow** (that run also scans the full git history for secrets). **Never** point tests at the production database |

---

## 4. 🗂️ Prepare the database

| Where | How |
| --- | --- |
| **Local** | `cd backend && php artisan migrate` |
| **Render** | Nothing to run: the Docker image runs `php artisan migrate --force` at every container start (`AUTORUN_LARAVEL_MIGRATION=true`) |
| **Check** | `cd backend && php artisan migrate:status` lists `create_settings_table`, `create_scheduler_steps_table`, `create_operational_logs_table` as **Ran** |

**Expected**: a second `php artisan migrate` prints `Nothing to migrate`. Until the migrations have run, `/api/v1/health` reports `limited` and `/api/v1/health/details` lists the missing tables.

---

## 5. ☁️ Deploy

| Step | Where | Settings |
| --- | --- | --- |
| 0 | **PostgreSQL host** (see §1) | Create the database and copy its connection URL, e.g. `postgresql://user:password@host:5432/dbname?sslmode=require`. Keep it secret |
| 1 | **Render** → New → Blueprint → this repo (`render.yaml`) | Enter the 3 prompted values: `APP_KEY` (output of `php artisan key:generate --show`), `DB_URL` (from step 0), and `FRONTEND_URL` (the Vercel URL from step 2; it can be filled in after step 2). `SCHEDULER_SECRET` and `OPERATOR_HEALTH_SECRET` are **generated by Render**: copy them from the dashboard for steps 3 and V7. ⚠️ The Docker image is first built here (not built locally); check the deploy log for the three migrations |
| 2 | **Vercel** → Import repo, root directory `frontend` | `NEXT_PUBLIC_API_BASE_URL=https://<render-host>` (no trailing slash). Then set Render's `FRONTEND_URL` to the Vercel URL |
| 3 | **cron-job.org** → New job | URL `https://<render-host>/api/v1/internal/tick`, method **POST**, every **1 min**, header `X-Scheduler-Secret: <secret>`, timeout 30 s, **email on failure** + on recovery |
| 4 | **GitHub** → branch protection on `main` | First create `main` (e.g. open a pull request `feat/s0-foundation` → `main`, or create `main` from the branch) and let `ci` run once, so the check names exist. Then Settings → Branches → add a rule (or ruleset) for `main`: require a pull request and the status checks `backend`, `frontend`, `e2e`, `secrets-scan` (optionally `docker-image`) |

---

## 6. ✅ Validation scenarios

| # | Proves | Do this | Expected |
| --- | --- | --- | --- |
| V1 | US1, FR-001, FR-005 | Open the Vercel URL on a phone (or a 360 px viewport) | Arabic RTL page, "الخدمة تعمل", disclaimer in footer, no horizontal scroll |
| V2 | US1, FR-006 | `curl -sI https://<vercel-host>/` and view the page source | `X-Robots-Tag: noindex` header and robots meta `noindex,nofollow` |
| V3 | US1 · limited | On a **trial** backend, set `DB_URL` to a wrong host, wait 60 s, reload | "الخدمة تعمل بشكل محدود مؤقتًا"; no technical detail visible |
| V4 | US1 · unavailable | Point `NEXT_PUBLIC_API_BASE_URL` at a dead host in a preview deploy | After ≤ 8 s: "الخدمة غير متاحة مؤقتًا" + Retry button |
| V5 | US2, FR-008 | `curl -X POST https://<render-host>/api/v1/internal/tick` with no header, 12 times | `401` ×10, then `429`; one aggregated `scheduler.refused` row in `operational_logs` |
| V6 | US2, FR-010, SC-003 | Send two correct tick calls at the same moment | One `success`, the other `busy` or `skipped`; the `heartbeat` row in `scheduler_steps` updated once for that minute |
| V7 | US2, FR-013 | Open `https://<render-host>/api/v1/health/details` in a phone browser | Login prompt; with `operator` + secret → JSON with `scheduler.lastSuccessAt` < 2 min ago |
| V8 | US2, SC-002 | Leave cron running 7 days; check cron-job.org history; at random hours run `curl -s -o /dev/null -w "%{time_total}" https://<render-host>/api/v1/health` | ≥ 99% successful runs; `/api/v1/health` ≤ 3 s every time |
| V9 | US3, SC-004 | Run the contract suite on SQLite and on a disposable PostgreSQL database | Both 100% green |
| V10 | US4, FR-024/025 | `settings:list`; then `settings:set WaveSize 6 --by=owner`; then, on a **local or trial** database only, store `abc` for `WaveSize` with SQL and run `settings:list` | Defaults shown; then `6` (source override) + `setting.changed` log; then default `5` with source `invalid→default` + `setting.invalid` log |
| V11 | US5, FR-028 | Open a PR that breaks one Pest test | Required check fails; merge blocked |
| V12 | FR-030, SC-008 | Look at the `secrets-scan` CI job | Zero findings |
| V13 | SC-010 | Look at the e2e job's axe report | Zero WCAG 2.1 AA violations |
| V14 | FR-031/033 | On a trial backend, remove `SCHEDULER_SECRET` (or `DB_URL`); locally, roll back the last migration | `/health` → `limited`; `/health/details` lists the missing variable or table; tick → `503` |

> ⚠️ V3, V10's SQL edit, and V14 are **test procedures for local or trial databases only**. Rule AD-09 (no manual edits) applies to production.

---

## 7. 🧾 Launch checklist for this feature

- [ ] Render workspace has **no other free services** (750 h budget)
- [ ] Free-tier terms re-checked for Vercel, Render, cron-job.org, and the PostgreSQL host (research R15)
- [ ] PostgreSQL host chosen and recorded in the plan; **not** Render's expiring free database for real data
- [ ] Database reachable only with credentials (`sslmode=require`); `DB_URL` stored only in Render
- [ ] Render deploy log shows the three migrations; `/api/v1/health` reports `running`
- [ ] `.env` files absent from git; `.env.example` present with empty values
- [ ] cron-job.org failure emails reach the owner
- [ ] Before any personal data: scheduled `pg_dump` backup in place and a restore tested (DS-08)
