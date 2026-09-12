# Operational Readiness Slice 1

**Date:** 2026-09-10  
**Status:** approved  
**Author:** brainstorm session  
**Spec path:** `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`

---

## Summary

Operational Readiness Slice 1 completes the finance-facing order-to-payment workflow without expanding into advanced AI or ecosystem features. It adds a limited finance role, invoice lifecycle, bounded history, payment reminders, baseline invoice/payment metrics, legacy invoice backfill, and the existing delivery proof UI requirement.

The goal is to make the current platform usable and measurable in real operational workflows before investing in pricing, scoring, replenishment, or other data-dependent roadmap items.

---

## Context

### Current State

The Laravel API and Next.js web application already provide order creation/approval, payment recording, credit limits, delivery status/proof, analytics, WhatsApp notifications, and role-based authorization. Payment and order balances currently use existing order/payment behavior, while invoice and reminder domain records do not yet exist. Payment history and some operational history endpoints require bounded pagination.

Existing relevant routes include:

- `POST/GET /api/orders` and order approval/tracking routes.
- `POST/GET /api/payments`.
- `GET/PUT /api/credit-limit`.
- Delivery assignment/status routes.
- `GET /api/analytics/dashboard`.
- Authenticated WhatsApp catalog/notification/retry routes.

### Problem / Motivation

The repository's T1–T9 execution plan is closed, but the broader roadmap still lacks a complete operational invoice/payment workflow and reliable baseline measurement. Advanced roadmap items depend on data and outcomes that will be more trustworthy after this slice.

Evidence from the context scan:

- No invoice or reminder model/controller was found.
- Existing payment/order workflows are present but payment history needs bounded access.
- The delivery API accepts proof data, while the UI only exposes recipient name and proof URL.
- Existing AI output is deterministic and explicitly unmeasured.

### Related Areas

- `apps/api/app/Models/Order.php`, `Payment.php`, `CreditLimit.php`, `Delivery.php`, `User.php`.
- `apps/api/app/Services/OrderCreationService.php`, `PaymentService.php`, `CreditLimitService.php`, `WhatsAppOutboundService.php`, `AnalyticsService.php`.
- `apps/api/app/Http/Controllers/OrderController.php`, `PaymentController.php`, `CreditLimitController.php`, `DeliveryController.php`, `AnalyticsController.php`.
- `apps/api/routes/api.php`.
- `apps/web/src/app/orders`, `payments`, `dashboard`, and `delivery`.
- `apps/api/tests/Feature/OrderTest.php`, `PaymentTest.php`, `DeliveryTest.php`, `AnalyticsTest.php`, and WhatsApp tests.

---

## Scope

### In-Scope

1. Add a single `finance` role assigned by an existing admin.
2. Allow finance to view invoice/payment/reminder data and record valid payments after delivery; restrict unrelated admin access.
3. Add invoice lifecycle tied to approved orders.
4. Configure payment terms per outlet with a 7-day fallback and a valid integer range of 1–90 days.
5. Add role-scoped, bounded order/payment history.
6. Add WhatsApp H-1 and first-overdue reminders with idempotency, audit, and bounded retry.
7. Add the six baseline invoice/payment metric groups using a default 30-day window.
8. Backfill invoices for existing delivered/paid orders without duplicating records.
9. Keep delivery completion UI/API requiring recipient name and valid proof URL.

### Out-of-Scope

- External payment gateway, refund, or financial-partner integration.
- Credit scoring; existing credit limits remain unchanged.
- Dynamic pricing or promotion broadcast.
- Inventory optimization or automated replenishment.
- PWA/mobile application.
- Sales target, sales order collection, and sales performance dashboard.
- Geographic or supplier BI expansion.
- Broad role-management redesign or multi-role users.
- JWT response contract redesign, database replacement, or architecture migration.
- Invoice PDF/download, automated collections, or arbitrary reminder channels.
- Photo/signature upload or object-storage proof; the first UI contract remains recipient name + proof URL.

---

## Architecture Constraints

