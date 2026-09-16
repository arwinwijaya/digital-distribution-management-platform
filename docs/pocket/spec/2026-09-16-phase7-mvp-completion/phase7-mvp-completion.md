# Phase 7 — MVP Completion & Core Operations

**Date:** 2026-09-16
**Status:** draft
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md

---

## Summary

Tutup gap MVP yang menghambat administrasi, outlet lifecycle, dan operasi order. Setelah Phase 6 menyelesaikan infrastruktur pilot (qualification, metrics, evaluation), Phase 7 melengkapi fitur core yang belum tersedia: role management umum dengan `platform_owner`, outlet profile/category/scoring/purchase-history, product price management dengan history, promotion management penuh (CRUD + broadcast), sales order collection dengan territory scope, sales quota/performance dashboard, dan WhatsApp promotion broadcast.

Semua perubahan memiliki authorization dan audit yang sesuai. Design tetap mengikuti pola Laravel REST + JWT yang sudah ada, tanpa mengubah pipeline order/invoice/payment yang sudah berjalan.

---

## Context

### Current State

Repository adalah monorepo Laravel 11 REST API (JWT auth) + Next.js 16 dengan PostgreSQL support. Phase 1-3 dan Phase 6 sudah DONE. Namun beberapa gap MVP masih terbuka:

- **Role management:** hanya `outlet↔finance` via `FinanceRoleController`; tidak ada `platform_owner`, tidak ada user listing, tidak ada profile update.
- **Outlet:** registration dan payment terms ada; tidak ada category, scoring, admin list/update, purchase history endpoint.
- **Product:** listing, search, SKU ada; tidak ada price management, product CRUD admin, atau promotion.
- **Sales:** visit scheduling ada; sales tidak bisa membuat order untuk outlet; tidak ada quota/target atau performance dashboard.
- **WhatsApp:** webhook, catalog, notification, reminder ada; tidak ada promotion broadcast.
- **Frontend:** 14 pages; tidak ada admin CRUD pages untuk roles, outlets, products, promotions, atau sales dashboard.

Semua test Phase 1-3 dan Phase 6 PASS (termasuk 24 uji `Pilot*`).

### Problem / Motivation

Roadmap Phase 7 berstatus belum dikerjakan. Admin tidak bisa mengelola role, outlet lifecycle tidak lengkap (tanpa category/scoring), harga produk tidak bisa diubah tanpa DB access, tidak ada mekanisme promosi, sales tidak bisa membantu outlet order di lapangan, dan tidak ada visibility ke sales performance. Tanpa Phase 7, platform tidak bisa dioperasikan secara penuh oleh admin.

### Related Areas

- `apps/api/app/Models/User.php` — existing role enum (admin/supplier/outlet/sales/driver/finance), `getJWTCustomClaims`, `$fillable` includes `role`.
- `apps/api/app/Http/Controllers/AuthController.php` — login/register/logout/refresh/me endpoints.
- `apps/api/app/Http/Controllers/FinanceRoleController.php` + `apps/api/app/Services/FinanceRoleService.php` — existing role transition pattern (assign/remove with audit).
- `apps/api/app/Models/RoleAssignmentAudit.php` — existing audit trail for role changes.
- `apps/api/app/Models/Outlet.php` — outlet model (phone canonicalization, territory relation, no category/scoring).
- `apps/api/app/Http/Controllers/OutletController.php` — registration, payment terms (update lacks admin assertion — S3 gap).
- `apps/api/app/Models/Product.php` + `apps/api/app/Http/Controllers/ProductController.php` — listing, search, `scopePurchasable`.
- `apps/api/app/Http/Controllers/SalesController.php` + `apps/api/app/Models/SalesVisit.php` — visit CRUD, calendar integration.
- `apps/api/app/Services/OrderCreationService.php` — order pipeline (lock → idempotency → prepareProducts → credit check → reserve → persist). Sales orders must reuse this.
- `apps/api/app/Services/CreditLimitService.php` — `assertCanPlace` at create-time.
- `apps/api/app/Services/InvoiceService.php` — `createForApprovedOrder`, `cancelOrder` (has `firstOrFail` gap for New orders).
- `apps/api/app/Services/WhatsAppOutboundService.php` — `notifyConfirmedOrder`, `shareCatalog`, `sendInvoiceReminder`, outbound dedup via `logical_key` + `insertOrIgnore`.
- `apps/api/app/Models/WhatsAppMessage.php` — message log (no `promo_broadcast` type yet).
- `apps/api/app/Models/Territory.php` + `apps/api/app/Http/Controllers/TerritoryController.php` — territory CRUD, outlet assignment. No sales↔territory link.
- `apps/api/routes/api.php` — route layout, authz patterns (`assertAdmin`, `deny.finance`).
- `apps/api/app/Services/FinanceAuthorizationService.php` — `isAdmin`, `hasCurrentRole`.
- `apps/web/src/app/` — 14 existing pages (dashboard, orders, products, outlets, marketplace, payments, delivery, sales, analytics, invoices, admin/orders, data-intelligence, operations).
- `apps/api/tests/Feature/` — 46 test files, existing conventions (RefreshDatabase, JWT via login, JSON assertions).
- `checklist.md` and `development-roadmap.md` — Phase 7 gap evidence.

