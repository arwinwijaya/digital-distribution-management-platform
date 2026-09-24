# Phase 9 — AI Action & Supply Chain (Recommendation→Action, Approval Workflow, Automated Replenishment, Forecast Calibration, Revenue Lift)

**Date:** 2026-09-24
**Status:** planned (spec approved for planning; implementation not started)
**Execution plan:** docs/pocket/plans/2026-09-24-phase9-ai-action-supply-chain/execution-plan/index.md (planned)
**Author:** roadmap gap analysis (development-roadmap.md → Phase 9)
**Spec path:** docs/pocket/spec/2026-09-24-phase9-ai-action-supply-chain/phase9-ai-action-supply-chain.md

---

## Summary

Roadmap Phase 9 menuntut intelijen yang **dapat ditindaklanjuti**: bukan hanya
rekomendasi/forecast yang ditampilkan, tetapi alur **action → approval → execute**
yang auditable, replenishment otomatis yang aman, kalibrasi forecast, dan pengukuran
revenue lift lewat eksperimen.

Saat ini Phase 3 sudah menyediakan fondasi deterministik yang lengkap tetapi
**read-only**: `RecommendationService` (rekomendasi produk per outlet, tanpa model
eksternal), `ForecastService` (prakiraan permintaan), `StockPlanningService`
(sinyal reorder — **tidak pernah mengeksekusi** pembelian), `MeasurementService`
(funnel rekomendasi + WAPE prakiraan), `DataPipelineService` + `ActiveDataSnapshotReader`
(snapshot immutable), dan `RecommendationEvent` + `forecast_actuals` sebagai jejak event.
Tidak ada satu pun jalur yang mengubah state bisnis (order/promo/PO) dari sebuah rekomendasi.

Spec ini mendefinisikan **Phase 9 end-to-end** dalam 6 fase bertahap: fondasi data
(tabel action/approval/replenishment/kalibrasi/eksperimen) → API backend (draft dari
rekomendasi, workflow approval, eksekusi replenishment) → adapter ML/LLM *seam* opsional
dengan fallback deterministik + layanan kalibrasi/A-B/revenue-lift → permukaan frontend
(inbox approval, replenishment, dashboard eksperimen) → parity dummy + wiring RBAC/menu →
integrasi & verifikasi lintas unit.

**Prinsip pengaman (non-negotiable):** MVP Phase 9 berjalan **100% deterministik di PHP**
dan **setiap mutasi state bisnis wajib lewat approval manusia**. Python ML/LLM adalah
**adapter opsional di balik interface** yang, bila tidak tersedia/gagal, otomatis jatuh ke
jalur deterministik — dan **tidak pernah** menjadi prasyarat untuk lulus acceptance criteria.

---

## Context

### Current State

- **Recommendation:** `apps/api/app/Services/RecommendationService.php` — deterministik,
  tanpa model eksternal. Konstanta `MIN_DATA_POINTS = 3`, `WINDOW_DAYS = 30`; status order
  dikecualikan `Cancelled/Canceled/Rejected/Invalid`; mengembalikan `data_sufficiency`,
  `fallback`, `method`, `method_version`, `measurement`. **Read-only** — tidak ada draft order
  atau draft promo yang dibuat dari rekomendasi.
- **Forecast:** `apps/api/app/Services/ForecastService.php` — rata-rata historis
  terkelompok dengan fallback `insufficient-data`. Belum ada kalibrasi (bias/seasonality)
  yang dipersist, dan belum ada hubungan ke `forecast_actuals` untuk evaluasi berjalan.
- **Stock planning / replenishment:** `apps/api/app/Services/StockPlanningService.php` —
  stage producer untuk snapshot `stock`; `reorder_quantity = lead_time_demand − available_stock`.
  **Eksplisit tidak pernah mengeksekusi reorder** (komentar kode + hanya baca). Belum ada
  purchase order (PO) draft, supplier routing, atau eksekusi idempotent.
- **Measurement:** `apps/api/app/Services/MeasurementService.php` — funnel 30 hari
  (`displayed/clicked/cart/purchased`, attribution `order_match` by `product_id`+`outlet_id`,
  tanpa campaign attribution) + WAPE dengan target `WAPE < 0.30`; `WAPE_TARGET = 0.30`,
  `WINDOW_DAYS = 30`; status `insufficient-data`/`pending`/`target_achieved`/`target_not_achieved`.
  Belum ada revenue-lift (before/after atau A/B) dan belum ada eksperimen.
