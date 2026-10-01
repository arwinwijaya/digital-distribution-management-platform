# Task T2 — Add product_name_snapshot column to order_items

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Add product_name_snapshot column to order_items [prereq]

**OBJECTIVE**
Add the frozen product-name snapshot column that detail and PDF will read, verifying the real schema today has no such column.

Steps:
1. Write failing test for: order_items has product_name_snapshot column
   Test file: `apps/api/tests/Feature/OrderItemSnapshotColumnTest.php`
   Level: integration
   Test intent: Given a fresh migrated database / When inspecting order_items schema / Then column `product_name_snapshot` exists as nullable string and `OrderItem` model `fillable` includes it
   Exercise through: Schema inspection via Laravel's Schema facade and model `$fillable`
   Test doubles: none (uses in‑memory SQLite test DB)
   Expected RED: column does not exist and model lacks fillable entry
2. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotColumnTest`
3. Implement migration `xxxx_xx_xx_add_product_name_snapshot_to_order_items_table.php` adding nullable string `product_name_snapshot` after `product_id`, update `app/Models/OrderItem.php` `$fillable` and `casts` to include the new column, backfill existing rows from the related `products.name` where null; verify PASS; refactor while green to extract column constant; commit

4. Write failing test for: snapshot is populated at order creation and survives product rename
   Test file: `apps/api/tests/Feature/OrderItemSnapshotPopulateTest.php`
   Level: integration
   Test intent: Given a product named `Sabun A` and an order created for it / When the order is persisted via `OrderCreationService::persistOrder` / Then the resulting `order_items.product_name_snapshot` equals `Sabun A` and does not change after the product is renamed to `Sabun B`
   Exercise through: Create product, create order via `OrderCreationService->create(...)`, then rename product and fetch order items
   Test doubles: none; use real services (no mocking of OrderCreationService)
   Expected RED: snapshot column remains null or reflects renamed product name
5. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotPopulateTest`
6. Implement snapshot population directly in `app/Services/OrderCreationService::persistOrder` after each `OrderItem::create` call, setting `product_name_snapshot` to `$product->name` (retrieved via `$order->items->first()->product->name` or via the `$items` array prepared earlier). Ensure existing backfill logic runs for legacy rows.
   Verify PASS; refactor while green to extract a helper `populateProductSnapshot(OrderItem $item, Product $product)`; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Content (frozen product name), Architecture Constraints (new column via migration), verified schema: order_items today has no snapshot column

## WHY THIS APPROACH
Complexity: lightweight
Justification: Schema addition isolated to order_items; must exist before detail/PDF read it.

## SANDWICH CONTEXT
[CRITICAL: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]
You are implementing product_name_snapshot for Invoice Detail, PDF Export \& Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/database/migrations/*add_product_name_snapshot*, apps/api/app/Models/OrderItem.php, apps/api/app/Services/OrderCreationService.php (persistOrder method), apps/api/database/factories/OrderItemFactory.php
Available after: none (prereq)
Architecture rule: Additive migration only; nullable column so existing rows remain valid; down drops column
[RESTATE: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]

## DELIVERABLE
Given migrated DB, When inspecting order_items, Then `product_name_snapshot` column exists and is fillable
Given a product `Sabun A`, When an order is created via `OrderCreationService::persistOrder` / `OrderCreationService->create`, Then `order_items.product_name_snapshot` equals `Sabun A` and does not change after product rename
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Nullable string column `product_name_snapshot`
  - Backfill for existing rows; creation path populates snapshot from product name

Must-not-have:
  - Recomputing snapshot on every read; changes to invoice totals or payment logic

Open question risks:
  - none

Rollback note:
  - migrate:rollback drops column; snapshot code can be removed without data loss

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Order creation path is not the one that ultimately creates `order_items`; if so, create an observer instead.
Escalate when: Constraint violated.


## OBJECTIVE
Add the frozen product-name snapshot column that detail and PDF will read, verifying the real schema today has no such column.

Steps:
1. Write failing test for: order_items has product_name_snapshot column
   Test file: `apps/api/tests/Feature/OrderItemSnapshotColumnTest.php`
   Level: integration
   Test intent: Given migrated database / When inspecting order_items schema / Then column product_name_snapshot exists as nullable string and OrderItem fillable includes it
   Exercise through: Schema::hasColumn and model fillable
   Test doubles: none (uses test DB)
   Expected RED: column does not exist
2. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotColumnTest`
3. Implement migration `xxxx_xx_xx_add_product_name_snapshot_to_order_items_table.php` adding nullable string product_name_snapshot after product_id, update `app/Models/OrderItem.php` fillable and casts, backfill existing rows from products.name where null; verify PASS; refactor while green; commit

4. Write failing test for: new order_items snapshot is populated from product name at creation
   Test file: `apps/api/tests/Feature/OrderItemSnapshotPopulateTest.php`
   Level: integration
   Test intent: Given a product named Sabun A / When an order_item is created for that product / Then product_name_snapshot equals Sabun A and survives a later product rename to Sabun B
   Exercise through: OrderItem factory / creation path + Product update
   Test doubles: none (uses factories); do NOT mock OrderItem
   Expected RED: snapshot column stays null or follows rename
5. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotPopulateTest`
6. Implement snapshot population in order creation service/observer (set product_name_snapshot from product.name on create if null); verify PASS; refactor while green to extract helper; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Content (frozen product name), Architecture Constraints (new column via migration), verified schema: order_items today has no snapshot column

## WHY THIS APPROACH
Complexity: lightweight
Justification: Schema addition isolated to order_items; must exist before detail/PDF read it.

## SANDWICH CONTEXT
[CRITICAL: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]
You are implementing product_name_snapshot for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/database/migrations/*add_product_name_snapshot*, apps/api/app/Models/OrderItem.php, apps/api/app/Services/OrderService.php or OrderItem observer, apps/api/database/factories/OrderItemFactory.php
Available after: none (prereq)
Architecture rule: Additive migration only; nullable column so existing rows remain valid; down drops column
[RESTATE: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]

## DELIVERABLE
Given migrated DB, When inspecting order_items, Then product_name_snapshot column exists and is fillable
Given a product Sabun A, When an order_item is created, Then snapshot equals Sabun A and does not change when product is renamed to Sabun B
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Nullable string column product_name_snapshot
  - Backfill for existing rows
  - Creation path populates snapshot from product.name

Must-not-have:
  - Recomputing snapshot on every read
  - Changes to invoice totals or payment logic

Open question risks:
  - none

Rollback note:
  - migrate:rollback drops column

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Order creation path is not the one that ultimately creates order_items
Escalate when: Constraint violated
