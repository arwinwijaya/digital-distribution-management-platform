# EXECUTION PLAN — Operational Readiness Slice 1

**Date:** 2026-09-10  
**Spec:** `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`  
**Status:** draft  
**Total tasks:** 10

---

## Execution Overview

### Recommended Order

```
T1 → T2, T8 (parallel) → T3 → T4, T5 (parallel) → T6 → T7 → T9 → T10
```

> Dependency order above is recommended — pocket-development enforces actual
> parallelism and sequencing based on its routing logic.

### Phases

- **Phase A — Foundation:** T1
- **Phase B — Operational workflows:** T2, T3, T4, T5, T6, T7, T8
- **Phase C — UI and integration:** T9, T10

### Parallelizable Groups

| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T2, T8 | T1 completes |
| Group B | T4, T5 | T3 completes |

### Constraints Reminder

**Architecture:** Extend the current Laravel/Next.js flow. Preserve JWT response/auth contracts, order idempotency, credit-limit locking, payment transactions, delivery authorization, WhatsApp webhook/outbound behavior, and deterministic bounded queries. Read the effective role from current authorization state on every request; do not trust stale JWT role claims. Do not add dependencies or introduce a new database/event architecture.

**Out-of-scope:** Payment gateways, refunds, credit scoring, dynamic pricing, inventory optimization, PWA/mobile, sales targets/performance dashboards, geographic/supplier BI, broad permission graphs, JWT redesign, database replacement, invoice PDF/downloads, automated collections, arbitrary reminder channels, photo/signature upload, and object-storage proof.

**Assumptions at risk:** Finance metrics may render in the existing dashboard; invoice identifiers may reuse current order/payment reference conventions; cancelled invoices are excluded from active overdue-rate denominators. If any assumption changes, stop the affected task and report `NEEDS_CONTEXT`.

**Sequencing:** Dependency annotations are recommended order; pocket-development enforces actual blocking and parallelism. Implementation is not authorized by this plan until the user approves the reviewed plan and pocket-structuring completes.

### File Structure Map

#### Rule: Data foundation and shared operational records

- Create: `apps/api/database/migrations/2026_09_10_000019_add_payment_term_days_to_outlets_table.php`
- Create: `apps/api/database/migrations/2026_09_10_000020_create_invoices_table.php`
- Create: `apps/api/database/migrations/2026_09_10_000021_create_invoice_reminders_table.php`
- Create: `apps/api/database/migrations/2026_09_10_000022_create_role_assignment_audits_table.php`
- Create: `apps/api/app/Models/Invoice.php`
- Create: `apps/api/app/Models/InvoiceReminder.php`
- Create: `apps/api/app/Models/RoleAssignmentAudit.php`
- Create: `apps/api/database/factories/InvoiceFactory.php`
- Create: `apps/api/database/factories/InvoiceReminderFactory.php`
- Modify: `apps/api/app/Models/Outlet.php`, `apps/api/app/Models/Order.php`, `apps/api/app/Models/User.php`
- Test: `apps/api/tests/Feature/OperationalSchemaTest.php`

#### Rule: Finance role, authorization, and outlet payment terms

- Create: `apps/api/app/Services/FinanceAuthorizationService.php`
- Create: `apps/api/app/Services/FinanceRoleService.php`
- Create: `apps/api/app/Http/Controllers/FinanceRoleController.php`
- Create: `apps/api/app/Http/Requests/SetPaymentTermRequest.php`
- Create: `apps/api/app/Http/Requests/SetFinanceRoleRequest.php`
- Modify: `apps/api/app/Http/Controllers/OutletController.php`, `apps/api/app/Models/User.php`, `apps/api/app/Models/Outlet.php`, `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/FinanceAccessTest.php`, `apps/api/tests/Feature/PaymentTermTest.php`

#### Rule: Invoice lifecycle and cancellation

- Create: `apps/api/app/Services/InvoiceService.php`
- Create: `apps/api/app/Http/Controllers/InvoiceController.php`
- Create: `apps/api/app/Http/Requests/CancelOrderRequest.php`
- Modify: `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/app/Models/Order.php`, `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/InvoiceTest.php`

#### Rule: Payment lifecycle and bounded visibility

- Modify: `apps/api/app/Services/PaymentService.php`, `apps/api/app/Http/Controllers/PaymentController.php`, `apps/api/app/Http/Requests/StorePaymentRequest.php`, `apps/api/app/Models/Payment.php`, `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/InvoicePaymentTest.php`, `apps/api/tests/Feature/PaymentTest.php`

#### Rule: Legacy invoice backfill

- Create: `apps/api/app/Services/InvoiceBackfillService.php`
- Create: `apps/api/app/Console/Commands/BackfillInvoices.php`
- Test: `apps/api/tests/Feature/InvoiceBackfillTest.php`

#### Rule: WhatsApp invoice reminders

- Create: `apps/api/app/Services/InvoiceReminderService.php`
- Create: `apps/api/app/Http/Controllers/InvoiceReminderController.php`
- Create: `apps/api/app/Console/Commands/ProcessInvoiceReminders.php`
- Modify: `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Console/Kernel.php`, `apps/api/config/whatsapp.php`, `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/InvoiceReminderTest.php`, `apps/api/tests/Feature/WhatsAppPostgresConcurrencyTest.php`

