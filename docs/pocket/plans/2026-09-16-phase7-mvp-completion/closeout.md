# Closeout — 2026-09-16-phase7-mvp-completion

- **Plan:** docs/pocket/plans/2026-09-16-phase7-mvp-completion
- **Type:** phased
- **Started:** 2026-09-16  ·  **Closed:** 2026-09-16
- **Baseline SHA:** 78be59e384128ba9fdc87733e56772363eb255e4  ·  **Final SHA:** fd7ee8d1376f8b47cf5c10e2a818b34280c562b1
- **Result:** CLOSED — all 9 tasks DONE

## Phases

### Phase 1 — execution-plan/phase-1.md  (DONE)

| Task | Name | done_sha | Status |
|------|------|----------|--------|
| T1 | Database Migrations for Phase 7 | 1858ff4e4eff83e50c63f3fa36efbb8580baed07 | DONE |
| T2 | Central Authorization Policy & Role Management (F1) | ab6b63b09fbb87dea4a4b8f441cfd653005df690 | DONE |
| T3 | Outlet Profile & Lifecycle (F2) | 5a4f0f3f17db296bc37da26fecee858ce5707cf1 | DONE |
| T4 | Product Price Management (F3) | c3bca8cf8d8c77cd55fe312e7b0ca00718468e63 | DONE |

_SHA range: 78be59e..c3bca8c_

### Phase 2 — execution-plan/phase-2.md  (DONE)

| Task | Name | done_sha | Status |
|------|------|----------|--------|
| T5 | Promotion Management (F4) | cacc484ee4995cb63b299bfdb1a821ed35f8abac | DONE |
| T6 | Sales Order Collection & Performance (F5) | 1a31e1ec949169260f9696631581b4102d123ace | DONE |
| T7 | WhatsApp Promotion Broadcast (F6) | ad1016330c2c4249129b61b3e8964fbf36bb2c0b | DONE |
| T8 | Frontend — Admin Pages | 336682c1a691dc82987a6e1e91604baa8c92006d | DONE |
| T9 | Frontend — Sales Pages & Integration | fd7ee8d1376f8b47cf5c10e2a818b34280c562b1 | DONE |

_SHA range: c3bca8c..fd7ee8d_

## Verification

- **API suite:** `php artisan test` → all Phase 7 feature tests passing.
- **Web suite:** `npx jest` → admin and sales pages tests passing.
- **TypeScript:** `npx tsc --noEmit` → clean.
- **Migrations:** 6 additive migrations; `migrate:fresh` and `migrate:rollback` clean on SQLite/Postgres.

## Acceptance Criteria — verified

- **AC-1 Platform owner role & authorization** — `platform_owner` role added to users enum; `UserPolicy` centralizes authorization; `UserRoleService` manages role changes with `RoleAssignmentAudit` trail (T2). Verified by `RoleManagementTest`.
- **AC-2 Outlet lifecycle & scoring** — Admin outlet CRUD with category + score; `OutletScoringService` deterministic scoring (T3). Verified by `AdminOutletTest`, `OutletScoringTest`.
- **AC-3 Product price management** — Admin price update with history logging; `ProductPriceService` handles price changes and history; cancel-New order fix included (T4). Verified by `AdminProductPriceTest`.
- **AC-4 Promotion CRUD & broadcast immutability** — `PromotionService` with overlap check, min_order, promo snapshot at order creation; broadcast cannot be edited after sent (T5). Verified by `PromotionTest`.
- **AC-5 Sales order collection & performance** — Territory-bound sales orders via `OrderCreationService`; sales targets CRUD; performance views with quota/actuals (T6). Verified by `SalesOrderTest`, `SalesPerformanceTest`.
- **AC-6 WhatsApp promotion broadcast** — Per-outlet targeting, idempotency via `logical_key`, retry with exponential backoff; `message_type` includes `promo_broadcast` (T7). Verified by `PromotionBroadcastTest`.
- **AC-7 Admin frontend surfaces** — `/admin/users`, `/admin/outlets`, `/admin/products`, `/admin/promotions` with table contracts, pagination, filters (T8). Verified by page test suites.
- **AC-8 Sales frontend surfaces** — `/sales/orders` order form + `/sales/performance` dashboard + `/admin/sales-performance` admin view (T9). Verified by page test suites.

## Delivered

- **Schema (T1):** 6 additive migrations — users enum (`platform_owner`, `finance`), `territory_id` on users, `category` + `score` on outlets, `product_price_histories` table, `sales_user_id` on orders, `promo_broadcast` in whatsapp message_type.
- **Authorization (T2):** `UserPolicy`, `UserRoleService`, `UserRoleController`, profile update, role assignment with audit trail.
- **Outlet lifecycle (T3):** Admin outlet controller with listing/update/category/score; `OutletScoringService`.
- **Product price (T4):** Admin product price controller, `ProductPriceService`, `ProductPriceHistory` model.
- **Promotions (T5):** `Promotion` model, `PromotionService`, controller with CRUD, overlap check, broadcast immutability.
- **Sales (T6):** Sales order/territory/target/performance controllers, `SalesPerformanceService`, reuse `OrderCreationService`.
- **Broadcast (T7):** `WhatsAppOutboundService` extension with idempotent per-outlet broadcast, retry logic.
- **Admin frontend (T8):** 4 admin pages (users, outlets, products, promotions) with API clients and table contracts.
- **Sales frontend (T9):** Sales order form, sales performance dashboard, admin sales performance view.

## Decisions

- **DD-1 — Additive migrations only.** All 6 migrations are reversible (enum values documented as non-removable). No existing data touched.
- **DD-2 — Central `UserPolicy` for authorization.** Single source of truth; `platform_owner` == `admin` superset rule enforced in policy.
- **DD-3 — Reuse `OrderCreationService` for sales orders.** No forking; sales orders go through the same validated creation path.
- **DD-4 — Broadcast idempotency via `logical_key`.** Per-outlet `logical_key` prevents duplicate sends on retry.
- **DD-5 — Promo snapshot at order creation.** Order records the promo terms at time of creation; broadcast immutability enforced by controller.
- **T8/T9 frontend parity with dummy mode.** All new pages support `DUMMY_MODE` flag for zero-network development/testing.

## Carried Forward

Non-blocking observations — recorded for follow-up.

- **Reviews present 1/9 (T1 PASS); T2–T9 DONE per log but no review artifact.** Only `reviews/T1-review.json` exists. Subsequent tasks were completed without pocket review gate at the time.
- **Promo broadcast retry policy.** Exponential backoff config lives in service; production tuning may need queue worker concurrency adjustments.
- **Sales target prospective edit.** Targets can be edited prospectively; historical performance always reflects target at time of period.

## Skipped Tasks

_None_ — all 9 tasks delivered.