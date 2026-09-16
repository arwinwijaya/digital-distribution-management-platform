# EXECUTION PLAN — Phase 7 MVP Completion & Core Operations

**Date:** 2026-09-16
**Spec:** docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
**Status:** draft
**Total tasks:** 9

---

## Execution Overview

### Recommended Order
```
T1 → T2, T3, T4 (parallel) → T5 → T6, T7, T8 (parallel) → T9
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T2, T3, T4 | T1 completes |
| Group B | T6, T7, T8 | T5 completes |
| Final | T9 | T6, T7 complete (T8 may still be in flight — T9 does not touch T8 files) |

### Constraints Reminder
**Architecture:** Laravel 11 REST API + JWT auth; string-role enum (no spatie); service-layer business logic, thin controllers; additive migrations only; reuse OrderCreationService for sales orders (no fork); reuse WhatsAppOutboundService + logical_key dedup for broadcast; RoleAssignmentAudit for all role changes; pagination `{data[], meta{limit,has_more}}` with limit+1; Postgres enum migrations (additive, non-transactional); central UserPolicy for authorization.
**Out-of-scope:** Mobile PWA, GPS, driver, PoD, ML/LLM, multi-distributor, dynamic pricing, financial services, supplier self-service pricing, promo stacking, voucher codes, CSV bulk update.
**Assumptions at risk:** Sales quota prospective edit; promo edit after broadcast blocked; platform_owner is superset of admin; scoring normalization formula as specified.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules.

### File Structure Map

```
Rule: F1 Platform Owner superset
  Create: apps/api/app/Policies/UserPolicy.php                    (T2)
  Create: apps/api/app/Services/UserRoleService.php               (T2)
  Create: apps/api/app/Http/Controllers/UserRoleController.php    (T2)
  Create: apps/api/app/Http/Requests/UpdateProfileRequest.php     (T2)
  Create: apps/api/app/Http/Requests/AssignRoleRequest.php        (T2)
  Modify: apps/api/app/Models/User.php                            (T2)
  Modify: apps/api/app/Services/FinanceAuthorizationService.php   (T2)
  Modify: apps/api/app/Http/Controllers/OutletController.php      (T2)
  Modify: apps/api/routes/api.php                                 (T2)
  Test:   apps/api/tests/Feature/RoleManagementTest.php           (T2)

Rule: F2 Outlet listing / update / category
  Create: apps/api/app/Http/Controllers/AdminOutletController.php (T3)
  Create: apps/api/app/Http/Requests/UpdateOutletRequest.php      (T3)
  Create: apps/api/app/Services/OutletScoringService.php          (T3)
  Modify: apps/api/app/Models/Outlet.php                          (T3)
  Modify: apps/api/routes/api.php                                 (T3)
  Test:   apps/api/tests/Feature/AdminOutletTest.php              (T3)
  Test:   apps/api/tests/Feature/OutletScoringTest.php            (T3)

Rule: F3 Price update / history / snapshot
  Create: apps/api/app/Http/Controllers/AdminProductController.php (T4)
  Create: apps/api/app/Http/Requests/UpdateProductPriceRequest.php (T4)
  Create: apps/api/app/Services/ProductPriceService.php            (T4)
  Create: apps/api/app/Models/ProductPriceHistory.php              (T4)
  Create: apps/api/database/migrations/..._create_product_price_histories_table.php (T4)
  Modify: apps/api/routes/api.php                                  (T4)
  Test:   apps/api/tests/Feature/AdminProductPriceTest.php         (T4)

Rule: F4 Promotion CRUD / overlap / min_order / snapshot
  Create: apps/api/app/Models/Promotion.php                        (T5)
  Create: apps/api/app/Http/Controllers/PromotionController.php    (T5)
  Create: apps/api/app/Http/Requests/StorePromotionRequest.php     (T5)
  Create: apps/api/app/Http/Requests/UpdatePromotionRequest.php    (T5)
  Create: apps/api/app/Services/PromotionService.php               (T5)
  Create: apps/api/database/migrations/..._create_promotions_table.php (T5)
  Modify: apps/api/routes/api.php                                  (T5)
  Test:   apps/api/tests/Feature/PromotionTest.php                 (T5)

Rule: F5 Sales order / territory / quota / performance
  Create: apps/api/app/Http/Controllers/SalesOrderController.php   (T6)
  Create: apps/api/app/Http/Requests/StoreSalesOrderRequest.php    (T6)
  Create: apps/api/app/Http/Controllers/SalesTargetController.php  (T6)
  Create: apps/api/app/Http/Controllers/SalesPerformanceController.php (T6)
  Create: apps/api/app/Http/Requests/StoreSalesTargetRequest.php   (T6)
  Create: apps/api/app/Http/Requests/UpdateSalesTargetRequest.php  (T6)
  Create: apps/api/app/Models/SalesTarget.php                      (T6)
  Create: apps/api/app/Services/SalesPerformanceService.php        (T6)
  Create: apps/api/database/migrations/..._create_sales_targets_table.php (T6)
  Modify: apps/api/app/Models/Order.php                            (T6)
  Modify: apps/api/app/Services/OrderCreationService.php           (T6)
  Modify: apps/api/routes/api.php                                  (T6)
  Test:   apps/api/tests/Feature/SalesOrderTest.php                (T6)
  Test:   apps/api/tests/Feature/SalesPerformanceTest.php          (T6)

Rule: F6 WhatsApp promotion broadcast
  Modify: apps/api/app/Services/WhatsAppOutboundService.php        (T7)
  Modify: apps/api/app/Models/WhatsAppMessage.php                  (T7)
  Create: apps/api/app/Http/Controllers/PromotionBroadcastController.php (T7)
  Modify: apps/api/routes/api.php                                  (T7)
  Test:   apps/api/tests/Feature/PromotionBroadcastTest.php        (T7)

Frontend (all groups)
  Create: apps/web/src/app/admin/users/page.tsx                    (T8)
  Create: apps/web/src/app/admin/users/api.ts                      (T8)
  Create: apps/web/src/app/admin/outlets/page.tsx                  (T8)
  Create: apps/web/src/app/admin/outlets/api.ts                    (T8)
  Create: apps/web/src/app/admin/products/page.tsx                 (T8)
  Create: apps/web/src/app/admin/products/api.ts                   (T8)
  Create: apps/web/src/app/admin/promotions/page.tsx               (T8)
  Create: apps/web/src/app/admin/promotions/api.ts                 (T8)
  Create: apps/web/src/app/sales/orders/page.tsx                   (T9)
  Create: apps/web/src/app/sales/orders/api.ts                     (T9)
  Create: apps/web/src/app/sales/performance/page.tsx              (T9)
  Create: apps/web/src/app/sales/performance/api.ts                (T9)
  Create: apps/web/src/app/admin/sales-performance/page.tsx        (T9)
  Create: apps/web/src/app/admin/sales-performance/api.ts          (T9)
```

---

## Pocket Packets

---

### Task 1: Database Migrations for Phase 7 [prereq]

## OBJECTIVE
Create all additive database migrations needed by Phase 7 feature groups. Every migration must be reversible and non-destructive to existing data.

Files:
- Create: `apps/api/database/migrations/2026_09_16_000001_add_platform_owner_to_users_role_enum.php`
- Create: `apps/api/database/migrations/2026_09_16_000002_add_territory_id_to_users_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000003_add_category_and_score_to_outlets_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000004_create_product_price_histories_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000005_add_sales_user_id_to_orders_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000006_add_promo_broadcast_to_whatsapp_messages_message_type.php`

(Note: promotions + sales_targets tables are created in T5 and T6 respectively — not here — because their RED cycles must create them.)

Steps:
1. Create migration: add `platform_owner` and `finance` to users.role enum.
   - Postgres: `ALTER TYPE ... ADD VALUE IF NOT EXISTS 'platform_owner'` and `'finance'`
   - Down: document non-reversibility (enum values cannot be removed in Postgres)
   - Verify: `php artisan migrate --pretend` shows no errors

2. Create migration: add nullable `territory_id` FK to users table.
   - Down: drop column

3. Create migration: add nullable `category` (default 'lainnya') and `score` (default 0, integer) to outlets table.
   - Down: drop columns

4. Create migration: create `product_price_histories` table.
   - Columns: id, product_id FK, old_price decimal(12,2), new_price decimal(12,2), changed_by FK users, changed_at timestamp, timestamps
   - Down: drop table

5. Create migration: add nullable `sales_user_id` FK to orders table.
   - Down: drop column

6. Create migration: add `promo_broadcast` to whatsapp_messages.message_type enum.
   - Postgres: `ALTER TYPE ... ADD VALUE IF NOT EXISTS 'promo_broadcast'`
   - Down: document non-reversibility

7. Verify promotions table design: `broadcast_at` is included as a nullable timestamp column in T5's `create_promotions_table` migration — no separate T1 migration for it.

8. Verify all migrations run: `php artisan migrate:fresh`
9. Verify all rollbacks: `php artisan migrate:rollback`
10. Commit: `git add apps/api/database/migrations/ && git commit -m "chore(db): add Phase 7 additive migrations for roles, outlets, products, promotions, orders, and whatsapp"`

[no-tdd — structural task]

## REFERENCES LOADED
- spec — Context → Related Areas: users table has enum `['admin','supplier','outlet','sales','driver']`; whatsapp_messages has message_type enum
- apps/api/database/migrations/2024_01_01_000000_create_users_table.php — existing role enum definition
- apps/api/database/migrations/2026_09_08_000014_create_whatsapp_messages_table.php — existing message_type enum

## WHY THIS APPROACH
All migrations are additive (new columns nullable with defaults, new tables). Postgres enum ADD VALUE is non-transactional but safe for new values. No existing rows affected.

Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: All migrations must be additive — no dropping columns or removing enum values from existing tables]
You are implementing database migrations for Phase 7 MVP Completion.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Incremental service-layer extension with dedicated controllers per domain.
Files in scope: apps/api/database/migrations/ (new files only)
Available after: none (prereq)
Architecture rule: Postgres enum migrations are additive and non-transactional; document non-reversible down-migrations
[RESTATE: All migrations must be additive — no destructive changes to existing schema]

## DELIVERABLE
Given all Phase 7 migrations exist, When `php artisan migrate:fresh` runs, Then all tables/columns/enums are created without error
Given all Phase 7 migrations exist, When `php artisan migrate:rollback` runs, Then rollback completes for reversible migrations
Given the users table exists, When platform_owner role is added, Then existing rows are unaffected (new enum value only)

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Every migration is reversible where Postgres allows (document non-reversible enum down-migrations)
- New columns are nullable with defaults where applicable
- No existing data is modified or deleted
- Foreign keys have appropriate indexes

Must-not-have:
- Destructive changes to existing tables (no DROP COLUMN on populated tables)
- Non-nullable columns without defaults on existing tables
- Migrations that require downtime

Rollback note:
- Enum migrations (platform_owner, promo_broadcast) are non-reversible in Postgres — documented in migration files
- All other migrations are standard up/down

## STOP CONDITIONS
Done when: all migrations created, `php artisan migrate:fresh` succeeds, `php artisan migrate:rollback` completes
Escalate when: migration requires destructive change to existing data

---

### Task 2: Central Authorization Policy & Role Management (F1) [depends: T1]

## OBJECTIVE
Implement platform_owner role support, central UserPolicy for authorization, general role assignment with audit and JWT invalidation, user listing with pagination, and user profile update. Fix the missing admin assertion on OutletController::updatePaymentTerms.

Files:
- Create: `apps/api/app/Policies/UserPolicy.php`
- Create: `apps/api/app/Services/UserRoleService.php`
- Create: `apps/api/app/Http/Controllers/UserRoleController.php`
- Create: `apps/api/app/Http/Requests/UpdateProfileRequest.php`
- Create: `apps/api/app/Http/Requests/AssignRoleRequest.php`
- Modify: `apps/api/app/Models/User.php` — add `isPlatformOwner()`, add `territory_id` to fillable
- Modify: `apps/api/app/Services/FinanceAuthorizationService.php` — add `isPlatformOwner()`, `assertAdminOrOwner()`
- Modify: `apps/api/app/Http/Controllers/OutletController.php` — add admin assertion to `updatePaymentTerms`
- Modify: `apps/api/routes/api.php` — add new routes
- Create: `apps/api/tests/Feature/RoleManagementTest.php`

Steps:
1. Write failing tests for: Platform Owner superset access
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent:
   Given a user with role=platform_owner, When calling GET /admin/users, Then access is granted (200)
   Given a user with role=admin, When calling PATCH /admin/users/{id}/role (role management endpoint), Then 403 forbidden
   Given a user with role=outlet, When calling GET /admin/users, Then 403 forbidden
   Exercise through: HTTP endpoints via authenticated requests
   Test doubles: none — real JWT auth with factory users
   Expected RED: UserPolicy does not exist; isPlatformOwner() does not exist; admin assertion missing from updatePaymentTerms

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected failure: Method not found or 403/500 errors

3. Implement UserPolicy with `viewAny`, `update`, `assignRole` methods checking platform_owner OR admin.
   Implement `isPlatformOwner()` on User model.
   Implement `assertAdminOrOwner()` on FinanceAuthorizationService.
   File: `apps/api/app/Policies/UserPolicy.php`, `apps/api/app/Models/User.php`, `apps/api/app/Services/FinanceAuthorizationService.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected: PASS

5. Write failing tests for: Role assignment with audit + JWT invalidation
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent:
   Given admin assigns role sales→driver to user X, When checking audit log, Then from_role=sales, to_role=driver, actor=admin logged
   Given admin changes user X's role, When user X tries to use old refresh token, Then 401 forced re-login
   Given admin tries to assign invalid role "superuser", Then validation error returned
   Exercise through: HTTP POST /admin/users/{id}/role endpoint
   Test doubles: none — real JWT with RoleAssignmentAudit verification
   Expected RED: UserRoleService does not exist; no role assignment endpoint; no JWT invalidation on role change

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected failure: Route not defined or service class not found

7. Implement UserRoleService (assign role with RoleAssignmentAudit + JWT invalidation via token blacklist/refresh revocation).
   Implement UserRoleController with `assignRole` action.
   Implement AssignRoleRequest validation (valid role enum, not 'superuser').
   File: `apps/api/app/Services/UserRoleService.php`, `apps/api/app/Http/Controllers/UserRoleController.php`, `apps/api/app/Http/Requests/AssignRoleRequest.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected: PASS

9. Write failing tests for: User profile update
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent:
   Given authenticated user, When calling PATCH /auth/me with name/email, Then profile updated and returned
   Given authenticated user, When calling PATCH /auth/me with role=outlet, Then 200 with role unchanged (ignored)
   Exercise through: HTTP PATCH /auth/me endpoint
   Test doubles: none
   Expected RED: AuthController::me is GET-only; no PATCH handler; role field not filtered

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected failure: Method not allowed (405) for PATCH /auth/me

11. Add `update` method to AuthController handling PATCH /auth/me with name/email only (role field ignored).
    Create UpdateProfileRequest validation.
    File: `apps/api/app/Http/Controllers/AuthController.php`, `apps/api/app/Http/Requests/UpdateProfileRequest.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected: PASS

13. Write failing tests for: User listing with pagination
    Test file: `apps/api/tests/Feature/RoleManagementTest.php`
    Level: integration
    Test intent:
    Given admin, When calling GET /admin/users with role filter=sales, Then only sales users returned (paginated)
    Given outlet user, When calling GET /admin/users, Then 403 forbidden
    Exercise through: HTTP GET /admin/users endpoint
    Test doubles: none
    Expected RED: GET /admin/users route does not exist

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected failure: Route not defined

15. Implement UserRoleController::index with role filter, limit+1 pagination.
    File: `apps/api/app/Http/Controllers/UserRoleController.php`

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected: PASS

17. Write failing test for: Fix updatePaymentTerms missing admin assertion
    Test file: `apps/api/tests/Feature/RoleManagementTest.php`
    Level: integration
    Test intent:
    Given outlet user, When calling PUT /admin/outlets/{id}/payment-terms, Then 403 forbidden
    Exercise through: HTTP PUT endpoint
    Test doubles: none
    Expected RED: outlet user can currently update payment terms (missing assertion)

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected failure: 200 instead of 403

19. Add `$this->authorization->assertAdmin($request->user())` to `updatePaymentTerms`.
    File: `apps/api/app/Http/Controllers/OutletController.php`

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected: PASS

21. Refactor while green: Extract valid role constants to User model. Ensure UserPolicy is registered in AuthServiceProvider.
22. Commit:
    `git add apps/api/app/Policies/ apps/api/app/Services/UserRoleService.php apps/api/app/Http/Controllers/UserRoleController.php apps/api/app/Http/Requests/ apps/api/app/Models/User.php apps/api/app/Services/FinanceAuthorizationService.php apps/api/app/Http/Controllers/OutletController.php apps/api/routes/api.php apps/api/tests/Feature/RoleManagementTest.php`
    `git commit -m "feat(auth): add platform_owner role, UserPolicy, UserRoleService, user listing, profile update, and fix updatePaymentTerms admin assertion"`

## REFERENCES LOADED
- spec — F1 Role Management rules, all GWT scenarios
- apps/api/app/Models/User.php — existing role helpers, JWT claims
- apps/api/app/Services/FinanceAuthorizationService.php — existing isAdmin/assertAdmin pattern
- apps/api/app/Models/RoleAssignmentAudit.php — existing audit trail
- apps/api/app/Services/FinanceRoleService.php — existing assign/remove + audit pattern
- apps/api/app/Http/Controllers/AuthController.php — existing me endpoint (GET only)

## WHY THIS APPROACH
Reuses the FinanceRoleService audit pattern for general role assignment. Centralizes authorization in UserPolicy. Minimal new abstraction — extends existing patterns.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: platform_owner is superset of admin — every admin endpoint must grant access to platform_owner; every platform_owner-only endpoint must deny admin]
You are implementing Role Management (F1) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Incremental service-layer extension; UserPolicy centralizes authorization
Files in scope: apps/api/app/Policies/, apps/api/app/Services/UserRoleService.php, apps/api/app/Http/Controllers/UserRoleController.php, apps/api/app/Http/Requests/, apps/api/app/Models/User.php, apps/api/app/Services/FinanceAuthorizationService.php, apps/api/app/Http/Controllers/OutletController.php, apps/api/routes/api.php
Test framework: Laravel PHPUnit with RefreshDatabase, JWT via login helper
Available after: T1 (migrations)
Architecture rule: string-role enum (no spatie); RoleAssignmentAudit for all role changes; JWT invalidation on role change forces re-login
[RESTATE: platform_owner superset of admin — owner has all admin access; admin does NOT have owner-only access]

## DELIVERABLE
Given user with role=platform_owner, When calling any admin endpoint, Then access is granted
Given user with role=admin, When calling platform_owner-only endpoint, Then 403
Given user with role=outlet, When calling any admin endpoint, Then 403
Given admin assigns role sales→driver, When checking audit log, Then from_role=sales, to_role=driver, actor=admin
Given admin changes role, When user uses old refresh token, Then 401
Given invalid role "superuser", When assigning, Then validation error
Given authenticated user, When PATCH /auth/me with name/email, Then updated
Given authenticated user, When PATCH /auth/me with role, Then role unchanged
Given admin, When GET /admin/users?role=sales, Then paginated sales users
Given outlet, When GET /admin/users, Then 403
Given outlet, When PUT /admin/outlets/{id}/payment-terms, Then 403

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- UserPolicy registered and used for admin endpoints
- isPlatformOwner() on User model
- UserRoleService uses DB::transaction + lockForUpdate (matching FinanceRoleService pattern)
- RoleAssignmentAudit created for every role change
- JWT invalidation on role change
- limit+1 pagination for user listing
- Admin assertion on updatePaymentTerms

Must-not-have:
- Modifications to payment/invoice/delivery core logic
- spatie/laravel-permission usage
- Role field accepted in profile update (silently ignored)

Open question risks:
- Platform Owner vs Admin endpoint matrix assumed owner=superset → if wrong: per-endpoint authz review needed

## STOP CONDITIONS
Done when: all F1 GWT scenarios pass, tests green, commit created
Escalate when: authorization policy grants access to wrong roles

---

### Task 3: Outlet Profile & Lifecycle (F2) [depends: T1]

## OBJECTIVE
Implement admin outlet listing with filters and pagination, admin outlet update, outlet category validation, outlet scoring auto-recalculation on Delivered status, and outlet purchase history endpoints.

Files:
- Create: `apps/api/app/Http/Controllers/AdminOutletController.php`
- Create: `apps/api/app/Http/Requests/UpdateOutletRequest.php`
- Create: `apps/api/app/Services/OutletScoringService.php`
- Modify: `apps/api/app/Models/Outlet.php` — add `category`, `score` to fillable/casts, add scopes
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/AdminOutletTest.php`
- Create: `apps/api/tests/Feature/OutletScoringTest.php`

Steps:
1. Write failing tests for: Admin outlet listing with filters
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent:
   Given admin, When calling GET /admin/outlets, Then paginated list with name, category, territory, is_active
   Given admin with filter territory_id=1, Then only outlets in territory 1 returned
   Given outlet user, When calling GET /admin/outlets, Then 403
   Exercise through: HTTP GET /admin/outlets
   Test doubles: none — factory outlets
   Expected RED: GET /admin/outlets route does not exist

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AdminOutletTest`
   Expected failure: Route not defined

3. Implement AdminOutletController::index with name/category/territory/is_active filters, limit+1 pagination, admin-only access.
   File: `apps/api/app/Http/Controllers/AdminOutletController.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AdminOutletTest`
   Expected: PASS

5. Write failing tests for: Admin outlet update
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent:
   Given admin, When PATCH /admin/outlets/{id} with name/category/address, Then updated and returned
   Given outlet user, When PATCH /admin/outlets/{id}, Then 403
   Given category="unknown_type", Then 422 validation error
   Exercise through: HTTP PATCH /admin/outlets/{id}
   Test doubles: none
   Expected RED: endpoint does not exist; category validation not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AdminOutletTest`
   Expected failure: Route not defined

7. Implement AdminOutletController::update + UpdateOutletRequest with category enum validation.
   Add Outlet VALID_CATEGORIES constant. Update Outlet model fillable and casts.
   File: `apps/api/app/Http/Controllers/AdminOutletController.php`, `apps/api/app/Http/Requests/UpdateOutletRequest.php`, `apps/api/app/Models/Outlet.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AdminOutletTest`
   Expected: PASS

9. Write failing tests for: Outlet category default
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent:
   Given new outlet with no category, When created, Then category='lainnya'
   Given category ∈ [warung, minimarket, supermarket, grosir, restoran, kafe, toko_kelontong, lainnya], When creating, Then accepted
   Exercise through: Outlet factory / HTTP POST /outlets
   Test doubles: none
   Expected RED: category column does not exist or no default

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=AdminOutletTest`
    Expected failure: column not found or no default

11. Ensure migration default 'lainnya' takes effect. Add category to Outlet fillable.
    File: `apps/api/app/Models/Outlet.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=AdminOutletTest`
    Expected: PASS

13. Write failing tests for: Outlet scoring
    Test file: `apps/api/tests/Feature/OutletScoringTest.php`
    Level: integration
    Test intent:
    Given outlet with orders, When an order reaches Delivered status, Then score recalculated
    Given outlet with 0 orders, When score calculated, Then score=0
    Given sales-collected order, When scoring, Then counts same as self-serve
    Exercise through: OutletScoringService::recalculate(Outlet $outlet)
    Test doubles: factory orders with various statuses and dates
    Expected RED: OutletScoringService does not exist; score column has no computation

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=OutletScoringTest`
    Expected failure: Class not found

15. Implement OutletScoringService with formula:
    - volume = (order count last 90d / max_possible) * 100, capped at 100, * 0.4
    - frequency = (orders_per_week, capped at 10) * 10 * 0.3
    - recency = (1 - days_since_last_order/90), floored at 0, * 0.3
    - score=0 for zero orders
    Call from order status transition to Delivered.
    File: `apps/api/app/Services/OutletScoringService.php`

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=OutletScoringTest`
    Expected: PASS

17. Write failing tests for: Outlet purchase history
    Test file: `apps/api/tests/Feature/AdminOutletTest.php`
    Level: integration
    Test intent:
    Given admin, When calling GET /admin/outlets/{id}/orders, Then paginated order list with status, date, total
    Given admin, When calling GET /admin/outlets/{id}/summary, Then total_orders, total_spend, last_order_date
    Exercise through: HTTP GET endpoints
    Test doubles: factory orders for outlet
    Expected RED: endpoints do not exist

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=AdminOutletTest`
    Expected failure: Route not defined

19. Implement AdminOutletController::orders (paginated) and AdminOutletController::summary (aggregate).
    File: `apps/api/app/Http/Controllers/AdminOutletController.php`

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=AdminOutletTest`
    Expected: PASS

21. Refactor while green: Extract scoring formula constants to config or class constants.
22. Commit:
    `git add apps/api/app/Http/Controllers/AdminOutletController.php apps/api/app/Http/Requests/UpdateOutletRequest.php apps/api/app/Services/OutletScoringService.php apps/api/app/Models/Outlet.php apps/api/routes/api.php apps/api/tests/Feature/AdminOutletTest.php apps/api/tests/Feature/OutletScoringTest.php`
    `git commit -m "feat(outlets): add admin outlet CRUD, category enum, scoring service, and purchase history"`

## REFERENCES LOADED
- spec — F2 Outlet Profile & Lifecycle rules, all GWT scenarios
- apps/api/app/Models/Outlet.php — existing fillable, no category/score
- apps/api/app/Http/Controllers/OutletController.php — existing store, payment terms
- apps/api/app/Models/Order.php — existing status transitions
- apps/api/routes/api.php — existing route patterns

## WHY THIS APPROACH
Extends existing Outlet model with new columns. OutletScoringService is deterministic and synchronous (acceptable for MVP). Reuses existing pagination pattern from OrderController.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Outlet scoring formula is volume*0.4 + frequency*0.3 + recency*0.3; sales-collected orders count same as self-serve]
You are implementing Outlet Profile & Lifecycle (F2) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: OutletScoringService is synchronous on Delivered event
Files in scope: apps/api/app/Http/Controllers/AdminOutletController.php, apps/api/app/Http/Requests/UpdateOutletRequest.php, apps/api/app/Services/OutletScoringService.php, apps/api/app/Models/Outlet.php, apps/api/routes/api.php
Available after: T1 (migrations add category/score columns)
Architecture rule: Scoring synchronous on Delivered; pagination limit+1; admin-only access
[RESTATE: Scoring formula is volume*0.4 + frequency*0.3 + recency*0.3; zero orders = score 0]

## DELIVERABLE
Given admin, When GET /admin/outlets, Then paginated list with category, territory, is_active
Given admin, When GET /admin/outlets?territory_id=1, Then filtered to territory 1
Given outlet, When GET /admin/outlets, Then 403
Given admin, When PATCH /admin/outlets/{id}, Then updated
Given outlet, When PATCH /admin/outlets/{id}, Then 403
Given new outlet, When created, Then category='lainnya'
Given invalid category, When creating, Then 422
Given outlet with orders on Delivered, When scoring, Then score recalculated per formula
Given outlet with 0 orders, When scoring, Then score=0
Given admin, When GET /admin/outlets/{id}/orders, Then paginated order list
Given admin, When GET /admin/outlets/{id}/summary, Then totals returned

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Scoring formula matches spec exactly
- Category enum validation with allowed values
- limit+1 pagination on all list endpoints
- Admin-only access on all admin endpoints

Must-not-have:
- Modifications to order pipeline (scoring is read-only on order data)
- Async scoring (synchronous for MVP)

Open question risks:
- Scoring normalization windows assumed as specified → if different: formula adjustment needed

## STOP CONDITIONS
Done when: all F2 GWT scenarios pass, tests green, commit created
Escalate when: scoring formula produces unexpected results

---

### Task 4: Product Price Management (F3) [depends: T1]

## OBJECTIVE
Implement admin product price update with history logging, price history endpoint, and ensure price snapshot is frozen at order creation.

Files:
- Create: `apps/api/app/Http/Controllers/AdminProductController.php`
- Create: `apps/api/app/Http/Requests/UpdateProductPriceRequest.php`
- Create: `apps/api/app/Services/ProductPriceService.php`
- Create: `apps/api/app/Models/ProductPriceHistory.php`
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/AdminProductPriceTest.php`

Steps:
1. Write failing tests for: Admin price update
   Test file: `apps/api/tests/Feature/AdminProductPriceTest.php`
   Level: integration
   Test intent:
   Given admin, When PATCH /admin/products/{id} with price=15000, Then product price updated
   Given supplier user, When PATCH /admin/products/{id}, Then 403
   Given price=-100, Then 422 validation error
   Given price=0, Then accepted (free item)
   Exercise through: HTTP PATCH /admin/products/{id}
   Test doubles: factory users (admin, supplier), factory product
   Expected RED: endpoint does not exist

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AdminProductPriceTest`
   Expected failure: Route not defined

3. Implement AdminProductController::update with admin-only access, price >= 0 validation.
   Implement UpdateProductPriceRequest.
   File: `apps/api/app/Http/Controllers/AdminProductController.php`, `apps/api/app/Http/Requests/UpdateProductPriceRequest.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AdminProductPriceTest`
   Expected: PASS

5. Write failing tests for: Price history
   Test file: `apps/api/tests/Feature/AdminProductPriceTest.php`
   Level: integration
   Test intent:
   Given product with 3 price changes, When GET /admin/products/{id}/prices, Then all 3 listed with old_price, new_price, changed_at, changed_by
   Exercise through: HTTP GET /admin/products/{id}/prices
   Test doubles: factory price history records
   Expected RED: endpoint does not exist; ProductPriceHistory model does not exist

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AdminProductPriceTest`
   Expected failure: Route not found or model not found

7. Implement ProductPriceHistory model with fillable/casts.
   Implement ProductPriceService::updatePrice that logs history before updating.
   Implement AdminProductController::prices endpoint.
   File: `apps/api/app/Models/ProductPriceHistory.php`, `apps/api/app/Services/ProductPriceService.php`, `apps/api/app/Http/Controllers/AdminProductController.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AdminProductPriceTest`
   Expected: PASS

9. Write failing tests for: Price snapshot at order creation
   Test file: `apps/api/tests/Feature/AdminProductPriceTest.php`
   Level: integration
   Test intent:
   Given product price=10000 at order creation, When price changed to 15000 then order approved, Then order unit_price=10000 (frozen)
   Given New order with no invoice, When price changed then order cancelled, Then cancel succeeds
   Exercise through: OrderCreationService + price change + approval flow
   Test doubles: factory order with items
   Expected RED: price snapshot is already frozen in OrderCreationService (existing behavior) — verify this holds; cancel-New path may fail if InvoiceService::cancelOrder does `firstOrFail` on invoice

10. Run test — verify PASS (existing behavior) or FAIL (cancel-New gap):
    `cd apps/api && php artisan test --filter=AdminProductPriceTest`
    Expected: PASS for frozen price; FAIL for cancel-New if invoice not found

11. If cancel-New fails: fix InvoiceService::cancelOrder to handle New orders without invoice.
    File: `apps/api/app/Services/InvoiceService.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=AdminProductPriceTest`
    Expected: PASS

13. Refactor while green: Ensure ProductPriceService uses DB::transaction for atomicity.
14. Commit:
    `git add apps/api/app/Http/Controllers/AdminProductController.php apps/api/app/Http/Requests/UpdateProductPriceRequest.php apps/api/app/Services/ProductPriceService.php apps/api/app/Models/ProductPriceHistory.php apps/api/routes/api.php apps/api/tests/Feature/AdminProductPriceTest.php`
    `git commit -m "feat(products): add admin price management with history logging and cancel-New order fix"`

## REFERENCES LOADED
- spec — F3 Product Price Management rules, all GWT scenarios
- apps/api/app/Models/Product.php — existing price column, decimal:2 cast
- apps/api/app/Services/OrderCreationService.php — existing price snapshot (unit_price from product.price at create time)
- apps/api/app/Services/InvoiceService.php — existing cancelOrder with firstOrFail gap

## WHY THIS APPROACH
ProductPriceService logs history before updating price (audit trail). Price snapshot is already frozen in OrderCreationService. InvoiceService cancel-New fix is minimal and independently safe.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Price snapshot frozen at order creation — OrderCreationService reads product.price at create time; approval must NOT re-price]
You are implementing Product Price Management (F3) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: ProductPriceService with history logging; price snapshot already frozen
Files in scope: apps/api/app/Http/Controllers/AdminProductController.php, apps/api/app/Http/Requests/UpdateProductPriceRequest.php, apps/api/app/Services/ProductPriceService.php, apps/api/app/Models/ProductPriceHistory.php, apps/api/routes/api.php, apps/api/app/Services/InvoiceService.php (cancel-New fix only)
Available after: T1 (migrations create product_price_histories table)
Architecture rule: Admin-only price updates; supplier → 403; price >= 0; price=0 allowed
[RESTATE: Price frozen at order creation — approval must NOT re-price; cancel-New allowed without invoice]

## DELIVERABLE
Given admin, When PATCH /admin/products/{id} with price=15000, Then updated
Given supplier, When PATCH /admin/products/{id}, Then 403
Given price=-100, When updating, Then 422
Given price=0, When updating, Then accepted
Given 3 price changes, When GET /admin/products/{id}/prices, Then all 3 listed
Given price=10000 at creation, When changed to 15000 then approved, Then unit_price=10000
Given New order, When cancelled, Then succeeds without invoice

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Price history logged atomically with price update
- changed_by records the actor user ID
- Admin-only access on all product endpoints
- cancel-New order path works without invoice

Must-not-have:
- Re-pricing on approval (frozen at creation)
- Supplier access to price update

## STOP CONDITIONS
Done when: all F3 GWT scenarios pass, tests green, commit created
Escalate when: price snapshot not frozen at creation

---

### Task 5: Promotion Management (F4) [depends: T3, T4]

## OBJECTIVE
Implement promotion CRUD with overlap checking, min order threshold, promo snapshot at order creation, and immutability after broadcast.

Files:
- Create: `apps/api/app/Models/Promotion.php`
- Create: `apps/api/app/Http/Controllers/PromotionController.php`
- Create: `apps/api/app/Http/Requests/StorePromotionRequest.php`
- Create: `apps/api/app/Http/Requests/UpdatePromotionRequest.php`
- Create: `apps/api/app/Services/PromotionService.php`
- Create: `apps/api/database/migrations/2026_09_16_000007_create_promotions_table.php`
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/PromotionTest.php`

Steps:
1. Write failing tests for: Promotion CRUD
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent:
   Given admin, When creating promotion with valid data, Then created with is_active=true
   Given promotion with end_date in past, When checked, Then is_active can be false
   Given admin, When deleting a non-broadcast promotion, Then removed (200/204)
   Exercise through: HTTP POST /admin/promotions
   Test doubles: factory admin, factory product
   Expected RED: promotions table does not exist; route not defined

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected failure: table not found

3. Create promotions table migration + Promotion model + factory.
   Implement PromotionController::store, ::index (limit+1 pagination), ::show, ::update, ::destroy.
   Implement StorePromotionRequest, UpdatePromotionRequest.
   File: all new files listed above

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected: PASS

5. Write failing tests for: Single promo per product per time (overlap check)
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent:
   Given product A with active promo P1 (Sept 1-30), When creating P2 for same product with overlapping dates (Sept 15-Oct 15), Then 422 conflict
   Given product A promo P1 (Sept) and P2 (Oct, non-overlapping), When created, Then both valid
   Exercise through: HTTP POST /admin/promotions
   Test doubles: factory promotions
   Expected RED: overlap check not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected failure: P2 created without conflict error

7. Implement PromotionService::assertNoOverlappingPromotion using DB query (check for active promos on same product with overlapping date ranges).
   File: `apps/api/app/Services/PromotionService.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected: PASS

9. Write failing tests for: Min order threshold
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent:
   Given promo with min_order=5 items, When outlet orders 4 items, Then promo not applied
   Given promo with min_order=5 items, When outlet orders 5 items, Then promo applied
   Exercise through: OrderCreationService with promo_id in request
   Test doubles: factory promo, factory products
   Expected RED: promo not applied to orders; min_order not checked

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected failure: promo_id not handled in order creation

11. Implement promo application in OrderCreationService: validate min_order (item count), calculate discount, apply to total.
    Credit check uses post-discount total.
    File: `apps/api/app/Services/OrderCreationService.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected: PASS

13. Write failing tests for: Promo snapshot at order creation
    Test file: `apps/api/tests/Feature/PromotionTest.php`
    Level: integration
    Test intent:
    Given promo active at order creation, When promo later deactivated, Then order keeps promo price
    Given promo created after order, When order approved, Then no retroactive discount
    Exercise through: order creation flow with promo
    Test doubles: factory order, factory promo
    Expected RED: promo snapshot not stored on order

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected failure: promo snapshot not persisted

15. Store promo snapshot (promo_id, discount_amount) on order at creation time. Ensure approval does not re-apply promo.
    File: `apps/api/app/Services/OrderCreationService.php`, `apps/api/app/Models/Order.php`

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected: PASS

17. Write failing tests for: Promo immutability after broadcast
    Test file: `apps/api/tests/Feature/PromotionTest.php`
    Level: integration
    Test intent:
    Given promo with broadcast_at set, When admin tries to update, Then 422
    Exercise through: HTTP PATCH /admin/promotions/{id}
    Test doubles: factory promo with broadcast_at
    Expected RED: immutability check not implemented

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected failure: update succeeds when it should be blocked

19. Add broadcast_at check to PromotionService::update — if broadcast_at is set, reject update with 422.
    File: `apps/api/app/Services/PromotionService.php`

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected: PASS

21. Refactor while green: Ensure overlap check uses DB transaction for consistency.
22. Commit:
    `git add apps/api/app/Models/Promotion.php apps/api/app/Http/Controllers/PromotionController.php apps/api/app/Http/Requests/ apps/api/app/Services/PromotionService.php apps/api/database/migrations/ apps/api/routes/api.php apps/api/tests/Feature/PromotionTest.php apps/api/app/Services/OrderCreationService.php apps/api/app/Models/Order.php`
    `git commit -m "feat(promotions): add promotion CRUD, overlap check, min_order, promo snapshot, and immutability"`

## REFERENCES LOADED
- spec — F4 Promotion Management rules, all GWT scenarios
- apps/api/app/Services/OrderCreationService.php — existing order pipeline (lock, credit check, stock reserve)
- apps/api/app/Models/Order.php — existing fillable (no promo fields yet)

## WHY THIS APPROACH
PromotionService centralizes overlap check and immutability. Promo snapshot stored on order at creation (not approval) per spec. Credit check uses post-discount total.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Promo applied at order creation only — no retroactive application on approval; single promo per product per time enforced by overlap check]
You are implementing Promotion Management (F4) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: PromotionService with overlap check; promo snapshot on order at creation
Files in scope: apps/api/app/Models/Promotion.php, apps/api/app/Http/Controllers/PromotionController.php, apps/api/app/Http/Requests/, apps/api/app/Services/PromotionService.php, apps/api/database/migrations/, apps/api/routes/api.php, apps/api/app/Services/OrderCreationService.php, apps/api/app/Models/Order.php
Available after: T3 (outlet scoring), T4 (product prices)
Architecture rule: Admin-only promo CRUD; single promo per product per time; min_order threshold; promo immutability after broadcast
[RESTATE: Promo applied at order creation only — no retroactive application on approval]

## DELIVERABLE
Given admin, When creating promo with valid data, Then created with is_active=true
Given promo with past end_date, When checked, Then is_active can be false
Given active promo P1 on product A, When creating P2 with overlapping dates, Then 422
Given non-overlapping promos on same product, When created, Then both valid
Given promo min_order=5, When ordering 4 items, Then promo not applied
Given promo min_order=5, When ordering 5 items, Then promo applied
Given promo active at creation, When deactivated later, Then order keeps promo price
Given promo created after order, When approved, Then no retroactive discount
Given promo broadcast_at set, When updating, Then 422

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Overlap check uses DB transaction for consistency
- Promo snapshot stored on order at creation
- Credit check uses post-discount total
- Broadcast_at check on update
- Admin-only access

Must-not-have:
- Promo stacking (single promo only)
- Retroactive promo application on approval
- Voucher codes

Open question risks:
- Promo edit after broadcast assumed blocked → if wrong: need versioned snapshot

## STOP CONDITIONS
Done when: all F4 GWT scenarios pass, tests green, commit created
Escalate when: overlap check has race condition or promo not applied correctly

---

### Task 6: Sales Order Collection & Performance (F5) [depends: T3, T4, T5]

## OBJECTIVE
Implement sales order creation with territory binding, sales target CRUD, and sales performance views (own + admin).

Files:
- Create: `apps/api/app/Http/Controllers/SalesOrderController.php`
- Create: `apps/api/app/Http/Requests/StoreSalesOrderRequest.php`
- Create: `apps/api/app/Http/Controllers/SalesTargetController.php`
- Create: `apps/api/app/Http/Controllers/SalesPerformanceController.php`
- Create: `apps/api/app/Http/Requests/StoreSalesTargetRequest.php`
- Create: `apps/api/app/Http/Requests/UpdateSalesTargetRequest.php`
- Create: `apps/api/app/Models/SalesTarget.php`
- Create: `apps/api/app/Services/SalesPerformanceService.php`
- Create: `apps/api/database/migrations/2026_09_16_000008_create_sales_targets_table.php`
- Modify: `apps/api/app/Models/Order.php` — add `sales_user_id` to fillable
- Modify: `apps/api/app/Services/OrderCreationService.php` — accept optional sales_user_id
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/SalesOrderTest.php`
- Create: `apps/api/tests/Feature/SalesPerformanceTest.php`

Steps:
1. Write failing tests for: Sales-territory binding
   Test file: `apps/api/tests/Feature/SalesOrderTest.php`
   Level: integration
   Test intent:
   Given sales user with territory_id=T, When creating order for outlet in territory T, Then 201 with sales_user_id recorded
   Given sales user with territory_id=T, When creating order for outlet in territory U, Then 403
   Given sales user with territory_id=null, When creating any order, Then 403
   Exercise through: HTTP POST /sales/orders
   Test doubles: factory sales user with territory, factory outlet in territory
   Expected RED: SalesOrderController does not exist; territory check not implemented

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=SalesOrderTest`
   Expected failure: Route not defined

3. Implement SalesOrderController::store with territory check, delegating to OrderCreationService.
   Add sales_user_id parameter to OrderCreationService::create.
   Add sales_user_id to Order fillable.
   File: `apps/api/app/Http/Controllers/SalesOrderController.php`, `apps/api/app/Http/Requests/StoreSalesOrderRequest.php`, `apps/api/app/Services/OrderCreationService.php`, `apps/api/app/Models/Order.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=SalesOrderTest`
   Expected: PASS

5. Write failing tests for: Sales target CRUD
   Test file: `apps/api/tests/Feature/SalesPerformanceTest.php`
   Level: integration
   Test intent:
   Given admin, When creating sales target with target_amount=50000000 for period=2026-09, Then created
   Given admin, When listing sales targets, Then paginated list returned
   Given admin, When updating a sales target (PATCH), Then updated; When deleting, Then removed
   Given sales user, When creating sales target, Then 403
   Exercise through: HTTP POST/GET /admin/sales-targets
   Test doubles: factory admin, factory sales user
   Expected RED: SalesTarget model does not exist; routes not defined

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=SalesPerformanceTest`
   Expected failure: table not found

7. Create sales_targets table migration + SalesTarget model + factory.
   Implement SalesTargetController with CRUD (admin-only).
   File: all new files listed above

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=SalesPerformanceTest`
   Expected: PASS

9. Write failing tests for: Sales performance views
   Test file: `apps/api/tests/Feature/SalesPerformanceTest.php`
   Level: integration
   Test intent:
   Given sales with monthly target=50M, When current month orders total=30M, Then achievement=60%
   Given sales with target=0, When calculating achievement, Then 0% (no div-zero)
   Given cancelled order, When calculating achievement, Then excluded
   Given admin, When GET /admin/sales/performance?period=2026-09, Then all sales with target, achievement, percentage
   Given sales user, When GET /sales/my-performance, Then own data only
   Given sales user, When GET /admin/sales/performance, Then 403
   Exercise through: HTTP GET endpoints + SalesPerformanceService
   Test doubles: factory orders, factory sales targets
   Expected RED: SalesPerformanceService does not exist; endpoints not defined

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=SalesPerformanceTest`
    Expected failure: Class not found

11. Implement SalesPerformanceService::calculatePerformance(salesUserId, period) with:
    - target from sales_targets
    - achievement from orders (Confirmed/Delivered/Paid/Partially Paid) excluding Cancelled
    - percentage = achievement/target * 100, or 0% if target=0
    Implement SalesPerformanceController::myPerformance and ::adminPerformance.
    File: `apps/api/app/Services/SalesPerformanceService.php`, `apps/api/app/Http/Controllers/SalesPerformanceController.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=SalesPerformanceTest`
    Expected: PASS

13. Refactor while green: Ensure SalesPerformanceService queries are efficient (indexed on sales_user_id + status).
14. Commit:
    `git add apps/api/app/Http/Controllers/SalesOrderController.php apps/api/app/Http/Requests/StoreSalesOrderRequest.php apps/api/app/Http/Controllers/SalesTargetController.php apps/api/app/Http/Controllers/SalesPerformanceController.php apps/api/app/Http/Requests/StoreSalesTargetRequest.php apps/api/app/Http/Requests/UpdateSalesTargetRequest.php apps/api/app/Models/SalesTarget.php apps/api/app/Services/SalesPerformanceService.php apps/api/database/migrations/ apps/api/routes/api.php apps/api/tests/Feature/SalesOrderTest.php apps/api/tests/Feature/SalesPerformanceTest.php apps/api/app/Models/Order.php apps/api/app/Services/OrderCreationService.php`
    `git commit -m "feat(sales): add sales order collection with territory binding, sales targets, and performance views"`

## REFERENCES LOADED
- spec — F5 Sales Order Collection & Performance rules, all GWT scenarios
- apps/api/app/Services/OrderCreationService.php — existing order pipeline (must reuse, no fork)
- apps/api/app/Models/Order.php — existing fillable
- apps/api/app/Models/User.php — existing territory_id (after T2 migration)
- apps/api/app/Models/Territory.php — existing territory model

## WHY THIS APPROACH
SalesOrderController is a thin wrapper over OrderCreationService (no fork). SalesPerformanceService aggregates from orders + sales_targets. Territory check is simple FK comparison.

Complexity: deep

## SANDWICH CONTEXT
[CRITICAL: Sales orders MUST go through OrderCreationService pipeline — no parallel order-creation path; territory check is user.territory_id === outlet.territory_id]
You are implementing Sales Order Collection & Performance (F5) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: SalesOrderController delegates to OrderCreationService; SalesPerformanceService for aggregation
Files in scope: apps/api/app/Http/Controllers/SalesOrderController.php, apps/api/app/Http/Requests/StoreSalesOrderRequest.php, apps/api/app/Http/Controllers/SalesTargetController.php, apps/api/app/Http/Controllers/SalesPerformanceController.php, apps/api/app/Http/Requests/, apps/api/app/Models/SalesTarget.php, apps/api/app/Services/SalesPerformanceService.php, apps/api/database/migrations/, apps/api/routes/api.php, apps/api/app/Models/Order.php, apps/api/app/Services/OrderCreationService.php
Available after: T3 (outlet model), T4 (product prices), T5 (promo application in orders)
Architecture rule: Reuse OrderCreationService; territory binding; limit+1 pagination; admin-only targets
[RESTATE: Sales orders MUST go through OrderCreationService — no parallel order-creation path]

## DELIVERABLE
Given sales with territory_id=T, When ordering for outlet in T, Then 201 with sales_user_id
Given sales with territory_id=T, When ordering for outlet in U, Then 403
Given sales with territory_id=null, When ordering, Then 403
Given admin, When creating sales target, Then created
Given sales, When creating sales target, Then 403
Given target=50M and achievement=30M, When calculating, Then 60%
Given target=0, When calculating, Then 0% (no div-zero)
Given cancelled order, When calculating, Then excluded
Given admin, When GET /admin/sales/performance, Then all sales with targets
Given sales, When GET /sales/my-performance, Then own data only
Given sales, When GET /admin/sales/performance, Then 403

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Territory check before delegating to OrderCreationService
- sales_user_id stored on order
- Achievement excludes Cancelled orders
- target=0 → 0% (no division by zero)
- Admin-only on target CRUD and admin performance view
- Sales-user-only on my-performance

Must-not-have:
- Forked order creation path
- Sales user accessing admin endpoints
- Performance calculation including cancelled orders

Open question risks:
- Sales quota prospective edit assumed → if wrong: achievement recalculation needed

## STOP CONDITIONS
Done when: all F5 GWT scenarios pass, tests green, commit created
Escalate when: OrderCreationService pipeline bypassed or territory check missing

---

### Task 7: WhatsApp Promotion Broadcast (F6) [depends: T5]

## OBJECTIVE
Implement promotion broadcast to eligible outlets via WhatsApp, using existing WhatsAppOutboundService + logical_key dedup pattern.

Files:
- Modify: `apps/api/app/Services/WhatsAppOutboundService.php` — add `broadcastPromotion()` method
- Modify: `apps/api/app/Models/WhatsAppMessage.php` — add `promo_broadcast` to message_type awareness
- Create: `apps/api/app/Http/Controllers/PromotionBroadcastController.php`
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/PromotionBroadcastTest.php`

