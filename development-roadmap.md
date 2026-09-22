# Development Roadmap - Digital Distribution Management Platform

## Product Vision

Build a digital distribution ecosystem that connects FMCG suppliers,
distributors, sales teams, and thousands of retail outlets/warungs
through a single platform.

The objective is to transform traditional FMCG distribution into a
data-driven business by utilizing:

-   Outlet network
-   Digital ordering
-   Sales intelligence
-   Operational automation
-   AI-based recommendation and forecasting

## Implementation Status Audit (2026-09-21)

Roadmap **tetap belum 100% terimplementasi**. Sejak audit 2026-09-14, enam plan tambahan selesai: Business Validation Production Pilot (pre-pilot compatibility & readiness, 6 tasks T1→T6, `DONE`), Concierge Production Pilot Phase 6 (3 services pilot + TDD, `DONE`), Dummy Mode Jabodetabek (12 tasks, `DONE`, closeout), **Phase 7 — MVP Completion & Core Operations (9 tasks T1→T9, `DONE`)** yang menutup gap role management, outlet lifecycle, price/promotion, sales order collection, sales quota/performance, dan WhatsApp promotion broadcast, hardening UX **Admin Table Readability (17 tasks, `DONE`, closeout)** untuk sort/paging/ringkasan/density di seluruh tabel admin, **RBAC Menu Matrix (12 tasks T1→T12, `DONE`, closeout)** yang mengikat 19 menu × 7 role ke middleware `rbac:<menu>:<level>` di semua route terlindungi plus halaman editor `/admin/rbac`, dan **Analytics Deeper Insight (7 tasks T1→T7, `DONE`, closeout)** yang menambah `AnalyticsService::insight()` + `GET /analytics/insight` (perbandingan 30 hari vs 30 hari sebelumnya, delta, needs-attention) sebagai strategic home Analitik. Data Intelligence, operational wall (readiness pipeline), infrastruktur pilot/pengukuran, administrasi MVP, otorisasi menu, dan insight strategis kini memiliki rangkaian penutup yang lengkap. Sisa pekerjaan adalah validasi lapangan sebenarnya (pilot produksi dengan partner/outlet nyata) dan sisa fase 8–10.

Legend: `[x]` implemented · `[~]` partial/MVP/foundation · `[ ]` not implemented · `[?]` outcome belum terverifikasi.

Detail checklist per fitur tersedia di [`checklist.md`](checklist.md). Closeout terbaru:

- [`Operational Readiness`](docs/pocket/plans/2026-09-10-operational-readiness/closeout.md)
- [`Phase 3 Data Intelligence Foundation`](docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/closeout.md)
- [`Business Validation — Pre-Pilot Compatibility & Readiness`](docs/pocket/plans/2026-09-14-business-validation-production-pilot/closeout.md)
- [`Concierge Production Pilot — Phase 6 Business Validation`](docs/pocket/plans/2026-09-15-concierge-production-pilot/closeout.md)
- [`Dummy Mode Jabodetabek`](docs/pocket/plans/2026-02-14-dummy-mode-jabodetabek/closeout.md)
- [`Admin Table Readability (Paging, Sort, Ringkasan, Kepadatan)`](docs/pocket/plans/2026-09-17-admin-table-ux/closeout.md)
- [`Phase 7 — MVP Completion & Core Operations`](docs/pocket/plans/2026-09-16-phase7-mvp-completion/execution-plan/index.md) (log `DONE`; 9 tasks, belum ada file closeout terpisah)
- [`RBAC Menu Matrix (19 menu × 7 role)`](docs/pocket/plans/2026-09-18-rbac-menu-matrix/closeout.md)
- [`Analytics Deeper Insight (30d vs 30d, delta, needs-attention)`](docs/pocket/plans/2026-09-18-analytics-deeper-insight/closeout.md)

Ringkasan:

- Phase 0 — Business Validation & Planning: `[~]` dokumen ada, tetapi riset bisnis, BRD, dan partner database belum terbukti. Sejak saat itu, **platform readiness sudah dibuktikan** oleh `2026-09-14-business-validation-production-pilot` (T1 baseline `docs/pilot/pre-pilot-compatibility-baseline.md` + compatibility gate `docs/pilot/pre-pilot-runbook.md`, `READY_FOR_PILOT=allow`—record `OperationalEvent`/`operational_events` hanya append, tanpa mutasi sumber transaksi), dan **Phase 6 Business Validation tervalidasi secara teknikal** oleh `2026-09-15-concierge-production-pilot` (T1 `PilotQualificationService` qualification 10-outlet/30d, T2 `PilotMetricsService` lifecycle `measureLifecycleTiming`→`calculateSpeedDelta` + guardrails `error<5% / delivery>95% / payment>90%`, T3 `PilotEvaluationService` decision matrix scale-up/iterate/stop + `phase7Evidence`). Validasi **lapangan yang sesungguhnya** (partner & order nyata) belum dieksekusi.
- Phase 1 — MVP Platform: `[x]` **DONE** — core flow berjalan dan gap administrasi ditutup oleh Phase 7: `platform_owner` + `UserPolicy` + role management API/UI, RBAC menu matrix (19 menu × 7 role), outlet category/scoring/purchase-history, product price management + history, promotion CRUD, dan dashboard admin. Sisa: verifikasi adoption bisnis (`[?]`).
- Phase 2 — Sales & Distribution Automation: `[~]` invoice, reminder, payment, delivery, sales visit, WhatsApp order, sales order collection, sales quota/performance dashboard, dan WhatsApp promotion broadcast tersedia. Yang belum: PWA offline, GPS/live tracking, driver roster, route optimization, dan proof of delivery foto/tanda tangan (Phase 8).
- Phase 3 — Data Intelligence & AI: `[x]` **DONE** — foundation selesai: pipeline, geographic/supplier BI, stock planning, measurement, admin UI/map, atomic publication, dan strategic insight comparison (`/analytics/insight`, plan `2026-09-18-analytics-deeper-insight`) tersedia; ML/LLM production dan action workflow tetap `[ ]` out-of-scope Phase 3.
- Phase 4 — Ecosystem Expansion: `[~]` marketplace dasar ada; dynamic pricing, financial services, distributor network, dan automated replenishment belum tersedia.
- Phase 7 — MVP Completion & Core Operations: `[x]` **DONE** — 9 tasks (`1858ff4..fd7ee8d`) mencakup F1 role management/`platform_owner`, F2 outlet lifecycle, F3 price management, F4 promotion management, F5 sales order collection & performance, F6 WhatsApp promotion broadcast, plus halaman admin/sales; ditutup dengan Admin Table UX (17 tasks) dan RBAC Menu Matrix (12 tasks).

------------------------------------------------------------------------

# Phase 0 - Business Validation & Planning — `[~] PARTIAL`

## Objective

Validate business model, target users, and operational process before
development.

## Activities

### Business Process Mapping

-   Current distribution workflow
-   Supplier relationship process
-   Outlet ordering behavior
-   Payment process
-   Delivery process

### User Research

Target users: - Business owner - Supplier/principal - Sales
representative - Warung/store owner - Delivery team

### Output

-   Business requirement document — `[ ]` belum ditemukan
-   Product roadmap — `[x]` tersedia di file ini
-   MVP scope — `[x]` tersedia di `docs/pocket/spec/` dan execution plan
-   Initial partner database — `[ ]` belum tersedia; fixture bukan database partner produksi

**Current status:** `[~] PARTIAL`. Process mapping dan user research belum memiliki bukti validasi lapangan.

------------------------------------------------------------------------

# Phase 1 - MVP Platform Development — `[x] DONE`

## Objective

Build the first platform version to manage distribution operations.

## Main Features

## 1. User Management

Roles:

-   Platform Owner
-   Admin
-   Sales
-   Supplier
-   Outlet Partner

Roles: Platform Owner (`[x]`), Admin, Sales, Supplier, Outlet Partner, Finance (`[x]`).

Features: - Login `[x]` - Role management `[x]` (`UserPolicy`, `UserRoleService`, `UserRoleController`, audit `RoleAssignmentAudit`, JWT invalidation) - User profile `[x]` (`GET`/`PATCH /auth/me`, `UpdateProfileRequest`) - RBAC menu matrix `[x]` (`menu_definitions`/`role_menu_access`, middleware `rbac:<menu>:<level>` di semua route terlindungi, `GET`/`PUT /admin/rbac/matrix`, map di `GET /auth/me`, halaman `/admin/rbac`; plan `2026-09-18-rbac-menu-matrix`)

------------------------------------------------------------------------

## 2. Outlet Management

Features:

-   Outlet registration — `[x]`
-   Outlet profile — `[x]` admin list/update via `AdminOutletController`
-   Location mapping — `[~]` lat/long + territory/map data tersedia; belum ada map picker khusus outlet
-   Outlet category — `[x]` enum `warung|minimarket|supermarket|grosir|restoran|kafe|toko_kelontong|lainnya`, default `lainnya`
-   Purchase history — `[x]` `GET /admin/outlets/{id}/orders` + `summary`
-   Outlet scoring — `[x]` `OutletScoringService` (volume/frequency/recency), recalculation saat order `Delivered`

Goal:

Create digital database of retail network.

------------------------------------------------------------------------

## 3. Product Catalog

Features:

-   Product master — `[x]`
-   SKU management — `[x]`
-   Price management — `[x]` `AdminProductController` + `ProductPriceService` + `product_price_histories`
-   Promotion — `[x]` `PromotionController`/`PromotionService` (CRUD, overlap guard, min_order, snapshot saat order, immutable setelah broadcast)
-   Product availability — `[x]`

------------------------------------------------------------------------

## 4. Order Management

Features:

-   Outlet ordering — `[x]`
-   Order approval — `[x]`
-   Order status tracking — `[x]`
-   Order history — `[x]`
-   Sales order collection — `[x]` `POST /sales/orders` (territory-scoped, reuse `OrderCreationService`, mencatat `sales_user_id`)

Flow:

    Outlet
      |
    Order
      |
    Admin Approval
      |
    Warehouse
      |
    Delivery
      |
    Completed

------------------------------------------------------------------------

## 5. Dashboard Basic Analytics

Metrics:

-   Total outlets — `[x]`
-   Active outlets — `[x]`
-   Sales value — `[x]`
-   Order volume — `[x]`
-   Product performance — `[~]` metrik/count dan ranking dasar tersedia
-   Admin tables — `[x]` sort, offset paging, ringkasan, timestamp, dan density toggle di outlets/products/users/promotions/sales-performance/orders (plan `2026-09-17-admin-table-ux`)

------------------------------------------------------------------------

## Phase 1 Success Criteria

Target:

-   500 registered outlets — `[?]` belum terbukti sebagai adoption produksi
-   100 active ordering outlets — `[?]` belum terbukti sebagai adoption produksi
-   5-10 supplier partners — `[?]` belum terbukti sebagai partner aktif produksi

**Current status:** `[x] DONE` (implementasi). Core register → browse → order → approval flow berjalan dan gap administrasi (role management, outlet category/scoring/history, price/promotion management, dashboard admin) telah ditutup oleh Phase 7 (`2026-09-16-phase7-mvp-completion`), hardening tabel admin (`2026-09-17-admin-table-ux`), dan RBAC menu matrix (`2026-09-18-rbac-menu-matrix`). Target adoption bisnis tetap `[?]`.

------------------------------------------------------------------------

# Phase 2 - Sales & Distribution Automation — `[~] PARTIAL`

## Objective

Improve field execution and operational efficiency.

## Features

------------------------------------------------------------------------

# Sales Force Application

Features:

-   Sales visit planning — `[x]`
-   Outlet visit tracking — `[~]` status/notes tersedia; GPS/check-in telemetry belum ada
-   Order collection — `[x]` `SalesOrderController` (territory-scoped)
-   Sales target — `[x]` `SalesTargetController` + `sales_targets` (periode `YYYY-MM`)
-   Performance dashboard — `[x]` `SalesPerformanceController` + halaman sales/admin

------------------------------------------------------------------------

# Delivery Management

Features:

-   Delivery planning — `[x]`
-   Driver management — `[~]` assignment/validasi driver ada; driver roster CRUD belum ada
-   Route planning — `[~]` `RoutingService`/`route_data` sebagai seam MVP; optimasi penuh belum ada
-   Delivery confirmation — `[x]`
-   Proof of delivery — `[~]` recipient name + proof URL; foto/tanda tangan belum ada

------------------------------------------------------------------------

# Payment Management

Features:

-   Invoice tracking — `[x]` invoice otomatis dari order confirmed, status, dan riwayat berbasis role
-   Outstanding payment — `[x]`
-   Credit limit — `[x]` dengan enforcement saat submit order
-   Payment reminder — `[x]` scheduler, state machine, retry/idempotency, dan metrik reminder

