# Analitik Deeper Insight — Owner Strategic Analytics

**Date:** 2026-09-18
**Status:** draft
**Author:** brainstorm session (pitch → grinding)
**Spec path:** docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md

---

## Summary

Menu "Analitik AI" saat ini hanya menampilkan tiga kartu tipis dari endpoint `/ai/*`
(rekomendasi produk, badge segmentasi, prakiraan 4 minggu) tanpa konteks perbandingan
apa pun, sehingga owner/admin tidak bisa membaca gambaran strategis. Spec ini mengubah
Analitik menjadi **rumah analitik strategis untuk owner**: strip metrik dengan delta
periode-ke-periode, grafik tren harian, peringkat outlet (top-10 + jumlah total), strip
deterministik "Needs attention", lalu seksi AI yang dipertahankan dengan label
kepercayaan inline. Seluruh insight diturunkan dari data yang sudah ada — tanpa
pengumpulan data baru, tanpa ML/LLM produksi.

---

## Context

### Current State
- Halaman: `apps/web/src/app/analytics/page.tsx` (JSX monolitik satu baris) +
  `apps/web/src/app/analytics/api.ts` (loader `loadAnalytics` dengan guard
  `withDummyRead`, 3-way `Promise.all` ke `/ai/recommendations`,
  `/ai/forecast?period=weekly&horizon=4`, `/ai/segmentation`).
- Halaman menampilkan 3 kartu: daftar rekomendasi, badge/daftar segmentasi, grid
  prakiraan 4 minggu. Tidak ada baseline, tidak ada implikasi, tidak ada aksi.
- `/api/analytics/dashboard` sudah mengembalikan `metrics` + `sales_trends` +
  `outlet_performance` (bounded `MAX_DATE_RANGE_DAYS=366`, `OUTLET_PERFORMANCE_LIMIT=10`,
  tie-break deterministik nama lalu id) tetapi hanya dikonsumsi halaman Dashboard.
- Endpoint `/admin/analytics/*` (geographic, suppliers, stock-planning, measurement)
  dan `FinanceMetricsController` memuat material lain — di luar cakupan spec ini.
- `AIController::segmentation` sudah memiliki pola role-scoping (admin/outlet, outlet
  hanya boleh sinyal sendiri).
- `PilotMetricsService` sudah menghitung `deltaPercent` di sisi server (baris 66–75) —
  preseden untuk derivasi delta server-side.

### Problem / Motivation
- Analitik berada di **lapisan arsitektur yang salah**: terhubung ke `/ai/*` (lapisan AI)
  padahal mental model owner adalah "dashboard analitik". Ini kecelakaan penamaan, bukan
  keputusan desain.
- **Elemen yang hilang adalah dimensi perbandingan (baseline), bukan kuantitas metrik.**
  Total tanpa pembanding adalah *data*, bukan *insight*. Insight = observasi +
  perbandingan + implikasi.
- Semua data mentah sudah ada (orders/payments/outlets/products); celahnya murni
  derivasi + komposisi + presentasi → biaya rendah, dampak tinggi.
- Trust scaffolding (`data_sufficiency`, `measurement`, `method`) sudah dikembalikan API
  tetapi tidak pernah ditampilkan → mengangkatnya mengubah "heuristik" dari kelemahan
  menjadi fitur.

### Related Areas
- `apps/api/app/Services/AnalyticsService.php` — sumber agregat (dipakai ulang).
- `apps/api/app/Http/Controllers/AnalyticsController.php` — gate admin (akan diperluas).
- `apps/api/app/Services/FinanceAuthorizationService.php::isAdminOrOwner()` — helper otorisasi.
- `apps/api/app/Http/Controllers/AIController.php` + `RecommendationService` /
  `ForecastService` / `SegmentationService` — seksi AI (dipakai apa adanya).
- `apps/web/src/components/Charts.tsx` — `SalesTrendChart`, `OutletPerformanceChart`.
- `apps/web/src/components/ui/StatCard.tsx` — sudah mendukung `change` + `changeType`.
- `apps/web/src/components/Sidebar.tsx` — NAV_ITEMS + filter `adminOnly`.
- `apps/web/src/dummy/*` — `aggregates.ts`, `dates.ts`, `index.ts`, `guards.ts`, `store.ts`.

---

## Scope

### In-Scope
- Analitik menjadi **rumah analitik strategis owner** dengan urutan seksi:
  (1) strip metrik + delta chips, (2) grafik tren harian, (3) peringkat outlet top-10
  (+ "dan N outlet lain"), (4) strip "Needs attention" (maks 5), (5) seksi AI.
- Endpoint baru `GET /api/analytics/insight` yang memakai ulang helper agregat
  `AnalyticsService` (sumber kebenaran tunggal = kode bersama, bukan payload bersama).
- Deepen kartu AI: label kepercayaan inline (`data_sufficiency`, `measurement`, `method`).
- Akses `admin` OR `platform_owner` via `FinanceAuthorizationService::isAdminOrOwner()`.
- Nav item `/analytics` ditandai `adminOnly`, dan `platform_owner` diperlakukan
  setara admin sehingga item `adminOnly` tetap terlihat oleh owner.
- Parity dummy mode untuk semua field baru (`dummy.analyticsInsight`).
- Tes: feature test API untuk kontrak baru; tes web untuk halaman/loader.

### Out-of-Scope
- ML/LLM produksi — heuristik tetap deterministik (out-of-scope Phase 3 closeout).
- BI suite / report builder penuh — `/data-intelligence` + `/admin/analytics/*` sudah
  melayani power user.
- Date-range picker — jendela **tetap** 30 hari; `/analytics/insight` tidak menerima
  `start_date`/`end_date`.
- Membangun ulang `/data-intelligence` atau `/admin/analytics/*`.
- Pipeline pengumpulan data baru / migrasi skema baru.
- Mengganti peran operasional halaman Dashboard.
- Generasi narasi LLM — hanya label deterministik.
- Drill-down / baris clickable — **dropped** karena tidak ada dynamic route maupun
  filter param di codebase (kandidat follow-up).
- Versi Analitik self-scoped untuk outlet user — **deferred** follow-up.

---

## Architecture Constraints

- **Layers this work may touch:** `apps/api` (AnalyticsService, AnalyticsController,
  routes, feature tests), `apps/web` (analytics page/api, reuse `components/ui`,
  dummy aggregates + guards + fixtures).
- **Layers this work must NOT touch:** `DataPipelineService`/snapshot publication;
  internal algoritma forecast/recommendation/segmentation; auth/RBAC core.
- **Patterns that must be followed:** agregat bounded (`MAX_DATE_RANGE_DAYS=366`,
  `OUTLET_PERFORMANCE_LIMIT=10`); sumber kebenaran tunggal dengan Dashboard; guard
  `withDummyRead`; envelope JSON `status`/`data`; format id-ID; tie-break deterministik
  (nama lalu id); jalur uang integer-cents (`decimalToCents`/`moneyFromCents`).
- **Architecture validation result:** PASS

---

## Dependencies

### Existing (to leverage)
- **Carbon** (Laravel/Nesbot) — aritmetika tanggal & jendela periode.
- **`AnalyticsService` private helpers** — `orderQuery`, `paymentQuery`,
  `outstandingOrderQuery`, `salesTrends`, `outletPerformance`, `periodKey`,
  `decimalToCents`, `moneyFromCents` (dipakai ulang, tanpa refactor).
- **React 18 / Next 16 / Zustand 4 / Jest + Testing Library** — halaman & tes.
- **`StatCard`, `Badge`, `Card`, `Table`, `EmptyState`, `PageHeader`** — primitif UI.
- **`Charts.tsx`** — `SalesTrendChart`, `OutletPerformanceChart`.

### New (proposed)
none. Tidak ada library baru. Aritmetika uang/persentase **wajib** memakai jalur
integer-cents yang ada agar tidak lahir jalur pembulatan kedua (menambahkan decimal
library akan melanggar single-source-of-truth dan ditolak).

---

## Stories + Scenarios

### Story 1: Strip metrik strategis dengan perbandingan periode
> As an admin/owner, I want each headline metric with a period-over-period delta, so that I can see what changed, not just what the total is.

**Rule 1.1: Jendela tetap & bersebelahan**
- current = `end−29 … end` (30 hari inklusif); previous = `end−59 … end−30` (30 hari sebelumnya).

```gherkin
Scenario: Jendela sama panjang dan bersebelahan
  Given hari ini adalah 2026-09-18
  When  admin meminta GET /api/analytics/insight
  Then  comparison.period.start_date adalah "2026-08-20" dan end_date "2026-09-18"
  And   comparison.previous_period.start_date adalah "2026-07-21" dan end_date "2026-08-19"
```

**Rule 1.2: Perhitungan delta**
- `delta_percent = (current − previous) / previous × 100`, dibulatkan 1 desimal.
- `previous == 0` → delta `null` → chip "belum ada pembanding".
- `current == previous` → delta `0.0` → direction `neutral`, chip "0,0%".
- Arah mengikuti delta **unrounded** sehingga chip `up` tidak pernah menampilkan "0,0%".
- Metrik: `orders_total`, `sales_total`, `payments_total`, `outstanding_total`.
- `outlets_total`/`products_total` adalah count point-in-time → **tanpa** delta.

```gherkin
Scenario: Delta naik
  Given sales_total previous "100.00" dan current "120.00"
  Then  metrics_delta.sales_total.delta_percent adalah 20.0 dan direction "up"

Scenario: Delta turun
  Given previous "100.00" dan current "80.00"
  Then  delta_percent adalah -20.0 dan direction "down"

Scenario: Baseline nol menghasilkan delta null (bukan persentase palsu)
  Given previous "0.00" dan current "50.00"
  Then  delta_percent adalah null dan direction "neutral"
  And   UI menampilkan "belum ada pembanding"

Scenario: Perubahan nol menghasilkan chip 0,0%
  Given previous "100.00" dan current "100.00"
  Then  delta_percent adalah 0.0 dan direction "neutral"
  And   UI menampilkan "0,0%" (bukan "belum ada pembanding")

Scenario: Status dikecualikan tidak dihitung di kedua jendela
  Given sebuah outlet hanya punya order "Cancelled" di jendela sebelumnya
  Then  sales_total previous outlet itu adalah "0.00"
```

### Story 2: Strip "Needs attention"
> As an admin/owner, I want a short ranked list of outlets that need attention, so that I can act on risk without scanning every outlet.

**Rule 2.1: Kriteria kualifikasi**
- Kualifikasi jika **sales decline ≥ 20%** vs jendela sebelumnya (dengan `previous > 0`)
  **ATAU** **outstanding point-in-time > 0** (allow-list `OUTSTANDING_ORDER_STATUSES`).
- Ambang dievaluasi pada **unrounded** delta dengan aritmetika cents → tepat −20%
  **termasuk**.
- Outlet dengan `previous == 0` dan tanpa outstanding → **dikecualikan** (tanpa baseline).

```gherkin
Scenario: Penurunan >=20% masuk needs_attention
  Given outlet menjual "1000.00" previous dan "700.00" current
  Then  needs_attention memuat outlet itu dengan reason "sales_decline" dan delta_percent -30.0

Scenario: Penurunan tepat -20% termasuk (ambang inklusif)
  Given outlet menjual "1000.00" previous dan "800.00" current
  Then  needs_attention memuat outlet itu

Scenario: Penurunan di bawah ambang dikecualikan (unrounded)
  Given outlet menjual "1000.00" previous dan "800.04" current
  Then  needs_attention TIDAK memuat outlet itu

Scenario: Outstanding point-in-time masuk needs_attention
  Given outlet punya order belum dibayar total "5000.00" yang lebih tua dari 30 hari
  Then  needs_attention memuat outlet itu dengan reason "outstanding_risk"

Scenario: Outlet tanpa baseline dan tanpa outstanding dikecualikan
  Given previous "0.00", current "500.00", tanpa outstanding
  Then  needs_attention TIDAK memuat outlet itu
```

**Rule 2.2: Cap & urutan deterministik**
- Maks **5** item.
- Urutan: semua item `sales_decline` lebih dulu (urut `|unrounded delta_percent|` desc),
  lalu item `outstanding_risk` (urut `outstanding_total` desc), masing-masing tie-break
  nama asc → id asc.
- Outlet yang memenuhi kedua alasan muncul **sekali**, `sales_decline` menang.

```gherkin
Scenario: needs_attention dibatasi lima
  Given delapan outlet yang memenuhi kualifikasi
  Then  needs_attention berisi tepat 5 item

Scenario: Urutan deterministik decline-dulu lalu magnitudo mentah
  Given outlet decline -35% dan outlet outstanding 900000
  Then  outlet decline mendahului outlet outstanding

Scenario: Tie-break nama untuk severity sama
  Given "Alpha" dan "Beta" dengan severity sama
  Then  "Alpha" mendahului "Beta"

Scenario: Outlet dengan kedua alasan muncul sekali
  Given outlet punya penurunan 30% dan outstanding "5000.00"
  Then  outlet muncul tepat sekali dengan reason "sales_decline"
```

### Story 3: Grafik tren + peringkat outlet
> As an admin/owner, I want a daily sales trend and a top-outlet ranking, so that I can see momentum and concentration.

**Rule 3.1: Bucket harian zero-filled**
- `sales_trends` mengembalikan **tepat 30 bucket** harian (hari tanpa order =
  `orders_total: 0`, `sales_total: "0.00"`, `payments_total: "0.00"`).
- Zero-fill dilakukan di **lapisan insight**; Dashboard tetap sparse; helper
  `salesTrends()` tidak diubah.

**Rule 3.2: Peringkat + has_more + total**
- Peringkat top-10 (tie-break deterministik) + `outlet_performance_total`
  (`COUNT DISTINCT outlet_id` di jendela current).
- `has_more = total > 10`; UI menampilkan "dan N outlet lain" dengan N = total − 10;
  baris dihilangkan bila total ≤ 10.

```gherkin
Scenario: Tren zero-fill hari tanpa order
  Given jendela current 30 hari hanya punya order di 12 hari
  Then  sales_trends berisi tepat 30 bucket
  And   hari tanpa order punya orders_total 0 dan sales_total "0.00"

Scenario: Jumlah outlet menggerakkan baris "dan N outlet lain"
  Given 15 outlet punya penjualan di jendela current
  Then  outlet_performance_total adalah 15 dan UI menampilkan "dan 5 outlet lain"

Scenario: Tanpa kelebihan outlet, baris dihilangkan
  Given hanya 7 outlet punya penjualan
  Then  outlet_performance_total adalah 7 dan baris penutup dihilangkan
```

### Story 4: Kartu AI dengan label kepercayaan inline
> As an admin/owner, I want the heuristic nature of AI cards disclosed inline, so that I can trust what I'm reading.

**Rule 4.1: Label kepercayaan**
- Kartu rekomendasi menampilkan `data_sufficiency.level` + `measurement.note` bila ada.
- Kartu forecast menampilkan `method` (+ `data_sufficiency`) bila ada.
- `level` (`insufficient|limited|adequate`) dipetakan ke copy id-ID
  (mis. `insufficient` → "Data belum cukup").
