# Outlet Fast Reorder from Purchase History

**Date:** 2026-10-02
**Status:** approved
**Author:** pocket-grinding session
**Spec path:** docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md

---

## Summary

Mempermudah outlet memesan ulang produk yang pernah dibeli dengan menampilkan seksi "Pesan Lagi" di halaman `/orders` yang terisi dari riwayat (favorit top-5 + item 3 order terakhir). Outlet bisa menambah per item ("+ Tambah") atau seluruh order terakhir ("↻ Ulangi Pesanan Terakhir") ke keranjang secara aditif. Guard client-side memfilter produk nonaktif/stok habis/melebihi kredit; validasi final tetap di server. Tidak ada API baru — memanfaatkan cache `outletOrdersCache`, endpoint `/products`, dan POST `/orders` yang sudah ada.

---

## Context

### Current State
- `OutletDashboard` menampilkan `favorites` (top-5) dan `summary.recent` (10 order terbaru dengan item). Tautan "Pesan lagi" hanya ke `/orders` tanpa konteks.
- `OrderForm` memuat katalog via `loadOrderFormProducts` (GET `/products`), filter nama/harga, submit via `submitOutletOrder` (POST `/orders` dengan idempotency key), mendukung offline queue (`order-queue.ts`).
- `outletOrdersCache` di `dashboard/api.ts` menyimpan hingga 1000 order (items: `product_id`, `product_name`, `quantity`, `unit_price`). `fetchOutletDashboard` membangunnya; `loadOutletCachedSections` merekomputasi favorit/shopping dari cache.
- Backend: `OrderController::store` + `OrderCreationService` (validasi stok, harga, kredit, idempotency). `ProductController` expose `/products` dengan filter `stock_health` & `is_active`. `GET /credit-limit` mengembalikan `available_credit`.

### Problem / Motivation
Outlet harus mencari & mengetik ulang produk yang sama setiap order. Riwayat sudah ada tapi tidak terhubung ke alur pemesanan. Reorder cepat akan meningkatkan efisiensi, frekuensi order, dan retensi outlet.

### Related Areas
- `apps/web/src/app/orders/page.tsx` — entry point halaman order
- `apps/web/src/components/OrderForm.tsx` — form pemesanan, keranjang, submit
- `apps/web/src/app/dashboard/api.ts` — `outletOrdersCache`, `fetchOutletDashboard`, `loadOutletCachedSections`, `invalidateOutletOrdersCache`
- `apps/web/src/app/dashboard/outlet-helpers.ts` — `topFavorites`, `periodWindow`
- `apps/api/app/Http/Controllers/OrderController.php` — `store`, `index`
- `apps/api/app/Services/OrderCreationService.php` — validasi order
- `apps/web/src/lib/offline/order-queue.ts` — offline queue, idempotency

---

## Scope

### In-Scope
- Seksi "Pesan Lagi" di `/orders` dibangun dari: favorit top-5 + item unik dari 3 order terakhir (dedup by `product_id`).
- Setiap item: nama, harga terkini (dari `/products`), indikator stok, qty terakhir dibeli, tanggal terakhir dibeli.
- CTA per item: "+ Tambah" → tambah `last_qty` ke keranjang (additif: jika sudah ada, qty dijumlahkan).
- CTA global: "↻ Ulangi Pesanan Terakhir" → prefill semua item order terakhir yang valid sekaligus (additif).
- Guard: produk nonaktif / stok 0 / melebihi `available_credit` → **tidak masuk keranjang**, ditampilkan alasan, tombol disabled. Sisanya diproses normal.
- Prefill menggunakan data `/products` terkini (harga, stok, `is_active`), bukan harga lama dari order.
- Data riwayat dari `outletOrdersCache`; jika cache kosong (langsung buka `/orders`), panggil `fetchOutletDashboard` sekali.
- Outlet baru (0 order): sembunyikan seksi reorder, tampilkan katalog + hint *"Belum ada riwayat pesanan — pilih dari katalog di bawah untuk pesanan pertama Anda."*
- Validasi berlapis: client prefilter + server `OrderCreationService` definitive.
- Submit order via existing flow (online + offline queue).

### Out-of-Scope
- Rekomendasi produk **belum pernah dibeli** (Direction B / discovery) — iterasi terpisah.
- Personalisasi ML / co-purchase cross-outlet — out of scope v1.
- Perubahan skema DB / migrasi data.
- Push notification / realtime update reorder section.
- Auto-refresh reorder section via WebSocket/SSE — refresh manual/navigasi cukup untuk v1.

---

## Architecture Constraints

- Layers this work may touch: FE (`OrderForm`, `dashboard/api.ts`, `outlet-helpers.ts`, types), BE (existing `OrderController` only).
- Layers this work must NOT touch: Auth/middleware, invoice/billing, delivery, payment, admin panel, product master.
- Patterns that must be followed:
  * Client components + fetch via `api.ts` hooks
  * Idempotency key pada POST `/orders`
  * Offline queue support via `createOrderQueue` / `createDefaultAdapter`
  * Error/loading/retry patterns existing in `OrderForm`
  * Tailwind + existing UI components (`Table`, `Card`, `Button`, `StatusBadge`, `ViewModeToggle`, `EmptyState`)
- Architecture validation result: **PASS** (quick checklist — single layer, no new deps, no contract change)

---

## Dependencies

### Existing (to leverage)
- `axios` — HTTP client (existing)
- `zustand` — global store (dummy mode, auth) — existing
- Web Crypto API (`crypto.randomUUID`, `crypto.subtle.digest`) — idempotency key generation (native)
- `createOrderQueue` / `createDefaultAdapter` — offline queue (existing `@/lib/offline/order-queue`)
- `loadOrderFormProducts`, `submitOutletOrder`, `fetchOutletDashboard`, `loadOutletCachedSections`, `invalidateOutletOrdersCache` — existing API helpers
- `topFavorites` — favorite aggregation (existing)
- UI components: `Card`, `Button`, `Table`, `StatusBadge`, `EmptyState`, `ViewModeToggle`, `Input` — existing

### New (proposed)
- **none** — hand-rolling commodity problems (idempotency) justified: using Web Crypto + existing backend idempotency column; no new library needed.

---

## Stories + Scenarios

### Story 1: Reorder dari Riwayat (Favorit + 3 Order Terakhir)
> As a **outlet yang sudah pernah memesan**, I want **melihat dan menambahkan produk dari riwayat pesanan saya ke keranjang dengan cepat**, so that **saya tidak perlu mencari dan mengetik ulang produk yang sama**.

**Rule 1: Daftar Reorder Unik dari Favorit + 3 Order Terakhir**
- Example A: Favorit [A,B,C,D,E]; 3 order terakhir items [A×2, F, G, B] → Daftar unik: A,B,C,D,E,F,G
- Example B: Order terakhir mengandung produk nonaktif H → H **tidak** muncul di daftar

```gherkin
Scenario: Outlet melihat seksi reorder dengan favorit dan item 3 order terakhir
  Given outlet telah login dan memiliki minimal 1 order sebelumnya
  And cache outletOrdersCache terisi (atau fetchOutletDashboard berhasil)
  When outlet membuka halaman /orders
  Then seksi "Pesan Lagi" terlihat di atas katalog
  And daftar produk unik dari favorit + 3 order terakhir ditampilkan
  And setiap item menampilkan nama, harga terkini, stok, qty terakhir, tanggal terakhir

Scenario: Produk nonaktif di riwayat tidak muncul di daftar reorder
  Given outlet memiliki riwayat order mengandung produk H yang kini is_active=false
  When outlet membuka /orders
  Then produk H tidak terlihat di seksi "Pesan Lagi"
```

**Rule 2: CTA Per Item "+ Tambah" (Additif ke Keranjang)**
- Example C: Keranjang sudah A×2; klik "+ Tambah" A (last_qty=3) → Keranjang A×5

```gherkin
Scenario: Outlet menambah satu produk dari reorder ke keranjang (additif)
  Given outlet membuka /orders dan seksi reorder terlihat
  And keranjang sudah berisi produk A qty 2
  When outlet menekan "+ Tambah" pada produk A (qty terakhir = 3)
  Then keranjang produk A menjadi qty 5
  And total keranjang terupdate
```

**Rule 3: CTA Global "Ulangi Pesanan Terakhir" (Additif, Skip Invalid)**
- Example D: Order terakhir: A(qty2,aktif), B(qty1,aktif), C(qty3,stok 0) → Cart tambah A×2, B×1; C dilewati + notifikasi "Produk C tidak ditambahkan: Stok habis"

```gherkin
Scenario: Outlet menekan "Ulangi Pesanan Terakhir" dengan beberapa item valid dan satu tidak valid
  Given outlet memiliki order terakhir berisi: A(qty 2, aktif), B(qty 1, aktif), C(qty 3, stok 0)
  When outlet menekan "↻ Ulangi Pesanan Terakhir"
  Then keranjang bertambah A×2 dan B×1
  And produk C tidak masuk keranjang
  And notifikasi terlihat: "Produk C tidak ditambahkan: Stok habis"
```

**Rule 4: Guard Kredit per Item**
- Example E: Sisa kredit 50.000; produk D harga 30.000, last_qty=2 (total 60.000 > sisa) → Kartu D label "Melebihi batas kredit", tombol disabled; produk lain normal

```gherkin
Scenario: Produk melebihi batas kredit ditandai dan tidak bisa ditambahkan
  Given sisa kredit outlet = Rp 50.000
  And produk D harga Rp 30.000, qty terakhir = 2 (total Rp 60.000 > sisa kredit)
  When outlet melihat seksi reorder
  Then kartu D menampilkan label "Melebihi batas kredit"
  And tombol "+ Tambah" untuk D disabled
  And produk lain tidak terpengaruh
```

**Rule 5: Harga Terkini dari `/products`, Bukan Harga Lama**
- Example F: Produk A terakhir dibeli 10.000; harga sekarang 12.000 → Kartu tampil 12.000, qty default tetap last_qty

```gherkin
Scenario: Harga produk berubah sejak terakhir dibeli ditampilkan harga terkini
  Given produk A terakhir dibeli harga Rp 10.000
  And harga terkini di /products = Rp 12.000
  When outlet melihat seksi reorder
  Then kartu A menampilkan harga Rp 12.000
  And qty default tetap qty terakhir dibeli
```

**Rule 6: Cache Kosong → Fetch Sekali**
- Example G: Outlet langsung buka `/orders` tanpa lewat dashboard → `fetchOutletDashboard` dipanggil sekali, cache terisi, reorder render normal

```gherkin
Scenario: Cache kosong saat outlet langsung buka /orders memicu fetch sekali
  Given outletOrdersCache kosong untuk token outlet ini
  When outlet membuka /orders
  Then fetchOutletDashboard dipanggil sekali
  And cache terisi
  And seksi reorder terisi seperti biasa
```

---

### Story 2: Outlet Baru Tanpa Riwayat
> As a **outlet baru**, I want **melihat katalog normal tanpa seksi reorder kosong**, so that **saya bisa mulai memesan langsung**.

**Rule 7: Sembunyikan Reorder + Empty-State Hint**
- Example H: 0 order → no reorder section, catalog shown, hint visible

```gherkin
Scenario: Outlet baru tanpa riwayat tidak melihat seksi reorder
  Given outlet baru login pertama kali (0 order history)
  When outlet membuka /orders
  Then seksi "Pesan Lagi" tidak terlihat
  And katalog produk normal ditampilkan
  And pesan "Belum ada riwayat pesanan — pilih dari katalog di bawah untuk pesanan pertama Anda." terlihat
```

---

### Story 3: Validasi Berlapis (Client + Server)
> As a **sistem**, I want **memvalidasi harga, stok, status aktif, dan batas kredit dua kali**, so that **UX responsif tanpa mengorbankan keamanan**.

**Rule 8: Client-Side Prefilter via `/products`**
- Example I: Prefill: produk stok 0 → tidak muncul (atau disabled + label "Stok habis")

**Rule 9: Server-Side Definitive Validation**
- Example J: Submit: stok habis antara prefill dan submit → backend error stok tidak cukup → FE tampilkan error tanpa crash

```gherkin
Scenario: Submit order tetap divalidasi server meskipun prefill sudah lolos client
  Given outlet men-submit order yang lolos validasi client
  When backend memproses order
  And stok salah satu produk habis tepat saat submit (race condition)
  Then backend return error stok tidak cukup
  And FE menampilkan error tersebut tanpa crash
```

---

### Additional Edge-Case Scenarios (from Edge Case Hunter)

```gherkin
Scenario: Concurrent tabs — order di Tab A, reorder section Tab B stale sampai refresh
  Given outlet membuka /orders di Tab A dan Tab B
  When outlet menempatkan order baru di Tab A (submit sukses)
  And outlet beralih ke Tab B tanpa refresh
  Then seksi "Pesan Lagi" di Tab B masih menampilkan state lama (produk baru belum muncul, kredit belum update)
  When outlet me-refresh Tab B
  Then seksi "Pesan Lagi" terupdate dengan order terbaru dan kredit terkini

Scenario: Offline "Ulangi Pesanan Terakhir" dengan mixed product states
  Given outlet offline (tidak ada koneksi)
  And cache outletOrdersCache terisi (last order: A aktif, B inactive sejak order, C stok 0, D exceed credit)
  When outlet menekan "↻ Ulangi Pesanan Terakhir"
  Then keranjang prefilled hanya dengan A (berdasarkan last-known status dari cache)
  And notifikasi terlihat untuk B, C, D dengan keterangan "berdasarkan data terakhir — validasi final saat online"
  And aksi tidak dikirim ke server sampai online

Scenario: Online — produk jadi inactive/stok habis/exceed credit antara prefill dan submit
  Given outlet online, menekan "↻ Ulangi Pesanan Terakhir", keranjang terisi item valid
  Before submit, admin menonaktifkan produk B / stok C habis / kredit outlet berkurang
  When outlet menekan "Kirim pesanan"
  Then backend reject order dengan error per-item (mis. "Produk B tidak aktif", "Stok C tidak cukup", "Melebihi batas kredit")
  And FE menampilkan error-item tersebut
  And item valid (A) tetap di keranjang, siap di-edit dan re-submit

Scenario: Accessibility — keyboard navigation untuk seksi reorder
  Given outlet menavigasi /orders hanya dengan keyboard (Tab/Shift+Tab/Enter/Space)
  When fokus masuk seksi "Pesan Lagi"
  Then setiap "+ Tambah" dan "↻ Ulangi Pesanan Terakhir" reachable via Tab
  And Enter/Space memicu aksi yang sama dengan klik
  And label ARIA/aria-label menjelaskan aksi dan status guard (mis. "Tambah Produk A, qty 3 — Stok: 50", "Melebihi batas kredit — tidak dapat ditambahkan")
```

---

## Acceptance Criteria

```
Rule: Reorder List Composition (R1)
  ✓ Given outlet dengan favorit [A,B,C,D,E] dan 3 order terakhir items [A×2, F, G, B], When buka /orders, Then daftar unik A,B,C,D,E,F,G terlihat
  ✓ Given riwayat mengandung produk nonaktif H, When buka /orders, Then H tidak terlihat di daftar
  ✗ Given riwayat kosong (0 order), When buka /orders, Then seksi reorder TIDAK terlihat (covered by Rule 7)

Rule: Additive Per-Item Add (R2)
  ✓ Given keranjang A×2, When klik "+ Tambah" A (last_qty=3), Then keranjang A×5
  ✓ Given keranjang kosong, When klik "+ Tambah" B (last_qty=1), Then keranjang B×1

Rule: Reorder Last Order Global (R3)
  ✓ Given last order A×2(aktif), B×1(aktif), C×3(stok 0), When klik "Ulangi Pesanan Terakhir", Then cart +A×2, +B×1; C skipped; notifikasi "Produk C tidak ditambahkan: Stok habis"
  ✓ Given last order semua item invalid, When klik "Ulangi Pesanan Terakhir", Then cart unchanged; notifikasi semua item skipped

Rule: Credit Guard Per Item (R4)
  ✓ Given sisa kredit 50k, produk D 30k×2=60k, When lihat reorder, Then D label "Melebihi batas kredit", tombol disabled
  ✓ Given sisa kredit cukup, When lihat reorder, Then tombol enabled

Rule: Current Price from /products (R5)
  ✓ Given last price 10k, current price 12k, When lihat reorder, Then kartu tampil 12k; qty default last_qty

Rule: Cache Miss Triggers Fetch (R6)
  ✓ Given cache kosong, When buka /orders, Then fetchOutletDashboard x1, cache populated, reorder renders

Rule: New Outlet Empty State (R7)
  ✓ Given 0 order history, When buka /orders, Then no reorder section; catalog + hint visible

Rule: Layered Validation (R8, R9)
  ✓ Given produk stok 0 di cache, When build reorder, Then client prefilter hides/disables it
  ✓ Given race condition stok habis antara prefill & submit, When submit, Then backend error stok; FE tampilkan error; cart valid items retained

Rule: Concurrent Tabs Stale Cache (Edge)
  ✓ Given order di Tab A, When Tab B tidak di-refresh, Then reorder section stale (acceptable v1 behavior)
  ✓ Given order di Tab A, When Tab B di-refresh, Then reorder section updated

Rule: Offline Reorder (Edge)
  ✓ Given offline, When klik "Ulangi Pesanan Terakhir", Then prefill from cache only; notif "berdasarkan data terakhir"; no network call; enqueue on submit
  ✗ Given offline, When klik "+ Tambah" produk yang jadi inactive, Then cached status may allow add (accepted risk — server validates on sync)

Rule: Product State Change Between Prefill & Submit (Edge)
  ✓ Given prefill valid, Before submit produk jadi inactive, When submit, Then backend item-specific error; FE show; valid items retained

Rule: Accessibility Keyboard/ARIA (Edge)
  ✓ Given keyboard-only nav, When tab through reorder, Then all buttons reachable; Enter/Space triggers; ARIA labels describe action + guard status
```

