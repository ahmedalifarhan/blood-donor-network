<!--
SYNC IMPACT REPORT (scratch — remove before committing)
Version change: 1.0.0 → 1.0.1 (PATCH)
Bump rationale: wording only. The governance precedence list now also names feature specs,
and Principle IV's pilot example uses the settled spelling "Arab Mutair". No rule changed.

Previous change: (unratified template) → 1.0.0, first ratification on 2026-10-18.

Principles defined (template slot → title):
  PRINCIPLE_1 → I. Not a Medical Authority (NON-NEGOTIABLE)
  PRINCIPLE_2 → II. Donor Accepts First — Privacy by Design (NON-NEGOTIABLE)
  PRINCIPLE_3 → III. One Phone, One Verified Donor
  PRINCIPLE_4 → IV. Location-Agnostic, Configuration over Code
  PRINCIPLE_5 → V. Storage-Agnostic Behind Repositories
  Added beyond template: VI. Emergency Reliability on a Shoestring
  Added beyond template: VII. Accountability & Abuse Resistance

Sections: SECTION_2 → Technology & Architecture Constraints
          SECTION_3 → Development Workflow & Quality Gates
Removed sections: none

Decisions captured from the 2026-10-18 clarification round:
  - Brief supersedes README.md / docs/BUSINESS_RULES.md where they conflict
    (name "Blood Donor Network", Next.js frontend, low-cost services allowed).
  - Hosting: free tier kept awake by an external scheduler.
  - Duplicates: one donor per normalized phone (BUSINESS_RULES D1 "3 per phone" rejected).
  - Contact model: donor accepts first (README §20 #2 option c, pulled into MVP).
  - Notifications: Telegram bot in MVP, WhatsApp Cloud API next (interpreted
    "Telegram bot (free) and then whatsapp" as phased, not simultaneous).
  - Escalation: notification waves + admin alert.
  - Search: public aggregate counts only; contact only through a request.
  - Location: generic Country → Region → District → Locality hierarchy.

Dependent templates (.specify/templates/*): not modified (read constitution at runtime).

Follow-up TODOs:
  - DONE 2026-10-19: README.md and docs/BUSINESS_RULES.md rewritten to match.
  - DONE 2026-10-19: pilot spelling settled as "Arab Mutair" (Principle IV example updated).
  - Legal review against Egypt PDPL (Law 151/2020) before pilot launch (Principle II).
-->

# Blood Donor Network Constitution

## Core Principles

### I. Not a Medical Authority (NON-NEGOTIABLE)

Blood Donor Network is a matching and contact network. It is not a blood bank, a clinic, or a
medical decision tool.

- The system MUST match only the exact blood type(s) the requester enters as specified by the
  hospital. It MUST NOT contain blood-compatibility logic or suggest alternative types.
- The system MUST NOT compute donation eligibility (no "can donate after X days" rules).
  `LastDonationDate` is informational and MAY affect ranking; it MUST NOT hide or block a donor.
- A donor's self-declared blood type is a claim until an admin verifies it. Any UI that shows an
  unverified type MUST label it as "not confirmed".
- The medical disclaimer MUST appear on request creation, request details, match results, public
  share pages, and in every donor notification.
- The system MUST NOT collect patient names, diagnoses, or medical documents. Evidence used to
  verify a blood type is viewed by the admin outside the system and never stored.

Rationale: medical decisions belong to the hospital. Keeping the platform out of them limits harm
and legal exposure, and keeps the scope buildable.

### II. Donor Accepts First — Privacy by Design (NON-NEGOTIABLE)

Donor phone numbers are protected by default and revealed only through donor consent.

- No API response to a public client or requester MAY contain a donor's phone number, full name,
  or exact identity, with one exception: after a donor explicitly accepts a specific request, the
  requester of that request MAY see that donor's display name and contact actions while the
  request is open. Contact details MUST stop being returned once the request is closed.
- Donors learn about requests through a notification containing request details and Accept /
  Decline actions. Declining or ignoring MUST reveal nothing about the donor to the requester.
- Public endpoints MAY expose only aggregate counts (blood type × location). Counts below a
  configured threshold (default 3) MUST be shown as "fewer than 3" to prevent re-identification
  in small communities. No page or endpoint MAY list individual donors publicly.
- All non-public pages MUST be `noindex`.
- Personal data collection requires an explicit, unticked-by-default consent checkbox. The system
  MUST store the consent timestamp and consent text version, and donors MUST confirm they are
  18 or older. Requesters give consent for their own contact data too.
- Data minimization: national ID, gender, address, date of birth, and medical history MUST NOT be
  collected.
- Donors MUST be able to pause (temporarily unavailable), opt out (do not contact), and erase
  their data. Erasure anonymizes the record (name and phone blanked) and keeps only statistics.
- Every accept, contact reveal, and notification MUST be logged. Phone numbers in logs and audit
  values MUST be partially masked (e.g. `2010****5678`).
- Before the pilot goes live, the data handling MUST be reviewed against Egypt's Personal Data
  Protection Law (No. 151 of 2020), which treats health data (blood type) as sensitive.

Rationale: in a village, a leaked list of names, phones, and blood types is a real harm. Consent
per request is the only model that protects donors without slowing the honest requester.

### III. One Phone, One Verified Donor

- Every phone number (donor, requester, admin search) MUST pass through a single normalizer
  before storage or comparison. It converts Arabic-Indic and Persian digits, strips separators,
  applies the country's dialing rules from configuration, and rejects non-mobile numbers.
- A normalized phone number MUST identify at most one donor record, including inactive records.
  A returning donor is reactivated, never re-created. Name MUST NOT be used as a duplicate key.
- Because the MVP store has no unique constraints, the duplicate check and the insert MUST run
  inside the same application-level write lock.
- A duplicate response MUST NOT confirm that a number is registered. It directs the person to
  their manage link or the admin.
- A donor MUST have a verified phone before receiving request notifications. Verification sits
  behind a `PhoneVerifier` interface. The MVP method is Telegram contact sharing, with manual
  admin verification over WhatsApp as the fallback.
- Changing a donor's phone resets phone verification. Changing a donor's blood type resets blood
  type verification to `Pending`.

Rationale: duplicate and stale records are the core failure of today's WhatsApp lists. Phone
ownership is the only identity signal available for free.

### IV. Location-Agnostic, Configuration over Code

- Locations MUST use a generic, admin-managed hierarchy: Country → Region → District → Locality.
  Matching, filters, statistics, and admin scopes MUST reference location IDs, never names.
- No pilot-specific name (e.g. Arab Mutair, Egypt) MAY appear in code, identifiers, routes, or
  config keys. The pilot exists only as data rows and seed fixtures.
- Country-specific behavior (dial code, mobile prefixes, timezone, default language) MUST come
  from per-country configuration, not hard-coded branches.
- Timestamps MUST be stored in UTC (ISO 8601) and displayed in the location's timezone.
- The UI MUST be Arabic-first, right-to-left, and mobile-first. All user-facing strings MUST go
  through translation files so that English and other languages can be added without code edits.

Rationale: the product must move from one village to any city or country by adding data and
config, not by rewriting code.

### V. Storage-Agnostic Behind Repositories

- All persistence MUST go through repository interfaces in the Laravel backend. Controllers,
  services, jobs, and the frontend MUST NOT call the Google Sheets API (or any database) directly.
- The active store is selected by configuration (`DATA_DRIVER=sheets|mysql|pgsql`). Switching
  stores MUST NOT require changes to services, API contracts, or the frontend.
- Every repository implementation MUST pass the same repository contract test suite.
- While Google Sheets is the store:
  - IDs MUST be ULIDs. Sequential IDs are forbidden.
  - Writes MUST use `valueInputOption=RAW`, and cell values starting with `=`, `+`, `-`, or `@`
    MUST be neutralized to prevent formula injection.
  - All writes MUST be serialized through one write lock. The backend MUST run as a single
    instance.
  - Reads MUST be cached with invalidation on write, batched per sheet, and retried with
    exponential backoff on quota errors (HTTP 429).
  - The spreadsheet MUST be private, shared only with the service account and one owner. Manual
    edits to the sheet are forbidden; every change goes through the API so validation and audit
    apply.
  - A scheduled backup copy MUST be kept in a private Drive folder.
- Migration to SQL MUST be planned when any of these is true: more than 5,000 donor rows,
  repeated quota errors in normal use, or a need for more than one backend instance.

Rationale: Sheets makes the pilot free, but it has no transactions, constraints, or concurrency
control. The repository boundary contains those weaknesses and keeps the exit cheap.

### VI. Emergency Reliability on a Shoestring

- Each paid or external service MUST sit behind an interface and have a working free path for
  the pilot. Adding a paid service requires a written decision in the feature plan.
- Notifications MUST go through a `NotificationChannel` interface. The MVP channel is a Telegram
  bot. WhatsApp Cloud API is the next channel and MUST be addable without changing callers.
  Unofficial WhatsApp automation (WhatsApp Web scripting) is forbidden.
- Donors without a reachable channel MUST appear in an admin relay queue, so no matching donor is
  silently skipped.
- Requests MUST be notified in waves: the top-ranked N donors first, then the next N on a
  schedule (shorter intervals for `Emergency`). When the eligible donors are exhausted, admins
  MUST be alerted. Wave size, intervals, and rotation windows are settings, not code constants.
- A fairness rotation MUST lower donors contacted recently, so the same people are not
  notified for every request.
- Time-based behavior (waves, request expiry, availability auto-return, stale verification)
  MUST be driven by one scheduler endpoint that an external free cron calls every minute. The
  same call keeps the free-tier backend awake. Each scheduled step MUST be idempotent.
- Failed notifications and scheduler errors MUST be logged and shown to admins. They MUST NOT
  fail silently.
- Emergency flows (create request, accept) MUST work on a cold cache and slow mobile networks.

Rationale: the primary KPI is time to reach a willing donor (target under 5 minutes). A free
stack is acceptable only if it does not quietly fail at 3 a.m.

### VII. Accountability & Abuse Resistance

- Every input MUST be validated on the server (Laravel Form Requests). Frontend validation is
  for usability only.
- Rate limits MUST apply to registration, verification, request creation, accept actions, and
  admin login. Limits live in configuration.
- Requests and donor self-service use secret manage links. Tokens MUST be random, stored only as
  hashes, revocable, and re-issuable by an admin.
- Admin authentication uses Laravel Sanctum, with lockout after repeated failures. Admin roles
  MUST support scoping to a location subtree (`SuperAdmin`, `RegionAdmin`).
- Nothing is hard-deleted. Records are deactivated or anonymized (Principle II).
- An append-only audit log MUST record every admin action, login attempt, manage-link use,
  request status change, accept, and contact reveal, with actor, entity, masked old/new values,
  IP, and time.
- Secrets MUST live only in environment variables. `.env.example` is committed with empty values.

Rationale: the main abuse risk is fake requests used to harvest donors. Limits plus a complete
audit trail make abuse expensive and detectable without slowing real emergencies.

## Technology & Architecture Constraints

| Layer | Required choice |
| --- | --- |
| Frontend | Next.js (App Router), TypeScript `strict`, Tailwind CSS, shadcn/ui (Radix), RTL-first |
| Backend | Laravel (PHP 8.3+), versioned REST JSON API under `/api/v1` |
| MVP store | Google Sheets API via service account, behind repositories |
| Future store | MySQL or PostgreSQL via Eloquent repositories |
| Notifications | Telegram Bot API (MVP) → WhatsApp Cloud API (next) |
| Hosting | Vercel free tier (frontend); free-tier Laravel host kept awake by external cron |
| Repository | Monorepo: `frontend/`, `backend/`, `docs/` |

Architectural invariants:

- Laravel is the only gatekeeper for data and business rules. The Next.js app is a client of the
  Laravel API. Next.js route handlers and server actions MUST NOT access storage or contain
  business rules; they MAY render pages and forward requests.
- API responses MUST be built with Laravel API Resources that whitelist fields. Sensitive fields
  are excluded by default and included only where Principle II allows.
- Breaking API changes require a new API version.
- Business logic lives in services (normalizer, matching, wave scheduler, verification).
  Controllers stay thin.

## Development Workflow & Quality Gates

- Spec-driven: every feature follows `/speckit-specify` → `/speckit-plan` → `/speckit-tasks` →
  `/speckit-implement` → `/speckit-converge`. No application code is written without an approved
  spec.
- Work is delivered in vertical slices. Each slice ships API, UI, and tests together and is
  demoable on its own.
- Every `plan.md` MUST pass a Constitution Check against Principles I–VII. Any deviation MUST be
  recorded with its justification in the plan's Complexity Tracking table.
- Test-first is mandatory for the phone normalizer, duplicate prevention, matching and ranking,
  wave scheduling, and every privacy rule. Each public endpoint MUST have a feature test asserting
  that no donor phone number leaks outside the accepted-request case.
- Backend: Pest/PHPUnit feature and unit tests, Laravel Pint (PSR-12), Larastan level 6 or higher.
- Frontend: ESLint, Prettier, `tsc --noEmit` with no errors. Playwright end-to-end tests cover
  donor registration, request creation, and donor acceptance.
- Repository contract tests run against an in-memory fake on every CI run and against a sandbox
  spreadsheet before each release.
- CI (GitHub Actions free tier) MUST pass lint, static analysis, tests, and builds before merge.

## Governance

- This constitution supersedes `docs/BUSINESS_RULES.md`, `README.md`, feature specs, and any
  other project document where they conflict. Those documents MUST be updated to match it.
- Amendments are made through `/speckit-constitution`, with a written rationale and a Sync Impact
  Report. Versioning follows semantic versioning: MAJOR for removing or redefining a principle,
  MINOR for adding a principle or materially expanding guidance, PATCH for clarifications.
- Every feature plan, review, and `/speckit-analyze` run checks compliance with this document.
  Exceptions MUST be time-boxed and recorded in the feature's plan.

**Version**: 1.0.1 | **Ratified**: 2026-10-18 | **Last Amended**: 2026-10-19
