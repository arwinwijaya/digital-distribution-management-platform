# Task T4 — Complete payment lifecycle and bounded payment history

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Complete payment lifecycle and bounded payment history [depends: T3]

## OBJECTIVE
Extend the existing payment transaction to update the invoice from payment rows where `status = completed` and amount is positive, authorize finance, reject invalid/conflicting payments without mutation, and return stable bounded payment history while preserving current idempotency and credit locking.

Files:
- Modify `apps/api/app/Services/PaymentService.php`, `apps/api/app/Http/Controllers/PaymentController.php`, `apps/api/app/Http/Requests/StorePaymentRequest.php`, `apps/api/app/Models/Payment.php`, and `apps/api/routes/api.php`.
- Test `apps/api/tests/Feature/InvoicePaymentTest.php` and existing `apps/api/tests/Feature/PaymentTest.php`.

Steps:
1. Regression verification — Existing Confirmed/undelivered rejection:
   Test file: `apps/api/tests/Feature/PaymentTest.php`. Level: integration. Test intent: Given an approved undelivered order, when payment is posted, then it remains rejected with no mutation. Exercise through `POST /api/payments`. Test doubles: none. This is a characterization/regression check because existing `test_payment_rejects_unauthorized_order_and_invalid_status_or_amount` already proves the rejection; it is not a RED cycle.
   Run verification: `cd apps/api && php artisan test tests/Feature/PaymentTest.php --filter=test_payment_rejects_unauthorized_order_and_invalid_status_or_amount --testdox`.
2. RED/GREEN cycle — Delivered order accepts finance payment:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given finance and a delivered unpaid invoice, when a valid payment is posted, then one completed payment is recorded and the invoice reconciles. Test doubles: no application mocks. Expected RED: finance is not authorized/invoice is not synchronized.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=finance_can_record_delivered_payment --testdox`.
   Implement finance authorization and delivered eligibility, then run the same command and verify PASS.
3. RED/GREEN cycle — Partially-paid order accepts remaining payment:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given an order already in `Partially Paid`, when finance records a valid remaining payment, then it is accepted; this is the only pre-delivery exception permitted by the architecture rule. Test doubles: none. Expected RED: eligibility is currently underspecified.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=partially_paid_order_accepts_remaining_payment --testdox`.
   Implement explicit `Delivered` or `Partially Paid` eligibility, then run the same command and verify PASS.
4. RED/GREEN cycle — Partial payment state:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given a delivered 1,000,000 invoice, when 300,000 is paid, then invoice is `partially_paid`, balance is 700,000, and due date is unchanged. Test doubles: none. Expected RED: payment updates order only.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=partial_payment_updates_invoice --testdox`.
   Implement completed-payment sum reconciliation, then run the same command and verify PASS.
5. RED/GREEN cycle — Full payment state:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given 700,000 remaining, when exact amount is paid, then invoice is `paid`, excluded from outstanding/overdue, and reminder eligibility is suppressed. Test doubles: none. Expected RED: no invoice close transition.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=full_payment_closes_invoice --testdox`.
   Implement the final state transition, then run the same command and verify PASS.
6. RED/GREEN cycle — Invalid values do not mutate:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given zero, negative, or overpayment input, when posted, then validation/conflict is returned and no payment/invoice/order mutation occurs. Test doubles: none. Expected RED: edge behavior is incomplete.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=invalid_payment_values_do_not_mutate --testdox`.
   Implement validation and cents-safe comparisons, then run the same command and verify PASS.
7. RED/GREEN cycle — Same identity replays:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given the same idempotency key and identical payload, when replayed, then it returns the existing payment without a second mutation. Test doubles: none. Expected RED: invoice-aware replay is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=same_identity_replays_without_mutation --testdox`.
   Implement exact replay semantics, then run the same command and verify PASS.
8. RED/GREEN cycle — Conflicting identity rejects:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given an idempotency key already used for another order/amount, when reused, then conflict is returned and state is unchanged. Test doubles: none. Expected RED: conflicting reuse is not independently covered.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=conflicting_identity_is_rejected --testdox`.
   Implement conflict checks, then run the same command and verify PASS.
9. RED/GREEN cycle — Concurrent same identity:
   Test file: `apps/api/tests/Feature/PaymentTest.php`. Level: integration (with concurrency scenario). Test intent: Given concurrent same-identity submissions, then one completed payment and one balance transition exist. Exercise through real HTTP/concurrency harness. Test doubles: no service/DB mocks. Expected RED: invoice synchronization may double-apply.
   Run RED: `cd apps/api && DB_CONNECTION=pgsql php artisan test tests/Feature/PaymentTest.php --filter=test_concurrent_same_identity_payment_posts_replay_one_payment --testdox`.
   Preserve lock order and make invoice sync idempotent, then run the same PostgreSQL command and verify PASS; assert one payment and the expected invoice balance/status.
10. RED/GREEN cycle — Paid invoice rejects payment:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given a paid invoice, when payment is posted, then it is rejected without mutation. Test doubles: none. Expected RED: paid-state guard absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=paid_invoice_rejects_payment --testdox`.
   Implement the paid guard, then run the same command and verify PASS.
