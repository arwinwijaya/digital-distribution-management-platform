# EXECUTION PLAN — Phase 8 Mobile Field Operations (PWA Offline + GPS Check-in + Live Tracking + PoD)

**Date:** 2026-09-22
**Spec:** docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md
**Status:** DONE (2026-09-22)
**Closeout:** [closeout.md](closeout.md)
**Total tasks:** 18

---

## Plan Summary

- Total tasks: 18
- Phases: 6
- Phase 1: Data foundation (T1–T4)
- Phase 2: Backend field-ops API (T5–T9)
- Phase 3: PWA offline shell (T10–T12)
- Phase 4: Frontend field surfaces (T13–T16)
- Phase 5: Dummy parity + RBAC menu wiring (T17)
- Phase 6: Cross-unit integration verification (T18)

---

## Execution Overview

### Recommended Order
```
T1 → T2, T3, T4 (parallel)
T2 + T4 → T5
T1 → T6, T7, T8 (parallel)
T3 + T5 → T9
T10 → T11 → T12
T5 → T13
T6 → T14
T8 → T15
T7 → T16
T13 + T14 + T15 + T16 → T17
T12 + T17 → T18
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism/sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1 | (start) |
| Group B | T2, T3, T4 | T1 completes |
| Group C | T6, T7, T8 | T1 completes |
| Group D | T5 | T2 + T4 complete |
| Group E | T9 | T3 + T5 complete |
| Group F | T10 | (start, frontend-only) |
| Group G | T11 | T10 completes |
| Group H | T12 | T11 completes |
| Group I | T13, T14, T15, T16 | T5/T6/T8/T7 respectively |
| Group J | T17 | T13–T16 complete |
| Group K | T18 | T12 + T17 complete |

### Constraints Reminder
**Architecture:**
- Boleh disentuh: migrasi additive baru; `app/Models/{DriverProfile,User}.php`; `app/Services/RoutingService.php`; `app/Http/Controllers/{DriverRoster,Sales,Delivery}Controller.php`; `app/Http/Requests/*`; `routes/api.php`; `config/{orders,filesystems}.php`; `app/Models/MenuDefinition.php` + seeder RBAC; `apps/web/src/{app,components,lib,dummy,hooks}/*`.
- **TIDAK boleh** disentuh: pipeline `Order`/`Invoice`/`Payment` (kecuali wiring `route_data` di `DeliveryController@store`), `Auth`/JWT, kolom lama `sales_visits`/`deliveries`.
- Semua migrasi **additive + reversible**, portabel SQLite (test) & PostgreSQL (runtime). Tidak ada tipe Postgres-only.
- Semua route baru di belakang `auth:api` + `rbac:<menu>:<level>`.
- RBAC menu baru **wajib** terdaftar di `MenuDefinition::CATALOG` (single source of truth) + seeder default idempotent.
- Dummy mode ON = zero network; setiap modul api baru wajib punya guard `withDummyRead` + fixture deterministik.
- `RoutingService::plan()` tetap deterministik, tanpa dependency solver eksternal, `MAX_STOPS` bounded.
- PoD disimpan via disk abstraction (`Storage::disk(config('filesystems.default'))`); **tidak** hardcode S3.
- Service worker **tidak** meng-cache request API/POST; hanya app shell/statis + fallback `/offline`.
- Fitur PWA dapat dimatikan via `NEXT_PUBLIC_PWA_ENABLED=false` (rollback path).

**Out-of-scope (no task may touch):** Python ML service, payment gateway, multi-tenant, dynamic pricing, warehouse/processing workflow, WebSocket/push notification, route optimization solver lanjutan, S3/object storage produksi.

**Assumptions at risk:**
- Radius check-in default 200 m (config `orders.visit_radius_m`).
- `config/filesystems.php` belum ada di repo → T8 membuatnya.
- Titik awal rute = stop pertama by `id` (tidak ada tabel gudang).
- `POST /orders` sudah idempotent (`idempotency_payload_hash`) → antrean offline hanya replay.
- IndexedDB tersedia; fallback localStorage bila absen.

**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

**Commit message convention (conventional commits):**
- T1 → `feat(api): add field-ops schema (driver profiles, visit GPS, PoD meta, location pings)`
- T2 → `feat(api): add DriverProfile model, relations, factory, seeder`
- T3 → `feat(api): implement bounded deterministic RoutingService`
- T4 → `feat(api): add driver_roster and field_ops RBAC menus + defaults`
- T5 → `feat(api): add driver roster CRUD endpoints`
- T6 → `feat(api): add sales visit check-in/check-out with radius validation`
- T7 → `feat(api): add delivery location ping + admin track endpoints`
- T8 → `feat(api): add proof-of-delivery upload endpoint + filesystems config`
- T9 → `feat(api): expose delivery route planning result`
- T10 → `feat(web): add PWA service worker, offline page, online indicator`
- T11 → `feat(web): add offline order queue with idempotent flush`
- T12 → `feat(web): enqueue orders offline and auto-flush on reconnect`
- T13 → `feat(web): add admin driver roster page`
- T14 → `feat(web): add sales visit check-in/out UI with geolocation`
- T15 → `feat(web): add driver proof-of-delivery capture UI`
- T16 → `feat(web): add admin live tracking page with polling`
- T17 → `feat(web): field-ops dummy fixtures + nav/rbac wiring`
- T18 → `test: field-ops cross-unit integration + full suite verification`

### File Structure Map

```
Rule: field-ops-schema
  Create: apps/api/database/migrations/*_create_driver_profiles_table.php        (created by: T1)
  Create: apps/api/database/migrations/*_create_delivery_location_pings_table.php (created by: T1)
  Create: apps/api/database/migrations/*_add_gps_columns_to_sales_visits_table.php (created by: T1)
  Create: apps/api/database/migrations/*_add_pod_metadata_to_deliveries_table.php (created by: T1)
  Test:   apps/api/tests/Feature/FieldOpsMigrationTest.php                        (created by: T1)

Rule: driver-profile-domain
  Create: apps/api/app/Models/DriverProfile.php                 (created by: T2)
  Modify: apps/api/app/Models/User.php                          (created by: T2)
  Create: apps/api/database/factories/DriverProfileFactory.php  (created by: T2)
  Test:   apps/api/tests/Unit/DriverProfileTest.php             (created by: T2)

Rule: routing-service
  Modify: apps/api/app/Services/RoutingService.php
  Test:   apps/api/tests/Unit/RoutingServiceTest.php            (created by: T3)

Rule: rbac-field-ops
  Modify: apps/api/app/Models/MenuDefinition.php
  Modify: apps/api/database/seeders/*Rbac*Seeder.php
  Test:   apps/api/tests/Feature/RbacFieldOpsCatalogTest.php    (created by: T4)

Rule: driver-roster-api
  Create: apps/api/app/Http/Controllers/DriverRosterController.php
  Create: apps/api/app/Http/Requests/StoreDriverProfileRequest.php  (created by: T5)
  Create: apps/api/app/Http/Requests/UpdateDriverProfileRequest.php (created by: T5)
  Modify: apps/api/routes/api.php
  Test:   apps/api/tests/Feature/FieldOps/DriverRosterTest.php      (created by: T5)

Rule: visit-checkin-api
  Modify: apps/api/app/Http/Controllers/SalesController.php
  Create: apps/api/app/Http/Requests/CheckInVisitRequest.php    (created by: T6)
  Create: apps/api/app/Http/Requests/CheckOutVisitRequest.php   (created by: T6)
  Modify: apps/api/config/orders.php
  Test:   apps/api/tests/Feature/FieldOps/SalesVisitCheckinTest.php (created by: T6)

Rule: delivery-tracking-api
  Modify: apps/api/app/Http/Controllers/DeliveryController.php
  Create: apps/api/app/Http/Requests/StoreLocationPingRequest.php (created by: T7)
  Test:   apps/api/tests/Feature/FieldOps/DeliveryTrackingTest.php (created by: T7)

Rule: delivery-proof-api
  Modify: apps/api/app/Http/Controllers/DeliveryController.php
  Create: apps/api/app/Http/Requests/UploadProofRequest.php      (created by: T8)
  Create: apps/api/config/filesystems.php                        (created by: T8)
  Test:   apps/api/tests/Feature/FieldOps/DeliveryProofUploadTest.php (created by: T8)

Rule: delivery-route-read
  Modify: apps/api/app/Http/Controllers/DeliveryController.php
  Test:   apps/api/tests/Feature/FieldOps/DeliveryRouteTest.php   (created by: T9)

Rule: pwa-shell
  Create: apps/web/public/sw.js                                  (created by: T10)
  Create: apps/web/src/lib/pwa/register.ts                       (created by: T10)
  Create: apps/web/src/app/offline/page.tsx                      (created by: T10)
  Create: apps/web/src/hooks/useOnlineStatus.ts                  (created by: T10)
  Modify: apps/web/src/components/Topbar.tsx
  Modify: apps/web/src/components/Topbar.test.tsx               (existing — extend)
  Test:   apps/web/src/lib/pwa/register.test.ts                  (created by: T10)

Rule: offline-queue
  Create: apps/web/src/lib/offline/order-queue.ts                (created by: T11)
  Create: apps/web/src/lib/offline/storage-adapter.ts            (created by: T11)
  Test:   apps/web/src/lib/offline/order-queue.test.ts           (created by: T11)

Rule: offline-order-form
  Modify: apps/web/src/components/OrderForm.tsx
  Test:   apps/web/src/components/OrderForm.offline.test.tsx     (created by: T12)

Rule: admin-driver-roster-page
  Create: apps/web/src/app/admin/drivers/page.tsx                (created by: T13)
  Create: apps/web/src/app/admin/drivers/api.ts                  (created by: T13)
  Test:   apps/web/src/app/admin/drivers/page.test.tsx           (created by: T13)

Rule: sales-checkin-ui
  Create: apps/web/src/app/sales/VisitCheckin.tsx                (created by: T14)
  Modify: apps/web/src/app/sales/page.tsx
  Modify: apps/web/src/app/sales/api.ts
  Test:   apps/web/src/app/sales/checkin.test.tsx                (created by: T14)

Rule: pod-capture-ui
  Create: apps/web/src/components/delivery/PodCapture.tsx        (created by: T15)
  Modify: apps/web/src/app/delivery/api.ts
  Test:   apps/web/src/components/delivery/PodCapture.test.tsx   (created by: T15)

Rule: admin-tracking-page
  Create: apps/web/src/app/admin/tracking/page.tsx               (created by: T16)
  Create: apps/web/src/app/admin/tracking/api.ts                 (created by: T16)
  Test:   apps/web/src/app/admin/tracking/page.test.tsx          (created by: T16)

Rule: field-ops-dummy-parity
  Modify: apps/web/src/dummy/aggregates.ts
  Modify: apps/web/src/dummy/index.ts
  Modify: apps/web/src/components/Sidebar.tsx
  Modify: apps/web/src/components/Sidebar.test.tsx              (existing — extend)
  Test:   apps/web/src/dummy/field-ops.test.ts                   (created by: T17)

Rule: field-ops-integration
  Create: apps/api/tests/Feature/FieldOps/FieldOpsIntegrationTest.php     (created by: T18)
  Create: apps/web/src/app/__tests__/field-ops-integration.test.tsx       (created by: T18)
```

---

## Pocket Packets

---

### Task 1: Migrasi & model field-ops [prereq]

## OBJECTIVE
Tambahkan skema additive untuk Phase 8: tabel `driver_profiles` dan `delivery_location_pings`, kolom GPS pada `sales_visits`, dan kolom metadata PoD pada `deliveries`. Semua migrasi reversible dan portabel SQLite + PostgreSQL.

Steps:
1. Write failing test for: skema tabel baru + kolom baru ada.
   Test file: `apps/api/tests/Feature/FieldOpsMigrationTest.php`
   Level: feature
   Test intent: Given fresh migrated DB / When `Schema::hasTable('driver_profiles')` dan `Schema::hasTable('delivery_location_pings')` / Then `true`; When `Schema::hasColumns('sales_visits', ['check_in_at','check_in_latitude','check_in_longitude','check_in_accuracy_m','check_out_at','check_out_latitude','check_out_longitude'])` / Then `true`; When `Schema::hasColumns('deliveries', ['pod_captured_at','pod_latitude','pod_longitude'])` / Then `true`.
   Exercise through: `RefreshDatabase` + `Schema`.
   Test doubles: none.
   Expected RED: tabel/kolom belum ada → assertion gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=FieldOpsMigrationTest`
3. Buat 4 migrasi additive; implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md — Scope In-Scope (roster, GPS, tracking, PoD), Architecture Constraints (portable SQLite/PostgreSQL, additive+reversible).

## WHY THIS APPROACH
Complexity: lightweight
Justification: Fondasi data untuk seluruh Phase 8; memperluas model yang ada (DD-1) alih-alih membuat entitas visit/delivery baru.

## SANDWICH CONTEXT
[CRITICAL: migrasi additive + reversible; jangan ubah kolom lama; jangan sentuh guard/auth]
You are implementing the data foundation untuk Phase 8 mobile field operations.
Spec: docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md
Design decision: DD-1 (additive seam-preserving).
Files in scope: `apps/api/database/migrations/*` (4 file baru), `apps/api/tests/Feature/FieldOpsMigrationTest.php`
Available after: none (prereq)
Architecture rule: tidak ada fitur Postgres-only; gunakan tipe portabel (`decimal(10,7)`, `unsignedInteger`).
[RESTATE: migrasi additive + reversible; jangan ubah kolom lama]

## DELIVERABLE
- `driver_profiles`: `id`, `user_id` (unique, FK users restrict), `vehicle_type`, `plate_number`, `capacity_kg` (unsigned int nullable), `service_territory_id` (FK territories nullable), `shift_start` (time nullable), `shift_end` (time nullable), `is_available` (bool default true), timestamps.
- `delivery_location_pings`: `id`, `delivery_id` (FK deliveries cascade), `latitude` (decimal 10,7), `longitude` (decimal 10,7), `accuracy_m` (unsigned int nullable), `recorded_at` (datetime), timestamps; index `['delivery_id','recorded_at']`.
- `sales_visits` +7 kolom GPS (semua nullable).
- `deliveries` +3 kolom PoD meta (nullable).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Setiap migrasi punya `down()` yang membalik tepat (drop kolom/tabel).
  - `migrate` + `migrate:rollback` bersih di SQLite.
  - FK pakai `restrictOnDelete` (profil) / `cascadeOnDelete` (ping).
Must-not-have:
  - Mengubah/menghapus kolom `sales_visits`/`deliveries` yang ada.
  - Menambah dependency DB baru.
Open question risks:
  - A-2: penyimpanan PoD memakai disk lokal (bukan S3).
Rollback note:
  - `migrate:rollback` menghapus semua perubahan Phase 8.
Red flags:
  - Migrasi gagal di SQLite → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: test skema PASS + migrate/rollback bersih + hanya 4 migrasi + 1 test baru.
Uncertain when: tipe kolom tidak portabel di SQLite.
Escalate when: dibutuhkan perubahan skema transaksi order.

---

### Task 2: `DriverProfile` model + relasi + factory + seeder

## OBJECTIVE
Buat model `DriverProfile` dengan relasi ke `User` dan `Territory`, factory, dan tambahkan relasi `driverProfile()` pada `User`.

Steps:
1. Write failing test for: relasi + fillable + cast.
   Test file: `apps/api/tests/Unit/DriverProfileTest.php`
   Level: unit
   Test intent: Given `DriverProfile::factory()->create(['user_id' => $driver->id])` / When `$driver->driverProfile` / Then instance `DriverProfile`; When `$profile->user` / Then instance `User`; When `$profile->capacity_kg` / Then `int`.
   Exercise through: Eloquent relasi.
   Test doubles: none.
   Expected RED: class `App\Models\DriverProfile` belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DriverProfileTest`
3. Implement model + factory + relasi User → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 8 — In-Scope (driver roster CRUD), AC-1.

## WHY THIS APPROACH
Complexity: lightweight
Justification: Roster butuh entitas profil terpisah dari `users` agar assignment tidak memaksa kolom kendaraan di tabel user.

## SANDWICH CONTEXT
[CRITICAL: `user_id` unique; jangan ubah model User lain selain menambah relasi]
Files in scope: `apps/api/app/Models/DriverProfile.php`, `apps/api/app/Models/User.php` (tambah relasi), `apps/api/database/factories/DriverProfileFactory.php`, `apps/api/database/seeders/*`.
Available after: T1 (tabel ada).
Architecture rule: `$fillable` eksplisit; cast `is_available` bool, `capacity_kg` int.

## DELIVERABLE
- `DriverProfile` model: fillable `user_id, vehicle_type, plate_number, capacity_kg, service_territory_id, shift_start, shift_end, is_available`; relasi `user()`, `serviceTerritory()`.
- `User::driverProfile()` hasOne.
- `DriverProfileFactory` deterministik.
- Seeder opsional: 2–3 profil untuk driver di `DatabaseSeeder` (idempotent).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Relasi dua arah teruji.
  - Factory menghasilkan `plate_number` unik-format valid.
Must-not-have:
  - Mengubah kolom `users`.
Open question risks:
  - A-5: driver tanpa profil tetap valid (relasi nullable).
Rollback note:
  - Hapus model/factory/relasi.

## STOP CONDITIONS
Done when: unit test relasi PASS + factory bisa dipakai di test lain.
Escalate when: butuh kolom user tambahan.

---

### Task 3: `RoutingService` nearest-neighbor bounded deterministik

## OBJECTIVE
Ganti `RoutingService::plan()` yang selalu mengembalikan `[]` menjadi nearest-neighbor bounded deterministik: ambil stop berkoordinat dari order/delivery, urutkan dari titik awal (gudang/outlet pertama), hitung jarak haversine, cap `MAX_STOPS`, tie-break `id`.

Steps:
1. Write failing test for: urutan deterministik + total jarak.
   Test file: `apps/api/tests/Unit/RoutingServiceTest.php`
   Level: unit
   Test intent: Given 3 stop koordinat tetap / When `plan()` / Then urutan stop sesuai nearest-neighbor dari titik awal; When dipanggil dua kali / Then hasil identik; When stop > `MAX_STOPS` / Then hasil ≤ `MAX_STOPS`.
   Exercise through: `RoutingService::plan()`.
   Test doubles: model Delivery/Order in-memory atau factory.
   Expected RED: `plan()` mengembalikan `[]` → assertion gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RoutingServiceTest`
3. Implement haversine + nearest-neighbor + cap → PASS → refactor → commit.

4. Write failing test for: stop tanpa koordinat dilewati.
   Test intent: Given 1 stop tanpa lat/long / When `plan()` / Then stop itu tidak muncul, tidak ada exception.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-3, DD-2.

## WHY THIS APPROACH
Complexity: medium
Justification: seam sudah ada (`RoutingService`), hanya mengisi implementasi bounded deterministik agar testable tanpa ML/solver eksternal.

## SANDWICH CONTEXT
[CRITICAL: deterministik; tanpa dependency eksternal; jangan ubah signature `plan(Delivery $delivery): array`]
Files in scope: `apps/api/app/Services/RoutingService.php`, `apps/api/tests/Unit/RoutingServiceTest.php`.
Available after: T1 (kolom outlet lat/long + delivery).
Architecture rule: haversine murni PHP; `MAX_STOPS` konstanta kelas (mis. 20).

## DELIVERABLE
- `plan()` mengembalikan `['stops' => [['id'=>..,'latitude'=>..,'longitude'=>..,'distance_km'=>..], ...], 'total_distance_km' => float]` atau `[]` bila tak ada koordinat.
- Deterministik (tie-break `id` asc).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Deterministik: input sama → output sama.
  - Bounded `MAX_STOPS`.
  - Stop tanpa koordinat dilewati tanpa error.
Must-not-have:
  - Dependency paket rute eksternal.
  - Perubahan signature publik.
Open question risks:
  - Definisi titik awal rute (gudang vs outlet pertama) — asumsikan titik awal = stop pertama by id.
Rollback note:
  - Kembalikan `plan()` ke `return []`.

## STOP CONDITIONS
Done when: unit test urutan + determinisme + cap + skip PASS.
Uncertain when: titik awal tidak terdefinisi.
Escalate when: butuh data gudang yang belum ada.

---

### Task 4: RBAC catalog + seeder default (`driver_roster`, `field_ops`)

## OBJECTIVE
Tambahkan 2 menu baru ke `MenuDefinition::CATALOG` (`driver_roster`, `field_ops`) dan matriks akses default pada seeder: admin `edit`, platform_owner `edit`, sales `read` (field_ops), driver `read` (field_ops), finance/outlet `none`.

Steps:
1. Write failing test for: catalog + default matrix.
   Test file: `apps/api/tests/Feature/RbacFieldOpsCatalogTest.php`
   Level: feature
   Test intent: Given seeded RBAC / When `MenuDefinition::keys()` / Then memuat `driver_roster` + `field_ops`; When `GET /admin/rbac/matrix` sebagai admin / Then kedua key ada; When map role `driver` / Then `field_ops = read`, `driver_roster = none`.
   Exercise through: seeder + `RbacMatrixService`.
   Test doubles: none.
   Expected RED: key belum ada → assertion gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacFieldOpsCatalogTest`
3. Tambah CATALOG + seeder default → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-8, Architecture Constraints (RBAC), `MenuDefinition::CATALOG` (19 menu saat ini).

## WHY THIS APPROACH
Complexity: lightweight
Justification: menu baru butuh entri single-source-of-truth CATALOG + seeder agar endpoint RBAC merender map penuh di fresh DB.

## SANDWICH CONTEXT
[CRITICAL: CATALOG adalah single source of truth; jangan ubah 19 menu lama; seeder idempotent]
Files in scope: `apps/api/app/Models/MenuDefinition.php`, `apps/api/database/seeders/*Rbac*`, `apps/api/tests/Feature/RbacFieldOpsCatalogTest.php`.
Available after: T1.
Architecture rule: `sort` melanjutkan urutan (20, 21); grup `operasional` (field_ops) & `admin` (driver_roster).

## DELIVERABLE
- CATALOG +2 entri: `field_ops` (label "Operasi Lapangan", grup operasional, sort 20), `driver_roster` (label "Roster Driver", grup admin, sort 21).
- Seeder default: admin/platform_owner `edit`; sales/driver `read` field_ops; driver_roster admin-only.
- Idempotent re-seed.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 19 → 21 menu; key lama tidak berubah.
  - Seeder idempotent (aman dijalankan ulang).
Must-not-have:
  - Mengubah level menu lama.
Open question risks:
  - Apakah sales butuh `driver_roster:read`? Asumsi: tidak.
Rollback note:
  - Hapus 2 entri CATALOG + baris seeder; hapus baris `role_menu_access` terkait.

## STOP CONDITIONS
Done when: test catalog + matrix PASS; re-seed idempotent.
Escalate when: butuh menu baru lain (mis. `tracking`).

---

### Task 5: `DriverRosterController` CRUD + requests + routes

## OBJECTIVE
Endpoint CRUD roster driver di `/admin/drivers` (index dengan kontrak `ListQuery`, store, update, destroy/soft-toggle) dengan validasi user harus role `driver` & belum punya profil, otorisasi `rbac:driver_roster:<level>`.

Steps:
1. Write failing test for: create + validasi + otorisasi.
   Test file: `apps/api/tests/Feature/FieldOps/DriverRosterTest.php`
   Level: feature
   Test intent: Given admin + user driver tanpa profil / When `POST /admin/drivers` / Then 201 + record; When `user_id` bukan driver / Then 422; When sudah punya profil / Then 422; When sales / Then 403; When tanpa token / Then 401.
   Exercise through: HTTP endpoint.
   Test doubles: factory User (driver/sales/admin), Territory.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DriverRosterTest`
3. Implement controller + requests + routes → PASS → refactor → commit.

4. Write failing test for: index sort/paging/total + update.
   Test intent: Given 25 roster / When `GET /admin/drivers?per_page=10` / Then `meta.total=25` + 10 baris; When `PATCH /admin/drivers/{id}` ubah `vehicle_type` / Then 200 + berubah.
5. Run test — verify FAIL → implement `ListQuery` → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-1; `apps/api/app/Support/ListQuery.php` (kontrak tabel admin); `AdminOutletController` sebagai preseden pola.

## WHY THIS APPROACH
Complexity: medium
Justification: mengikuti pola controller admin yang sudah ada (ListQuery + FormRequest + rbac middleware).

## SANDWICH CONTEXT
[CRITICAL: rbac:driver_roster:* di semua route; jangan duplikasi logika ListQuery]
Files in scope: `apps/api/app/Http/Controllers/DriverRosterController.php`, `apps/api/app/Http/Requests/{Store,Update}DriverProfileRequest.php`, `apps/api/routes/api.php`, test.
Available after: T2 (model), T4 (menu+level).
Architecture rule: envelope `{status,data}`; `meta.total` top-level; sort allowlist `['created_at','plate_number','id']`.

## DELIVERABLE
- `GET /admin/drivers` (rbac:driver_roster:read) dengan sort/paging/total/summary.
- `POST /admin/drivers`, `PATCH /admin/drivers/{id}`, `DELETE /admin/drivers/{id}` (rbac:driver_roster:edit).
- Validasi: `user_id` exists + role driver + belum punya profil; `plate_number` unik.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 401/403 teruji untuk tiap route.
  - Validasi driver + duplikat profil.
  - `platform_owner` boleh (setara admin via policy/level).
Must-not-have:
  - Menghapus user; destroy hanya menghapus profil (atau soft `is_available=false`).
Open question risks:
  - Delete vs deactivate — asumsi: DELETE menghapus profil roster, user tetap.
Rollback note:
  - Hapus controller/requests/routes.

## STOP CONDITIONS
Done when: CRUD + validasi + otorisasi + index kontrak PASS.
Escalate when: butuh ubah model User/route guard.

---

### Task 6: Visit check-in/check-out endpoints + radius validation

## OBJECTIVE
Tambah `POST /sales/visits/{id}/check-in` dan `POST /sales/visits/{id}/check-out` pada `SalesController`, menyimpan GPS + waktu, memvalidasi radius ke outlet (default 200 m), scoped ke sales pemilik visit, dengan guard idempotensi.

Steps:
1. Write failing test for: check-in dalam radius.
   Test file: `apps/api/tests/Feature/FieldOps/SalesVisitCheckinTest.php`
   Level: feature
   Test intent: Given sales + visit `planned` outlet berkoordinat / When check-in dengan lat/long dekat outlet / Then 200 + `check_in_at` terisi; When koordinat jauh (>radius) / Then 422; When visit sales lain / Then 403; When sudah check-in lalu check-in lagi / Then 409/422.
   Exercise through: HTTP endpoint.
   Test doubles: factory Outlet (lat/long), SalesVisit.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=SalesVisitCheckinTest`
3. Implement check-in → PASS → refactor → commit.

4. Write failing test for: check-out mengubah status.
   Test intent: Given visit sudah check-in / When check-out / Then `check_out_at` terisi + status `completed`; When check-out tanpa check-in / Then 422.
5. Run test — verify FAIL → implement check-out → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-2, A-1 (radius 200 m).

## WHY THIS APPROACH
Complexity: medium
Justification: memperluas `SalesController` + `sales_visits` (DD-1), radius via config `config('orders.visit_radius_m', 200)`.

## SANDWICH CONTEXT
[CRITICAL: scoped ke sales pemilik; jangan ubah endpoint visit lama; jangan tambah tabel baru]
Files in scope: `apps/api/app/Http/Controllers/SalesController.php`, `apps/api/app/Http/Requests/*VisitCheck*Request.php`, `apps/api/routes/api.php`, `apps/api/config/orders.php`, test.
Available after: T1 (kolom GPS).
Architecture rule: haversine radius; tolak bila outlet tidak punya koordinat (422 dengan pesan jelas).

## DELIVERABLE
- `POST /sales/visits/{id}/check-in` (rbac:sales:edit): simpan `check_in_*`, 200.
- `POST /sales/visits/{id}/check-out` (rbac:sales:edit): simpan `check_out_*`, status `completed`.
- Guard: 403 bukan pemilik, 422 di luar radius / tanpa koordinat outlet, 409 bila sudah check-in.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Radius & otorisasi teruji.
  - Idempotensi check-in.
Must-not-have:
  - Mengubah endpoint visit existing.
Open question risks:
  - A-1: radius 200 m; configurable.
Rollback note:
  - Hapus 2 route + method; kolom GPS tetap (nullable).

## STOP CONDITIONS
Done when: check-in/out + radius + otorisasi + idempotensi PASS.
Escalate when: butuh reverse geocoding/geo-fence kompleks.

---

### Task 7: Delivery location ping endpoints

## OBJECTIVE
Tambah `POST /deliveries/{id}/location` (driver pemilik mengirim ping GPS) dan `GET /admin/deliveries/{id}/track` (admin melihat posisi terakhir + N ping terbaru).

Steps:
1. Write failing test for: driver kirim ping.
   Test file: `apps/api/tests/Feature/FieldOps/DeliveryTrackingTest.php`
   Level: feature
   Test intent: Given driver dengan delivery miliknya / When `POST /deliveries/{id}/location` lat/long / Then 201 + ping tersimpan; When driver lain / Then 403; When lat/long invalid / Then 422.
   Exercise through: HTTP endpoint.
   Test doubles: factory Delivery + driver User.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DeliveryTrackingTest`
3. Implement POST location → PASS → refactor → commit.

4. Write failing test for: admin track read.
   Test intent: Given 5 ping untuk delivery / When `GET /admin/deliveries/{id}/track` sebagai admin / Then `last_position` = ping terbaru + `pings` maks N (terbaru dulu); When sales / Then 403.
5. Run test — verify FAIL → implement GET track → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-4, DD-5 (polling).

## WHY THIS APPROACH
Complexity: medium
Justification: tabel ping + 2 endpoint minimal; polling agar tidak perlu WebSocket.

## SANDWICH CONTEXT
[CRITICAL: driver hanya boleh ping delivery miliknya; admin-only untuk track; jangan ubah endpoint delivery lama]
Files in scope: `apps/api/app/Http/Controllers/DeliveryController.php` (tambah method), `apps/api/app/Http/Requests/StoreLocationPingRequest.php`, `apps/api/routes/api.php`, test.
Available after: T1 (tabel ping).
Architecture rule: simpan `recorded_at = now()`; `pings` cap (mis. 50) order `recorded_at` desc.

## DELIVERABLE
- `POST /deliveries/{id}/location` (rbac:field_ops:edit atau delivery:edit) → 201.
- `GET /admin/deliveries/{id}/track` (rbac:field_ops:read) → `{last_position, pings}`.
- Validasi lat (-90..90) / long (-180..180).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Ownership driver teruji.
  - Cap ping teruji.
Must-not-have:
  - Menyimpan ping tanpa validasi koordinat.
Open question risks:
  - Menu mana yang menggerbangi tracking (field_ops vs delivery) — asumsi `field_ops`.
Rollback note:
  - Hapus 2 route + method; tabel ping di-rollback di T1.

## STOP CONDITIONS
Done when: ping + track + otorisasi + validasi PASS.
Escalate when: butuh WebSocket/push.

---

### Task 8: Proof-of-delivery upload endpoint

## OBJECTIVE
Tambah `POST /deliveries/{id}/proof` yang menerima foto + tanda tangan (base64/multipart), menyimpannya via disk abstraction, dan menulis URL ke `proof_of_delivery` json plus metadata `pod_captured_at`/`pod_latitude`/`pod_longitude`. Driver hanya untuk delivery miliknya.

Steps:
1. Write failing test for: upload sukses.
   Test file: `apps/api/tests/Feature/FieldOps/DeliveryProofUploadTest.php`
   Level: feature
   Test intent: Given driver + delivery `in_progress` / When `POST /deliveries/{id}/proof` dengan foto valid + signature valid / Then 201 + `proof_of_delivery.photo_url` & `signature_url` terisi + `pod_captured_at` terisi; `Storage::fake()`.
   Exercise through: HTTP endpoint + `Storage::fake`.
   Test doubles: `Storage::fake('local')`, factory.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DeliveryProofUploadTest`
3. Implement upload + metadata → PASS → refactor → commit.

4. Write failing test for: validasi & otorisasi.
   Test intent: Given file > batas ukuran / When upload / Then 422; Given tipe tak didukung / Then 422; Given driver lain / Then 403.
5. Run test — verify FAIL → implement validasi → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-5, DD-3 (disk-agnostic), A-2 (lokal).

## WHY THIS APPROACH
Complexity: medium
Justification: `UpdateDeliveryStatusRequest` sudah punya field URL; T8 menambah endpoint upload nyata tanpa mengunci ke S3.

## SANDWICH CONTEXT
[CRITICAL: pakai disk abstraction; driver-only; jangan ubah flow status delivery existing]
Files in scope: `apps/api/app/Http/Controllers/DeliveryController.php`, `apps/api/app/Http/Requests/UploadProofRequest.php`, `apps/api/config/filesystems.php` (BARU — belum ada di repo), `apps/api/routes/api.php`, test.
Available after: T1 (kolom pod meta).
Architecture rule: `Storage::disk(config('filesystems.default'))`; simpan path relatif; kembalikan URL via `Storage::url()`. CATATAN: `config/filesystems.php` belum ada → buat dengan disk `local` (`storage/app/private`) + `public` link, default `local`.

## DELIVERABLE
- `POST /deliveries/{id}/proof` (rbac:field_ops:edit) menerima `photo` (image, max 5MB) + `signature` (image/png, max 2MB) + optional `latitude`/`longitude`.
- Menulis `proof_of_delivery = {photo_url, signature_url, captured_at}` + kolom `pod_*`.
- 403 bukan pemilik; 422 tipe/ukuran salah.
- `config/filesystems.php` dibuat dengan disk `local` (default) + `public`; helper `Storage::fake()` di test.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `Storage::fake` di test (tanpa I/O nyata).
  - Validasi mime + size.
Must-not-have:
  - Menyimpan base64 mentah ke DB.
  - Hardcode path S3.
Open question risks:
  - A-2: disk `local` default; jika butuh S3 → NEEDS_CONTEXT.
Rollback note:
  - Hapus route + method + request; file ter-upload tidak dibersihkan otomatis (catat).

## STOP CONDITIONS
Done when: upload + validasi + otorisasi PASS dengan `Storage::fake`; `config/filesystems.php` ada & `Storage::disk('local')` berfungsi.
Escalate when: butuh S3/object storage produksi (A-2 → NEEDS_CONTEXT).

---

### Task 9: Wire `RoutingService` ke `DeliveryController@store` + route admin

## OBJECTIVE
Pastikan `DeliveryController@store` menyimpan hasil `RoutingService::plan()` yang sudah berisi stop ke `route_data`, dan tambah endpoint `GET /admin/deliveries/{id}/route` untuk melihat rute terjadwal.

Steps:
1. Write failing test for: route_data terisi saat assignment.
   Test file: `apps/api/tests/Feature/FieldOps/DeliveryRouteTest.php`
   Level: feature
   Test intent: Given admin assign delivery ke driver untuk order confirmed dengan outlet berkoordinat / When `POST /deliveries` / Then `route_data.stops` tidak kosong; When outlet tanpa koordinat / Then `route_data` null tanpa error.
   Exercise through: HTTP endpoint.
   Test doubles: factory Order/Outlet/Delivery/User.
   Expected RED: `route_data` masih `null` (plan mengembalikan `[]` lama) atau route baru 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DeliveryRouteTest`
3. Wire plan() → route_data + tambah `GET /admin/deliveries/{id}/route` → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-3, DD-2; `DeliveryController@store` (memanggil `$this->routing->plan()`).

## WHY THIS APPROACH
Complexity: lightweight
Justification: `store()` sudah memanggil `plan()`; T9 hanya memastikan output T3 benar-benar tersimpan & terekspos, plus route baca untuk admin.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah validasi/transaksi assignment; route_data nullable]
Files in scope: `apps/api/app/Http/Controllers/DeliveryController.php`, `apps/api/routes/api.php`, test.
Available after: T3 (plan nyata), T5 (pola rbac admin).
Architecture rule: `route_data` null bila tak ada stop; endpoint baca `rbac:field_ops:read`.

## DELIVERABLE
- `store()` menyimpan `route_data` hasil plan (sudah ada — verifikasi perilaku).
- `GET /admin/deliveries/{id}/route` mengembalikan `route_data` (atau `null`).
- Test memverifikasi kedua jalur (ada/tanpa koordinat).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Tidak mengubah kontrak `store()` yang ada (status/error).
  - `route_data` null-safe.
Must-not-have:
  - Menambah kolom baru (sudah ada `route_data`).
Open question risks:
  - Format `route_data` — ikuti output T3 (`{stops,total_distance_km}`).
Rollback note:
  - Hapus route baca; `store()` tetap kompatibel.

## STOP CONDITIONS
Done when: route_data tersimpan + endpoint baca PASS.
Escalate when: format plan T3 tidak sesuai.

---

### Task 10: Service worker + offline shell [prereq]

## OBJECTIVE
Tambahkan service worker (app shell cache + offline fallback), registrasi di client, halaman `/offline`, dan indikator online/offline di `Topbar`. Dinonaktifkan via `NEXT_PUBLIC_PWA_ENABLED`.

Steps:
1. Write failing test for: registrasi hanya bila flag ON.
   Test file: `apps/web/src/lib/pwa/register.test.ts`
   Level: unit
   Test intent: Given `NEXT_PUBLIC_PWA_ENABLED=true` + `navigator.serviceWorker` mock / When `registerServiceWorker()` / Then `register('/sw.js')` dipanggil sekali; Given flag false / Then tidak memanggil.
   Exercise through: `registerServiceWorker()`.
   Test doubles: mock `navigator.serviceWorker`, mock env.
   Expected RED: modul belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/pwa/register.test.ts`
3. Implement register + `public/sw.js` + `/offline` page → PASS → refactor → commit.

4. Write failing test for: indikator Topbar.
   Test intent: Given `navigator.onLine=false` / When render Topbar / Then badge "Offline" tampil; When `online` event fired / Then badge hilang.
   Test file: `apps/web/src/components/Topbar.test.tsx`
   Test doubles: mock `window.addEventListener('online'/'offline')`.
5. Run test — verify FAIL → implement hook `useOnlineStatus` + badge → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-6, Rollback (flag `NEXT_PUBLIC_PWA_ENABLED`); `manifest.ts` existing.

## WHY THIS APPROACH
Complexity: medium
Justification: service worker manual (tanpa `next-pwa`) agar terkontrol & testable; flag untuk rollback mudah.

## SANDWICH CONTEXT
[CRITICAL: SW tidak boleh meng-cache respons API POST; hanya app shell/statis; hormati flag]
Files in scope: `apps/web/public/sw.js`, `apps/web/src/lib/pwa/register.ts`, `apps/web/src/app/offline/page.tsx`, `apps/web/src/components/Topbar.tsx`, `apps/web/src/hooks/useOnlineStatus.ts`, test.
Available after: none (prereq).
Architecture rule: cache-first untuk aset statis, network-first untuk navigasi; skip cache untuk `/api/`.

## DELIVERABLE
- `public/sw.js`: install cache app shell, fetch handler skip `/api/`, offline fallback `/offline`.
- `registerServiceWorker()` idempotent, guard flag + `typeof navigator`.
- Halaman `/offline` minimal.
- Badge online/offline di Topbar.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - SW tidak mengganggu request API/order.
  - Registrasi tidak dijalankan di server (`typeof window`).
Must-not-have:
  - Menambah dependency `next-pwa`/workbox tanpa alasan.
Open question risks:
  - Next.js 16 + SW: pastikan `sw.js` di `public/` disajikan di root scope.
Rollback note:
  - Set flag false → registrasi tak jalan; hapus `sw.js`.

## STOP CONDITIONS
Done when: register test + Topbar test PASS; `sw.js` disajikan; flag mematikan PWA.
Escalate when: SW scope/update strategy tidak jelas di Next 16.

---

### Task 11: Offline order queue (IndexedDB wrapper + flush)

## OBJECTIVE
Buat antrean order offline client-side (IndexedDB via wrapper, fallback localStorage) yang menyimpan payload order saat offline dan mem-flush ke `POST /orders` (idempotent) saat kembali online.

Steps:
1. Write failing test for: enqueue + list.
   Test file: `apps/web/src/lib/offline/order-queue.test.ts`
   Level: unit
   Test intent: Given queue kosong / When `enqueue({payload})` / Then `list()` memuat 1 item dengan id unik + `created_at`; When `remove(id)` / Then `list()` kosong.
   Exercise through: `orderQueue.enqueue/list/remove`.
   Test doubles: adapter storage in-memory (inject `StorageAdapter` stub; TIDAK memakai `fake-indexeddb` — belum ada di deps).
   Expected RED: modul belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/offline/order-queue.test.ts`
3. Implement queue storage → PASS → refactor → commit.

4. Write failing test for: flush sukses + idempotency key.
   Test intent: Given 2 item antrean + `POST /orders` sukses / When `flushQueue(send)` / Then 2 item terkirim dengan header idempotency (payload hash) + antrean kosong; Given satu item gagal (network) / Then item itu tetap di antrean, yang sukses terhapus.
   Test doubles: mock `send` (jest.fn) sukses/gagal.
5. Run test — verify FAIL → implement flush → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-6, DD-4 (idempotent replay via payload hash).

## WHY THIS APPROACH
Complexity: medium
Justification: `POST /orders` sudah idempotent (`idempotency_payload_hash`), jadi antrean cukup menyimpan payload dan replay; tanpa endpoint sync baru.

## SANDWICH CONTEXT
[CRITICAL: flush tidak boleh menghapus item yang gagal; tidak ada endpoint baru]
Files in scope: `apps/web/src/lib/offline/order-queue.ts`, `apps/web/src/lib/offline/storage-adapter.ts` (wrapper), test.
Available after: T10 (offline shell).
Architecture rule: `StorageAdapter` interface (`get/set/delete/keys`) dapat di-inject agar test tidak butuh IndexedDB nyata; implementasi `IndexedDbAdapter` (native, tanpa dependency baru) + fallback `LocalStorageAdapter` bila `indexedDB` absen (A-3). JANGAN menambah paket `idb-keyval`/`fake-indexeddb` (tidak ada di `apps/web/package.json`).

## DELIVERABLE
- `orderQueue`: `enqueue(payload)`, `list()`, `remove(id)`, `clear()`.
- `flushQueue(send: (payload)=>Promise<Response>)` mengembalikan `{sent, failed}`.
- Idempotency key deterministik dari payload (hash).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Item gagal tidak hilang.
  - Idempotency key stabil untuk payload sama.
Must-not-have:
  - Menyimpan token/secret di IndexedDB.
Open question risks:
  - A-3: IndexedDB absen → fallback localStorage.
Rollback note:
  - Hapus modul queue; tidak ada dampak server.

## STOP CONDITIONS
Done when: enqueue/list/remove + flush sukses/gagal PASS.
Escalate when: hash idempotency tidak cocok dengan kontrak `POST /orders`.

---

### Task 12: Integrasi antrean offline ke form order + status UI

## OBJECTIVE
Sambungkan `OrderForm` ke antrean offline: bila offline (atau request gagal karena network), order masuk antrean dan ditampilkan sebagai "menunggu sinkron"; saat online, `flushQueue` dijalankan otomatis dan order muncul di daftar.

Steps:
1. Write failing test for: submit saat offline → enqueue.
   Test file: `apps/web/src/components/OrderForm.offline.test.tsx`
   Level: component
   Test intent: Given `navigator.onLine=false` + isi order valid / When submit / Then pesan "Menunggu sinkronisasi" tampil dan `orderQueue.list()` berisi 1 item (zero fetch).
   Exercise through: render `OrderForm` + `fireEvent.submit`.
   Test doubles: mock `navigator.onLine`, mock queue, mock fetch.
   Expected RED: submit mencoba fetch dan error.
2. Run test — verify FAIL: `cd apps/web && npx jest OrderForm.offline.test.tsx`
3. Implement guard offline → enqueue → PASS → refactor → commit.

4. Write failing test for: kembali online → flush.
   Test intent: Given 1 item antrean / When `online` event fired / Then `flushQueue` dipanggil dan antrean kosong + order muncul di daftar.
5. Run test — verify FAIL → implement auto-flush hook → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-6, AC-7.

## WHY THIS APPROACH
Complexity: medium
Justification: memakai `useOnlineStatus` (T10) + `orderQueue` (T11); tidak mengubah `OrderCreationService`.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah perilaku submit online normal; offline hanya jalur tambahan]
Files in scope: `apps/web/src/components/OrderForm.tsx`, test.
Available after: T10 (online status), T11 (queue).
Architecture rule: bila dummy mode ON, offline queue dinonaktifkan (dummy sudah zero-network).

## DELIVERABLE
- Submit offline → enqueue + toast "Menunggu sinkronisasi".
- `online` event → auto-flush + refresh daftar.
- Indikator jumlah item tertunda.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Jalur online tidak berubah (test lama tetap hijau).
  - Tidak enqueue saat dummy ON.
Must-not-have:
  - Duplikasi order (idempotency key dari T11).
Open question risks:
  - Sinkron dengan refresh dummy (`useDummyRefresh`).
Rollback note:
  - Hapus guard offline; kembali ke perilaku lama.

## STOP CONDITIONS
Done when: submit offline + auto-flush PASS; test OrderForm lama tetap hijau.
Escalate when: kontrak `OrderForm` tidak memungkinkan injeksi queue.

---

### Task 13: Halaman admin roster driver

## OBJECTIVE
Buat halaman `/admin/drivers` dengan tabel roster (kontrak admin-table: sort, paging, ringkasan, density), form create/edit, dan modul `api.ts` yang memakai `withDummyRead`.

Steps:
1. Write failing test for: loader + tabel.
   Test file: `apps/web/src/app/admin/drivers/page.test.tsx`
   Level: component
   Test intent: Given mock `listDrivers` mengembalikan 25 baris / When render halaman / Then tabel menampilkan baris + `TablePagination` + `TableSummary`; When klik kolom "Plat" / Then `listDrivers` dipanggil dengan sort param.
   Exercise through: render page + `@testing-library`.
   Test doubles: mock `@/app/admin/drivers/api`.
   Expected RED: halaman belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/drivers`
3. Implement `api.ts` + `page.tsx` (pakai `admin-table.ts` + `Table*`) → PASS → refactor → commit.

4. Write failing test for: create/edit form.
   Test intent: Given form create / When submit valid / Then `createDriver` dipanggil; Given error 422 / Then pesan validasi tampil.
5. Run test — verify FAIL → implement form → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-7; pola `apps/web/src/app/admin/outlets/page.tsx` + `admin-table.ts` + `Table*` (plan `2026-09-17-admin-table-ux`).

## WHY THIS APPROACH
Complexity: medium
Justification: mengikuti pola halaman admin yang sudah ada (kontrak tabel + api module) agar konsisten.

## SANDWICH CONTEXT
[CRITICAL: pakai helper `admin-table.ts` + komponen `Table*`; jangan reinvent sort/paging]
Files in scope: `apps/web/src/app/admin/drivers/page.tsx`, `apps/web/src/app/admin/drivers/api.ts`, test.
Available after: T5 (endpoint).
Architecture rule: `withDummyRead` untuk read; dummy fixture disediakan di T17.

## DELIVERABLE
- `api.ts`: `listDrivers(params)`, `createDriver`, `updateDriver`, `deleteDriver` (guard dummy).
- `page.tsx`: tabel + pagination + summary + density + modal form.
- Kolom: nama driver, kendaraan, plat, wilayah, kapasitas, shift, status ketersediaan.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Kontrak admin-table (sort allowlist, offset paging, meta.total).
  - Loading/error/empty state.
Must-not-have:
  - Fetch langsung tanpa guard dummy.
Open question risks:
  - Field opsional (kapasitas/shift) tampil "-" bila null.
Rollback note:
  - Hapus folder halaman.

## STOP CONDITIONS
Done when: loader + tabel + form PASS; `tsc --noEmit` bersih.
Escalate when: endpoint T5 belum final.

---

### Task 14: UI check-in/out kunjungan sales

## OBJECTIVE
Tambahkan UI check-in/check-out pada halaman kunjungan sales (`/sales`) yang meminta izin geolokasi, menampilkan status, dan memanggil endpoint T6.

Steps:
1. Write failing test for: tombol check-in meminta geolokasi.
   Test file: `apps/web/src/app/sales/checkin.test.tsx`
   Level: component
   Test intent: Given visit `planned` / When klik "Check-in" / Then `navigator.geolocation.getCurrentPosition` dipanggil; When sukses / Then `checkInVisit(id, coords)` dipanggil + status berubah.
   Exercise through: render + click + mock geolocation.
   Test doubles: mock `navigator.geolocation`, mock api.
   Expected RED: UI belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/sales/checkin.test.tsx`
3. Implement tombol + geolocation + panggilan api → PASS → refactor → commit.

4. Write failing test for: error geolokasi + di luar radius.
   Test intent: Given geolocation error (permission denied) / Then pesan error tampil, tidak memanggil api; Given api 422 radius / Then pesan "Di luar radius outlet".
5. Run test — verify FAIL → implement error handling → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-2, AC-7, A-1.

## WHY THIS APPROACH
Complexity: medium
Justification: memakai `navigator.geolocation` bawaan browser; tidak ada dependency peta baru untuk check-in.

## SANDWICH CONTEXT
[CRITICAL: jangan panggil api bila geolokasi gagal; tampilkan pesan jelas]
Files in scope: `apps/web/src/app/sales/page.tsx` (atau komponen baru `VisitCheckin.tsx`), `apps/web/src/app/sales/api.ts`, test.
Available after: T6 (endpoint).
Architecture rule: guard dummy (check-in dummy = fake sukses); timeout geolokasi 10s.

## DELIVERABLE
- Tombol Check-in/Check-out dengan status loading/disabled.
- Permintaan izin geolokasi + fallback error.
- Tampilan `check_in_at`/`check_out_at` bila sudah terisi.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Geolokasi gagal → tidak memanggil API.
  - Pesan error radius ditampilkan.
Must-not-have:
  - Menambah library peta untuk check-in.
Open question risks:
  - Izin lokasi ditolak permanen → sediakan pesan panduan.
Rollback note:
  - Hapus UI + api method.

## STOP CONDITIONS
Done when: check-in sukses + error geolokasi + error radius PASS.
Escalate when: kontrak endpoint T6 berubah.

---

### Task 15: UI capture PoD driver

## OBJECTIVE
Buat komponen capture Proof of Delivery untuk driver: ambil foto (kamera/file input), gambar tanda tangan pada `<canvas>`, lalu unggah ke endpoint T8.

Steps:
1. Write failing test for: submit foto + tanda tangan.
   Test file: `apps/web/src/components/delivery/PodCapture.test.tsx`
   Level: component
   Test intent: Given komponen render / When set foto (file) + gambar tanda tangan + klik submit / Then `uploadProof(deliveryId, {photo, signature})` dipanggil.
   Exercise through: render + fireEvent + mock canvas `toBlob`.
   Test doubles: mock `HTMLCanvasElement.prototype.toBlob`, mock api.
   Expected RED: komponen belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/delivery/PodCapture.test.tsx`
3. Implement kamera input + canvas tanda tangan + submit → PASS → refactor → commit.

4. Write failing test for: disable submit bila belum lengkap.
   Test intent: Given tanpa foto / Then tombol submit disabled; Given tanpa tanda tangan / Then disabled; Given keduanya ada / Then enabled.
5. Run test — verify FAIL → implement guard → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-5, AC-7, DD-3.

## WHY THIS APPROACH
Complexity: medium
Justification: canvas tanda tangan + `<input type=file accept=image/* capture=environment>` untuk kamera; tanpa dependency baru.

## SANDWICH CONTEXT
[CRITICAL: jangan kirim bila salah satu bukti kosong; canvas harus mengembalikan blob PNG]
Files in scope: `apps/web/src/components/delivery/PodCapture.tsx`, `apps/web/src/app/delivery/api.ts`, test.
Available after: T8 (endpoint upload).
Architecture rule: kirim `multipart/form-data`; guard dummy (fake sukses); reset setelah sukses.

## DELIVERABLE
- Komponen `PodCapture`: input foto (preview), canvas tanda tangan (clear), tombol submit.
- `uploadProof(id, {photo, signature, latitude?, longitude?})` di `delivery/api.ts`.
- Disabled state + pesan sukses/gagal.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Submit disabled sampai foto + tanda tangan ada.
  - Canvas mengembalikan PNG blob non-kosong.
Must-not-have:
  - Menyimpan foto di state global/dummy store.
Open question risks:
  - Ukuran canvas/high-DPI dapat memperbesar blob → batasi dimensi.
Rollback note:
  - Hapus komponen + api method.

## STOP CONDITIONS
Done when: submit + disabled guard PASS; `tsc --noEmit` bersih.
Escalate when: canvas tidak tersedia (SSR) → guard `typeof document`.

---

### Task 16: Halaman live tracking admin

## OBJECTIVE
Buat halaman tracking delivery untuk admin: peta `GeoMap` menampilkan posisi terakhir driver + polling berkala ke `GET /admin/deliveries/{id}/track`.

Steps:
1. Write failing test for: render posisi + polling.
   Test file: `apps/web/src/app/admin/tracking/page.test.tsx`
   Level: component
   Test intent: Given mock `getTrack` mengembalikan `last_position` / When render / Then `GeoMap` menerima 1 titik; When fake timer maju interval / Then `getTrack` dipanggil lagi.
   Exercise through: render + jest fake timers.
   Test doubles: mock `@/app/admin/tracking/api`, mock `GeoMap`.
   Expected RED: halaman belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/tracking`
3. Implement halaman + polling + GeoMap → PASS → refactor → commit.

4. Write failing test for: tanpa posisi.
   Test intent: Given `last_position` null / Then tampil state "Belum ada lokasi" tanpa error; polling tetap berjalan.
5. Run test — verify FAIL → implement empty state → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-4, AC-7, DD-5; `apps/web/src/components/data-intelligence/GeoMap.tsx`.

## WHY THIS APPROACH
Complexity: medium
Justification: reuse `GeoMap` (Leaflet) yang sudah ada; polling interval tetap (bukan WebSocket).

## SANDWICH CONTEXT
[CRITICAL: bersihkan interval saat unmount; guard dummy; jangan render peta bila tak ada titik]
Files in scope: `apps/web/src/app/admin/tracking/page.tsx`, `apps/web/src/app/admin/tracking/api.ts`, test.
Available after: T7 (endpoint track).
Architecture rule: `GeoMap` menerima array `{latitude,longitude,label?}`; interval mis. 15s; dummy mode → fixture statis.

## DELIVERABLE
- Halaman `/admin/tracking` (atau `/admin/deliveries/[id]/tracking`): pilih delivery → peta + info.
- Polling dengan cleanup; indikator update terakhir.
- `getTrack(deliveryId)` di `api.ts` (guard dummy).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Interval dibersihkan saat unmount.
  - Empty state tanpa error.
Must-not-have:
  - WebSocket/push.
Open question risks:
  - Rute halaman (list delivery vs deep link) — asumsi deep link `/admin/deliveries/{id}/tracking` + tautan dari daftar delivery.
Rollback note:
  - Hapus halaman + api method.

## STOP CONDITIONS
Done when: render posisi + polling + empty state PASS.
Escalate when: `GeoMap` butuh perubahan kontrak.

---

### Task 17: Dummy fixtures + NavItem + RBAC page wiring

## OBJECTIVE
Lengkapi parity dummy mode untuk seluruh permukaan Phase 8 (roster, check-in, PoD, tracking) dan daftarkan menu baru (`driver_roster`, `field_ops`) di `NavItem` frontend sehingga Sidebar/RBAC page menampilkannya sesuai role.

Steps:
1. Write failing test for: dummy roster + tracking fixture.
   Test file: `apps/web/src/dummy/field-ops.test.ts`
   Level: unit
   Test intent: Given dummy mode ON / When `listDrivers()` / Then mengembalikan fixture deterministik tanpa fetch; When `getTrack(id)` / Then fixture posisi ada.
   Exercise through: dummy api modules + store.
   Test doubles: dummy store.
   Expected RED: fixture belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/field-ops.test.ts`
3. Implement fixture `fieldOps` di `dummy/aggregates.ts` + guard di modul api → PASS → commit.

4. Write failing test for: NavItem + RBAC visibility.
   Test intent: Given matrix admin / When render Sidebar / Then "Operasi Lapangan" & "Roster Driver" tampil; Given role driver dengan field_ops read / Then "Operasi Lapangan" tampil, "Roster Driver" tidak.
   Test file: `apps/web/src/components/Sidebar.test.tsx` (tambahan).
5. Run test — verify FAIL → tambah NavItem + key → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-7, AC-8; `apps/web/src/dummy/*`, `Sidebar.tsx`, `NavItem`, `MenuDefinition::CATALOG` (T4).

## WHY THIS APPROACH
Complexity: medium
Justification: parity dummy adalah kontrak repo; menu baru butuh NavItem agar Sidebar matrix-driven menampilkannya.

## SANDWICH CONTEXT
[CRITICAL: dummy ON = zero network; key NavItem harus sama dengan CATALOG (`driver_roster`, `field_ops`)]
Files in scope: `apps/web/src/dummy/aggregates.ts`, `apps/web/src/dummy/index.ts`, `apps/web/src/components/Sidebar.tsx` (+ NavItem list), test.
Available after: T13–T16.
Architecture rule: fixture deterministik (seeded RNG); `platform_owner` setara admin untuk `adminOnly`.

## DELIVERABLE
- Fixture: driver roster (≥8), tracking pings, visit dengan check-in, PoD sample.
- NavItem `field_ops` + `driver_roster` (grup & adminOnly sesuai CATALOG).
- Semua modul api Phase 8 memakai `withDummyRead`.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Zero network saat dummy ON (diverifikasi test).
  - Tidak ada state kosong saat ON.
  - Key menu sinkron backend↔frontend.
Must-not-have:
  - Duplikasi logika RBAC di frontend (pakai map dari store).
Open question risks:
  - Label menu final (id-ID) — samakan dengan CATALOG T4.
Rollback note:
  - Hapus fixture + NavItem entries.

## STOP CONDITIONS
Done when: dummy test + Sidebar visibility test PASS.
Escalate when: key CATALOG T4 berbeda dari NavItem.

---

### Task 18: Integrasi lintas unit + verifikasi suite penuh [test-risk]

## OBJECTIVE
Verifikasi skenario lintas unit end-to-end Phase 8 dan jalankan seluruh suite (API + web + TypeScript) untuk memastikan tidak ada regresi.

Steps:
1. Write failing test for: skenario lintas unit (offline → flush → tracking → PoD).
   Test file: `apps/api/tests/Feature/FieldOps/FieldOpsIntegrationTest.php` + `apps/web/src/app/__tests__/field-ops-integration.test.tsx`
   Level: integration
   Test intent:
   - API: Given sales check-in visit lalu driver kirim ping lalu upload PoD / When alur dijalankan berurutan / Then semua state tersimpan konsisten dan endpoint track mengembalikan posisi terakhir.
   - Web: Given offline order di antrean / When online + flush / Then order terkirim dan muncul di daftar (mock fetch end-to-end).
   Exercise through: HTTP (API) + render (web).
   Test doubles: `Storage::fake`, mock geolocation, mock online event.
   Expected RED: skenario belum tertutup (fails karena wiring belum lengkap).
2. Run test — verify FAIL.
3. Perbaiki wiring yang kurang → PASS → refactor → commit.

4. Jalankan suite penuh dan catat hasil.
   - `cd apps/api && php artisan test`
   - `cd apps/web && npx jest`
   - `cd apps/web && npx tsc --noEmit`
5. Jika ada regresi → perbaiki; ulangi sampai 0 failed.
6. Tulis ringkasan verifikasi (jumlah test, skip pgsql-only, hasil tsc) → commit.

## REFERENCES LOADED
Spec Phase 8 — seluruh Acceptance Criteria; pola verifikasi closeout plan lain (mis. `2026-09-18-rbac-menu-matrix/closeout.md`).

## WHY THIS APPROACH
Complexity: medium
Justification: fitur Phase 8 menyentuh 3 unit (API, PWA client, dummy parity); integrasi lintas unit adalah satu-satunya cara membuktikan alur lapangan end-to-end.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah fitur untuk "mempermudah" test; perbaiki wiring nyata]
Files in scope: `apps/api/tests/Feature/FieldOps/FieldOpsIntegrationTest.php`, `apps/web/src/app/__tests__/field-ops-integration.test.tsx`.
Available after: T12 (offline), T17 (dummy/RBAC).
Architecture rule: test integrasi tidak boleh bergantung pada layanan eksternal; pgsql-only skip diizinkan.

## DELIVERABLE
- 1 test integrasi API + 1 test integrasi web (lintas unit).
- Laporan hasil suite: API (passed/skipped/failed), web (passed/failed), tsc clean.
- Tidak ada regresi pada test lama.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Alur offline→flush→tracking→PoD terverifikasi.
  - Suite penuh 0 failed (skip pgsql-only diperbolehkan).
Must-not-have:
  - Menonaktifkan/menghapus test lama untuk hijau.
Open question risks:
  - Flakiness timer (polling/flush) → pakai fake timers deterministik.
Rollback note:
  - Test bersifat additive.

## STOP CONDITIONS
Done when: integrasi lintas unit PASS + suite penuh 0 failed + tsc clean.
Uncertain when: regresi pada test lama yang tidak terkait Phase 8.
Escalate when: dibutuhkan perubahan kontrak lintas fitur.
