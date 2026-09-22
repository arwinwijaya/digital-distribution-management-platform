# Phase 8 — Mobile Field Operations — Execution Index

**Date:** 2026-09-22
**Spec:** docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md
**Source Plan:** ../execution-plan.md
**source-sha256:** f57243b2d2544d5f2638671c1ec7381fe36ba2a5d2201a9b9b6b2c8dc0ae83a3
**Total Tasks:** 18
**Total Phases:** 6

---

## Execution Flow

```
T1 → T2,T3,T4 (PARALLEL)
T2,T4 → T5
T1 → T6,T7,T8 (PARALLEL)
T3,T5 → T9
T10 → T11 → T12
T5 → T13 | T6 → T14 | T8 → T15 | T7 → T16 (PARALLEL)
T13,T14,T15,T16 → T17
T12,T17 → T18
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Data foundation (migrasi, model, routing, RBAC catalog) (T1, T2, T3, T4) — **DONE**
- **Phase 2:** [phase-2.md](phase-2.md) — Backend field-ops API (roster, check-in, tracking, PoD) (T5, T6, T7, T8, T9) — **DONE**
- **Phase 3:** [phase-3.md](phase-3.md) — PWA offline shell + offline order queue (T10, T11, T12)
- **Phase 4:** [phase-4.md](phase-4.md) — Frontend field surfaces (roster, check-in, PoD capture, tracking) (T13, T14, T15, T16)
- **Phase 5:** [phase-5.md](phase-5.md) — Dummy parity + RBAC menu wiring (T17)
- **Phase 6:** [phase-6.md](phase-6.md) — Cross-unit integration verification (T18)

---

## Progress Log

- **2026-09-22 — Phase 1 DONE.** T1 `6696c42`, T2 `24762db`, T3 `0d111d8`, T4 `e5a8ebe`.
  - Backend suite: 545 passed / 7 skipped. Web suite: 487 passed (unchanged; dummy RBAC parity deferred to T17).
  - Migration rollback verified clean for the 4 new migrations (`--step=4`). Note: a full
    `migrate:rollback` still fails on the pre-existing `2026_09_14_000025_add_territory_id_to_outlets_table`
    (SQLite cannot drop an indexed column) — this failure reproduces on baseline `18f0413` and is out of scope.
  - `pint --test` is not wired into any CI/Makefile in this repo and fails on baseline HEAD, so it is not used as a gate.
- **2026-09-22 — Phase 2 DONE.** T5 `121e34a`, T6 `610af38`, T7 `54829b7`, T8 `212fc95`, T9 `2f37653`.
  - Backend suite: 592 passed / 7 skipped (3701 assertions). New Phase 2 tests: 47 cases
    (`DriverRosterTest` 11, `SalesVisitCheckinTest` 8, `DeliveryTrackingTest` 12, `DeliveryProofUploadTest` 9, `DeliveryRouteTest` 7).
  - Deviation: `POST /deliveries/{id}/proof` is gated on `rbac:delivery:edit` (not `field_ops:edit`)
    because the default matrix grants drivers only `read` on `field_ops`; `delivery:edit` is the
    driver-writable menu. Admin-only reads (`/admin/deliveries/{id}/track`, `/route`) keep
    `rbac:field_ops:read` plus an explicit admin/owner assertion.
  - New shared helpers: `App\Services\GeoService` (haversine), `config/filesystems.php`
    (`local` default + `public`), `DeliveryLocationPing` model + `Delivery::locationPings()`.
  - Web suite unchanged (backend-only phase).

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Migrasi & model field-ops (driver_profiles, visit GPS, PoD meta, location pings) | Phase 1 | [T1-migrasi-model-field-ops.md](tasks/T1-migrasi-model-field-ops.md) | [prereq] |
| T2 | `DriverProfile` model + relasi + factory + seeder | Phase 1 | [T2-driver-profile-model-relations-factory-seeder.md](tasks/T2-driver-profile-model-relations-factory-seeder.md) | [depends: T1] |
| T3 | `RoutingService` nearest-neighbor bounded deterministik | Phase 1 | [T3-routing-service-nearest-neighbor-bounded.md](tasks/T3-routing-service-nearest-neighbor-bounded.md) | [depends: T1] |
| T4 | RBAC catalog + seeder default (`driver_roster`, `field_ops`) | Phase 1 | [T4-rbac-catalog-seeder-driver-roster-field-ops.md](tasks/T4-rbac-catalog-seeder-driver-roster-field-ops.md) | [depends: T1] |
| T5 | `DriverRosterController` CRUD + requests + routes | Phase 2 | [T5-driver-roster-controller-crud.md](tasks/T5-driver-roster-controller-crud.md) | [depends: T2, T4] |
| T6 | Visit check-in/check-out endpoints + radius validation | Phase 2 | [T6-visit-checkin-checkout-endpoints.md](tasks/T6-visit-checkin-checkout-endpoints.md) | [depends: T1] |
| T7 | Delivery location ping endpoints (driver POST + admin track GET) | Phase 2 | [T7-delivery-location-ping-endpoints.md](tasks/T7-delivery-location-ping-endpoints.md) | [depends: T1] |
| T8 | Proof-of-delivery upload endpoint (foto + tanda tangan) | Phase 2 | [T8-proof-of-delivery-upload-endpoint.md](tasks/T8-proof-of-delivery-upload-endpoint.md) | [depends: T1] |
| T9 | Wire `RoutingService` ke `DeliveryController@store` + route admin | Phase 2 | [T9-wire-routing-into-delivery-store.md](tasks/T9-wire-routing-into-delivery-store.md) | [depends: T3, T5] |
| T10 | Service worker + registrasi + halaman offline + indikator Topbar | Phase 3 | [T10-service-worker-offline-shell-topbar.md](tasks/T10-service-worker-offline-shell-topbar.md) | [prereq] |
| T11 | Offline order queue (IndexedDB wrapper + flush) | Phase 3 | [T11-offline-order-queue-indexeddb-flush.md](tasks/T11-offline-order-queue-indexeddb-flush.md) | [depends: T10] |
| T12 | Integrasi antrean offline ke form order + status UI | Phase 3 | [T12-offline-queue-order-form-integration.md](tasks/T12-offline-queue-order-form-integration.md) | [depends: T11] |
| T13 | Halaman admin roster driver (`/admin/drivers`) + api + kontrak tabel | Phase 4 | [T13-admin-driver-roster-page.md](tasks/T13-admin-driver-roster-page.md) | [depends: T5] |
| T14 | UI check-in/out kunjungan sales + geolokasi | Phase 4 | [T14-sales-visit-checkin-ui.md](tasks/T14-sales-visit-checkin-ui.md) | [depends: T6] |
| T15 | UI capture PoD driver (kamera + canvas tanda tangan) | Phase 4 | [T15-driver-pod-capture-ui.md](tasks/T15-driver-pod-capture-ui.md) | [depends: T8] |
| T16 | Halaman live tracking admin (`GeoMap` + polling) | Phase 4 | [T16-admin-live-tracking-page.md](tasks/T16-admin-live-tracking-page.md) | [depends: T7] |
| T17 | Dummy fixtures + NavItem + RBAC page wiring | Phase 5 | [T17-dummy-fixtures-navitem-rbac-wiring.md](tasks/T17-dummy-fixtures-navitem-rbac-wiring.md) | [depends: T13, T14, T15, T16] |
| T18 | Integrasi lintas unit + verifikasi suite penuh | Phase 6 | [T18-cross-unit-integration-verification.md](tasks/T18-cross-unit-integration-verification.md) | [depends: T12, T17] [test-risk] |

---

## Phase Completion Gate

Setiap phase DONE bila SELURUH syarat berikut terpenuhi:
- Semua task di phase berstatus DONE
- Semua test pass (`php artisan test` + `npx jest` + `tsc --noEmit`)
- Semua commit dibuat dengan format konvensional (`feat(api):`/`feat(web):`/`test(...):`)
- Tidak ada task berstatus BLOCKED atau NEEDS_CONTEXT

Hand off ke phase berikutnya HANYA setelah gate ini lulus.