- Layers this work may touch: Laravel models, migrations, services, controllers, form requests, routes, scheduled/command execution, Next.js pages/components, and feature tests.
- Layers this work must not touch: unrelated auth contracts, external payment integrations, mobile/PWA layers, object storage, or a new service/database architecture.
- Patterns that must be followed:
  - Existing Laravel service/controller/request conventions.
  - Existing JWT and request-time authorization behavior.
  - Existing order idempotency, credit-limit locking, payment transaction, delivery authorization, and WhatsApp webhook contracts.
  - Bounded, deterministic, authorization-scoped list and aggregate queries.
  - Transactional/idempotent state changes and auditable status transitions.
- Finance is a single role, not a multi-role permission graph. Existing admin assigns or removes it.
- Role removal must be effective on the next request; stale JWT/session claims must not preserve finance access.
- Payment is allowed only after the related order is delivered or already partially paid.
- Architecture validation result: **CONDITIONAL PASS**.

Conditional mitigations:

- Use explicit transaction boundaries for approval → invoice and payment → balance mutations.
- Enforce unique invoice-per-order and reminder-per-invoice/event/date identities.
- Make legacy backfill repeatable and safe after partial failure.
- Read effective role from current authorization state on each protected request.
- Preserve current payment and WhatsApp idempotency keys and concurrency controls.

---

## Dependencies

### Existing (to leverage)

- `laravel/framework:^11.0` — models, migrations, validation, scheduler/commands, transactions, and routing.
- `tymon/jwt-auth:^2.0` — existing authentication contract; no response redesign.
- `spatie/laravel-permission:^6.4` — considered for future granular permissions, but not required by the chosen option.
- `spatie/laravel-query-builder:^6.0` — available for bounded filtering/pagination if it matches current patterns.
- Existing `PaymentService`, `WhatsAppOutboundService`, and analytics query patterns — reuse transactional, retry, and authorization behavior.

### New (proposed)

None. Option A intentionally avoids introducing a new dependency or migrating the current role model to a permission framework.

---

## Stories + Scenarios

### Story: Finance access

> As an admin, I want to assign a limited finance role so finance staff can operate invoice/payment workflows without broad admin access.

**Rule 1: Single-role finance assignment**

- An existing admin assigns `finance` to an active user.
- Assignment to an existing finance user is idempotent.
- Assignment to an inactive user is rejected.
- Finance replaces the user's prior single role; multi-role users are out of scope.
- Role removal takes effect on the next request.

```gherkin
Scenario: Admin assigns finance role
  Given an authenticated active admin and an active target user
  When the admin assigns the finance role
  Then the target user has exactly the finance role
  And the assignment is audited

Scenario: Repeated finance assignment is idempotent
  Given an active user already has the finance role
  When an admin assigns finance again
  Then no duplicate role assignment is created
  And the request succeeds without changing unrelated user data

Scenario: Inactive user cannot receive finance access
  Given the target user is inactive
  When an admin assigns the finance role
  Then the request is rejected
  And the inactive user cannot access finance endpoints

Scenario: Role removal invalidates access on the next request
  Given a user previously had the finance role
  When an admin removes the finance role
  Then the user's next finance request is forbidden
  And an old token does not preserve finance access
```

**Rule 2: Finance authorization scope**

```gherkin
Scenario: Finance can operate payment workflows
  Given an authenticated finance user
  When the user requests invoice/payment/reminder/finance-metrics data
  Then the request is allowed for authorized operational data

Scenario: Finance cannot access unrelated administration
  Given an authenticated finance user
  When the user requests users, products, marketplace, credit-limit configuration, AI, sales, delivery assignment, or order approval data
  Then the request is forbidden

Scenario: Outlet data is isolated
  Given outlet A and outlet B both have invoices
  When outlet A requests its invoice history
  Then only outlet A's authorized data is returned
  And outlet B's data is not exposed
```

### Story: Invoice lifecycle

> As an admin/finance user, I want an invoice to represent the payment lifecycle of an approved order so outstanding and overdue balances are reliable.

**Rule 1: Creation and due date**

- Invoice is created when an order is approved.
- Outlet terms are integer days from 1 through 90.
- If no term is configured, the invoice uses a 7-day fallback.
- Term changes affect new invoices only.
- Invoice creation is unique per order and safe to retry.

