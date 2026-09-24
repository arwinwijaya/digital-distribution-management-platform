# Phase 6 — Cross-Unit Integration Verification

**Date:** 2026-09-24
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 5 DONE (semua task sebelumnya selesai)
**Contains tasks:** {T17, T18}
**Unlocks next:** (closeout)

---

## Task List

Total: 2 tasks | Final phase

- **T17:** Fallback & guardrail adapter lintas unit [depends: T3, T5–T8] [test-risk] → [tasks/T17-adapter-fallback-guardrail.md](tasks/T17-adapter-fallback-guardrail.md)
- **T18:** Cross-unit integration verification end-to-end [depends: semua task Phase 1–5] [test-risk] → [tasks/T18-cross-unit-integration.md](tasks/T18-cross-unit-integration.md)

T17, T18 paralel (T18 menunggu seluruh dependensinya; T17 bisa jalan saat T3–T8 selesai).

---

## Phase Completion Gate

DONE when ALL of the following:
- T17, T18 status DONE
- `php artisan test` + `npx jest` + `tsc --noEmit` hijau
- Skenario end-to-end: draft → approve → execute (order & campaign) + audit + idempotency + fallback adapter lulus
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT
- Siap untuk `closeout.md`