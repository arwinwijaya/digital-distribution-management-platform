# Dummy Mode JABODETABEK — Foundation + Data (Phase 1 of 3)

**Date:** 2026-02-14
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T3, T4, T5, T6, T7}
**Unlocks next:** Phase 2

---

## Task List

Total: 7 tasks | Prerequisite phases must be complete before starting

### Task 1: Dummy module scaffold + seeded RNG + date utils + JABODETABEK seed constants [prereq]

→ Task file: [tasks/T1-dummy-module-scaffold-seeded-rng-date-utils-jabode.md](tasks/T1-dummy-module-scaffold-seeded-rng-date-utils-jabode.md)

### Task 2: Zustand dummy store (isDummy, entities, role, toggle, localStorage persist) [depends: T1]

→ Task file: [tasks/T2-zustand-dummy-store-isdummy-entities-role-toggle-l.md](tasks/T2-zustand-dummy-store-isdummy-entities-role-toggle-l.md)

### Task 3: Role persistence at login (ddp_role) + Sidebar offline role read [depends: T2]

→ Task file: [tasks/T3-role-persistence-at-login-ddp-role-sidebar-offline.md](tasks/T3-role-persistence-at-login-ddp-role-sidebar-offline.md)

### Task 4: Topbar toggle UI [depends: T2]

→ Task file: [tasks/T4-topbar-toggle-ui.md](tasks/T4-topbar-toggle-ui.md)

### Task 5: DummyFactory — master data (territories/outlets/products/suppliers) [depends: T1] [parallel: T2]

→ Task file: [tasks/T5-dummyfactory-master-data-territories-outlets-produ.md](tasks/T5-dummyfactory-master-data-territories-outlets-produ.md)

### Task 6: DummyFactory — transactions + analytics/DI/AI aggregates [depends: T5]

→ Task file: [tasks/T6-dummyfactory-transactions-analytics-di-ai-aggregat.md](tasks/T6-dummyfactory-transactions-analytics-di-ai-aggregat.md)

### Task 7: Read-guard helper + commit-guard helper [depends: T1] [parallel: T2]

→ Task file: [tasks/T7-read-guard-helper-commit-guard-helper.md](tasks/T7-read-guard-helper-commit-guard-helper.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 2 ONLY after this gate passes.
