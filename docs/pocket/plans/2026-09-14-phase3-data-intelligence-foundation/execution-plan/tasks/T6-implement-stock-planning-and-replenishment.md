# Task T6 — Implement stock planning and replenishment

**Phase:** 2
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Implement stock planning and replenishment [depends: T5] [test-risk]

## OBJECTIVE
Implement the `stock` pipeline stage and admin stock-planning BI over a 30-day rolling demand window. The admin endpoint reads the active `stock` section through `ActiveDataSnapshotReader` and returns its `snapshot_version` and window metadata rather than recomputing live results. Demand includes only positive order-item quantities from orders whose status is not `Cancelled`, `Canceled`, `Rejected`, or `Invalid`; use supplier lead time and the exact reorder formula, clamp negative stock to zero, mark invalid lead time `insufficient-data`, and return no reorder for zero demand/sufficient stock.

Files:
- Create: `apps/api/app/Services/StockPlanningService.php`
- Create: `apps/api/app/Http/Controllers/StockPlanningController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/StockPlanningTest.php`

Steps:
1. Write failing test for: SKU receives a reorder recommendation
   Test file: `apps/api/tests/Feature/StockPlanningTest.php`
   Level: feature
   Test intent: Given a published active `stock` section for a SKU has positive order-item quantities totaling exactly 60 units from eligible orders in the rolling 30-day window, excluded-status orders (`Cancelled`, `Canceled`, `Rejected`, `Invalid`) contribute no quantity, supplier `lead_time_days` is 5, and available stock is 3, When an admin requests stock planning, Then average daily demand is `60/30 = 2 units/day`, the lead-time demand is `2 × 5 = 10`, suggested reorder quantity is `max(0, 10 − 3) = 7`, the SKU has a stockout/reorder warning, and the response includes the active `snapshot_version` and window metadata.
   Exercise through: the real `StockPlanningService` stage, the public `DataPipelineService` publication entry point, and the admin stock-planning HTTP consumer backed by `ActiveDataSnapshotReader`; do not bypass publication or recompute live data in the endpoint.
   Test doubles: mock none; use real product/supplier/order-item records and database; do not mock the stage, publisher, reader, service or controller.
   Expected RED: route/stage/reader contract is missing, active version/window fields are absent, or the exact formula output does not exist.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sku_receives_a_reorder_recommendation'`
   Expected failure: missing route or reorder fields/formula.
3. Implement minimal code: add the `stock` stage method, sum only positive quantities on order items whose parent order status is not `Cancelled`, `Canceled`, `Rejected`, or `Invalid` within the rolling 30-day window, divide by 30, apply `max(0, average daily demand × lead_time_days − available stock)`, persist the typed stage output, inject `ActiveDataSnapshotReader` into the admin consumer, and return warning/reorder fields plus active `snapshot_version`/window metadata without live recomputation.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sku_receives_a_reorder_recommendation'`
   Expected: PASS; the real stage publishes the `stock` section and the reader-backed consumer returns active `snapshot_version`/window metadata with reorder quantity 7.
5. Refactor while green (bounded): keep demand and reorder arithmetic in the service and decimal/integer-safe; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sku_receives_a_reorder_recommendation'` and require Expected: PASS.
6. Commit: `git add apps/api/app/Services/StockPlanningService.php apps/api/app/Http/Controllers/StockPlanningController.php apps/api/routes/api.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "feat(analytics): add stock replenishment planning"`

7. Write failing test for: sufficient stock or zero demand produces no reorder
   Test file: `apps/api/tests/Feature/StockPlanningTest.php`
   Level: feature
   Test intent: Given a SKU has sufficient available stock or zero demand, When stock planning is generated, Then no reorder action is recommended.
   Exercise through: stock-planning HTTP endpoint with both fixtures.
   Test doubles: mock none; use real database records; do not mock the service.
   Expected RED: implementation emits a reorder for all SKUs or treats zero demand as shortage.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sufficient_stock_or_zero_demand_produces_no_reorder'`
   Expected failure: reorder action is present.
9. Implement minimal code: return explicit no-action status and zero/non-action quantity for sufficient stock and zero demand.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sufficient_stock_or_zero_demand_produces_no_reorder'`
    Expected: PASS.
11. Refactor while green (bounded): preserve separate demand/stock/action fields; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sufficient_stock_or_zero_demand_produces_no_reorder'` and require Expected: PASS.
12. Commit: `git add apps/api/app/Services/StockPlanningService.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "test(analytics): cover no-action stock plans"`

13. Write failing test for: invalid stock-planning input is safe
   Test file: `apps/api/tests/Feature/StockPlanningTest.php`
   Level: feature
   Test intent: Given a SKU has negative stock or a null/negative supplier lead time, When stock planning is generated, Then negative stock is treated as zero and invalid lead time produces insufficient-data with no false reorder quantity.
   Exercise through: stock-planning HTTP endpoint with invalid stock/lead-time fixtures.
   Test doubles: mock none; use real models/database; do not mock service/controller.
   Expected RED: negative stock leaks into formula or invalid lead time creates a false reorder.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='invalid_stock_planning_input_is_safe'`
   Expected failure: negative or non-null reorder quantity assertion fails.
15. Implement minimal code: clamp stock at zero, validate lead time > 0, and return insufficient-data/no recommendation for invalid lead time.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='invalid_stock_planning_input_is_safe'`
    Expected: PASS.
