# Phase 8 — Mobile Field Operations — Dummy Parity + RBAC Menu Wiring (Phase 5 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 4 (frontend field surfaces)
**Contains tasks:** {T17}
**Unlocks next:** Phase 6

---

## Task List

Total: 1 task

- **T17:** Dummy fixtures + NavItem + RBAC page wiring [depends: T13, T14, T15, T16] → [tasks/T17-dummy-fixtures-navitem-rbac-wiring.md](tasks/T17-dummy-fixtures-navitem-rbac-wiring.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Task status DONE
- Dummy mode ON → seluruh halaman baru zero network, tanpa state kosong
- Menu `driver_roster` + `field_ops` tampil sesuai role; `platform_owner` setara admin
- `npx jest` + `npx tsc --noEmit` hijau
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 6 ONLY after this gate passes.
