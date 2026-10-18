# 🌐 UI Contract: Home Status Page

**Feature**: 001-project-foundation · **API**: [http-api.yaml](./http-api.yaml)

---

## 🧭 Page shell (every page)

| Element | Requirement | Source |
| --- | --- | --- |
| `<html>` | `lang="ar"`, `dir="rtl"` | FR-001 |
| Metadata | `robots: { index: false, follow: false }` by default; a page opts in explicitly. Plus `X-Robots-Tag: noindex` response header | FR-006 |
| Footer | Medical disclaimer, from `messages/ar.json` → `footer.disclaimer` | FR-005 |
| Font | IBM Plex Sans Arabic, self-hosted, `display: swap` | research R11 |
| Layout | Usable at **360 px** width; logical CSS properties only | FR-001 |

## 🔄 Status component states

| State | Trigger | Visual | Text key |
| --- | --- | --- | --- |
| ⏳ `checking` | Initial render, before the response | Neutral badge + spinner (respects `prefers-reduced-motion`) | `status.checking` |
| ✅ `running` | `200` with `status: "running"` | Green badge + check icon | `status.running` |
| ⚠️ `limited` | `200` with `status: "limited"` | Amber badge + warning icon | `status.limited` |
| ❌ `unavailable` | No response within **8 s**, network error, or any non-200 | Grey/red badge + icon | `status.unavailable` |

**Rules**

- Color is **never** the only signal: every state has an icon and text (WCAG 1.4.1).
- The status text sits in a region with `role="status"` and `aria-live="polite"`.
- No technical detail is shown: no codes, hosts, or errors (FR-004).
- A **"Retry"** button (≥ 44×44 px) appears only in `unavailable`; it re-runs the check.
- Contrast ≥ 4.5:1 for text, ≥ 3:1 for badge borders and icons (WCAG 2.1 AA).

## 🗣️ Message keys (`messages/ar.json`)

> 📜 Constitution IV: **every** user-facing string, including the 404 page and page titles, comes from these keys. Hard-coded UI text in components is not allowed.

| Key | Arabic text (draft) |
| --- | --- |
| `site.name` | شبكة متبرعي الدم |
| `status.checking` | جارٍ التحقق من حالة الخدمة… |
| `status.running` | الخدمة تعمل |
| `status.limited` | الخدمة تعمل بشكل محدود مؤقتًا |
| `status.unavailable` | الخدمة غير متاحة مؤقتًا. حاول مرة أخرى بعد قليل |
| `status.retry` | إعادة المحاولة |
| `notFound.title` | الصفحة غير موجودة |
| `notFound.body` | الرابط الذي فتحته غير صحيح أو لم يعد متاحًا. |
| `notFound.home` | العودة إلى الصفحة الرئيسية |
| `footer.disclaimer` | قرارات التبرع ونقل الدم وتوافق الفصائل يتم تحديدها بواسطة المختصين والجهة الطبية المعنية. المنصة شبكة للتواصل فقط ولا تقدم تشخيصًا أو قرارًا طبيًا، وليست بنك دم. |

## ✅ Checked by e2e

| Check | Tool |
| --- | --- |
| Each of the 4 states renders with the right text and icon (backend mocked via route interception) | Playwright |
| `lang`, `dir`, and the robots meta are present on every page | Playwright |
| Zero axe violations for `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` | @axe-core/playwright |
| No horizontal scroll at 360 px | Playwright viewport |
| Touch targets ≥ 44×44 px | Playwright bounding boxes |
