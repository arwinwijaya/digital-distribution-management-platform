# Task T4 — Product Price Management (F3)

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
