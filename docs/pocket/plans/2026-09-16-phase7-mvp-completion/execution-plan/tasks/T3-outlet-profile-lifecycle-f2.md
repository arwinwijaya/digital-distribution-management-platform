# Task T3 — Outlet Profile & Lifecycle (F2)

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
