# Outlet Recommendation Discovery ("Coba produk lain")

**Date:** 2026-10-06
**Status:** draft
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md

---

## Summary

Menambahkan daftar discovery produk yang belum pernah dibeli outlet ("Coba produk lain") di bawah section "Pesan Lagi" pada `OrderForm` (`/orders`). Ranking deterministik tanpa ML (populer per kategori outlet, fallback populer global untuk outlet baru), dengan guard ketersediaan, alasan transparan per item, tambah-ke-keranjang + dismiss session-only, dan tracking funnel `displayed/clicked/cart` via endpoint outlet baru. Menyelesaikan pitch `pitch-exploration.md` di direktori yang sama (Direction A: discovery terpisah dari reorder).

---

## Context

### Current State

- Reorder sudah ada & teruji: `useOutletReorder` (favorites + 3 order terakhir, guard stok/nonaktif/kredit) + `ReorderSection` ("Pesan Lagi") terintegrasi di `OrderForm` (`apps/web/src/components/OrderForm.tsx`). Guard flag `NEXT_PUBLIC_ENABLE_REORDER`.
- Rekomendasi server saat ini BUKAN discovery: `GET /api/ai/recommendations` → `AIController::recommendations()` → `RecommendationModelAdapter::resolve()->predict()` → `RecommendationService::recommend()` memakai `purchase_frequency_v1` — meranking produk yang **sudah dibeli** outlet. Tidak ada sinyal co-purchase/popularitas/never-bought di API.
- Konsumen existing: halaman `/analytics` (rekomendasi heuristik + `TrustLabel`), halaman `/dashboard` (favorites + orders untuk reorder).
- Infrastruktur tracking sudah ada tapi admin-only: `POST /admin/measurement/events` menerima `event_type=displayed/clicked/cart` dengan `event_uuid`, `outlet_id`, `product_id`, `occurred_at` → model `RecommendationEvent`. Endpoint ini memakai `rbac:data_intelligence:read` sehingga outlet tidak bisa memakainya langsung.
- Pola yang dipakai ulang: guard ketersediaan (`is_active`, `stock_quantity > 0`, supplier `subscription_status active` — teruji di `AITest::test_outlet_purchase_history_returns_bounded_ranked_recommendations`), alasan transparan per item, kontrol penuh outlet (tanpa auto-add), `resolveOutletId()` token-scope di `AIController`, validasi stok atomik di `OrderCreationService` (422 `items.{index}.quantity: "Insufficient stock..."`).

### Problem / Motivation

Outlet hanya bisa memesan ulang produk yang pernah dibeli (via "Pesan Lagi") atau mencari manual di katalog ("Pilih produk"). Tidak ada jalur ringan untuk menemukan produk baru yang relevan. Endpoint `/api/ai/recommendations` yang ada justru memperkuat pembelian berulang, bukan discovery. Pitch 2026-10-06 memutuskan Direction A: discovery sebagai section terpisah di bawah "Pesan Lagi" agar reorder cepat tidak terganggu.

### Related Areas

- `apps/api/app/Http/Controllers/AIController.php` — tambah method `discovery()` + `storeDiscoveryEvent()`, reuse `resolveOutletId()` (diperketat: outlet-only, 403 untuk non-outlet).
- `apps/api/app/Services/RecommendationService.php` — tambah method `discover()` (query never-bought + ranking populer), reuse konstanta `EXCLUDED_ORDER_STATUSES`, `DEFAULT_LIMIT`.
- `apps/api/routes/api.php` — tambah `GET /ai/discovery` + `POST /ai/discovery/events` dalam grup `prefix('ai')` (tanpa `rbac:` gate, otorisasi di controller seperti route AI existing).
- `apps/api/app/Models/RecommendationEvent.php` — reuse tanpa perubahan skema.
- `apps/web/src/components/OrderForm.tsx` — render section discovery di bawah `ReorderSection`, pakai `useOnlineStatus()` untuk hide saat offline.
- `apps/web/src/hooks/` — hook baru (mis. `useOutletDiscovery`) mengikuti pola `useOutletReorder`.
- Tests: `apps/api/tests/Feature/AITest.php` (pola guard), web component test mengikuti `ReorderSection` test.

