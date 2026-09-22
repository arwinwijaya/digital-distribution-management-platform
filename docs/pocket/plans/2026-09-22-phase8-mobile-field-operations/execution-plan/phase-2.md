# Phase 8 — Mobile Field Operations — Backend Field-Ops API (Phase 2 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 1 (data foundation)
**Contains tasks:** {T5, T6, T7, T8, T9}
**Unlocks next:** Phase 3, Phase 4
**Status:** DONE (2026-09-22)

---

## Status

Phase 2 selesai. Semua task DONE dengan commit:

- **T5** `121e34a` — `DriverRosterController` CRUD (`GET/POST/PATCH/DELETE /admin/drivers`, rbac:driver_roster).
- **T6** `610af38` — `POST /sales/visits/{id}/check-in|check-out` + radius (`GeoService`, `config('orders.visit_radius_m')`).
- **T7** `54829b7` — `POST /deliveries/{id}/location` + `GET /admin/deliveries/{id}/track` (`DeliveryLocationPing`).
- **T8** `212fc95` — `POST /deliveries/{id}/proof` + `config/filesystems.php` (disk `local` default + `public`).
- **T9** `2f37653` — `GET /admin/deliveries/{id}/route` (route_data plan dari T3 tersimpan di `store()`).

Gate verification:
- `php artisan test` → **592 passed / 7 skipped** (3701 assertions).
- Test baru Phase 2: `DriverRosterTest` (11), `SalesVisitCheckinTest` (8), `DeliveryTrackingTest` (12),
  `DeliveryProofUploadTest` (9), `DeliveryRouteTest` (7) = **47 kasus**.
- Semua route baru: 401/403 diuji; `/admin/deliveries/{id}/track` & `/route` memakai
  `rbac:field_ops:read` + assert admin/owner eksplisit.
- Catatan deviasi: `POST /deliveries/{id}/proof` memakai `rbac:delivery:edit` (driver `edit`)
  alih-alih `field_ops:edit` (driver hanya `read`), lihat `execution-plan/index.md` Progress Log.

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
