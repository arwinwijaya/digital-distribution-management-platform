# Task T5 — Add idempotent legacy invoice backfill

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: Add idempotent legacy invoice backfill [depends: T3]

## OBJECTIVE
Provide an admin-controlled Artisan backfill that creates/reuses one invoice for eligible legacy delivered/paid orders and derives state only from existing payment rows with `status = completed` and positive amount, without duplicating invoices or payments.

Files:
- Create `apps/api/app/Services/InvoiceBackfillService.php` and `apps/api/app/Console/Commands/BackfillInvoices.php`.
- Test `apps/api/tests/Feature/InvoiceBackfillTest.php`.

Steps:
1. RED/GREEN cycle — Eligible legacy orders:
   Test file: `apps/api/tests/Feature/InvoiceBackfillTest.php`. Level: integration. Test intent: Given delivered/paid legacy orders without invoices, when `php artisan invoices:backfill` runs, then one invoice per eligible order is created and status/balance derive only from `payments.status = completed` positive-amount records. Exercise through the real Artisan command and database. Test doubles: no application mocks. Expected RED: command/service does not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceBackfillTest.php --filter=backfill_creates_invoices_for_eligible_orders --testdox`.
   Implement chunked eligibility selection and reconciliation; define a valid payment as `payments.status = completed` with a positive amount and never create synthetic payment rows; then run the same command and verify PASS.
2. RED/GREEN cycle — Ineligible orders are skipped:
   Test file: `apps/api/tests/Feature/InvoiceBackfillTest.php`. Level: integration. Test intent: Given new/failed/cancelled/ineligible orders, when backfill runs, then no invoice is created and command output identifies skipped rows. Exercise through Artisan. Test doubles: no application mocks. Expected RED: no command selection/output exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceBackfillTest.php --filter=backfill_skips_ineligible_orders --testdox`.
   Implement explicit predicates and counters, then run the same command and verify PASS.
3. RED/GREEN cycle — Backfill retry after partial completion:
   Test file: `apps/api/tests/Feature/InvoiceBackfillTest.php`. Level: integration. Test intent: Given a partially completed prior run, when backfill runs again, then existing invoices are reused, no duplicate invoices/payments are created, and failures are visible. Exercise through repeated Artisan commands. Test doubles: no application mocks. Expected RED: no unique reuse/retry behavior exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceBackfillTest.php --filter=backfill_is_idempotent_after_partial_completion --testdox`.
   Implement unique reuse and per-row failure handling, then run the same command and verify PASS.
4. Refactor while green: keep selection predicates and counters in `InvoiceBackfillService`, and run `cd apps/api && php artisan test tests/Feature/InvoiceBackfillTest.php --testdox`.
5. Commit: `git add apps/api/app/Services/InvoiceBackfillService.php apps/api/app/Console/Commands/BackfillInvoices.php apps/api/tests/Feature/InvoiceBackfillTest.php && git commit -m "feat(invoice): add repeatable legacy backfill"`.

## REFERENCES LOADED
- Spec legacy backfill GWT scenarios and rollback plan.
- T3 `InvoiceService` contract and existing `Order`, `Payment` models.
- `apps/api/app/Console/Kernel.php` and `routes/console.php` — command registration conventions.

## WHY THIS APPROACH
Complexity: standard. It is an operational migration task whose main risk is repeatability after partial failure.

## SANDWICH CONTEXT
[CRITICAL: Backfill must be safe to rerun and must never invent or duplicate payment records.]
You are implementing the legacy invoice backfill for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend the existing ledger with invoice records.
Files in scope: the files listed for Task 5.
Available after: T3 invoice persistence/lifecycle.
Architecture rule: use additive invoice records and only existing `status = completed`, positive-amount payments; stop safely on per-row failure and retain audit output.
[RESTATE: Backfill must be safe to rerun and must never invent or duplicate payment records.]

## DELIVERABLE
- Given eligible delivered/paid orders without invoices, when backfill runs, then one invoice per order exists with state derived from valid payments.
- Given a partially completed run, when backfill reruns, then existing invoices are reused and no payment row is duplicated.
- Given an ineligible order, when backfill runs, then no invoice is created and the result identifies it as skipped.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: idempotent unique lookup, chunked bounded processing, per-row failure visibility, no synthetic payments, and explicit command exit behavior.
Must-not-have: no destructive data cleanup and no automatic invocation during web requests.
Open question risks: legacy issue date selection is an implementation detail; document the selected deterministic source in command output/tests.
Rollback note: stop the command and remove only invoices created by an identified backfill run; never delete payments.

## STOP CONDITIONS
Stop if an eligible legacy order lacks enough data to choose a deterministic issue/due date; report `NEEDS_CONTEXT` rather than guessing silently.
