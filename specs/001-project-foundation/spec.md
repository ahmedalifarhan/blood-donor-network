# Feature Specification: Project Foundation

**Feature Branch**: `001-project-foundation`

**Created**: 2026-10-19

**Status**: Draft

**Input**: User description: "Project foundation for "Blood Donor Network": an always-reachable web app and API skeleton with no business features yet. The public site opens in Arabic, right-to-left, mobile-first, and shows a service status read live from the backend and its storage. Every page carries a footer with the medical disclaimer (the platform is a contact network, not a medical service or blood bank). Pages are hidden from search engines unless a later feature marks them public. A secret-protected scheduler endpoint is called every minute by an external free cron; it runs registered time-based steps (none yet), each safe to run twice, and its regular calls keep the free backend host from sleeping. Storage is reached only through repositories, so the store can later change from Google Sheets to a SQL database without touching features; every storage implementation must pass the same shared contract test suite. Application settings (wave size, wave intervals, thresholds, consent version) are stored as editable key/value settings with defaults. The repository is a monorepo with a frontend app and a backend API, automated quality checks on every change, and a deployed environment (frontend on Vercel free tier, backend on Render free tier) reachable over HTTPS."

> 🧱 **What this feature is:** the base every later feature builds on. It contains **no donor, request, or admin features**. Its value is proof that the platform is **reachable, awake, safe to extend, and free to run**.

| 👤 Actor | Role in this feature |
| --- | --- |
| **Visitor** | Opens the public site on a phone and sees whether the service is up |
| **Operator** | The project owner. Deploys, configures secrets, changes settings, reads health |
| **Scheduler** | External free timer that calls the platform every minute |
| **Contributor** | Anyone (human or AI) adding a later feature; relies on storage contracts and automated checks |

## Clarifications

### Session 2026-10-19

- Q: Where should operational log entries be kept? → A: Split by importance. Failures, warnings, refused calls, and setting changes go to the data store; routine successful runs go only to host logs.
- Q: How fresh must the visitor-facing service status be? → A: Backend reachability is checked live on every load; the store-reachability result is reused for up to 60 seconds.
- Q: How should the store's structure (tabs and header rows) be created in a new environment? → A: With an idempotent operator setup command that creates anything missing, reports what changed, and never deletes or reorders existing columns. _(Superseded on 2026-10-19: see the change below.)_
- Q: How should the operator see detailed health? → A: Through a detailed health view protected by its own operator secret (separate from the scheduler secret). Callers without it get only the three-state public summary.
- Q: What accessibility level should the public pages meet? → A: WCAG 2.1 AA, checked automatically on every change plus a manual pass.

### Change 2026-10-19: database store

- 🐘 **Owner decision:** the data store is a **PostgreSQL database** instead of Google Sheets. Local development and automated tests use a lightweight local database. The owner wants a proper relational store with transactions and constraints.
- 🗂️ **Structure:** tables are created and changed by versioned **schema migrations** that run automatically on every deploy. This replaces the operator setup command (clarification Q3 above).
- 🔁 **Requirements adjusted** (numbering unchanged): FR-015, FR-018, FR-019, FR-020, FR-022, FR-033, User Story 3, and the related edge cases and assumptions now describe database behavior. Spreadsheet-only mechanics (formula neutralizing, write lock, read cache, rate-limit retries, sandbox spreadsheet, tabs and header rows) no longer apply.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Visitor sees a live, Arabic service status (Priority: P1)

A visitor opens the site on a mobile phone. The page appears in Arabic, laid out right-to-left, and tells them whether the service is working. The answer comes from a live check of the backend and its data store, not from a fixed message. Every page ends with the medical disclaimer.

**Why this priority**: it is the thinnest end-to-end slice. It proves the public site, the backend, and the data store are deployed, connected, and reachable over a secure connection. Every later feature depends on this path.

**Independent Test**: open the deployed site on a phone-sized screen and check the language, direction, status message, and disclaimer. Then make the store unreachable in a test environment and confirm the status changes.

**Acceptance Scenarios**:

1. **Given** the backend and store are healthy, **When** a visitor opens the home page, **Then** they see an Arabic, right-to-left page stating the service is running, with the medical disclaimer in the footer.
2. **Given** the data store is unreachable, **When** a visitor opens the home page, **Then** they see a calm Arabic message that the service is temporarily limited, with no technical details, and the disclaimer is still shown.
3. **Given** the backend cannot be reached at all, **When** a visitor opens the home page, **Then** the page still loads and states that the service is temporarily unavailable.
4. **Given** any page of the site, **When** a search engine requests it, **Then** the page tells search engines not to index it.

---

### User Story 2 - Scheduler keeps the platform awake and runs timed work safely (Priority: P2)

Every minute, an external timer calls a protected scheduler address. Each call runs every registered timed step and keeps the free backend host from going to sleep. Calls without the correct secret are refused. Repeated or overlapping calls never repeat a step's effects.

**Why this priority**: later features rely on timed work: notification waves, request expiry, and availability return. The free host sleeps when idle, which is unacceptable during an emergency. Neither works without this.

**Independent Test**: register a test step that records each effect, call the scheduler twice in a row and twice at the same moment, and confirm one effect per due period. Call it with a wrong secret and confirm nothing runs.

**Acceptance Scenarios**:

1. **Given** a call with the correct secret, **When** the scheduler runs, **Then** every registered step that is due runs and the time of the run is recorded.
2. **Given** a call with a missing or wrong secret, **When** it reaches the scheduler, **Then** it is refused, no step runs, and the refused attempt is recorded.
3. **Given** two calls arrive at the same time, **When** both run, **Then** each due step runs at most once for that period.
4. **Given** one step fails, **When** the scheduler runs, **Then** the other steps still run and the failure is recorded with the step name and time.
5. **Given** the timer has called every minute for a day, **When** a visitor opens the site at any hour, **Then** the backend's status check answers within 3 seconds, without a long wake-up delay.

---

### User Story 3 - Contributors can swap or extend storage safely (Priority: P3)

A contributor adding a feature reads and writes data only through defined storage contracts. The data lives in a relational database: a server database when deployed and a lightweight local database for development and tests. Another database must be able to replace either one with a configuration change, without touching features. One shared contract test suite proves every database behaves the same.

**Why this priority**: this keeps features independent of the database and lets tests run without a server. It is not visible to visitors, but skipping it makes every later feature more expensive to move.

**Independent Test**: run the shared contract suite against the local test database and against a disposable copy of the deployed database type, switching only configuration. Both must pass with identical results.

**Acceptance Scenarios**:

1. **Given** the shared contract suite, **When** it runs against each supported database, **Then** every test passes on each one.
2. **Given** a value that looks like a number or a formula (for example `201012345678` or `=SUM(A1)`), **When** it is saved and read back, **Then** it comes back exactly as entered and is never executed or reformatted.
3. **Given** two writes arrive at the same moment, **When** both complete, **Then** neither write is lost and the records are not corrupted.
4. **Given** the store cannot be reached, **When** a read or write is attempted, **Then** a clear "store unavailable" failure is reported quickly, without hanging, and nothing partial is saved.

---

### User Story 4 - Operator tunes behavior through settings, not code (Priority: P4)

The operator needs to change numbers such as wave size, wave intervals, thresholds, and the consent text version without a code change. Every setting has a safe default, so a fresh deployment works before anything is configured.

**Why this priority**: later features read these values. Defaults are needed from day one; editing them through an admin screen comes with the admin features.

**Independent Test**: start with an empty settings store and confirm every setting returns its default. Change one value through the operator path, confirm the new value is used, then save an invalid value and confirm the default is used and a warning is recorded.

**Acceptance Scenarios**:

1. **Given** no stored value for a setting, **When** it is read, **Then** its documented default is returned.
2. **Given** the operator changes a setting, **When** it is next read, **Then** the new value is used, and who changed it and when are recorded.
3. **Given** a stored value is invalid (wrong type or out of range), **When** it is read, **Then** the default is used and a warning is recorded.

---

### User Story 5 - Every change is checked automatically (Priority: P5)

