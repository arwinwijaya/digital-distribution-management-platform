# Phase 8 — Mobile Field Operations — Backend Field-Ops API (Phase 2 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 1 (data foundation)
**Contains tasks:** {T5, T6, T7, T8, T9}
**Unlocks next:** Phase 3, Phase 4

---

## Task List

Total: 5 tasks | Prerequisite phases must be complete before starting

- **T5:** `DriverRosterController` CRUD + requests + routes [depends: T2, T4] → [tasks/T5-driver-roster-controller-crud.md](tasks/T5-driver-roster-controller-crud.md)
- **T6:** Visit check-in/check-out endpoints + radius validation [depends: T1] → [tasks/T6-visit-checkin-checkout-endpoints.md](tasks/T6-visit-checkin-checkout-endpoints.md)
- **T7:** Delivery location ping endpoints (driver POST + admin track GET) [depends: T1] → [tasks/T7-delivery-location-ping-endpoints.md](tasks/T7-delivery-location-ping-endpoints.md)
- **T8:** Proof-of-delivery upload endpoint (foto + tanda tangan) [depends: T1] → [tasks/T8-proof-of-delivery-upload-endpoint.md](tasks/T8-proof-of-delivery-upload-endpoint.md)
- **T9:** Wire `RoutingService` ke `DeliveryController@store` + route admin [depends: T3, T5] → [tasks/T9-wire-routing-into-delivery-store.md](tasks/T9-wire-routing-into-delivery-store.md)

T6, T7, T8 boleh paralel; T5 memerlukan T2+T4; T9 memerlukan T3+T5.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `php artisan test --filter=FieldOps` hijau (semua test Feature baru)
- Setiap route baru terlindungi `auth:api` + `rbac:*` dan diuji 401/403
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 3/4 ONLY after this gate passes.