#### Rule: Finance metrics

- Create: `apps/api/app/Services/InvoiceMetricsService.php`
- Create: `apps/api/app/Http/Controllers/FinanceMetricsController.php`
- Modify: `apps/api/routes/api.php`, `apps/api/app/Services/AnalyticsService.php` only if a shared aggregate helper is extracted without changing the existing dashboard contract
- Test: `apps/api/tests/Feature/InvoiceMetricsTest.php`

#### Rule: Delivery proof

- Modify: `apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php`, `apps/api/app/Http/Controllers/DeliveryController.php`, `apps/web/src/app/delivery/page.tsx` only if the current UI contract needs a compatibility-preserving adjustment
- Test: `apps/api/tests/Feature/DeliveryTest.php`

#### Rule: Finance web surfaces

- Create: `apps/web/src/app/invoices/page.tsx`
- Modify: `apps/web/src/app/payments/page.tsx`, `apps/web/src/app/dashboard/page.tsx`, `apps/web/src/components/LoginForm.tsx`, `apps/web/src/components/Sidebar.tsx`
- Test/verify: `npm --workspace apps/web run lint`, `npm --workspace apps/web run build`

#### Rule: Full operational integration

- Create: `apps/api/tests/Feature/OperationalReadinessTest.php`
- Modify: none outside files already listed above
- Test: `cd apps/api && php artisan test tests/Feature/OperationalReadinessTest.php --testdox`

---

## Pocket Packets

---

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

---

### Task 2: Add finance role, request-time authorization, and payment terms [depends: T1]

## OBJECTIVE
Implement admin-controlled single-role finance assignment/removal, audit it, enforce current-role authorization, and expose validated 1–90 day outlet payment-term configuration with a seven-day fallback contract.

Files:
- Create `apps/api/app/Services/FinanceAuthorizationService.php`, `apps/api/app/Services/FinanceRoleService.php`, `apps/api/app/Http/Controllers/FinanceRoleController.php`, `apps/api/app/Http/Requests/SetPaymentTermRequest.php`, and `apps/api/app/Http/Requests/SetFinanceRoleRequest.php`.
- Modify `apps/api/app/Models/User.php`, `apps/api/app/Models/Outlet.php`, `apps/api/app/Http/Controllers/OutletController.php`, and `apps/api/routes/api.php`.
- Test `apps/api/tests/Feature/FinanceAccessTest.php` and `apps/api/tests/Feature/PaymentTermTest.php`.

Steps:
1. RED/GREEN cycle — Admin assigns finance role:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given an active admin and active target, when the admin assigns finance, then the target has exactly finance and an audit exists. Exercise through the authenticated admin role-assignment HTTP route. Test doubles: none for auth/database; use real JWT and DB. Expected RED: route/service/audit do not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=admin_assigns_finance --testdox`.
   Implement only the assignment path, then run the same command and verify PASS.
2. RED/GREEN cycle — Repeated assignment:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given an active user already has finance, when admin assigns finance again, then the request succeeds without a duplicate role/audit or unrelated mutation. Exercise through the same HTTP route. Test doubles: none. Expected RED: no idempotent repeat behavior exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=finance_assignment_is_idempotent --testdox`.
   Implement repeat behavior, then run the same command and verify PASS.
3. RED/GREEN cycle — Inactive target rejection:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given an inactive target, when admin assigns finance, then the request is rejected and the target cannot access finance. Exercise through the same HTTP route. Test doubles: none. Expected RED: no active-user guard exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=inactive_user_cannot_receive_finance --testdox`.
   Implement the active-user guard, then run the same command and verify PASS.
4. RED/GREEN cycle — Single-role replacement:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given a user with another role, when finance is assigned, then exactly finance replaces the prior role and the change is audited. Exercise through the assignment route. Test doubles: none. Expected RED: role replacement is not implemented.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=finance_replaces_prior_role --testdox`.
   Implement replacement/audit behavior, then run the same command and verify PASS.
5. RED/GREEN cycle — Role removal invalidates old token:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given a finance token issued before removal, when admin removes finance and the user makes the next finance request, then the request is forbidden. Exercise through removal followed by a real protected HTTP request. Test doubles: none. Expected RED: stale role claims are not currently tested/guarded.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=removed_finance_role_denies_old_token --testdox`.
   Implement current-database-role resolution/removal, then run the same command and verify PASS.
6. RED/GREEN cycle — Finance cannot access unrelated administration:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given a finance user, when unrelated user/product/marketplace/credit-limit/AI/sales/delivery-assignment/order-approval routes are requested, then each returns 403. Exercise through real HTTP routes; operational allow-list tests belong to their owning tasks. Test doubles: none. Expected RED: finance role and current-role guard are absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=finance_cannot_access_unrelated_administration --testdox`.
   Implement shared authorization and route checks, then run the same command and verify PASS.
