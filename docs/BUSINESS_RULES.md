# Blood Donor Network: Business Rules

Every business rule, grouped by service.

- **Status:** approved baseline, aligned with [constitution](../.specify/memory/constitution.md) v1.0.1 (2026-10-19)
- **Precedence:** constitution → this file → [README.md](../README.md). If two conflict, the earlier one wins and the later one gets fixed
- **Data model and API:** README §9 and §11
- **Changing a rule:** edit it here first, then the specs that use it. A rule that contradicts the constitution needs a constitution amendment (`/speckit-constitution`)

---

## 📑 Contents

| # | Section | Rule IDs |
| --- | --- | --- |
| 1 | [📞 Phone Normalization](#1--phone-normalization) | `PH-xx` |
| 2 | [📝 Donor Registration](#2--donor-registration) | `DR-xx` |
| 3 | [🔁 Duplicate Prevention](#3--duplicate-prevention) | `DUP-xx` |
| 4 | [📲 Phone Verification](#4--phone-verification) | `PV-xx` |
| 5 | [🩸 Blood Type Verification](#5--blood-type-verification) | `BV-xx` |
| 6 | [🙋 Donor Self-Service & Availability](#6--donor-self-service--availability) | `AV-xx` |
| 7 | [📅 Last Donation](#7--last-donation) | `LD-xx` |
| 8 | [🆘 Blood Request](#8--blood-request) | `BR-xx` |
| 9 | [🔄 Request Lifecycle](#9--request-lifecycle) | `RL-xx` |
| 10 | [🎯 Matching & Ranking](#10--matching--ranking) | `MT-xx` |
| 11 | [📣 Notification Waves](#11--notification-waves) | `NW-xx` |
| 12 | [🤝 Donor Response & Contact](#12--donor-response--contact) | `CT-xx` |
| 13 | [🚨 Relay & Escalation](#13--relay--escalation) | `ES-xx` |
| 14 | [🔑 Admin & Dashboard](#14--admin--dashboard) | `AD-xx` |
| 15 | [📍 Locations](#15--locations) | `LC-xx` |
| 16 | [📊 Public Statistics](#16--public-statistics) | `PS-xx` |
| 17 | [🧾 Audit](#17--audit) | `AU-xx` |
| 18 | [🔒 Privacy & Data Protection](#18--privacy--data-protection) | `PR-xx` |
| 19 | [💾 Data Storage (Google Sheets MVP)](#19--data-storage-google-sheets-mvp) | `DS-xx` |
| 20 | [⏰ Scheduler](#20--scheduler) | `SC-xx` |

---

## 📌 Decisions

### ✅ Decided (2026-10-18)

| # | Decision | Rules |
| --- | --- | --- |
| D1 | **One donor per phone number** | DUP-01 |
| D2 | Contact model: **donor accepts first**. No donor number is shown before the donor accepts a specific request | CT-01…CT-06 |
| D3 | Notifications: **Telegram bot** in MVP, WhatsApp Cloud API next | NW-09, CT-09 |
| D4 | Escalation: **notification waves + admin alert** | NW-01…NW-08, ES-01…ES-05 |
| D5 | Search: **public counts only**; contact only through a request | PS-01…PS-04 |
| D6 | Locations: generic **Country → Region → District → Locality** hierarchy | LC-01…LC-07 |
| D7 | Hosting: free tier kept awake by an external cron | SC-01…SC-05 |

### ❓ Still open (default applies until you decide)

| # | Question | Default | Rules |
| --- | --- | --- | --- |
| O1 | Notify donors whose blood type is still `Pending`? | **Yes**, only after verified donors run out, labelled "blood type not confirmed" | MT-03 |
| O2 | Allow a request to list more than one blood type? | **Yes**, only the types the hospital named | BR-03 |
| O3 | Must an admin approve a request before notifications start? | **No**. Rate limits and audit instead; revisit if abuse happens | BR-10 |
| O4 | Which free backend host? | Decide before building | SC-05 |
| O5 | Who are the pilot admins? | — | |
| O6 | Pilot spelling for seed data: "Arab Muteer" or "Arab Matir"? | Arab Muteer (عرب مطير) | LC-05 |

---

## 1. 📞 Phone Normalization

| ID | Rule | Why |
| --- | --- | --- |
| PH-01 | Convert Arabic-Indic (`٠-٩`) and Persian (`۰-۹`) digits to `0-9` before anything else | Arabic keyboards type `٠١٠…`. Without this, valid numbers are rejected or stored twice |
| PH-02 | Strip spaces, dashes, dots, and brackets | `010 1234 5678`, `010-1234-5678` |
| PH-03 | Accept local, missing-zero, and international forms. Egypt: `01XXXXXXXXX`, `1XXXXXXXXX`, `201XXXXXXXXX`, `+201XXXXXXXXX`, `00201XXXXXXXXX` | All are common |
| PH-04 | Store as dial code + national number, digits only, no `+` (Egypt: `201012345678`) | Works directly in `wa.me/…`; add `+` only for `tel:` links |
| PH-05 | Only mobile prefixes from the country config are valid (Egypt: `10`, `11`, `12`, `15`) | Rejects landlines and typos. Landlines can't use Telegram or WhatsApp |
| PH-06 | Country rules come from `config/countries.php`. The country is taken from the selected location, falling back to `DEFAULT_COUNTRY` | Location-agnostic (constitution IV) |
| PH-07 | One normalizer is used for donor phones, requester phones, Telegram contacts, and admin search | Otherwise `010…` won't find `2010…` |
| PH-08 | Show users the local format (`0101 234 5678`) | People recognize the local format |

## 2. 📝 Donor Registration

| ID | Rule | Why |
| --- | --- | --- |
| DR-01 | Required: full name, phone, blood type (or "I don't know"), locality, 18+ confirmation, consent | |
| DR-02 | Full name: 2–4 words, 3–60 characters, Arabic or Latin letters only | Blocks junk like `aaa` or numbers |
| DR-03 | Blood type: 8 buttons (`A+ A- B+ B- AB+ AB- O+ O-`) plus **"I don't know my blood type"**. Never free text | Without "don't know", people guess, which is the worst outcome |
| DR-04 | "I don't know" is saved as `BloodType=Unknown`. These donors are volunteers and are never matched until a type is added and verified | |
| DR-05 | Locality chosen with the cascading picker (Region → District → Locality). Never free text | |
| DR-06 | **"I am 18 or older"** checkbox required | Legal age to consent to data use. This is **not** a medical eligibility rule |
| DR-07 | Consent checkbox required and unticked by default. Store `ConsentAcceptedAt` and `ConsentVersion` | Proves which text the person agreed to |
| DR-08 | Consent text says the number is shared **only with the requester of a request the donor accepts** | Matches the contact model (D2) |
| DR-09 | `HasWhatsApp` yes/no | Decides whether a WhatsApp button is shown after accept and in relay |
| DR-10 | Last donation date optional; not in the future; not before 1950 | |
| DR-11 | Starting values: `Status=Active`, `Availability=Available`, `PhoneVerified=false`, `BloodVerification=Pending` | |
| DR-12 | After registering, the donor gets a **manage link** (secret token, stored hashed, shown once) with a "send to yourself on WhatsApp" button, plus a **"Connect Telegram"** button | No accounts in the MVP. The link is how donors update their data (AV-01) |
| DR-13 | Rate limit: 5 registrations per IP per hour | Stops spam and number probing |
| DR-14 | Never collect: national ID, gender, address, date of birth, medical history | Data minimization (constitution II) |

## 3. 🔁 Duplicate Prevention

| ID | Rule | Why |
| --- | --- | --- |
| DUP-01 | **A normalized phone identifies at most one donor** | Decision D1 |
| DUP-02 | Name is never a duplicate key | Many people share names |
| DUP-03 | The duplicate check runs **after** normalization (PH-01…PH-05) | |
| DUP-04 | On a duplicate, never say "phone already exists". Say: "This number may already be registered. Use your manage link or contact the admin" | Otherwise anyone can test whether a number is in a blood database |
| DUP-05 | Inactive (soft-deleted) donors still count as duplicates. The admin reactivates them instead of creating a new record | Keeps history; avoids two records for one person |
| DUP-06 | The duplicate check and the insert run inside the same write lock | Google Sheets has no unique constraint, so two fast submissions would both pass |
| DUP-07 | Admin "Possible duplicates" view: very similar names in the same locality (different phones) | Catches people who registered twice with two numbers |

## 4. 📲 Phone Verification

| ID | Rule | Why |
| --- | --- | --- |
| PV-01 | Primary method, **Telegram contact share**: the donor opens `t.me/<bot>?start=<token>`; the bot asks them to tap "Share my phone number" | Free, official, and Telegram has already verified the number |
| PV-02 | The link token is one-time, valid 24 h, and stored hashed. A new one can be generated from the manage page | |
| PV-03 | The contact is accepted only if its user ID equals the sender's ID **and** its normalized number equals the registered phone | Blocks forwarded contacts and other people's numbers |
| PV-04 | On success: `PhoneVerified=true`, `PhoneVerificationMethod=TelegramContact`, `PhoneVerifiedAt`, and `TelegramChatId` saved | The same chat becomes the notification channel |
| PV-05 | A mismatch leaves the donor unverified, explains what to do, and is logged | |
| PV-06 | Fallback, **WhatsApp admin check**: a 6-digit code valid 7 days, stored hashed. "Verify via WhatsApp" opens `wa.me/<admin>` with "Blood Donor Network code 482913" pre-filled. The admin verifies only if the **sender number equals the registered number and** the code matches. Store `PhoneVerifiedBy` | For donors without Telegram. The sender number is the real proof |
| PV-07 | Phone unverified after 7 days → "Stale pending" admin list. After 30 days → `Status=Inactive` | Clears abandoned registrations |
| PV-08 | Changing the phone → `PhoneVerified=false`, a new duplicate check, and Telegram must be reconnected | Otherwise a verified donor could switch to an unverified number |
| PV-09 | Behind a `PhoneVerifier` interface so SMS OTP can be added later | |

## 5. 🩸 Blood Type Verification

| ID | Rule | Why |
| --- | --- | --- |
| BV-01 | `BloodType` (what the donor says) is separate from `BloodVerificationStatus` | |
| BV-02 | Statuses: `Pending`, `Verified`, `Rejected` | |
| BV-03 | The admin verifies only with evidence: lab result, donation card, hospital document, or personal knowledge. Store `BloodVerificationMethod` | |
| BV-04 | The admin views evidence in WhatsApp or Telegram. **The system never stores the image** | Medical documents are sensitive (constitution I) |
| BV-05 | Wrong blood type → the admin **corrects** it and verifies (not Reject) | Reject is only for fake or unverifiable registrations |
| BV-06 | The donor changes blood type → back to `Pending` automatically | Otherwise a verified donor could change type and stay "verified" |
| BV-07 | Store `BloodVerifiedBy` and `BloodVerifiedAt` | Accountability |
| BV-08 | The admin can verify phone and blood type in one action | Less admin work |

## 6. 🙋 Donor Self-Service & Availability

| ID | Rule | Why |
| --- | --- | --- |
| AV-01 | Manage link: change availability, locality, last donation date, HasWhatsApp, phone (PV-08), blood type (BV-06); connect Telegram; erase data | How donors update data without accounts |
| AV-02 | Availability: `Available`, `TemporarilyUnavailable`, `DoNotContact` | |
| AV-03 | `TemporarilyUnavailable` requires an until-date of at most 6 months. The donor returns to `Available` automatically after it | Otherwise people forget to switch back and the network shrinks |
| AV-04 | `DoNotContact`: never notified; the donor can switch back at any time | |
| AV-05 | Telegram bot buttons: "Available", "Pause" (1 week / 1 month / 3 months), "Stop notifications", "Send me a new manage link" (the old link is revoked) | Faster than the web page; the chat is the verified owner |
| AV-06 | Lost manage link without Telegram → the admin re-issues it after a WhatsApp check; the old link stops working | |
| AV-07 | **Erase my data**: name and phone blanked, `ErasedAt` set, the anonymous row kept for statistics. Available to the donor and the admin | Right to have data removed (constitution II) |
| AV-08 | Every 6 months the bot asks "Are you still available?". The answer updates `LastConfirmedAt` and availability. Donors without Telegram appear in an admin list | Numbers and availability go stale |
| AV-09 | Every self-service change is logged (actor `Donor`) | |

## 7. 📅 Last Donation

| ID | Rule | Why |
| --- | --- | --- |
| LD-01 | `LastDonationDate` is information and ranking only. It **never** blocks or hides a donor | Eligibility is a medical decision (constitution I) |
| LD-02 | Shown to the requester after Accept, and to admins, as relative time ("3 months ago" / "unknown") | Lets the requester and hospital judge |
| LD-03 | When an admin logs a donation (Phase 2 `Donations`), `LastDonationDate` updates automatically | |
| LD-04 | No "eligible after X days" calculation anywhere | |

## 8. 🆘 Blood Request

| ID | Rule | Why |
| --- | --- | --- |
| BR-01 | Required: requester name, requester phone, blood type(s), hospital name, search area, units, urgency, consent | |
| BR-02 | Optional: hospital maps link, required date (default today), notes (max 300 characters) | A free maps link, not a paid maps API |
| BR-03 | Requester selects **one or more** blood types, with the help text "only the types the hospital told you". The system never suggests types | Decision O2 |
| BR-04 | Units: 1–10 | |
| BR-05 | Urgency: `Normal`, `Urgent`, `Emergency` | |
| BR-06 | **Never collect** patient name or diagnosis | Not needed for matching; protects the patient |
| BR-07 | `SearchLocationID` = where to look for donors, at any level of the hierarchy | The hospital is often in a city; the donors are in villages |
| BR-08 | Requester consent checkbox: their name and phone are shared with donors who accept and appear on the share page. Store consent time and version | Requester data is personal data too |
| BR-09 | Same requester phone + overlapping blood type + request still open → no new request; tell them one is already open (use its link or contact the admin) | Families submit twice in panic |
| BR-10 | No admin approval before notifications | Decision O3 |
| BR-11 | Rate limit: 3 requests per phone per day, 5 per IP per day | The main abuse risk is fake requests |
| BR-12 | Requester gets a **manage link** (secret, stored hashed): status, notification progress, accepted donors, fulfil, cancel, share | No accounts. Without it, requests stay open forever and the KPIs are wrong |
| BR-13 | Request IDs are ULIDs, never 1, 2, 3… | |
| BR-14 | After creation, the requester sees how many usable donors match (count only) and that notifications have started | Sets expectations without revealing anyone |
| BR-15 | Every new `Emergency` request alerts the admin Telegram group | Admins can follow up immediately |

## 9. 🔄 Request Lifecycle

| ID | Rule | Why |
| --- | --- | --- |
| RL-01 | Statuses: `Open`, `InProgress`, `Fulfilled`, `Cancelled`, `Expired` | |
| RL-02 | `Open → InProgress` automatically on the **first donor acceptance** | |
| RL-03 | `→ Fulfilled` only by the requester (manage link) or an admin; record `UnitsFulfilled` (partial allowed) | |
| RL-04 | `→ Cancelled` by the requester or an admin, with reason: `PatientRecovered`, `FoundElsewhere`, `Duplicate`, `Fake`, `Other` | Reasons make reports useful |
| RL-05 | `→ Expired` automatically: Emergency/Urgent **48 h** after creation; Normal **1 day after RequiredDate** | |
| RL-06 | Fulfilled, Cancelled, and Expired are final; only an admin can reopen | |
| RL-07 | Timestamps: `CreatedAt`, `FirstNotifiedAt`, `FirstAcceptedAt`, `FulfilledAt`, `ClosedAt` | Main KPI = `FirstAcceptedAt − CreatedAt` |
| RL-08 | Closing a request stops waves, makes response links say "closed", and hides accepted donors' contact details from the requester | Donor numbers must not stay reachable through old links (constitution II) |
| RL-09 | Every status change is logged | |

## 10. 🎯 Matching & Ranking

| ID | Rule | Why |
| --- | --- | --- |
| MT-01 | Match the **exact** blood type(s) the requester selected. No compatibility logic | Core rule: not a medical system |
| MT-02 | Eligible: `Status=Active` AND (`Available` OR pause ended) AND `PhoneVerified=true` AND blood type known AND not `Rejected` AND locality inside the search area AND not already notified for this request | |
| MT-03 | `Verified` donors first. `Pending` donors only after those run out, labelled **"blood type not confirmed"** | Decision O1. Better than nobody in an emergency; the hospital tests all blood anyway |
| MT-04 | Ranking within each group: (1) location closeness: same locality → same district → same region; (2) not notified in the last `RotationDays`; (3) oldest last donation first (unknown = oldest); (4) most recently confirmed; (5) stable tie-breaker | |
| MT-05 | **Fairness rotation**: donors notified in the last `RotationDays` (default 7) rank lower | Otherwise the same donors get every request and burn out |
| MT-06 | Distance is by location hierarchy, **never** GPS | |
| MT-07 | Matching never returns a donor list to a requester. It feeds the notification waves | Decision D2 |

## 11. 📣 Notification Waves

| ID | Rule | Why |
| --- | --- | --- |
| NW-01 | Wave 1 is sent **immediately** on request creation | |
| NW-02 | Wave size `WaveSize` (default 5) | Controls donor fatigue and future message cost |
| NW-03 | Next wave after `WaveIntervalMinutes` by urgency: Emergency **5**, Urgent **10**, Normal **30** | |
| NW-04 | Waves stop when accepted donors ≥ `UnitsRequired × AcceptTargetMultiplier` (default 2) or the request closes | Some acceptors won't show up |
| NW-05 | A donor gets **at most one** notification per request | |
| NW-06 | Quiet hours 23:00–07:00 (local time) apply to `Normal` requests only | Don't wake people for non-urgent requests |
| NW-07 | Notification content (Arabic): blood type(s), hospital, area, urgency, units, needed-by date, medical disclaimer, **Accept / Decline**. **No requester phone** before Accept | |
| NW-08 | Sending updates the donor's `LastNotifiedAt` and creates a `Notifications` row | Needed for fairness and KPIs |
| NW-09 | Channel: Telegram when connected and reachable. Otherwise the notification is queued as `AdminRelay` (ES-01) | No matching donor is silently skipped (constitution VI) |
| NW-10 | Send failures are recorded. A "bot blocked" error sets `TelegramUnreachableAt` and re-queues the notification as relay | No silent failures |
| NW-11 | Wave size, intervals, multiplier, rotation, and quiet hours are settings, not code | |

## 12. 🤝 Donor Response & Contact

| ID | Rule | Why |
| --- | --- | --- |
| CT-01 | Donors respond with Telegram buttons or on the web response page (`/respond/<token>`, token stored hashed) | The web page serves relay donors too |
| CT-02 | Before accepting, the donor sees: "Your number will be shared only with the requester of this request" | Informed consent per request |
| CT-03 | **On Accept**, the donor immediately gets the requester's name and phone with Call and WhatsApp buttons. WhatsApp opens with a pre-filled Arabic message (blood type, hospital, "via Blood Donor Network") | The donor can act at once |
| CT-04 | **On Accept**, the requester's manage page shows the donor: first name + last initial, locality, verification label, last donation (relative), Call and WhatsApp buttons (WhatsApp only if `HasWhatsApp`) | The only place a donor's number ever appears |
| CT-05 | **Decline** options: "Can't now", "Pause me for 1 month", "Stop notifications". The latter two update availability. Nothing is revealed | |
| CT-06 | Accepts after the target (NW-04) are still recorded while the request is open | Extra donors are useful if some don't show up |
| CT-07 | Every accept, decline, and contact reveal is logged | KPI, fairness, abuse investigation |
| CT-08 | **Share request** button → public page with blood types, hospital, area, units, urgency, requester first name and contact, disclaimer, and "Register as a donor". **No donor data**. `noindex` | Uses the WhatsApp groups people already post in, and grows the donor base for free |
| CT-09 | All channels sit behind the `NotificationChannel` interface. WhatsApp Cloud API is added next without changing callers | |
| CT-10 | No unofficial WhatsApp automation (WhatsApp Web scripting), ever | Violates WhatsApp terms and gets numbers banned |

## 13. 🚨 Relay & Escalation

| ID | Rule | Why |
| --- | --- | --- |
| ES-01 | **Relay queue**: queued `AdminRelay` notifications, Emergency first, then by wave. Each item opens `wa.me/<donor>` with a pre-filled Arabic message containing the donor's response link (`tel:` if no WhatsApp). The admin marks it Sent (`SentBy`, `SentAt`) | Reaches donors without Telegram, for free |
| ES-02 | **Exhausted**: all eligible donors notified and accepted < units needed → alert the admin Telegram group plus a dashboard banner | |
| ES-03 | **Zero matches** at creation → the same alert immediately | |
| ES-04 | **Emergency without acceptance** after `EmergencyNoAcceptAlertMinutes` (default 15) → admin alert | |
| ES-05 | On ES-02/03, the requester's page says "All matching donors have been notified" and offers the admin's WhatsApp contact and the share button | Never a dead end in an emergency |
| ES-06 | Each alert fires once per condition per request | Avoids alert fatigue |
| ES-07 | Admins see failed notifications with the reason and can retry | |

## 14. 🔑 Admin & Dashboard

| ID | Rule | Why |
| --- | --- | --- |
| AD-01 | Admin login: email + password; locked for 15 min after 5 failed tries; rate-limited. No public sign-up; the first SuperAdmin is created from the command line | |
| AD-02 | Roles: `SuperAdmin` (everything) and `RegionAdmin` (only their location subtree). The MVP uses SuperAdmin, but scope checks exist | Ready for expansion without a data change |
| AD-03 | Dashboard: donors (total / phone-verified / blood-verified / usable / pending / Telegram-connected), open and emergency requests, relay queue size, failed notifications | |
| AD-04 | Blood type chart counts **usable** donors (Active + Available + phone-verified + not Rejected), with the total in brackets | Total registered overstates real coverage |
| AD-05 | Shortage warning when a blood type has fewer than `ShortageThreshold.<type>` usable donors | Rare types (AB-, O-) are always low, so one fixed threshold would always alarm |
| AD-06 | KPIs (last 30 days): median time to first accept, median time to fulfil, fulfil / cancel / expire rates, accept rate per wave | |
| AD-07 | Admin actions: verify, reject, edit, deactivate, reactivate, erase, re-issue manage or Telegram link, relay sent, request status changes, reopen, settings | |
| AD-08 | Admins cannot hard-delete anything | |
| AD-09 | **No one edits the Google Sheet by hand.** All changes go through the dashboard | Manual edits skip validation, duplicate checks, and the audit log |
| AD-10 | Admin lists show phones masked; the full number appears only on the donor detail page and in the relay action | Less exposure on shared screens |

## 15. 📍 Locations

| ID | Rule | Why |
| --- | --- | --- |
| LC-01 | Hierarchy: Country (config) → Region → District → Locality (Egypt: governorate → center → village) | Decision D6 |
| LC-02 | Names are unique within their parent | Two villages can share a name in different centers |
| LC-03 | Donors attach only to a **Locality**. Requests can search at any level | |
| LC-04 | A deactivated location is hidden from public pickers; its donors stay matchable | |
| LC-05 | The pilot is seed data only. Nothing in code, routes, or config keys refers to it by name | Constitution IV |
| LC-06 | Country config: dial code, mobile prefixes, national number length, timezone, default language | |
| LC-07 | Every location change is audited | |

## 16. 📊 Public Statistics

| ID | Rule | Why |
| --- | --- | --- |
| PS-01 | Public counts per blood type of usable donors in the chosen area subtree | Replaces open donor search (D5) |
| PS-02 | A count below `PublicCountThreshold` (default 3) is shown as **"fewer than 3"** | Prevents identifying people in small villages |
| PS-03 | No names, IDs, or per-donor data in any public response | |
| PS-04 | Counts cached for 5 minutes | Protects Sheets quotas |

## 17. 🧾 Audit

| ID | Rule | Why |
| --- | --- | --- |
| AU-01 | Log every admin change: actor, action, entity, ID, old value, new value, IP, time | |
| AU-02 | Also log: admin login success and failure, manage-link use, donor self-service changes, request status changes, notifications, accepts, declines, contact reveals | |
| AU-03 | Actor types: `Admin`, `Donor`, `Requester`, `System` | |
| AU-04 | The audit log is append-only: no edit or delete, even by SuperAdmin | |
| AU-05 | Phone numbers in audit values are masked (`2010****5678`) | The audit log mustn't become a second copy of personal data |

## 18. 🔒 Privacy & Data Protection

| ID | Rule | Why |
| --- | --- | --- |
| PR-01 | No public page lists donors. Every page is `noindex` except the home page | |
| PR-02 | A donor number appears only on the manage page of a request that donor accepted, and only while it is open (CT-04, RL-08) | Constitution II |
| PR-03 | The donor can pause, opt out, or erase their data (AV-03, AV-04, AV-07) | |
| PR-04 | Review against **Egypt's Personal Data Protection Law (No. 151 of 2020)** before the pilot | Blood type is health data, which counts as sensitive |
| PR-05 | Privacy notice page in Arabic: what is collected, why, who sees it, how to remove it | |
| PR-06 | Medical disclaimer on request creation, the requester manage page, share pages, response pages, and in every notification | Constitution I |
| PR-07 | Manage, response, and Telegram link tokens are random, stored only as hashes, and revocable | |

## 19. 💾 Data Storage (Google Sheets MVP)

| ID | Rule | Why |
| --- | --- | --- |
| DS-01 | All IDs are ULIDs (random, time-sortable) | Sheets can't auto-increment safely; sequential IDs are guessable |
| DS-02 | Write with `valueInputOption=RAW` | Otherwise Sheets turns `201012345678` into a number or scientific notation |
| DS-03 | Values starting with `=`, `+`, `-`, or `@` are neutralized before writing | Stops formula injection (e.g. a name like `=HYPERLINK(...)`) |
| DS-04 | Read each sheet in one batch, cache briefly, filter in PHP, and clear the cache on write | Stays inside Sheets API quotas |
| DS-05 | All writes go through one write lock; the backend runs as **one instance** | Same reason as DUP-06 |
| DS-06 | Retry with exponential backoff on quota errors (HTTP 429) | |
| DS-07 | Times stored in UTC (ISO 8601) and displayed in the location's timezone | Egypt uses daylight saving time again |
| DS-08 | Weekly backup: copy the spreadsheet to a private Drive folder | |
| DS-09 | Repository interfaces, so MySQL/PostgreSQL replaces Sheets without changing business logic | Constitution V |
| DS-10 | Plan the move to SQL at > 5,000 donors, repeated quota errors, or a need for more than one backend instance | Constitution V |

## 20. ⏰ Scheduler

| ID | Rule | Why |
| --- | --- | --- |
| SC-01 | A free external cron calls `POST /api/v1/internal/tick` every minute with a secret header | Free; Laravel's scheduler needs an always-running process |
| SC-02 | Tick steps: send due waves, escalations, expire requests, return availability, deactivate stale unverified donors, six-month confirmations, weekly backup | One place for all time-based rules |
| SC-03 | Each step is idempotent: running tick twice never sends or changes anything twice | Cron retries and overlaps happen |
| SC-04 | A failed step is logged and reported to the admin Telegram group | No silent failures (constitution VI) |
| SC-05 | The same call keeps the free-tier backend awake. Check that the chosen host (O4) allows this | Free hosts sleep when idle |

---

## 🔀 Changes from the first review (2026-10-18)

- **Duplicates:** "max 3 donors per phone" was dropped in favour of one donor per phone (D1).
- **Contact:**
  - Removed: showing donor numbers to requesters (10 at a time), and the per-donor "Direct / Via admin" contact preference.
  - Now: donors accept first (D2), and accepting is the only way a number is shared.
- **Notifications:**
  - Removed: `tel:` / `wa.me` links as the main channel.
  - Now: the Telegram bot, with admin relay for donors without Telegram (D3). New sections: Notification Waves, Relay & Escalation.
- **Phone verification:** Telegram contact share is now the primary method; WhatsApp admin check is the fallback.
- **Locations:** the `Villages` table became the generic location hierarchy (D6).
- **Search:** removed the request-bound donor list; the public now sees counts only (D5).
- **Lifecycle:** `InProgress` is now triggered by the first acceptance, not the first contact click.
- **Name and stack:** BloodLink → Blood Donor Network; Vue → Next.js (README).
