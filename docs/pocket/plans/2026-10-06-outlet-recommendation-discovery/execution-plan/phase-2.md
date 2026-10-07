# Outlet Recommendation Discovery — Implement AIController::storeDiscoveryEvent() + tracking idempotency (Phase 2 of 2)

**Date:** 2026-10-06
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 1 must be COMPLETE — all tests green, all commits created
**Contains tasks:** {T4, T6, T7, T8, T9}
**Unlocks next:** All phases complete — proceed to final validation

---

## Task List

Total: 5 tasks | Prerequisite phases must be complete before starting

- **T4:** Implement AIController::storeDiscoveryEvent() + tracking idempotency [depends: T3] → [tasks/T4-implement-aicontroller-storediscoveryevent-tracking-idempotency.md](tasks/T4-implement-aicontroller-storediscoveryevent-tracking-idempotency.md)
- **T6:** Implement useOutletDiscovery hook logic [depends: T5] → [tasks/T6-implement-useoutletdiscovery-hook-logic.md](tasks/T6-implement-useoutletdiscovery-hook-logic.md)
- **T7:** Implement DiscoverySection component UI [depends: T5] → [tasks/T7-implement-discoverysection-component-ui.md](tasks/T7-implement-discoverysection-component-ui.md)
- **T8:** Integrate DiscoverySection into OrderForm [depends: T6, T7] → [tasks/T8-integrate-discoverysection-into-orderform.md](tasks/T8-integrate-discoverysection-into-orderform.md)
- **T9:** Verify discovery order flow end-to-end (Playwright E2E) [depends: T8] [test-risk] → [tasks/T9-verify-discovery-order-flow-end-to-end-playwright-e2e.md](tasks/T9-verify-discovery-order-flow-end-to-end-playwright-e2e.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to (none — all phases complete) ONLY after this gate passes.