---

## Scope

### In-Scope

**F1 — Role Management:**
- `platform_owner` role (DB enum migration, superset of admin).
- General user listing (`GET /admin/users` with role filter, pagination).
- General role assignment (any role → any role, with audit via `RoleAssignmentAudit`).
- User profile update (`PATCH /auth/me` for name/email; `role` field ignored).
- JWT invalidation on role change (force re-login via refresh token invalidation).
- Central authorization policy (`UserPolicy` or gate) + regression GWT (outlet/sales/driver → 403 on all admin endpoints).
- Fix `OutletController::updatePaymentTerms` missing admin assertion.

**F2 — Outlet Profile & Lifecycle:**
- Admin outlet list (`GET /admin/outlets` with name/category/territory/is_active filters, pagination).
- Admin outlet update (`PATCH /admin/outlets/{id}`).
- Outlet category: fixed enum `[warung, minimarket, supermarket, grosir, restoran, kafe, toko_kelontong, lainnya]`, default `lainnya`, DB migration.
- Outlet scoring: auto-recalc on order `Delivered` status. Formula: volume (order count last 90d, normalized 0-100) * 0.4 + frequency (orders/week) * 0.3 + recency (days since last order, inverse) * 0.3. Score 0 for zero orders. Sales-collected orders score same as self-serve.
- Outlet purchase history: `GET /admin/outlets/{id}/orders` (paginated) + `GET /admin/outlets/{id}/summary` (total_orders, total_spend, last_order_date).

**F3 — Product Price Management:**
- Admin price update (`PATCH /admin/products/{id}` with price >= 0; price=0 allowed; supplier → 403).
- Price history: `product_price_histories` table (product_id, old_price, new_price, changed_by, changed_at) + `GET /admin/products/{id}/prices`.
- Price snapshot: order `unit_price` frozen at creation; approval does not re-price; cancel `New` order allowed without invoice.

**F4 — Promotion Management:**
- `promotions` table: id, name, description, discount_type (percentage/fixed), discount_value, max_discount (for percentage cap), product_id, min_order (pre-discount subtotal), start_date, end_date, is_active, broadcast_at, created_by.
- Promotion CRUD (admin only): `POST/GET/PATCH/DELETE /admin/promotions`.
- Single promo per product per time: overlapping dates → 422 conflict. DB-level check or serializable transaction. Timezone Asia/Jakarta; start inclusive, end inclusive 23:59:59.
- Promo applied at order creation only; no retroactive application on approval.
- Credit check: post-discount total (promo reduces credit consumption).
- Promo immutability after broadcast: editing a broadcast promo → 422 (or versioned snapshot).