7. RED/GREEN cycle — Valid payment terms:
   Test file: `apps/api/tests/Feature/PaymentTermTest.php`. Level: integration. Test intent: Given an authenticated admin and outlet, when an integer term from 1 through 90 is configured, then GET/PUT returns and persists it. Exercise through `/api/admin/outlets/{id}/payment-terms`. Test doubles: none. Expected RED: endpoint and field do not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/PaymentTermTest.php --filter=admin_can_set_valid_payment_term --testdox`.
   Implement the endpoint and persistence, then run the same command and verify PASS.
8. RED/GREEN cycle — Invalid payment terms:
   Test file: `apps/api/tests/Feature/PaymentTermTest.php`. Level: integration. Test intent: Given zero, negative, fractional, or >90 input, when configuration is attempted, then validation returns 422 and no term changes. Exercise through the same endpoint. Test doubles: none. Expected RED: no validation contract exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/PaymentTermTest.php --filter=invalid_payment_terms_are_rejected --testdox`.
   Implement integer/range validation, then run the same command and verify PASS.
9. Refactor while green: make every finance check call `FinanceAuthorizationService`; keep role assignment and term validation domain-scoped; run `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php tests/Feature/PaymentTermTest.php --testdox`.
10. Commit: `git add apps/api/app/Services/FinanceAuthorizationService.php apps/api/app/Services/FinanceRoleService.php apps/api/app/Http/Controllers/FinanceRoleController.php apps/api/app/Http/Requests/SetPaymentTermRequest.php apps/api/app/Http/Requests/SetFinanceRoleRequest.php apps/api/app/Models/User.php apps/api/app/Models/Outlet.php apps/api/app/Http/Controllers/OutletController.php apps/api/routes/api.php apps/api/tests/Feature/FinanceAccessTest.php apps/api/tests/Feature/PaymentTermTest.php && git commit -m "feat(auth): add finance role and payment terms"`.

## REFERENCES LOADED
- Spec finance access and terms GWT scenarios.
- `apps/api/app/Models/User.php`, `AuthController.php`, `routes/api.php` — single role and JWT request-time resolution.
- `apps/api/app/Http/Controllers/CreditLimitController.php`, `DeliveryController.php`, and existing authorization tests — must-not scope boundaries.

## WHY THIS APPROACH
Complexity: deep. It changes authorization without changing JWT response shape and must prove stale-token invalidation and endpoint isolation.

## SANDWICH CONTEXT
[CRITICAL: Finance authorization must read the current user role from the database on every protected request; stale JWT claims must never preserve access.]
You are implementing finance access and outlet payment terms for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — preserve the existing single-role model.
Files in scope: the files listed for Task 2.
Available after: T1 schema/models.
Architecture rule: use existing JWT/auth middleware and controller/request conventions; do not introduce Spatie permission migration or multi-role users.
[RESTATE: Finance authorization must read the current user role from the database on every protected request; stale JWT claims must never preserve access.]

## DELIVERABLE
- Given an active admin and active target, when finance is assigned twice, then exactly one finance role and one audit per actual change exist and the repeated assignment is idempotent.
- Given an inactive target, when assignment is requested, then it is rejected.
- Given a removed finance role and an old token, when the next finance request is made, then it returns 403.
- Given an outlet term, when 1–90 integer days are configured, then it is stored; invalid values return 422 and never reach invoice creation.
- Given a finance user, when unrelated admin endpoints are requested, then each is forbidden and no unrelated data is exposed.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: request-time role checks, audit persistence, active-user guard, outlet isolation, precise validation, and no auth response redesign.
Must-not-have: no broad permission graph, no multi-role array, no admin privilege leakage to finance.
Open question risks: none beyond the dashboard/invoice display assumptions.
Rollback note: revert role assignments through the admin route; leave existing role behavior intact.

## STOP CONDITIONS
Stop if current-user resolution cannot distinguish current role from stale claims, or if an unrelated controller needs a global architecture rewrite.

---

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

---

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

---

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

---

### Task 6: Implement idempotent WhatsApp invoice reminders and scheduler [depends: T4] [test-risk]

## OBJECTIVE
Create H-1 and first-overdue reminder processing in Asia/Jakarta with unique invoice/event/date identities, three bounded retries at 1/5/15 minutes, provider idempotency reuse, failure audit, and finance-visible bounded reminder history.

Files:
- Create `apps/api/app/Services/InvoiceReminderService.php`, `apps/api/app/Http/Controllers/InvoiceReminderController.php`, and `apps/api/app/Console/Commands/ProcessInvoiceReminders.php`.
- Modify `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Console/Kernel.php`, `apps/api/config/whatsapp.php`, and `apps/api/routes/api.php`.
- Test `apps/api/tests/Feature/InvoiceReminderTest.php` and preserve relevant `WhatsAppPostgresConcurrencyTest.php` coverage.

