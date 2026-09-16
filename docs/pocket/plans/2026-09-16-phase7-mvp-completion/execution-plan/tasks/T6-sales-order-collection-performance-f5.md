# Task T6 — Sales Order Collection & Performance (F5)

**Phase:** 2
**Depends:** T3, T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
