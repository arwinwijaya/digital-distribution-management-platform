# Outlet Dashboard & Insight

**Date:** 2026-10-01
**Status:** approved
**Author:** brainstorm session (pocket-pitching → pocket-grinding)
**Spec path:** docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
**Pitch source:** docs/pocket/spec/2026-10-01-outlet-dashboard-insight/pitch-exploration.md

---

## Summary

Outlet yang login saat ini dialihkan dari `/dashboard` ke `/orders` dan tidak memiliki halaman utama untuk memantau proses pemesanan maupun melihat insight atas aktivitas belanjanya sendiri. Fitur ini menjadikan `/dashboard` sebagai halaman utama outlet: ringkasan + daftar pesanan terbaru, ringkasan belanja per periode, status kredit, dan produk favorit — semuanya ter-scope ke outlet yang login dan dikomposisi dari endpoint yang sudah ada tanpa endpoint backend baru di v1.

---

## Context

### Current State
- `apps/web/src/app/dashboard/page.tsx` (`'use client'`): `useDashboardSession` melewati pemuatan untuk `role === 'outlet'`, lalu `useEffect` melakukan `router.replace('/orders')`.
- `apps/web/src/app/dashboard/api.ts`: `loadDashboard()` hanya bercabang `finance` (`GET /finance/metrics`) vs `admin` (`GET /analytics/dashboard`) — tidak ada cabang outlet.
- `apps/web/src/app/login/page.tsx`: `roleDestination()` mengarahkan `outlet → '/orders'`.
- `apps/api/app/Http/Controllers/OrderController.php`: `index()` **admin-only** (`indexGuard` menolak non-admin); `show()` mengisolasi `outlet_id` untuk non-admin.
- `apps/api/app/Support/OrderListFilters.php`: allowlist status `New, Confirmed, Delivered, Partially Paid`; mendukung `outlet_id`, `status`, `start`, `end`; `MAX_FILTER_RANGE_DAYS = 90`.
- `apps/api/app/Support/OrderFormatter.php`: item berisi `product_id, product_name, quantity, unit_price, subtotal`.
- `InvoiceController::index` dan `PaymentController::index` sudah mengisolasi `outlet_id`.
- `CreditLimitService::summary()` → `{credit_limit (null bila belum diset), outstanding_balance, available_credit (null bila tanpa limit)}`; outstanding dari status `New, Confirmed, Delivered, Partially Paid`.
- RBAC (`RbacMatrixSeeder.php` ↔ `apps/web/src/dummy/rbac.ts`): outlet punya `dashboard:edit`, `orders:edit`, `payments:edit`, `invoices:read`, `delivery:read`, `products:read`, `outlets:read`; `analytics/data_intelligence/admin_* = none`.
- Dummy mode: `withDummyRead` + `useDummyStore`; fixture ada `dashboardAdmin`, `dashboardFinance`; belum ada `dashboardOutlet`.

### Problem / Motivation
Outlet tidak punya tempat untuk menjawab "pesanan saya di tahap apa?", "apa yang perlu saya tindaklanjuti?", dan "bagaimana pola belanja saya?" tanpa bertanya ke admin/sales. Menu `Dasbor` sudah terlihat untuk outlet (RBAC `dashboard:edit`), tetapi halaman yang dituju me-redirect mereka — jadi hambatannya adalah perilaku halaman, bukan izin.

### Related Areas
`apps/web/src/app/dashboard/*`, `apps/web/src/app/login/page.tsx`, `apps/web/src/dummy/*`, `apps/web/src/components/{Sidebar,Topbar,Charts,ui}.tsx`, `apps/web/src/app/payments/*` (pola credit-limit + partial error), `apps/api/app/Http/Controllers/{Order,Invoice,Payment,CreditLimit}Controller.php`, `apps/api/app/Support/{OrderListFilters,OrderFormatter}.php`, `apps/api/app/Services/CreditLimitService.php`, `apps/api/routes/api.php`.

---

## Scope

