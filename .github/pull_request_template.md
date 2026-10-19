## 📝 Summary

<!-- What does this change do, and why? Link the Spec Kit feature and task IDs (e.g. specs/001-project-foundation, T064). -->

## ✅ Constitution checklist

Tick every item, or explain under **Notes** why it does not apply ([constitution](../.specify/memory/constitution.md)).

- [ ] **I. Not a medical authority**: no blood-compatibility or eligibility logic; the disclaimer is shown where required
- [ ] **II. Donor accepts first**: no donor phone, name, or identity in public responses; phones masked in logs; `noindex` on non-public pages
- [ ] **III. One phone, one verified donor**: phones go through the normalizer; duplicates blocked by a unique constraint
- [ ] **IV. Location-agnostic**: no pilot-specific names in code; user-facing text in translation files; times stored in UTC
- [ ] **V. Storage behind repositories**: data access only through repository interfaces; schema changes only through migrations; bound parameters only
- [ ] **VI. Reliability on a shoestring**: no new paid service without a written plan decision; scheduled steps are idempotent; failures are logged
- [ ] **VII. Accountability**: server-side validation; rate limits on new endpoints; audited actions are logged

## 🧪 Quality

- [ ] **Tests first** for core rules (normalizer, duplicates, matching, waves, privacy)
- [ ] All CI checks green: `backend`, `frontend`, `e2e`, `secrets-scan` (and `docker-image`)
- [ ] No secrets, `.env` files, local databases, or real credentials in the diff
- [ ] Docs updated (spec, plan, tasks, quickstart, README) where behavior changed

## 📎 Notes

<!-- Deviations, follow-ups, screenshots. -->
