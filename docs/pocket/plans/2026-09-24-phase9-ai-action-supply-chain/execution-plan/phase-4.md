# Phase 4 — Frontend Action Surfaces

**Date:** 2026-09-24
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 2 DONE (T7, T8), Phase 3 DONE (T10, T12)
**Contains tasks:** {T13, T14, T15}
**Unlocks next:** Phase 5

---

## Task List

Total: 3 tasks | Prerequisite phases must be complete before starting

- **T13:** Halaman inbox approval rekomendasi [depends: T7, T8] → [tasks/T13-approval-inbox-page.md](tasks/T13-approval-inbox-page.md)
- **T14:** Halaman replenishment (generate/approve/execute) [depends: T10] → [tasks/T14-replenishment-page.md](tasks/T14-replenishment-page.md)
- **T15:** Dashboard eksperimen + revenue lift [depends: T12] → [tasks/T15-experiment-dashboard.md](tasks/T15-experiment-dashboard.md)

T13, T14, T15 paralel (masing-masing bergantung pada backend yang berbeda).

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `npx jest` + `tsc --noEmit` hijau di `apps/web`
- Test akses RBAC + dummy parity lulus
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT