# Admin Table Readability — Paging, Sort Terbaru, Ringkasan, Kepadatan

**Date:** 2026-09-17
**Status:** approved
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md

---

## Summary

Membuat semua tabel admin lebih mudah dibaca dengan: urutan default terbaru-terlama, paging UI yang memberi orientasi ("Halaman X dari Y · N data"), ringkasan di atas tabel (total + breakdown status), kolom `created_at`/`updated_at` yang terlihat, kontrol kepadatan baris, dan header yang dapat diklik untuk sort manual. Pekerjaan mencakup `Table.tsx`, 6 halaman admin + `api.ts` masing-masing, controller Laravel terkait, dan path dummy-mode agar tetap lolos guard.

---

## Context

### Current State
- 6 halaman admin (`outlets`, `products`, `users`, `promotions`, `sales-performance`, `orders`) masing-masing punya tabel via `components/ui/Table.tsx`.
- `Table.tsx` generik: header statis, tanpa sort header, tanpa paging/density/count.
- API: cursor+`limit` (15/20), return `hasMore` boolean. Frontend hanya render teks "Ada data lebih lanjut" — tidak ada kontrol halaman.
- Backend: `AdminOutletController@index` pakai `orderBy('id')` ASC; `UserRoleController@index` `orderBy('id')`; `PromotionController` + `SalesPerformanceController` pakai id-based cursor (`next_cursor`); `orders`/`products` tanpa cursor. `created_at`/`updated_at` ada di semua entitas tapi tidak tampil sebagai kolom.
- Dummy-mode API path (`app/admin/*/api.ts` + `withDummyRead`) sudah ada; guard & page.test terikat pada contract sekarang.

### Problem / Motivation
- Paging tak terlihat → admin tak tahu ada berapa data dan ada di halaman mana.
- Urutan default tidak konsisten/tidak terbaru → admin harus filter/tebak untuk ketemu data baru.
- Tidak ada ringkasan → admin harus hitung manual atau scroll.
- Tabel tanpa kontrol kepadatan/sort → ahli butuh dense, pemula butuh lapang, semua butuh sort kolom tanpa filter tambahan.

### Related Areas
- `apps/web/src/components/ui/Table.tsx` — generic table, satu komponen dipakai semua halaman.
- `apps/web/src/app/admin/*/page.tsx` (6 files) + `apps/web/src/app/admin/*/api.ts` (6 files) — halaman & loader terkait.
- `apps/api/app/Http/Controllers/AdminOutletController.php`, `UserRoleController.php`, `AdminProductController.php`, `PromotionController.php`, `SalesPerformanceController.php`, `OrderController.php` (admin orders).
  - Catatan: list produk pakai `GET /products` → `ProductController@index` (bukan `AdminProductController`, yang hanya punya `update` + `prices`).
- `apps/api/app/Models/Outlet.php` (`$fillable`, `VALID_CATEGORIES`), `Product`, `User`, `Order`, `Promotion` (sort key harus kolom real/ter-indeks).
- `apps/web/src/dummy/*` + `apps/web/src/app/admin/outlets/api.ts` dummy branch + `__tests__/dummy-mode.e2e.test.tsx`.
- Tests: `apps/web/src/app/admin/*/page.test.tsx`, `dummy-guard.test.ts`, `apps/api/tests/Feature/AdminOutletTest.php`, `AdminProductPriceTest.php`.

---

## Scope

### In-Scope
- Default sort terbaru-terlama (by `created_at DESC` fallback `updated_at DESC` / `id DESC` bila timestamp kosong) di semua tabel admin (frontend sort default + backend `orderBy` benar).
- Summary strip di atas tabel: total data + breakdown status/kategori relevan.
- Paging UI menggantikan teks `hasMore`: label posisi (`Halaman X dari Y` atau `X–Y dari N`) + prev/next + jump (page number), total diketahui atau estimasi berlabel.
- Kolom `created_at` / `updated_at` tampil di tabel (format lokal, toleransi null). Berlaku untuk **5 tabel entitas** (outlets, products, users, promotions, orders); `sales-performance` adalah agregat per-user per-periode → tidak punya kolom waktu (dikecualikan, lihat Implementation Notes).
- Row density toggle (Compact / Default / Comfortable) yang persist (`localStorage`) dan memengaruhi padding/row height `Table`.
- Header kolom yang sortable via klik (toggle asc/desc), dengan indikator arah, dan query param `sort`/`order` yang mengalir ke API.

### Out-of-Scope
- Export CSV/Excel — fitur terpisah, bukan keterbacaan tabel.
- Bulk action (centang banyak + aksi massal) — tidak terkait paging/sort.
- Column visibility toggle — tahap 2, tidak sekarang.
- Infinite scroll — diganti paging berorientasi, bukan virtual scroll.
- Filter baru / pencarian lanjutan — filter yang ada tidak diubah.

---

## Architecture Constraints

- Layers boleh disentuh: `components/ui/Table.tsx` (+ helper kecil domain table jika perlu), `apps/web/src/app/admin/*/page.tsx` + `api.ts`, controller Laravel admin terkait (sort param + total/count + orderBy), `apps/web/src/dummy/*` untuk path dummy.
- TIDAK boleh sentuh: pipeline `Order`/`Invoice`/`Payment`, `Auth`/JWT, schema/migrasi DB (sort harus pakai kolom yang sudah ada).
- Pola wajib: `Table` tetap generik + dipakai semua halaman; API tetap cursor/limit (tambahan param `sort`/`order`/`total`), dummy-mode path tidak pecah dan tetap diuji.
- Validasi: controller `orderBy` hanya allowlist kolom aman; paging tetap limit+1 / cursor.

### Kontrak cursor — DIPUTUSKAN (offset seragam)
Semua 6 tabel memakai **offset cursor**: `cursor=0`, lalu `limit`, `2*limit`, dst. Jump ke halaman 3 = `cursor=(3-1)*limit`. Controller `promotions` & `sales-performance` yang kini id-based cursor diubah ke offset agar konsisten. Tradeoff (skip/duplikat saat insert konkuren) diterima karena volume data admin kecil (~48–100 row).

### Kontrak `total` — DIPUTUSKAN (aditif, semua controller)
Semua 6 controller mengembalikan **top-level `meta.total`** (hasil `COUNT(*)` dengan filter yang sama persis dengan `index`) **plus** `meta.summary` untuk breakdown (bila ada). `has_more` tetap dipertahankan. Outlet saat ini menampilkan `has_more/limit/cursor` di bawah `data` — dinormalisasi agar `meta.total` seragam di top-level `meta`. Frontend memakai `meta.total`; bila `total` absen → fallback label estimasi ("Halaman 2 · ada data lain").

### Sort allowlist per controller — DIPUTUSKAN
Param `sort`/`order` divalidasi terhadap allowlist. Nilai invalid/berbahaya (`__proto__`, `; DROP`, kolom tak dikenal) → **fallback diam ke default `created_at DESC, id DESC`, HTTP 200** (bukan 422/500).

| Controller | Allowlist kolom sortable |
|-----------|--------------------------|
| outlets | `created_at, updated_at, name, id, category, score` |
| products | `created_at, updated_at, name, sku, price, stock_quantity, id` |
| users | `created_at, updated_at, name, email, role, id` |
| promotions | `created_at, updated_at, start_date, end_date, id` |
| orders | `created_at, updated_at, order_id, status, total_amount, id` |
| sales-performance | `name, id` (kolom DB real) — `achievement` diurutkan client-side (nilai derived), `period` adalah filter param, bukan kolom |

> **Catatan sales-performance:** karena `achievement` dihitung dari `SalesPerformanceService::calculatePerformance` (bukan kolom DB), sort `achievement` dilakukan **client-side** setelah data termuat. Default sort endpoint ini = `name ASC, id ASC` (urutan DB), lalu frontend menampilkan default `achievement DESC` via sort client-side. Konversi cursor id-based → offset tetap berlaku.

### Nulls-last dua arah — DIPUTUSKAN
Timestamp kosong dikoersi ke `NULL`. Ordering memakai ekspresi **portabel** `ORDER BY (<col> IS NULL) ASC, <col> <dir>, id DESC` — berlaku identik di PostgreSQL (runtime) dan SQLite in-memory (suite test), tanpa bergantung sintaks `NULLS LAST` yang tidak didukung semua versi SQLite. Frontend defensif: null selalu di bawah pada asc maupun desc.

### Summary strip source — DIPUTUSKAN
Summary dihitung dari query agregat **terpisah dengan filter yang sama** dengan `index` (bukan dari panjang `data` paginated). Backend return `meta.summary`. Kunci breakdown dipatok per halaman:
- outlets → `{ active, inactive }`
- products → `{ total, out_of_stock }` (stok ≤ 0)
- users → `{ total }` (opsional breakdown `by_role` bila murah)
- promotions → `{ total, active, scheduled, ended }` (berdasarkan `start_date`/`end_date` vs now)
- orders → `{ total }`
- sales-performance → `{ total }`

Halaman tanpa breakdown natural (users/orders/sales-performance) → summary cukup total.

### Dummy-mode parity — DIPUTUSKAN
`apps/web/src/app/admin/*/api.ts` dummy branch + helper `listDummy*` WAJIB implementasi sort/order/total yang identik: urutkan `created_at DESC` (fallback `id DESC`), offset slice sama, `total = filtered.length`, allowlist sama. Harus tetap zero-network dan lolos `dummy-guard.test.ts` + `dummy-mode.e2e.test.tsx`.

### Table.tsx generic contract — DIPUTUSKAN
Sortable state tinggal di `Table` via props opsional: `sortableColumns?: string[]`, `sort?: { column, direction }`, `onSort?: (k) => void`. Kolom `Aksi` tidak masuk `sortableColumns` (inert). `th` sortable render `aria-sort`. Halaman `orders` (fetch inline tanpa api.ts) tetap pakai props yang sama dengan local sort state.

> **Empty state:** saat `rows.length === 0`, `Table` menampilkan `empty` (tanpa `<thead>`) seperti sekarang — diterima karena tidak ada baris untuk di-sort; summary strip tetap menampilkan "0" di atas tabel.

### Authorization — DIPUTUSKAN
Param `sort`/`order`/`total` **inherit guard yang sudah ada** di tiap endpoint (tidak ada aturan baru). Unauthorized → perilaku 401/403 seperti sekarang, tidak berubah.

### Density + SSR hydration — DIPUTUSKAN
Initial density = **Default** di server render; baca `localStorage` (`admin:table-density`) hanya di `useEffect` setelah mount (mirror pola `readPersistedFlag` di `dummy/store.ts`) agar tidak hydration mismatch. Viewport sempit = scroll horizontal di dalam `overflow-x-auto` card (bukan page scroll).

- Architecture validation result: PASS (lihat Phase 6 checklist).

---

## Dependencies

### Existing (to leverage)
- `Next.js 16` + `React 18` + `Tailwind CSS` + `Zustand` (dummy store) — stack web.
- `jest` + `@testing-library/react` + `babel-jest` — test framework web.
- `Laravel 11` Eloquent query builder — sort/pagination/count di backend.

### New (proposed)
- none — tidak ada library baru. Sort/density/paging murni komponen + query param.

---

## Stories + Scenarios

### Story 1: Urutan default terbaru-terlama
> As an **admin**, I want tabel admin terurut terbaru→terlama secara default, so that data yang baru langsung terlihat tanpa filter.

**Rule 1: default sort terbaru**
- Example A: Outlet dengan `created_at` newest muncul baris pertama.
- Example B: Produk tanpa `created_at` jatuh ke `id DESC`.
- Example C: Backend `GET /admin/outlets` tanpa `sort` tetap `created_at DESC` (atau `id DESC` bila belum ada timestamp).