When a contributor proposes a change to either app, automated checks run: formatting, static analysis, tests, and a build. A change with any failing check cannot be merged.

**Why this priority**: the project is built largely by AI agents, step by step. Automated gates catch regressions and constitution violations before they reach the pilot.

**Independent Test**: propose a change that breaks one test and confirm merging is blocked. Fix it and confirm merging is allowed.

**Acceptance Scenarios**:

1. **Given** a proposed change, **When** it is submitted, **Then** formatting, static analysis, tests, and builds run for both apps.
2. **Given** any check fails, **When** someone tries to merge, **Then** the merge is blocked until the checks pass.

---

### Edge Cases

- **Store unreachable, paused, or full:** the status page shows the "limited" state, the scheduler records the failure, and nothing crashes.
- **Scheduler silent for several minutes** (timer outage or host asleep): the next call catches up on due steps once, without running each missed minute separately.
- **Scheduler call takes longer than a minute:** the next call does not start a second copy of a step that is still running.
- **Wrong or missing secret repeated many times:** refused every time, and excessive attempts are rate-limited.
- **Mixed text direction:** Latin text and digits (phone numbers, codes) inside Arabic sentences render in the correct order.
- **Very slow mobile connection:** the page shows readable content before any secondary resources finish loading.
- **Structure update run twice, or on a store that already has data:** nothing is duplicated or lost, and the second run reports that there is nothing to do.
- **Deployed before the structure exists:** health reports "not configured" until the structure is in place (FR-033).
- **Secrets missing in an environment:** the backend refuses to start or reports "not configured" in health, rather than running half-configured.
- **Test runs:** the contract suite runs only against disposable test databases, never a deployed one.
- **Traffic burst** (a shared link opened by hundreds of people at once): the store is still checked at most about once per minute (FR-003), and the store becomes unreachable at most 60 seconds before the status shows "limited".

## Requirements *(mandatory)*

### Functional Requirements

**🌐 Public site**

- **FR-001**: The public site MUST open in Arabic with right-to-left layout by default and MUST be fully usable on a 360-pixel-wide screen.
- **FR-001a**: Public pages MUST meet **WCAG 2.1 AA**:
  - Text contrast of at least 4.5:1
  - Text resizable to 200% without loss of content
  - Screen-reader labels on every control and status message
  - Visible keyboard focus
  - Touch targets at least 44×44 px

  Automated accessibility checks MUST run on every change (FR-028), plus a manual screen-reader pass before release.
- **FR-002**: All user-facing text MUST come from translation resources, so another language can be added without changing feature logic.
- **FR-003**: The home page MUST show a service status based on a live check of the backend and the data store, with three states: **running**, **limited** (backend up, store unreachable), and **unavailable** (backend unreachable). Backend reachability MUST be checked on every page load. The store-reachability result MAY be reused for up to **60 seconds**, so visitor traffic causes at most about one store check per minute.
- **FR-004**: The status shown to visitors MUST NOT reveal internal details such as host names, error messages, identifiers, or configuration.
- **FR-005**: Every page MUST show the medical disclaimer in its footer, stating that the platform is a contact network and not a medical service or blood bank.
- **FR-006**: Every page MUST tell search engines not to index it unless a later feature explicitly marks it public. No page is public in this feature.
- **FR-007**: The public site MUST NOT access the data store directly. All data MUST pass through the backend.

**⏰ Scheduler**

- **FR-008**: The backend MUST expose a scheduler entry point that runs only when the call carries the correct shared secret. Other calls MUST be refused, recorded, and rate-limited.
- **FR-009**: Each scheduler call MUST run every registered step that is due. This feature registers one **heartbeat** step, which records the time of the latest successful run.
- **FR-010**: Each step MUST be safe to run twice. Repeated, late, or overlapping calls MUST NOT repeat a step's effects for the same period, and at most one copy of a step may run at a time.
- **FR-011**: A failing step MUST NOT prevent other steps from running. Each failure MUST be recorded with the step name, time, and reason.
- **FR-012**: An external free timer MUST call the scheduler every minute in the deployed environment. These calls are also what keeps the free backend host awake.
- **FR-013**: The operator MUST be able to see detailed health: when the scheduler last ran successfully, the latest outcome of each step, store reachability, and configuration problems. This view MUST require an **operator secret** that is separate from the scheduler secret. Callers without it MUST receive only the three-state public summary (FR-003, FR-004). The view MUST work from a phone browser and from a free uptime monitor.