- **Pipeline & snapshot:** `apps/api/app/Services/DataPipelineService.php`
  (`PIPELINE_VERSION='v1'`, `SECTIONS=['geographic','supplier','stock','measurement']`,
  publikasi atomik, snapshot immutable) + `ActiveDataSnapshotReader` (read-only view).
  Section baru untuk action/experiment dapat ditambahkan tanpa mengubah kontrak lama.
- **Event & actuals:** `RecommendationEvent` (migration
  `2026_09_14_000028_create_recommendation_events_table.php`; `event_uuid` unik,
  `event_type ∈ displayed|clicked|cart`, indeks attribution) dan `forecast_actuals`
  (migration `2026_09_14_000029`; `dimension_key` unik + `pairs` json).
- **Controller:** `apps/api/app/Http/Controllers/AIController.php` — `GET /ai/recommendations`
  + `GET /ai/forecast`; `StockPlanningController::index` admin-gated inline (`isAdmin()`)
  pada `GET /admin/analytics/stock-planning` dengan `rbac:data_intelligence:read`;
  `MeasurementController` (`POST /admin/measurement/events`,
  `GET /admin/measurement/recommendations|forecasts`) — semuanya **baca/tulis event saja**.
- **Approval precedent:** `OrderController::runApprovalTransaction()` — transaksi
  lock-protected, invoice immutable, retry `QueryException` 3x, barrier uji
  `ConcurrencyTestBarrier::await('approval')`; idempotency order via
  `OrderCreationService` (`idempotency_key` + `idempotency_payload_hash` + retry race read-back).
  Menu `admin_orders` = "Approval Pesanan" — pola approval yang bisa diikuti.
- **RBAC/menu:** `MenuDefinition::CATALOG` = 21 menu (satu sumber kebenaran bersama
  `role_menu_access.menu_key` dan `NavItem.key` frontend); `RbacMatrixSeeder` (21 menu × 7 role).
- **Frontend:** `apps/web/src/app/data-intelligence/page.tsx` (+ test) menampilkan
  geographic/supplier/stock/measurement; `apps/web/src/dummy/*` (Zustand store,
  `withDummyRead`, `commitIfCurrent`, `useDummyRefresh`, seed, guards) dipakai semua modul API.

### Problem / Motivation

- Rekomendasi dan sinyal reorder berhenti di layar — tidak ada jalur dari insight ke aksi,
  sehingga nilai bisnis Phase 3 belum terwujud (roadmap Phase 9 `[ ]`).
- Tidak ada audit siapa menyetujui/mengeksekusi apa → tidak dapat dipertanggungjawabkan.
- Replenishment otomatis tanpa approval + idempotency berisiko double-order ke supplier.
- Tanpa kalibrasi/eksperimen, klaim "AI meningkatkan penjualan" tidak terukur (revenue lift).
- Bila nanti ditambahkan ML/LLM eksternal, tanpa *seam* + guardrail, model bisa memutasi
  state bisnis secara langsung — risiko integritas data.

### Related Areas

- `apps/api/app/Services/RecommendationService.php`, `ForecastService.php`,
  `StockPlanningService.php`, `MeasurementService.php`, `DataPipelineService.php`,
  `ActiveDataSnapshotReader.php`, `OrderCreationService.php`, `PromotionService.php`
- `apps/api/app/Http/Controllers/AIController.php`, `StockPlanningController.php`,
  `MeasurementController.php`, `OrderController.php` (`runApprovalTransaction`)
- `apps/api/app/Models/RecommendationEvent.php`, `Order.php`, `Product.php`, `Supplier.php`,
  `Promotion.php`, `Outlet.php`, `User.php`
- `apps/api/database/migrations/2026_09_14_000028_create_recommendation_events_table.php`,
  `2026_09_14_000029_create_forecast_actuals_table.php`
- `apps/api/database/seeders/RbacMatrixSeeder.php`, `apps/api/app/Models/MenuDefinition.php`
- `apps/api/routes/api.php` (grup `auth:api`, `reject.stale_jwt`, `rbac:<menu>:<level>`)
- `apps/web/src/app/data-intelligence/*`, `apps/web/src/components/data-intelligence/*`
- `apps/web/src/dummy/*`, `apps/web/src/lib/*-api.ts`, `apps/web/src/components/{Sidebar,Topbar}.tsx`

---

## Scope

### In-Scope

- **Action layer (draft-first):** tabel `recommendation_actions` (sumber rekomendasi,
  tipe aksi `draft_order|draft_campaign`, payload draft, status
  `draft|pending_approval|approved|rejected|executed|failed|cancelled`, aktor,
  `idempotency_key` unik, `idempotency_payload_hash`, jejak audit) + service
  `RecommendationActionService` untuk membuat draft dari rekomendasi (tanpa mutasi state bisnis).
- **Approval workflow:** endpoint admin `POST /admin/recommendation-actions/{id}/approve`
  dan `.../reject` dengan transisi status terkunci, actor attribution (`approved_by`,
  `approved_at`, `rejection_reason`), idempotent replay, dan audit trail append-only
  (`recommendation_action_events`).
- **Execution (approval-gated):** `POST /admin/recommendation-actions/{id}/execute`
  yang **hanya** berjalan bila status `approved`; `draft_order` dieksekusi lewat
  `OrderCreationService` yang sudah idempotent; `draft_campaign` lewat `PromotionService`;
  status akhir `executed`/`failed` + `execution_result`.
- **Automated replenishment:** tabel `replenishment_plans` (+ `replenishment_plan_items`)
  yang menerjemahkan `StockPlanningService` (read-only) menjadi **draft PO per supplier**;
  endpoint `POST /admin/replenishment-plans/generate` (draft, idempotent per window+supplier),
  `POST /admin/replenishment-plans/{id}/approve`, dan `.../execute` (mencatat PO draft /
  reorder request — tidak mengubah stok langsung tanpa approval).
- **Forecast calibration:** tabel `forecast_calibrations` menyimpan faktor bias/seasonality
  per dimensi (produk/outlet) + service kalibrasi deterministik yang menerapkan faktor
  ke `ForecastService`, dengan `method_version` dan `fallback` bila data kurang.
- **A/B experiment & revenue lift:** tabel `ab_experiments` + `ab_experiment_assignments`
  + `revenue_lift_snapshots`; assignment deterministik (hash `experiment_key`+`subject_key`),
  dan perhitungan revenue lift dari `MeasurementService`/order aktual (bukan klaim).
- **ML/LLM adapter seam (opsional):** interface `RecommendationModelAdapter`
  (mis. `predict()` / `explain()`) dengan implementasi default
  `DeterministicRecommendationAdapter` (membungkus `RecommendationService`); adapter
  Python/LLM hanya dipilih via config dan **selalu** memiliki fallback deterministik +
  timeout/circuit-breaker; adapter **tidak boleh** memutasi state bisnis (hanya mengembalikan
  saran) dan output-nya divalidasi sebelum dipakai.
- **Guardrail LLM:** validator output terstruktur (skema ketat), penolakan bila field tak
  dikenal, pencatatan prompt/response digest ke audit (tanpa PII mentah), dan kill-switch
  config yang memaksa fallback deterministik.
- **Frontend action surfaces:** halaman admin **inbox approval** rekomendasi, halaman
  **replenishment** (generate/approve/execute), dan **dashboard eksperimen/revenue lift**;
  semuanya memakai pola RBAC + dummy parity yang ada.
- **Observability & audit:** setiap transisi aksi menulis baris audit append-only +
  mengekspos `method`/`method_version`/`fallback`/`data_sufficiency` pada respons.
- **Parity dummy mode** untuk seluruh endpoint/halaman baru (zero network saat ON).
- **RBAC:** menu baru `ai_actions` (+ `supply_chain` bila perlu), entri CATALOG, seeder
  default, dan `NavItem` frontend.

### Out-of-Scope

- **Pelatihan model ML/LLM produksi** (training pipeline, GPU, MLOps) — Phase 9 hanya
  menyediakan *seam* adapter opsional; MVP tidak membutuhkannya.
- **Autonomous execution tanpa approval** — dilarang mutlak di Phase 9.
- AWS deployment/object storage/observability produksi (Phase 10) — audit disimpan di DB.
- Multi-tenant/dynamic pricing/payment gateway (Phase 10).
- Perubahan destruktif pada skema/transaksi order atau `OrderCreationService` inti
  (hanya **dipanggil** sebagai reuse).
- Optimasi solver tingkat lanjut (VRP/time-window) — di luar supply-chain replenishment dasar.
- Rebuild halaman `/data-intelligence` atau `/analytics` (hanya menambah section/halaman baru).

---

## Architecture Constraints

- **API:** Laravel REST, respons envelope `{status, data}`; error `{status:'error', message}`.
- **Otorisasi:** setiap route baru dilindungi `auth:api` + `reject.stale_jwt` +
  `rbac:<menu>:<level>`; admin-only untuk approval/execute; actor selalu diambil dari
  `$request->user()` (tidak pernah dari payload).
- **DB:** PostgreSQL runtime, SQLite test. Migrasi **additive + reversible**; ekspresi/kolom
  portabel SQLite + PostgreSQL (tanpa `ALTER ... DROP` pada kolom berindeks).
- **Idempotency:** semua endpoint mutasi menerima `idempotency_key` unik; replay dengan
  payload identik mengembalikan hasil lama (`idempotent_replay: true`); payload berbeda → 409/422.
  Pola mengikuti `OrderCreationService` + `MeasurementController::storeEvent`.
- **Guardrail:** tidak ada jalur dari adapter ML/LLM langsung ke mutasi state bisnis;
  eksekusi hanya lewat service action yang memeriksa status `approved`.
- **Frontend:** Next.js 16 App Router, semua halaman `'use client'`, Zustand ^4.5.0,
  Tailwind; tanpa dependency baru kecuali benar-benar diperlukan (mis. client ML — dihindari).
- **Parity dummy:** semua modul API baru memakai `withDummyRead`; setiap field baru punya
  fixture di `apps/web/src/dummy/*`.
- **Test:** API `php artisan test` (PHPUnit, Feature + Unit); web `npx jest` + `tsc --noEmit`.
- **Pipeline:** section baru (mis. `actions`, `experiments`) ditambahkan sebagai stage
  terdaftar; kontrak snapshot lama tidak berubah.

---

## Design Decision

**DD-1 — Draft-first, approval-gated, never autonomous.** Semua aksi lahir sebagai *draft*
(`recommendation_actions`, `replenishment_plans`) dan tidak menyentuh state bisnis. Mutasi
hanya terjadi pada `execute` setelah status `approved` oleh admin. Alasan: keamanan + audit;
MVP tidak boleh mengandalkan kepercayaan pada model.

**DD-2 — Reuse service transaksional yang sudah ada.** Eksekusi `draft_order` memanggil
`OrderCreationService::create()` (idempotent, lock outlet/produk, credit check) dan
`draft_campaign` memanggil `PromotionService::create()` (overlap check + audit). Tidak
membuat jalur order/promo baru — menghindari divergensi invariant.

**DD-3 — Idempotent replay di setiap mutasi.** Setiap endpoint mutasi memakai
`idempotency_key` unik + `idempotency_payload_hash`; replay identik mengembalikan hasil lama,
payload berbeda ditolak. Pola disalin dari `OrderCreationService`/`MeasurementController`.

**DD-4 — Deterministic baseline, ML/LLM as optional seam.** `RecommendationModelAdapter`
memiliki implementasi default deterministik (`DeterministicRecommendationAdapter`). Adapter
eksternal dipilih via config, memiliki timeout + fallback, dan **tidak pernah** menjadi
prasyarat acceptance criteria. Alasan: roadmap menuntut kemampuan AI, tetapi keandalan
produk tidak boleh bergantung pada layanan eksternal non-deterministik.

**DD-5 — Audit trail append-only + actor dari sesi.** Semua transisi ditulis ke
`recommendation_action_events` (append-only, tanpa update/delete) dengan actor dari
`$request->user()`; prompt/response LLM (bila ada) hanya disimpan sebagai digest/metadata
tanpa PII mentah.

**DD-6 — Replenishment sebagai draft PO, bukan mutasi stok langsung.** `StockPlanningService`
tetap read-only; draft PO diturunkan dari output-nya dan dieksekusi lewat approval. Alasan:
menghindari loop otomatis yang mengubah stok/supplier tanpa kontrol manusia.

---

## Acceptance Criteria