------------------------------------------------------------------------

# WhatsApp Integration

Features:

-   Product catalog via WhatsApp — `[x]`
-   Order via WhatsApp — `[x]` (signed webhook)
-   Automated notification — `[x]`
-   Promotion broadcast — `[x]` `PromotionBroadcastController` + `promo_broadcast` message type, targeting outlet dengan order 30d terakhir, idempotency `promo-broadcast:{promo_id}`

------------------------------------------------------------------------

## Phase 2 Success Criteria

Target:

-   1,000+ active outlets — `[?]` belum terbukti sebagai adoption produksi
-   Digital ordering adoption \>70% — `[?]` belum ada measurement
-   Reduced manual order processing — `[?]` belum ada measurement

**Current status:** `[~] PARTIAL`. Operational readiness untuk invoice, payment, finance role, reminder, dan delivery proof sudah selesai; sales order collection, sales target/performance dashboard, dan promotion broadcast telah ditambahkan oleh Phase 7. Yang masih belum tersedia adalah PWA/offline, GPS/live tracking, driver roster, route optimization, dan proof of delivery foto/tanda tangan (dijadwalkan Phase 8).

------------------------------------------------------------------------

# Phase 3 - Data Intelligence & AI Capability — `[x] FOUNDATION COMPLETE`

## Objective

Transform distribution data into business intelligence.

------------------------------------------------------------------------

# AI Recommendation Engine

Purpose:

Increase sales opportunity.

Capability:

-   Product recommendation
-   Cross-selling suggestion
-   Outlet behavior analysis

Example:

    Outlet A usually buys:
    - Coffee
    - Sugar

    Recommendation:
    Add snack product X

------------------------------------------------------------------------

# Sales Forecasting

Purpose:

Predict demand.

Capability:

-   Outlet order prediction
-   Product demand forecast
-   Stock planning

------------------------------------------------------------------------

# Outlet Intelligence

Capability:

Automatic segmentation:

-   High potential outlet
-   Growth outlet
-   Declining outlet

------------------------------------------------------------------------

# Business Intelligence Dashboard

Features:

-   Sales trend — `[x]`
-   Geographic analysis — `[x]` territory, coverage, dan map data
-   Product performance — `[x]` melalui snapshot data intelligence
-   Supplier performance — `[x]` fulfillment, lead time, coverage, dan revenue
-   Measurement — `[x]` recommendation funnel dan forecast accuracy/WAPE
-   Strategic insight comparison — `[x]` `AnalyticsService::insight()` + `GET /analytics/insight` (fixed 30d vs previous 30d, delta per metrik, needs-attention outlet decline ≥20%/outstanding; plan `2026-09-18-analytics-deeper-insight`)

------------------------------------------------------------------------

**Current status:** `[~] FOUNDATION COMPLETE`. Recommendation dengan sparse fallback, deterministic forecasting, segmentation, geographic BI, supplier BI, stock planning, measurement, pipeline snapshot, admin UI/map, dan atomic publication sudah tersedia. Dedicated action workflow, Python ML/LLM integration, dan verifikasi target akurasi/acceptance bisnis masih belum tersedia.

------------------------------------------------------------------------

# Phase 4 - Ecosystem Expansion — `[~] PARTIAL`

## Objective

Become a digital FMCG distribution ecosystem.

------------------------------------------------------------------------

## Advanced Features

### Marketplace Model

Connect:

-   Multiple suppliers
-   Multiple distributors
-   Thousands of outlets

------------------------------------------------------------------------

### Dynamic Pricing

AI-based:

-   Promotion recommendation
-   Pricing optimization
-   Demand-based strategy

------------------------------------------------------------------------

### Financial Services Integration

Potential:

-   Digital payment
-   Outlet credit scoring
-   Working capital partnership

------------------------------------------------------------------------

### Predictive Supply Chain

Capability:

-   Demand prediction — `[x]` forecasting dasar tersedia
-   Inventory optimization — `[~]` bounded stock planning melalui reorder point dan safety stock tersedia; optimasi lintas gudang belum ada
-   Automated replenishment — `[ ]` eksekusi ke supplier atau purchase order belum tersedia

**Current status:** `[~] PARTIAL`. Marketplace multi-supplier dasar tersedia, tetapi distributor network, dynamic pricing, financial services, dan automated replenishment belum diimplementasikan.

------------------------------------------------------------------------

# Remaining Roadmap — Fase Implementasi Berikutnya

## Phase 6 — Business Validation & Production Pilot

Status (2026-09-18): **teknikal DONE, validasi lapangan tertunda (deferred)** — lihat dua closeout Phase 6 di atas. Phase 7 (`2026-09-16-phase7-mvp-completion`) sudah menindaklanjuti `phase7Evidence` dari `PilotEvaluationService` dengan menutup gap MVP.

**Apa yang sudah dilakukan (terbukti di repo):**

- **Pre-pilot platform readiness:** `docs/pilot/pre-pilot-compatibility-baseline.md`, `docs/pilot/pre-pilot-runbook.md`, `docs/pilot/pre-pilot-gate-checklist.md`, `docs/pilot/pre-pilot-compatibility-matrix.md`, `config/ops_events.php`, `OperationalEvent`/`operational_events` append-only, `PrePilotFeatureGate`/`AttachCorrelationId`, `OperationalReadinessService`+`OperationalIssueService` (plan `docs/pocket/plans/2026-09-14-business-validation-production-pilot/closeout.md`, 6 tasks `476f437..c77c0d5`, gate `READY_FOR_PILOT`).
- **Concierge pilot instrumentation:** T1 `apps/api/app/Services/PilotQualificationService.php` + `apps/api/tests/Feature/PilotQualificationTest.php` (threshold 10 outlet/30 hari, PIC 08:00–17:00, docs+internet checks), T2 `apps/api/app/Services/PilotMetricsService.php` + `apps/api/tests/Feature/PilotWorkflowTest.php` + `apps/api/tests/Support/PilotWorkflowFixtures.php` (lifecycle New→Confirmed→Delivered→Invoiced→Paid, `measureLifecycleTiming`→`calculateSpeedDelta` target −30%, guardrails error/delivery/payment, volume `countValidOrders` exclude cancelled/bump +3d when <20), T3 `apps/api/app/Services/PilotEvaluationService.php` + `apps/api/tests/Feature/PilotEvaluationTest.php` (decision `SCALE_UP|ITERATE|STOP`, `phase7Evidence`, `lessonsLearned`, `rootCauseAnalysis`, `recommendations`) — 24 uji `Pilot*` PASS — lihat `docs/pocket/plans/2026-09-15-concierge-production-pilot/closeout.md` (3 tasks `71b6b15..6598ce0`). `OperationalEventService` hanya ditambah `extraMetadata`+`getEventsByCorrelation`/`getPilotEvents` (tidak ada migrasi skema).
- **Runbook & pengukuran:** pilot 1 partner / 1 territory, 1 minggu +3 hari extend, min 20 order valid, KPI −30% time vs baseline (48j→~4j = −91.7%), guardrails error<5%/delivery>95%/payment>90%.

**Apa yang masih tertunda:**

- Supplier relationship process mapping, outlet ordering research, interview lintas fungsi, BRD, dan partner database produksi.
- AWS deployment, object storage, backup/restore, secrets management, dan production observability.
- Pilot produksi sesungguhnya dengan partner/outlet nyata untuk menghasilkan KPI dari transaksi live (bukan `DataSeeder`/fixture).

**Exit criteria (belum penuh):** satu partner pilot aktif yang menjalankan order nyata, proses bisnis terdokumentasi, production runbook diperbarui dengan hasil lapangan, serta KPI baseline (active outlet, digital ordering, processing time, payment collection) terukur dari data live.

## Phase 7 — MVP Completion & Core Operations

Status (2026-09-18): **DONE** — plan `docs/pocket/plans/2026-09-16-phase7-mvp-completion` (log `DONE`, 9 tasks `T1..T9`, SHA `1858ff4..fd7ee8d`).

**Tujuan:** menutup gap MVP yang menghambat administrasi, outlet lifecycle, dan operasi order.

**Scope & bukti:**

