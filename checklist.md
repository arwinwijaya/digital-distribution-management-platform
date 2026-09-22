# Roadmap Implementation Checklist

**Audit date:** 2026-09-21  
**Source roadmap:** [`development-roadmap.md`](development-roadmap.md)  
**Execution evidence:** [`docs/pocket/plans/2025-09-08-development-phasing/closeout.md`](docs/pocket/plans/2025-09-08-development-phasing/closeout.md), [`docs/pocket/plans/2026-09-10-operational-readiness/closeout.md`](docs/pocket/plans/2026-09-10-operational-readiness/closeout.md), [`docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/closeout.md`](docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/closeout.md)

## Status legend

- `[x]` Implemented and supported by code/tests.
- `[~]` Partially implemented or simplified for MVP.
- `[ ]` Not implemented or no credible evidence found.
- `[?]` Business outcome not verified by repository evidence.

## Overall status

**Roadmap belum selesai seluruhnya.** Execution plan awal, operational readiness, dan Phase 3 data intelligence sudah ditutup dengan `REVIEW_PASS`. Business Validation Production Pilot (pre-pilot compatibility & readiness) dan Concierge Production Pilot Phase 6 Business Validation (`DONE`, lihat `docs/pocket/plans/2026-09-14-business-validation-production-pilot/closeout.md` + `docs/pocket/plans/2026-09-15-concierge-production-pilot/closeout.md`) telah menambahkan infrastruktur pilot (kelayakan partner 10 outlet/30d, pengukuran lifecycle order→payment, pengawalan error<5%/delivery>95%/payment>90%, decision matrix Phase 6). RBAC menu matrix (19 menu × 7 role) telah ditutup (`docs/pocket/plans/2026-09-18-rbac-menu-matrix/closeout.md`), dan strategic insight `/analytics/insight` telah ditutup (`docs/pocket/plans/2026-09-18-analytics-deeper-insight/closeout.md`), melengkapi role management, Platform Owner, price/promotion management, sales target/dashboard, dan WhatsApp promotion broadcast pada Phase 7. Fase berikutnya adalah eksekusi lapangan sesungguhnya (partner/outlet nyata, order produksi); **Phase 8 (Mobile Field Operations) sudah memiliki spec + execution plan task-level (18 tasks, 6 phase, `PENDING`)** yang siap dieksekusi, sedangkan Phase 9–10 belum dimulai.

| Roadmap phase | Status | Ringkasan |
|---|---|---|
| Phase 0 — Business Validation & Planning | `[~]` Partial | Dokumen planning ada, tetapi validasi bisnis, riset pengguna, BRD, dan partner database belum terbukti. Pre-pilot platform readiness sudah ditutup oleh `2026-09-14-business-validation-production-pilot` (`READY_FOR_PILOT=allow`), dan Phase 6 Business Validation tervalidasi secara teknikal oleh `2026-09-15-concierge-production-pilot` (`PilotQualificationService`, `PilotMetricsService`, `PilotEvaluationService`, 24 uji `Pilot*` PASS). |
| Phase 1 — MVP Platform | `[x]` Done | Core flow berjalan; role management + RBAC menu matrix, Platform Owner, promosi, harga produk, outlet kategori/scoring/history, dan strategic insight sudah ada; sisa minor: map picker outlet dan product performance ranking. |
| Phase 2 — Sales & Distribution Automation | `[~]` Partial | Invoice, reminder, payment, delivery, sales visit, sales order collection, sales target/dashboard, dan WhatsApp order + broadcast promosi tersedia; live tracking dan GPS check-in belum ada. |
| Phase 3 — Data Intelligence & AI | `[x]` Done | Foundation sudah selesai (pipeline, geographic/supplier BI, stock planning, measurement, admin UI/map, atomic publication, strategic insight `/analytics/insight`) — lihat `docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/closeout.md` + `docs/pocket/plans/2026-09-18-analytics-deeper-insight/closeout.md`. ML/LLM production dan action workflow tetap `[ ]` out-of-scope Phase 3. |
| Phase 4 — Ecosystem Expansion | `[~]` Partial | Marketplace multi-supplier dasar tersedia; pricing, financial services, distributor network, dan automated replenishment belum tersedia. |