### In-Scope
- Halaman utama outlet di `/dashboard` (cabang client-side; admin/finance tetap seperti sekarang).
- Ringkasan jumlah pesanan per status + daftar 10 pesanan terbaru dengan tanggal, label status ramah outlet, total, dan tautan detail.
- Ringkasan belanja (total + jumlah transaksi) dengan selector periode 7/30/90 hari (default 30).
- Kartu status kredit (batas kredit, piutang berjalan, kredit tersedia) — disembunyikan bila `credit_limit` null.
- Produk favorit top-5 (agregasi klien) + pintasan "Pesan lagi" ke `/orders`.
- Pembedaan kondisi kosong vs error, partial render, dan tombol "Coba lagi" per-seksi.
- Pemetaan status internal → label outlet + hint langkah berikutnya + CTA.
- Login outlet diarahkan ke `/dashboard`.
- Semua data ter-scope server-side ke outlet yang login.

### Out-of-Scope
- Analitik/benchmarking lintas-outlet — di luar kebutuhan outlet dan berisiko privasi.
- Rekomendasi otomatis / estimasi pengiriman (ETA) — butuh mesin/data baru; belum divalidasi.
- Perubahan alur pemesanan (cart → checkout) — dashboard hanya menampilkan pesanan yang ada.
- Perubahan dashboard admin/finance — cabang outlet tidak boleh mengubah metrik admin.
- Endpoint backend baru di v1 — v1 mengomposisi endpoint existing; eskalasi hanya bila cap terbukti berat.

---

## Architecture Constraints

- Layers this work may touch: `apps/web/src/app/dashboard/*`, `apps/web/src/app/login/page.tsx`, `apps/web/src/dummy/*`, komponen UI; `apps/api/app/Http/Controllers/OrderController.php` (perluasan guard index), `apps/api/app/Support/OrderListFilters.php` (perluasan allowlist).
- Layers this work must NOT touch: `AnalyticsController`/`AnalyticsService` (analitik admin), alur pembuatan pesanan (`OrderCreationService`), `FinanceMetricsController`, RBAC seeder (tidak ada kunci menu baru).
- Patterns that must be followed: envelope `{status, data, meta}`; `withDummyRead` + fixture parity; `StatCard/Card/PageHeader` dari `components/ui`; guard `outlet_id` server-side; pola partial-error di `app/payments/page.tsx`.
- Architecture validation result: **PASS** (lihat Phase 6 checklist di bawah).

---

## Dependencies

### Existing (to leverage)
- `next@^16` / `react@^18` — App Router client component branch.
- `zustand@^4.5` — `useDummyStore`, `useRbacStore` (sudah ada).
- `fetch` + `lib/api` (`apiUrl`, `authHeaders`, `getStoredToken`) — sudah dipakai dashboard.
- `leaflet`/`react-leaflet` — tidak dipakai fitur ini (peta tetap di halaman lain).
- `jest` + `@testing-library/react` + `@playwright/test` — unit + e2e.

### New (proposed)
none — semua kebutuhan tercakup dependensi terpasang. Tidak ada masalah komoditas yang di-hand-roll (tanggal memakai `toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' })` yang sudah ada di `dashboard/page.tsx`).

---

## Stories + Scenarios

### Story 1: Ringkasan & daftar pesanan outlet
> As an outlet user, I want melihat ringkasan status + pesanan terbaru, so that saya tahu kondisi pesanan tanpa bertanya ke admin/sales.

**Rule 1: Ringkasan per status untuk outlet login**
- Example A: 3 `New` + 2 `Delivered` → ringkasan `New=3, Delivered=2`.
- Example B: 1.500 order → ringkasan dari 1.000 terbaru + catatan batas.

**Rule 2: Daftar 10 terbaru dengan tautan detail**
- Example C: 12 order → daftar 10 terbaru urut `created_at DESC`.

**Rule 3: Scoping server-side**
- Example D: token outlet A + `outlet_id=B` → hanya order A.

**Rule 4: Ringkasan & daftar boleh tidak konsisten sesaat**
- Example E: order berpindah `New→Confirmed` antar-request → tidak ada auto-refetch loop.