**F5 — Sales Order Collection & Performance:**
- `territory_id` (nullable FK) on `users` table via migration; admin assigns sales→territory.
- Sales order endpoint: `POST /sales/orders` (or extend existing). Checks `$user->territory_id === $outlet->territory_id`; null territory_id → 403. Reuses `OrderCreationService` pipeline (outlet lock, credit check post-discount, stock reserve). Records `sales_user_id` on order.
- `sales_targets` table: id, sales_user_id, period (YYYY-MM), target_amount, created_by, timestamps.
- Sales quota CRUD (admin only): `POST/GET/PATCH/DELETE /admin/sales-targets`.
- Sales performance: `GET /sales/my-performance` (own data: target, achievement, percentage, order_count for current month). `GET /admin/sales/performance?period=YYYY-MM` (all sales, admin only).
- Quota rules: target=0 → 0% achievement (no div-zero); cancelled orders excluded; `Confirmed/Delivered/Paid/Partially Paid` count toward revenue.
- Frontend: sales order form + sales performance dashboard + admin sales dashboard.

**F6 — WhatsApp Promotion Broadcast:**
- `promo_broadcast` added to `whatsapp_messages.message_type` enum.
- Broadcast endpoint: `POST /admin/promotions/{id}/broadcast`.
- Targeting: outlets with ≥1 order in last 30d (not just `is_active` flag). Uses `scopePurchasable` to exclude inactive-supplier products.
- Idempotency: `logical_key = promo-broadcast:{promo_id}`. Double-POST → 200 `already_sent`. Provider-failure retry re-sends only `failed` messages.
- Message content: promo name, discount value, valid dates, min_order, outlet name.
- Failed delivery → status=failed, retry via existing `POST /whatsapp/messages/{messageId}/retry`.
- Broadcast to 0 eligible outlets → 200 success with count=0.

**Frontend (all groups):**
- `/admin/users` — user list with role filter + role assignment UI.
- `/admin/outlets` — outlet list with filters + edit form + scoring display + purchase history view.
- `/admin/products` — product list + price edit + price history view.
- `/admin/promotions` — promotion CRUD + broadcast trigger.
- `/sales/orders` (or extend `/sales`) — order creation form for sales (outlet selector scoped to territory).
- `/sales/performance` (or extend `/sales`) — own performance dashboard.
- `/admin/sales/performance` — admin view of all sales performance.

**Tests:** Laravel feature tests for all new behavior (following existing conventions). Frontend Jest tests for new pages where applicable.

### Out-of-Scope

- Mobile PWA / mobile sales app (Phase 8).
- GPS check-in/check-out, visit telemetry (Phase 8).
- Driver roster CRUD, route optimization, live tracking (Phase 8).
- Proof of delivery photo/signature (Phase 8).
- Python ML service, LLM integration (Phase 9).
- Recommendation approval workflow, automated replenishment (Phase 9).
- Multi-distributor workflow, tenant isolation (Phase 10).
- Dynamic pricing beyond fixed promotion rules (Phase 10).
- External digital payment, credit scoring, working capital (Phase 10).
- AWS deployment, object storage, production observability.
- Real pilot execution with partner/outlet (deferred).
- Supplier self-service price management (admin only for Phase 7).
- Promo stacking (single promo only for Phase 7).
- Voucher code system (discount %/fixed only for Phase 7).
- Bulk price update via CSV (single update + history for Phase 7; bulk is future).
- Outlet opt-out from broadcast (future enhancement).

---

## Architecture Constraints

- **Layers this work may touch:** Laravel migrations/models/services/controllers/request validation/feature tests; Next.js admin pages/components/API client; existing shared frontend types where required.
- **Layers this work must NOT touch:** Payment/invoice/delivery core logic (reuse only, no modification except: `InvoiceService::cancelOrder` New-order path fix, `OutletController::updatePaymentTerms` admin assertion fix); WhatsApp inbound webhook; pilot services (`PilotQualificationService`, `PilotMetricsService`, `PilotEvaluationService`); data intelligence pipeline; operational readiness services.
- **Patterns that must be followed:** Eloquent models and migrations; service-layer business logic; REST controllers with request validation; JWT auth with role checks at request boundary; database transactions/locks for idempotency; existing Laravel (PHPUnit/RefreshDatabase) and Jest/Next.js test conventions; string-role enum approach (NOT spatie/laravel-permission — keep current pattern); conventional commits.
- **Auth pattern:** extend existing `isAdmin()`-style helpers with `isPlatformOwner()`; `platform_owner` is superset of `admin`; every admin endpoint must assert admin-or-owner; every platform_owner-only endpoint must assert owner explicitly. Add `UserPolicy` or gate to centralize.
- **Order pipeline reuse:** sales orders MUST go through `OrderCreationService` (same lock, credit check, stock reserve, idempotency). No parallel order-creation path.
- **WhatsApp pattern:** broadcast MUST use existing `WhatsAppOutboundService` + `logical_key` dedup pattern. New `promo_broadcast` message type must be routed in `dispatchDelivery`.
- **Pagination contract:** use `{data[], meta{limit,has_more}}` with `limit+1` technique (matching `OrderController::index`) for all new list endpoints. Max limit 100.
- **Audit:** all role changes via `RoleAssignmentAudit`; price changes via `product_price_histories.changed_by`; promo CRUD and quota CRUD must log actor (created_by/updated_by or audit table); broadcast execution logs `broadcast_at` + actor.

