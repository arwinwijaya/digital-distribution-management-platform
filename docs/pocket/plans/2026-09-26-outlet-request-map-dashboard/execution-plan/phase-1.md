# Outlet Request Map Dashboard — Shared geographic filter helper (Phase 1 of 3)

**Date:** 2026-09-26
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T4, T5}
**Unlocks next:** Phase 2

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T1:** Shared geographic filter helper [prereq] → [tasks/T1-shared-geographic-filter-helper.md](tasks/T1-shared-geographic-filter-helper.md)
- **T2:** Backend — GeographicAnalyticsService v2 payload + tightened coordinate rule [prereq] → [tasks/T2-backend-geographicanalyticsservice-v2-payload-tightened-coordinate-rule.md](tasks/T2-backend-geographicanalyticsservice-v2-payload-tightened-coordinate-rule.md)
- **T4:** Backend — OrderController additive filters [prereq] → [tasks/T4-backend-ordercontroller-additive-filters.md](tasks/T4-backend-ordercontroller-additive-filters.md)
- **T5:** API client — typed ApiError + fetchGeographicData propagation [prereq] → [tasks/T5-api-client-typed-apierror-fetchgeographicdata-propagation.md](tasks/T5-api-client-typed-apierror-fetchgeographicdata-propagation.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 2 ONLY after this gate passes.