```gherkin
Scenario: Ringkasan + daftar sesuai data outlet
  Given outlet login memiliki 3 order "New" dan 2 order "Delivered"
  When  membuka /dashboard
  Then  ringkasan menampilkan New=3, Delivered=2
  And   daftar menampilkan 10 pesanan terbaru (urut created_at DESC) dengan tautan detail

Scenario: Outlet tanpa pesanan
  Given outlet login belum pernah membuat pesanan
  When  membuka /dashboard
  Then  menampilkan "Belum ada pesanan" (bukan angka nol seolah error)

Scenario: Daftar pesanan gagal dimuat
  Given endpoint GET /orders mengembalikan 5xx
  When  membuka /dashboard
  Then  seksi pesanan menampilkan pesan error + tombol "Coba lagi"
  And   seksi kredit/belanja/favorit tetap dirender (partial render)

Scenario: Cap 1.000 pesanan terbaru
  Given outlet memiliki 1.500 pesanan
  When  ringkasan dimuat
  Then  ringkasan mencerminkan 1.000 pesanan terbaru
  And   catatan kecil "Menampilkan 1.000 pesanan terbaru dari {meta.total} pesanan" tampil di atas daftar
  And   kontrol paginasi disembunyikan dengan baris "Tidak ada data lebih baru (batas 1.000 pesanan terbaru)"

Scenario: IDOR outlet_id diabaikan
  Given token outlet A dikirim dengan query outlet_id=B
  When  GET /orders diproses
  Then  hanya order milik A yang dikembalikan (tanpa 403 leak)
```

### Story 2: Pemetaan status ramah outlet
> As an outlet user, I want status yang saya mengerti + langkah berikutnya, so that saya tahu harus apa.

**Rule 1: Label kanonik**
- Example A: `New` → "Menunggu konfirmasi admin" + hint "Tidak ada aksi".
- Example B: `Delivered` → "Terkirim, periksa invoice" + CTA "Lihat tagihan".

**Rule 2: Fallback status tak dikenal**
- Example C: `Rejected` → teks mentah (di-escape, dipotong 20 char + `…`), tooltip "Lainnya".

```gherkin
Scenario: Status New ditampilkan ramah outlet
  Given order berstatus "New"
  When  ditampilkan di dashboard
  Then  label "Menunggu konfirmasi admin" dan hint "Tidak ada aksi"

Scenario: Status Delivered punya CTA tagihan
  Given order berstatus "Delivered"
  When  ditampilkan
  Then  label "Terkirim, periksa invoice" dan CTA "Lihat tagihan"

Scenario: Status tak dikenal
  Given order berstatus "Super-Pending-Review!!!"
  When  ditampilkan
  Then  label menampilkan teks yang di-escape & dipotong (tidak blank/crash)
  And   tooltip menampilkan "Lainnya"

Scenario: Varian Canceled dinormalisasi
  Given order berstatus "Canceled"
  When  ditampilkan
  Then  label "Dibatalkan" (sama seperti "Cancelled")
```

### Story 3: Ringkasan belanja dengan periode
> As an outlet user, I want tahu total belanja saya per periode, so that saya memahami pengeluaran.

**Rule 1: Window periode**
- Example A: default 30 hari → label "30 hari terakhir".
- Example B: window = N tanggal kalender inklusif: `start = (hari ini − (N−1) hari) @00:00 Asia/Jakarta`, `end = sekarang`, `created_at` inklusif, dikonversi ke UTC. Karena N tanggal inklusif, selector 90 hari tetap ≤ batas API 90 tanggal (tidak 422).

**Rule 2: Definisi angka**
- Example C: total = Σ `total_amount` order kecuali `Cancelled`; transaksi = jumlah order unik.

**Rule 3: Window kosong ≠ belum pernah memesan**
- Example D: order 60 hari lalu, periode 7 hari → "Tidak ada transaksi pada periode ini".

