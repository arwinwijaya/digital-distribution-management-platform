# Task T1 — Create operational data foundation and shared domain records

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Create operational data foundation and shared domain records [prereq]

## OBJECTIVE
Create the additive schema and Eloquent contracts required by invoices, reminders, outlet terms, and audited finance-role assignment. No endpoint or workflow behavior is implemented in this task.

Files:
- Create `apps/api/database/migrations/2026_09_10_000019_add_payment_term_days_to_outlets_table.php`.
- Create `apps/api/database/migrations/2026_09_10_000020_create_invoices_table.php`.
- Create `apps/api/database/migrations/2026_09_10_000021_create_invoice_reminders_table.php`.
- Create `apps/api/database/migrations/2026_09_10_000022_create_role_assignment_audits_table.php`.
- Create `apps/api/app/Models/Invoice.php`, `apps/api/app/Models/InvoiceReminder.php`, `apps/api/app/Models/RoleAssignmentAudit.php`.
- Create `apps/api/database/factories/InvoiceFactory.php` and `apps/api/database/factories/InvoiceReminderFactory.php`.
- Modify `apps/api/app/Models/Outlet.php`, `apps/api/app/Models/Order.php`, and `apps/api/app/Models/User.php` only for relationships, casts, and role predicate support.
- Test `apps/api/tests/Feature/OperationalSchemaTest.php`.

Steps:
1. RED/GREEN cycle — Operational schema contract:
   Test file: `apps/api/tests/Feature/OperationalSchemaTest.php`. Level: integration. Test intent: Given a fresh database, when migrations run, then invoice-per-order and reminder logical-key uniqueness, nullable outlet terms, role-audit references, domain casts, indexes, and reversible down paths are observable. Exercise through Laravel migrations and Eloquent metadata; do not test private implementation methods. Test doubles: none; use the real database schema. Expected RED: the new tables/models/relationships do not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/OperationalSchemaTest.php --testdox`.
   Implement the additive migrations, models, relationships, factories, and constants for `unpaid`, `partially_paid`, `paid`, and `cancelled` invoice states. Preserve all existing order/payment columns and do not add a second payment ledger.
   Run PASS: `cd apps/api && php artisan test tests/Feature/OperationalSchemaTest.php --testdox`.
2. Refactor while green: keep invoice/reminder status values centralized in domain models; do not create generic utility files; re-run the same command.
3. Commit: `git add apps/api/database/migrations/2026_09_10_000019_add_payment_term_days_to_outlets_table.php apps/api/database/migrations/2026_09_10_000020_create_invoices_table.php apps/api/database/migrations/2026_09_10_000021_create_invoice_reminders_table.php apps/api/database/migrations/2026_09_10_000022_create_role_assignment_audits_table.php apps/api/app/Models/Invoice.php apps/api/app/Models/InvoiceReminder.php apps/api/app/Models/RoleAssignmentAudit.php apps/api/app/Models/Outlet.php apps/api/app/Models/Order.php apps/api/app/Models/User.php apps/api/database/factories/InvoiceFactory.php apps/api/database/factories/InvoiceReminderFactory.php apps/api/tests/Feature/OperationalSchemaTest.php && git commit -m "feat(operations): add invoice reminder and finance foundation"`.

## REFERENCES LOADED
- `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md` — data foundation, invoice uniqueness, reminder identity, role audit, and rollback rules.
- `apps/api/app/Models/Order.php`, `Payment.php`, `Outlet.php`, `User.php` — existing relations, casts, and single-role model.
- `apps/api/database/migrations/2026_09_08_000007_create_payments_table.php` and existing migration sequence — additive migration conventions.

## WHY THIS APPROACH
Complexity: standard. The task is scaffolding but includes relational constraints that protect later idempotency and rollback behavior.

## SANDWICH CONTEXT
[CRITICAL: Do not replace the existing payment/order ledger or add a new dependency/database architecture.]
You are creating the persistence foundation for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend the existing Laravel operational flow.
Files in scope: the migrations, models, factories, and schema test listed above.
Available after: none.
Architecture rule: additive Laravel migrations and Eloquent relations only; preserve JWT/auth, order idempotency, existing payment records, and WhatsApp records.
[RESTATE: Do not replace the existing payment/order ledger or add a new dependency/database architecture.]

## DELIVERABLE
- Given a fresh database, when migrations run, then all four new schema surfaces exist with indexes and reversible down paths.
- Given an order, when a second invoice for the same order is attempted, then the database rejects it deterministically.
- Given a reminder identity, when the same invoice/event/date is attempted twice, then the unique logical key prevents duplication.
- Given an outlet with no configured term, then the persisted term remains nullable so the service can apply the seven-day fallback.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Explicit foreign keys/indexes for invoice order/outlet, due-date/status, reminder next-attempt/status, and role-audit target/actor.
- Down paths are safe and ordered; no existing order/payment data is deleted.
- Tests are written before implementation.

Must-not-have:
- No new package, queue provider, permission graph, payment gateway, or object-storage schema.
- No duplicate payment ledger or JWT contract changes.

Open question risks:
- Invoice identifier display format remains an implementation detail; report if schema naming would leak an incompatible public contract.

Rollback note:
- If later backfill is stopped, retain audit output and remove only identified backfill-created invoices; this task must not make destructive rollback necessary.

## STOP CONDITIONS
Done when schema tests pass and the commit exists. Escalate if existing migrations need destructive edits, a new dependency is proposed, or the payment ledger must be replaced.