Steps:
Timing test contract: every H-1/overdue/retry cycle freezes Carbon at an explicit instant in `Asia/Jakarta`, asserts the exact due-boundary inclusivity/exclusivity, and restores the test clock after the scenario.
1. RED/GREEN cycle — H-1 reminder once:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration with external-service boundary. Test intent: Given an eligible unpaid invoice due tomorrow in Asia/Jakarta, when the Artisan reminder command runs twice, then one H-1 reminder identity/provider call exists. Exercise through `php artisan invoices:reminders`; fake only `WhatsAppClient`. Expected RED: no reminder service/command exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=h_minus_one_reminder_is_sent_once --testdox`.
   Implement candidate selection and unique event identity, then run the same command and verify PASS.
2. RED/GREEN cycle — Missed H-1 recovery:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given H-1 was missed and the invoice remains eligible, when the next command runs, then exactly one H-1 reminder is created. Exercise through Artisan with Carbon in Asia/Jakarta. Test doubles: fake `WhatsAppClient` only. Expected RED: no recovery window exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=missed_h_minus_one_is_recovered --testdox`.
   Implement recovery predicate, then run the same command and verify PASS.
3. RED/GREEN cycle — First overdue reminder once:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given an unpaid invoice crosses its due boundary, when the command runs repeatedly, then one overdue reminder exists and later runs do not create another. Test doubles: fake `WhatsAppClient` only. Expected RED: no overdue event identity exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=overdue_reminder_is_sent_once --testdox`.
   Implement first-overdue selection and uniqueness, then run the same command and verify PASS.
4. RED/GREEN cycle — Paid invoice suppression:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given an invoice becomes paid before processing, when the command runs, then no reminder is created/sent. Test doubles: fake `WhatsAppClient` only. Expected RED: paid-state suppression is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=paid_invoice_suppresses_reminder --testdox`.
   Implement the paid eligibility check, then run the same command and verify PASS.
5. RED/GREEN cycle — Cancelled invoice suppression:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given an invoice is cancelled before processing, when the command runs, then no reminder is created/sent. Test doubles: fake `WhatsAppClient` only. Expected RED: cancelled-state suppression is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=cancelled_invoice_suppresses_reminder --testdox`.
   Implement the cancelled eligibility check, then run the same command and verify PASS.
6. RED/GREEN cycle — Provider retry schedule and identity:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration with external-service boundary. Test intent: Given provider errors, when retry processing occurs, then attempts use one reminder/provider idempotency identity and next attempts are scheduled at 1, 5, and 15 minutes. Test doubles: fake `WhatsAppClient`; do not mock reminder/invoice/DB. Expected RED: no invoice retry state exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=provider_failure_schedules_idempotent_backoff --testdox`.
   Implement attempt/backoff identity, then run the same command and verify PASS.
7. RED/GREEN cycle — Final failure audit and invoice immutability:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given all bounded attempts fail, when final processing completes, then reminder is failed/audited and invoice status/balance remain unchanged. Test doubles: fake `WhatsAppClient` that always fails. Expected RED: no final audit/immutability assertion exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=final_reminder_failure_is_audited_without_invoice_mutation --testdox`.
   Implement final failure state, then run the same command and verify PASS.
8. RED/GREEN cycle — Valid bounded reminder history:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given reminders for two outlets, when authorized finance/admin requests a valid page/limit, then rows are scoped/stable/bounded with metadata. Test doubles: none. Expected RED: no history endpoint exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=reminder_history_is_bounded_and_scoped --testdox`.
   Implement the bounded query, then run the same command and verify PASS.
9. RED/GREEN cycle — Invalid reminder pagination:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given invalid page or limit, when reminder history is requested, then 422 is returned before an unbounded query. Test doubles: none. Expected RED: no validation exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=invalid_reminder_pagination_is_rejected --testdox`.
   Implement validation, then run the same command and verify PASS.
10. RED/GREEN cycle — Scheduler registration and timezone:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given the Laravel scheduler, when registered events are inspected, then invoice reminder processing is scheduled with `Asia/Jakarta` timezone at a cadence that can process minute-level retries. Exercise through the real scheduler event registry, not a private service method. Test doubles: none; freeze Carbon only in date-boundary tests. Expected RED: Kernel has no reminder event.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=scheduler_registers_invoice_reminders_in_jakarta --testdox`.
   Implement Kernel schedule registration, then run the same command and verify PASS.
11. RED/GREEN cycle — Closed invoice before retry:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration with external-service boundary. Test intent: Given a failed pending reminder, when the invoice is paid or cancelled before its retry time, then the next processing run makes no provider call and does not advance retry mutation. Test doubles: fake `WhatsAppClient` with call count; do not mock invoice/reminder/database. Expected RED: retry path does not re-check current invoice state.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=closed_invoice_suppresses_pending_retry --testdox`.
   Implement retry-time state check, then run the same command and verify PASS.
12. RED/GREEN cycle — Concurrent reminder workers:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php` and `apps/api/tests/Feature/WhatsAppPostgresConcurrencyTest.php`. Level: integration (with PostgreSQL concurrency scenario). Test intent: Given concurrent reminder workers for one invoice/event/date, when both claim/send, then one logical reminder and one provider call exist. Test doubles: fake WhatsApp client; do not mock claim/transaction. Expected RED: existing WhatsApp concurrency test covers order notifications only.
   Run RED: `cd apps/api && DB_CONNECTION=pgsql php artisan test tests/Feature/WhatsAppPostgresConcurrencyTest.php tests/Feature/InvoiceReminderTest.php --filter='concurrent_invoice_reminder_workers|invoice_reminder_is_sent_once' --testdox`.
   Implement reminder-specific claim/unique-key integration, then run the same PostgreSQL command and verify PASS.