```gherkin
Scenario: Default 30 hari
  Given outlet memiliki 4 order dalam 30 hari terakhir
  When  membuka /dashboard
  Then  total belanja = Σ total_amount 4 order, jumlah transaksi = 4
  And   label "30 hari terakhir"

Scenario: Ganti periode ke 7 hari
  Given selector periode diubah ke 7 hari
  When  perubahan diterapkan
  Then  total & jumlah dihitung ulang untuk window 7 hari

Scenario: Ada riwayat tapi window kosong
  Given outlet punya order 60 hari lalu, tidak ada dalam 7 hari terakhir
  When  memilih periode 7 hari
  Then  menampilkan "Tidak ada transaksi pada periode ini" (bukan "Belum ada pesanan")

Scenario: Batas timezone (hari ke-8 di-exclude)
  Given order created_at = 2026-05-06T16:59 UTC (23:59 Asia/Jakarta pada 6 Mei)
  When  window 7 hari dihitung pada 2026-05-13T23:55 Asia/Jakarta (start 7 Mei 00:00)
  Then  order tersebut TIDAK masuk window (tepat hari ke-8, tidak off-by-tz)

Scenario: Batas timezone (hari pertama di-include)
  Given order created_at = 2026-05-06T17:00 UTC (00:00 Asia/Jakarta pada 7 Mei)
  When  window 7 hari dihitung pada 2026-05-13T23:55 Asia/Jakarta (start 7 Mei 00:00)
  Then  order tersebut masuk window

Scenario: Selector 90 hari tidak menolak
  Given user memilih periode 90 hari
  When  query dibangun (start = hari ini − 89 hari)
  Then  request berhasil (90 tanggal inklusif = max range, tidak 422)
```

### Story 4: Status kredit
> As an outlet user, I want lihat batas kredit & piutang saya, so that saya tahu sisa kemampuan belanja.

**Rule 1: Sumber & format**
- Example A: `{credit_limit, outstanding_balance, available_credit}` → tiga nilai terformat Rp id-ID.

**Rule 2: Tanpa limit → sembunyikan kartu**
- Example B: `credit_limit = null` (HTTP 200) → kartu kredit tidak tampil, tanpa error.

**Rule 3: Gagal → error seksi**
- Example C: 5xx → error + "Coba lagi" di seksi kredit saja.

```gherkin
Scenario: Kredit terkonfigurasi
  Given GET /credit-limit mengembalikan credit_limit, outstanding_balance, available_credit
  When  membuka /dashboard
  Then  tiga nilai ditampilkan terformat Rp (id-ID, tanpa desimal)

Scenario: Tanpa limit kredit
  Given GET /credit-limit mengembalikan credit_limit = null dengan HTTP 200
  When  membuka /dashboard
  Then  kartu kredit tidak ditampilkan dan tidak ada error

Scenario: Kredit gagal dimuat
  Given GET /credit-limit gagal (5xx/timeout)
  When  membuka /dashboard
  Then  seksi kredit menampilkan error + "Coba lagi"
  And   seksi pesanan tetap dirender

Scenario: Retry hanya seksi kredit
  Given seksi kredit dalam status error
  When  user menekan "Coba lagi" pada seksi kredit
  Then  hanya seksi kredit yang di-fetch ulang
  And   data seksi pesanan/belanja/favorit tidak berkedip atau hilang
```

### Story 5: Produk favorit / pesan lagi
> As an outlet user, I want tahu produk yang sering saya pesan, so that saya bisa pesan ulang dengan cepat.

**Rule 1: Agregasi klien**
- Example A: produk A(qty 10), B(qty 6) → top-5 menampilkan A lalu B.
- Example B: kunci = `product_id` (skip null), nama = `product_name` terbaru, jumlah = Σ `quantity` (integer).

**Rule 2: Fallback & empty**
- Example C: `product_name` null → label `Produk #{product_id}`.
- Example D: tanpa riwayat → pesan kosong.

```gherkin
Scenario: Produk favorit dari riwayat
  Given outlet memiliki riwayat dengan produk A (qty 10) dan B (qty 6)
  When  membuka /dashboard
  Then  top-5 menampilkan A lalu B dengan jumlah dan tautan "Pesan lagi" ke /orders

Scenario: Tanpa riwayat pesanan
  Given outlet belum punya pesanan
  When  membuka /dashboard
  Then  seksi favorit menampilkan pesan kosong

Scenario: Produk terhapus / nama hilang
  Given item order dengan product_id null, dan item dengan product_id=5 & product_name null
  When  agregasi favorit dijalankan
  Then  item invalid di-skip tanpa error
  And   item product_id=5 memakai fallback "Produk #5"
  And   dashboard tetap render

Scenario: Tie-break jumlah sama
  Given dua produk memiliki total quantity sama
  When  top-5 dihitung
  Then  urutan tie-break berdasarkan nama produk asc
```