```gherkin
Scenario: default sort terbaru — happy path
  Given admin membuka halaman "Kelola outlet" pertama kali (belum set sort)
  When halaman memuat outlet
  Then baris pertama adalah outlet dengan created_at terbaru (atau id terbesar bila timestamp null)

Scenario: backend default sort tanpa param
  Given admin memanggil GET /admin/outlets tanpa query sort
  When backend mengembalikan data
  Then urutan data adalah created_at DESC nulls-last lalu id DESC

Scenario: sort kolom yang tidak diizinkan
  Given admin memanggil GET /admin/outlets?sort=__proto__&order=desc
  When backend memvalidasi sort terhadap allowlist
  Then backend fallback diam ke default terbaru (HTTP 200, bukan 422/500)
```

**Rule 2: sort manual via header**
- Example A: Klik header "Dibuat" → `sort=created_at&order=desc` (toggle ke `asc` pada klik kedua).
- Example B: Klik header non-sortable (mis. "Aksi") tidak mengubah sort.

```gherkin
Scenario: sort manual via klik header
  Given admin melihat tabel outlet dengan kolom "Dibuat" sortable
  When admin mengklik header "Dibuat"
  Then tabel terurut created_at DESC dan indikator panah tampil di header "Dibuat"

Scenario: toggle sort direction
  Given tabel sudah sort created_at DESC
  When admin mengklik header "Dibuat" lagi
  Then tabel terurut created_at ASC dan indikator panah berubah arah

Scenario: kolom non-sortable tidak memicu sort
  Given header "Aksi" ditandai non-sortable
  When admin mengklik header "Aksi"
  Then sort tidak berubah (tidak ada onSort, tidak ada fetch)
```

### Story 2: Paging berorientasi + label posisi
> As an **admin**, I want paging yang menunjukkan "Halaman X dari Y" + prev/next + jump, so that saya tahu posisi saya dan bisa lompat halaman.

**Rule 3: paging UI menggantikan hasMore text**
- Example A: 48 outlet, limit 15 → label "Halaman 1 dari 4 · 15 dari 48" dan tombol 1 2 3 4.
- Example B: Halaman terakhir: next disabled.

```gherkin
Scenario: paging label & kontrol tampil
  Given ada 48 outlet dan admin di halaman 1 (limit 15)
  When tabel dirender
  Then label menampilkan total/halaman yang benar dan tombol nomor halaman + prev/next tampil

Scenario: halaman terakhir — next disabled
  Given admin di halaman terakhir
  When tabel dirender
  Then tombol "Berikutnya" disabled dan tidak memicu fetch

Scenario: jump ke halaman
  Given admin di halaman 1 dari 4
  When admin memilih halaman 3 (klik nomor)
  Then tabel memuat data halaman 3 dengan cursor=(3-1)*limit dan sort+filter dipertahankan

Scenario: cursor beyond total — halaman kosong tanpa crash
  Given total 48 (4 halaman) dan admin memaksa cursor=60 (halaman 5)
  When request dikirim
  Then backend return data:[] has_more:false; UI disable next + tampil "tidak ada data lanjutan" tanpa crash

Scenario: kasus limit+1 tanpa total — estimasi berlabel
  Given backend hanya mengembalikan has_more tanpa total
  When frontend menampilkan paging
  Then label memakai bentuk estimasi ("Halaman 2 · ada data lain") tanpa klaim total palsu
```

**Rule 4: backend total/count untuk paging**
- Example A: `GET /admin/outlets` mengembalikan `meta.total` selain `has_more/limit/cursor`.
- Example B: Total konsisten dengan filter yang sama.

```gherkin
Scenario: backend mengembalikan total
  Given admin memanggil GET /admin/outlets?limit=15
  When backend merespons
  Then payload berisi meta.total yang konsisten dengan filter

Scenario: total dengan filter aktif
  Given admin memanggil GET /admin/outlets?category=warung&limit=15
  When backend merespons
  Then meta.total mencerminkan hanya outlet ber-kategori warung
```

**Rule 5: reset state (sort/filter/page interplay)**
- Example A: Ubah sort ATAU ubah filter → reset `cursor=0`, param lain dipertahankan.
- Example B: Ubah halaman → sort + filter dipertahankan.

