# Task T10 — Verify the complete operational readiness slice end to end

**Phase:** 3
**Depends:** T5, T6, T7, T8, T9
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 10: Verify the complete operational readiness slice end to end [depends: T5, T6, T7, T8, T9] [test-risk]

## OBJECTIVE
Prove the full user-visible path through the real Laravel HTTP boundary: finance assignment, approval-to-invoice, delivery proof, payment lifecycle, reminders, bounded history, metrics, and isolation/idempotency.

Files:
- Create `apps/api/tests/Feature/OperationalReadinessTest.php`.
- No production files are expected to change. If an integration failure exposes a real defect, return to the owning task rather than patching production behavior here.

Steps:
1. RED/GREEN cycle — Finance assignment through HTTP:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E through Laravel HTTP routes. Test intent: Given active admin/target users, when finance is assigned through HTTP, then exactly one role and audit exist. Test doubles: fake only WhatsApp; no application mocks. Expected RED: cross-unit role flow is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_finance_assignment_is_audited --testdox`.
   Route failures to T2, then run the same command and verify PASS.
2. RED/GREEN cycle — Old token removal:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given a finance token issued before removal, when the role is removed and the token is reused, then the next finance request is forbidden. Test doubles: fake only WhatsApp. Expected RED: current-role invalidation seam is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_removed_finance_role_denies_old_token --testdox`.
   Route failures to T2, then run the same command and verify PASS.
3. RED/GREEN cycle — Approval creates one invoice:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given an order and configured term, when approval is posted and retried, then one invoice and one confirmed history transition are visible. Test doubles: fake only WhatsApp. Expected RED: approval/invoice seam is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_approval_retry_creates_one_invoice --testdox`.
   Route failures to T3, then run the same command and verify PASS.
4. RED/GREEN cycle — Proof rejection at HTTP boundary:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given an in-progress delivery, when delivered is posted without proof, then HTTP validation fails and delivery remains in progress. Test doubles: fake only WhatsApp; no delivery/request mocks. Expected RED: API accepts missing proof.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_delivery_rejects_missing_proof --testdox`.
   Route failures to T8, then run the same command and verify PASS.
5. RED/GREEN cycle — Completion and partial payment:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given valid proof, when delivery completes and finance records a partial payment, then delivery is delivered and invoice is partially paid with correct balance. Test doubles: fake only WhatsApp. Expected RED: delivery/payment/invoice collaboration is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_delivery_and_partial_payment --testdox`.
   Route failures to T4/T8, then run the same command and verify PASS.
6. RED/GREEN cycle — Full payment and cancellation guard:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given a partial invoice, when final payment is recorded, then it is paid; given any payment, when cancellation is attempted, then it is rejected. Test doubles: fake only WhatsApp. Expected RED: invoice/payment/cancellation seam is incomplete.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_full_payment_and_cancellation_guard --testdox`.
   Route failures to T3/T4, then run the same command and verify PASS.
7. RED/GREEN cycle — Reminder command and provider failure:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given eligible unpaid invoice and failing provider, when Artisan reminder processing repeats, then one event identity/bounded failure audit exists and invoice state is unchanged. Test doubles: fake only `WhatsAppClient`; no app mocks. Expected RED: scheduler/provider seam is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_reminder_retry_is_bounded_and_immutable --testdox`.
   Route failures to T6, then run the same command and verify PASS.
8. RED/GREEN cycle — Metrics and bounded histories:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given operational records across outlets and dates, when finance requests metrics/invoice/payment/reminder histories, then all six groups, zero-safe values, bounded pagination, and outlet isolation are returned. Test doubles: fake only WhatsApp. Expected RED: cross-unit metrics/history contract is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_metrics_and_histories_are_bounded_scoped --testdox`.
   Route failures to T3/T4/T6/T7, then run the same command and verify PASS.
9. RED/GREEN cycle — Repeated command/request duplicate safety:
   Test file: `apps/api/tests/Feature/OperationalReadinessTest.php`. Level: E2E. Test intent: Given repeated approval/payment/reminder requests, when each is retried, then no duplicate invoice/payment/reminder mutation occurs. Test doubles: fake only WhatsApp. Expected RED: complete idempotency seam is not proven.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --filter=e2e_retries_do_not_duplicate_operational_records --testdox`.
   Route failures to T3/T4/T6, then run the same command and verify PASS.
10. Refactor while green: remove only duplicate fixture setup inside the test file, retain named scenario assertions, and run `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php tests/Feature/InvoiceTest.php tests/Feature/InvoicePaymentTest.php tests/Feature/InvoiceBackfillTest.php tests/Feature/InvoiceReminderTest.php tests/Feature/InvoiceMetricsTest.php tests/Feature/DeliveryTest.php tests/Feature/OperationalReadinessTest.php --testdox`.
11. Commit: `git add apps/api/tests/Feature/OperationalReadinessTest.php && git commit -m "test(operations): verify readiness slice end to end"`.

## REFERENCES LOADED
- Entire approved spec and all prior task contracts in this plan.
- `apps/api/tests/Feature/Phase1IntegrationTest.php`, `PaymentTest.php`, `OrderTest.php`, `DeliveryTest.php`, `AnalyticsTest.php` — existing HTTP/concurrency test patterns.
- `apps/api/tests/Support/http_request.php` and `apps/web/order-flow.test.js` — real-boundary E2E conventions.

## WHY THIS APPROACH
Complexity: deep and `[test-risk]`. The acceptance criteria explicitly require collaboration across auth, order, invoice, delivery, payment, reminders, metrics, and external WhatsApp transport; unit tests cannot prove the seam.

## SANDWICH CONTEXT
[CRITICAL: This task verifies collaboration only; do not add production code or bypass real authorization/transaction/provider boundaries to make the test pass.]
You are verifying the complete Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend existing operational flow.
Files in scope: `apps/api/tests/Feature/OperationalReadinessTest.php` only.
Available after: T5, T6, T7, T8, and T9.
Architecture rule: use real Laravel HTTP/Artisan boundaries and fake only the external WhatsApp client; route production defects to their owning packet.
[RESTATE: This task verifies collaboration only; do not add production code or bypass real authorization/transaction/provider boundaries to make the test pass.]

## DELIVERABLE
- Given the complete operational setup, when the E2E scenario runs, then approval, invoice, proof-gated delivery, finance payment, reminders, metrics, bounded history, and role/outlet isolation all pass together.
- Given duplicate requests or repeated commands, then no duplicate invoice/payment/reminder mutation occurs.
- Given provider failure, then the E2E result shows bounded audited failure with invoice state unchanged.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: real route/command boundaries, authenticated role transitions, database assertions, external provider fake only, complete spec scenario coverage, and no production changes hidden in the integration task.
Must-not-have: no shortcuts through service calls, disabled authorization, mocked application units, or ignored failing scenarios.
Open question risks: if the E2E setup exposes a changed identifier or dashboard contract, stop and report rather than silently widening scope.
Rollback note: remove only the new test if backend/UI rollback occurs; retain operational records.

## STOP CONDITIONS
Escalate if the full flow cannot run with the existing test infrastructure, if a production defect has no owning packet, or if passing requires an out-of-scope architecture change.
