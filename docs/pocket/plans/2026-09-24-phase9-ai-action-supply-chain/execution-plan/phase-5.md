# Phase 5 — Parity Dummy + RBAC Wiring

**Date:** 2026-09-24
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 4 DONE
**Contains tasks:** {T16}
**Unlocks next:** Phase 6

---

## Task List

Total: 1 task

- **T16:** Fixture dummy + `NavItem` + wiring RBAC [depends: T13, T14, T15] → [tasks/T16-dummy-navitem-rbac-wiring.md](tasks/T16-dummy-navitem-rbac-wiring.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- T16 status DONE
- `npx jest` + `tsc --noEmit` hijau (dummy tests)
- Dummy mode ON: semua halaman baru zero network, data dummy tampil
- Menu baru `ai_actions` / `supply_chain` tampil/hilang sesuai role
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT