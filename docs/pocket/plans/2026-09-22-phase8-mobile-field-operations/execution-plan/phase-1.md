# Phase 8 — Mobile Field Operations — Data Foundation (Phase 1 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T3, T4}
**Unlocks next:** Phase 2

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T1:** Migrasi & model field-ops (driver_profiles, visit GPS, PoD meta, location pings) [prereq] → [tasks/T1-migrasi-model-field-ops.md](tasks/T1-migrasi-model-field-ops.md)
- **T2:** `DriverProfile` model + relasi + factory + seeder [depends: T1] → [tasks/T2-driver-profile-model-relations-factory-seeder.md](tasks/T2-driver-profile-model-relations-factory-seeder.md)
- **T3:** `RoutingService` nearest-neighbor bounded deterministik [depends: T1] → [tasks/T3-routing-service-nearest-neighbor-bounded.md](tasks/T3-routing-service-nearest-neighbor-bounded.md)
- **T4:** RBAC catalog + seeder default (`driver_roster`, `field_ops`) [depends: T1] → [tasks/T4-rbac-catalog-seeder-driver-roster-field-ops.md](tasks/T4-rbac-catalog-seeder-driver-roster-field-ops.md)

T2, T3, T4 boleh paralel setelah T1 selesai.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `php artisan test` hijau untuk test baru (`FieldOpsMigrationTest`, `DriverProfileTest`, `RoutingServiceTest`, `RbacFieldOpsCatalogTest`)
- `php artisan migrate` + `migrate:rollback --step=4` berjalan bersih di SQLite untuk 4 migrasi Phase 8
  (rollback penuh tetap gagal di migrasi lama `2026_09_14_000025` — pre-existing, di luar scope)
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

## Status: DONE (2026-09-22)

- T1 `6696c42` · T2 `24762db` · T3 `0d111d8` · T4 `e5a8ebe`
- Backend suite 545 passed / 7 skipped; web suite 487 passed.
- Migrasi + rollback `--step=4` bersih (tabel/kolom hilang tanpa error).

Hand off to Phase 2 ONLY after this gate passes.