---

## Acceptance Criteria

**Date:** 2026-09-16 | Scope confirmed: yes (full Phase 7, all 6 groups; string enum approach)

### F1 — Role Management

**Rule: Platform Owner superset**
- ✓ Given a user with role=platform_owner, When calling any admin endpoint, Then access is granted
- ✓ Given a user with role=admin, When calling platform_owner-only endpoint (role management), Then 403 forbidden
- ✓ Given a user with role=outlet, When calling any admin endpoint, Then 403 forbidden

**Rule: Role assignment with audit + JWT invalidation**
- ✓ Given admin assigns role sales→driver to user X, When checking audit log, Then from_role=sales, to_role=driver, actor=admin logged
- ✓ Given admin changes user X's role, When user X uses old refresh token, Then 401 forced re-login
- ✗ Given admin tries to assign invalid role "superuser", Then validation error returned

**Rule: User profile update**
- ✓ Given authenticated user, When calling PATCH /auth/me with name/email, Then profile updated and returned
- ✓ Given authenticated user, When calling PATCH /auth/me with role=outlet, Then 200 with role unchanged (ignored, not 422)

**Rule: User listing**
- ✓ Given admin, When calling GET /admin/users with role filter=sales, Then only sales users returned (paginated)
- ✓ Given outlet user, When calling GET /admin/users, Then 403 forbidden

### F2 — Outlet Profile & Lifecycle

**Rule: Outlet listing**
- ✓ Given admin, When calling GET /admin/outlets, Then paginated list with name, category, territory, is_active
- ✓ Given admin with filter territory_id=1, Then only outlets in territory 1 returned

**Rule: Outlet update**
- ✓ Given admin, When calling PATCH /admin/outlets/{id} with name/category/address, Then updated and returned
- ✗ Given outlet user, When calling PATCH /admin/outlets/{id}, Then 403 forbidden

**Rule: Outlet category**
- ✓ Given new outlet with no category, When created, Then category='lainnya' (default)
- ✓ Given category ∈ [warung, minimarket, supermarket, grosir, restoran, kafe, toko_kelontong, lainnya], When creating/updating, Then accepted
- ✗ Given category="unknown_type", Then 422 validation error with allowed values

**Rule: Outlet scoring**
- ✓ Given outlet with orders, When an order reaches Delivered status, Then score recalculated
- ✓ Given outlet with 0 orders, When score calculated, Then score=0
- ✓ Given sales-collected order, When scoring, Then counts same as self-serve order

**Rule: Purchase history**
- ✓ Given admin, When calling GET /admin/outlets/{id}/orders, Then paginated order list with status, date, total
- ✓ Given admin, When calling GET /admin/outlets/{id}/summary, Then total_orders, total_spend, last_order_date returned

### F3 — Product Price Management

**Rule: Price update**
- ✓ Given admin, When calling PATCH /admin/products/{id} with price=15000, Then product price updated
- ✗ Given supplier user, When calling PATCH /admin/products/{id}, Then 403 forbidden
- ✗ Given price=-100, Then 422 validation error
- ✓ Given price=0, Then accepted (free item allowed)

**Rule: Price history**
- ✓ Given product with 3 price changes, When calling GET /admin/products/{id}/prices, Then all 3 listed with old_price, new_price, changed_at, changed_by

**Rule: Price snapshot**
- ✓ Given product price=10000 at order creation, When price changed to 15000 then order approved, Then order unit_price=10000 (frozen)
- ✓ Given New order with no invoice, When price changed then order cancelled, Then cancel succeeds

### F4 — Promotion Management

**Rule: Promotion CRUD**
- ✓ Given admin, When creating promotion with valid data, Then created with status=active
- ✓ Given promotion with end_date in past, When checked, Then status=expired

**Rule: Single promo per product per time**
- ✓ Given product A with active promo P1, When creating P2 for same product with overlapping dates, Then 422 conflict
- ✓ Given product A promo P1 (Sept) and P2 (Oct, non-overlapping), When created, Then both valid

**Rule: Min order threshold**
- ✓ Given promo with min_order=5, When outlet orders 4 items, Then promo not applied
- ✓ Given promo with min_order=5, When outlet orders 5 items, Then promo applied

**Rule: Promo snapshot**
- ✓ Given promo active at order creation, When promo later deactivated, Then order keeps promo price
- ✓ Given promo created after order, When order approved, Then no retroactive discount

**Rule: Broadcast idempotency**
- ✓ Given promo not yet broadcast, When admin triggers broadcast, Then messages sent to eligible outlets
- ✓ Given promo already broadcast, When admin triggers again, Then 200 with already_sent status

### F5 — Sales Order Collection & Performance

**Rule: Sales-territory binding**
- ✓ Given sales user with territory_id=T, When creating order for outlet in territory T, Then 201 created with sales_user_id recorded
- ✗ Given sales user with territory_id=T, When creating order for outlet in territory U, Then 403 forbidden
- ✗ Given sales user with territory_id=null, When creating any order, Then 403 (must be assigned)

**Rule: Sales quota**
- ✓ Given sales with monthly target=50M, When current month orders total=30M, Then achievement=60%
- ✓ Given sales with target=0, When calculating achievement, Then 0% (no division by zero)
- ✓ Given cancelled order, When calculating achievement, Then excluded

**Rule: Performance views**
- ✓ Given admin, When calling GET /admin/sales/performance?period=2026-09, Then all sales users with target, achievement, percentage
- ✓ Given sales user, When calling GET /sales/my-performance, Then own data only
- ✗ Given sales user, When calling GET /admin/sales/performance, Then 403 forbidden

### F6 — WhatsApp Promotion Broadcast

**Rule: Broadcast targeting**
- ✓ Given 50 outlets with orders in last 30d, When broadcast triggered, Then 50 WhatsAppMessage records created
- ✓ Given outlet with last order >30d ago, When broadcast triggered, Then excluded

**Rule: Idempotency**
- ✓ Given promo already broadcast, When broadcast again, Then 200 already_sent (not error)
- ✓ Given provider failure after partial send, When retry, Then only failed messages re-sent

**Rule: Message content**
- ✓ Given promo with name/discount/dates/min_order, When broadcast, Then each message body contains promo details + outlet name
- ✓ Given broadcast completes, When checking messages, Then type=promo_broadcast

**OPEN QUESTIONS (risks if unresolved):**
- Sales quota for same period edited mid-month → assumed: prospective (new target applies from edit date, past achievement unchanged). If wrong: achievement recalculation needed.
- Promo edit after broadcast → assumed: blocked with 422. If product team wants editable promos: need versioned snapshot in message payload.
- Platform Owner vs Admin endpoint matrix → assumed: owner superset of admin everywhere except explicitly owner-only (role management). If wrong: authz matrix needs per-endpoint review.
- Scoring normalization windows → assumed: volume = order count last 90d normalized to max 100; frequency = orders/week capped at 10; recency = 1 - (days_since_last/90), floored at 0. If business wants different weights: formula is configurable in service.

**OUT-OF-SCOPE (remind pocket-planning):**
- Mobile PWA, GPS, driver roster, route optimization, live tracking, PoD photo (Phase 8)
- Python ML, LLM, replenishment automation (Phase 9)
- Multi-distributor, dynamic pricing, financial services (Phase 10)
- Supplier self-service pricing, promo stacking, voucher codes, CSV bulk update, outlet broadcast opt-out

---

## Dependencies