- Field metadata absen → label dihilangkan (tanpa "undefined", tanpa crash).
- Kartu dipertahankan & dipindah ke bawah seksi baru; konten lain tidak berubah.

```gherkin
Scenario: Label kepercayaan tampil saat metadata ada
  Given recommendations data_sufficiency.level "insufficient"
  Then  kartu menampilkan "Data belum cukup"

Scenario: Metadata absen tidak merusak kartu
  Given metadata measurement tidak ada
  Then  tidak ada label measurement dan halaman tetap render tanpa error
```

### Story 5: Kontrol akses
> As the platform, I want Analitik restricted to admin/owner, so that cross-outlet strategic data is not exposed.

**Rule 5.1: Gate admin/owner**
- Gate memakai `FinanceAuthorizationService::isAdminOrOwner()` (termasuk cek `is_active`).
- `platform_owner` aktif → 200. Non-admin/non-owner → 403 envelope error. Tanpa token → 401.
- `platform_owner` non-aktif → 403.
- Nav item `/analytics` `adminOnly`; `platform_owner` diperlakukan setara admin sehingga
  item `adminOnly` tetap terlihat oleh owner.

```gherkin
Scenario: platform_owner diizinkan
  Given platform_owner terautentikasi dan aktif
  Then  GET /api/analytics/insight mengembalikan 200

Scenario: Non-admin/non-owner ditolak
  Given outlet user terautentikasi
  Then  GET /api/analytics/insight mengembalikan 403 dengan status "error"

Scenario: Tanpa token ditolak
  Given tidak ada token
  Then  401

Scenario: platform_owner non-aktif ditolak
  Given platform_owner dengan is_active false
  Then  GET /api/analytics/insight mengembalikan 403

Scenario: platform_owner tetap melihat nav Analitik
  Given sidebar dirender untuk platform_owner
  Then  item Analitik terlihat (item adminOnly ditampilkan untuk owner)
```

### Story 6: Parity dummy mode
> As a developer, I want dummy mode to serve the full new contract, so that dummy parity holds.

**Rule 6.1: Fixture & jendela dummy**
- Fixture baru `dummy.analyticsInsight` memiliki field baru.
- Jendela dummy existing (`dummyWindow` = 61 tanggal, `end−60…end`) dipecah:
  current = `end−29…end`, previous = `end−59…end−30`; `end−60` tidak dipakai.
  `dummyWindow` **tidak diubah**.
- Dummy role selalu admin → strip selalu render. Dummy ON → **nol** network call.

```gherkin
Scenario: Dummy mode menyajikan kontrak penuh tanpa network
  Given dummy mode ON
  Then  delta dan needs_attention dirender dan tidak ada network request

Scenario: Jendela dummy dipecah current 30 + previous 30
  Given dummyWindow end "2026-02-14"
  Then  slice current "2026-01-16".."2026-02-14" dan previous "2025-12-17".."2026-01-15"
```

### Story 7: Degradasi terpisah per seksi
> As an admin/owner, I want a failing section to not blank the whole page, so that I still get value from the sections that work.

**Rule 7.1: Degradasi ter-scope**
- Kegagalan `/analytics/insight` tidak mengosongkan kartu AI; kegagalan `/ai/*` tidak
  mengosongkan strip. Setiap seksi menampilkan error ter-scope.

```gherkin
Scenario: Kegagalan insight hanya merusak seksinya
  Given /analytics/insight gagal tetapi tiga /ai/* sukses
  Then  kartu AI tetap render dan strip menampilkan error ter-scope

Scenario: Kegagalan AI hanya merusak seksinya
  Given salah satu /ai/* gagal tetapi /analytics/insight sukses
  Then  strip render normal dan seksi AI menampilkan error ter-scope
```

---

## Acceptance Criteria