## Phase 0 — Business Validation & Planning

### Field/business validation (belum selesai)

- `[~]` Current distribution workflow terdokumentasi di `docs/panduan-pengguna.md` dan `docs/dokumentasi-teknis.md`; belum ada artefak observasi/validasi lapangan.
- `[ ]` Supplier relationship process mapping.
- `[ ]` Outlet ordering behavior research.
- `[~]` Payment dan delivery process terdokumentasi; belum ada bukti user research.
- `[ ]` Target-user research/interview untuk owner, supplier, sales, outlet, dan delivery team.
- `[ ]` Business requirement document.
- `[x]` Product roadmap — `development-roadmap.md`.
- `[x]` MVP scope — `docs/pocket/spec/2025-09-08-development-phasing/`.
- `[ ]` Initial partner database. Model/factory supplier dan outlet bukan bukti database partner nyata.

### Platform readiness & Phase 6 instrumentation (selesai)

- `[x]` Pre-pilot compatibility baseline — `docs/pilot/pre-pilot-compatibility-baseline.md`, `docs/pilot/pre-pilot-compatibility-matrix.md`; created by T1 `2026-09-14-business-validation-production-pilot` (`476f437`).
- `[x]` Pre-pilot controls, correlation ID, operational event append-only — `apps/api/app/Services/PrePilotFeatureGate.php`, `apps/api/app/Http/Middleware/AttachCorrelationId.php`, `apps/api/app/Models/OperationalEvent.php`, `apps/api/app/Services/OperationalEventService.php`, `apps/api/database/migrations/2026_09_15_000001_create_operational_events_table.php`; created by T3 (`a775bdd`).
- `[x]` Readiness + operational issue diagnostics API — `apps/api/app/Services/OperationalReadinessService.php`, `apps/api/app/Services/OperationalIssueService.php`, `apps/api/app/Http/Controllers/OperationalReadinessController.php`; created by T4 (`1d94bbb`).
- `[x]` Admin operational web surface — `apps/web/src/app/operations/page.tsx`, `apps/web/src/lib/operations-api.ts`, `apps/web/src/lib/operations-types.ts`; created by T5 (`847779b`).
- `[x]` Pre-pilot runbook & gate — `docs/pilot/pre-pilot-runbook.md`, `docs/pilot/pre-pilot-gate-checklist.md`; created by T6 (`c77c0d5`).
- `[x]` Partner qualification validation — `apps/api/app/Services/PilotQualificationService.php` (`evaluate`, `countQualifiedOutlets`, `checkDocumentation`, `checkPic`, `checkInternet`), threshold 10 outlet/30d; created by T1 `2026-09-15-concierge-production-pilot` (`71b6b15`).
- `[x]` Pilot metrics & KPI — `apps/api/app/Services/PilotMetricsService.php` (`measureLifecycleTiming`→`calculateSpeedDelta`, `calculateReliability`, `countValidOrders`, `evaluateVolume` +3d extend), excludes cancelled/bump events; created by T2 (`184ea92`).
- `[x]` Pilot evaluation & decision — `apps/api/app/Services/PilotEvaluationService.php` (`evaluate`→`decision/kpiSummary/guardrailsMet/phase7Evidence/lessonsLearned/rootCauseAnalysis/recommendations`), constants `ERROR_RATE_LIMIT<5`, `DELIVERY_SUCCESS_LIMIT>95`, `PAYMENT_COMPLETION_LIMIT>90`, `MIN_VALID_ORDERS=20`; created by T3 (`6598ce0`).
- `[x]` Pilot TDD tests — `apps/api/tests/Feature/PilotQualificationTest.php` (6 uji), `apps/api/tests/Feature/PilotWorkflowTest.php` (14 uji), `apps/api/tests/Feature/PilotEvaluationTest.php` (4 uji), `apps/api/tests/Support/PilotWorkflowFixtures.php`; 24 uji `Pilot*` PASS.

