# Siklus Pemesanan End-to-End (Hybrid API + Thin UI) — Scaffold Playwright config + install (Phase 1 of 2)

**Date:** 2026-09-30
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T3, T5}
**Unlocks next:** Phase 2

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T1:** Scaffold Playwright config + install [prereq] [no-tdd — structural task] → [tasks/T1-scaffold-playwright-config-install.md](tasks/T1-scaffold-playwright-config-install.md)
- **T2:** Seed isolation script [prereq] [no-tdd — structural task] → [tasks/T2-seed-isolation-script.md](tasks/T2-seed-isolation-script.md)
- **T3:** Add data-testid contract to critical pages [prereq] → [tasks/T3-add-data-testid-contract-to-critical-pages.md](tasks/T3-add-data-testid-contract-to-critical-pages.md)
- **T5:** API negative checks — extend order-flow.test.js [prereq] → [tasks/T5-api-negative-checks-extend-order-flow-test-js.md](tasks/T5-api-negative-checks-extend-order-flow-test-js.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 2 ONLY after this gate passes.