### Story 6: Isolasi data & autentikasi
> As a system, I want data outlet terisolasi dan akses terautentikasi, so that tidak ada kebocoran antar-outlet.

**Rule 1: Scoping server-side**
- Example A: outlet A tidak melihat data outlet B.

**Rule 2: Perilaku auth**
- Example B: tanpa token → `/login`.
- Example C: token tanpa relasi outlet → pesan inline "Akun tidak terhubung ke outlet" (tanpa retry).
- Example D: login outlet → `/dashboard`.

```gherkin
Scenario: Isolasi antar-outlet
  Given outlet A login
  When  membuka /dashboard
  Then  tidak ada order/kredit outlet B yang muncul

Scenario: Belum login
  Given tidak ada token
  When  membuka /dashboard
  Then  redirect ke /login

Scenario: Akun tanpa relasi outlet
  Given token valid untuk user tanpa outlet (403 dari API)
  When  memuat dashboard
  Then  menampilkan pesan inline "Akun ini tidak terhubung ke outlet. Hubungi admin." (tanpa tombol retry)

Scenario: Login outlet diarahkan ke dashboard
  Given login berhasil sebagai outlet
  When  redirect selesai
  Then  diarahkan ke /dashboard

Scenario: Sesi outlet lama tetap bekerja
  Given outlet sudah login dan membuka /orders langsung
  When  halaman dimuat
  Then  /orders tetap berfungsi untuk membuat pesanan (tidak dipaksa pindah)
```

---

## Acceptance Criteria

```
Rule: Ringkasan + daftar pesanan (Story 1)
  ✓ Given outlet dengan 3 New + 2 Delivered, When buka /dashboard, Then ringkasan New=3 Delivered=2 dan daftar 10 terbaru ber-tautan detail
  ✓ Given outlet tanpa pesanan, When buka /dashboard, Then tampil "Belum ada pesanan"
  ✗ Given GET /orders 5xx, When buka /dashboard, Then seksi pesanan error + "Coba lagi" dan seksi lain tetap render
  ✓ Given outlet 1.500 order, When ringkasan dimuat, Then mencerminkan 1.000 terbaru + catatan batas
  ✗ Given token outlet A + outlet_id=B, When GET /orders, Then hanya order A (tanpa 403 leak)

Rule: Pemetaan status (Story 2)
  ✓ Given status New, When ditampilkan, Then label "Menunggu konfirmasi admin" + hint "Tidak ada aksi"
  ✓ Given status Delivered, When ditampilkan, Then label + CTA "Lihat tagihan"
  ✓ Given status "Canceled", When ditampilkan, Then label "Dibatalkan"
  ✗ Given status tak dikenal, When ditampilkan, Then teks mentah di-escape & dipotong (tidak blank/crash)

Rule: Ringkasan belanja (Story 3)
  ✓ Given 4 order dalam 30 hari, When buka /dashboard, Then total = Σ total_amount & transaksi = 4, label "30 hari terakhir"
  ✓ Given selector diubah ke 7 hari, When diterapkan, Then total & jumlah dihitung ulang
  ✓ Given order hanya 60 hari lalu + periode 7 hari, When dipilih, Then "Tidak ada transaksi pada periode ini"
  ✓ Given order created_at 23:59 Jakarta tepat hari ke-8, When window 7 hari dihitung, Then order di-exclude (tanpa off-by-tz)
  ✓ Given order created_at 00:00 Jakarta hari pertama window, When window 7 hari dihitung, Then order di-include
  ✗ Given selector 90 hari (start = hari ini − 89 hari), When query dibangun, Then berhasil (tidak 422)

Rule: Status kredit (Story 4)
  ✓ Given credit_limit terisi, When buka /dashboard, Then tiga nilai terformat Rp
  ✓ Given credit_limit null (200), When buka /dashboard, Then kartu kredit disembunyikan tanpa error
  ✗ Given GET /credit-limit 5xx, When buka /dashboard, Then seksi kredit error + "Coba lagi", seksi pesanan tetap render
  ✓ Given seksi kredit error, When "Coba lagi" ditekan, Then hanya seksi kredit yang re-fetch (seksi lain tidak berkedip)

Rule: Produk favorit (Story 5)
  ✓ Given riwayat A(qty10) B(qty6), When buka /dashboard, Then top-5 A lalu B dengan tautan "Pesan lagi" ke /orders
  ✓ Given tanpa riwayat, When buka /dashboard, Then seksi favorit kosong
  ✓ Given product_name null, When agregasi, Then label fallback "Produk #{id}"
  ✗ Given item invalid (product_id null), When agregasi, Then di-skip tanpa crash
  ✓ Given quantity sama, When top-5 dihitung, Then tie-break nama asc

Rule: Isolasi & auth (Story 6)
  ✓ Given outlet A login, When buka /dashboard, Then tidak ada data outlet B
  ✓ Given tanpa token, When buka /dashboard, Then redirect /login
  ✗ Given token tanpa relasi outlet, When memuat dashboard, Then pesan inline "Akun ini tidak terhubung ke outlet. Hubungi admin." (tanpa retry)
  ✓ Given login outlet, When redirect selesai, Then ke /dashboard
  ✓ Given outlet membuka /orders langsung, When dimuat, Then tetap berfungsi (tidak dipaksa pindah)

Rule: Observability (partial failure)
  ✓ Given seksi gagal render, When partial render terjadi, Then console.warn per-seksi (tanpa PII) dicatat
```