---

## Scope

### In-Scope

- Daftar discovery produk never-bought, tampil terpisah di bawah "Pesan Lagi" pada `OrderForm` (`/orders`), maksimal 5 item.
- Sinyal ranking deterministik tanpa ML: populer per kategori outlet (agregat `SUM(quantity)` order non-batal semua outlet dalam kategori yang pernah dibeli outlet ini); fallback populer global untuk outlet tanpa riwayat (label "Populer di platform").
- Guard ketersediaan: hanya produk `is_active`, `stock_quantity > 0`, supplier aktif (atau tanpa supplier); deduplikasi terhadap produk sudah-dibeli, produk di keranjang aktif, dan produk ter-dismiss sesi ini.
- Alasan transparan per item: "Populer di kategori \<X\>" / "Populer di platform"; tambah-ke-keranjang memakai harga/stok katalog terkini; dismiss session-only (memori browser).
- Error/empty states: pesan "Belum ada rekomendasi baru saat ini" saat semua kandidat tersaring; state error ringan + tombol "Coba lagi" saat fetch gagal padahal online (tidak memblokir OrderForm); disembunyikan saat offline dengan pesan "tersedia saat online".
- Tracking funnel outlet: `POST /api/ai/discovery/events` (outlet-auth, `outlet_id` dari token) mencatat `displayed/clicked/cart` ke tabel `RecommendationEvent` yang ada; kegagalan tracking tidak memblokir order (fire-and-forget + `console.warn`).
- API + web tests untuk perilaku baru.

### Out-of-Scope

- ML / model eksternal / ranking probabilistik — v1 deterministik saja (alasan: transparansi + tanpa infrastruktur model).
- Auto-add ke keranjang tanpa aksi outlet — discovery sepenuhnya opt-in (alasan: kontrol outlet).
- Perubahan logika reorder existing ("Pesan Lagi" tidak diubah) — discovery query/section terpisah (alasan: hindari regresi alur reorder cepat).
- Rekomendasi untuk role non-outlet (admin/sales/marketplace) — endpoint discovery outlet-only, 403 untuk role lain (alasan: scope pitch outlet-facing).
- Klaim "Optimal/AI" di UI tanpa basis terukur — alasan memakai label populer yang jujur (alasan: hindari overclaim).
- Migrasi DB baru — reuse tabel `RecommendationEvent`, dismiss session-only (alasan: constraint arsitektur; migrasi hanya jika terbukti perlu, dan tidak terbukti perlu).

---

## Architecture Constraints

- Layers this work may touch: `RecommendationService` (+ method baru), `AIController` (+ 2 method baru), route `/api/ai/*`, outlet web hooks & components (`OrderForm`, hook baru, section baru), outlet cache invalidation setelah order (ikut pola reorder).
- Layers this work must NOT touch: kontrak `OrderCreationService` (validasi stok 422 existing dipakai apa adanya), auth/RBAC existing (otorisasi outlet-only via pola `resolveOutletId()`/`isOutlet()` yang sudah ada, tanpa mengubah gate), skema DB existing (tanpa migrasi).
- Patterns that must be followed: heuristik deterministik + bounded (limit 5, tie-breaker `id ASC`), metadata fallback (`fallback`, `method`, `data_sufficiency`-style), guard reuse dari `AITest`, idempotency order tetap berlaku, tracking fire-and-forget.
- Architecture validation result: PASS (lihat temuan di bawah).

**Temuan validasi (Phase 6, terhadap kode aktual):**
1. `AIController::resolveOutletId()` sudah mengimplementasikan token-scope + 403 lintas-outlet (`AITest` baris 122 menegaskan forbidden); pengetatan outlet-only untuk discovery memakai pola `isOutlet()` yang sudah ada di `segmentation()` — tanpa perubahan auth/RBAC. ✅
2. `RecommendationService::EXCLUDED_ORDER_STATUSES = ['Cancelled','Canceled','Rejected','Invalid']` cocok dengan definisi "sudah dibeli" yang disetujui — reuse langsung. ✅
3. `OrderCreationService` baris 211-213 sudah mengembalikan 422 atomik `items.{index}.quantity: "Insufficient stock. Only N unit(s) remain."` — skenario "stok habis antara tambah dan submit" terpenuhi tanpa menyentuh kontrak. ✅
4. Kolom `products.category` (nullable), `is_active`, `stock_quantity`, `supplier_id` (nullable, `nullOnDelete`) terkonfirmasi di migrasi — query populer-per-kategori + guard supplier memungkinkan tanpa migrasi. ✅
5. `RecommendationEvent` fillable mencakup `event_uuid/outlet_id/product_id/occurred_at/metadata` — endpoint outlet baru reuse tabel tanpa perubahan skema. ✅
6. `OrderForm` render `ReorderSection` di dalam `<section>` sebelum "Pilih produk" (baris ~260), dengan `useOnlineStatus()` tersedia — slot penempatan discovery terkonfirmasi. ✅
7. Grup route `prefix('ai')` memang tanpa `rbac:` gate (otorisasi di controller) — endpoint baru mengikuti pola yang sama. ✅

---

## Dependencies

### Existing (to leverage)

- `RecommendationService` — guard ketersediaan + `EXCLUDED_ORDER_STATUSES` + pola bounded/limit + metadata fallback.
- `AIController::resolveOutletId()` / `isOutlet()` / `forbidden()` — token-scope + outlet-only guard.
- `RecommendationEvent` (model + tabel) — penyimpanan event funnel tanpa migrasi.
- `OrderCreationService` — validasi stok atomik 422 saat submit.
- Web: `OrderForm`, `useOnlineStatus()`, pola `useOutletReorder`/`ReorderSection` (fetch, guard flag, cache invalidation).
- Tests: `AITest.php` (pola guard + scoping test) sebagai template test baru.

### New (proposed)

none.

---

## Stories + Scenarios

### Story: Discovery terpisah di OrderForm

> As a outlet, I want melihat produk yang belum pernah saya beli di bawah "Pesan Lagi", so that saya bisa menemukan produk baru tanpa mengganggu reorder.

**Rule 1: eksklusi produk yang sudah dibeli**
- Example A: outlet pernah membeli P1 dan P2 (order non-batal) → keduanya tidak muncul di discovery.
- Example B: outlet pernah beli P1 lalu P1 dinonaktifkan → tetap tidak muncul.
- Example C: produk hanya muncul di order berstatus Cancelled/Canceled/Rejected/Invalid → TIDAK dikecualikan (boleh muncul).

```gherkin
Scenario: discovery hanya menampilkan produk belum-pernah-dibeli yang tersedia
  Given outlet dengan riwayat pembelian P1 dan P2 (order non-batal), dan katalog berisi P1, P2, P3 (aktif, stok 10), P4 (stok 0)
  When outlet membuka /orders
  Then section "Coba produk lain" tampil di bawah "Pesan Lagi" berisi P3 dengan alasan, dan tidak berisi P1, P2, P4

Scenario: deduplikasi terhadap keranjang aktif
  Given P3 tampil di discovery dan outlet menambah P3 ke keranjang
  When keranjang berisi P3
  Then P3 tidak lagi tampil di discovery selama sesi tersebut

Scenario: discovery kosong karena semua kandidat tersaring
  Given semua produk belum-pernah-dibeli stok 0 atau nonaktif
  When outlet membuka /orders
  Then section discovery tampil dengan pesan "Belum ada rekomendasi baru saat ini" tanpa tombol tambah
```

**Rule 2: guard ketersediaan**
- Example A: produk P3 aktif, stok 10, supplier aktif → tampil di discovery.
- Example B: produk P4 stok 0 / nonaktif / supplier nonaktif → tidak tampil.

**Rule 3: dismiss session-only**
- Example A: outlet dismiss P3 → P3 hilang dari discovery sampai reload.
- Example B: outlet dismiss P3 lalu tambah P3 via katalog/search → discovery tetap tidak menampilkan P3 sesi itu.

```gherkin
Scenario: dismiss menyembunyikan item sesi ini
  Given P3 tampil di discovery
  When outlet dismiss P3
  Then P3 hilang dari discovery sampai halaman dimuat ulang
```

### Story: Outlet baru tanpa riwayat

> As a outlet baru tanpa riwayat pembelian, I want melihat produk populer yang tersedia, so that saya bisa mulai memesan dengan panduan.

**Rule: fallback populer global (limit 5, tie-breaker id ASC)**
- Example A: outlet tanpa order, 8 produk aktif tersedia → discovery berisi 5 produk berlabel "Populer di platform".
- Example B: kurang dari 5 produk tersedia → tampilkan yang ada tanpa error.
- Example C: 0 produk tersedia → pesan "Belum ada rekomendasi baru saat ini".

```gherkin
Scenario: outlet baru melihat produk populer global
  Given outlet tanpa riwayat pembelian, dan 8 produk aktif tersedia
  When outlet membuka /orders
  Then discovery berisi maksimal 5 produk dengan alasan "Populer di platform"

Scenario: ranking deterministik saat skor seri
  Given 8 produk dengan skor popularitas identik
  When discovery diambil berulang
  Then 5 produk yang sama kembali setiap request (tie-breaker id ASC)
```

### Story: Tambah ke keranjang + validasi harga/stok

> As a outlet, I want menambahkan produk discovery ke keranjang dengan harga/stok terkini, so that tidak memesan produk yang sudah tidak tersedia.

**Rule 1: gunakan katalog terbaru saat tambah**
- Example A: harga katalog P3 = 12.000 → keranjang berisi P3 qty 1 seharga 12.000.
- Example B: stok katalog P3 berubah jadi 0 sebelum diklik → tombol tambah nonaktif dengan alasan "Stok habis".
- Example C: harga berubah 12.000 → 15.000 sebelum diklik → tombol tetap aktif, tampilkan harga baru 15.000.

**Rule 2: validasi final saat submit (kontrak existing)**
- Example: stok P3 habis antara tambah dan submit → submit 422 atomik, order tidak dibuat, item error untuk P3 ditampilkan.

```gherkin
Scenario: tambah produk discovery menggunakan data katalog terkini
  Given P3 tampil di discovery dengan harga katalog 12.000 dan stok 5
  When outlet klik "Tambah" pada P3
  Then keranjang berisi P3 qty 1 dengan harga katalog terkini

Scenario: produk discovery kehabisan stok sebelum ditambah
  Given katalog terbaru menunjukkan P3 stok 0
  When outlet melihat item P3 di discovery
  Then tombol "Tambah" nonaktif dengan alasan "Stok habis"

Scenario: stok habis antara tambah dan submit
  Given keranjang berisi P3, lalu stok P3 menjadi 0 sebelum submit
  When outlet submit order
  Then order tidak dibuat dan item error untuk P3 ditampilkan
```

### Story: Tracking CTR & add-rate

> As a tim produk, I want mencatat event tampil/klik/tambah pada discovery outlet, so that CTR dan add-rate dapat diukur.

**Rule: tracking tidak memblokir order**
- Example A: endpoint tracking gagal → `console.warn`, event hilang, order tetap bisa dibuat.
- Example B: setiap item discovery yang tampil mendapat `displayed`; klik "Tambah" mendapat `clicked` + `cart`.

```gherkin
Scenario: event tracking discovery tercatat
  Given outlet melihat 3 item discovery dan menambah 1 ke keranjang
  When interaksi terjadi
  Then event displayed tercatat untuk 3 item, dan clicked + cart tercatat untuk item yang ditambah

Scenario: kegagalan tracking tidak memblokir order
  Given endpoint tracking error
  When outlet menambah produk discovery dan submit order
  Then order tetap dibuat dan error tracking tidak ditampilkan ke outlet
```

### Story: Offline

> As a outlet offline, I want tidak melihat discovery basi, so that tidak memesan berdasarkan data lama.

**Rule: discovery hanya saat online**
- Example A: offline → section discovery disembunyikan dengan pesan "tersedia saat online"; reorder cache tetap tampil.
- Example B: kembali online → discovery dimuat ulang.
- Example C: online tapi fetch 500/timeout → "Pesan Lagi" tetap tampil; discovery tampil state error ringan + tombol "Coba lagi".

```gherkin
Scenario: discovery disembunyikan saat offline
  Given outlet offline dengan cache reorder tersedia
  When outlet membuka /orders
  Then "Pesan Lagi" tampil dari cache, discovery disembunyikan dengan pesan "tersedia saat online"

Scenario: discovery dimuat ulang saat kembali online
  Given outlet kembali online
  When status koneksi berubah
  Then discovery dimuat ulang dari server

Scenario: discovery gagal dimuat padahal online
  Given outlet online tetapi GET /api/ai/discovery 500/timeout
  When OrderForm selesai loading
  Then "Pesan Lagi" tetap tampil dan discovery menampilkan notice error + tombol "Coba lagi" tanpa memblokir order
```

### Story: Scoping keamanan

> As a pemilik platform, I want endpoint discovery ter-scope ketat per outlet, so that outlet tidak bisa mengintip data outlet lain.

**Rule: strict token-only scoping**
- Example A: outlet memanggil dengan `?outlet_id=<milik outlet lain>` → 403.
- Example B: admin/sales/marketplace memanggil discovery → 403.
- Example C: request tanpa auth → 401 (pola existing).

```gherkin
Scenario: outlet tidak bisa mengakses discovery outlet lain
  Given outlet A terautentikasi
  When outlet A memanggil GET /api/ai/discovery?outlet_id=<id outlet B>
  Then response 403

Scenario: non-outlet ditolak
  Given user admin/sales/marketplace terautentikasi
  When memanggil GET /api/ai/discovery
  Then response 403
```

---

## Acceptance Criteria

```
Rule: eksklusi produk yang sudah dibeli
  ✓ Given outlet dengan riwayat P1, P2 (order non-batal), When membuka /orders, Then discovery berisi P3 (tersedia) dan tidak berisi P1, P2, P4 (stok 0)
  ✓ Given produk hanya ada di order Cancelled/Rejected, When membuka /orders, Then produk boleh muncul di discovery
  ✓ Given P3 di keranjang aktif, When melihat discovery, Then P3 tidak tampil sesi itu
  ✓ Given semua kandidat tersaring, When membuka /orders, Then pesan "Belum ada rekomendasi baru saat ini" tanpa tombol tambah

Rule: fallback populer global outlet baru
  ✓ Given outlet tanpa riwayat + 8 produk tersedia, When membuka /orders, Then discovery maksimal 5 produk beralasan "Populer di platform"
  ✓ Given skor seri, When fetch berulang, Then hasil identik (tie-breaker id ASC)

Rule: tambah + validasi harga/stok
  ✓ Given P3 stok 5 harga 12.000, When klik "Tambah", Then keranjang berisi P3 qty 1 harga katalog terkini
  ✓ Given stok katalog P3 = 0, When melihat P3, Then tombol nonaktif beralasan "Stok habis"
  ✓ Given stok P3 habis antara tambah dan submit, When submit, Then 422 atomik + item error P3, order tidak dibuat

Rule: dismiss session-only
  ✓ Given P3 tampil, When dismiss, Then P3 hilang sampai reload (tanpa API/DB)

Rule: tracking funnel outlet
  ✓ Given 3 item tampil + 1 ditambah, When interaksi, Then 3 displayed + 1 clicked + 1 cart tercatat via POST /api/ai/discovery/events
  ✓ Given endpoint tracking error, When tambah + submit, Then order tetap dibuat tanpa error ke outlet

Rule: offline & fetch error
  ✓ Given offline, When membuka /orders, Then discovery disembunyikan ("tersedia saat online"), reorder cache tetap tampil
  ✓ Given kembali online, When status berubah, Then discovery reload
  ✓ Given fetch 500/timeout saat online, When loading selesai, Then reorder tetap tampil + discovery notice error + "Coba lagi"

Rule: strict token-only scoping
  ✓ Given outlet A + ?outlet_id=B, When GET /api/ai/discovery, Then 403
  ✓ Given non-outlet role, When GET /api/ai/discovery, Then 403
  ✗ Given tanpa auth, When GET /api/ai/discovery, Then 401
```

---

## Design Decision

**Chosen option:** Option A — perluasan `AIController` + `RecommendationService` (endpoint baru di grup `/api/ai/*`, method `discover()` baru, reuse `RecommendationEvent`).

