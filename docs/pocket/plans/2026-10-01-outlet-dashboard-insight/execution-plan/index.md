# Outlet Dashboard & Insight — Execution Index

**Date:** 2026-10-01
**Spec:** docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
**Source Plan:** ../execution-plan.md
**source-sha256:** 8ff70202655dd1c696069ea612677e876ed6a885df63c2cd2b69db8e58053e3c
**Total Tasks:** 6
**Total Phases:** 1

---

## Execution Flow

```
T1,T2(PARALLEL)→T3,T4(PARALLEL)→T5→T6
```

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Backend — outlet-scoped OrderController index + OrderListFilters allowlist | Phase 1 | [T1-backend-outlet-scoped-ordercontroller-index-orderlistfilters-allowlist.md](tasks/T1-backend-outlet-scoped-ordercontroller-index-orderlistfilters-allowlist.md) | [prereq] |
| T2 | Shared helpers — status labels, period window, favorite aggregation | Phase 1 | [T2-shared-helpers-status-labels-period-window-favorite-aggregation.md](tasks/T2-shared-helpers-status-labels-period-window-favorite-aggregation.md) | [prereq] |
| T3 | Dummy parity — dashboardOutlet fixture | Phase 1 | [T3-dummy-parity-dashboardoutlet-fixture.md](tasks/T3-dummy-parity-dashboardoutlet-fixture.md) | [depends: T2] |
| T4 | Data layer — dashboard/api.ts outlet branch + login redirect | Phase 1 | [T4-data-layer-dashboard-api-ts-outlet-branch-login-redirect.md](tasks/T4-data-layer-dashboard-api-ts-outlet-branch-login-redirect.md) | [depends: T1, T2] |
| T5 | UI — OutletDashboard composition (all outlet sections + a11y) | Phase 1 | [T5-ui-outletdashboard-composition-all-outlet-sections-a11y.md](tasks/T5-ui-outletdashboard-composition-all-outlet-sections-a11y.md) | [depends: T3, T4] |
| T6 | Cross-cut integration — isolation, auth redirect, and empty-vs-error wiring | Phase 1 | [T6-cross-cut-integration-isolation-auth-redirect-and-empty-vs-error-wiring.md](tasks/T6-cross-cut-integration-isolation-auth-redirect-and-empty-vs-error-wiring.md) | [depends: T1, T4, T5] |