11. RED/GREEN cycle — Cancelled invoice rejects payment:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given a cancelled invoice, when payment is posted, then it is rejected without mutation. Test doubles: none. Expected RED: cancelled-state guard absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=cancelled_invoice_rejects_payment --testdox`.
   Implement the cancelled guard, then run the same command and verify PASS.
12. RED/GREEN cycle — Valid payment reconciliation:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given positive and non-positive payment rows with `completed` and non-completed statuses, when invoice balance is reconciled, then only positive `status = completed` rows affect balance/status. Test doubles: none. Expected RED: invoice reconciliation is not implemented.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=only_completed_positive_payments_affect_balance --testdox`.
   Implement the explicit valid-payment predicate, then run the same command and verify PASS.
13. RED/GREEN cycle — Valid bounded history:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given payment rows for two outlets, when authorized finance/admin/outlet requests a valid page/limit, then stable bounded rows and metadata are returned with outlet isolation. Test doubles: none. Expected RED: current controller calls unbounded `get()`.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=payment_history_is_bounded_and_scoped --testdox`.
   Implement bounded stable pagination, then run the same command and verify PASS.
14. RED/GREEN cycle — Invalid history pagination:
   Test file: `apps/api/tests/Feature/InvoicePaymentTest.php`. Level: integration. Test intent: Given invalid page or limit, when history is requested, then 422 is returned before an unbounded query. Test doubles: none. Expected RED: no validation exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php --filter=invalid_payment_pagination_is_rejected --testdox`.
   Implement request validation, then run the same command and verify PASS.
15. Refactor while green: retain outlet→order lock ordering, receipt/idempotency helpers, and `status=completed` with positive amount as the only valid payment record; run `cd apps/api && php artisan test tests/Feature/InvoicePaymentTest.php tests/Feature/PaymentTest.php --testdox`.
16. Commit: `git add apps/api/app/Services/PaymentService.php apps/api/app/Http/Controllers/PaymentController.php apps/api/app/Http/Requests/StorePaymentRequest.php apps/api/app/Models/Payment.php apps/api/routes/api.php apps/api/tests/Feature/InvoicePaymentTest.php apps/api/tests/Feature/PaymentTest.php && git commit -m "feat(payment): reconcile invoice balances and bound history"`.

## REFERENCES LOADED
- Spec payment lifecycle, cancellation protection, idempotency, and bounded visibility GWT scenarios.
- `apps/api/app/Services/PaymentService.php` — current transaction, locks, receipt/idempotency replay, and credit-order lock coordination.
- `apps/api/app/Http/Controllers/PaymentController.php`, `StorePaymentRequest.php`, `PaymentTest.php` — current API contract and tests.

## WHY THIS APPROACH
Complexity: deep. The task changes a concurrency-sensitive financial mutation and must preserve existing locking/idempotency behavior.

## SANDWICH CONTEXT
[CRITICAL: Balance authority is the sum of payment rows with `status = completed` and positive amount inside the existing transaction and lock boundary; never trust a client-supplied or independently maintained balance.]
You are implementing invoice-aware payment recording for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — reuse `PaymentService` and existing payment ledger.
Files in scope: the files listed for Task 4.
Available after: T3 invoice lifecycle.
Architecture rule: preserve JWT/auth, order idempotency, credit locking, payment transaction boundaries, receipt generation, and the explicit `Delivered` or `Partially Paid` eligibility rule.
[RESTATE: Balance authority is the sum of payment rows with `status = completed` and positive amount inside the existing transaction and lock boundary; never trust a client-supplied or independently maintained balance.]

## DELIVERABLE
- Given a delivered unpaid invoice, when 300,000 is paid on a 1,000,000 invoice, then status is partially paid, balance is 700,000, and due date is unchanged.
- Given the remaining balance, when it is paid, then status is paid and future reminders are ineligible.
- Given zero, negative, or overpayment input, when submitted, then the request rejects without mutation; given the same idempotency identity and payload, the request replays successfully without a second mutation; given a conflicting reuse, the request rejects without mutation.
- Given a `Confirmed` undelivered invoice, when payment is submitted, then it is rejected; given an order already in `Partially Paid`, the remaining valid payment is allowed; given cancelled or paid invoice, payment is rejected.
- Given invalid page/limit, when payment history is requested, then validation returns 422 and no unbounded query is executed.
- Given concurrent same-identity submissions, then exactly one valid payment row and one balance transition exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: cents-safe comparisons, valid-payment sum, idempotent replay/conflict checks, finance authorization, stable bounded pagination, and race-test coverage.
Must-not-have: no payment gateway, refund, credit-limit redesign, or removal of existing compatible payment clients.
Open question risks: payment date semantics for metrics use existing `created_at`; report if a source system requires a different immutable payment date.
Rollback note: preserve existing payment records if invoice synchronization is rolled back.

## STOP CONDITIONS
Escalate on any race where duplicate payment rows or double balance transitions remain possible, or if preserving current credit-lock ordering is impossible.