```gherkin
Scenario: Approved order creates one invoice
  Given a confirmed order and an outlet with a 14-day term
  When an admin approves the order
  Then exactly one invoice is created
  And its due date is 14 days after issue date
  And it contributes to outstanding balance

Scenario: Missing term uses seven-day fallback
  Given an outlet has no configured payment term
  When an approved order creates an invoice
  Then the due date is seven days after issue date

Scenario: Term changes affect only new invoices
  Given an outlet has an existing unpaid invoice
  When an admin changes the outlet term
  Then the existing invoice due date is unchanged
  And a later invoice uses the new term

Scenario: Approval retry does not duplicate invoice
  Given an approved order already has an invoice
  When the approval request is retried
  Then the existing invoice is reused
  And no second invoice is created
```

**Rule 2: Payment state and cancellation**

- Finance may record payment only after the order is delivered.
- Balance authority is the sum of valid payment records.
- Partial payment produces `partially_paid`; the due date remains active.
- Full payment produces `paid` and suppresses future reminders.
- Admin alone may cancel an approved order with no payment.
- An order with any payment cannot be cancelled in this slice.
- A cancelled unpaid invoice is excluded from outstanding/overdue and rejects new payment.

```gherkin
Scenario: Payment before delivery is rejected
  Given an invoice exists for an approved but undelivered order
  When finance records a payment
  Then the request is rejected
  And invoice balance and status remain unchanged

Scenario: Partial payment preserves remaining balance
  Given a delivered invoice of Rp1,000,000 is unpaid
  When finance records Rp300,000
  Then invoice status becomes partially_paid
  And the balance is Rp700,000
  And the original due date remains active

Scenario: Full payment closes invoice
  Given a delivered invoice has Rp700,000 remaining
  When finance records Rp700,000
  Then invoice status becomes paid
  And it is excluded from outstanding and overdue metrics
  And no future reminder is generated

Scenario: Overpayment and duplicate payment are rejected safely
  Given a delivered invoice has a remaining balance
  When finance submits a zero, negative, overpayment, duplicate, or conflicting payment identity
  Then the request is rejected with a validation/conflict response
  And no duplicate balance mutation occurs

Scenario: Admin cancels unpaid invoice
  Given an approved order has an unpaid invoice
  When an admin cancels the order
  Then the invoice becomes cancelled
  And it is excluded from outstanding and overdue metrics
  And new payment is rejected

Scenario: Payment prevents cancellation
  Given an invoice has any valid payment
  When an admin attempts to cancel the order
  Then the request is rejected
  And the payment records and invoice state remain unchanged
```

**Rule 3: Legacy backfill**

```gherkin
Scenario: Existing delivered/paid orders are backfilled
  Given delivered or paid legacy orders without invoices exist
  When the idempotent backfill runs
  Then one invoice is created per eligible order
  And existing valid payment records determine the balance/status

Scenario: Backfill retry is safe
  Given a prior backfill partially completed
  When the backfill runs again
  Then existing invoices are reused
  And no duplicate invoice or payment record is created
```

### Story: Reminder workflow

> As finance, I want controlled WhatsApp reminders so outlets are notified without duplicates or uncontrolled retries.

**Rule 1: Timing and uniqueness**

- Timezone is `Asia/Jakarta`.
- H-1 reminder is generated once when an eligible unpaid invoice is one day before due.
- Overdue reminder is generated once when the invoice first crosses the due boundary.
- If H-1 scheduling is missed, the next run sends it if the invoice is still eligible and the event was not sent.
- A unique key is based on invoice, event type, and event date.

```gherkin
Scenario: H-1 reminder is sent once
  Given an unpaid invoice is due tomorrow in Asia/Jakarta
  When the reminder scheduler processes it
  Then one H-1 WhatsApp reminder is created
  And a second run on the same day reuses the existing reminder

Scenario: Missed H-1 reminder is recovered
  Given the H-1 scheduler did not run
  And the invoice remains unpaid and eligible
  When the scheduler runs next
  Then the H-1 reminder is created once

Scenario: Overdue reminder is sent once
  Given an unpaid invoice crosses its due date
  When the scheduler processes it
  Then one overdue reminder is created
  And later overdue runs do not send another overdue reminder for that invoice
```