---

## Design Decision

**Chosen option:** Option A — Same-route outlet branch (`/dashboard`)

**Summary:** Ganti `router.replace('/orders')` di `dashboard/page.tsx` dengan cabang render `<OutletDashboard/>` untuk `role === 'outlet'`, dan tambahkan cabang `role === 'outlet'` pada `dashboard/api.ts` yang mengomposisi endpoint existing (`GET /orders` yang diperluas untuk outlet, `GET /credit-limit`). Admin/finance tidak berubah.

**Rejected options:**
- Option B (Enrich `/orders`): mencampur fungsi transaksional & analitis di satu halaman; tidak memberi ruang insight yang layak; tetap butuh agregasi yang sama.
- Option C (New outlet overview module + summary endpoint): kode baru terbanyak (endpoint + RBAC + menu + dummy + guard); overkill karena komposisi klien dari endpoint existing memadai untuk v1.

**Key tradeoffs accepted:**
- Satu halaman menampung 3 peran (admin/finance/outlet) → disiplin komponen (`OutletDashboard` terpisah).
- Agregasi klien dibatasi 1.000 order terbaru → risiko undercount untuk outlet sangat aktif, dimitigasi dengan catatan batas eksplisit.
- Ringkasan & daftar boleh tidak konsisten sesaat (request terpisah) → tidak ada snapshot transaksional.

**Perluasan backend minimal (bagian dari Option A):**
- `OrderController::index`: izinkan outlet, auto-scope `outlet_id` dari token (abaikan/override `outlet_id` klien).
- `OrderListFilters`: perluas allowlist status ke `New, Confirmed, Delivered, Partially Paid, Paid, Cancelled, Canceled`; normalisasi `Canceled`→`Cancelled`.

---

## Phase 6 — Architecture Validation

```
[x] Respects layer boundaries defined in Phase 2?  (web dashboard + perluasan guard index; tidak menyentuh AnalyticsService/OrderCreationService)
[x] Follows existing patterns from Phase 1?  (envelope {status,data,meta}, withDummyRead, StatCard/Card/PageHeader, pola partial-error payments)
[x] No new dependencies violating constraints?  (none)
[x] Build-vs-buy considered?  (tidak ada masalah komoditas baru; tanggal/format memakai util existing)
[x] Rollback / undo strategy defined?  (lihat Rollback Plan)
[x] No silent data migrations or breaking changes to contracts?  (tidak ada migrasi; /orders aditif — outlet hanya di-scope, admin tetap)
[x] Performance characteristics acceptable?  (fetch terpaginasi cap 1.000; komposisi klien)
[x] No security regressions?  (server override outlet_id; RBAC tidak berubah; tidak ada kunci menu baru)
```

