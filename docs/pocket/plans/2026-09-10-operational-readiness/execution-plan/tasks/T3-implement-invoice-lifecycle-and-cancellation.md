# Task T3 — Implement invoice lifecycle and cancellation

**Phase:** 2
**Depends:** T1, T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Implement invoice lifecycle and cancellation [depends: T1, T2]

## OBJECTIVE
Create one invoice atomically with approved orders, expose bounded role-scoped invoice history, make approval retry idempotent, apply immutable due dates, and implement admin-only cancellation for unpaid invoices with payment protection.

Files:
- Create `apps/api/app/Services/InvoiceService.php`, `apps/api/app/Http/Controllers/InvoiceController.php`, and `apps/api/app/Http/Requests/CancelOrderRequest.php`.
- Modify `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/app/Models/Order.php`, and `apps/api/routes/api.php`.
- Test `apps/api/tests/Feature/InvoiceTest.php`.

Steps:
1. RED/GREEN cycle — Configured-term invoice creation:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given a confirmed order and 14-day outlet term, when authenticated admin approval completes, then exactly one invoice exists with issue date and due date 14 days apart and contributes to outstanding. Exercise through `PUT /api/orders/{id}/approve`; do not call `InvoiceService` directly. Test doubles: fake only post-commit WhatsApp client if needed; do not mock DB/order/invoice. Expected RED: approval currently creates no invoice.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=approved_order_creates_invoice_with_configured_term --testdox`.
   Implement the minimal transactional invoice creation, then run the same command and verify PASS.
2. RED/GREEN cycle — Seven-day fallback:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given no outlet term, when approval creates an invoice, then due date is seven days after issue date. Exercise through approval HTTP. Test doubles: fake only WhatsApp. Expected RED: no fallback exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=missing_payment_term_uses_seven_day_fallback --testdox`.
   Implement fallback, then run the same command and verify PASS.
3. RED/GREEN cycle — Term changes affect new invoices only:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given an unpaid invoice and changed outlet term, when a later invoice is issued, then the existing due date is unchanged and only the later invoice uses the new term. Exercise through approval HTTP. Test doubles: fake only WhatsApp. Expected RED: no immutable term snapshot exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=term_changes_affect_new_invoices_only --testdox`.
   Implement immutable issue/due snapshots, then run the same command and verify PASS.
4. RED/GREEN cycle — Approval retry:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given an already approved order with an invoice, when approval is retried, then the existing invoice is returned and no duplicate status history is appended. Exercise through approval HTTP. Test doubles: fake only WhatsApp. Expected RED: current retry returns an error.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=approval_retry_reuses_invoice --testdox`.
   Implement retry-safe create-or-reuse, then run the same command and verify PASS.
5. RED/GREEN cycle — Concurrent approvals:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration (with concurrency scenario). Test intent: Given concurrent approval requests, when both contend for the order, then one invoice and one confirmed history transition exist. Exercise through real concurrent HTTP requests and the barrier. Test doubles: fake only WhatsApp; do not mock locks/transactions. Expected RED: concurrency is not invoice-aware.
   Run RED: `cd apps/api && DB_CONNECTION=pgsql php artisan test tests/Feature/InvoiceTest.php --filter=concurrent_approvals_create_one_invoice --testdox`.
   Implement row-locked invoice creation, then run the same PostgreSQL command and verify PASS; assert one invoice and one confirmed status-history row.
6. RED/GREEN cycle — Valid scoped invoice history:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given invoices for outlets A and B, when an authorized admin/finance/outlet requests valid page/limit, then rows are bounded, stable, scoped, and metadata is returned. Exercise through `GET /api/invoices`. Test doubles: none. Expected RED: endpoint does not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=invoice_history_is_bounded_and_scoped --testdox`.
   Implement query/serializer, then run the same command and verify PASS.
7. RED/GREEN cycle — Invalid invoice pagination:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given invalid page or limit, when invoice history is requested, then 422 is returned before an unbounded query. Exercise through `GET /api/invoices`. Test doubles: none. Expected RED: no pagination validation exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=invalid_invoice_pagination_is_rejected --testdox`.
   Implement request validation/bounds, then run the same command and verify PASS.