### AC-1 — Draft aksi dari rekomendasi (draft-first)
- **Given** admin dengan `rbac:ai_actions:edit` dan rekomendasi tersedia untuk (outlet, produk),
  **When** ia `POST /admin/recommendation-actions` dengan `type=draft_order`,
  `outlet_id`, `items[]`, dan `idempotency_key`, **Then** 201 `{status:'success', data:{...}}`
  dengan status `draft` dan **tidak ada** order/promo baru yang tercipta.
- **Given** payload `type=draft_campaign`, **When** draft dibuat, **Then** tersimpan sebagai
  `draft` tanpa memanggil `PromotionService`.
- **Given** `idempotency_key` yang sama dengan payload identik, **When** dipanggil ulang,
  **Then** respons sama + `idempotent_replay: true` (tidak ada baris baru).
- **Given** `idempotency_key` sama dengan payload berbeda, **Then** 409/422.
- **Given** role tanpa izin, **When** akses, **Then** 403.

### AC-2 — Approval / rejection workflow
- **Given** action berstatus `draft`, **When** admin `POST /admin/recommendation-actions/{id}/approve`,
  **Then** status → `approved`, `approved_by`/`approved_at` terisi, dan satu baris audit tercatat.
- **Given** action `draft`, **When** admin `.../reject` dengan `reason`, **Then** status →
  `rejected` + `rejection_reason` tersimpan + audit.
- **Given** action `approved`, **When** approve lagi, **Then** idempotent (status tetap,
  tanpa duplikasi audit transisi).
- **Given** action `executed`/`rejected`, **When** approve, **Then** 422 (transisi ilegal).
- **Given** non-admin, **When** approve/reject, **Then** 403.

### AC-3 — Eksekusi approval-gated
- **Given** action `draft` (belum approved), **When** `POST .../execute`, **Then** 422
  (tidak boleh eksekusi sebelum approval).
- **Given** action `approved` `draft_order`, **When** execute, **Then** order tercipta via
  `OrderCreationService` (idempotent), status → `executed`, `execution_result` berisi
  `order_id`; **When** execute diulang, **Then** tidak ada order ganda (replay hasil lama).
- **Given** action `approved` `draft_campaign`, **When** execute, **Then** promo tercipta
  via `PromotionService` dan status → `executed`.
- **Given** eksekusi gagal (mis. stok kurang), **Then** status → `failed`, `execution_result`
  berisi pesan, dan **tidak ada** state bisnis parsial.

### AC-4 — Automated replenishment (draft PO + approval)
- **Given** data stok memadai, **When** admin `POST /admin/replenishment-plans/generate`,
  **Then** terbentuk draft plan + item per (supplier, produk) dengan
  `reorder_quantity` dari `StockPlanningService`; **tidak ada** mutasi stok.
- **Given** generate dipanggil ulang untuk window+supplier sama, **Then** idempotent
  (tidak ada plan ganda).
- **Given** plan `draft`, **When** `.../approve` lalu `.../execute`, **Then** PO draft
  tercatat dengan referensi supplier + item, status → `executed`.
- **Given** plan belum approved, **When** execute, **Then** 422.
- **Given** produk tanpa supplier / data kurang, **Then** item ditandai
  `data_sufficiency=insufficient` dan tidak dieksekusi.

### AC-5 — Forecast calibration
- **Given** data historis ≥ `MIN_DATA_POINTS`, **When** kalibrasi dihitung,
  **Then** faktor bias/seasonality tersimpan di `forecast_calibrations` dengan
  `method_version`, dan forecast terkalibrasi = forecast dasar × faktor.
- **Given** data kurang, **Then** `fallback=true`, faktor netral (1.0), tanpa error.
- **Given** input sama, **Then** hasil deterministik.

### AC-6 — A/B experiment & revenue lift
- **Given** `experiment_key` + `subject_key`, **When** assignment diminta, **Then** bucket
  deterministik (`hash`) → grup `control`/`treatment` konsisten untuk input sama.
- **Given** eksperimen berjalan dengan data order, **When** revenue lift dihitung,
  **Then** `revenue_lift_snapshots` menyimpan uplift (treatment vs control) dengan
  `method_version`, sampel, dan status `insufficient-data` bila sampel < minimum.
- **Given** tidak ada data, **Then** status `insufficient-data` (bukan 0/perfect score).

### AC-7 — ML/LLM adapter seam + guardrail
- **Given** config default (tanpa ML/LLM), **When** rekomendasi diminta, **Then** adapter
  deterministik dipakai dan seluruh AC lain tetap lulus.
