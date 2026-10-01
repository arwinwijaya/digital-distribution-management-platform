# Task T4 — Invoice detail endpoint with authorization and content

**Phase:** 1
**Depends:** T1, T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Invoice detail endpoint with authorization and content [depends: T1, T2]

## OBJECTIVE
Provide click-to-detail JSON that respects outlet ownership and returns frozen names, full payment history, stored totals, and overdue badge.

Steps:
1. Write failing test for: detail authorization matrix
   Test file: `apps/api/tests/Feature/InvoiceDetailAuthorizationTest.php`
   Level: integration
   Test intent: Given outlet user outlet_id=5 with invoices:read / When GET /invoices/123 owned by 5 Then 200; When GET /invoices/999 owned by 9 Then 403; Given no token When GET /invoices/123 Then 401; Given valid token When GET /invoices/555555 Then 404
   Exercise through: HTTP GET /invoices/{id} via Rbac + controller
   Test doubles: none (uses factories for invoices/orders/outlets); do NOT mock InvoiceService
   Expected RED: route returns 404 for all ids
2. Run test — verify FAIL: `php artisan test --filter=InvoiceDetailAuthorizationTest`
3. Implement route GET /invoices/{id} with middleware auth:api + rbac:invoices:read, controller method InvoiceController@show delegating to InvoiceService::getDetail, enforcing outlet ownership (outlet users only see own outlet_id, admin/finance see all), returning 403 for cross-outlet, 404 for missing; verify PASS; refactor while green; commit

4. Write failing test for: detail content uses snapshot, full payments, stored totals, badges
   Test file: `apps/api/tests/Feature/InvoiceDetailContentTest.php`
   Level: integration
   Test intent: Given invoice created when product was Sabun A then product renamed to Sabun B / When GET detail Then line item shows Sabun A (snapshot); Given invoice with 3 payments completed/pending/failed / When GET detail Then all 3 returned ordered created_at DESC; Given stored totals / When GET detail Then total_amount/paid_amount/balance_amount equal stored snapshot not recomputed; Given due_date < today and balance >0 / When GET detail Then overdue badge true; Given status cancelled / When GET detail Then status cancelled and totals still stored
   Exercise through: HTTP GET /invoices/{id} JSON shape (header, line items, payments, outlet info)
   Test doubles: none; use factories with product_name_snapshot
   Expected RED: returns live product name, filters payments, or recomputes totals
5. Run test — verify FAIL: `php artisan test --filter=InvoiceDetailContentTest`
6. Implement InvoiceService::getDetail to eager load order.orderItems.product, payments, outlet, map line items from product_name_snapshot fallback, order payments by created_at DESC without status filter, return stored totals, compute overdue badge as due_date < today && balance_amount > 0, surface status; verify PASS; refactor while green extracting badge helper; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Authorization + Content, Architecture Constraints (read-only detail, preserve invoices:read), verified schema for order_items snapshot

## WHY THIS APPROACH
Complexity: standard
Justification: Authz plus content mapping across multiple relations; needs careful outlet scoping and snapshot handling.

## SANDWICH CONTEXT
[CRITICAL: Cross-outlet detail must return 403 and must not leak data; read-only, no change to issuance/cancellation]
You are implementing invoice detail endpoint for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/routes/api.php, apps/api/app/Http/Controllers/InvoiceController.php, apps/api/app/Services/InvoiceService.php, apps/api/app/Models/Invoice.php, apps/api/app/Models/OrderItem.php
Available after: T1 (template table), T2 (snapshot column)
Architecture rule: Route is read-only GET with rbac:invoices:read; outlet scoping must match existing list endpoint; overdue badge is computed dynamically but totals are stored snapshots
[RESTATE: Cross-outlet detail must return 403 and must not leak data; read-only, no change to issuance/cancellation]

## DELIVERABLE
Given outlet user accessing own invoice, When GET /invoices/{id}, Then 200 with header, frozen line items, payment history, outlet info
Given outlet accessing another outlet's invoice, When GET, Then 403
Given unauthenticated, When GET, Then 401
Given missing id, When GET, Then 404
Given product rename after invoice, When GET detail, Then line item shows frozen snapshot name
Given multiple payments, When GET detail, Then all statuses returned newest first
Given stored totals, When GET detail, Then totals equal snapshot
Given overdue condition, When GET detail, Then badge true; Given cancelled, Then status cancelled with stored totals
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - RBAC via rbac:invoices:read
  - Outlet ownership check
  - Snapshot, full payment history, stored totals, overdue badge logic

Must-not-have:
  - Recomputed totals
  - Filtered payments
  - Modification of invoice/order data

Open question risks:
  - none

Rollback note:
  - Removing route restores prior 404 behavior

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Outlet scoping diverges from list endpoint
Escalate when: Constraint violated
