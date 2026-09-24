# Phase 8 — Mobile Field Operations (PWA Offline, GPS Check-in, Driver Roster, Route Optimization, Live Tracking, Proof of Delivery)

**Date:** 2026-09-22
**Status:** implemented (2026-09-22)
**Execution plan:** docs/pocket/plans/2026-09-22-phase8-mobile-field-operations/execution-plan/index.md (18 tasks, 6 phases)
**Closeout:** docs/pocket/plans/2026-09-22-phase8-mobile-field-operations/closeout.md
**Author:** roadmap gap analysis (development-roadmap.md → Phase 8)
**Spec path:** docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md

---

## Summary

Roadmap Phase 8 menuntut sales dan driver dapat bekerja di lapangan dengan andal,
termasuk saat koneksi tidak stabil. Saat ini platform sudah punya sales visit planning,
delivery assignment/confirmation, dan `RoutingService` sebagai *seam* kosong, tetapi:
tidak ada PWA/offline, tidak ada GPS check-in, tidak ada driver roster CRUD, tidak ada
optimasi rute, tidak ada live tracking, dan Proof of Delivery hanya menyimpan
`recipient_name` + URL (tanpa foto/tanda tangan yang benar-benar ditangkap).

Spec ini mendefinisikan **Phase 8 end-to-end** dalam 6 fase bertahap: fondasi data
(driver roster, kolom GPS, location ping, metadata PoD) → API backend (roster CRUD,
check-in/out, routing bounded, tracking) → penangkapan PoD (upload foto/tanda tangan) →
PWA offline shell (service worker + antrean order offline) → permukaan frontend lapangan
(halaman roster, check-in mobile, PoD capture, peta tracking) → integrasi & verifikasi
(parity dummy mode + RBAC menu + uji lintas unit).

Semua fitur dibangun di atas arsitektur yang ada: Laravel REST API + Next.js 16,
`withDummyRead` untuk parity dummy, middleware `rbac:<menu>:<level>`, dan `GeoMap`
Leaflet yang sudah dipakai Data Intelligence.

---

## Context

### Current State

- **Delivery:** `deliveries` (migration `2026_09_08_000012`) punya `driver_id`,
  `assigned_by_id`, `status` (`assigned|in_progress|delivered|failed`), `recipient_name`,
  `proof_of_delivery_url`, `proof_of_delivery` (json), `route_data` (json).
  `DeliveryController@store` memvalidasi driver aktif (`role === 'driver' && is_active`)
  dan memanggil `RoutingService::plan()` yang **selalu mengembalikan `[]`**.
  `UpdateDeliveryStatusRequest` sudah menerima `proof_of_delivery.photo_url` /
  `proof_of_delivery.signature_url` sebagai URL, tetapi tidak ada endpoint upload dan
  tidak ada metadata penangkapan (geo/waktu).
- **Sales visit:** `sales_visits` (migration `2026_09_08_000011`) punya `sales_user_id`,
  `outlet_id`, `visit_date`, `scheduled_at`, `status` (`planned`), `notes`, `outcome`.
  `SalesController@index/store/show/update` sudah scoped ke sales user sendiri, tetapi
  **tidak ada kolom/endpoint check-in/check-out maupun GPS**.
- **Driver role:** `User::isDriver()` ada (`role === 'driver'`); tidak ada tabel
  profil/roster driver (kendaraan, plat, kapasitas, wilayah, shift).
- **Frontend:** `apps/web/src/app/delivery/page.tsx` + `delivery/api.ts` + `filters.ts`;
  `apps/web/src/app/sales/page.tsx` + `sales/api.ts`; `apps/web/src/app/sales/orders/`.
  `manifest.ts` sudah ada (`display: standalone`) tetapi **tanpa service worker**.
  `GeoMap` (`apps/web/src/components/data-intelligence/GeoMap.tsx`) sudah render pin
  Leaflet + validasi koordinat — dapat dipakai ulang untuk tracking.
- **Parity dummy:** `apps/web/src/dummy/*` (Zustand store, `withDummyRead`,
  `commitIfCurrent`, `useDummyRefresh`) dipakai semua modul API; setiap endpoint baru
  wajib punya fixture dummy.
- **RBAC:** `MenuDefinition::CATALOG` (19 menu) + middleware `rbac:<menu>:<level>`;
  menambah menu baru butuh entri CATALOG + seeder + `NavItem` frontend.

### Problem / Motivation

- Sales tidak dapat mencatat kunjungan aktual (check-in/out) sehingga "Outlet visit
  tracking" tetap `[~]` di roadmap — tidak ada bukti kehadiran/telemetri.