**Result: PASS**

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Tie-break favorit bila quantity sama | assumed: nama produk asc | Urutan tidak deterministik antar-render |
| Zona waktu window periode | resolved: N tanggal kalender inklusif; `start = (hari ini − (N−1) hari) @00:00 Asia/Jakarta` → UTC (pakai `jakartaDateString`), `end = sekarang` | Order di batas hari salah masuk/keluar window |
| Truncation `{total}` | resolved: pakai `meta.total` dari `GET /orders` (1500 order → catatan "... dari 1500 pesanan") | Angka total bisa membingungkan bila tidak konsisten dengan cap |
| Batas fetch agregasi favorit | assumed: cap 1.000 order terbaru + catatan batas | Undercount untuk outlet >1.000 order dalam 90d |
| Tombol "Coba lagi" | resolved: per-seksi (D11) | — |
| Cache shared fetch | resolved: key `(outletId, limit=1000, sort=created_at DESC, cursor=0)`; retry credit tidak invalidasi cache order | Flicker seksi lain bila cache salah |
| Truncation note | resolved: di atas daftar, teks kecil abu-abu (G1) | — |
| Pagination UI di cap | resolved: kontrol disembunyikan + baris batas (G2) | — |
| Inline 403 style | resolved: `Card` warn + ikon ⚠️ + `my-3` (G3) | — |
| Unknown status sanitization | resolved: escape + truncate 20 char + tooltip "Lainnya" (G4) | XSS/layout break bila tidak di-escape |
| Layout shift kredit hidden | resolved: collapse instan, grid `auto-rows` (G7) | Layout jump mobile |
| Retry accessibility | resolved: `aria-live="polite"`, fokus kembali ke tombol, keyboard Enter/Space (G8) | Non-WCAG-AA |
| Observability partial failure | assumed: `console.warn` per-seksi tanpa PII | Debugging lebih sulit |

---

## Implementation Notes

- `dashboard/page.tsx` sudah `'use client'`; branch role cukup di komponen ini. Pertahankan perilaku admin/finance byte-for-byte.
- `dashboard/api.ts`: tambahkan `{ kind: 'outlet'; data: OutletDashboardData }` pada union `DashboardLoadResult`; pastikan `loadForRole` tidak dipanggil untuk outlet sebelum role diketahui.
- Tambah fixture `dashboardOutlet` di `apps/web/src/dummy/aggregates.ts` + tipe di `dummy/index.ts`, dengan paritas untuk story 1–5 (counts, kredit, aktivitas, favorit).
- Perluasan `OrderController::index` harus menjaga perilaku admin (query admin tetap boleh memakai filter `outlet_id`); hanya outlet yang di-override.
- Perluasan `OrderListFilters` allowlist harus disinkronkan dengan test filter yang ada (jangan melonggarkan validasi lain).
- `roleDestination` di `apps/web/src/app/login/page.tsx`: `outlet → '/dashboard'`. Pastikan test login/RBAC yang ada di-update.
- `data-testid` untuk seksi baru (mengikuti kontrak e2e `siklus-pemesanan-e2e`): `outlet-dashboard`, `outlet-orders-summary`, `outlet-orders-recent`, `outlet-shopping-summary`, `outlet-credit-card`, `outlet-favorites`, `outlet-section-error`, `outlet-retry`.
- Tidak ada migrasi DB dan tidak ada endpoint baru.

---

## Rollback Plan

- Revert branch outlet di `dashboard/page.tsx` + `dashboard/api.ts` → outlet kembali me-redirect ke `/orders` (perilaku lama).
- Revert `roleDestination` outlet → `/orders`.
- Revert perluasan `OrderController::index` + `OrderListFilters` allowlist → admin-only seperti semula.
- Tidak ada migrasi/flag; rollback = git revert commit terkait (tanpa langkah data).

---

## Handoff Context (for pocket-planning)
- Spec path: `docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md`
- Pitch source: `docs/pocket/spec/2026-10-01-outlet-dashboard-insight/pitch-exploration.md`
- Chosen design: Option A — Same-route outlet branch (`/dashboard`)
- Scope: outlet-only landing dashboard; komposisi endpoint existing; perluasan minimal `OrderController::index` + `OrderListFilters` allowlist.
- Out-of-scope reminder: cross-outlet analytics, auto recommendations/ETA, ordering flow changes, admin/finance dashboard changes, new backend endpoint v1.