### Outstanding (belum selesai)

- `[ ]` AWS deployment, object storage, backup/restore, secrets management, dan production observability.
- `[ ]` Eksekusi lapangan pilot produksi sesungguhnya dengan partner/outlet nyata, minimum 20 order valid, KPI −30% time vs baseline, decision matrix → Phase 7 evidence.

## Phase 1 — MVP Platform Development

### User management

- `[x]` Login/JWT — `apps/api/app/Http/Controllers/AuthController.php`, `apps/api/tests/Feature/AuthTest.php`.
- `[x]` Role representation tersedia untuk admin, supplier, outlet, sales, driver, finance, dan platform_owner (`apps/api/app/Models/User.php` — `isPlatformOwner()`; `apps/api/database/seeders/DatabaseSeeder.php`).
- `[x]` Role management API/UI — `apps/api/app/Http/Controllers/UserRoleController.php` + `FinanceRoleController.php`, halaman `apps/web/src/app/admin/users/page.tsx`; diuji `apps/api/tests/Feature/RoleManagementTest.php`.
- `[x]` RBAC menu matrix (19 menu × 7 role) — skema `menu_definitions`/`role_menu_access`, middleware `App\Http\Middleware\Rbac` (`rbac:<menu>:<level>`) di semua route terlindungi, endpoint `GET`/`PUT /admin/rbac/matrix`, map di `GET /auth/me`, Sidebar matrix-driven, dan halaman `/admin/rbac`; lihat `docs/pocket/plans/2026-09-18-rbac-menu-matrix/closeout.md`.
- `[x]` User profile — `GET /auth/me` + `PATCH /auth/me` (`apps/api/app/Http/Controllers/AuthController.php::update`, `UpdateProfileRequest`).

### Outlet management

- `[x]` Outlet registration.
- `[x]` Outlet profile dasar tersimpan; list/update lengkap tersedia via `apps/api/app/Http/Controllers/AdminOutletController.php` (`GET /admin/outlets`, `PATCH /admin/outlets/{id}`) + halaman `apps/web/src/app/admin/outlets/page.tsx`.
- `[~]` Latitude/longitude tersedia; map UI atau mapping service belum ada.
- `[x]` Outlet category — kolom `category` (`apps/api/database/migrations/2026_09_16_000003_add_category_and_score_to_outlets_table.php`), `Outlet::VALID_CATEGORIES`/`scopeOfCategory`.
- `[x]` Purchase history — admin endpoint `GET /admin/outlets/{outletId}/orders` + `GET /admin/outlets/{outletId}/summary` (`AdminOutletController`) dan UI riwayat di `apps/web/src/app/admin/outlets/page.tsx`; outlet-self history list (halaman outlet) belum ada.
- `[x]` Outlet scoring — kolom `score` (migration yang sama), default 0.

### Product catalog

- `[x]` Product master.
- `[x]` SKU management dan SKU lookup.
- `[x]` Product price admin workflow — `apps/api/app/Http/Controllers/AdminProductController.php` (`PATCH /admin/products/{id}`, `GET /admin/products/{id}/prices`) + halaman `apps/web/src/app/admin/products/page.tsx`.
- `[x]` Promotion — `apps/api/app/Http/Controllers/PromotionController.php` + halaman `apps/web/src/app/admin/promotions/page.tsx`.
- `[x]` Product availability: `is_active`, stock, dan supplier eligibility.
- `[x]` Basic product search — `Product::scopeSearch()` dan `ProductCatalog.tsx`.

### Order management