- Admin tidak dapat mengelola roster driver (kendaraan/plat/kapasitas) sehingga
  assignment hanya mengandalkan `users.role = driver`.
- `RoutingService` adalah seam kosong → rute tidak dioptimasi sama sekali.
- Tidak ada live tracking → dispatcher tidak tahu posisi driver.
- PoD tidak dapat menangkap foto/tanda tangan → bukti serah-terima tidak auditable.
- Tanpa PWA/offline, sales di area sinyal lemah kehilangan order → target Phase 2
  (adoption digital ordering) terhambat.

### Related Areas

- `apps/api/app/Http/Controllers/DeliveryController.php`, `SalesController.php`
- `apps/api/app/Services/RoutingService.php`, `OrderCreationService.php`
- `apps/api/app/Models/Delivery.php`, `SalesVisit.php`, `User.php`, `Order.php`
- `apps/api/app/Http/Requests/StoreDeliveryRequest.php`, `UpdateDeliveryStatusRequest.php`
- `apps/api/database/migrations/2026_09_08_000011_create_sales_visits_table.php`,
  `2026_09_08_000012_create_deliveries_table.php`
- `apps/api/routes/api.php` (grup `auth:api`, middleware `rbac:*`)
- `apps/web/src/app/delivery/*`, `apps/web/src/app/sales/*`, `apps/web/src/app/admin/*`
- `apps/web/src/components/Topbar.tsx`, `Sidebar.tsx`, `data-intelligence/GeoMap.tsx`
- `apps/web/src/dummy/*`, `apps/web/src/lib/api.ts`
- `apps/web/next.config.js`, `apps/web/src/app/manifest.ts`

---

## Scope

### In-Scope

- **Driver roster (CRUD):** tabel `driver_profiles` (user_id unik, vehicle_type, plate,
  capacity_kg, service_territory_id, shift_start/shift_end, is_available) + service +
  controller + requests + halaman admin `/admin/drivers`.
- **GPS visit telemetry:** kolom check-in/out pada `sales_visits`
  (`check_in_at`, `check_in_latitude`, `check_in_longitude`, `check_in_accuracy_m`,
  `check_out_at`, `check_out_latitude`, `check_out_longitude`) + endpoint
  `POST /sales/visits/{id}/check-in` dan `POST /sales/visits/{id}/check-out` dengan
  validasi radius ke outlet.
- **Route optimization:** `RoutingService::plan()` menghasilkan urutan stop deterministik
  (nearest-neighbor bounded, jarak haversine, cap N stop) yang disimpan ke `route_data`.
- **Live delivery tracking:** tabel `delivery_location_pings` + endpoint
  `POST /deliveries/{id}/location` (driver) dan `GET /admin/deliveries/{id}/track`
  (admin) mengembalikan posisi terakhir + riwayat terbatas.
- **Proof of Delivery capture:** endpoint upload `POST /deliveries/{id}/proof`
  (foto + tanda tangan, disk-agnostic) yang menyimpan URL ke `proof_of_delivery` json,
  plus metadata `pod_captured_at`, `pod_latitude`, `pod_longitude`.
- **PWA offline shell:** service worker (app shell + offline fallback), registrasi,
  indikator online/offline di Topbar, dan antrean order offline (IndexedDB) dengan
  sinkronisasi otomatis saat kembali online.
- **Frontend lapangan:** halaman admin roster driver, UI check-in/out mobile untuk sales,
  UI capture PoD (kamera + canvas tanda tangan) untuk driver, dan peta live tracking.
- **Parity dummy mode** untuk seluruh endpoint/halaman baru (zero network saat ON).
- **RBAC:** menu baru `driver_roster` + `field_ops`, entri CATALOG, seeder default, dan
  `NavItem` frontend.

### Out-of-Scope

- Python ML/LLM (Phase 9) dan multi-tenant/dynamic pricing/payment gateway (Phase 10).
- AWS deployment/object storage produksi — upload PoD memakai disk `local`/`public`
  yang sudah ada; konfigurasi S3 masuk Phase 6/10 (didokumentasikan sebagai risk).
- Optimasi rute tingkat lanjut (VRP/time-window/constraint solver) — hanya nearest-neighbor
  bounded deterministik; adapter eksternal tetap disiapkan sebagai seam.
- Push notification native (FCM/APNs) — live tracking berbasis polling.
- Perubahan pada `OrderCreationService` inti, guard auth, atau skema transaksi order.
- Rebuild halaman `/data-intelligence` atau `/analytics`.

---

## Architecture Constraints

