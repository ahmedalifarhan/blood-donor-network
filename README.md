# Blood Donor Network

**Community blood donor and emergency matching platform**

> Status: Planning. No application code yet.
> Budget: low-cost. Every service has a free path for the pilot, and paid services sit behind an interface.
> Pilot area: **Arab Muteer (عرب مطير), Egypt**. The pilot is a data row, not part of the product name or the code.

> 📜 **Source of truth:** [constitution](.specify/memory/constitution.md) → [docs/BUSINESS_RULES.md](docs/BUSINESS_RULES.md) → this README. If two conflict, the earlier one wins.

---

## 📑 Table of Contents

1. [🧩 Problem](#1--problem)
2. [🔭 Vision](#2--vision)
3. [🧭 Core Principle](#3--core-principle)
4. [👥 Users & Roles](#4--users--roles)
5. [🔄 Main Workflows](#5--main-workflows)
6. [📋 Functional Requirements](#6--functional-requirements)
7. [🔧 Non-Functional Requirements](#7--non-functional-requirements)
8. [🔖 Statuses & Reference Values](#8--statuses--reference-values)
9. [💾 Data Model](#9--data-model)
10. [🧱 Architecture](#10--architecture)
11. [🔌 API Design](#11--api-design)
12. [🔒 Security & Privacy](#12--security--privacy)
13. [🩺 Medical Disclaimer & Consent](#13--medical-disclaimer--consent)
14. [💰 Cost Plan](#14--cost-plan)
15. [🧰 Tech Stack](#15--tech-stack)
16. [📁 Project Structure](#16--project-structure)
17. [🚀 Roadmap](#17--roadmap)
18. [🚫 Out of Scope (for now)](#18--out-of-scope-for-now)
19. [📈 KPIs](#19--kpis)
20. [✅ Decisions](#20--decisions)

---

## 1. 🧩 Problem

When someone in hospital needs a specific blood type (for example **O+**), the search for donors today is manual:

- Asking relatives and friends
- Posting in WhatsApp groups and on social media
- Calling people at random
- Trying to remember who knows their blood type

This causes:

- Lost time in emergencies (often **30–120 minutes**)
- No way to know who is available right now
- Duplicate, outdated, or wrong data (old phone numbers, wrong blood types)
- Donor phone numbers spread publicly in group posts
- No count of donors per blood type
- No history of past blood requests
- No clear process for emergencies

## 2. 🔭 Vision

Not a directory of `Name / Phone / Blood Type`, but a **community blood donor network** that:

1. Registers donors with clean, structured data
2. Verifies phone numbers and prevents duplicates
3. Tracks blood type verification and donor availability
4. Lets patients, families, and hospital staff create blood requests
5. Notifies matching, available donors, who **choose** whether to respond
6. Handles emergencies with priority and escalation
7. Gives admins a dashboard, statistics, and an audit trail
8. Grows from one pilot village to any village, city, or country by adding data, not code

**The real value:** cut the time to reach a willing donor and turn a random manual search into a **structured emergency response**, without exposing donors' phone numbers.

## 3. 🧭 Core Principle

> **Don't build a village-specific blood database. Build a location-agnostic blood donor network whose first pilot is Arab Muteer.**

| Don't | Do |
| --- | --- |
| `ArabMuteerDonors`, `VillageBloodSystem` | `Donors`, `Locations`, `BloodRequests` |
| `VillageName = "عرب مطير"` on every record | `LocalityID` → `Locations` (Country → Region → District → Locality) |
| Hard-coded Egyptian phone rules | Per-country config (dial code, mobile prefixes, timezone) |
| Hard-coded pilot location | The pilot is rows in `Locations` plus seed data |

The system must be **location-agnostic, storage-agnostic, privacy-first, API-based, Arabic-first, and mobile-first**, even while it runs on Google Sheets.

## 4. 👥 Users & Roles

### 🩸 Donor (المتبرع)
- Registers with name, phone, blood type, locality, consent, and 18+ confirmation
- Verifies the phone by sharing it with the Telegram bot (fallback: admin check over WhatsApp)
- Receives request notifications and **accepts or declines** each one; the phone number is shared only on accept
- Manages availability (available / paused until a date / do not contact) through a secret manage link or the bot
- Can erase their personal data at any time

### 🆘 Requester (محتاج الدم)
A patient's relative or friend, or hospital staff. There are no accounts in the MVP.
- Sees public counts of available donors per blood type and area (no identities)
- Creates a blood request: blood type(s), hospital, search area, units, urgency, contact phone
- Follows the request through a secret manage link: notification progress, accepted donors, and fulfil/cancel actions

### 🔑 Admin / Moderator (المسؤول)
- Logs in to the dashboard
- Verifies phones (fallback method) and blood types
- Activates, deactivates, edits, and erases donors; reviews possible duplicates
- Monitors requests and emergencies, works the **relay queue** (donors without Telegram), and receives escalation alerts
- Manages locations and settings; views statistics and the audit log
- Roles: `SuperAdmin`, and `RegionAdmin` scoped to a location subtree (only SuperAdmin is used in the MVP)

## 5. 🔄 Main Workflows

### 5.1 Emergency blood request (donor accepts first)

```text
Patient needs O+
      ↓
Requester opens site → "I need blood" (أحتاج دم)
      ↓
Enters: blood type(s), hospital, search area, units, urgency, name, phone, consent
      ↓
Request created → requester gets a manage link
Page shows: "14 matching donors in this area. Notifying them now."
      ↓
Wave 1: top-ranked N donors get a Telegram notification
        (donors without Telegram → admin relay queue)
      ↓
Donor taps Accept ──→ donor receives requester's contact (call / WhatsApp)
                  ──→ requester's page shows the donor (name, call / WhatsApp)
Donor taps Decline → nothing is revealed
      ↓
No answer in time → next wave (shorter interval for Emergency)
      ↓
Enough donors accepted → waves stop
All eligible donors notified, still short → admins alerted
      ↓
Requester marks Fulfilled (or Cancelled) → contact details hidden
```

### 5.2 Donor registration and verification

```text
Step 1: Full name + phone
      ↓
Phone normalized (Arabic digits, country rules) → 201012345678
      ↓
Duplicate check under write lock → generic message if already registered
      ↓
Step 2: Blood type (8 buttons or "I don't know")
      ↓
Step 3: Location (Region → District → Locality), last donation date (optional),
        18+ checkbox, consent checkbox
      ↓
Saved: PhoneVerified = false, BloodVerificationStatus = Pending
Donor gets a manage link
      ↓
"Connect Telegram" → bot asks to share phone → number matches → PhoneVerified = true
(no Telegram → "Verify via WhatsApp" code to admin → admin verifies)
      ↓
Admin verifies blood type with evidence → Verified
```

### 5.3 Blood request lifecycle

```text
Open → InProgress                (first donor accepts)
Open / InProgress → Fulfilled    (requester or admin)
Open / InProgress → Cancelled    (requester or admin, with reason)
Open / InProgress → Expired      (automatic: Emergency/Urgent 48h after creation,
                                  Normal 1 day after RequiredDate)
Fulfilled / Cancelled / Expired are final; only an admin can reopen.
```

## 6. 📋 Functional Requirements

This is a summary. Every numbered rule is in [docs/BUSINESS_RULES.md](docs/BUSINESS_RULES.md).

### Donor
| ID | Requirement |
| --- | --- |
| FR-D01 | Donor registers with full name, phone, blood type (or "unknown"), locality, 18+ confirmation, and consent |
| FR-D02 | Blood type comes from the 8 fixed values or "unknown". Never free text |
| FR-D03 | Phone is normalized using the country's config before saving |
| FR-D04 | **One donor per normalized phone**, inactive records included. Name is never a duplicate key |
| FR-D05 | Phone must be verified before the donor receives notifications (Telegram contact share; WhatsApp admin fallback) |
| FR-D06 | New donors start with `BloodVerificationStatus = Pending` |
| FR-D07 | Donor can update availability, locality, and last donation date through the manage link or the bot |
| FR-D08 | Donor can pause, opt out ("Do not contact"), re-activate, or erase their data |
| FR-D09 | Donor accepts or declines each request notification. Accepting shares their contact with that requester only |

### Blood Request
| ID | Requirement |
| --- | --- |
| FR-R01 | Requester creates a request: name, phone, blood type(s), hospital, search area, units, urgency, required date, consent |
| FR-R02 | Urgency is one of `Normal`, `Urgent`, `Emergency` |
| FR-R03 | Emergency requests are notified first, with the shortest wave interval, and highlighted for admins |
| FR-R04 | Requester follows and closes the request through a secret manage link |
| FR-R05 | Request status follows the lifecycle in §5.3 |

### Matching & Notifications
| ID | Requirement |
| --- | --- |
| FR-M01 | Match the **exact blood type(s) requested**. No compatibility logic |
| FR-M02 | Only donors that are Active, Available, phone-verified, with a known and not-rejected blood type, inside the search area. Verified donors first; `Pending` donors only after those run out, labelled "not confirmed" (O1) |
| FR-M03 | Rank by location closeness, fairness rotation, last donation, and recent confirmation |
| FR-M04 | Notify in waves; stop when enough donors have accepted; alert admins when donors run out |
| FR-M05 | Public pages show **counts only**, never individual donors |

### Admin
| ID | Requirement |
| --- | --- |
| FR-A01 | Admin login with lockout |
| FR-A02 | Dashboard: donors (total / verified / usable / pending), open and emergency requests, relay queue, failed notifications |
| FR-A03 | Usable donor count per blood type, with shortage warnings |
| FR-A04 | Donor list: view, edit, verify, reject, deactivate, reactivate, erase, re-issue manage link |
| FR-A05 | Request list: view, update, cancel, mark fulfilled, reopen |
| FR-A06 | Relay queue: send prefilled WhatsApp messages to donors without Telegram and mark them sent |
| FR-A07 | Manage locations and settings |
| FR-A08 | Every admin action goes to the append-only audit log |
| FR-A09 | No hard delete. Deactivate or anonymize instead |

## 7. 🔧 Non-Functional Requirements

- **Mobile-first**: most users open it on a phone over weak connections
- **Arabic-first, RTL**, with every string in translation files (English next)
- **Fast emergency paths**: creating a request and accepting one work on a cold cache and slow networks
- **HTTPS only**
- **Backend validation** on every input
- **Rate limiting** on registration, verification, request creation, accept actions, and admin login
- **Storage-agnostic**: switching Google Sheets → MySQL/PostgreSQL changes no business logic, API, or frontend
- **Location-agnostic**: a new country or village needs data and config only
- **No silent failures**: failed notifications and scheduler errors are shown to admins

## 8. 🔖 Statuses & Reference Values

| Field | Values | Meaning |
| --- | --- | --- |
| `Status` | `Active`, `Inactive` | Record in use (soft delete) |
| `AvailabilityStatus` | `Available`, `TemporarilyUnavailable`, `DoNotContact` | Can we notify them now |
| `BloodVerificationStatus` | `Pending`, `Verified`, `Rejected` | Has an admin confirmed the blood type |
| `PhoneVerificationMethod` | `TelegramContact`, `AdminWhatsApp` | How the phone was proven |

| Field | Values |
| --- | --- |
| Blood types | `A+` `A-` `B+` `B-` `AB+` `AB-` `O+` `O-` (donor may also be `Unknown`) |
| Request urgency | `Normal`, `Urgent`, `Emergency` |
| Request status | `Open`, `InProgress`, `Fulfilled`, `Cancelled`, `Expired` |
| Cancel reason | `PatientRecovered`, `FoundElsewhere`, `Duplicate`, `Fake`, `Other` |
| Location level | `Region`, `District`, `Locality` (countries come from config) |
| Notification channel | `Telegram`, `AdminRelay` (next: `WhatsApp`) |
| Notification response | `Pending`, `Accepted`, `Declined`, `NoResponse` |
| Admin role | `SuperAdmin`, `RegionAdmin` |

## 9. 💾 Data Model

Designed as if for SQL, even while stored in Google Sheets (one sheet per table, row 1 = headers). All IDs are **ULIDs**. All times are **UTC ISO 8601**.

### Locations
| Column | Type | Notes |
| --- | --- | --- |
| ID | ulid | PK |
| CountryCode | string | ISO 3166-1 alpha-2, e.g. `EG`; rules in `config/countries.php` |
| ParentID | ulid | null for top-level regions |
| Level | enum | `Region`, `District`, `Locality` |
| Name | string | e.g. عرب مطير |
| NameEn | string | optional |
| IsActive | bool | |

Unique: (`CountryCode`, `ParentID`, `Name`).

### Donors
| Column | Type | Notes |
| --- | --- | --- |
| ID | ulid | PK |
| FullName | string | blanked on erase |
| Phone | string | normalized digits, **unique**; blanked on erase |
| CountryCode | string | |
| LocalityID | ulid | FK → Locations (Level = Locality) |
| HasWhatsApp | bool | |
| BloodType | enum | 8 values or `Unknown` |
| BloodVerificationStatus | enum | |
| BloodVerificationMethod | enum | `LabResult`, `DonationCard`, `HospitalDocument`, `PersonalKnowledge` |
| BloodVerifiedBy / BloodVerifiedAt | ulid / datetime | |
| PhoneVerified | bool | |
| PhoneVerificationMethod | enum | |
| PhoneVerifiedBy / PhoneVerifiedAt | ulid / datetime | `PhoneVerifiedBy` is null for Telegram |
| VerificationCodeHash / VerificationCodeExpiresAt | string / datetime | WhatsApp fallback |
| TelegramChatId | string | set when the bot is connected |
| TelegramLinkTokenHash / TelegramLinkTokenExpiresAt | string / datetime | one-time |
| TelegramUnreachableAt | datetime | set when the donor blocks the bot |
| AvailabilityStatus | enum | |
| UnavailableUntil | date | required for `TemporarilyUnavailable` |
| LastDonationDate | date | informational only |
| LastConfirmedAt | datetime | "still available" confirmation |
| LastNotifiedAt | datetime | fairness rotation |
| Status | enum | |
| AgeConfirmed | bool | |
| ConsentAcceptedAt / ConsentVersion | datetime / string | |
| ManageTokenHash | string | |
| ErasedAt | datetime | |
| CreatedAt / UpdatedAt | datetime | |

### BloodRequests
| Column | Type | Notes |
| --- | --- | --- |
| ID | ulid | PK |
| RequesterName | string | |
| RequesterPhone | string | normalized |
| RequesterConsentAt / ConsentVersion | datetime / string | |
| BloodTypes | list | one or more of the 8 values |
| HospitalName | string | |
| HospitalLocationUrl | string | optional maps link |
| SearchLocationID | ulid | FK → Locations, any level |
| UnitsRequired / UnitsFulfilled | int | 1–10 |
| Urgency | enum | |
| RequiredDate | date | default today |
| Notes | string | max 300 chars |
| Status | enum | |
| CloseReason | enum | |
| ManageTokenHash | string | |
| CurrentWave / NextWaveAt | int / datetime | wave scheduler state |
| CreatedAt, FirstNotifiedAt, FirstAcceptedAt, FulfilledAt, ClosedAt, UpdatedAt | datetime | KPI timestamps |

### Notifications
| Column | Type | Notes |
| --- | --- | --- |
| ID | ulid | PK |
| BloodRequestID | ulid | FK |
| DonorID | ulid | FK; at most one row per (request, donor) |
| Wave | int | |
| Channel | enum | `Telegram`, `AdminRelay` |
| SendStatus | enum | `Queued`, `Sent`, `Failed` |
| Response | enum | `Pending`, `Accepted`, `Declined`, `NoResponse` |
| ResponseTokenHash | string | for the accept/decline web page |
| SentBy | ulid | admin, for relay |
| SentAt / RespondedAt | datetime | |
| Error | string | |

### Admins
| Column | Type | Notes |
| --- | --- | --- |
| ID | ulid | PK |
| Name | string | |
| Email | string | unique |
| PasswordHash | string | bcrypt (Laravel `Hash`) |
| Role | enum | `SuperAdmin`, `RegionAdmin` |
| ScopeLocationID | ulid | nullable; RegionAdmin subtree |
| TelegramChatId | string | for alerts |
| IsActive | bool | |
| FailedLogins / LockedUntil | int / datetime | |
| CreatedAt | datetime | |

### AuditLogs (append-only)
| Column | Type | Notes |
| --- | --- | --- |
| ID | ulid | PK |
| ActorType | enum | `Admin`, `Donor`, `Requester`, `System` |
| ActorID | ulid | nullable |
| Action | string | e.g. `donor.verified`, `notification.accepted`, `contact.revealed` |
| Entity / EntityID | string / ulid | |
| OldValue / NewValue | json | phones masked `2010****5678` |
| IP | string | |
| CreatedAt | datetime | |

### Settings
Key/value: `WaveSize` (5), `WaveIntervalMinutes.Emergency` (5) / `.Urgent` (10) / `.Normal` (30), `AcceptTargetMultiplier` (2), `RotationDays` (7), `PublicCountThreshold` (3), `QuietHours` (23:00–07:00, Normal requests only), `ShortageThreshold.<type>`, `AdminWhatsAppNumber`, `ConsentVersion`.

### Donations (Phase 2, table reserved)
`ID`, `DonorID`, `BloodRequestID` (nullable), `DonationDate`, `Units`, `Notes`, `CreatedAt`.

### Relationships

```text
Locations 1 ── * Locations (parent → children)
Locations 1 ── * Donors          (Locality)
Locations 1 ── * BloodRequests   (search area)
BloodRequests 1 ── * Notifications * ── 1 Donors
Donors 1 ── * Donations (Phase 2)
Admins 1 ── * AuditLogs
```

## 10. 🧱 Architecture

**Next.js never talks to Google Sheets or holds business rules.** Laravel is the only gatekeeper. Credentials, sheet IDs, tokens, and rules stay on the server.

```text
   Users (mobile browser)              Donors (Telegram)
           │ HTTPS                            │
           ▼                                  ▼
   ┌────────────────┐               ┌──────────────────┐
   │ Next.js app    │               │ Telegram Bot API │
   │ (Vercel)       │               └────────┬─────────┘
   └───────┬────────┘                        │ webhook
           │ REST / JSON  /api/v1            │
           ▼                                 ▼
   ┌───────────────────────────────────────────────────┐
   │ Laravel API (single instance)                     │
   │ Validation · Normalizer · Duplicate lock          │
   │ Matching · Wave scheduler · Verification · Audit  │
   └───────┬───────────────────────────────┬───────────┘
           │ Repository interfaces          │ NotificationChannel
   ┌───────┴─────────┐              ┌───────┴──────────────┐
   ▼                 ▼              ▼                      ▼
 Sheets repos   Eloquent repos   TelegramChannel   WhatsAppCloudChannel
 (MVP)          (later)          (MVP)             (next)

 External free cron ──every minute──▶ POST /api/v1/internal/tick
 (sends due waves, expires requests, returns availability, keeps the host awake)
```

Key design rules (the constitution has the full list):

- **Repository pattern from day one.** `DATA_DRIVER=sheets|mysql|pgsql` picks the implementation, and every implementation passes the same contract tests.
- **Single writer lock** around every Sheets write, plus a short read cache invalidated on write. The backend runs as **one instance** while on Sheets.
- **`NotificationChannel` interface**: Telegram now, WhatsApp Cloud API next, without changing callers.
- **`PhoneVerifier` interface**: Telegram contact share now, WhatsApp admin check as fallback, SMS OTP possible later.
- **One scheduler endpoint** drives every time-based rule, and each step is idempotent.

## 11. 🔌 API Design

All routes are under `/api/v1`. Responses go through API Resources that whitelist fields.

### Public
| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/locations?parent_id=` | Cascading location lists |
| GET | `/reference` | Blood types, urgencies, consent text and version |
| GET | `/stats/availability?location_id=` | Usable donor counts per blood type (threshold applied) |
| POST | `/donors` | Register donor (returns manage link and Telegram link) |
| GET/PATCH | `/donors/manage/{token}` | Donor self-service |
| POST | `/donors/manage/{token}/telegram-link` | New Telegram connect link |
| POST | `/donors/manage/{token}/whatsapp-code` | WhatsApp fallback verification code |
| POST | `/donors/manage/{token}/erase` | Erase personal data |
| POST | `/blood-requests` | Create request (returns manage link) |
| GET/PATCH | `/blood-requests/manage/{token}` | Requester view: progress, accepted donors, close |
| GET | `/blood-requests/{id}/share` | Public share view, no donor data |
| GET/POST | `/responses/{token}` | Donor accept / decline page (Telegram and relay) |
| POST | `/telegram/webhook/{secret}` | Telegram bot updates |
| POST | `/internal/tick` | Scheduler, secret header required |

> There is **no** donor search endpoint. Requesters see counts, and donors reveal themselves only by accepting.

### Admin (Sanctum)
| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/admin/login` | Login |
| GET | `/admin/dashboard` | Stats |
| GET/PATCH | `/admin/donors`, `/admin/donors/{id}` | List, verify, edit, deactivate, erase, re-issue link |
| GET | `/admin/donors/duplicates` | Possible duplicates |
| GET/PATCH | `/admin/blood-requests`, `/admin/blood-requests/{id}` | List and update status |
| GET/POST | `/admin/relay-queue`, `/admin/relay-queue/{id}/sent` | Manual WhatsApp relay |
| GET/POST/PATCH | `/admin/locations` | Manage locations |
| GET/PATCH | `/admin/settings` | Settings |
| GET | `/admin/audit-logs` | Audit log |

## 12. 🔒 Security & Privacy

The data includes name, phone, location, and blood type (**health data**, which is sensitive).

- **Donor accepts first**: no donor phone or identity leaves the server until that donor accepts a specific request, and only that request's requester sees it, only while the request is open
- Public pages show only aggregate counts; small counts are shown as "fewer than 3"
- No page lists donors; non-public pages are `noindex`
- Google Sheet is **private**, shared only with the service account and one owner; no manual edits
- Secrets live only in `.env` (never committed)
- HTTPS, backend validation, rate limits, Sanctum admin auth with lockout
- Manage and response links use random tokens stored only as hashes
- Append-only audit log with masked phones
- Soft delete plus erase-by-anonymization; minimum data collected
- Review against **Egypt's Personal Data Protection Law (No. 151 of 2020)** before the pilot

## 13. 🩺 Medical Disclaimer & Consent

### Consent checkbox (registration)

> ☑ أوافق على استخدام بياناتي لإرسال طلبات التبرع بالدم إليّ، ولن يُشارك رقمي إلا مع صاحب الطلب الذي أقبله.

Plus: ☑ عمري 18 سنة أو أكثر.

### Medical disclaimer (request pages, share pages, notifications)

> قرارات التبرع ونقل الدم وتوافق الفصائل يتم تحديدها بواسطة المختصين والجهة الطبية المعنية. المنصة شبكة للتواصل فقط ولا تقدم تشخيصًا أو قرارًا طبيًا، وليست بنك دم.

Rules that follow:

- The system does **not** decide compatibility. It matches the **exact type(s) the hospital asked for**
- The system does **not** decide who can donate. `LastDonationDate` is information and ranking only
- A donor's blood type is a claim until an admin verifies it

## 14. 💰 Cost Plan

Goal: run the pilot for **$0**, and allow low-cost upgrades only behind interfaces.

| Need | Pilot option | Notes |
| --- | --- | --- |
| Code hosting + CI | GitHub (private repo) + GitHub Actions | free |
| Frontend | **Vercel** free tier | `*.vercel.app` subdomain |
| Backend | Free-tier Docker/PHP host (e.g. Render) | Sleeps when idle, so the cron below keeps it awake. Check the host's terms |
| Scheduler + keep-alive | Free external cron (e.g. cron-job.org) → `/internal/tick` every minute | |
| Database (MVP) | **Google Sheets API** via a service account | Free; per-minute quotas, so reads are cached |
| Donor notifications | **Telegram Bot API** | Free and official |
| Fallback contact | Admin relay through `wa.me` links | Free, manual |
| Phone verification | Telegram contact share; WhatsApp admin check | Free |
| Backups | Scheduled spreadsheet copy to private Drive | Free |
| Domain | Host subdomain | Buy one after the pilot |
| **Next paid step** | WhatsApp Cloud API notifications | Per-message cost; needs Meta business setup |

> Free-tier terms change often. Re-check each provider's limits before deploying.

## 15. 🧰 Tech Stack

| Layer | Technology |
| --- | --- |
| Frontend | Next.js (App Router), TypeScript strict |
| UI | Tailwind CSS (RTL), shadcn/ui (Radix) |
| i18n | Translation files, Arabic default (library chosen in the foundation slice) |
| Backend | Laravel (PHP 8.3+), REST JSON API `/api/v1` |
| Admin auth | Laravel Sanctum |
| MVP storage | Google Sheets API (`google/apiclient`) |
| Production DB | MySQL or PostgreSQL (later) |
| Notifications | Telegram Bot API → WhatsApp Cloud API |
| Tests | Pest/PHPUnit, Larastan, Pint; ESLint, Prettier, Playwright |
| Version control | Git + GitHub monorepo |
| Methodology | Spec Kit (spec-driven development) |

## 16. 📁 Project Structure

```text
blood-donor-network/
├── README.md
├── .specify/memory/constitution.md   ← project rules (source of truth)
├── docs/
│   └── BUSINESS_RULES.md             ← every numbered business rule
├── specs/                            ← one folder per Spec Kit feature (NNN-slice-name)
├── frontend/                         ← Next.js
│   ├── app/
│   │   ├── (public)/                 home, donate, request/new, share, privacy
│   │   ├── donor/manage/[token]/
│   │   ├── request/manage/[token]/
│   │   ├── respond/[token]/          donor accept / decline
│   │   └── admin/                    login, dashboard, donors, requests, relay, locations, settings, audit
│   ├── components/
│   ├── lib/api/                      typed API client
│   └── messages/                     ar.json, en.json
└── backend/                          ← Laravel
    ├── app/
    │   ├── Http/{Controllers,Requests,Resources}/
    │   ├── Services/                 PhoneNormalizer, Matching, WaveScheduler, Verification
    │   ├── Notifications/Channels/   Telegram, (WhatsAppCloud)
    │   ├── Repositories/
    │   │   ├── Contracts/
    │   │   ├── Sheets/
    │   │   ├── InMemory/             tests
    │   │   └── Eloquent/             later
    │   └── Support/                  Ulid, Masking, SheetsLock
    └── config/countries.php
```

### Environment variables (`backend/.env`, never committed)

```env
APP_ENV=production
APP_KEY=
DATA_DRIVER=sheets
DEFAULT_COUNTRY=EG
GOOGLE_SHEET_ID=
GOOGLE_SERVICE_ACCOUNT_JSON=
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_ADMIN_CHAT_ID=
SCHEDULER_SECRET=
FRONTEND_URL=
```

## 17. 🚀 Roadmap

### 📝 Phase 0: Planning (now)
- [x] Collect ideas and review business rules
- [x] Constitution v1.0.1
- [x] Business rules aligned with the constitution
- [ ] Remaining open decisions (§20)
- [ ] Google Sheet + service account created
- [ ] Telegram bot created

### 🚀 Phase 1: MVP pilot
Each item is built as a Spec Kit feature (`/speckit-specify` → … → `/speckit-converge`).
- Foundation, admin access, locations
- Donor registration, Telegram verification, admin verification, donor self-service
- Public counts, blood requests, matching and waves, donor responses, relay and escalation
- Admin dashboard, hardening, **pilot in Arab Muteer**, collect feedback

### 🌱 Phase 2: Growth
- WhatsApp Cloud API notifications
- MySQL/PostgreSQL (switch `DATA_DRIVER`)
- Donation history (`Donations`)
- Nearby blood banks list for zero-result requests
- Neighbouring localities, RegionAdmin roles in use
- Better reporting, English UI

### 🌍 Phase 3: Regional / multi-country
- More countries (config + location data)
- Mobile app + push notifications
- Hospital integrations
- Advanced analytics

## 18. 🚫 Out of Scope (for now)

- AI features
- Mobile app
- Medical compatibility or eligibility engine
- Live GPS tracking or distance by coordinates
- Government / national ID integration
- Unofficial WhatsApp automation (forbidden, not just deferred)
- Paid SMS
- Hospital APIs

## 19. 📈 KPIs

**Most important: time to first willing donor** (`FirstAcceptedAt − CreatedAt`). Today 30–120 min → target **under 5 min**, depending on donor availability.

| Area | Metrics |
| --- | --- |
| Donors | Total, phone-verified, blood-verified, usable (Active + Available + verified), Telegram-connected |
| Coverage | Usable donors per blood type and area |
| Requests | Total, Emergency, Fulfilled, Cancelled, Expired, Exhausted (escalated) |
| Speed | Created → first notified, created → first accepted, created → fulfilled |
| Response | Accept / decline / no-response rate per wave |

## 20. ✅ Decisions

### ✅ Decided (2026-10-18, recorded in the constitution)

| # | Decision |
| --- | --- |
| 1 | Name: **Blood Donor Network** (was BloodLink) |
| 2 | Frontend: **Next.js** (was Vue 3); backend: Laravel |
| 3 | Hosting: free tier kept awake by an external cron |
| 4 | Duplicates: **one donor per phone** |
| 5 | Contact model: **donor accepts first** |
| 6 | Notifications: **Telegram bot** in MVP, WhatsApp Cloud API next |
| 7 | Escalation: notification waves + admin alert |
| 8 | Search: public counts only; contact only through a request |
| 9 | Locations: generic Country → Region → District → Locality hierarchy |

### ❓ Still open (defaults used until you decide)

| # | Question | Default |
| --- | --- | --- |
| O1 | Notify donors whose blood type is still `Pending`? | Yes, after verified donors run out, labelled "not confirmed" |
| O2 | Allow a request to list more than one blood type? | Yes, only types the hospital named |
| O3 | Must an admin approve a request before notifications? | No (rate limits + audit instead) |
| O4 | Which free backend host? | Decide in the foundation slice |
| O5 | Who are the pilot admins? | — |
| O6 | Pilot spelling for seed data: "Arab Muteer" or "Arab Matir"? | Arab Muteer (عرب مطير) |
