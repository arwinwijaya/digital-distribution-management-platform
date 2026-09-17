# Admin Table Readability (Paging, Sort Terbaru, Ringkasan, Kepadatan) — Execution Index

**Date:** 2026-09-17
**Spec:** docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
**Source Plan:** ../execution-plan.md
**source-sha256:** a12d3c44558e863d8c9639e4da451c349560d3325b4a0f7d29ef8f2f8bba203c
**Total Tasks:** 17
**Total Phases:** 4

---

## Execution Flow

```
T1,T2(PARALLEL)→T3,T5,T6,T7,T8,T9,T10(PARALLEL)→T4→T11,T12,T13,T14,T15,T16(PARALLEL)→T17
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Backend `ListQuery` sort/total helper (T1, T2, T3, T5, T6, T7)
- **Phase 2:** [phase-2.md](phase-2.md) — `OrderController@index` — offset cursor + sort + total (T8, T9, T10)
- **Phase 3:** [phase-3.md](phase-3.md) — Table controls (Pagination/Summary/Density toggle + hook) (T4, T11, T12)
- **Phase 4:** [phase-4.md](phase-4.md) — Users page — sort/paging/summary/timestamps/density (T13, T14, T15, T16, T17)

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Backend `ListQuery` sort/total helper | Phase 1 | [T1-backend-listquery-sort-total-helper.md](tasks/T1-backend-listquery-sort-total-helper.md) | [prereq] |
| T2 | Frontend `admin-table.ts` helper | Phase 1 | [T2-frontend-admin-table-ts-helper.md](tasks/T2-frontend-admin-table-ts-helper.md) | [prereq] |
| T3 | `Table.tsx` — density + sortable header | Phase 1 | [T3-table-tsx-density-sortable-header.md](tasks/T3-table-tsx-density-sortable-header.md) | [depends: T2] |
| T5 | `AdminOutletController@index` — sort + total + summary + normalisasi meta | Phase 1 | [T5-adminoutletcontroller-index-sort-total-summary-normalisasi-meta.md](tasks/T5-adminoutletcontroller-index-sort-total-summary-normalisasi-meta.md) | [depends: T1] |
| T6 | `UserRoleController@index` — offset cursor + sort + total | Phase 1 | [T6-userrolecontroller-index-offset-cursor-sort-total.md](tasks/T6-userrolecontroller-index-offset-cursor-sort-total.md) | [depends: T1] |
| T7 | `PromotionController@index` — id-cursor → offset + sort + total + summary | Phase 1 | [T7-promotioncontroller-index-id-cursor-offset-sort-total-summary.md](tasks/T7-promotioncontroller-index-id-cursor-offset-sort-total-summary.md) | [depends: T1] |
| T8 | `OrderController@index` — offset cursor + sort + total | Phase 2 | [T8-ordercontroller-index-offset-cursor-sort-total.md](tasks/T8-ordercontroller-index-offset-cursor-sort-total.md) | [depends: T1] |
| T9 | `ProductController@index` — additive sort/cursor/total (public endpoint) | Phase 2 | [T9-productcontroller-index-additive-sort-cursor-total-public-endpoint.md](tasks/T9-productcontroller-index-additive-sort-cursor-total-public-endpoint.md) | [depends: T1] |
| T10 | `SalesPerformanceController@adminPerformance` — id-cursor → offset + sort + total | Phase 2 | [T10-salesperformancecontroller-adminperformance-id-cursor-offset-sort-total.md](tasks/T10-salesperformancecontroller-adminperformance-id-cursor-offset-sort-total.md) | [depends: T1] |
| T4 | Table controls (Pagination/Summary/Density toggle + hook) | Phase 3 | [T4-table-controls-pagination-summary-density-toggle-hook.md](tasks/T4-table-controls-pagination-summary-density-toggle-hook.md) | [depends: T2, T3] |
| T11 | Outlets page — sort/paging/summary/timestamps/density | Phase 3 | [T11-outlets-page-sort-paging-summary-timestamps-density.md](tasks/T11-outlets-page-sort-paging-summary-timestamps-density.md) | [depends: T4, T5] |
| T12 | Products page — sort/paging/summary/timestamps/density | Phase 3 | [T12-products-page-sort-paging-summary-timestamps-density.md](tasks/T12-products-page-sort-paging-summary-timestamps-density.md) | [depends: T4, T9] |
| T13 | Users page — sort/paging/summary/timestamps/density | Phase 4 | [T13-users-page-sort-paging-summary-timestamps-density.md](tasks/T13-users-page-sort-paging-summary-timestamps-density.md) | [depends: T4, T6] |
| T14 | Promotions page — sort/paging/summary/timestamps/density | Phase 4 | [T14-promotions-page-sort-paging-summary-timestamps-density.md](tasks/T14-promotions-page-sort-paging-summary-timestamps-density.md) | [depends: T4, T7] |
| T15 | Sales-performance page — sort/paging/summary/density | Phase 4 | [T15-sales-performance-page-sort-paging-summary-density.md](tasks/T15-sales-performance-page-sort-paging-summary-density.md) | [depends: T4, T10] |
| T16 | Orders page — sort/paging/summary/timestamps/density | Phase 4 | [T16-orders-page-sort-paging-summary-timestamps-density.md](tasks/T16-orders-page-sort-paging-summary-timestamps-density.md) | [depends: T4, T8] |
| T17 | Integration — admin table contract across pages + dummy parity | Phase 4 | [T17-integration-admin-table-contract-across-pages-dummy-parity.md](tasks/T17-integration-admin-table-contract-across-pages-dummy-parity.md) | [depends: T11, T12, T13, T14, T15, T16] [test-risk] |