**Rule 2: Provider failure and retry**

- Maximum 3 retries with exponential backoff at 1, 5, and 15 minutes.
- Retry reuses the same logical/provider idempotency key.
- Final failure is audited.
- Reminder failure never changes invoice status or balance.

```gherkin
Scenario: Failed reminder uses bounded retry
  Given the WhatsApp provider returns an error
  When the reminder send attempt fails
  Then the failure and attempt are recorded
  And up to three retries use the same reminder identity
  And the final failure is marked failed
  And invoice status and balance are unchanged

Scenario: Paid invoice suppresses reminder
  Given an invoice becomes paid before a scheduled reminder
  When the scheduler processes it
  Then no reminder is sent
```

### Story: Visibility, metrics, and delivery proof

> As admin/finance, I want bounded invoice/payment visibility and baseline metrics; as an operator, I want delivery completion to retain recipient proof.

**Rule 1: Bounded visibility**

```gherkin
Scenario: Finance views bounded payment history
  Given finance is authorized to view payment history
  When finance requests a page
  Then the response contains bounded rows and pagination metadata
  And results have stable ordering

Scenario: Invalid pagination is rejected safely
  Given a history endpoint receives an invalid page or limit
  When the request is processed
  Then a validation response is returned
  And no unbounded query is executed
```

**Rule 2: Invoice/payment metrics**

- Default window is the last 30 days.
- Current outstanding/status metrics include all active invoices, including invoices issued before the window.
- Metrics include issued invoices, outstanding balance, overdue rate, average collection time to full payment, payment-status breakdown, and reminder success/failure.
- Issue date, due date, and payment date are used according to metric semantics.
- Zero denominators return `0`, not an error.

```gherkin
Scenario: Metrics use current state and 30-day defaults
  Given active invoices include records issued before and inside the last 30 days
  When finance opens metrics without a date filter
  Then the response uses the last-30-day default for event metrics
  And current outstanding/status metrics include all active invoices
  And all six metric groups are returned

Scenario: Fully paid collection time is measured
  Given an invoice was issued and paid through one or more payments
  When metrics are calculated
  Then collection time is measured from issue date to full payment date
  And partially paid invoices are not counted as fully collected

Scenario: Empty metric denominator is safe
  Given no invoice qualifies for a metric denominator
  When metrics are calculated
  Then that metric returns zero
  And the response remains valid
```

**Rule 3: Delivery proof**

```gherkin
Scenario: Delivery cannot complete without proof
  Given a delivery is in progress
  When an operator submits delivered without a nonblank recipient name or valid proof URL
  Then the request is rejected
  And the delivery remains in progress

Scenario: Delivery completes with proof
  Given a delivery is in progress
  When an operator submits a recipient name and valid proof URL
  Then delivery becomes delivered
  And the proof data is stored and returned
```

---

## Acceptance Criteria