- **API:** Laravel REST, respons envelope `{status, data}`; error `{status:'error', message}`.
- **Otorisasi:** setiap route dilindungi `auth:api` + `reject.stale_jwt` + `rbac:<menu>:<level>`.
  Sales/driver scoped ke dirinya sendiri; admin melihat semua.
- **DB:** PostgreSQL runtime, SQLite test. Migrasi additive + reversible (tidak ada
  perubahan destruktif). Ekspresi/kolom harus portabel SQLite + PostgreSQL.
- **Frontend:** Next.js 16 App Router, semua halaman `'use client'`, Zustand ^4.5.0,
  Tailwind, Leaflet/react-leaflet yang sudah ada. Tidak ada dependency baru tanpa alasan kuat.
- **Parity dummy:** semua modul API baru memakai `withDummyRead`; setiap field baru punya
  fixture di `apps/web/src/dummy/*`.
- **Test:** API `php artisan test` (PHPUnit, Feature + Unit); web `npx jest` + `tsc --noEmit`.
- **Offline:** antrean order disimpan client-side (IndexedDB) dan di-flush via
  `POST /orders` yang sudah idempotent (payload hash) — tidak ada endpoint order baru.

---

## Design Decision

**DD-1 — Additive, seam-preserving field operations.** Phase 8 memperluas model yang ada
(`sales_visits`, `deliveries`) alih-alih membuat entitas baru untuk visit/delivery; hanya
`driver_profiles` dan `delivery_location_pings` yang benar-benar baru. Alasan: mempertahankan
satu sumber kebenaran order→delivery dan menghindari duplikasi state.

**DD-2 — Routing sebagai bounded deterministic service.** `RoutingService::plan()` menjadi
nearest-neighbor dengan jarak haversine dan cap `MAX_STOPS`, deterministik (tie-break by id)
agar dapat diuji tanpa ML. Adapter eksternal tetap bisa menggantikan implementasi.

**DD-3 — PoD via upload endpoint disk-agnostic.** `POST /deliveries/{id}/proof` menerima
base64/multipart, menyimpan lewat `Storage::disk(config('filesystems.default'))`-style
abstraction (default `local`), mengembalikan URL relatif. Tidak mengunci ke S3.

**DD-4 — Offline queue client-side + idempotent replay.** Antrean order offline memakai
IndexedDB dan di-flush ke `POST /orders` yang sudah idempotent; tidak ada endpoint
`/sync` baru, sehingga tidak ada risiko double-order.

**DD-5 — Live tracking via bounded polling.** `GET /admin/deliveries/{id}/track`
mengembalikan posisi terakhir + maksimum N ping; frontend polling interval tetap. Tidak
ada WebSocket/push di Phase 8.

---

## Acceptance Criteria

### AC-1 — Driver roster CRUD
- **Given** admin terautentikasi dengan `rbac:driver_roster:edit`, **When** ia `POST /admin/drivers`
  dengan `user_id` yang ber-role driver, `vehicle_type`, `plate_number`, **Then** respons 201
  `{status:'success', data:{...}}` dan record tersimpan di `driver_profiles`.
- **Given** admin, **When** ia `GET /admin/drivers`, **Then** daftar roster + `meta.total`
  dikembalikan; **When** ia `PATCH /admin/drivers/{id}`, **Then** field berubah.
- **Given** `user_id` bukan driver atau sudah punya profil, **When** `POST /admin/drivers`,
  **Then** 422 dengan pesan validasi.
- **Given** sales/outlet user, **When** akses `/admin/drivers`, **Then** 403.

### AC-2 — GPS visit check-in / check-out
- **Given** sales user dengan visit miliknya berstatus `planned`, **When** ia
  `POST /sales/visits/{id}/check-in` dengan lat/long dalam radius outlet, **Then** 200 dan
  `check_in_at` terisi.
- **Given** koordinat di luar radius (default 200 m), **When** check-in, **Then** 422.
- **Given** visit milik sales lain, **When** check-in, **Then** 403.
- **Given** visit sudah check-in, **When** check-in lagi, **Then** 409/422 (idempotent guard).
- **Given** visit sudah check-in, **When** `POST /sales/visits/{id}/check-out`, **Then**
  `check_out_at` terisi dan status berpindah ke `completed`.

### AC-3 — Route optimization
- **Given** delivery dengan order + outlet berkoordinat, **When** `RoutingService::plan()`
  dipanggil, **Then** mengembalikan array stop terurut deterministik dengan total jarak.