17. Refactor while green (bounded): centralize input normalization in `StockPlanningService` and keep null/negative semantics explicit; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='invalid_stock_planning_input_is_safe'` and require Expected: PASS.
18. Commit: `git add apps/api/app/Services/StockPlanningService.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "fix(analytics): guard invalid stock planning inputs"`

19. Write failing test for: stock-planning endpoint is admin-only
    Test name: `stock_planning_endpoint_is_admin_only`
    Test file: `apps/api/tests/Feature/StockPlanningTest.php`
    Level: feature
    Test intent: Given an unauthenticated caller, an authenticated non-admin outlet user, and an authenticated admin, When each requests the stock-planning endpoint, Then unauthenticated access returns HTTP 401, non-admin access returns HTTP 403 with the existing JSON authorization contract, and the admin receives stock-planning data; neither unauthorized request may expose stock fields.
    Exercise through: the real stock-planning HTTP route and auth middleware; do not call `StockPlanningService` directly.
    Test doubles: mock none; use real auth middleware, users, products, suppliers and database records; do not mock authorization, controller or service.
    Expected RED: the route is absent, lacks admin protection, or unauthorized responses/data differ from the existing contract.
20. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='stock_planning_endpoint_is_admin_only'`
    Expected failure: route not found, non-admin/unauthenticated status is not 401/403, or unauthorized response contains stock-planning data.
21. Implement minimal code: apply the existing admin request-boundary middleware/policy to the stock-planning route and preserve the existing 401/403 JSON authorization contract; do not change legacy AI authorization.
22. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='stock_planning_endpoint_is_admin_only'`
    Expected: PASS; unauthenticated is 401, non-admin is 403, admin succeeds, and unauthorized responses contain no stock data.
23. Refactor while green (bounded): keep authorization at the HTTP/controller boundary and leave demand/formula semantics in `StockPlanningService`; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='stock_planning_endpoint_is_admin_only'` and require `Expected: PASS`.
24. Commit: `git add apps/api/app/Http/Controllers/StockPlanningController.php apps/api/routes/api.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "fix(analytics): restrict stock planning to admins"`

## REFERENCES LOADED
- Stock planning GWT scenarios and exact formula/invalid-input rules from the spec.
- Demand eligibility: only positive order-item quantities from non-`Cancelled`/`Canceled`/`Rejected`/`Invalid` orders inside the rolling 30-day window; fixture contract `60 units / 30 days = 2/day`, `lead_time_days = 5`, `stock = 3`, expected reorder `7`.
- `apps/api/app/Models/Product.php`, `Supplier.php`, `OrderItem.php`, `Order.php` — stock, supplier and demand source conventions.
- `apps/api/app/Services/AnalyticsService.php` — rolling aggregate and money-safe arithmetic patterns.
- `apps/api/tests/Feature/ProductTest.php`, `OrderTest.php` — product/order fixture conventions.

## WHY THIS APPROACH
The stock service is isolated from supplier scoring so formula and insufficient-data behavior can be verified without opaque coupling. Complexity: standard/test-risk due to rolling demand, invalid numeric inputs and action thresholds.

## SANDWICH CONTEXT
[CRITICAL: Reorder quantity is exactly `max(0, average daily demand × supplier lead_time_days − available stock)`; null/negative lead time must never produce a recommendation.]
You are implementing stock planning for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: deterministic orchestrated snapshot with admin-readable BI output.
Files in scope: only files listed in this task.
Test framework: Laravel PHPUnit feature tests against real demand/product/supplier records.
Available after: T5 supplier contracts.
Architecture rule: 30-day window, decimal/integer-safe arithmetic, admin authorization at controller boundary.
[RESTATE: Reorder quantity is exactly `max(0, average daily demand × supplier lead_time_days − available stock)`; null/negative lead time must never produce a recommendation.]

## DELIVERABLE
Given positive demand, valid lead time and insufficient stock, When planning runs, Then warning and non-negative formula quantity are returned.
Given sufficient stock or zero demand, When planning runs, Then no reorder action is returned.
Given negative stock or invalid lead time, When planning runs, Then stock is clamped and invalid lead time is insufficient-data with no false quantity.
All four scenario cycles PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Exact 30-day demand and reorder formula.
- Negative stock clamp; null/negative lead time insufficient-data.
- Zero-demand/sufficient-stock no-action behavior.
- Admin-only endpoint and TDD order.

Must-not-have:
- No negative reorder quantity, fabricated lead-time fallback, automatic reorder execution, or changes to product ordering behavior.

Open question risks:
- If the source order-item status eligibility is ambiguous, stop for context rather than counting cancelled/invalid demand.

Rollback note:
- Disable stock-planning route/stage and retain last successful snapshot.

## STOP CONDITIONS
Done when: all four scenario cycles pass, including unauthenticated/non-admin 401/403 assertions, and commits exist.
Uncertain when: demand source status eligibility cannot be determined from existing semantics.
Escalate when: implementation sends orders or silently defaults invalid lead time.