Steps:
1. Write failing tests for: Broadcast targeting
   Test file: `apps/api/tests/Feature/PromotionBroadcastTest.php`
   Level: integration
   Test intent:
   Given 50 outlets with orders in last 30d, When broadcast triggered, Then 50 WhatsAppMessage records created with type=promo_broadcast
   Given outlet with last order >30d ago, When broadcast triggered, Then excluded
   Exercise through: HTTP POST /admin/promotions/{id}/broadcast
   Test double: factory outlets with orders, mock WhatsAppClient
   Expected RED: broadcast endpoint does not exist; WhatsAppOutboundService::broadcastPromotion does not exist

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected failure: Route not defined

3. Implement WhatsAppOutboundService::broadcastPromotion(Promotion $promo) that:
   - Queries outlets with >=1 order in last 30d (using scopePurchasable to exclude inactive-supplier products)
   - Creates WhatsAppMessage records with logical_key="promo-broadcast:{promo_id}"
   - Uses insertOrIgnore for idempotency
   - Dispatches delivery for each message
   - Returns count of messages created
   Implement PromotionBroadcastController::broadcast (admin-only).
   File: `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Http/Controllers/PromotionBroadcastController.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected: PASS

5. Write failing tests for: Broadcast idempotency
   Test file: `apps/api/tests/Feature/PromotionBroadcastTest.php`
   Level: integration
   Test intent:
   Given promo already broadcast, When broadcast again, Then 200 already_sent (not error)
   Given provider failure after partial send, When retry, Then only failed messages re-sent
   Exercise through: HTTP POST /admin/promotions/{id}/broadcast (twice)
   Test doubles: mock WhatsAppClient
   Expected RED: idempotency check not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected failure: second broadcast creates duplicate messages

7. Implement idempotency check: if logical_key exists and status=sent, return already_sent.
   Implement retry: only re-send messages with status=failed.
   File: `apps/api/app/Services/WhatsAppOutboundService.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected: PASS

9. Write failing tests for: Message content
   Test file: `apps/api/tests/Feature/PromotionBroadcastTest.php`
   Level: integration
   Test intent:
   Given promo with name/discount/dates/min_order, When broadcast, Then each message body contains promo details + outlet name
   Given broadcast completes, When checking messages, Then type=promo_broadcast
   Given 0 eligible outlets, When broadcast, Then 200 with count=0
   Exercise through: broadcast flow
   Test doubles: mock WhatsAppClient
   Expected RED: message body not formatted with promo details

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
    Expected failure: message body missing promo details

