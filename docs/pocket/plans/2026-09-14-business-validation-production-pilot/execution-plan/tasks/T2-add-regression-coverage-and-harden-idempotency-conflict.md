# Task T2 — Add regression coverage and harden idempotency conflict

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Add regression coverage and harden idempotency conflict [depends: T1] [test-risk]

## OBJECTIVE
Create regression tests at existing public HTTP boundaries and make the minimal compatibility hardening required for same-key/different-payload order requests to return `422` without changing the committed order. All other existing order-to-payment behavior remains unchanged.

Files:
- Create: `apps/api/tests/Feature/PrePilotCompatibilityTest.php`
- Create: `apps/api/tests/Feature/PrePilotConcurrencyCompatibilityTest.php`
- Modify: `apps/api/app/Http/Controllers/OrderController.php`
- Modify: `apps/api/app/Services/OrderCreationService.php`
- Modify: `apps/api/app/Models/Order.php`
- Create: `apps/api/database/migrations/2026_09_15_000002_add_idempotency_payload_hash_to_orders_table.php`

Steps:

1. Write failing regression tests for these scenarios in `PrePilotCompatibilityTest.php`:
   - valid outlet order keeps current `201` response, response fields, stock reservation, credit-limit behavior, initial `New` history, and idempotency identity;
   - identical order retry returns the same resource without a duplicate order/history;
   - same explicit key with a different canonical payload returns `422` conflict and leaves the first order, stock, credit, and history unchanged;
   - admin approval creates exactly one `Confirmed` history and one invoice on retry; WhatsApp/provider failure does not roll back the committed order;
   - delivery missing proof is rejected without delivery/order mutation;
   - partial payment and overpayment follow current payment policy and preserve outstanding balance;
   - unauthorized approval/delivery/payment attempts remain forbidden and do not mutate source data;
   - existing analytics/finance/pipeline status routes keep their current success shape for authorized roles.
   Test file: `apps/api/tests/Feature/PrePilotCompatibilityTest.php`
   Level: integration
   Test boundary: public HTTP routes; use existing factories/fixtures and do not call private methods.
   Test doubles: fake only external WhatsApp/provider transport; do not mock controllers, services, models, or authorization under test.
   Expected RED: the new test file is absent initially; after it is written, the explicit same-key/different-payload assertion must fail with the current `200` replay behavior before the hardening is implemented.
2. Write failing concurrency tests in `PrePilotConcurrencyCompatibilityTest.php` for:
   - concurrent same-identity order submissions returning one order result;
   - concurrent payment submissions not producing an invalid negative balance or duplicate posting;
   - concurrent delivery/payment or approval race preserving the existing one-wins/lock behavior documented by current tests.
   Test file: `apps/api/tests/Feature/PrePilotConcurrencyCompatibilityTest.php`
   Level: concurrency integration
   Test boundary: public HTTP routes and real database connections.
   Test doubles: fake only external WhatsApp/provider transport; preserve real database locks and unique constraints; do not mock the unit under test.
   Expected RED: the new concurrency test file is absent initially; any later failure must be a real concurrency assertion failure, not a fixture/import error.
3. Run the new tests before any implementation change:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotCompatibilityTest.php tests/Feature/PrePilotConcurrencyCompatibilityTest.php
   ```
   Expected RED: the new regression test files do not exist or contain incomplete assertions; once written, the different-payload case must specifically expose the current unsafe `200` replay behavior.
4. Implement the minimal idempotency hardening:
   - derive one canonical payload fingerprint from validated order items using the existing normalization: map each item to integer `product_id`/`quantity`, sort ascending by `product_id`, preserve the validated distinct-item set, JSON encode with `JSON_THROW_ON_ERROR`, and hash with SHA-256;
   - persist the 64-character fingerprint in a nullable indexed `orders.idempotency_payload_hash` column for new orders, with no destructive backfill;
   - on an existing idempotency identity, compare fingerprints before replay; exact match returns the existing order with current `200` replay response, different match throws a validation conflict returning HTTP `422` with `errors.idempotency_key` and message `This order request identity was already used with a different payload.`;
   - preserve request identity namespace, response fields/status for new and identical replay requests, stock/credit transaction, unique-key race handling, and source order immutability;
   - for legacy orders with a `NULL` fingerprint, derive the fingerprint from immutable `order_items` sorted by `product_id` for comparison without backfill; if required items are unavailable, reject replay with a safe `409`/validation error rather than silently treating it as exact.
5. Run the focused and existing relevant suites:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotCompatibilityTest.php tests/Feature/PrePilotConcurrencyCompatibilityTest.php
   cd apps/api && php artisan test tests/Feature/OrderTest.php tests/Feature/InvoiceTest.php tests/Feature/PaymentTest.php tests/Feature/PaymentConcurrencyTest.php tests/Feature/DeliveryTest.php tests/Feature/DeliveryConcurrencyTest.php tests/Feature/WhatsAppTest.php tests/Feature/OperationalReadinessTest.php
   ```
   Expected: all PASS. If an existing test is already failing, record it as a baseline blocker; do not weaken it.