13. Refactor while green: keep date logic in `InvoiceReminderService`, transport in `WhatsAppOutboundService`, and settings in `config/whatsapp.php`; run `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --testdox` and, when PostgreSQL is available, `cd apps/api && php artisan test tests/Feature/WhatsAppPostgresConcurrencyTest.php --testdox`.
14. Commit: `git add apps/api/app/Services/InvoiceReminderService.php apps/api/app/Http/Controllers/InvoiceReminderController.php apps/api/app/Console/Commands/ProcessInvoiceReminders.php apps/api/app/Services/WhatsAppOutboundService.php apps/api/app/Console/Kernel.php apps/api/config/whatsapp.php apps/api/routes/api.php apps/api/tests/Feature/InvoiceReminderTest.php apps/api/tests/Feature/WhatsAppPostgresConcurrencyTest.php && git commit -m "feat(reminders): add bounded invoice WhatsApp workflow"`.

## REFERENCES LOADED
- Spec reminder timing, idempotency, retry, audit, timezone, and suppression GWT scenarios.
- `apps/api/app/Services/WhatsAppOutboundService.php`, `WhatsAppMessage.php`, `WhatsAppTest.php`, and `WhatsAppPostgresConcurrencyTest.php` — existing provider idempotency, lease, and failure isolation.
- `apps/api/app/Console/Kernel.php` — scheduling seam.

## WHY THIS APPROACH
Complexity: deep and `[test-risk]`. It crosses invoice state, scheduler time, persistence uniqueness, network failure, and existing outbound leases.

## SANDWICH CONTEXT
[CRITICAL: Reminder failure must never mutate invoice status/balance, and every retry must reuse one logical/provider idempotency identity.]
You are implementing invoice reminders for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — reuse existing WhatsApp outbound transport.
Files in scope: the files listed for Task 6.
Available after: T4 invoice payment state synchronization.
Architecture rule: use Asia/Jakarta boundaries, additive reminder audit records, existing WhatsApp client/webhook contract, bounded retries, and no new queue/provider dependency.
[RESTATE: Reminder failure must never mutate invoice status/balance, and every retry must reuse one logical/provider idempotency identity.]

## DELIVERABLE
- Given an unpaid invoice due tomorrow in Asia/Jakarta, when the command runs twice, then one H-1 reminder exists/sends.
- Given H-1 was missed, when the next eligible run occurs, then the reminder is recovered once.
- Given an unpaid invoice crosses due date, when the command runs repeatedly, then one overdue reminder exists.
- Given a provider failure, then attempts are bounded at three retries with 1/5/15-minute backoff, final failure is audited, and invoice state is unchanged.
- Given paid/cancelled invoice, when the scheduler runs, then no reminder is created.
- Given reminder records for multiple outlets, when history is requested, then rows are bounded, stable, validated, and outlet-scoped.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: Asia/Jakarta date semantics, unique invoice/event/date key, same provider idempotency key across retry, final failure audit, and bounded/stable reminder list.
Must-not-have: no arbitrary reminder channel, no automated collections, no invoice status mutation from provider failure, no replacement of WhatsApp webhook contract.
Open question risks: exact reminder body text is implementation detail; it must identify the outlet/invoice and event without exposing unrelated outlet data.
Rollback note: disable scheduler before reverting if provider calls are problematic; retain reminder audit records.

## STOP CONDITIONS
Escalate if provider/client idempotency cannot be preserved or if a retry could send after payment/cancellation due to an unprotected state check.

---

### Task 7: Add finance invoice/payment metrics API [depends: T4, T6]

## OBJECTIVE
Expose the six baseline invoice/payment metric groups with a default 30-day event window, all-active current-state metrics, zero-safe denominators, role/outlet scoping, and stable date validation without changing the existing dashboard API contract.

Files:
- Create `apps/api/app/Services/InvoiceMetricsService.php` and `apps/api/app/Http/Controllers/FinanceMetricsController.php`.
- Modify `apps/api/routes/api.php`; modify `apps/api/app/Services/AnalyticsService.php` only when extracting a non-breaking shared aggregate helper is necessary.
- Test `apps/api/tests/Feature/InvoiceMetricsTest.php`.

Steps:
Timing test contract: freeze Carbon at a known instant in `Asia/Jakarta` for the default-window and date-boundary cycles; assert exact 30-day inclusivity/exclusivity, then clear the test clock.
1. RED/GREEN cycle — Default window/current-state separation:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given active invoices inside/outside 30 days, when metrics omit dates, then event groups use last 30 days and active outstanding/status includes all active invoices. Exercise through `GET /api/finance/metrics`. Test doubles: no query/service mocks; real data and Carbon. Expected RED: endpoint absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_use_default_window_and_current_state --testdox`.
   Implement separate predicates, then run the same command and verify PASS.
2. RED/GREEN cycle — Issued invoice metric:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given invoices issued inside/outside the event window, when metrics are requested, then issued-invoice count uses issue date and the default/custom window. Test doubles: none. Expected RED: group absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_issued_invoices --testdox`.
   Implement the issue-date aggregate, then run the same command and verify PASS.