```
ACCEPTANCE CRITERIA — Analitik Deeper Insight
Date: 2026-09-18 | Scope confirmed: yes

Rule: Jendela perbandingan tetap
  ✓ Given hari ini 2026-09-18, When admin meminta /api/analytics/insight,
    Then period = 2026-08-20..2026-09-18 dan previous = 2026-07-21..2026-08-19

Rule: Perhitungan delta
  ✓ Given previous "100.00", current "120.00", Then delta 20.0 dan direction "up"
  ✓ Given previous "100.00", current "80.00", Then delta -20.0 dan direction "down"
  ✓ Given previous "0.00", current "50.00", Then delta null dan chip "belum ada pembanding"
  ✓ Given previous "100.00", current "100.00", Then delta 0.0 dan chip "0,0%"
  ✗ Given previous "0.00", current "50.00", Then BUKAN 100% (harus null)

Rule: Status dikecualikan
  ✓ Given hanya order "Cancelled" di jendela sebelumnya, Then previous sales "0.00"

Rule: Kriteria needs_attention
  ✓ Given decline "1000.00"→"700.00", Then masuk dengan reason "sales_decline", delta -30.0
  ✓ Given decline tepat "1000.00"→"800.00", Then masuk (ambang inklusif)
  ✓ Given decline "1000.00"→"800.04", Then TIDAK masuk (unrounded)
  ✓ Given outstanding point-in-time "5000.00" (lebih tua dari 30 hari), Then masuk reason "outstanding_risk"
  ✓ Given previous "0.00", current "500.00", tanpa outstanding, Then TIDAK masuk

Rule: Cap & urutan needs_attention
  ✓ Given delapan outlet memenuhi, Then tepat 5 item
  ✓ Given decline -35% dan outstanding 900000, Then decline lebih dulu
  ✓ Given "Alpha"/"Beta" severity sama, Then "Alpha" lebih dulu
  ✓ Given outlet memenuhi kedua alasan, Then muncul sekali dengan "sales_decline"

Rule: Tren & peringkat
  ✓ Given order hanya di 12 hari, Then sales_trends tepat 30 bucket (zero-filled)
  ✓ Given 15 outlet, Then outlet_performance_total 15, UI "dan 5 outlet lain"
  ✓ Given 7 outlet, Then baris penutup dihilangkan

Rule: Label kepercayaan AI
  ✓ Given data_sufficiency.level "insufficient", Then kartu menampilkan "Data belum cukup"
  ✓ Given measurement absen, Then tanpa label dan tanpa error

Rule: Kontrol akses
  ✓ Given platform_owner aktif, Then 200
  ✓ Given outlet user, Then 403 status "error"
  ✗ Given tanpa token, Then 401
  ✗ Given platform_owner non-aktif, Then 403
  ✓ Given sidebar platform_owner, Then item Analitik terlihat

Rule: Parity dummy
  ✓ Given dummy ON, Then delta + needs_attention dirender dan nol network
  ✓ Given dummyWindow end "2026-02-14", Then current 2026-01-16..2026-02-14, previous 2025-12-17..2026-01-15

Rule: Degradasi terpisah
  ✓ Given /analytics/insight gagal, Then kartu AI tetap render
  ✓ Given /ai/* gagal, Then strip tetap render

OPEN QUESTIONS (risks if unresolved):
  - (tidak ada — semua pertanyaan blocking sudah diselesaikan)

OUT-OF-SCOPE (remind pocket-planning):
  - ML/LLM produksi; BI suite/report builder; date-range picker
  - Rebuild /data-intelligence atau /admin/analytics/*
  - Pipeline/migrasi skema baru; mengganti peran Dashboard
  - Narasi LLM; drill-down/clickable rows; versi self-scoped outlet user
```

---

## Design Decision

**Chosen option:** Option A — Extend `AnalyticsService` + new controller action