6. Refactor while green:
   - Reuse existing test support and fixture conventions.
   - Keep test assertions at public boundaries and avoid duplicating production logic in helpers.
7. Commit:
   ```bash
   git add apps/api/tests/Feature/PrePilotCompatibilityTest.php apps/api/tests/Feature/PrePilotConcurrencyCompatibilityTest.php apps/api/app/Http/Controllers/OrderController.php apps/api/app/Services/OrderCreationService.php apps/api/app/Models/Order.php apps/api/database/migrations/2026_09_15_000002_add_idempotency_payload_hash_to_orders_table.php
   git commit -m "feat(pre-pilot): enforce idempotency payload conflicts"
   ```

## REFERENCES LOADED

- T1 compatibility matrix and baseline.
- `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md` — explicit same-key/different-payload conflict hardening rule.
- `apps/api/tests/Feature/OrderTest.php`, `InvoiceTest.php`, `PaymentTest.php`, `PaymentConcurrencyTest.php`, `DeliveryTest.php`, `DeliveryConcurrencyTest.php`, `WhatsAppTest.php`, `OperationalReadinessTest.php` — current assertions and concurrency harnesses.
- `apps/api/tests/Support/PaymentConcurrencyHarness.php`, `InvoiceConcurrencyHarness.php`, `DeliveryTestFixtures.php` — reuse patterns.

## WHY THIS APPROACH

Complexity: deep. Compatibility cannot be inferred from unit tests alone; the critical behavior crosses controllers, transactions, locks, external notification boundaries, and database constraints.

## SANDWICH CONTEXT

[CRITICAL: Only the explicit idempotency conflict hardening may change production behavior; all other existing contracts must remain unchanged.]
You are adding pre-pilot compatibility evidence and the minimal idempotency safety fix.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: the two regression tests, OrderController, OrderCreationService, Order model, and one additive migration listed above.
Available after: T1.
Architecture rule: use public HTTP boundaries and real existing transaction/concurrency behavior; preserve existing request identity, source transaction, response shape, and external transport behavior.
[RESTATE: Only same-key/different-payload conflict may be hardened; no unrelated behavior, source mutation, or pilot implementation.]

## DELIVERABLE

Given existing order, approval, delivery, invoice, payment, notification, analytics, and role flows, When regression tests run, Then status codes, response contracts, source mutations, policy, authorization, and existing idempotency behavior match the current behavior.
Given an idempotency key is reused with a different canonical payload, When the request is processed, Then `422` conflict is returned and the first order remains unchanged.
Given concurrent requests, When the database resolves them, Then existing one-wins/lock guarantees remain true.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Public-boundary assertions.
- Existing test suite remains green.
- Real lock/unique-constraint behavior for concurrency.

Must-not-have:
- No change to unrelated production behavior or public response shape.
- No weakened or deleted existing assertion.
- No destructive backfill or mutation of the committed order on conflict.
- No pilot activation or adoption fixture presented as evidence.

## STOP CONDITIONS

Done when: new compatibility tests and all relevant existing tests pass, including same-key/different-payload `422` conflict behavior.
Uncertain when: legacy orders have no payload fingerprint and the safe replay policy is unclear.
Escalate when: the hardening requires changing an unrelated public contract or existing business behavior.