- `[x]` Outlet ordering.
- `[x]` Admin order approval.
- `[~]` Status tracking/history tersedia; workflow warehouse/processing belum lengkap.
- `[~]` Order history tersedia untuk admin dan tracking by order ID; outlet history list belum ada.
- `[x]` Idempotency dan stock reservation.
- `[~]` End-to-end register → browse → order → approval → delivery → payment tersedia sebagai MVP flow.

### Basic analytics

- `[~]` Outlet metric tersedia sebagai outlet aktif; total vs active belum dipisahkan sesuai roadmap.
- `[x]` Sales value.
- `[x]` Order volume.
- `[~]` Product performance baru berupa metrik/count dasar; ranking performance belum tersedia.
- `[x]` Sales trends daily/weekly/monthly.
- `[x]` Outlet performance ranking.
- `[x]` Strategic insight comparison — `AnalyticsService::insight()` + `GET /api/analytics/insight` (fixed 30d vs previous 30d, delta per metrik, `needs_attention`); halaman `/analytics` dirombak menjadi strategic home; lihat `docs/pocket/plans/2026-09-18-analytics-deeper-insight/closeout.md`.

- `[?]` Target Phase 1: 500 registered outlets, 100 active ordering outlets, 5–10 suppliers belum terbukti sebagai adoption bisnis. Fixture scale bukan data produksi.

## Phase 2 — Sales & Distribution Automation

### Sales force

- `[x]` Sales visit planning — `SalesController`, `SalesVisit`, dan `apps/web/src/app/sales/page.tsx`.
- `[~]` Visit tracking/status/notes tersedia; GPS/check-in telemetry belum ada.
- `[x]` Sales-specific order collection — `apps/api/app/Http/Controllers/SalesOrderController.php` (`POST /sales/orders`), halaman `apps/web/src/app/sales/orders/page.tsx`; diuji `apps/api/tests/Feature/SalesOrderTest.php`.
- `[x]` Sales target quota/period/achievement — `apps/api/app/Http/Controllers/SalesTargetController.php` (CRUD `/admin/sales-targets`); diuji `SalesPerformanceTest.php`.
- `[x]` Sales performance dashboard — `apps/api/app/Http/Controllers/SalesPerformanceController.php` (`GET /sales/my-performance`, `GET /admin/sales/performance`), halaman `apps/web/src/app/sales/performance/page.tsx` + `apps/web/src/app/admin/sales-performance/page.tsx`.

### Delivery

- `[x]` Delivery assignment/planning.
- `[~]` Driver role/assignment/validation ada; driver roster CRUD belum ada.
- `[~]` RoutingService dan `route_data` ada sebagai MVP seam; full route optimization belum ada.
- `[x]` Delivery confirmation dan status transition.
- `[~]` Proof of delivery didukung API; UI baru mengisi recipient name dan proof URL, belum photo/signature fields.
- `[~]` Status tracking via API tersedia; real-time push/live tracking belum ada.

### Payment

- `[x]` Invoice tracking — invoice otomatis dari order confirmed, status pending/paid/overdue, dan riwayat berbasis role.
- `[x]` Outstanding payment.
- `[x]` Credit limit dan submission-time enforcement.
- `[x]` Payment recording, partial payment, receipt, dan idempotency.
- `[x]` Payment reminder — scheduler, state machine, retry/idempotency, dan metrik reminder.

### WhatsApp

- `[x]` Product catalog via WhatsApp.
- `[x]` Order via signed WhatsApp webhook.
- `[x]` Automated confirmed-order notification dengan retry/idempotency.
- `[x]` Promotion broadcast — `apps/api/app/Http/Controllers/PromotionBroadcastController.php`, route `POST /admin/promotions/{id}/broadcast`, halaman `apps/web/src/app/admin/promotions/page.tsx`.
- `[~]` Provider integration/configuration tersedia; approval, credentials, dan PostgreSQL concurrency production belum terverifikasi di environment ini.

- `[?]` Target Phase 2: 1,000+ active outlets, >70% digital ordering, dan reduced manual processing belum memiliki bukti produksi.

## Phase 3 — Data Intelligence & AI Capability