11. Format message body with promo name, discount value, valid dates, min_order, outlet name.
    Set broadcast_at on promo after successful broadcast.
    File: `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Models/Promotion.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
    Expected: PASS

13. Refactor while green: Ensure broadcast uses DB transaction for atomicity.
14. Commit:
    `git add apps/api/app/Services/WhatsAppOutboundService.php apps/api/app/Models/WhatsAppMessage.php apps/api/app/Http/Controllers/PromotionBroadcastController.php apps/api/routes/api.php apps/api/tests/Feature/PromotionBroadcastTest.php apps/api/app/Models/Promotion.php`
    `git commit -m "feat(whatsapp): add promotion broadcast with targeting, idempotency, and message formatting"`

## REFERENCES LOADED
- spec — F6 WhatsApp Promotion Broadcast rules, all GWT scenarios
- apps/api/app/Services/WhatsAppOutboundService.php — existing logical_key dedup, dispatchDelivery pattern
- apps/api/app/Models/WhatsAppMessage.php — existing message_type, status fields
- apps/api/app/Models/Promotion.php — existing broadcast_at field (from T5)

## WHY THIS APPROACH
Reuses WhatsAppOutboundService + logical_key dedup pattern exactly. broadcast_at on promo tracks broadcast state. insertOrIgnore prevents duplicate messages.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Broadcast MUST use existing WhatsAppOutboundService + logical_key dedup; targeting uses scopePurchasable to exclude inactive-supplier products]
You are implementing WhatsApp Promotion Broadcast (F6) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Extend WhatsAppOutboundService with broadcastPromotion(); logical_key dedup
Files in scope: apps/api/app/Services/WhatsAppOutboundService.php, apps/api/app/Models/WhatsAppMessage.php, apps/api/app/Http/Controllers/PromotionBroadcastController.php, apps/api/routes/api.php, apps/api/tests/Feature/PromotionBroadcastTest.php, apps/api/app/Models/Promotion.php
Available after: T5 (promotions table exists)
Architecture rule: logical_key dedup; insertOrIgnore; admin-only broadcast; targeting by order history not is_active flag
[RESTATE: Broadcast MUST use existing WhatsAppOutboundService + logical_key dedup pattern]

## DELIVERABLE
Given 50 outlets with orders in last 30d, When broadcast, Then 50 messages created
Given outlet with last order >30d ago, When broadcast, Then excluded
Given promo already broadcast, When broadcast again, Then 200 already_sent
Given provider failure, When retry, Then only failed messages re-sent
Given promo details, When broadcast, Then message body contains promo info + outlet name
Given broadcast completes, When checking messages, Then type=promo_broadcast
Given 0 eligible outlets, When broadcast, Then 200 with count=0

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- logical_key = "promo-broadcast:{promo_id}" for idempotency
- Targeting by order history (last 30d), not is_active flag
- Message body includes promo name, discount, dates, min_order, outlet name
- broadcast_at set on promo after successful broadcast
- Admin-only access

Must-not-have:
- Duplicate messages on double-POST
- Targeting inactive-supplier products
- Modifications to WhatsApp inbound webhook

## STOP CONDITIONS
Done when: all F6 GWT scenarios pass, tests green, commit created
Escalate when: idempotency broken or targeting incorrect

---

### Task 8: Frontend — Admin Pages [depends: T2, T3, T4, T5]

## OBJECTIVE
Create admin frontend pages for user management, outlet management, product management, and promotion management. Each page includes list view with filters, create/edit forms, and relevant detail views.

Files:
- Create: `apps/web/src/app/admin/users/page.tsx`
- Create: `apps/web/src/app/admin/users/api.ts`
- Create: `apps/web/src/app/admin/outlets/page.tsx`
- Create: `apps/web/src/app/admin/outlets/api.ts`
- Create: `apps/web/src/app/admin/products/page.tsx`
- Create: `apps/web/src/app/admin/products/api.ts`
- Create: `apps/web/src/app/admin/promotions/page.tsx`
- Create: `apps/web/src/app/admin/promotions/api.ts`

Steps:
1. Create admin users page: list with role filter, role assignment UI, pagination.
   - API client: GET /admin/users, PATCH /admin/users/{id}/role
   - Follow existing page patterns (apps/web/src/app/admin/)
   - Verify: `cd apps/web && npm run build` succeeds

2. Create admin outlets page: list with name/category/territory/is_active filters, edit form, scoring display, purchase history view.
   - API client: GET /admin/outlets, PATCH /admin/outlets/{id}, GET /admin/outlets/{id}/orders, GET /admin/outlets/{id}/summary
   - Verify: `cd apps/web && npm run build` succeeds

3. Create admin products page: list, price edit form, price history view.
   - API client: GET /products, PATCH /admin/products/{id}, GET /admin/products/{id}/prices
   - Verify: `cd apps/web && npm run build` succeeds

4. Create admin promotions page: CRUD list, create/edit form, broadcast trigger button.
   - API client: GET /admin/promotions, POST /admin/promotions, PATCH /admin/promotions/{id}, DELETE /admin/promotions/{id}, POST /admin/promotions/{id}/broadcast
   - Verify: `cd apps/web && npm run build` succeeds

5. Run full frontend test suite:
   `cd apps/web && npm test`
   Expected: all tests pass

6. Commit:
   `git add apps/web/src/app/admin/`
   `git commit -m "feat(web): add admin pages for users, outlets, products, and promotions"`

[no-tdd — frontend pages are structural/UI tasks with build verification]

## REFERENCES LOADED
- spec — Frontend requirements for all admin pages
- apps/web/src/app/admin/ — existing admin page patterns
- apps/web/src/app/ — existing page layout patterns

## WHY THIS APPROACH
One page per admin domain following existing Next.js patterns. API clients follow existing axios/zustand patterns.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Frontend pages must use existing Tailwind + Zustand + axios patterns — no new UI framework]
You are implementing Admin Frontend Pages for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: One page per admin domain using existing patterns
Files in scope: apps/web/src/app/admin/users/, apps/web/src/app/admin/outlets/, apps/web/src/app/admin/products/, apps/web/src/app/admin/promotions/
Available after: T2 (users), T3 (outlets), T4 (products), T5 (promotions)
Architecture rule: Use existing Next.js 16 + Tailwind + Zustand + axios patterns
[RESTATE: Frontend pages must use existing patterns — no new UI framework]

## DELIVERABLE
Given admin, When visiting /admin/users, Then user list with role filter displayed
Given admin, When visiting /admin/outlets, Then outlet list with filters and edit form
Given admin, When visiting /admin/products, Then product list with price edit and history
Given admin, When visiting /admin/promotions, Then promotion CRUD with broadcast trigger

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Each page uses existing page layout pattern
- API clients use existing axios patterns
- Build succeeds without errors
- No new npm dependencies
- Jest render test per page where applicable (list renders, filter interaction, form submit handler mocked)

Must-not-have:
- New UI framework or component library
- Hardcoded API URLs

## STOP CONDITIONS
Done when: all admin pages created, `npm run build` succeeds, tests pass, commit created
Escalate when: build fails or API endpoints not available

---

### Task 9: Frontend — Sales Pages & Integration [depends: T6, T7]

## OBJECTIVE
Create sales frontend pages for order collection and performance dashboard, plus admin sales performance view. Includes cross-cutting integration verification.

Files:
- Create: `apps/web/src/app/sales/orders/page.tsx`
- Create: `apps/web/src/app/sales/orders/api.ts`
- Create: `apps/web/src/app/sales/performance/page.tsx`
- Create: `apps/web/src/app/sales/performance/api.ts`
- Create: `apps/web/src/app/admin/sales-performance/page.tsx`
- Create: `apps/web/src/app/admin/sales-performance/api.ts`
- Modify: `apps/web/src/app/sales/page.tsx` — add navigation links if needed

Steps:
1. Create sales orders page: order creation form for sales (outlet selector scoped to territory, product list, quantity inputs, submit).
   - API client: POST /sales/orders
   - Show territory-scoped outlets in selector
   - Verify: `cd apps/web && npm run build` succeeds

2. Create sales performance page (own): dashboard with target, achievement, percentage, order_count for current month.
   - API client: GET /sales/my-performance
   - Verify: `cd apps/web && npm run build` succeeds

3. Create admin sales performance page: view of all sales performance with period filter.
   - API client: GET /admin/sales/performance?period=YYYY-MM
   - Verify: `cd apps/web && npm run build` succeeds

4. Run full test suite (backend + frontend):
   `cd apps/api && php artisan test && cd ../web && npm test`
   Expected: all tests pass

5. Commit:
   `git add apps/web/src/app/sales/orders/ apps/web/src/app/sales/performance/ apps/web/src/app/admin/sales-performance/`
   `git commit -m "feat(web): add sales order form, sales performance dashboard, and admin sales view"`

[no-tdd — frontend pages are structural/UI tasks with build verification]

## REFERENCES LOADED
- spec — Frontend requirements for sales pages
- apps/web/src/app/sales/page.tsx — existing sales page
- apps/web/src/app/admin/sales/ — existing admin sales patterns

## WHY THIS APPROACH
Sales order form reuses existing outlet/product APIs. Performance dashboards fetch from new F5 endpoints.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Sales pages must scope outlet selector to sales user's territory — no unrestricted outlet access]
You are implementing Sales Frontend Pages for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Sales order form with territory-scoped outlets; performance dashboards
Files in scope: apps/web/src/app/sales/orders/, apps/web/src/app/sales/performance/, apps/web/src/app/admin/sales-performance/, apps/web/src/app/sales/page.tsx
Available after: T6 (sales orders + performance endpoints), T7 (promotion broadcast)
Architecture rule: Territory-scoped outlet selector; no new npm dependencies
[RESTATE: Sales outlet selector must be territory-scoped — no unrestricted outlet access]

## DELIVERABLE
Given sales user, When visiting /sales/orders, Then order form with territory-scoped outlets
Given sales user, When visiting /sales/performance, Then own performance dashboard
Given admin, When visiting /admin/sales/performance, Then all sales performance view

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Outlet selector scoped to sales user's territory
- Build succeeds without errors
- Performance data correctly displayed
- Jest render test per page where applicable

Must-not-have:
- Unrestricted outlet access for sales users
- Hardcoded performance data

## STOP CONDITIONS
Done when: all sales pages created, build succeeds, tests pass, commit created
Escalate when: build fails or territory scoping missing

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|------------------|
| T1 | Database Migrations (prereq) | none | lightweight | migrations run + rollback |
| T2 | Central Authz Policy & Role Mgmt (F1) | T1 | standard | platform_owner superset; audit; JWT invalidation; user listing |
| T3 | Outlet Profile & Lifecycle (F2) | T1 | standard | category default; scoring formula; purchase history |
| T4 | Product Price Management (F3) | T1 | standard | price update; history; snapshot frozen |
| T5 | Promotion Management (F4) | T3, T4 | standard | overlap check; min_order; snapshot; immutability |
| T6 | Sales Order & Performance (F5) | T3, T4, T5 | deep | territory binding; quota; performance views |
| T7 | WhatsApp Promotion Broadcast (F6) | T5 | standard | targeting; idempotency; message content |
| T8 | Frontend — Admin Pages | T2, T3, T4, T5 | standard | 4 admin pages build + render |
| T9 | Frontend — Sales Pages | T6, T7 | standard | sales order form + dashboards |