**💾 Storage**

- **FR-014**: All data reads and writes MUST go through defined storage contracts. Feature logic MUST NOT call a specific store directly.
- **FR-015**: The active database MUST be chosen by configuration. Two MUST be supported: the **deployed database** (a server database) and a **lightweight local database** (local development and automated tests).
- **FR-016**: A single shared contract test suite MUST run against every supported database, and each MUST pass it.
- **FR-017**: Records MUST receive random, time-ordered identifiers. Sequential numbers MUST NOT be used.
- **FR-018**: Values MUST be stored and read back exactly as given. Long digit strings stay text, and values beginning with `=`, `+`, `-`, or `@` come back unchanged. Values MUST always be passed to the store as data, never as part of a command, so they can never run as code.
- **FR-019**: Concurrent writes MUST never be lost or corrupt a record. Uniqueness (for example one row per setting key) MUST be enforced by the store itself, and each insert-or-update MUST be a single atomic operation.
- **FR-020**: When the store fails or cannot be reached, the failure MUST be reported quickly as a clear "store unavailable" error, without retries that could exceed the scheduler's time limit. The next scheduler call retries naturally.
- **FR-021**: All stored times MUST be in UTC.
- **FR-022**: Each deployed environment MUST have its own database. Automated tests MUST only ever use **disposable test databases** created for the run, never a deployed database.
- **FR-033**: The store's structure (a table with columns for every entity) MUST be created and changed only by versioned **schema migrations** that run automatically on every deploy. Running them again MUST change nothing. Later features add new migrations and never edit existing ones. If the backend finds the structure incomplete, its health check MUST report "not configured" instead of failing at random.

**⚙️ Settings**

- **FR-023**: Settings MUST be stored as key/value pairs, each with a documented default, type, and allowed range.
- **FR-024**: A missing setting MUST return its default. An invalid stored value MUST return the default and record a warning.
- **FR-025**: The operator MUST be able to change a setting without a code change. Each change MUST record who made it, when, and the old and new values. An admin screen for settings is out of scope here.
- **FR-026**: These settings and defaults MUST exist from the start:

  | Setting | Default | Meaning |
  | --- | --- | --- |
  | **WaveSize** | 5 | Donors notified per wave |
  | **WaveIntervalMinutes.Emergency** | 5 | Minutes between waves for Emergency |
  | **WaveIntervalMinutes.Urgent** | 10 | Minutes between waves for Urgent |
  | **WaveIntervalMinutes.Normal** | 30 | Minutes between waves for Normal |
  | **AcceptTargetMultiplier** | 2 | Stop waves when accepts reach units × this |
  | **RotationDays** | 7 | Fairness window for recently notified donors |
  | **PublicCountThreshold** | 3 | Public counts below this show "fewer than 3" |
  | **QuietHours** | 23:00–07:00 | No Normal-request notifications in this window |
  | **EmergencyNoAcceptAlertMinutes** | 15 | Alert admins if an Emergency has no accept |
  | **ShortageThreshold.<type>** | 5 (each of 8 types) | Usable-donor count that triggers a shortage warning |
  | **AdminWhatsAppNumber** | empty | Admin contact for fallbacks |
  | **ConsentVersion** | 1 | Current consent text version |

**🚀 Repository, checks, and deployment**

- **FR-027**: The repository MUST hold the public web app, the backend, and project documentation in one place.
- **FR-028**: Every proposed change MUST run formatting, static analysis, tests (including the storage contract suite on the local test database), and builds for both apps. A change with a failing check MUST NOT be mergeable.
- **FR-029**: The public site and the backend MUST be deployed and reachable over HTTPS, and the public site MUST be the only origin allowed to call the backend from a browser.
- **FR-030**: Secrets (store credentials, scheduler secret, operator health secret, bot tokens) MUST live only in each environment's configuration, never in the repository. An example configuration with empty values MUST be committed.
- **FR-031**: The backend MUST refuse to start, or report "not configured" in its health check, when a required secret is missing.