**Summary:** Mengikuti pola arsitektur AI existing (dual-audience group + otorisasi di controller, guard + bounded + metadata ala `recommend()`), tanpa migrasi dan tanpa duplikasi guard.

**Rejected options:**

- Option B (controller + service terpisah `AIDiscovery*`): rejected karena menduplikasi helper guard ketersediaan (`is_active` / stok / supplier) yang sudah teruji di `RecommendationService`, dan memecah konvensi modul AI existing tanpa manfaat fungsional.

**Key tradeoffs accepted:**

- Method `discover()` menambah kompleksitas `RecommendationService`, tapi menjaga satu sumber kebenaran guard.
- Query `NOT IN`/`NOT EXISTS` order items per outlet — untuk skala outlet wajar OK; jika data sangat besar, optimasi (`WHERE NOT EXISTS`, index) tanpa mengubah kontrak.
- Dismiss session-only berarti item muncul lagi setelah reload — diterima untuk v1 agar tanpa migrasi.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Sinyal ranking v1: populer-per-kategori vs co-purchase vs campuran? | assumed: populer per kategori outlet + fallback populer global; co-purchase ditunda (butuh validasi agregat multi-produk) | Ranking kurang relevan untuk outlet dengan kategori campur; mitigasi: label jujur + ukur CTR |
| Batas 5 item cukup? | assumed: ya untuk v1 (konsisten dengan bounded pattern) | Discovery terasa sedikit; mudah diubah via limit |
| Teks alasan per sinyal final? | assumed: "Populer di kategori \<X\>" / "Populer di platform" (1 kalimat) | Copy perlu penyesuaian UX writing |
| Tracking `console.warn` cukup untuk observabilitas event hilang? | assumed: ya untuk v1 | Data hole CTR tak terdeteksi; mitigasi: metrik server-side di endpoint events |
| Double-click "Tambah" (debounce/idempotency client)? | assumed: ditangani pocket-planning sebagai detail implementasi (disable saat pending) | Qty ganda 2 vs 1 — dicover acceptance "keranjang berisi qty 1" |

---

## Implementation Notes

- `GET /api/ai/discovery`: param `limit` (default 5, max 5 untuk v1 — lebih ketat dari `recommend()` yang max 10); `outlet_id` query param DITOLAK untuk outlet (403 jika beda dari token, abaikan jika sama — ikuti `resolveOutletId()`); response envelope ikuti `recommend()`: `{recommendations, limit, fallback, method: 'discovery_popular_v1', method_version, data_sufficiency, measurement}`.
- `POST /api/ai/discovery/events`: body `{event_uuid, event_type: displayed|clicked|cart, product_id, occurred_at, metadata?}`; `outlet_id` diambil dari token (bukan body); reuse logika idempotent-replay 409 dari `MeasurementController::storeEvent` (refactor ke helper/service bersama jika memungkinkan tanpa mengubah perilaku existing).
- Web: hook `useOutletDiscovery(token, products, {cart, dismissed})` — fetch saat online + `products` tersedia; kirim `displayed` sekali per item per sesi; `clicked`+`cart` saat tambah; semua tracking dibungkus try/catch diam.
- Invalidation: setelah order sukses, refresh discovery (produk yang dibeli keluar dari kandidat).
- Guard flag: `NEXT_PUBLIC_ENABLE_DISCOVERY !== 'false'` mengikuti pola `NEXT_PUBLIC_ENABLE_REORDER`.
- Dummy store: discovery disembunyikan saat `isDummy` (ikuti `showReorderSection`).

---

## Rollback Plan

- Feature-flag: set `NEXT_PUBLIC_ENABLE_DISCOVERY=false` — section hilang tanpa deploy API.
- Jika endpoint bermasalah: hapus/ комментарии-kan 2 route baru di grup `prefix('ai')` — route AI existing (`recommendations/forecast/segmentation`) tidak tersentuh.
- Tanpa migrasi → tidak ada rollback skema. Event `RecommendationEvent` yang sudah tercatat bertipe discovery tetap valid untuk analitik (filter via `metadata.source = 'discovery_v1'`).