- **F1 Role management & Platform Owner** — `apps/api/app/Policies/UserPolicy.php`, `apps/api/app/Services/UserRoleService.php`, `apps/api/app/Http/Controllers/UserRoleController.php`, `PATCH /auth/me`; `platform_owner` superset admin; audit via `RoleAssignmentAudit`; JWT invalidation saat role berubah.
- **F2 Outlet lifecycle** — `AdminOutletController`, `UpdateOutletRequest`, `OutletScoringService`, kategori enum + `score` (migrasi `2026_09_16_000003`), purchase history & summary endpoint.
- **F3 Product price management** — `AdminProductController`, `ProductPriceService`, model `ProductPriceHistory`, tabel `product_price_histories` (migrasi `..._000004`), price snapshot pada order.
- **F4 Promotion management** — `Promotion` model + `PromotionController` + `PromotionService` + `Store/UpdatePromotionRequest`, tabel `promotions` (migrasi `..._000007`), overlap guard, `min_order`, snapshot saat order creation, immutable setelah broadcast.
- **F5 Sales order collection & performance** — `SalesOrderController`, `SalesTargetController`, `SalesPerformanceController`, `SalesPerformanceService`, model `SalesTarget` + tabel `sales_targets` (migrasi `..._000008`), `territory_id` + `sales_user_id` (migrasi `..._000002`/`..._000005`), reuse `OrderCreationService`.
- **F6 WhatsApp promotion broadcast** — `PromotionBroadcastController`, `WhatsAppOutboundService` + `WhatsAppMessage` diperluas (`promo_broadcast`), idempotency + retry.
- **Frontend** — halaman `admin/users`, `admin/outlets`, `admin/products`, `admin/promotions`, `admin/sales-performance`, `admin/orders`, `sales/orders`, `sales/performance`.
- **Tests** — `RoleManagementTest`, `AdminOutletTest`, `OutletScoringTest`, `AdminProductPriceTest`, `PromotionTest`, `PromotionBroadcastTest`, `SalesOrderTest`, `SalesPerformanceTest`, plus Jest page tests.

**Exit criteria:** tercapai — admin dapat mengelola role, outlet, harga, promosi, dan target sales; seluruh perubahan memiliki authorization dan audit yang sesuai. Verifikasi adoption produksi tetap `[?]`.

## Phase 7.5 — Admin Table Readability (hardening, non-roadmap)

Status (2026-09-18): **DONE** — plan `docs/pocket/plans/2026-09-17-admin-table-ux` (closeout, 17 tasks `T1..T17`, SHA `cb54e09..c1b8053`). Menambahkan helper backend `ListQuery` (sort allowlist, offset cursor, total/summary) dan frontend `admin-table.ts` + komponen `TablePagination`/`TableSummary`/`TableDensityToggle`, diterapkan ke seluruh tabel admin dengan dummy-mode parity.

## Phase 7.6 — RBAC Menu Matrix (hardening, non-roadmap)

Status (2026-09-19): **DONE** — plan `docs/pocket/plans/2026-09-18-rbac-menu-matrix` (closeout, 12 tasks `T1..T12`, SHA `9cb3c61..422a261`). Menambahkan skema `menu_definitions`/`role_menu_access` (19 menu × 77 sel non-`none`), middleware `App\Http\Middleware\Rbac` (`rbac:<menu>:<level>`, ordinal `none<read<edit`) di semua route terlindungi, endpoint `GET`/`PUT /admin/rbac/matrix` + map `data.rbac` di `GET /auth/me`, `useRbacStore` + Sidebar matrix-driven, dan halaman editor `/admin/rbac` dengan dummy parity.

## Phase 7.7 — Analytics Deeper Insight (hardening, non-roadmap)

Status (2026-09-18): **DONE** — plan `docs/pocket/plans/2026-09-18-analytics-deeper-insight` (closeout, 7 tasks `T1..T7`, SHA `63e5d75..065bf3b`). Menambahkan `AnalyticsService::insight()` + `GET /api/analytics/insight` (gate `isAdminOrOwner`; window fixed 30d vs 30d sebelumnya, delta per metrik pada jalur integer-cents, `needs_attention` outlet dengan sales decline ≥20% atau outstanding > 0), rebuild halaman `/analytics` menjadi strategic home (MetricStrip + delta chip, trend chart, top-outlet ranking, NeedsAttention strip, trust label), dan nav `adminOnly` dengan `platform_owner` setara admin. Out-of-scope: ML/LLM narrative, date-range picker, rebuild `/data-intelligence`.

## Phase 8 — Mobile Field Operations

**Tujuan:** mendukung sales dan driver di lapangan dengan workflow mobile yang dapat diandalkan.