```gherkin
Scenario: ganti filter saat di halaman 3 reset cursor
  Given admin di halaman 3 (cursor=30) lalu ubah category=warung
  When admin terapkan filter
  Then request dikirim dengan cursor=0 + sort/order dipertahankan + filter baru; total mencerminkan filter

Scenario: ganti sort reset cursor
  Given admin di halaman 2 dengan sort created_at DESC
  When admin klik header "Nama"
  Then request dikirim dengan cursor=0 + sort baru + filter dipertahankan

Scenario: pindah halaman pertahankan sort+filter
  Given admin di halaman 1 dengan sort created_at DESC dan filter category=warung
  When admin pilih halaman 2
  Then request dikirim dengan cursor=limit + sort/order + filter yang sama
```

### Story 3: Ringkasan di atas tabel
> As an **admin**, I want strip ringkasan di atas tabel (total + breakdown), so that saya orientasi sebelum membaca baris.

**Rule 6: summary strip**
- Example A: Outlets: "48 outlet · 32 aktif · 16 nonaktif".
- Example B: Products: "30 produk · 2 stok habis".

```gherkin
Scenario: summary strip tampil
  Given admin membuka halaman outlet dengan 48 data
  When halaman selesai load
  Then strip di atas tabel menampilkan total + breakdown (mis. aktif/nonaktif)

Scenario: ringkasan saat filter aktif
  Given admin memfilter category=warung (12 hasil)
  When tabel termuat
  Then strip menampilkan angka yang sesuai filter ("12 outlet (filter: warung)")

Scenario: data kosong — ringkasan tetap informatif
  Given tidak ada outlet yang cocok filter
  When tabel termuat
  Then strip menampilkan "0 outlet" dan tabel menampilkan empty state (tidak kosong membingungkan)
```

### Story 4: Kolom waktu & format
> As an **admin**, I want kolom `Dibuat` / `Diperbarui` di tabel, so that saya tahu mana yang baru tanpa buka detail.

**Rule 7: kolom created_at/updated_at**
- Example A: Outlet row menampilkan "17 Sep 2026, 10:00".
- Example B: Data tanpa timestamp tampil "—".

```gherkin
Scenario: kolom waktu tampil & terformat
  Given sebuah outlet memiliki created_at
  When tabel dirender
  Then kolom "Dibuat" menampilkan tanggal terformat lokal (id-ID) dan bukan ISO mentah

Scenario: nilai waktu null
  Given sebuah produk tanpa created_at
  When tabel dirender
  Then sel kolom menampilkan "—" (bukan "Invalid Date")

Scenario: sort by kolom waktu — nulls-last dua arah
  Given kolom "Dibuat" sortable dan ada baris null
  When admin sort "Dibuat" DESC lalu ASC
  Then baris null selalu di bawah pada kedua arah; baris non-null terurut benar (id DESC tiebreak)
```

### Story 5: Kepadatan baris
> As an **admin**, I want toggle kepadatan (Compact/Default/Comfortable), so that saya atur tabel sesuai preferensi dan perangkat.

**Rule 8: density toggle**
- Example A: Pilih Compact → padding baris mengecil dan terlihat lebih banyak baris per viewport.
- Example B: Pilihan persist setelah reload.

```gherkin
Scenario: density toggle mengubah tampilan
  Given admin melihat tabel dengan density Default
  When admin memilih "Compact"
  Then tinggi baris/padding mengecil dan pilihan tersimpan

Scenario: density persist setelah reload
  Given admin memilih "Comfortable" lalu reload halaman
  When halaman termuat kembali
  Then density masih "Comfortable" (dibaca dari localStorage setelah mount)

Scenario: density tidak merusak layout
  Given density apapun (Compact/Default/Comfortable)
  When tabel dirender di viewport sempit
  Then tabel tetap scrollable horizontal dalam card (bukan overflow page)
```

---

## Acceptance Criteria

