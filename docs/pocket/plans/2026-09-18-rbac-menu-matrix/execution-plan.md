# EXECUTION PLAN — RBAC Menu Access Matrix (DB-backed role × menu → none|read|edit)

**Date:** 2026-09-18
**Spec:** docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
**Pitch:** docs/pocket/spec/2026-09-18-rbac-menu-matrix/pitch-exploration.md
**Status:** draft
**Total tasks:** 12

---

## Execution Overview

### Recommended Order
```
T1 ─┬─→ T2 (test infra — keep existing suite green)
    ├─→ T3 ─┬─→ T4
    │       └─→ T6 ──→ T7
    └─→ T5
T8 (independent) ──→ T9 ─┬─→ T10
                         └─→ T11
T6 + T7 + T10 + T11 ──→ T12
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism/sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T8 | (start) |
| Group B | T2, T3, T5 | T1 |
| Group C | T4, T6 | T3 |
| Group D | T7 | T6 |
| Group E | T9 | T5, T8 |
| Group F | T10, T11 | T9 |
| Group G | T12 | T6, T7, T10, T11 |

### Constraints Reminder
**Architecture:**
- Boleh disentuh: `apps/api` (migrasi, model, middleware, controller, routes, seeder, `AuthController@me`), `apps/web` (`Sidebar.tsx`, `dummy/*`, store Zustand baru, halaman `/admin/rbac`), `apps/api/tests/*`.
- **TIDAK boleh** disentuh: `docker-compose.yml`, nginx, Telescope, pipeline data-intelligence (`DataPipeline*`, `data_snapshots`), skema/migrasi non-RBAC, logika bisnis `Order`/`Invoice`/`Payment`.
- Migrasi **aditif & non-transaksional** (Postgres enum). Dua tabel baru saja; tidak mengubah tabel lama.
- Business logic tetap di service layer; controller tipis. Authorization terpusat (`FinanceAuthorizationService` / `UserPolicy`).
- Kontrak paginasi existing tidak berubah; endpoint RBAC mengembalikan objek, bukan list berpaginasi.

**Keputusan yang sudah dikunci (dari sesi planning):**
1. **Menu `worktree` ditambahkan** → total **19 menu** (spec menulis 18; `Sidebar.tsx` punya 19 item). `worktree` di-seed `read` untuk **semua 7 role**.
2. **Enforcement route: Full** — semua route terproteksi dianotasi `rbac:<menu_key>:<level>`; `deny.finance` **diganti** (tidak lagi dipakai).
3. **Ikuti default matrix spec; perbarui test lama** yang mengasumsikan guard lama (terutama `FinanceAccessTest`).

**Dua kontradiksi internal spec yang WAJIB diselesaikan (dokumentasi, bukan asumsi diam):**
- **K-A — `rbac_matrix` untuk `admin`.** Tabel default spec memberi `admin → rbac_matrix = read`, tetapi Story 2 mengharuskan `admin` bisa `PUT /admin/rbac/matrix` (butuh *edit*). **Resolusi:** endpoint RBAC (`GET`/`PUT /admin/rbac/matrix`) **tidak** memakai `rbac:` middleware; dijaga controller-level `assertAdminOrOwner`. Level matrix `rbac_matrix` hanya mengatur **visibilitas menu**, bukan mutasi endpoint. Ini menjaga tabel default spec tetap utuh.
- **K-B — `admin_users` untuk `admin`.** Default spec memberi `admin → admin_users = read`, tetapi `admin` harus bisa `POST/DELETE /admin/users/{id}/finance-role` (operasi *edit*). **Resolusi:** route mutasi `admin_users` (`finance-role`, `PATCH role`) memakai `rbac:admin_users:read` sebagai gerbang menu, lalu **controller-level `assertAdmin`/`isPlatformOwner`** tetap menjadi otorisasi mutasi sebenarnya (perilaku existing dipertahankan). `GET /admin/users` memakai `rbac:admin_users:read`.

**Aturan level:** ordinal `none < read < edit`. `required=read` lolos bila tersimpan `read` atau `edit`; `required=edit` hanya lolos bila tersimpan `edit`. Baris tidak ada → `none`.

**Assumptions at risk:**
- **Seeding di test suite.** 49 feature test memakai `RefreshDatabase` **tanpa** memanggil seeder. Karena "baris tidak ada → none → 403", middleware RBAC akan mem-403 **seluruh** suite bila matrix tidak di-seed. **T2 wajib** menambahkan seeding default di base `Tests\TestCase::setUp()` (guard `Schema::hasTable`). Tanpa T2, T6/T7 tidak bisa hijau.
- `menu_definitions.key` = string PK; `role_menu_access` PK komposit `(role, menu_key)` FK ke `menu_definitions.key`. Kompatibel SQLite (`:memory:`) dan PostgreSQL.
- `User.role` tetap enum string 7 nilai; `role` di `role_menu_access` **tanpa** FK ke `users` (role hardcoded).
- Test suite berjalan di SQLite `:memory:` (`phpunit.xml.dist` force) — hindari fitur Postgres-only di migrasi.

**Out-of-scope (tidak boleh disentuh task mana pun):** audit log perubahan matrix, hot-reload tanpa re-fetch, role template, guard tombol/aksi per-halaman, level granular (create/update/delete/approve terpisah).

**Commit message convention (conventional commits):**
- T1 → `feat(api): add rbac schema, models, and default matrix seeder`
- T2 → `test(api): seed rbac defaults in base test case`
- T3 → `feat(api): add rbac route middleware and kernel alias`
- T4 → `feat(api): add rbac matrix get/put endpoints`
- T5 → `feat(api): include rbac map in auth/me`
- T6 → `feat(api): enforce rbac on protected routes, remove deny.finance`
- T7 → `test(api): align existing tests with rbac default matrix`
- T8 → `feat(web): add dummy rbac matrix fixture`
- T9 → `feat(web): add useRbacStore`
- T10 → `refactor(web): drive sidebar visibility from rbac matrix`
- T11 → `feat(web): add admin rbac matrix page`
- T12 → `test(web): rbac integration + dummy parity`

### File Structure Map

```
Rule: rbac-schema (shared)
  Create: apps/api/database/migrations/2026_09_18_000001_create_rbac_tables.php   (T1)
  Create: apps/api/app/Models/MenuDefinition.php                                   (T1)
  Create: apps/api/app/Models/RoleMenuAccess.php                                   (T1)
  Create: apps/api/database/seeders/RbacMatrixSeeder.php                           (T1)
  Modify: apps/api/database/seeders/DatabaseSeeder.php                             (T1)
  Test:   apps/api/tests/Unit/RbacMatrixSeederTest.php                             (T1)

Rule: rbac-test-infra
  Modify: apps/api/tests/TestCase.php                                             (T2)

Rule: rbac-middleware
  Create: apps/api/app/Http/Middleware/Rbac.php                                   (T3)
  Modify: apps/api/app/Http/Kernel.php                                             (T3)
  Test:   apps/api/tests/Feature/RbacMiddlewareTest.php                           (T3)

Rule: rbac-matrix-api
  Create: apps/api/app/Http/Controllers/RbacMatrixController.php                  (T4)
  Create: apps/api/app/Http/Requests/UpdateRbacMatrixRequest.php                  (T4)
  Modify: apps/api/routes/api.php                                                 (T4)
  Test:   apps/api/tests/Feature/RbacMatrixEndpointTest.php                       (T4)

Rule: auth-me-rbac
  Modify: apps/api/app/Http/Controllers/AuthController.php                        (T5)
  Modify: apps/api/app/Services/... (helper resolver — lihat T5)                  (T5)
  Test:   apps/api/tests/Feature/AuthMeRbacTest.php                               (T5)

Rule: rbac-route-enforcement
  Modify: apps/api/routes/api.php                                                 (T6)
  Modify: apps/api/app/Http/Kernel.php                                            (T6)
  Delete: apps/api/app/Http/Middleware/DenyFinanceAdministration.php              (T6)
  Test:   apps/api/tests/Feature/RbacRouteEnforcementTest.php                     (T6)

Rule: rbac-existing-tests
  Modify: apps/api/tests/Feature/FinanceAccessTest.php                            (T7)
  Modify: apps/api/tests/Feature/* (hanya yang terbukti berubah — lihat T7)        (T7)

Rule: dummy-rbac
  Create: apps/web/src/dummy/rbac.ts                                              (T8)
  Create: apps/web/src/dummy/rbac.test.ts                                         (T8)

Rule: rbac-store
  Create: apps/web/src/store/useRbacStore.ts                                      (T9)
  Create: apps/web/src/store/useRbacStore.test.ts                                 (T9)

Rule: sidebar-matrix
  Modify: apps/web/src/components/Sidebar.tsx                                     (T10)
  Modify: apps/web/src/components/Sidebar.test.tsx                                (T10)

Rule: rbac-admin-page
  Create: apps/web/src/app/admin/rbac/page.tsx                                    (T11)
  Create: apps/web/src/app/admin/rbac/api.ts                                      (T11)
  Create: apps/web/src/app/admin/rbac/page.test.tsx                               (T11)

Rule: rbac-integration
  Create: apps/web/src/__tests__/rbac-integration.test.tsx                        (T12)
```

### Default Matrix (Seed) — 19 menu × 7 role

| key | label | group | platform_owner | admin | outlet | supplier | sales | driver | finance |
|-----|-------|-------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| dashboard | Dasbor | operasional | edit | edit | edit | edit | edit | edit | read |
| orders | Pesanan | operasional | edit | edit | edit | read | edit | read | read |
| products | Produk | operasional | edit | edit | read | read | read | read | read |
| outlets | Outlet | operasional | edit | edit | read | — | read | — | read |
| marketplace | Marketplace | operasional | edit | edit | edit | edit | edit | — | read |
| payments | Pembayaran | operasional | edit | edit | edit | — | read | — | edit |
| delivery | Pengiriman | operasional | edit | edit | read | — | read | edit | read |
| sales | Sales | operasional | edit | edit | read | — | edit | — | read |
| invoices | Invoice | operasional | edit | edit | read | — | — | — | edit |
| worktree | Worktree | operasional | read | read | read | read | read | read | read |
| analytics | Analitik | analitik | edit | read | — | — | — | — | — |
| data_intelligence | Data Intelligence | analitik | edit | read | — | — | — | — | — |
| operations | Operasi | analitik | edit | read | — | — | — | — | — |
| admin_orders | Approval Pesanan | admin | edit | edit | — | — | — | — | — |
| admin_products | Harga Produk | admin | edit | edit | — | — | — | — | — |
| admin_users | Kelola Pengguna | admin | edit | read | — | — | — | — | — |
| admin_promotions | Kelola Promosi | admin | edit | edit | — | — | — | — | — |
| admin_sales_performance | Performa Sales | admin | edit | edit | — | — | — | — | — |
| rbac_matrix | Kelola Akses | admin | edit | read | — | — | — | — | — |

`—` = `none`. `worktree` ditambahkan oleh keputusan #1 (spec 18 → 19).

> **Catatan jumlah:** tabel default spec (18 menu) menghasilkan **70** sel non-`none` (spec menyebut "~50" — angka spec tidak akurat; pakai 70). Dengan `worktree` (7 sel) total = **77**. T1 memverifikasi `RoleMenuAccess::where('level','!=','none')->count() === 77`.

### Route → menu_key / required_level mapping (untuk T6)

| Route (method path) | menu_key | level | Catatan |
|---------------------|----------|-------|---------|
| PATCH /admin/products/{id} | admin_products | edit | |
| GET /admin/products/{id}/prices | admin_products | read | |
| GET /admin/outlets | outlets | read | |
| POST /admin/outlets | outlets | edit | |
| PATCH /admin/outlets/{id} | outlets | edit | |
| GET /admin/outlets/{outletId}/orders | outlets | read | |
| GET /admin/outlets/{outletId}/summary | outlets | read | |
| GET /sales/outlets | sales | read | |
| POST /sales/orders | sales | edit | |
| GET /sales/my-performance | sales | read | |
| POST /admin/sales-targets | admin_sales_performance | edit | |
| GET /admin/sales-targets | admin_sales_performance | read | |
| GET /admin/sales-targets/{id} | admin_sales_performance | read | |
| PATCH /admin/sales-targets/{id} | admin_sales_performance | edit | |
| DELETE /admin/sales-targets/{id} | admin_sales_performance | edit | |
| GET /admin/sales/performance | admin_sales_performance | read | |
| GET /admin/promotions | admin_promotions | read | |
| POST /admin/promotions | admin_promotions | edit | |
| GET /admin/promotions/{id} | admin_promotions | read | |
| PATCH /admin/promotions/{id} | admin_promotions | edit | |
| DELETE /admin/promotions/{id} | admin_promotions | edit | |
| POST /admin/promotions/{id}/broadcast | admin_promotions | edit | |
| POST /admin/users/{userId}/finance-role | admin_users | read | mutasi via controller `assertAdmin` (K-B) |
| DELETE /admin/users/{userId}/finance-role | admin_users | read | mutasi via controller `assertAdmin` (K-B) |
| GET /admin/users | admin_users | read | |
| PATCH /admin/users/{userId}/role | admin_users | read | mutasi via controller `isPlatformOwner` (K-B) |
| GET /admin/outlets/{outletId}/payment-terms | outlets | read | |
| PUT /admin/outlets/{outletId}/payment-terms | outlets | edit | |
| POST /admin/outlets/{outletId}/payment-terms | outlets | edit | |
| POST /outlets | — | — | self-service; gate dihapus (K-C), `StoreOutletRequest::authorize()` menolak finance | |
| GET /products | products | read | |
| GET /marketplace/suppliers | marketplace | read | |
| GET /marketplace/products | marketplace | read | |
| POST /orders | orders | edit | |
| GET /orders | orders | read | controller tetap admin-only |
| GET /orders/{id} | orders | read | |
| GET /admin/orders | admin_orders | read | |
| GET /admin/orders/{id} | admin_orders | read | |
| PUT /orders/{id}/approve | admin_orders | edit | |
| PUT /orders/{id}/cancel | orders | edit | |
| GET /invoices | invoices | read | |
| GET /sales/visits | sales | read | |
| POST /sales/visits | sales | edit | |
| GET /sales/visits/{id} | sales | read | |
| PATCH /sales/visits/{id} | sales | edit | |
| GET /deliveries | delivery | read | |
| POST /deliveries | delivery | edit | |
| GET /deliveries/{id} | delivery | read | |
| PATCH/POST/PUT /deliveries/{id}/status & /deliveries/{id} | delivery | edit | |
| POST /payments | payments | edit | |
| GET /payments | payments | read | |
| GET /analytics/dashboard | analytics | read | |
| GET /analytics/insight | analytics | read | |
| GET /ai/recommendations | — | — | dual-audience; gate dihapus (K-C), `AIController` mengizinkan admin/outlet |
| GET /ai/forecast | — | — | dual-audience; gate dihapus (K-C) |
| GET /ai/segmentation | — | — | dual-audience; gate dihapus (K-C) |
| POST /whatsapp/catalog | orders | edit | |
| POST /whatsapp/orders/{orderId}/notification | orders | edit | |
| POST /whatsapp/messages/{messageId}/retry | orders | edit | |
| GET /finance/metrics | invoices | read | |
| GET /finance/reminders | invoices | read | |
| GET /reminders | invoices | read | |
| GET /finance/access | — | — | controller `assertFinance` |
| GET /admin/pipeline/status | data_intelligence | read | |
| POST /admin/pipeline/manual-trigger | data_intelligence | read | mutasi via controller `isAdmin` (K-C) | |
| GET /admin/analytics/geographic | data_intelligence | read | |
| GET /admin/territories | analytics | read | |
| POST /admin/territories | analytics | read | mutasi via controller `isAdmin` (K-C) | |
| PATCH /admin/territories/{territoryId} | analytics | read | mutasi via controller `isAdmin` (K-C) | |
| POST /admin/territories/{territoryId}/assign | analytics | read | mutasi via controller `isAdmin` (K-C) | |
| GET /admin/analytics/suppliers | data_intelligence | read | |
| GET /admin/analytics/stock-planning | data_intelligence | read | |
| POST /admin/measurement/events | data_intelligence | read | mutasi via controller `isAdmin` (K-C) | |
| GET /admin/analytics/measurement/recommendations | data_intelligence | read | |
| GET /admin/analytics/measurement/forecasts | data_intelligence | read | |
| GET /admin/operations/readiness | operations | read | |
| GET /admin/operations/issues | operations | read | |
| GET /admin/operations/issues/{id} | operations | read | |
| GET /credit-limit | outlets | read | |
| GET /admin/outlets/{outletId}/credit-limit | outlets | read | |
| PUT /admin/outlets/{outletId}/credit-limit | outlets | edit | |
| POST /admin/outlets/{outletId}/credit-limit | outlets | edit | |
| GET/PATCH /auth/me, POST /auth/logout, POST /auth/refresh | — | — | hanya `auth:api` |
| GET /admin/rbac/matrix, PUT /admin/rbac/matrix | — | — | controller `assertAdminOrOwner` (K-A) |

> **Catatan pemetaan yang perlu dikonfirmasi saat eksekusi:** `/credit-limit*` → `outlets`; `/admin/territories*` → `analytics`; `/admin/analytics/*` + `/ai/*` + `/admin/pipeline/*` → `data_intelligence`; `/finance/*` + `/reminders` → `invoices`; `/whatsapp/*` → `orders`. Bila ada route baru yang tidak terdaftar, default-kan ke menu terdekat dan catat di log.

---

## Pocket Packets

---

### Task 1: RBAC schema + models + default matrix seeder [prereq]

## OBJECTIVE
Buat skema RBAC (`menu_definitions`, `role_menu_access`), dua model Eloquent, dan seeder idempotent `RbacMatrixSeeder` yang memuat 19 menu × 7 role sesuai tabel default di Execution Overview. Daftarkan seeder di `DatabaseSeeder`.

Steps:
1. Write failing test for: seeder mengisi 19 menu & jumlah sel non-none yang benar
   Test file: `apps/api/tests/Unit/RbacMatrixSeederTest.php`
   Level: unit (RefreshDatabase)
   Test intent: Given DB kosong / When `$this->seed(RbacMatrixSeeder::class)` / Then `MenuDefinition::count() === 19`; `RoleMenuAccess::where('level','!=','none')->count() === 77` (70 sel dari 18 menu spec + 7 `worktree`; hitung ulang dari tabel default bila ragu); tiap `menu_key` di `role_menu_access` ada di `menu_definitions`.
   Exercise through: `RbacMatrixSeeder` + model
   Test doubles: none
   Expected RED: tabel/`RbacMatrixSeeder` belum ada → `QueryException`/`Class not found`.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacMatrixSeederTest`
3. Implement migrasi + model + seeder → PASS → refactor → commit.

4. Write failing test for: idempotensi
   Test file: `apps/api/tests/Unit/RbacMatrixSeederTest.php`
   Level: unit
   Test intent: Given seeder dijalankan dua kali / When `$this->seed(...)` dipanggil lagi / Then tidak ada baris duplikat (`MenuDefinition::count()` tetap 19, `RoleMenuAccess::count()` tidak berubah) dan tidak error.
   Exercise through: `RbacMatrixSeeder` (`firstOrCreate`/`upsert`)
   Test doubles: none
   Expected RED: seeder belum idempotent.
5. Run test — verify FAIL → implement idempotent upsert → PASS → refactor → commit.

6. Write failing test for: level constraint & struktur
   Test file: `apps/api/tests/Unit/RbacMatrixSeederTest.php`
   Level: unit
   Test intent: Given seeder selesai / When query / Then semua `level ∈ {none,read,edit}`; `platform_owner` = `edit` di 18 menu non-worktree; `worktree` = `read` untuk 7 role; `admin → rbac_matrix = read` dan `admin → admin_users = read` (sesuai spec, jangan "dinaikkan").
   Exercise through: `RoleMenuAccess`
   Test doubles: none
   Expected RED: data belum ada.
7. Run test — verify FAIL → implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Scope / In-Scope", "Architecture Constraints", "Default Matrix (Seed)", "Story 6".
docs/pocket/spec/2026-09-18-rbac-menu-matrix/pitch-exploration.md — arah Direction A.

## WHY THIS APPROACH
Complexity: standard
Justification: Skema + seeder adalah fondasi semua task lain. `firstOrCreate`/`upsert` idempoten mencegah duplikasi saat `db:seed` diulang (Story 6). Default matrix diambil literal dari spec (+ `worktree`) agar tidak ada drift.

## SANDWICH CONTEXT
[CRITICAL: migrasi aditif & non-transaksional; jangan ubah tabel lama; level hanya none|read|edit; ikuti tabel default spec apa adanya]
You are implementing RBAC schema + seeder untuk fitur RBAC Menu Matrix.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: Direction A (DB-backed), 2 tabel (`menu_definitions`, `role_menu_access`), PK komposit `(role, menu_key)`.
Files in scope: `apps/api/database/migrations/2026_09_18_000001_create_rbac_tables.php`, `apps/api/app/Models/MenuDefinition.php`, `apps/api/app/Models/RoleMenuAccess.php`, `apps/api/database/seeders/RbacMatrixSeeder.php`, `apps/api/database/seeders/DatabaseSeeder.php`, `apps/api/tests/Unit/RbacMatrixSeederTest.php`
Available after: none (prereq)
Architecture rule: `menu_definitions.key` string PK; `role_menu_access` FK `menu_key` → `menu_definitions.key`; `role` tanpa FK; kompatibel SQLite in-memory.
[RESTATE: migrasi aditif; level none|read|edit; ikuti tabel default spec]

## DELIVERABLE
Given DB kosong, When migrate + seed, Then 19 `menu_definitions` + 77 `role_menu_access` non-none (70 dari 18 menu spec + 7 `worktree`).
Given seeder diulang, Then idempoten (tanpa duplikat, tanpa error).
Given matrix di-query, Then `level` hanya none|read|edit; `platform_owner=edit` di semua menu non-worktree.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Dua tabel dengan PK/constraint persis spec; `role_menu_access` PK komposit.
  - Jumlah sel non-none = 77 (70 spec + 7 worktree).
  - Seeder idempoten (`firstOrCreate`/`upsert`), dipanggil dari `DatabaseSeeder::run()`.
  - Jumlah baris default persis sesuai tabel (19 menu; 77 sel non-`—` = 70 + 7 `worktree`).
Must-not-have:
  - Menyentuh tabel/model/migrasi lain; mengubah `User`/role enum.
  - Menambah kolom `audit`/`updated_by` (out-of-scope).
Open question risks:
  - Jumlah sel non-none: hitung ulang dari tabel; bila berbeda, sesuaikan angka ekspektasi test (bukan ubah tabel).
Rollback note:
  - `php artisan migrate:rollback` drop 2 tabel; hapus seeder.
Red flags:
  - Seeder tidak idempoten → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 3 test group PASS; hanya 5 file (1 migrasi, 2 model, 1 seeder, 1 test) + edit `DatabaseSeeder`.
Uncertain when: FK komposit tidak didukung SQLite pada versi proyek → pakai `foreignId` biasa + index.
Escalate when: dibutuhkan perubahan skema tabel lama.

---

### Task 2: Test infra — seed RBAC default di base TestCase [depends: T1]

## OBJECTIVE
Agar 49 feature test `RefreshDatabase` yang tidak memanggil seeder tetap lolos setelah middleware RBAC aktif (T6), tambahkan seeding default di base `Tests\TestCase::setUp()` — dipanggil setelah `parent::setUp()` (yaitu setelah `RefreshDatabase` menjalankan migrasi), diguard `Schema::hasTable('menu_definitions')` supaya test tanpa DB (mis. `PilotEvaluationTest`) tidak terpengaruh.

Steps:
1. Write failing test for: default matrix tersedia di dalam test tanpa seed eksplisit
   Test file: `apps/api/tests/Feature/RbacTestInfraTest.php`
   Level: feature (RefreshDatabase)
   Test intent: Given test extends `Tests\TestCase` + `RefreshDatabase` dan **tidak** memanggil seeder / When query dijalankan / Then `MenuDefinition::count() === 19` dan `RoleMenuAccess::where('role','admin')->where('menu_key','products')->value('level') === 'edit'`.
   Exercise through: base `setUp()`
   Test doubles: none
   Expected RED: base `setUp()` belum men-seed → count 0.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacTestInfraTest`
3. Implement `setUp()` di `apps/api/tests/TestCase.php` (guard `Schema::hasTable`) → PASS → refactor → commit.

4. Write failing test for: tidak menyentuh test non-DB
   Test file: `apps/api/tests/Feature/RbacTestInfraTest.php`
   Level: feature
   Test intent: Given `PilotEvaluationTest`-style test tanpa `RefreshDatabase` / When dijalankan / Then tidak error "table not found" (guard bekerja).
   Exercise through: guard `Schema::hasTable`
   Test doubles: none
   Expected RED: tanpa guard, query melempar exception.
5. Run test — verify FAIL → tambah guard → PASS → refactor → commit.
6. Verifikasi: jalankan 3–4 test lama sebagai smoke (`--filter=SalesOutletsTest`, `--filter=RoleManagementTest`) → PASS.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 4" (missing row → none → 403), "Open Questions" (fresh-DB fallback = none).
`apps/api/tests/TestCase.php`, `apps/api/phpunit.xml.dist` (SQLite `:memory:` force).

## WHY THIS APPROACH
Complexity: lightweight
Justification: Missing-row = none berarti suite tanpa seed akan 403 total. Menyentuh base TestCase (1 file) jauh lebih murah daripada mengedit 49 test. Guard `hasTable` menjaga test non-DB tetap jalan.

## SANDWICH CONTEXT
[CRITICAL: jangan mengubah assertion test lama di task ini; hanya infrastruktur seeding; jangan ubah phpunit config]
You are implementing test infrastructure untuk RBAC.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: seed default matrix per-test di base TestCase (guard Schema::hasTable), bukan edit 49 file.
Files in scope: `apps/api/tests/TestCase.php`, `apps/api/tests/Feature/RbacTestInfraTest.php`
Available after: T1 (tabel + seeder ada)
Architecture rule: seeding harus di dalam transaksi test (rollback otomatis) sehingga tiap test bersih.
[RESTATE: jangan ubah assertion test lama; jangan ubah phpunit config]

## DELIVERABLE
Given test RefreshDatabase tanpa seed eksplisit, Then default matrix tersedia.
Given test tanpa DB, Then tidak error.
Given suite lama, Then tetap hijau (smoke).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Guard `Schema::hasTable('menu_definitions')` sebelum seed.
  - Seeding setelah `parent::setUp()`.
Must-not-have:
  - Mengubah assertion file test lain; menambah dependency.
Open question risks:
  - Overhead seeding tiap test (77 insert) — jika lambat signifikan, pindah ke trait khusus (catat sebagai concern).
Rollback note:
  - Hapus `setUp()` + test; suite kembali ke perilaku lama.
Red flags:
  - Guard tidak jalan → test non-DB error → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 2 test group PASS + smoke 2 file lama PASS.
Uncertain when: urutan setUp membuat tabel belum ada.
Escalate when: perlu mengedit >2 file test lama untuk hijau (indikasi desain salah).

---

### Task 3: `Rbac` middleware + alias Kernel [depends: T1]

## OBJECTIVE
Buat middleware `App\Http\Middleware\Rbac` dengan signature `handle(Request, Closure, string $menuKey, string $requiredLevel)`, bandingkan level tersimpan (`role_menu_access` untuk `$request->user()->role`) dengan `$requiredLevel` secara ordinal; baris tidak ada → `none`; gagal → 403 JSON `{status:error, message:"Forbidden: requires <level> on <menu_key>"}`. Daftarkan alias `rbac` di `Kernel::$middlewareAliases`.

Steps:
1. Write failing test for: read vs edit vs none
   Test file: `apps/api/tests/Feature/RbacMiddlewareTest.php`
   Level: feature (RefreshDatabase + seeder)
   Test intent: Given `sales → products = read` / When GET route ber-`rbac:products:read` / Then 200; When POST route ber-`rbac:products:edit` / Then 403. Given `finance` tanpa baris `analytics` / When `rbac:analytics:read` / Then 403.
   Exercise through: HTTP endpoint uji (route test-only) + `Rbac`
   Test doubles: none (pakai route dummy di test atau route existing)
   Expected RED: middleware/alias belum ada → class not found / route tak terjaga.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacMiddlewareTest`
3. Implement middleware + alias → PASS → refactor → commit.

4. Write failing test for: 401 sebelum RBAC & edit lolos untuk edit
   Test file: `apps/api/tests/Feature/RbacMiddlewareTest.php`
   Level: feature
   Test intent: Given tanpa token / When route `auth:api`+`rbac:products:read` / Then 401 (bukan 403). Given `admin → admin_products = edit` / When POST `rbac:admin_products:edit` / Then lolos middleware.
   Exercise through: HTTP
   Test doubles: none
   Expected RED: belum ada.
5. Run test — verify FAIL → implement ordinal compare → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 4", "Implementation Notes" (signature, ordinal none<read<edit), "Edge Cases".
`apps/api/app/Http/Kernel.php` (alias pattern), `apps/api/app/Http/Middleware/DenyFinanceAdministration.php` (pola respons 403).

## WHY THIS APPROACH
Complexity: lightweight
Justification: Satu middleware generik + param route (menu_key, level) menghindari map global `uri→menu_key` yang rapuh dan membuat izin terlihat di definisi route (tradeoff yang diterima spec).

## SANDWICH CONTEXT
[CRITICAL: middleware hanya membaca matrix; jangan hardcode role; 401 harus menang atas 403; jangan sentuh controller]
You are implementing RBAC route middleware.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: `rbac:menu_key:required_level`, ordinal none<read<edit, missing row = none.
Files in scope: `apps/api/app/Http/Middleware/Rbac.php`, `apps/api/app/Http/Kernel.php`, `apps/api/tests/Feature/RbacMiddlewareTest.php`
Available after: T1
Architecture rule: `$request->user()->role`; query `role_menu_access`; jangan simpan cache statis lintas-request.
[RESTATE: jangan hardcode role; 401 menang atas 403; jangan sentuh controller]

## DELIVERABLE
Given stored ≥ required, Then lolos.
Given stored < required atau missing, Then 403 JSON konsisten.
Given tanpa token, Then 401.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Ordinal benar (edit ≥ read ≥ none); `edit` hanya lolos untuk `edit`.
  - Pesan 403 memuat menu_key + level.
Must-not-have:
  - Hardcode daftar role; sentuh controller/route existing (kecuali alias Kernel).
Open question risks:
  - Param ke-3/4 middleware diteruskan Laravel sebagai argumen — pastikan urutan benar.
Rollback note:
  - Hapus middleware + alias.
Red flags:
  - Middleware mem-403 walau level cukup → STOP.

## STOP CONDITIONS
Done when: 2 test group PASS; hanya 3 file.
Uncertain when: user null di route tanpa `auth:api`.
Escalate when: perlu mengubah `Authenticate`.

---

### Task 4: Matrix API `GET`/`PUT /admin/rbac/matrix` [depends: T1, T3]

## OBJECTIVE
Controller `RbacMatrixController` dengan `index()` (GET) dan `update(UpdateRbacMatrixRequest)` (PUT). Keduanya dijaga controller-level `FinanceAuthorizationService::assertAdminOrOwner` (K-A — **tanpa** `rbac:` middleware). GET → `{status:success, data:{role:{menu_key:level}}}` (7 role × 19 key, semua key eksplisit termasuk `none`). PUT menerima `cells: [{role, menu_key, level}]`, **all-or-nothing**, validasi ketat.

Steps:
1. Write failing test for: GET sukses & 403/401
   Test file: `apps/api/tests/Feature/RbacMatrixEndpointTest.php`
   Level: feature
   Test intent: Given admin/owner login / When GET /api/admin/rbac/matrix / Then 200, body punya 7 role, tiap role 19 key, `admin.rbac_matrix==='read'`; Given sales / Then 403; tanpa token / Then 401; DB kosong (tanpa seeder) / Then 200 dengan semua `none` (tanpa 500).
   Exercise through: HTTP
   Test doubles: none
   Expected RED: route/controller belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacMatrixEndpointTest`
3. Implement `index()` + route → PASS → refactor → commit.

4. Write failing test for: PUT valid + full matrix kembali
   Test file: `apps/api/tests/Feature/RbacMatrixEndpointTest.php`
   Level: feature
   Test intent: Given admin / When PUT `[{role:sales,menu_key:products,level:edit}]` / Then 200 dan response `sales.products==='edit'`; DB ter-update.
   Exercise through: HTTP
   Test doubles: none
   Expected RED: route/controller belum ada.
5. Run test — verify FAIL → implement `update()` happy path → PASS → refactor → commit.

6. Write failing test for: aturan keamanan PUT (semua all-or-nothing)
   Test file: `apps/api/tests/Feature/RbacMatrixEndpointTest.php`
   Level: feature
   Test intent: Given admin / When PUT memuat `platform_owner` / Then 403 & **tidak ada** sel diterapkan; Given admin `admin→rbac_matrix=none` / Then 422 (self-lockout); Given owner `platform_owner→rbac_matrix=none` / Then 422; Given owner `admin→rbac_matrix=none` / Then 200; Given `menu_key` tak ada / Then 422 tanpa efek; Given `level:write` / Then 422; Given `[]` / Then 422.
   Exercise through: HTTP
   Test doubles: none
   Expected RED: validasi belum ada.
7. Run test — verify FAIL → implement `UpdateRbacMatrixRequest` + guard → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 1", "Story 2", Acceptance Criteria, "Design Decision", K-A/K-B di plan ini.
`apps/api/app/Services/FinanceAuthorizationService.php`, `apps/api/app/Http/Controllers/UserRoleController.php` (pola controller tipis), `apps/api/routes/api.php`.

## WHY THIS APPROACH
Complexity: standard
Justification: K-A: tabel default memberi `admin→rbac_matrix=read`, padahal Story 2 butuh admin PUT — maka otorisasi endpoint lewat `assertAdminOrOwner` (bukan level matrix) agar tabel spec tetap utuh. All-or-nothing + self-lockout mencegah admin mengunci diri/owner.

## SANDWICH CONTEXT
[CRITICAL: PUT all-or-nothing; admin tidak boleh menyentuh baris platform_owner; self-lockout rbac_matrix → 422; jangan tambah audit log]
You are implementing RBAC matrix endpoints.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: controller-level `assertAdminOrOwner` (K-A); PUT `cells[]` all-or-nothing; validasi role/menu_key/level ketat.
Files in scope: `apps/api/app/Http/Controllers/RbacMatrixController.php`, `apps/api/app/Http/Requests/UpdateRbacMatrixRequest.php`, `apps/api/routes/api.php`, `apps/api/tests/Feature/RbacMatrixEndpointTest.php`
Available after: T1, T3
Architecture rule: controller tipis; gunakan service/model; respons `{status,data}`; GET kembalikan 19 key eksplisit per role.
[RESTATE: PUT all-or-nothing; admin tak boleh sentuh owner; self-lockout → 422; tanpa audit log]

## DELIVERABLE
Given admin/owner, When GET, Then 200 7×19 lengkap (termasuk none).
Given admin valid PUT, Then 200 + matrix ter-update.
Given pelanggaran aturan, Then 403/422 all-or-nothing.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Validasi role ∈ 7, menu_key ∈ `menu_definitions`, level ∈ none|read|edit; `cells` wajib non-empty.
  - All-or-nothing: validasi & cek izin sebelum menulis; gunakan DB transaction.
  - Self-lockout: aktor tidak boleh set `rbac_matrix` miliknya sendiri ke `none`.
Must-not-have:
  - `rbac:` middleware pada route RBAC (K-A); audit log; partial success.
Open question risks:
  - Last-write-wins tanpa lock (diterima spec).
Rollback note:
  - Hapus controller + request + route.
Red flags:
  - Sebagian sel tertulis saat ada pelanggaran → STOP.

## STOP CONDITIONS
Done when: 3 test group PASS.
Uncertain when: bentuk respons GET "nested object" ambigu → ikuti spec `{role:{menu_key:level}}`.
Escalate when: perlu mengubah `UserPolicy`.

---

### Task 5: `GET /auth/me` menyertakan map `rbac` [depends: T1]

## OBJECTIVE
Tambahkan `rbac: Record<menu_key, level>` (19 key eksplisit untuk role pemanggil, termasuk `none`) ke respons `AuthController::me()`. Ekstrak resolver ke service kecil (mis. `RbacMatrixService::mapForRole(string $role): array`) agar dipakai ulang oleh T4.

Steps:
1. Write failing test for: me memuat 19 key
   Test file: `apps/api/tests/Feature/AuthMeRbacTest.php`
   Level: feature
   Test intent: Given sales login / When GET /api/auth/me / Then `data.rbac` punya 19 key, `rbac.products==='read'`, `rbac.rbac_matrix==='none'`.
   Exercise through: HTTP
   Test doubles: none
   Expected RED: `data.rbac` belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AuthMeRbacTest`
3. Implement resolver + `me()` → PASS → refactor → commit.

4. Write failing test for: role tanpa baris → semua none (bukan null/hilang)
   Test file: `apps/api/tests/Feature/AuthMeRbacTest.php`
   Level: feature
   Test intent: Given DB tanpa seeder (matrix kosong) / When GET /auth/me / Then `rbac` tetap 19 key semua `none` (tidak null, tidak hilang).
   Exercise through: HTTP
   Test doubles: none
   Expected RED: belum ada fallback.
5. Run test — verify FAIL → implement fallback none → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 5", "Implementation Notes" (full 18→19 key map).
`apps/api/app/Http/Controllers/AuthController.php` (`me()` existing), `apps/api/app/Models/User.php`.

## WHY THIS APPROACH
Complexity: lightweight
Justification: Satu fetch saat login (Story 5) menghindari round-trip tambahan; map penuh + eksplisit `none` menghapus ambiguitas "key hilang" di frontend.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah field existing `me()` (id/name/email/role/created_at); jangan tambah fetch baru; fallback none]
You are implementing auth/me RBAC map.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: full 19-key map untuk role pemanggil; key eksplisit `none`.
Files in scope: `apps/api/app/Http/Controllers/AuthController.php`, resolver service, `apps/api/tests/Feature/AuthMeRbacTest.php`
Available after: T1
Architecture rule: resolver dipakai ulang T4; jangan duplikasi logika bentuk map.
[RESTATE: jangan ubah field existing me(); fallback none; 19 key]

## DELIVERABLE
Given role apa pun, When GET /auth/me, Then `data.rbac` 19 key eksplisit.
Given role tanpa baris, Then semua `none`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 19 key selalu ada; nilai `none` bila tidak ada baris.
  - Field lama `me()` tidak berubah.
Must-not-have:
  - Query per-key (N+1); ubah kontrak auth lain.
Open question risks:
  - Bentuk map harus identik dengan T4 agar frontend konsisten.
Rollback note:
  - Hapus `rbac` dari `me()` + resolver.
Red flags:
  - `rbac` null saat kosong → STOP.

## STOP CONDITIONS
Done when: 2 test group PASS.
Uncertain when: nama service bentrok — pakai `RbacMatrixService`.
Escalate when: perlu mengubah `AuthService`.

---

### Task 6: Enforcement penuh route + hapus `deny.finance` [depends: T3]

## OBJECTIVE
Anotasi **semua** route terproteksi di `routes/api.php` dengan `->middleware('rbac:<menu_key>:<level>')` sesuai tabel pemetaan di Execution Overview. Hapus semua penggunaan `deny.finance`, hapus alias di `Kernel.php`, dan hapus kelas `DenyFinanceAdministration`. Route `auth/*` dan RBAC matrix **tidak** diberi `rbac:` (lihat pemetaan). Urutan middleware: `auth:api` → `reject.stale_jwt` → `rbac:*`.

Steps:
1. Write failing test for: route ter-guard menolak role tanpa akses & mengizinkan yang punya
   Test file: `apps/api/tests/Feature/RbacRouteEnforcementTest.php`
   Level: feature
   Test intent: Given finance / When GET /api/products (`rbac:products:read`) / Then 200 (matrix baru); Given sales / When POST /api/products-tak-ada → gunakan route edit yang ada; Given finance / When GET /api/ai/recommendations / Then 403; Given finance / When PUT /api/orders/{id}/approve / Then 403; tanpa token → 401.
   Exercise through: HTTP
   Test doubles: none
   Expected RED: route belum dianotasi → perilaku lama (`deny.finance`).
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacRouteEnforcementTest`
3. Anotasi route sesuai tabel + hapus `deny.finance` → PASS → refactor → commit.

4. Write failing test for: `deny.finance` benar-benar hilang
   Test file: `apps/api/tests/Feature/RbacRouteEnforcementTest.php`
   Level: feature
   Test intent: Given grep/source / When cek `routes/api.php` & `Kernel.php` / Then tidak ada `deny.finance`; kelas `DenyFinanceAdministration` tidak ada.
   Exercise through: file system assertion (test) + manual grep
   Test doubles: none
   Expected RED: masih ada.
5. Run test — verify FAIL → hapus alias + kelas → PASS → refactor → commit.
6. Smoke: jalankan `--filter=SalesOrderTest --filter=DeliveryTest --filter=AdminProductPriceTest` → identifikasi test yang perlu diperbarui (feed ke T7).

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 4", "Open Questions" (deny.finance removal), tabel pemetaan di plan ini.
`apps/api/routes/api.php` (215 baris), `apps/api/app/Http/Kernel.php`, `apps/api/app/Http/Middleware/DenyFinanceAdministration.php`.

## WHY THIS APPROACH
Complexity: deep
Justification: Keputusan #2 = Full. Anotasi eksplisit per-route membuat izin terlihat di definisi route (tradeoff spec). Menghapus `deny.finance` mencegah double-guard yang membingungkan.

## SANDWICH CONTEXT
[CRITICAL: jangan hapus guard controller-level existing (assertAdmin dsb); jangan sentuh route publik/`auth/*`; jangan ubah logic controller]
You are implementing full route enforcement untuk RBAC.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: `rbac:<menu_key>:<level>` per route; `deny.finance` dihapus; route mutasi `admin_users` & RBAC matrix pakai controller-level (K-A/K-B).
Files in scope: `apps/api/routes/api.php`, `apps/api/app/Http/Kernel.php`, hapus `DenyFinanceAdministration.php`, `apps/api/tests/Feature/RbacRouteEnforcementTest.php`
Available after: T3
Architecture rule: urutan auth → stale_jwt → rbac; pertahankan `reject.stale_jwt` group.
[RESTATE: jangan hapus guard controller-level; jangan sentuh route publik/auth; jangan ubah logic controller]

## DELIVERABLE
Given setiap route terproteksi, Then punya `rbac:` sesuai tabel (kecuali pengecualian terdokumentasi).
Given `deny.finance`, Then tidak ada lagi di codebase.
Given role tanpa akses, Then 403; tanpa token → 401.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Semua route terproteksi teranotasi; level GET=read, mutasi=edit (kecuali K-A/K-B).
  - `deny.finance` + kelasnya hilang; alias Kernel dihapus.
Must-not-have:
  - Mengubah logic controller; menyentuh route publik (`/auth/login`, `/auth/register`, `/whatsapp/webhook`, `/health`).
Open question risks:
  - Beberapa route (credit-limit, territories, whatsapp, finance/*) pemetaannya judgment — catat di log; konsisten dengan tabel.
Rollback note:
  - `git checkout routes/api.php app/Http/Kernel.php`; pulihkan middleware.
Red flags:
  - Route publik ikut ter-guard → STOP.

## STOP CONDITIONS
Done when: 2 test group PASS + smoke 3 file.
Uncertain when: route tidak jelas menu-nya → pilih terdekat + catat.
Escalate when: perlu mengubah controller untuk lolos guard.

---

### Task 7: Selaraskan test lama dengan default matrix baru [depends: T6]

## OBJECTIVE
Perbarui assertion test yang mengasumsikan guard lama (`deny.finance`/adminOnly) agar mencerminkan default matrix spec. Fokus utama: `FinanceAccessTest::test_finance_cannot_access_unrelated_administration` (beberapa endpoint kini 200 untuk finance: `/products`, `/marketplace/*`, `/credit-limit*`, `/sales/visits`, `/deliveries`, `/orders`). Telusuri seluruh suite dan perbaiki **hanya** yang gagal karena perubahan matrix.

Steps:
1. Jalankan suite penuh: `cd apps/api && php artisan test` → catat daftar test gagal.
2. Untuk `FinanceAccessTest`: pisahkan endpoint yang **tetap** 403 (`/ai/recommendations`, `/orders/{id}/approve`, `POST /outlets`) dari yang **kini** 200/berbeda; perbarui ekspektasi agar selaras spec (finance = `read` di products/orders/delivery/sales/outlets/marketplace; `edit` di payments/invoices; `none` di analytics/data_intelligence/operations/admin_*).
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`
   Level: feature
   Test intent: Given finance / When akses tiap endpoint / Then sesuai level default matrix (read→200 kecuali controller lebih ketat, edit→200, none→403).
   Exercise through: HTTP
   Test doubles: none
   Expected RED: test lama assert 403 untuk endpoint yang kini 200.
3. Perbaiki test lain yang gagal (kemungkinan besar tidak ada; verifikasi `RoleManagementTest`, `SalesOrderTest`, `SalesOutletsTest`, `SalesPerformanceTest`, `PrePilotCompatibilityTest`, `AdminProductPriceTest` tetap hijau) → commit.

4. Dokumentasikan di `log.json`/closeout: daftar test yang diubah + alasan (matrix widening).

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Default Matrix", "Open Questions" (deny.finance), keputusan #3 plan ini.
`apps/api/tests/Feature/FinanceAccessTest.php` (baris 46–76), `RoleManagementTest.php` (99–107), `PrePilotCompatibilityTest.php` (325–341).

## WHY THIS APPROACH
Complexity: standard
Justification: Keputusan #3 = ikuti spec, perbarui test. Matrix memperlebar akses finance dari binary-deny menjadi bertingkat — assertion lama yang menyatakan "finance 403 di /products" kini salah menurut spec.

## SANDWICH CONTEXT
[CRITICAL: hanya ubah ekspektasi test, JANGAN ubah kode produksi atau default matrix; jangan melemahkan test yang seharusnya tetap 403]
You are aligning existing tests with the new RBAC default matrix.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: keputusan #3 (ikuti spec, update test lama).
Files in scope: `apps/api/tests/Feature/FinanceAccessTest.php` (+ test lain yang terbukti gagal)
Available after: T6
Architecture rule: perubahan hanya pada assertion; sumber kebenaran = tabel default matrix.
[RESTATE: jangan ubah kode produksi/default matrix; jangan melemahkan test yang benar]

## DELIVERABLE
Given suite penuh, Then hijau.
Given perubahan perilaku, Then didokumentasikan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Setiap perubahan assertion disertai alasan level matrix.
  - Test yang seharusnya tetap 403 (analytics, admin_*) tetap 403.
Must-not-have:
  - Mengubah kode produksi; menghapus test; `markTestSkipped` untuk menyembunyikan gagal.
Open question risks:
  - Endpoint dengan controller-level lebih ketat (mis. `GET /orders` admin-only) tetap 403 meski matrix read — jaga assertion sesuai perilaku nyata.
Rollback note:
  - `git checkout` file test.
Red flags:
  - Menonaktifkan test → STOP.

## STOP CONDITIONS
Done when: `php artisan test` hijau penuh.
Uncertain when: endpoint 403/200 ambigu → verifikasi via controller + matrix.
Escalate when: perlu mengubah kode produksi untuk hijau (indikasi bug).

---

### Task 8: Fixture matrix dummy (frontend) [prereq]

## OBJECTIVE
Buat `apps/web/src/dummy/rbac.ts` berisi `DUMMY_RBAC_MATRIX` (literal 19 menu × 7 role, **identik** dengan seeder T1) + `getDummyMatrix(role): Record<menu_key, level>` (fallback semua `none` untuk role tak dikenal). Murni in-memory, zero-network.

Steps:
1. Write failing test for: bentuk & fallback
   Test file: `apps/web/src/dummy/rbac.test.ts`
   Level: unit
   Test intent: Given `getDummyMatrix('finance')` / Then 19 key, `products==='read'`, `rbac_matrix==='none'`; Given `getDummyMatrix('unknown')` / Then 19 key semua `none`.
   Exercise through: `getDummyMatrix`
   Test doubles: none
   Expected RED: modul belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/rbac.test.ts`
3. Implement → PASS → refactor → commit.

4. Write failing test for: parity dengan seeder (spot-check)
   Test file: `apps/web/src/dummy/rbac.test.ts`
   Level: unit
   Test intent: Given beberapa sel kunci / Then nilainya sama dengan tabel default (mis. `admin.analytics==='read'`, `platform_owner.rbac_matrix==='edit'`, `worktree` read untuk 7 role).
   Exercise through: `getDummyMatrix`
   Test doubles: none
   Expected RED: belum ada.
5. Run test — verify FAIL → implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 3", "Implementation Notes" (dummy matrix).
`apps/web/src/dummy/store.ts`, `apps/web/src/dummy/index.ts` (pola dummy murni).

## WHY THIS APPROACH
Complexity: lightweight
Justification: Dummy mode harus mencerminkan DB (Story 3) tanpa network; modul murni mudah diuji dan dipakai ulang store (T9).

## SANDWICH CONTEXT
[CRITICAL: dummy = zero-network; jangan import fetch/api; jangan duplikasi logika store]
You are implementing the dummy RBAC fixture.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: literal matrix mirroring seeder; `getDummyMatrix` fallback none.
Files in scope: `apps/web/src/dummy/rbac.ts`, `apps/web/src/dummy/rbac.test.ts`
Available after: none (prereq)
Architecture rule: pure function; tanpa efek samping; tanpa network.
[RESTATE: dummy zero-network; jangan import fetch/api]

## DELIVERABLE
Given role dikenal, Then 19 key sesuai default matrix.
Given role tak dikenal, Then 19 key `none`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 19 key eksplisit; nilai identik seeder.
  - `getDummyMatrix` murni & sinkron.
Must-not-have:
  - Import `fetch`/`lib/api`; mutasi global.
Open question risks:
  - Drift seeder vs dummy — dijaga test parity spot-check; sinkronkan bila T1 berubah.
Rollback note:
  - Hapus 2 file.
Red flags:
  - Nilai berbeda dari seeder → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 2 test group PASS.
Uncertain when: perlu mengekspor matrix mentah untuk test — ekspor `DUMMY_RBAC_MATRIX`.
Escalate when: perlu mengubah store.

---

### Task 9: `useRbacStore` (Zustand) [depends: T5, T8]

## OBJECTIVE
Buat store Zustand `useRbacStore` (`apps/web/src/store/useRbacStore.ts`) dengan state `matrix: Record<role, Record<menu_key, level>> | null`, `role: string | null`, aksi `hydrateFromMe(rbac, role)`, `loadFromServer(token)` (fetch `/admin/rbac/matrix` untuk admin page), `save(token, cells)`, dan helper `levelFor(menuKey)`, `canRead(menuKey)`, `canEdit(menuKey)`. Dummy ON → isi dari `getDummyMatrix(role)` tanpa fetch.

Steps:
1. Write failing test for: levelFor/canRead/canEdit
   Test file: `apps/web/src/store/useRbacStore.test.ts`
   Level: unit
   Test intent: Given matrix `{sales:{products:'read'}}` & role sales / Then `levelFor('products')==='read'`, `canRead('products')===true`, `canEdit('products')===false`, `levelFor('missing')==='none'`.
   Exercise through: store actions
   Test doubles: none
   Expected RED: store belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/store/useRbacStore.test.ts`
3. Implement store + helper → PASS → refactor → commit.

4. Write failing test for: dummy ON tidak fetch
   Test file: `apps/web/src/store/useRbacStore.test.ts`
   Level: unit
   Test intent: Given dummy ON / When `hydrateFromMe`/init untuk role outlet / Then `levelFor('products')==='read'` dari dummy & `fetch` **tidak** dipanggil.
   Exercise through: store + `useDummyStore`
   Test doubles: fetch spy (assert not called)
   Expected RED: belum ada.
5. Run test — verify FAIL → implement dummy branch → PASS → refactor → commit.

6. Write failing test for: loadFromServer & save (dummy OFF)
   Test file: `apps/web/src/store/useRbacStore.test.ts`
   Level: unit
   Test intent: Given dummy OFF + fetch stub / When `loadFromServer(token)` / Then matrix terisi; When `save(token, cells)` / Then PUT terkirim & matrix di-refresh.
   Exercise through: store
   Test doubles: fetch stub
   Expected RED: belum ada.
7. Run test — verify FAIL → implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 3", "Story 5", "Implementation Notes" (`levelFor/canRead/canEdit`).
`apps/web/src/dummy/store.ts` (pola Zustand + dummy flag), `apps/web/src/lib/api.ts` (`apiUrl`, `authHeaders`, `getStoredToken`).

## WHY THIS APPROACH
Complexity: standard
Justification: Store tunggal memberi sumber kebenaran frontend (Sidebar + halaman admin), mencerminkan pola `dummy/store.ts`, dan mengisolasi dummy vs network.

## SANDWICH CONTEXT
[CRITICAL: dummy ON = zero-network; jangan panggil fetch saat dummy; jangan ubah dummy/store.ts]
You are implementing the RBAC Zustand store.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: `levelFor/canRead/canEdit`; dummy pakai `getDummyMatrix`; real pakai `/admin/rbac/matrix`.
Files in scope: `apps/web/src/store/useRbacStore.ts`, `apps/web/src/store/useRbacStore.test.ts`
Available after: T5 (bentuk map), T8 (dummy)
Architecture rule: ikuti pola `dummy/store.ts`; state ringan; tanpa persist matrix.
[RESTATE: dummy ON zero-network; jangan ubah dummy/store.ts]

## DELIVERABLE
Given matrix+role, Then `levelFor/canRead/canEdit` benar (missing→none).
Given dummy ON, Then terisi dari dummy tanpa fetch.
Given dummy OFF, Then load/save via API.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `levelFor` fallback `none`; helper konsisten dengan middleware (read<edit).
  - Dummy branch tidak memanggil fetch.
Must-not-have:
  - Menyimpan matrix ke localStorage; mengubah store dummy.
Open question risks:
  - Kapan hydrate dipanggil (login/Sidebar) — T10 memutuskan; store harus idempoten.
Rollback note:
  - Hapus store + test.
Red flags:
  - Fetch saat dummy → STOP.

## STOP CONDITIONS
Done when: 3 test group PASS.
Uncertain when: bentuk `hydrateFromMe` — terima `(rbac, role)`.
Escalate when: perlu mengubah `lib/api.ts`.

---

### Task 10: Sidebar digerakkan matrix [depends: T9]

## OBJECTIVE
Refactor `Sidebar.tsx`: hapus flag `finance`/`adminOnly` dari `NavItem` dan fungsi `visibleItemsFor()` hardcoded. Tambahkan `key` (menu_key) pada tiap `NavItem`; visibilitas = `useRbacStore.levelFor(item.key) !== 'none'`. Saat role ter-resolve, hidrasi store dari `GET /auth/me` (`data.rbac`) — atau dummy bila dummy ON. Pertahankan logika `adminHref` (hanya kontrol visibilitas, bukan href). Perbarui `Sidebar.test.tsx`.

**Dua penyesuaian WAJIB di `Sidebar.test.tsx` (kalau tidak, seluruh file test ini merah):**
1. `mockRole(role)` saat ini mengembalikan `{data:{role}}` **tanpa** `rbac`. Karena visibilitas kini dari matrix, mock harus menyertakan `rbac` (mis. `json: async () => ({ data: { role, rbac: getDummyMatrix(role) } })`) atau test men-set `useRbacStore` langsung.
2. Test `'still restricts finance to finance-flagged items only'` **bertentangan** dengan default matrix (finance kini juga melihat Produk/Pesanan/Outlet/Marketplace/Pengiriman/Sales/Worktree). Perbarui ekspektasinya ke himpunan menu matrix finance (sembunyikan hanya `analytics`/`data_intelligence`/`operations`/`admin_*`). Test `'hides every admin menu from outlet/sales/driver'` tetap valid (matrix memberi `none` di `admin_*`).

Steps:
1. Write failing test for: visibilitas dari matrix
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: unit/integration (jsdom)
   Test intent: Given matrix finance (`analytics:'none'`, `payments:'edit'`, `dashboard:'read'`) / Then sidebar menampilkan Dasbor & Pembayaran, menyembunyikan Analitik/Data Intelligence/Operasi/admin_*; Given admin (`admin_users:'read'`) / Then "Kelola pengguna" tampil; Given key tanpa baris / Then tersembunyi.
   Exercise through: render `<Sidebar/>` + store set
   Test doubles: fetch stub untuk `/auth/me` (kecuali dummy)
   Expected RED: masih pakai guard hardcoded.
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/Sidebar.test.tsx`
3. Implement refactor → PASS → refactor → commit.

4. Write failing test for: dummy parity + adminHref
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: unit
   Test intent: Given dummy ON + role outlet / Then pakai `getDummyMatrix` tanpa fetch; Given admin / Then item Outlet memakai `adminHref='/admin/outlets'`.
   Exercise through: render
   Test doubles: fetch spy (not called saat dummy)
   Expected RED: belum ada.
5. Run test — verify FAIL → implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 3", "Implementation Notes" (Sidebar refactor).
`apps/web/src/components/Sidebar.tsx` (baris 9–35, 131–136, 145), `Sidebar.test.tsx`, `apps/web/src/store/useRbacStore.ts`.

## WHY THIS APPROACH
Complexity: standard
Justification: Menghapus guard hardcoded adalah inti spec (matrix = sumber kebenaran). Mempertahankan `adminHref` sesuai asumsi spec (matrix hanya visibilitas).

## SANDWICH CONTEXT
[CRITICAL: jangan ubah href/label/icon existing; jangan ubah perilaku auth-change listener; dummy ON zero-network]
You are refactoring the Sidebar to be matrix-driven.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: `levelFor(key) !== 'none'`; hapus flag boolean; hidrasi dari /auth/me atau dummy.
Files in scope: `apps/web/src/components/Sidebar.tsx`, `apps/web/src/components/Sidebar.test.tsx`
Available after: T9
Architecture rule: store = sumber visibilitas; jangan query matrix manual di komponen.
[RESTATE: jangan ubah href/label/icon; dummy zero-network]

## DELIVERABLE
Given matrix role, Then hanya menu level≠none tampil.
Given dummy ON, Then dummy matrix dipakai tanpa fetch.
Given admin, Then `adminHref` tetap berlaku.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Setiap `NavItem` punya `key` cocok `menu_definitions.key` (19 item).
  - Tidak ada lagi `adminOnly`/`finance` di kode.
Must-not-have:
  - Mengubah `adminHref`/label; memecah listener `ddp-auth-change`.
Open question risks:
  - Urutan hidrasi vs render (flash menu) — tampilkan kosong sampai `authResolved` (perilaku existing).
Rollback note:
  - `git checkout Sidebar.tsx` + test.
Red flags:
  - Menu bocor untuk role none → STOP.

## STOP CONDITIONS
Done when: 2 test group PASS + test Sidebar existing hijau.
Uncertain when: store belum ter-hidrasi saat render — pastikan effect memanggil hydrate.
Escalate when: perlu mengubah `LoginForm`.

---

### Task 11: Halaman admin `/admin/rbac` [depends: T9]

## OBJECTIVE
Buat `apps/web/src/app/admin/rbac/page.tsx` (grid 19 baris × 7 kolom; tiap sel dropdown none/read/edit; tombol Save) + `api.ts` (GET/PUT `/admin/rbac/matrix` via `useRbacStore.loadFromServer`/`save`). Akses halaman hanya untuk `platform_owner`+`admin` (gerbang store `canRead('rbac_matrix')`); dummy ON → pakai dummy matrix + no-op save.

Steps:
1. Write failing test for: render grid & simpan
   Test file: `apps/web/src/app/admin/rbac/page.test.tsx`
   Level: unit/integration
   Test intent: Given admin + matrix stub / When render / Then 19 baris × 7 kolom; ubah sel `sales.products`→edit & klik Save / Then PUT berisi `{role:sales,menu_key:products,level:edit}`; Given non-admin / Then pesan/redirect tanpa fetch PUT.
   Exercise through: render page + store
   Test doubles: fetch stub
   Expected RED: page belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/rbac/page.test.tsx`
3. Implement page + api → PASS → refactor → commit.

4. Write failing test for: dummy parity
   Test file: `apps/web/src/app/admin/rbac/page.test.tsx`
   Level: unit
   Test intent: Given dummy ON / Then grid terisi dari `getDummyMatrix` & Save tidak memanggil network.
   Exercise through: render + dummy store
   Test doubles: fetch spy (not called)
   Expected RED: belum ada.
5. Run test — verify FAIL → implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — "Story 2", "Scope" (`/admin/rbac`), Acceptance Criteria.
`apps/web/src/app/admin/users/page.tsx` + `api.ts` (pola halaman admin), `apps/web/src/lib/api.ts`, `useRbacStore`.

## WHY THIS APPROACH
Complexity: standard
Justification: Halaman adalah UI wajib agar admin bisa mengubah matrix tanpa deploy (motivasi utama spec).

## SANDWICH CONTEXT
[CRITICAL: PUT all-or-nothing di backend; UI kirim hanya sel berubah (atau semua) — tangani 403/422; dummy zero-network]
You are implementing the /admin/rbac page.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: grid 19×7; Save via store; gate `canRead('rbac_matrix')`; dummy no-op.
Files in scope: `apps/web/src/app/admin/rbac/page.tsx`, `apps/web/src/app/admin/rbac/api.ts`, `apps/web/src/app/admin/rbac/page.test.tsx`
Available after: T9
Architecture rule: pakai store; jangan panggil fetch langsung dari komponen.
[RESTATE: tangani 403/422; dummy zero-network]

## DELIVERABLE
Given admin, When render, Then grid 19×7 dari matrix.
Given ubah + Save, Then PUT terkirim & matrix refresh.
Given non-admin, Then tidak bisa mengakses/menyimpan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 19×7 sel; level none/read/edit; Save memanggil store.
  - Gate akses halaman; dummy no-op.
Must-not-have:
  - Logika otorisasi baru di frontend (backend tetap sumber); direct fetch.
Open question risks:
  - Menampilkan error 403/422 dari PUT (self-lockout) — tampilkan pesan, jangan crash.
Rollback note:
  - Hapus folder `app/admin/rbac`.
Red flags:
  - Save saat dummy memanggil network → STOP.

## STOP CONDITIONS
Done when: 2 test group PASS.
Uncertain when: layout grid — gunakan `<table>` sederhana agar mudah diuji.
Escalate when: perlu komponen UI baru di `components/ui`.

---

### Task 12: Verifikasi integrasi + parity dummy [depends: T6, T7, T10, T11]

## OBJECTIVE
Test integrasi end-to-end (jsdom + fetch stub): (a) login → `/auth/me` → store → Sidebar menampilkan menu sesuai matrix; (b) admin ubah matrix di `/admin/rbac` → store refresh → Sidebar berubah; (c) stale cache: matrix berubah server-side → Sidebar masih tampil tapi API 403 (simulasi); (d) dummy parity: dummy ON → Sidebar & halaman pakai dummy tanpa network.

Steps:
1. Write failing test for: login→sidebar & admin edit→sidebar
   Test file: `apps/web/src/__tests__/rbac-integration.test.tsx`
   Level: integration
   Test intent: Given fetch stub `/auth/me` mengembalikan rbac finance / Then Sidebar sembunyikan Analitik; Given admin PUT sukses lalu matrix berubah / Then Sidebar memperbarui item.
   Exercise through: komponen + store
   Test doubles: fetch stub
   Expected RED: skenario belum tertutup.
2. Run test — verify FAIL: `cd apps/web && npx jest src/__tests__/rbac-integration.test.tsx`
3. Implement/perbaiki → PASS → refactor → commit.

4. Write failing test for: dummy parity + stale cache
   Test file: `apps/web/src/__tests__/rbac-integration.test.tsx`
   Level: integration
   Test intent: Given dummy ON / Then Sidebar & page pakai dummy, `fetch` tidak dipanggil (zero-network); Given matrix cache lama / Then Sidebar tetap tampil (stale) — dokumentasikan bahwa enforcement server tetap 403 (diverifikasi di T6).
   Exercise through: komponen + dummy
   Test doubles: fetch spy
   Expected RED: belum ada.
5. Run test — verify FAIL → implement → PASS → refactor → commit.
6. Jalankan kedua suite: `cd apps/api && php artisan test` dan `cd apps/web && npx jest` → semua hijau.

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md — Acceptance Criteria (stale cache, dummy parity), "Story 3".
`apps/web/src/components/Sidebar.tsx`, `apps/web/src/app/admin/rbac/page.tsx`, `useRbacStore`, `apps/web/src/dummy/*`.

## WHY THIS APPROACH
Complexity: deep
Justification: Menutup gap antar-layer (auth→store→UI) dan memverifikasi invariant zero-network dummy yang tidak terlihat di test unit.

## SANDWICH CONTEXT
[CRITICAL: dummy ON zero-network; jangan reimplement filter di test; jangan pukul network asli]
You are implementing RBAC integration verification.
Spec: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
Design decision: verifikasi behavior via boundary publik; parity dummy = real.
Files in scope: `apps/web/src/__tests__/rbac-integration.test.tsx`
Available after: T6, T7, T10, T11
Architecture rule: assert `fetch` tidak dipanggil saat dummy; jangan duplikasi logika store.
[RESTATE: dummy zero-network; jangan reimplement filter; jangan pukul network asli]

## DELIVERABLE
Given login dengan matrix, Then Sidebar sesuai matrix.
Given admin edit, Then Sidebar refresh.
Given dummy ON, Then zero-network parity.
Given stale cache, Then Sidebar stale (server tetap 403, dari T6).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Assert `fetch` tidak dipanggil saat dummy ON.
  - Tidak reimplement filter di test.
Must-not-have:
  - Memukul network asli; mengetes detail implementasi.
Open question risks:
  - Sinkronisasi store antar-render di jsdom — gunakan `useRbacStore.setState`/actions.
Rollback note:
  - Hapus file test baru.
Red flags:
  - Test memukul network → STOP.

## STOP CONDITIONS
Done when: 2 test group PASS + kedua suite hijau.
Uncertain when: store tidak ter-hidrasi di jsdom → set state langsung.
Escalate when: parity mustahil tanpa mengubah pipeline.

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | RBAC schema + models + seeder | prereq | standard | 19 menu + 57 non-none + idempoten |
| T2 | Seed default di base TestCase | T1 | lightweight | suite lama tetap hijau tanpa edit 49 file |
| T3 | `Rbac` middleware + alias | T1 | lightweight | ordinal none<read<edit; 401 menang 403 |
| T4 | Matrix GET/PUT endpoint | T1, T3 | standard | all-or-nothing + self-lockout + 403 owner |
| T5 | `/auth/me` rbac map | T1 | lightweight | 19 key eksplisit; fallback none |
| T6 | Enforcement route + hapus `deny.finance` | T3 | deep | semua route ter-guard; deny.finance hilang |
| T7 | Selaraskan test lama | T6 | standard | suite API hijau penuh |
| T8 | Fixture dummy matrix | prereq | lightweight | 19×7 parity seeder; fallback none |
| T9 | `useRbacStore` | T5, T8 | standard | levelFor/canRead/canEdit; dummy no-fetch |
| T10 | Sidebar matrix-driven | T9 | standard | visibilitas dari matrix; hapus flag |
| T11 | Halaman `/admin/rbac` | T9 | standard | grid 19×7 + Save; dummy no-op |
| T12 | Integrasi + parity dummy | T6,T7,T10,T11 | deep | login→sidebar, edit→refresh, zero-network |
