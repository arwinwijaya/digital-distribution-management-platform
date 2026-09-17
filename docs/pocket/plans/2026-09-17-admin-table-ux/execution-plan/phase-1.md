# Admin Table Readability (Paging, Sort Terbaru, Ringkasan, Kepadatan) — Backend `ListQuery` sort/total helper (Phase 1 of 4)

**Date:** 2026-09-17
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T3, T5, T6, T7}
**Unlocks next:** Phase 2

---

## Task List

Total: 6 tasks | Prerequisite phases must be complete before starting

- **T1:** Backend `ListQuery` sort/total helper [prereq] → [tasks/T1-backend-listquery-sort-total-helper.md](tasks/T1-backend-listquery-sort-total-helper.md)
- **T2:** Frontend `admin-table.ts` helper [prereq] → [tasks/T2-frontend-admin-table-ts-helper.md](tasks/T2-frontend-admin-table-ts-helper.md)
- **T3:** `Table.tsx` — density + sortable header [depends: T2] → [tasks/T3-table-tsx-density-sortable-header.md](tasks/T3-table-tsx-density-sortable-header.md)
- **T5:** `AdminOutletController@index` — sort + total + summary + normalisasi meta [depends: T1] → [tasks/T5-adminoutletcontroller-index-sort-total-summary-normalisasi-meta.md](tasks/T5-adminoutletcontroller-index-sort-total-summary-normalisasi-meta.md)
- **T6:** `UserRoleController@index` — offset cursor + sort + total [depends: T1] → [tasks/T6-userrolecontroller-index-offset-cursor-sort-total.md](tasks/T6-userrolecontroller-index-offset-cursor-sort-total.md)
- **T7:** `PromotionController@index` — id-cursor → offset + sort + total + summary [depends: T1] → [tasks/T7-promotioncontroller-index-id-cursor-offset-sort-total-summary.md](tasks/T7-promotioncontroller-index-id-cursor-offset-sort-total-summary.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 2 ONLY after this gate passes.