3. RED/GREEN cycle — Outstanding balance metric:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given active invoices including one issued before the window, when metrics are requested, then current outstanding balance includes all active unpaid/partial balances and excludes paid/cancelled. Test doubles: none. Expected RED: no invoice aggregate.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_current_outstanding_balance --testdox`.
   Implement current-state aggregate, then run the same command and verify PASS.
4. RED/GREEN cycle — Overdue rate:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given active overdue/non-overdue/paid/cancelled invoices, when metrics are requested, then overdue rate follows the defined active denominator and excludes cancelled invoices. Test doubles: none. Expected RED: no overdue metric.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_overdue_rate --testdox`.
   Implement the due-date/status aggregate, then run the same command and verify PASS.
5. RED/GREEN cycle — Full collection time:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given an invoice paid by multiple completed payments, when metrics are calculated, then collection time runs issue date to final payment date and partial-only invoices are excluded. Test doubles: none. Expected RED: no payment-date aggregate.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_measure_full_collection_time --testdox`.
   Implement final completed-payment date aggregation, then run the same command and verify PASS.
6. RED/GREEN cycle — Payment-status breakdown:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given invoices in each status, when metrics are requested, then payment-status breakdown counts each status correctly. Test doubles: none. Expected RED: no status breakdown.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_payment_status_breakdown --testdox`.
   Implement status aggregation, then run the same command and verify PASS.
7. RED/GREEN cycle — Reminder success/failure:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given reminder records inside the window with sent and failed outcomes, when metrics are requested, then success/failure counts are returned. Test doubles: none. Expected RED: reminders are not included.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_reminder_success_and_failure --testdox`.
   Implement reminder aggregates, then run the same command and verify PASS.
8. RED/GREEN cycle — Valid custom window:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given a valid custom window, when requested, then event metrics use it. Test doubles: none. Expected RED: no date contract exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_accept_valid_custom_window --testdox`.
   Implement valid date parsing, then run the same command and verify PASS.
9. RED/GREEN cycle — Invalid custom window:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given malformed, out-of-order, or over-limit dates, when requested, then 422 is returned before unbounded aggregation. Test doubles: none. Expected RED: invalid date behavior is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_reject_invalid_custom_window --testdox`.
   Implement validation/range bounds, then run the same command and verify PASS.
10. RED/GREEN cycle — Zero denominators:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given no qualifying denominator, when metrics are requested, then each metric returns numeric zero and the response remains valid. Test doubles: none. Expected RED: endpoint absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_are_zero_safe --testdox`.
   Implement zero-safe arithmetic, then run the same command and verify PASS.