```text
Rule: Finance access
  ✓ Given an active user and admin, When finance is assigned, Then the user has exactly one finance role and can access scoped finance operations.
  ✓ Given a finance user, When unrelated admin/product/user/approval endpoints are requested, Then access is forbidden.
  ✓ Given a removed finance role, When the next request uses an old token, Then finance access is denied.

Rule: Invoice creation and terms
  ✓ Given an approved order, When approval completes, Then exactly one invoice is created.
  ✓ Given an outlet term of 1–90 integer days, When an invoice is created, Then the due date uses that term.
  ✓ Given no outlet term, When an invoice is created, Then a 7-day fallback is used.
  ✗ Given zero, negative, fractional, or >90-day term, When configured, Then validation rejects it.
  ✓ Given an existing invoice, When the outlet term changes, Then its due date is unchanged.

Rule: Payment lifecycle
  ✓ Given an undelivered order, When finance records payment, Then the request is rejected.
  ✓ Given a delivered invoice and partial payment, When payment is recorded, Then status and remaining balance are correct.
  ✓ Given a delivered invoice and exact remaining payment, When payment is recorded, Then status becomes paid and reminders stop.
  ✗ Given zero, negative, overpayment, duplicate, or conflicting payment identity, When submitted, Then no duplicate mutation occurs.
  ✓ Given an unpaid approved invoice, When admin cancels the order, Then invoice is cancelled and excluded from outstanding.
  ✗ Given any payment exists, When cancellation is attempted, Then cancellation is rejected.

Rule: Legacy backfill
  ✓ Given eligible legacy orders without invoices, When backfill runs, Then one invoice is created using valid payment records.
  ✓ Given backfill runs repeatedly or after partial failure, When rerun, Then no duplicates are created.

Rule: Reminders
  ✓ Given an eligible invoice at H-1 in Asia/Jakarta, When the scheduler runs, Then one H-1 reminder is created.
  ✓ Given an invoice crosses due date, When the scheduler runs, Then one overdue reminder is created.
  ✓ Given H-1 was missed, When the next eligible run occurs, Then the missing event is sent once.
  ✗ Given provider failure, When retries occur, Then maximum three retries use one identity and final failure is audited without changing invoice state.
  ✓ Given invoice is paid/cancelled, When scheduler runs, Then no eligible reminder is sent.

Rule: Visibility and metrics
  ✓ Given authorized finance/outlet users, When history is requested, Then data is role-scoped, bounded, ordered, and paginated.
  ✗ Given invalid pagination, When requested, Then validation fails without an unbounded query.
  ✓ Given the default window, When metrics are requested, Then the six invoice/payment metric groups use the defined date/current-state rules.
  ✓ Given a zero denominator, When metrics are calculated, Then the result is zero without an error.

Rule: Delivery proof
  ✗ Given missing recipient or invalid proof URL, When delivery is completed, Then the request is rejected.
  ✓ Given valid recipient and proof URL, When delivery is completed, Then proof is persisted and visible.
```

---

## Design Decision

**Chosen option:** Option A — Extend Existing Operational Flow.

**Summary:** Extend the existing Laravel/Next.js order, payment, WhatsApp, analytics, and delivery patterns with explicit invoice/reminder records and a bounded finance access surface. This minimizes architectural change while creating the operational data needed for later roadmap work.

**Rejected options:**

- Option B — Finance Permission Module: rejected for Slice 1 because migrating from the current single-role model to a broad permission architecture expands auth risk and scope.
- Option C — Event-Driven Operational Ledger: rejected for Slice 1 because queue/event ordering and reconciliation would delay the first operational value.

**Key tradeoffs accepted:**

- A single finance role is intentionally less flexible than a full permission graph.
- Reminder processing uses bounded retries and existing WhatsApp patterns rather than a new distributed event architecture.
- Metrics are intentionally limited to invoice/payment operations and do not expand into geographic or supplier BI.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Where should finance metrics render? | Assumption: use the existing dashboard surface or a finance subsection without changing the API metric contract. | Minor UI placement rework. |
| What invoice identifier format is displayed? | Assumption: reuse existing order/payment reference conventions; exact display format is implementation detail. | Minor UI/API formatting change. |
| How are cancelled invoices treated in overdue-rate denominator? | Assumption: cancelled invoices are excluded from active overdue denominator. | Metric definition adjustment. |

---

## Implementation Notes

- Add migrations with explicit down paths and indexes for invoice order/status/due-date queries, payment reconciliation, and reminder uniqueness.
- Preserve current `PaymentService` authorization, transaction, idempotency, and delivered-order rule.
- Use an idempotent admin-controlled backfill for legacy delivered/paid orders.
- Use request-time role authorization so finance removal takes effect without relying on stale token claims.
- Use Asia/Jakarta for reminder date boundaries and an idempotency key per invoice/event/date.
- Keep list endpoints bounded and return stable pagination metadata.
- Direct API tests must cover delivery proof validation; UI-only validation is insufficient.

---

## Rollback Plan

- Disable reminder scheduling before rollback if provider calls are causing operational issues.
- Roll back application code and migrations in dependency order, preserving existing order/payment records.
- If invoice backfill is problematic, stop the backfill command, retain its audit output, and remove only invoices created by the identified backfill run; do not delete existing payment records.
- Revert finance role assignments through the admin path and keep existing admin/outlet/sales/driver authorization behavior unchanged.
