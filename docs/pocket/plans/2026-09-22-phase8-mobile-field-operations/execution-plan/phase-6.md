# Phase 8 — Mobile Field Operations — Cross-Unit Integration Verification (Phase 6 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 3 (PWA) + Phase 5 (dummy/RBAC wiring)
**Contains tasks:** {T18}
**Unlocks next:** none (final phase)

---

## Task List

Total: 1 task

- **T18:** Integrasi lintas unit + verifikasi suite penuh [depends: T12, T17] [test-risk] → [tasks/T18-cross-unit-integration-verification.md](tasks/T18-cross-unit-integration-verification.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Task status DONE
- `php artisan test` → 0 failed (skips pgsql-only boleh)
- `npx jest` → 0 failed
- `npx tsc --noEmit` → clean
- Skenario lintas unit (offline order → flush → tracking → PoD) terverifikasi
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT
