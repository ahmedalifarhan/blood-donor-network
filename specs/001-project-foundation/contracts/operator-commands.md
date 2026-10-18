# 🧑‍🔧 Operator Commands

**Feature**: 001-project-foundation · **Why not on Render**: free instances have no shell or one-off jobs (research R8)

All commands are Laravel Artisan commands in `backend/`. They talk to the configured store directly, so they run **on the operator's machine** or through **GitHub Actions `workflow_dispatch`**.

---

## 📋 Commands

| Command | Purpose | Writes? | Exit codes |
| --- | --- | --- | --- |
| `php artisan store:setup [--dry-run] [--force] [--json]` | Create missing tabs and headers (FR-033). Checks only the **store** variables (`GOOGLE_SHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON`), since it runs without the web secrets | ✅ unless `--dry-run` | `0` done or nothing to do · `1` store unreachable · `2` config missing · `3` aborted at the confirmation prompt |
| `php artisan settings:list` | Print every setting: key, effective value, default, source (`default` / `override` / `invalid→default`) | ❌ | `0` · `1` store unreachable |
| `php artisan settings:set <key> <value> --by=<name>` | Validate and store an override; logs `setting.changed` with old and new values (FR-025) | ✅ | `0` saved · `3` unknown key · `4` invalid value · `1` store unreachable |
| `php artisan settings:reset <key> --by=<name>` | Remove the override by storing the default explicitly and logging the change | ✅ | as above |

### Output format

- Human-readable table by default; `--json` for machine use.
- `store:setup` lists each change: `+ tab OperationalLogs`, `+ header SchedulerSteps.ConsecutiveFailures`, or `✔ no changes`.
- Secrets and full phone numbers are **never** printed.

### Guards

- `--by` is required for every write, so `UpdatedBy` and the log actor are never empty.
- Writing to a spreadsheet whose title doesn't end with `[sandbox]` asks for confirmation (or needs `--force` in CI). The production workflow requires a GitHub Environment reviewer instead.

---

## ⚙️ GitHub Actions wrappers

| Workflow | Trigger | Inputs | Environment |
| --- | --- | --- | --- |
| `ops-store-setup.yml` | `workflow_dispatch` | `environment` (`sandbox` / `production`), `dry_run` (bool) | GitHub Environment of the same name |
| `ops-settings-set.yml` | `workflow_dispatch` | `environment`, `key`, `value`, `by` | same |

- Secrets come from the chosen **GitHub Environment**: `GOOGLE_SERVICE_ACCOUNT_JSON`, `GOOGLE_SHEET_ID`.
- The `production` environment has **required reviewers**, so a second person approves production changes once co-admins exist.
- Run logs show who triggered what and when, a free secondary audit trail.

> ⏱️ **Effect timing**: the running backend reads settings through a 60 s cache, so changes apply within **1 minute**.