### AI recommendation

- `[x]` Product recommendation, termasuk sparse-data fallback.
- `[~]` Cross-selling heuristic tersedia; dedicated campaign/action workflow belum ada.
- `[x]` Outlet behavior analysis berbasis order history.

### Forecasting

- `[x]` Outlet order prediction.
- `[x]` Product/demand forecast dan SKU-level stock planning berbasis deterministic bounded service.
- `[x]` Reorder point, safety stock, dan replenishment recommendation tersedia; eksekusi purchase/replenishment otomatis belum ada.

### Outlet intelligence

- `[x]` Outlet segmentation: high potential/growth/declining-style segments.
- `[x]` Basic sales trend BI.
- `[x]` Geographic analysis melalui territory, outlet coverage, dan map data.
- `[x]` Product performance BI melalui data intelligence snapshot.
- `[x]` Supplier performance BI: fulfillment, lead time, coverage, dan revenue.
- `[x]` Recommendation funnel/acceptance measurement infrastructure.
- `[x]` Forecast accuracy measurement (WAPE/forecast actuals); target akurasi bisnis belum terbukti.
- `[ ]` Python ML service dan LLM integration; implementasi saat ini tetap deterministic PHP heuristics.

## Phase 4 — Ecosystem Expansion

### Marketplace

- `[~]` Multi-supplier marketplace/catalog dasar — `MarketplaceController.php`, `MarketplaceTest.php`, dan `MarketplaceCatalog.tsx`.
- `[ ]` Multiple distributor model/workflow dan tenant isolation.
- `[?]` Thousands of outlets belum terbukti sebagai network produksi.

### Dynamic pricing

- `[ ]` Promotion recommendation engine.
- `[ ]` Pricing optimization.
- `[ ]` Demand-based pricing strategy.

### Financial services

- `[ ]` External digital payment integration.
- `[ ]` Outlet credit scoring. Credit limit bukan credit scoring.
- `[ ]` Working-capital partnership integration.

### Predictive supply chain

- `[x]` Demand forecasting dasar sudah ada.
- `[~]` Inventory planning/optimization bounded tersedia melalui reorder point dan safety stock; optimasi lintas gudang/inventory aktual belum ada.
- `[ ]` Automated replenishment execution ke supplier atau purchase order.

## Recommended technical architecture

- `[x]` Next.js frontend — `apps/web/`.
- `[ ]` PWA/mobile sales application.
- `[x]` Laravel REST API — `apps/api/`.
- `[x]` PostgreSQL support/configuration.
- `[~]` Redis tersedia di Docker/config; workflow berat belum sepenuhnya memakai queue/cache.
- `[ ]` Object storage integration.
- `[x]` Containerization dan CI/CD — `docker-compose.yml`, `.github/workflows/ci.yml`.
- `[ ]` AWS infrastructure/deployment.
- `[ ]` Python ML service, ML pipeline, dan LLM integration.
- `[~]` Analytics platform masih berupa in-application Laravel aggregates, bukan platform analytics terpisah.

## Remaining Roadmap — Fase Implementasi Berikutnya

### Phase 6 — Business Validation & Production Pilot

- **Scope:** supplier/process mapping, user research, BRD, partner database, AWS, object storage, backup/restore, secrets, dan observability.
- **Exit criteria:** partner pilot aktif, production runbook tersedia, dan KPI baseline terukur.

### Phase 7 — MVP Completion & Core Operations

- **Scope:** role management, Platform Owner, outlet profile/category/scoring/history, price/promotion management, sales order collection, sales dashboard/target, dan WhatsApp promotion broadcast.
- **Exit criteria:** admin dapat mengelola role, outlet, harga, promosi, dan target sales dengan authorization serta audit yang sesuai.
- **Status:** `[x]` Done (implementasi). Role management + RBAC menu matrix, Platform Owner, price/promotion management, sales order collection, sales dashboard/target, WhatsApp promotion broadcast, outlet profile/category/scoring/history, dan strategic insight `/analytics/insight` sudah `[x]`. Verifikasi adoption produksi tetap `[?]`.