- **Given** lebih dari `MAX_STOPS` stop, **Then** hasil dibatasi `MAX_STOPS`.
- **Given** outlet tanpa koordinat, **Then** stop dilewati tanpa error.
- **Given** input sama, **Then** output identik (deterministik).

### AC-4 — Live delivery tracking
- **Given** driver dengan delivery miliknya, **When** `POST /deliveries/{id}/location`
  dengan lat/long, **Then** 201 dan ping tersimpan.
- **Given** admin, **When** `GET /admin/deliveries/{id}/track`, **Then** mengembalikan
  `last_position` + `pings` (maks N, terbaru dulu).
- **Given** driver lain, **When** kirim lokasi ke delivery bukan miliknya, **Then** 403.

### AC-5 — Proof of Delivery capture
- **Given** driver dengan delivery `in_progress`, **When** `POST /deliveries/{id}/proof`
  dengan foto + tanda tangan valid, **Then** 201 dan `proof_of_delivery` json berisi
  `photo_url` + `signature_url`, plus `pod_captured_at`/geo terisi.
- **Given** file melebihi batas ukuran / tipe tidak didukung, **Then** 422.
- **Given** delivery bukan milik driver, **Then** 403.

### AC-6 — PWA offline shell
- **Given** aplikasi dibuka, **When** service worker terdaftar, **Then** app shell +
  halaman offline tersedia tanpa jaringan.
- **Given** mode offline, **When** sales submit order, **Then** order masuk antrean lokal
  (bukan error) dan ter-flush ke `POST /orders` saat kembali online.
- **Given** kembali online, **When** flush berhasil, **Then** antrean kosong dan order
  muncul di daftar.

### AC-7 — Frontend field surfaces
- **Given** admin, **When** membuka `/admin/drivers`, **Then** tabel roster tampil dengan
  aksi create/edit (kontrak admin-table: sort/paging/ringkasan).
- **Given** sales, **When** membuka halaman kunjungan, **Then** tombol check-in/out
  meminta izin geolokasi dan menampilkan status.
- **Given** driver, **When** membuka detail delivery, **Then** dapat mengambil foto +
  menggambar tanda tangan dan submit PoD.
- **Given** admin, **When** membuka tracking delivery, **Then** posisi terakhir driver
  tampil di `GeoMap`.

### AC-8 — Parity & otorisasi
- **Given** dummy mode ON, **When** membuka semua halaman baru, **Then** zero network dan
  data dummy tampil (tidak ada state kosong).
- **Given** role tanpa izin, **When** akses endpoint/menu baru, **Then** 403 / menu tidak
  tampil; `platform_owner` setara admin.

---

## Open Questions / Assumptions

- **A-1:** Radius check-in default 200 m — diasumsikan cukup; dapat dijadikan config.
- **A-2:** Penyimpanan PoD memakai disk `local` (bukan S3) di Phase 8; URL disajikan via
  route `storage` atau proxy. Jika S3 diperlukan lebih awal → report NEEDS_CONTEXT.
- **A-3:** IndexedDB tersedia di semua target browser; fallback `localStorage` disiapkan
  bila IndexedDB tidak ada.
- **A-4:** Live tracking berbasis polling (bukan WebSocket) diterima untuk Phase 8.
- **A-5:** `driver_profiles` boleh kosong untuk driver lama; assignment tidak wajib punya
  profil (backward compatible).

---

## Rollback Plan

- Semua migrasi additive + `down()` reversible; rollback = `migrate:rollback`.
- Service worker dapat dinonaktifkan dengan menghapus registrasi + `sw.js` (feature flag
  `NEXT_PUBLIC_PWA_ENABLED`), tanpa memengaruhi fitur lain.
- Route baru dapat dihapus tanpa mengubah route lama; `RoutingService` tetap kompatibel
  (mengembalikan `[]` bila tidak ada koordinat).
- Antrean offline hanya di client; membersihkan IndexedDB mengembalikan perilaku lama.

---

## Dependencies

- **Existing to leverage:** `withDummyRead`/`commitIfCurrent`/`useDummyRefresh`,
  `GeoMap`, `ListQuery` (kontrak tabel admin), middleware `Rbac`, `MenuDefinition::CATALOG`,
  `OrderCreationService` (idempotent order), `AuthService`/JWT.
- **Newly proposed:** antrean offline memakai adapter storage **injectable** (`StorageAdapter`)
  dengan implementasi native `IndexedDB` + fallback `localStorage` — **tanpa dependency npm
  baru** (`idb-keyval`/`fake-indexeddb` tidak ditambahkan); service worker ditulis manual
  (tanpa `next-pwa`) agar terkontrol dan testable.