---

## Design Decision

**Chosen option:** Option A — Client-Side Reorder Engine (No New API)

**Summary:** Bangun logika reorder sepenuhnya di frontend dengan memanfaatkan `outletOrdersCache`, `fetchOutletDashboard`, `/products` untuk enrich data terkini, dan `submitOutletOrder` + `order-queue` untuk submit. Tidak menambah endpoint backend. Blocker idempotency key inconsistency (pre-existing) ditangani terpisah sebelum/bersamaan implementasi.

**Rejected options:**
- Option B (Server Helper Endpoint): rejected karena menambah kompleksitas API tanpa benefit signifikan untuk Direction A; idempotency blocker tetap terpisah; over-engineering untuk v1.

**Key tradeoffs accepted:**
- Cache reorder bisa stale setelah order baru sampai refresh/navigasi dashboard (mitigasi: panggil `invalidateOutletOrdersCache` di `submitOutletOrder` success).
- Credit check client-side = soft guard; server `OrderCreationService` remains authoritative.
- Offline reorder section disabled/cache-only; validasi final server saat sync.
- Idempotency key generation inconsistency (`OrderForm` vs `order-queue`) harus diperbaiki terpisah (hotfix) agar offline reorder aman.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Urutan daftar reorder: favorit dulu baru recent, atau sort by qty desc? | assumed: favorit dulu, lalu recent; dirapikan implementasi | Minor UX — bisa diubah tanpa breaking change |
| Apakah `invalidateOutletOrdersCache` dipanggil otomatis setelah submit order sukses? | assumed: ya, akan ditambahkan di `submitOutletOrder` success path | Jika tidak, reorder section stale sampai manual refresh |
| Bagaimana handle produk yang `product_name` di cache berbeda dari `/products` (rename)? | assumed: gunakan nama dari `/products` sebagai source of truth; cache name hanya fallback | Nama produk di reorder bisa beda dari histori — acceptable |
| Apakah offline reorder sepenuhnya di-disable atau cache-only dengan notif? | assumed: cache-only, notif "validasi final saat online", aksi masuk queue | Jika di-disable total, outlet offline kehilangan kemudahan reorder |
| Idempotency fix scope: di `order-queue` saja, atau `OrderForm` juga? | assumed: unify di shared util `generateIdempotencyKey(items)` dipakai keduanya | Jika tidak diunify, duplicate order tetap mungkin |

---

## Implementation Notes

- **File utama ubah:** `apps/web/src/components/OrderForm.tsx` (tambah state reorder items, render section, prefill logic), `apps/web/src/app/dashboard/api.ts` (tambah `invalidateOutletOrdersCache` call di `submitOutletOrder` wrapper, atau expose helper untuk dipanggil dari `OrderForm`).
- **Baru:** hook `useOutletReorder(token)` di `apps/web/src/app/orders/` atau `hooks/` yang encapsulate: load cache → enrich via `/products` → compute reorder list → actions `addToCart(product_id, last_qty)`, `reorderLastOrder()`.
- **UI:** Sisipkan seksi "Pesan Lagi" di `OrderForm.tsx` di atas katalog (sebelum filter/search), gunakan `Card` + `Table`/`grid` view mode konsisten.
- **Idempotency fix (prerequisite):** Buat util `generateIdempotencyKey(items: OrderItem[])` di `@/lib/offline/idempotency.ts` yang dipakai `OrderForm` (online) dan `order-queue` (offline flush). Key = SHA-256(JSON.stringify(sorted items)) atau format deterministik yang sama.
- **Testing:** Tambah test di `OrderForm.test.tsx` untuk: render reorder section, additive add, reorder last order skip invalid, credit guard, empty state, offline mode, keyboard nav.

---

## Rollback Plan

1. **Feature flag via env/dummy mode:** Set `NEXT_PUBLIC_ENABLE_REORDER=false` → `OrderForm` skip render reorder section (no deploy needed jika flag runtime).
2. **Jika sudah deploy tanpa flag:** Revert commit yang menambah reorder section di `OrderForm.tsx` dan hook baru — single PR revert.
3. **Cache invalidation side-effect:** Jika `invalidateOutletOrdersCache` menyebabkan bug dashboard, revert panggilan tersebut; outlet tetap bisa refresh manual.

---

## GitHub Issue (Pocket Enterprise)

> Will be created on handoff to pocket-planning if `mode --contract 2` returns `enterprise=true`.