# Outlet Request Map Dashboard — Execution Index

**Date:** 2026-09-26
**Spec:** docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
**Source Plan:** ../execution-plan.md
**source-sha256:** 02ac54d0f0646e954802259bf6dc9b6e7b593845e78fc9d486552268e2684f0a
**Total Tasks:** 11
**Total Phases:** 3

---

## Execution Flow

```
T1,T2,T4,T5(PARALLEL)→T3,T6,T7(PARALLEL)→T8→T9→T10,T11(PARALLEL)
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Shared geographic filter helper (T1, T2, T4, T5)
- **Phase 2:** [phase-2.md](phase-2.md) — Backend — GeographicAnalyticsController passthrough + meta (T3, T6, T7)
- **Phase 3:** [phase-3.md](phase-3.md) — Frontend — OutletDrawer (snapshot-only, freeze banner, a11y) (T8, T9, T10, T11)

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Shared geographic filter helper | Phase 1 | [T1-shared-geographic-filter-helper.md](tasks/T1-shared-geographic-filter-helper.md) | [prereq] |
| T2 | Backend — GeographicAnalyticsService v2 payload + tightened coordinate rule | Phase 1 | [T2-backend-geographicanalyticsservice-v2-payload-tightened-coordinate-rule.md](tasks/T2-backend-geographicanalyticsservice-v2-payload-tightened-coordinate-rule.md) | [prereq] |
| T4 | Backend — OrderController additive filters | Phase 1 | [T4-backend-ordercontroller-additive-filters.md](tasks/T4-backend-ordercontroller-additive-filters.md) | [prereq] |
| T5 | API client — typed ApiError + fetchGeographicData propagation | Phase 1 | [T5-api-client-typed-apierror-fetchgeographicdata-propagation.md](tasks/T5-api-client-typed-apierror-fetchgeographicdata-propagation.md) | [prereq] |
| T3 | Backend — GeographicAnalyticsController passthrough + meta | Phase 2 | [T3-backend-geographicanalyticscontroller-passthrough-meta.md](tasks/T3-backend-geographicanalyticscontroller-passthrough-meta.md) | [depends: T2] |
| T6 | Dummy — buildGeographic v2 + deterministic fixture parity | Phase 2 | [T6-dummy-buildgeographic-v2-deterministic-fixture-parity.md](tasks/T6-dummy-buildgeographic-v2-deterministic-fixture-parity.md) | [depends: T1] |
| T7 | Frontend — GeoMap marker-layer diff, fitBounds scope, coordinate parity, a11y list | Phase 2 | [T7-frontend-geomap-marker-layer-diff-fitbounds-scope-coordinate-parity-a11y-list.md](tasks/T7-frontend-geomap-marker-layer-diff-fitbounds-scope-coordinate-parity-a11y-list.md) | [depends: T1, T5] |
| T8 | Frontend — OutletDrawer (snapshot-only, freeze banner, a11y) | Phase 3 | [T8-frontend-outletdrawer-snapshot-only-freeze-banner-a11y.md](tasks/T8-frontend-outletdrawer-snapshot-only-freeze-banner-a11y.md) | [depends: T1, T5, T7] |
| T9 | Frontend — Page filter chips, empty states, error handling, drawer wiring | Phase 3 | [T9-frontend-page-filter-chips-empty-states-error-handling-drawer-wiring.md](tasks/T9-frontend-page-filter-chips-empty-states-error-handling-drawer-wiring.md) | [depends: T1, T5, T7, T8] |
| T10 | Integration — Page + GeoMap + Drawer filter sync | Phase 3 | [T10-integration-page-geomap-drawer-filter-sync.md](tasks/T10-integration-page-geomap-drawer-filter-sync.md) | [depends: T9] |
| T11 | Performance — ScaleFixtureSeeder baseline, <100ms recompute, <500KB response + truncation cap + frontend warning | Phase 3 | [T11-performance-scalefixtureseeder-baseline-100ms-recompute-500kb-response-truncation-cap-frontend-warning.md](tasks/T11-performance-scalefixtureseeder-baseline-100ms-recompute-500kb-response-truncation-cap-frontend-warning.md) | [depends: T3, T9] |