```
Rule: default sort terbaru
  ✓ Given admin buka halaman admin tanpa set sort, When data dimuat, Then baris pertama = created_at terbaru (atau id DESC bila null)
  ✓ Given GET /admin/outlets tanpa sort, When backend merespons, Then urutan = created_at DESC nulls-last lalu id DESC
  ✗ Given GET /admin/outlets?sort=kolom_tidak_aman, When divalidasi, Then sort diabaikan → fallback default terbaru (HTTP 200, tidak 422/500)

Rule: sort manual via header
  ✓ Given kolom Dibuat sortable, When klik header Dibuat, Then sort=created_at DESC dan indikator tampil
  ✓ Given sudah sort DESC, When klik lagi, Then sort ASC dan indikator berbalik
  ✓ Given header Aksi non-sortable, When diklik, Then sort tidak berubah

Rule: paging UI
  ✓ Given 48 data limit 15 di halaman 1, When tabel dirender, Then label posisi + nomor halaman + prev/next tampil benar
  ✓ Given di halaman terakhir, When dirender, Then tombol Berikutnya disabled
  ✓ Given admin pilih halaman 3, When jump, Then fetch halaman 3 dengan cursor=(3-1)*limit dan sort+filter dipertahankan
  ✓ Given cursor beyond total, When request dikirim, Then data:[] + next disabled + tidak crash
  ✓ Given backend tanpa total, When paging dirender, Then label memakai estimasi berlabel (tidak klaim total palsu)

Rule: backend total/count
  ✓ Given GET /admin/outlets?limit=15, When merespons, Then payload berisi meta.total yang konsisten
  ✓ Given GET /admin/outlets?category=warung, When merespons, Then meta.total mencerminkan filter

Rule: reset state (sort/filter/page)
  ✓ Given di halaman 3 lalu ubah filter, When terapkan, Then cursor=0 + sort dipertahankan + filter baru
  ✓ Given di halaman 2 lalu ubah sort, When klik header lain, Then cursor=0 + sort baru + filter dipertahankan
  ✓ Given pindah halaman, When jump, Then sort+filter dipertahankan

Rule: summary strip
  ✓ Given halaman outlet 48 data, When load selesai, Then strip menampilkan total + breakdown
  ✓ Given filter aktif (warung → 12), When termuat, Then strip mencerminkan angka terfilter
  ✓ Given 0 hasil, When termuat, Then strip "0" + empty state (tidak blank)

Rule: kolom waktu & format
  ✓ Given row punya created_at, When dirender, Then kolom Dibuat terformat lokal (bukan ISO mentah)
  (Sales-performance dikecualikan — baris adalah agregat per-user per-periode tanpa `created_at`; lihat Implementation Notes.)
  ✓ Given row tanpa timestamp, When dirender, Then sel menampilkan "—"
  ✓ Given sort Dibuat DESC/ASC, When diterapkan, Then terbaru/terlama benar, null selalu di bawah

Rule: density toggle
  ✓ Given density Default, When pilih Compact, Then padding/height mengecil dan pilihan tersimpan
  ✓ Given pilih Comfortable lalu reload, When halaman termuat, Then density masih Comfortable
  ✓ Given density apapun di viewport sempit, When dirender, Then tabel tetap scrollable tanpa overflow page
```

---

## Design Decision

**Chosen option:** Option B — Full Paging.

**Summary:** Upgrade `Table.tsx` generik (density + sortable header + paging slot) dan dipakai 6 halaman admin; controller Laravel menambah sort allowlist + `meta.total`/`meta.summary` (tetap cursor/limit offset); path dummy disesuaikan agar contract baru tidak memecah guard/test.

**Rejected options:**
- **Option A (Smart Default saja):** default sort + summary strip + kolom waktu, tanpa paging UI. Ditolak — skenario "paging label & kontrol", "cursor beyond total", dan "reset state" gagal karena tidak ada paging.
- **Option C (Progressive bertahap):** fase 1 sort+summary, fase 2 paging, fase 3 density. Ditolak — acceptance criteria paging + density harus lolos penuh; pemecahan fase menambah risiko setengah jadi.