11. RED/GREEN cycle — Authorization and outlet isolation:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given finance/admin and outlet users, when metrics are requested, then finance/admin are allowed and outlet results exclude other outlets. Test doubles: none. Expected RED: route authorization absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_are_authorized_and_outlet_scoped --testdox`.
   Implement current-role scope checks, then run the same command and verify PASS.
12. Refactor while green: reuse only named aggregate helpers that preserve `analytics/dashboard`; run `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php tests/Feature/AnalyticsTest.php --testdox`.
13. Commit: `git add apps/api/app/Services/InvoiceMetricsService.php apps/api/app/Http/Controllers/FinanceMetricsController.php apps/api/routes/api.php apps/api/app/Services/AnalyticsService.php apps/api/tests/Feature/InvoiceMetricsTest.php && git commit -m "feat(analytics): add finance invoice metrics"`.

## REFERENCES LOADED
- Spec metrics definitions and zero-denominator GWT scenarios.
- `apps/api/app/Services/AnalyticsService.php`, `AnalyticsController.php`, `AnalyticsTest.php` — bounded aggregate style and existing contract.
- Invoice/payment/reminder models and services from T1/T3/T4/T6.

## WHY THIS APPROACH
Complexity: standard. It adds a separate contract to protect the existing executive dashboard while sharing its database-aggregate discipline.

## SANDWICH CONTEXT
[CRITICAL: Current-state metrics must include all active invoices while event metrics use the defined date window; never collapse both semantics into one date predicate.]
You are implementing finance invoice/payment metrics for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend existing analytics patterns.
Files in scope: the files listed for Task 7.
Available after: T4 payment state and T6 reminder outcomes.
Architecture rule: use bounded deterministic database aggregates and preserve the existing analytics dashboard response contract.
[RESTATE: Current-state metrics must include all active invoices while event metrics use the defined date window; never collapse both semantics into one date predicate.]

## DELIVERABLE
- Given invoices inside and outside 30 days, when metrics use defaults, then event groups use the last 30 days and active outstanding/status groups include all active invoices.
- Given an invoice paid through multiple records, then collection time runs from issue date to final payment date and partial-only invoices are excluded.
- Given no qualifying denominator, then the metric returns `0` and the API remains valid.
- Given finance requests outlet-scoped metrics, then only authorized outlet data is aggregated; unrelated admin/finance routes remain protected.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: all six groups, explicit date semantics, zero-safe arithmetic, bounded query shape, default/custom window validation, and role/outlet isolation.
Must-not-have: no geographic/supplier BI, no changes to legacy analytics response fields, no unbounded table hydration.
Open question risks: cancelled overdue denominator follows the approved assumption; report if product owner changes it.
Rollback note: remove only the new route/service; preserve existing dashboard analytics.

## STOP CONDITIONS
Escalate if metric definitions require data not represented by existing invoice/payment/reminder records or if the existing dashboard contract must change.

---

### Task 8: Enforce delivery proof at the API boundary [prereq]

## OBJECTIVE
Make recipient name and proof URL mandatory, nonblank, and valid only when transitioning a delivery to `delivered`, while retaining current authorization, state-transition, and proof response behavior.

Files:
- Modify `apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php` and `apps/api/app/Http/Controllers/DeliveryController.php` only as needed.
- Modify `apps/web/src/app/delivery/page.tsx` only if the existing required fields need a compatibility-preserving accessibility/error adjustment.
- Test `apps/api/tests/Feature/DeliveryTest.php`.

Steps:
1. RED/GREEN cycle — Required recipient:
   Test file: `apps/api/tests/Feature/DeliveryTest.php`. Level: integration. Test intent: Given an in-progress delivery, when delivered is submitted without a recipient or with whitespace, then validation rejects and delivery remains in progress. Exercise through `PATCH /api/deliveries/{id}/status`. Test doubles: no request/model/transaction mocks. Expected RED: current nullable rule allows omission.
   Run RED: `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --filter=delivered_requires_nonblank_recipient --testdox`.
   Implement conditional recipient validation, then run the same command and verify PASS.
2. RED/GREEN cycle — Required valid proof URL:
   Test file: `apps/api/tests/Feature/DeliveryTest.php`. Level: integration. Test intent: Given an in-progress delivery, when delivered is submitted without or with invalid proof URL, then validation rejects and state remains in progress. Exercise through the same PATCH boundary. Test doubles: none. Expected RED: current nullable URL rule allows omission.
   Run RED: `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --filter=delivered_requires_valid_proof_url --testdox`.
   Implement conditional URL validation, then run the same command and verify PASS.
3. Regression verification — Valid completion and non-delivery transitions:
   Test file: `apps/api/tests/Feature/DeliveryTest.php`. Level: integration. Test intent: Add named characterization tests `test_delivery_completion_persists_proof` and `test_non_delivery_transitions_do_not_require_proof`; given valid proof, completion persists/returns proof, and given assigned/in-progress transition, proof remains optional. Exercise through the existing PATCH boundary and assert persisted state. Test doubles: none. These are characterization checks because the existing implementation already satisfies them; they are not RED cycles.
   Run verification: `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --filter='test_delivery_completion_persists_proof|test_non_delivery_transitions_do_not_require_proof' --testdox`.
4. Refactor while green: keep transition-specific validation in the FormRequest and state mutation in the controller; run `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --testdox`.
5. Commit: `git add apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php apps/api/app/Http/Controllers/DeliveryController.php apps/web/src/app/delivery/page.tsx apps/api/tests/Feature/DeliveryTest.php && git commit -m "fix(delivery): require proof before completion"`.

## REFERENCES LOADED
- Spec delivery proof GWT scenarios.
- `apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php`, `DeliveryController.php`, `DeliveryTest.php` — current validation, locks, authorization, and UI payload.
- `apps/web/src/app/delivery/page.tsx` — existing recipient/proof input contract.

## WHY THIS APPROACH
Complexity: lightweight. The UI already collects both fields; the missing guarantee is the API boundary.

## SANDWICH CONTEXT
[CRITICAL: Delivery completion must remain an authorized state transition and must not accept missing/blank proof.]
You are enforcing delivery proof for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — preserve the existing delivery flow.
Files in scope: the files listed for Task 8.
Available after: none (prerequisite task can run independently).
Architecture rule: preserve delivery authorization, row locks, status history, and recipient/proof response fields; no uploads/storage.
[RESTATE: Delivery completion must remain an authorized state transition and must not accept missing/blank proof.]

## DELIVERABLE
- Given an in-progress delivery, when recipient or valid proof URL is missing/blank/invalid, then API returns validation error and delivery remains in progress.
- Given valid recipient and URL, when delivered is submitted, then delivery becomes delivered and both fields are persisted and returned.
- Given assigned/in-progress transition not completing delivery, then proof remains optional.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: server-side conditional validation, persisted proof, current authorization and state-history behavior, direct API tests.
Must-not-have: no photo/signature upload, object storage, or UI-only enforcement.
Open question risks: none.
Rollback note: revert request/controller changes while retaining existing delivery records.

## STOP CONDITIONS
Escalate if enforcing proof requires changing delivery status names or driver/sales authorization.

---

### Task 9: Add finance invoice/payment/metrics web surfaces [depends: T2, T3, T4, T7, T8]

## OBJECTIVE
Expose the approved finance workflow in the existing Next.js shell: finance can log in to payment/invoice/metrics pages, view bounded data, record payments, and see the six finance metric groups without changing API contracts. Preserve the existing delivery proof UI contract.

Files:
- Create `apps/web/src/app/invoices/page.tsx`.
- Modify `apps/web/src/app/payments/page.tsx`, `apps/web/src/app/dashboard/page.tsx`, `apps/web/src/components/LoginForm.tsx`, and `apps/web/src/components/Sidebar.tsx`.

Steps:
[no-tdd — structural UI adapter; API/E2E behavior is verified in Tasks 3, 4, 7, 8, and 10]
1. Implement the finance role label/navigation, invoice page, bounded invoice/payment requests and pagination metadata, payment idempotency payload, and six finance metric cards using existing Next.js UI/API conventions. Preserve the current delivery page payload; do not modify it here.
2. Verify the structural adapter: `npm --workspace apps/web run lint`.
   Expected: TypeScript catches any role/type/API-shape mismatch; no new dependency or test-environment setup is required.
3. Build the production bundle: `npm --workspace apps/web run build`.
   Expected: Next.js compiles all finance routes and existing pages successfully.
4. Refactor within listed UI files only: keep existing components/helpers, avoid generic utilities and new state libraries; re-run `npm --workspace apps/web run lint && npm --workspace apps/web run build`.
5. Commit: `git add apps/web/src/app/invoices/page.tsx apps/web/src/app/payments/page.tsx apps/web/src/app/dashboard/page.tsx apps/web/src/components/LoginForm.tsx apps/web/src/components/Sidebar.tsx && git commit -m "feat(web): add finance operational surfaces"`.

## REFERENCES LOADED
- Spec visibility, metrics, delivery proof, and finance access assumptions.
- `apps/web/src/app/payments/page.tsx`, `dashboard/page.tsx`, `delivery/page.tsx` — existing fetch/UI contracts.
- `apps/web/src/components/LoginForm.tsx`, `Sidebar.tsx`, `ui` components, and `apps/web/package.json` — role/login and test/build conventions.

## WHY THIS APPROACH
Complexity: standard. This is a thin adapter over approved API contracts, but it must avoid widening finance access in the UI and must preserve current delivery proof behavior.

## SANDWICH CONTEXT
[CRITICAL: Frontend changes must consume the approved API contracts and must not weaken server-side authorization or delivery proof validation.]
You are implementing finance web surfaces for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend existing Next.js pages/components.
Files in scope: the files listed for Task 9.
Available after: T2, T3, T4, T7, and T8 API contracts.
Architecture rule: use existing fetch/auth/UI conventions; no new dependency, API redesign, or client-side-only security.
[RESTATE: Frontend changes must consume the approved API contracts and must not weaken server-side authorization or delivery proof validation.]

## DELIVERABLE
- Given finance credentials, when invoice/payment pages load, then only authorized scoped data is shown with bounded pagination.
- Given finance records a valid payment, then the page sends the existing idempotency field and refreshes the invoice/payment state.
- Given finance metrics contain zero values, then the dashboard renders valid zero cards without division/UI errors.
- Given delivery completion, then the existing page still sends recipient name and proof URL while API remains authoritative.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: finance role acceptance, bounded requests, stable error states, API-contract fidelity, accessibility-preserving proof fields, lint/build/test green.
Must-not-have: no client-only authorization, no broad finance navigation to unrelated admin actions, no gateway/PWA/new dependency.
Open question risks: dashboard placement and invoice identifier display are intentionally implementation details; report only if API contracts must change.
Rollback note: revert page/component changes independently of backend records and APIs.

## STOP CONDITIONS
Escalate if the UI requires a new API contract, a new dependency, or bypasses server authorization.

---

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

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Operational data foundation | prereq | standard | Schema constraints, reversibility, unique invoice/reminder identities |
| T2 | Finance role and payment terms | T1 | deep | Current-role authorization, audit, 1–90-day validation, outlet isolation |
| T3 | Invoice lifecycle and cancellation | T1, T2 | deep | Atomic approval invoice, retry idempotency, cancellation/payment guard |
| T4 | Payment lifecycle and bounded history | T3 | deep | Delivered-only, valid-payment sum, idempotency/concurrency, pagination |
| T5 | Legacy invoice backfill | T3 | standard | Repeatable command with no synthetic/duplicate payments |
| T6 | WhatsApp invoice reminders | T4 | deep | Asia/Jakarta timing, unique events, 1/5/15 retries, failure audit |
| T7 | Finance metrics API | T4, T6 | standard | Six groups, window/current-state semantics, zero denominators |
| T8 | Delivery proof enforcement | prereq | lightweight | API rejects missing/invalid proof and persists valid proof |
| T9 | Finance web surfaces | T2, T3, T4, T7, T8 | standard | Finance UI consumes bounded API and preserves proof contract |
| T10 | Full operational E2E verification | T5, T6, T7, T8, T9 | deep | Real HTTP/Artisan cross-unit flow and duplicate safety |

**Test strategy audit trigger:** T3/T4/T6/T10 span persistence, concurrency, scheduling, and external provider boundaries; T6 and T10 are marked `[test-risk]`. A dedicated test-strategy audit is required before plan approval.