**Summary:** Tambahkan method publik `insight()` pada `AnalyticsService` yang memanggil
helper privatnya sendiri (`orderQuery`, `paymentQuery`, `outstandingOrderQuery`,
`salesTrends`, `outletPerformance`) untuk jendela current & previous, lalu compose
delta + zero-fill + needs_attention. Tambahkan `AnalyticsController::insight()` pada
route `GET /api/analytics/insight` dengan gate `isAdminOrOwner()`. Frontend memanggil
satu endpoint baru + tiga `/ai/*`, dengan degradasi terpisah per seksi.

**Rejected options:**
- **Option B** (extract shared trait + `AnalyticsInsightService`): pemisahan jangka
  panjang lebih baik, tetapi refactor menyentuh jalur Dashboard yang sudah teruji
  (`AnalyticsTest.php`) dengan blast radius tidak sepadan untuk ukuran fitur ini —
  bisa jadi cleanup terpisah.
- **Option C** (komposisi frontend, dua panggilan `/analytics/dashboard`): **gagal**
  skenario kritis — tidak bisa menghasilkan outstanding point-in-time, tidak bisa
  `outlet_performance_total`, tidak bisa needs_attention server-side, menggandakan
  request, dan menurut spike Phase 3 tidak bisa menghasilkan delta peringkat.

**Key tradeoffs accepted:**
- `AnalyticsService` bertambah besar (~180 → ~300 baris) demi menghindari refactor
  berisiko pada jalur Dashboard yang sudah lulus tes.
- Endpoint baru berarti Dashboard tidak membayar biaya komputasi delta yang tidak
  dirender; "sumber kebenaran tunggal" dipenuhi lewat **kode bersama**, bukan payload.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Deltas untuk `outlets_total`/`products_total`? | assumed: tidak ada (count point-in-time) | Owner mungkin mengharapkan delta; mudah ditambah kemudian |
| Definisi severity untuk sort? | resolved: raw values, decline-first, lalu magnitudo mentah | Tidak ada — sudah eksplisit |
| Bahasa label kepercayaan | assumed: copy id-ID via peta level (`insufficient` → "Data belum cukup") | Copy bisa perlu penyesuaian wording |
| Apakah `platform_owner` setara admin di Sidebar secara global? | assumed: ya, untuk item `adminOnly` | Bisa mengubah visibilitas nav owner untuk item admin lain (perilaku diinginkan) |

*(Semua pertanyaan blocking sudah diselesaikan; sisa di atas adalah non-blocking.)*

---

## Implementation Notes

- Endpoint `/analytics/insight` **harus** mengabaikan/menolak `start_date`/`end_date`;
  jendela tetap `end−29…end`.
- Reuse `decimalToCents`/`moneyFromCents` untuk seluruh aritmetika uang & persentase —
  jangan menambah jalur pembulatan kedua.
- Ambang −20% dievaluasi pada cents (atau epsilon) agar tepat −20% termasuk.
- Arah delta mengikuti nilai **unrounded**.
- Zero-fill tren dilakukan di lapisan insight; `salesTrends()` dan Dashboard tidak diubah.
- Gate controller memakai `FinanceAuthorizationService::isAdminOrOwner()`.
- Sidebar: perlakukan `role === 'admin' || role === 'platform_owner'` sebagai cabang
  full-nav agar item `adminOnly` tetap terlihat owner.
- Parity dummy wajib: fixture `dummy.analyticsInsight` + pemecahan jendela 61→(30+30).

---

## Rollback Plan

- Revert route `GET /api/analytics/insight`, action `AnalyticsController::insight()`, dan
  method `AnalyticsService::insight()` — tidak ada perubahan skema/migrasi.
- Frontend: kembalikan `analytics/page.tsx` + `api.ts` ke versi 3-kartu; kembalikan
  NAV_ITEMS/Sidebar.
- Dummy: hapus fixture `analyticsInsight` + pemecahan jendela.
- Tidak ada perubahan pada `/analytics/dashboard` maupun kontrak Dashboard, sehingga
  Dashboard aman selama rollback.