**Key tradeoffs accepted:**
- `COUNT(*)` + agregat tambahan per list (biaya 1–2 query) — diterima demi label "X dari N" + summary.
- Offset cursor (bukan keyset/id-based) — diterima demi keseragaman; tradeoff skip/duplikat saat insert konkuren tidak material pada volume admin kecil.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Kolom `created_at`/`updated_at` ada di semua model? | assumed: `timestamps()` ada di Outlet/Product/User/Order/Promotion (5 entitas; `sales-performance` dikecualikan karena agregat per-user per-periode — lihat Implementation Notes); bila tidak → fallback `id DESC`, kolom render "—" | Sort terbaru jatuh ke id; kolom tetap render tanpa crash |
| `COUNT(*)` tambahan boleh? | resolved: boleh (halaman admin, bukan hot path) | — |
| Key localStorage density | resolved: `admin:table-density` global | — |
| Cursor semantics | resolved: offset seragam | — |
| Total shape | resolved: top-level `meta.total` + `meta.summary` aditif | — |
| Sort sales-performance `achievement` | resolved: `achievement`/`period` bukan kolom DB → sort client-side; allowlist server-side hanya `name, id` | — |

---

## Implementation Notes

- `Table.tsx` tetap generic: tambah props `density`, `sortableColumns`, `onSort`, `sortState`; paging control jadi komponen `TablePagination` terpisah agar tidak membengkakkan `Table`.
- Query param sort: `sort` + `order` (`asc`/`desc`), allowlist per-controller (tabel di atas).
- Format tanggal: `Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })` atau helper existing; null/malformed → "—".
- Dummy-mode: dummy branch di `apps/web/src/app/admin/*/api.ts` urutkan `created_at DESC` (fallback `id DESC`), offset slice sama, `total = filtered.length`, allowlist sama; tetap zero-network.
- Reset state: ganti sort/filter → `cursor=0`; pindah halaman → pertahankan sort+filter.
- **`orders`** — `OrderController@index` sudah `latest('created_at')` tapi belum punya `cursor`/offset (hanya `limit`). Tambah offset cursor + `meta.total` TANPA menghapus cek `isAdmin()` dan middleware `deny.finance` (dipakai bersama oleh `/orders` dan `/admin/orders`).
- **`products`** — halaman admin products memakai endpoint publik `GET /products` (`ProductController@index`, filter supplier aktif + `orderBy('id')` + hard `limit(100)` tanpa cursor). Tambah `sort`/`order`/`cursor`/`total` secara **aditif** dengan default yang mempertahankan perilaku sekarang (orderBy id, limit 100) agar marketplace/catalog tidak terpengaruh.
- **`sales-performance`** — kolom `achievement` adalah nilai **derived** (dihitung `performanceService`, bukan kolom DB), jadi sort `achievement` dilakukan **client-side** setelah data termuat; allowlist sort server-side untuk endpoint ini hanya kolom DB (`name`, `id`). `period` adalah query param, bukan kolom sortable. Konversi cursor dari id-based ke offset pada `PromotionController@index` + `SalesPerformanceController@adminPerformance` (hapus `where id > cursor`, ganti `offset`).
- **`sales-performance` & Rule 7 (kolom waktu)** — `SalesPerformanceRow` adalah **agregat per-user per-periode**, bukan entitas, sehingga tidak memiliki `created_at`/`updated_at`. Kolom `Dibuat`/`Diperbarui` **tidak** ditambahkan di halaman ini; Rule 7 berlaku hanya untuk 5 tabel entitas (outlets, products, users, promotions, orders). Kolom `Periode` yang sudah ada menjadi penanda waktu di halaman sales-performance.
- **Tabel tanpa timestamp (dummy)** — dummy produk/order bisa tidak punya `created_at`; comparator menaruh null di bawah (nulls-last) dan kolom render `—`.

---

## Rollback Plan

- Frontend: revert `Table.tsx` + 6 `page.tsx`/`api.ts` — tabel kembali ke hasMore text & tanpa kolom waktu/density (tidak ada migrasi).
- Backend: revert controller `sort`/`total`/`summary` — response tetap kompatibel karena frontend baru toleran terhadap `total` yang hilang (fallback estimasi).
- Dummy: revert branch dummy di `api.ts` bila perlu (in-memory only).