**📝 Operational logging**

- **FR-032**: Step failures, invalid-setting warnings, refused scheduler calls, and setting changes MUST be written durably to the data store as operational log entries. Routine successful scheduler runs MUST be written only to the host's logs; in the store they update only the step's latest-run state (FR-009, FR-013).

### Key Entities *(include if feature involves data)*

- **Setting**: one tunable value. Key, current value, default, type, allowed range, last changed by, last changed at.
- **Scheduler Step State**: one registered timed step. Step name, last period completed, last run time, last outcome (success / failure), last failure reason. Used to guarantee each step runs once per period.
- **Service Status**: derived, not stored. Backend reachable, store reachable, last successful scheduler run. Visitors see only the three-state summary (FR-003); the operator sees the detail through the secret-protected health view (FR-013).
- **Operational Log Entry**: a durably stored event: a refused scheduler call, step failure, setting change, or invalid-setting warning. Records who or what acted, what happened, and when. Routine successes are not stored here (FR-032). Phone numbers never appear unmasked (constitution II).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a mid-range phone over a slow mobile connection (**150 ms latency, 1.6 Mbps download**, phone CPU about 4× slower than a laptop), a visitor sees the status page readable within **5 seconds**.
- **SC-002**: Over **7 consecutive days**, at least **99%** of the every-minute scheduler calls succeed, and the backend's public status check answers within **3 seconds** at any hour (no cold-start delay).
- **SC-003**: Running the scheduler twice in a row, or twice at the same moment, leaves **exactly the same** results as running it once, in **100%** of test runs.
- **SC-004**: The shared contract suite passes **100%** on every supported database, and switching databases requires **zero** changes outside the storage layer.
- **SC-005**: **100%** of pages show the medical disclaimer and are excluded from search indexing.
- **SC-006**: **100%** of changes with a failing check are blocked from merging. ⏭️ *Not enforced for now: branch protection (T066) was skipped by owner decision on 2026-10-19 (solo-maintained project); see `plan.md` → Complexity Tracking. The checks still run and report failures (FR-028), but they do not block merges.*
- **SC-007**: Monthly running cost for hosting, storage, and scheduling is **$0**.
- **SC-008**: An automated secret scan of the repository finds **zero** credentials.
- **SC-009**: A new contributor can run both apps locally against the local database by following the README in **under 30 minutes**.
- **SC-010**: Automated accessibility checks report **zero** WCAG 2.1 AA violations on every public page.

## Assumptions

- 📜 **Governing rules:** the [constitution](../../.specify/memory/constitution.md) and [docs/BUSINESS_RULES.md](../../docs/BUSINESS_RULES.md) apply. In particular, Principles IV–VII and rules **DS-01…DS-10** and **SC-01…SC-05**.
- 💾 **Store:** the deployed database is **PostgreSQL**, reached through one secret connection URL; the local database is SQLite (owner decision, 2026-10-19; constitution V). The PostgreSQL host is still to be chosen: free options have expiry, pause, or usage limits, and a paid host would affect SC-007.
- ☁️ **Hosting:** the public site is on Vercel's free tier and the backend on Render's free tier (decision D11). The every-minute timer uses a free external cron service. Free-tier terms are re-checked before launch.
- 🌍 **Language:** Arabic is the only language shipped in this feature. English arrives in a later feature through the same translation resources.
- 🔐 **Operator path for settings:** the operator changes settings through a protected command until the admin dashboard exists (admin feature).
- 📣 **Alerts:** step failures and refused calls are recorded in this feature. Sending them to the admin Telegram group comes with the notification features.
- 🧪 **Environments:** each deployed environment has its own database; tests use only disposable databases. Until pilot launch the deployed database holds no personal data.
- 🚫 **Out of scope:** donors, requests, matching, notifications, admin login, locations, and every other business feature.