### Phase 7.5 — Admin Table Readability (hardening)

- **Status:** `[x]` Done — `docs/pocket/plans/2026-09-17-admin-table-ux/closeout.md` (17 tasks). Helper `ListQuery` + `admin-table.ts` + `TablePagination`/`TableSummary`/`TableDensityToggle` di seluruh tabel admin.

### Phase 7.6 — RBAC Menu Matrix (hardening)

- **Status:** `[x]` Done — `docs/pocket/plans/2026-09-18-rbac-menu-matrix/closeout.md` (12 tasks). 19 menu × 7 role, middleware `rbac:<menu>:<level>`, editor `/admin/rbac`.

### Phase 7.7 — Analytics Deeper Insight (hardening)

- **Status:** `[x]` Done — `docs/pocket/plans/2026-09-18-analytics-deeper-insight/closeout.md` (7 tasks). `AnalyticsService::insight()` + `GET /api/analytics/insight` + rebuild `/analytics`.

### Phase 8 — Mobile Field Operations

- **Status:** `[ ]` Planned — spec [`phase8-mobile-field-operations.md`](docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md) + execution plan [`execution-plan/index.md`](docs/pocket/plans/2026-09-22-phase8-mobile-field-operations/execution-plan/index.md) (18 tasks `T1..T18`, 6 phase, log `PENDING`). Belum ada implementasi.
- **Scope:** PWA/mobile sales-driver, offline order, GPS check-in, driver roster, route optimization, live tracking, serta proof of delivery foto/tanda tangan.
- **Exit criteria:** sales dan driver dapat menjalankan workflow lapangan yang dapat diaudit, termasuk saat koneksi tidak stabil.

**Sub-rencana (task-level):**

- `[ ]` Phase 1 — Data foundation: T1 migrasi+skema field-ops, T2 `DriverProfile`, T3 `RoutingService` bounded, T4 RBAC menu `driver_roster`/`field_ops`.
- `[ ]` Phase 2 — Backend API: T5 roster CRUD, T6 visit check-in/out + radius, T7 location ping + track, T8 PoD upload, T9 wire routing.
- `[ ]` Phase 3 — PWA offline: T10 service worker + `/offline`, T11 antrean order + flush, T12 integrasi `OrderForm`.
- `[ ]` Phase 4 — Frontend: T13 roster admin, T14 check-in sales, T15 PoD capture, T16 live tracking.
- `[ ]` Phase 5 — T17 dummy parity + NavItem/RBAC wiring.
- `[ ]` Phase 6 — T18 integrasi lintas unit + verifikasi suite penuh.

### Phase 9 — AI Action & Supply Chain

- **Scope:** Python ML service/pipeline, guarded LLM integration, recommendation menjadi draft order/campaign, approval stock plan, automated replenishment, forecast calibration, A/B test, dan revenue-lift monitoring.
- **Exit criteria:** rekomendasi dan replenishment memiliki approval, idempotency, audit trail, dan dampak bisnis yang terukur.

### Phase 10 — Commercial Ecosystem & Financial Services

- **Scope:** multi-distributor dan tenant isolation, promotion recommendation, pricing optimization, demand-based pricing, external payment, credit scoring, dan working-capital partnership.
- **Exit criteria:** isolasi data/authorization, pricing guardrail, reconciliation pembayaran, audit, dan kontrol risiko tersedia.

**Urutan dependensi:** Phase 6 → Phase 7 → Phase 8 → Phase 9 → Phase 10. Phase 9 dapat dimulai setelah data pilot stabil; Phase 10 membutuhkan security, legal, dan operational readiness yang disetujui.

> Status `[?]` tetap berarti implementasi teknis belum cukup untuk membuktikan outcome bisnis. Fixture atau test seeder tidak boleh dianggap sebagai adoption produksi.
