# 🧑‍🔧 Operator Commands

**Feature**: 001-project-foundation · **Why not on Render**: free instances have no shell or one-off jobs (research R8)

All commands are Laravel Artisan commands in `backend/`. They talk to the **database** directly through `DB_URL`, so they run **on the operator's machine** or through **GitHub Actions `workflow_dispatch`**.

> 🗂️ **Schema is not an operator command.** Tables and columns change only through **Laravel migrations**, which run automatically at container start on every deploy (`AUTORUN_LARAVEL_MIGRATION=true`; locally `php artisan migrate`). The former `store:setup` command and its `ops-store-setup.yml` wrapper were removed on 2026-10-19 (FR-033, research R16).

---

## 📋 Commands

| Command | Purpose | Writes? | Exit codes |
| --- | --- | --- | --- |
| `php artisan settings:list` | Print every setting: key, effective value, default, source (`default` / `override` / `invalid→default`) | ❌ | `0` · `1` store unreachable |
| `php artisan settings:set <key> <value> --by=<name>` | Validate and store an override; logs `setting.changed` with old and new values (FR-025) | ✅ | `0` saved · `3` unknown key · `4` invalid value · `1` store unreachable |
| `php artisan settings:reset <key> --by=<name>` | Remove the override by storing the default explicitly and logging the change | ✅ | as above |

### Output format

- Human-readable table by default; `--json` for machine use.
- Secrets (including `DB_URL`) and full phone numbers are **never** printed.

### Guards

- `--by` is required for every write, so `updated_by` and the log actor are never empty.
- The target database is whatever `DB_URL` points at. Running against **production** goes through the GitHub workflow below, whose `production` Environment requires a reviewer.

---

## ⚙️ GitHub Actions wrapper

| Workflow | Trigger | Inputs | Environment |
| --- | --- | --- | --- |
| `ops-settings-set.yml` (not built yet) | `workflow_dispatch` | `environment` (`sandbox` / `production`), `key`, `value`, `by` | GitHub Environment of the same name |

- 🔑 **Secret**: `DB_URL` comes from the chosen **GitHub Environment**, and the job sets `DB_CONNECTION=pgsql`.
- 🌐 **Network**: the database must **accept TLS connections from GitHub Actions** (`sslmode=require`). Hosted runners have no fixed IP, so an IP allow-list blocks them (research R8).
- 👥 The `production` environment has **required reviewers**, so a second person approves production changes once co-admins exist.
- 📜 Run logs show who triggered what and when, a free secondary audit trail.

> ⏱️ **Effect timing**: the running backend reads settings through a 60 s cache, so changes apply within **1 minute**.