- **Given** adapter eksternal diaktifkan tetapi gagal/timeout, **Then** sistem jatuh ke
  fallback deterministik (`fallback=true`) tanpa error 500.
- **Given** adapter mengembalikan output tak sesuai skema, **Then** ditolak + fallback.
- **Given** adapter mana pun, **When** dipanggil, **Then** **tidak ada** mutasi state
  bisnis (order/promo/PO) — hanya saran.

### AC-8 — Frontend action surfaces
- **Given** admin, **When** membuka inbox approval, **Then** daftar action `draft`/`pending`
  tampil dengan aksi approve/reject (kontrak admin-table: sort/paging/ringkasan).
- **Given** admin, **When** membuka halaman replenishment, **Then** dapat generate/approve/execute
  dengan status jelas.
- **Given** admin, **When** membuka dashboard eksperimen, **Then** revenue lift + status
  sampel tampil (termasuk `insufficient-data`).

### AC-9 — Parity & otorisasi
- **Given** dummy mode ON, **When** membuka semua halaman baru, **Then** zero network dan
  data dummy tampil (tidak ada state kosong).
- **Given** role tanpa izin, **When** akses endpoint/menu baru, **Then** 403 / menu tidak
  tampil; `platform_owner` setara admin.

### AC-10 — Audit & observability
- **Given** setiap transisi (draft/approve/reject/execute/fail), **When** terjadi,
  **Then** satu baris `recommendation_action_events` tercatat (append-only) dengan actor +
  timestamp + metadata.
- **Given** respons action, **Then** menyertakan `method`/`method_version`/`fallback`/
  `data_sufficiency` agar keputusan dapat ditelusuri.

---

## Open Questions / Assumptions

- **A-1:** Ambang minimum sampel untuk revenue lift/eksperimen = **30** (selaras
  `WINDOW_DAYS`/`MIN_DATA_POINTS`); dapat dijadikan config.
- **A-2:** "Execute" replenishment hanya **mencatat draft PO/reorder request** (belum
  integrasi ERP/supplier eksternal) — integrasi nyata menyusul di Phase 10.
- **A-3:** Tidak ada layanan ML/LLM yang tersedia di lingkungan saat ini; adapter eksternal
  disiapkan sebagai *seam* dan diuji lewat fake/stub, bukan layanan nyata.
- **A-4:** Audit disimpan di DB (bukan object storage); retensi mengikuti kebijakan umum.
- **A-5:** Kalibrasi awal = faktor bias sederhana (rasio actual/forecast historis);
  seasonality lanjutan di luar scope MVP.
- **A-6:** Eksperimen tidak mengubah routing pengguna secara otomatis di MVP — hanya
  assignment + pengukuran; penerapan perlakuan tetap lewat action approval.

---

## Rollback Plan

- Semua migrasi **additive + `down()` reversible**; rollback = `migrate:rollback`
  (per-step). Tidak ada perubahan destruktif pada tabel lama.
- Feature **kill-switch**: config `ai_actions.enabled` + `ml_adapter.driver=deterministic`
  mematikan action layer dan memaksa fallback deterministik tanpa deploy ulang.
- Tidak ada perubahan pada jalur order/promo inti — rollback Phase 9 tidak memengaruhi
  order/promo yang sudah ada; baris action/audit tetap sebagai riwayat.
- Halaman frontend baru dapat disembunyikan dengan menghapus `NavItem` + menu RBAC.

---

## Dependencies

- **Phase 3 (Data Intelligence Foundation)** — `RecommendationService`, `ForecastService`,
  `StockPlanningService`, `MeasurementService`, `DataPipelineService`,
  `ActiveDataSnapshotReader`, `RecommendationEvent`, `forecast_actuals`. **Prasyarat keras.**
- **Phase 7 (Order/Promo lifecycle)** — `OrderCreationService` (idempotency),
  `PromotionService` (overlap + audit), `OrderController::runApprovalTransaction` (pola approval).
- **Phase 8 (RBAC menu matrix, dummy parity patterns)** — `MenuDefinition::CATALOG`,
  `RbacMatrixSeeder`, `withDummyRead`.
- **Tidak ada dependency baru eksternal** yang wajib; adapter ML/LLM bersifat opsional.