- Existing: Laravel 11, JWT auth, `OrderCreationService`, `CreditLimitService`, `InvoiceService`, `WhatsAppOutboundService`, `RoleAssignmentAudit`, `Territory` model, `spatie/laravel-query-builder` (for filtering).
- New migrations: `platform_owner` enum value, `territory_id` on users, `category` + `score` on outlets, `product_price_histories` table, `promotions` table, `sales_targets` table, `sales_user_id` on orders, `promo_broadcast` message type, `broadcast_at` on promotions.
- No new composer/npm packages required.

---

## Design Decision

**Chosen: Incremental service-layer extension with dedicated controllers per domain.**

**Reasoning:**
- F1 role management extends the existing `FinanceRoleController`/`FinanceRoleService` pattern (assign/remove + audit) to a general `UserRoleController`/`UserRoleService`, reusing `RoleAssignmentAudit`. This satisfies the audit GWT scenarios with minimal new abstraction.
- F2 outlet scoring is a new `OutletScoringService` (deterministic, synchronous on `Delivered` event) — consistent with Phase 3's deterministic PHP heuristic approach. No queue needed for MVP scale.
- F3 price history is a new `product_price_histories` table + `ProductPriceService`, following the existing service-layer pattern.
- F4 promotion is a new `Promotion` model + `PromotionService` + `PromotionController` (full CRUD), with overlap check in service layer + DB unique partial index where supported.
- F5 sales orders reuse `OrderCreationService` directly (no fork), with a thin `SalesOrderController` that resolves outlet + territory check before delegating. Sales targets are a new `SalesTarget` model + `SalesPerformanceService` for aggregation.
- F6 broadcast extends `WhatsAppOutboundService` with a `broadcastPromotion()` method using the existing `logical_key` dedup. No new messaging infrastructure.
- Frontend: one page per admin domain (`/admin/users`, `/admin/outlets`, `/admin/products`, `/admin/promotions`, `/admin/sales/performance`) + extended `/sales` page. All use existing Tailwind + Zustand + axios patterns.

**Alternatives considered:**
- *Option A (monolithic AdminController):* single controller for all admin CRUD. Rejected — violates single-responsibility; existing codebase already has per-domain controllers.
- *Option B (spatie/laravel-permission adoption):* full RBAC migration. Rejected per user decision — keep string enum; migration risk outweighs benefit for MVP. Spatie remains installed for future use.

**Tradeoffs:**
- + Minimal new abstraction; every feature maps to existing patterns.
- + No new dependencies; all migrations are additive (rollback-safe).
- - Scoring synchronous on Delivered may add latency at scale (acceptable for MVP; async is future work).
- - Single-promo constraint enforced at app layer (race possible under high concurrency; DB exclusion constraint added where Postgres supports it).

---

## Rollback Plan

- All migrations are additive (new columns nullable with defaults, new tables). Rollback = `php artisan migrate:rollback` per migration, no data loss to existing tables.
- Enum migrations (`platform_owner`, outlet `category`, `promo_broadcast` message type): Postgres `ALTER TYPE ... ADD VALUE` is non-transactional and non-reversible without recreate. Down-migration recreates enum without new value (documented in migration file). No existing rows affected (new values only).
- If promotion/price/scoring logic causes order pipeline errors: feature-flag via `is_active` column (promotions) or revert controller route registration (no order pipeline code modified except cancel-New fix, which is independently safe).
- Frontend pages are additive (new routes); removal does not affect existing pages.

---

## Architecture Validation

- [x] Respects layer boundaries: service-layer logic, controllers thin, migrations additive.
- [x] Follows existing patterns: Eloquent, JWT role checks, `logical_key` dedup, `limit+1` pagination, RefreshDatabase tests.
- [x] No new dependencies: zero composer/npm additions.
- [x] Build-vs-buy: no commodity problem hand-rolled; WhatsApp/Log/pagination reuse existing services.
- [x] Rollback defined: additive migrations, enum down-migration documented, feature isolation via `is_active`.
- [x] No silent data migrations: category defaults to `lainnya`; scoring starts at 0; no existing row mutation.
- [x] Performance acceptable: scoring sync on Delivered (single outlet recalc, bounded query); broadcast batches via existing lease mechanism.
- [x] No security regressions: central authz policy, regression GWT per endpoint×role, JWT invalidation on role change, IDOR scoping (outlet→own, sales→territory).