**Scope:**

- PWA/mobile sales application dengan offline order.
- GPS check-in/check-out dan visit telemetry.
- Driver roster CRUD.
- Route optimization dan live delivery tracking.
- Proof of delivery foto dan tanda tangan.

**Exit criteria:** sales dapat mengunjungi outlet dan mengumpulkan order tanpa koneksi stabil; driver menyelesaikan rute dengan status dan proof of delivery yang dapat diaudit.

## Phase 9 — AI Action & Supply Chain

**Tujuan:** mengubah insight menjadi tindakan operasional yang terukur.

**Scope:**

- Python ML service dan ML pipeline.
- LLM integration hanya untuk use case yang memiliki guardrail dan fallback.
- Promotion/recommendation engine yang menghasilkan draft campaign atau draft order.
- Approval workflow untuk recommendation dan stock plan.
- Automated replenishment ke supplier atau purchase order.
- Forecast calibration, A/B test, recommendation acceptance, dan revenue-lift monitoring.

**Exit criteria:** rekomendasi dapat diterima/ditolak dan dilacak dampaknya; replenishment memiliki approval, idempotency, dan audit trail; akurasi forecast diukur dari data produksi.

## Phase 10 — Commercial Ecosystem & Financial Services

**Tujuan:** memperluas platform dari distributor tunggal menjadi ecosystem yang aman dan dapat dimonetisasi.

**Scope:**

- Multi-distributor workflow dan tenant isolation.
- Promotion recommendation engine, pricing optimization, dan demand-based pricing.
- External digital payment integration.
- Outlet credit scoring.
- Working-capital partnership integration.

**Exit criteria:** setiap distributor terisolasi secara data dan authorization; pricing memiliki guardrail; integrasi pembayaran/financing memiliki reconciliation, audit, dan kontrol risiko.

**Urutan dependensi:** Phase 6 → Phase 7 → Phase 8 → Phase 9 → Phase 10. Phase 6 (teknikal) dan Phase 7 sudah `DONE`; fase berikutnya adalah Phase 8 (Mobile Field Operations). Phase 9 dapat dimulai setelah data pilot stabil, sedangkan Phase 10 membutuhkan security, legal, dan operational readiness yang telah disetujui.

------------------------------------------------------------------------

# Recommended Technical Architecture

## Frontend

-   Phase 1: Next.js Web Application — `[x]` implemented di `apps/web/`
-   Phase 2: Progressive Web App (PWA) — `[~]` `apps/web/src/app/manifest.ts` (standalone display, icon 192/512) tersedia; service worker/offline caching belum ada
-   Phase 2: Mobile Sales Application — `[ ]` belum tersedia

------------------------------------------------------------------------

## Backend

Recommended:

-   Laravel / NestJS
-   REST API Architecture

------------------------------------------------------------------------

## Database

Primary:

-   PostgreSQL

Supporting:

-   Redis Cache
-   Object Storage

------------------------------------------------------------------------

## Cloud Infrastructure

Recommended:

-   AWS
-   Container-based deployment
-   CI/CD pipeline

------------------------------------------------------------------------

## AI Infrastructure

Phase 3:

-   Python ML Service — `[ ]` belum tersedia; AI saat ini berupa deterministic PHP services
-   Machine Learning Pipeline — `[ ]` belum tersedia
-   LLM Integration — `[ ]` belum tersedia
-   Analytics Platform — `[x]` snapshot-based data intelligence pipeline dan in-application analytics tersedia; platform analytics eksternal belum ada

**Architecture status:** `[~] PARTIAL`. Laravel REST API, PostgreSQL, Docker, CI/CD, dan data intelligence foundation tersedia; object storage, AWS deployment, Python ML/LLM stack, serta production observability belum tersedia.

------------------------------------------------------------------------

# Development Team Requirement

## Initial Team (MVP)

-   Product Owner
-   Business Analyst
-   UI/UX Designer
-   Frontend Developer
-   Backend Developer
-   QA Engineer
-   DevOps Engineer

------------------------------------------------------------------------

# Long Term Vision

From:

"Traditional FMCG sales network"

Become:

"Digital Distribution Intelligence Platform"

The competitive advantage:

1.  Strong outlet network
2.  FMCG sales experience
3.  Real-time market data
4.  AI-driven decision making
5.  Efficient distribution operation