8. RED/GREEN cycle — Admin cancels unpaid invoice:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given an approved unpaid invoice, when authenticated admin cancels it, then order/invoice become cancelled and active state excludes them. Exercise through admin-only cancellation HTTP. Test doubles: none. Expected RED: route/transaction absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=admin_can_cancel_unpaid_invoice --testdox`.
   Implement cancellation transaction, then run the same command and verify PASS.
9. RED/GREEN cycle — Payment row blocks cancellation:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given any payment row exists, when admin cancellation is attempted, then conflict is returned and order/invoice/payment state is unchanged. Test doubles: none. Expected RED: payment guard absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=payment_row_prevents_cancellation --testdox`.
   Implement the any-row guard, then run the same command and verify PASS.
10. RED/GREEN cycle — Non-admin cancellation forbidden:
   Test file: `apps/api/tests/Feature/InvoiceTest.php`. Level: integration. Test intent: Given a non-admin authenticated user, when cancellation is requested, then 403 is returned and no state changes. Test doubles: none. Expected RED: route authorization absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --filter=non_admin_cannot_cancel_order --testdox`.
   Implement admin-only authorization, then run the same command and verify PASS.
11. Refactor while green: keep transaction/state/formatting rules in `InvoiceService`, avoid duplicated scope predicates, and run `cd apps/api && php artisan test tests/Feature/InvoiceTest.php --testdox`.
12. Commit: `git add apps/api/app/Services/InvoiceService.php apps/api/app/Http/Controllers/InvoiceController.php apps/api/app/Http/Requests/CancelOrderRequest.php apps/api/app/Http/Controllers/OrderController.php apps/api/app/Models/Order.php apps/api/routes/api.php apps/api/tests/Feature/InvoiceTest.php && git commit -m "feat(invoice): add approval lifecycle and cancellation"`.

## REFERENCES LOADED
- Spec invoice creation, due date, cancellation, and visibility GWT scenarios.
- `apps/api/app/Http/Controllers/OrderController.php` — existing locked approval transaction and post-commit WhatsApp call.
- `apps/api/app/Models/Order.php`, `Outlet.php`, and existing `OrderTest.php` — status history and outlet scoping.

## WHY THIS APPROACH
Complexity: deep. Approval idempotency, transactional state, immutable terms, pagination, authorization, and cancellation all meet at one seam.

## SANDWICH CONTEXT
[CRITICAL: Invoice creation must be in the same transactional approval boundary as the order status transition and must be unique per order.]
You are implementing the invoice lifecycle for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend existing order flow.
Files in scope: the files listed for Task 3.
Available after: T1 persistence and T2 current-role authorization/terms.
Architecture rule: preserve order idempotency, row locking, status history, and post-commit WhatsApp failure isolation.
[RESTATE: Invoice creation must be in the same transactional approval boundary as the order status transition and must be unique per order.]

## DELIVERABLE
- Given a confirmed order and 14-day term, when approval completes, then one invoice is issued with a due date 14 days after issue date.
- Given no term, when approval completes, then due date is seven days after issue date.
- Given a term change, when an existing invoice is read, then its due date is unchanged and only later invoices use the new term.
- Given approval retry or concurrent approval, then the existing invoice is reused and no second invoice/history mutation is created.
- Given an unpaid approved invoice, when an authenticated admin uses the cancellation route, then order/invoice become cancelled and active metrics exclude it; given any payment row exists, cancellation returns a conflict and state is unchanged.
- Given outlet A requests history, then outlet B records never appear; invalid page/limit returns 422 before query execution.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: unique invoice/order identity, immutable issue/due dates, stable bounded pagination, current-role authorization, transactional approval/cancellation, and preserved status history.
Must-not-have: no invoice PDF, refund, gateway, broad role redesign, or deletion of payment records.
Open question risks: invoice public identifier format is implementation-defined; report if a new public format is needed.
Rollback note: stop any dependent backfill before reverting; do not delete existing payment data.

## STOP CONDITIONS
Escalate if approval cannot be made retry-safe without changing the existing order idempotency contract, or if cancellation requires deleting records.
