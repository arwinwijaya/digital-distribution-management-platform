# Admin Table Readability (Paging, Sort Terbaru, Ringkasan, Kepadatan) — `OrderController@index` — offset cursor + sort + total (Phase 2 of 4)

**Date:** 2026-09-17
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 1 must be COMPLETE — all tests green, all commits created
**Contains tasks:** {T8, T9, T10}
**Unlocks next:** Phase 3

---

## Task List

Total: 3 tasks | Prerequisite phases must be complete before starting

- **T8:** `OrderController@index` — offset cursor + sort + total [depends: T1] → [tasks/T8-ordercontroller-index-offset-cursor-sort-total.md](tasks/T8-ordercontroller-index-offset-cursor-sort-total.md)
- **T9:** `ProductController@index` — additive sort/cursor/total (public endpoint) [depends: T1] → [tasks/T9-productcontroller-index-additive-sort-cursor-total-public-endpoint.md](tasks/T9-productcontroller-index-additive-sort-cursor-total-public-endpoint.md)
- **T10:** `SalesPerformanceController@adminPerformance` — id-cursor → offset + sort + total [depends: T1] → [tasks/T10-salesperformancecontroller-adminperformance-id-cursor-offset-sort-total.md](tasks/T10-salesperformancecontroller-adminperformance-id-cursor-offset-sort-total.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 3 ONLY after this gate passes.
