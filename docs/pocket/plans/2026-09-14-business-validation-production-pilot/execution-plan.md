# EXECUTION PLAN — Pre-Pilot Feature Compatibility & Readiness

**Date:** 2026-09-14  
**Spec:** `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`  
**Status:** draft  
**Phase:** pre-pilot; no production pilot activation  
**Total tasks:** 6

---

## Execution Overview

### Recommended Order

```text
T1 → T2, T3 (PARALLEL) → T4 → T5 → T6
```

> Dependency order is recommended; implementation must stop if the compatibility gate fails. No task in this plan activates a partner or claims pilot adoption.

### Task List

| Task | Name | Depends | Complexity |
|---|---|---|---|
| T1 | Baseline compatibility contract and evidence | prereq | standard |
| T2 | Regression coverage and idempotency conflict hardening | T1 | deep |
| T3 | Feature flag, kill switch, correlation ID, and event journal | T1 | deep |
| T4 | Readiness and operational issue API | T2, T3 | standard |
| T5 | Admin operational readiness web surface | T4 | standard |
| T6 | Final compatibility gate and pre-pilot runbook | T2, T3, T4, T5 | standard |

### Parallelizable Groups

| Group | Tasks | Unblocked after |
|---|---|---|
| Group A | T2, T3 | T1 completes |

### Hard Boundaries

- This plan does **not** create pilot-specific order tagging, pilot event tables, KPI denominator, baseline reduction target, reconciliation, or 100-order production evidence.
- Existing order, invoice, payment, delivery, WhatsApp, analytics, and pipeline behavior remains authoritative.
- New diagnostics are additive. They may write only to the new operational event journal and must never mutate source transaction rows.
- “Admin ops” means an existing `admin` user acting under current authorization; no new role is introduced.
- `READY_FOR_PILOT` is the only output gate. It means the platform is ready for a later pilot plan, not that a pilot has run.

---

## File Structure Map

```text
Rule: Compatibility baseline
  Modify: docs/pilot/pre-pilot-compatibility-matrix.md (updated by: T1)
  Modify: docs/pilot/pre-pilot-compatibility-baseline.md (updated by: T1)

Rule: Existing behavior regression and idempotency hardening
  Create: apps/api/tests/Feature/PrePilotCompatibilityTest.php (created by: T2)
  Create: apps/api/tests/Feature/PrePilotConcurrencyCompatibilityTest.php (created by: T2)
  Modify: apps/api/app/Http/Controllers/OrderController.php (created by: T2)
  Modify: apps/api/app/Services/OrderCreationService.php (created by: T2)
  Modify: apps/api/app/Models/Order.php (created by: T2)
  Create: apps/api/database/migrations/2026_09_15_000002_add_idempotency_payload_hash_to_orders_table.php (created by: T2)

Rule: Safe controls and correlation
  Create: apps/api/config/pre_pilot.php (created by: T3)
  Create: apps/api/app/Services/PrePilotFeatureGate.php (created by: T3)
  Create: apps/api/app/Http/Middleware/AttachCorrelationId.php (created by: T3)
  Create: apps/api/database/migrations/2026_09_15_000001_create_operational_events_table.php (created by: T3)
  Create: apps/api/app/Models/OperationalEvent.php (created by: T3)
  Create: apps/api/app/Services/OperationalEventService.php (created by: T3)
  Modify: apps/api/app/Http/Kernel.php (created by: T3)
  Test: apps/api/tests/Feature/PrePilotControlsTest.php (created by: T3)

Rule: Readiness and operational issues
  Create: apps/api/app/Services/OperationalReadinessService.php (created by: T4)
  Create: apps/api/app/Services/OperationalIssueService.php (created by: T4)
  Create: apps/api/app/Http/Controllers/OperationalReadinessController.php (created by: T4)
  Create: apps/api/app/Http/Requests/ListOperationalIssuesRequest.php (created by: T4)
  Modify: apps/api/routes/api.php (created by: T4)
  Test: apps/api/tests/Feature/OperationalDiagnosticsTest.php (created by: T4)

Rule: Admin operational web surface
  Create: apps/web/src/app/operations/page.tsx (created by: T5)
  Create: apps/web/src/app/operations/page.test.tsx (created by: T5)
  Create: apps/web/src/lib/operations-api.ts (created by: T5)
  Create: apps/web/src/lib/operations-types.ts (created by: T5)
  Modify: apps/web/src/components/Sidebar.tsx (created by: T5)
  Test: apps/web/src/components/Sidebar.test.tsx (created by: T5)

Rule: Pre-pilot operating gate
  Create: docs/pilot/pre-pilot-runbook.md (created by: T6)
  Create: docs/pilot/pre-pilot-gate-checklist.md (created by: T6)
```

---

## Pocket Packets

---

### Task 1: Establish compatibility baseline and contract [prereq]

## OBJECTIVE
Review and refine the existing compatibility contract and evidence baseline for every existing order-to-payment feature that must remain unchanged. This is documentation-only; do not add production code or pilot schema.

Files:
- Create: `docs/pilot/pre-pilot-compatibility-matrix.md`
- Create: `docs/pilot/pre-pilot-compatibility-baseline.md`

Steps:

1. Read the current implementation and relevant tests listed in the spec. Record, for each feature, public route, actor roles, success/error status codes, important response fields, idempotency behavior, concurrency guarantee, retry/lease behavior, and source tables.
2. Review/update `pre-pilot-compatibility-matrix.md` with rows for auth/RBAC, order, approval/invoice, delivery, payment, WhatsApp, analytics/finance/pipeline, web, database/config. Mark each row `preserve`, `additive`, or `blocked`; identify the exact regression command that proves it.
3. Review/update `pre-pilot-compatibility-baseline.md` with:
   - current role matrix (`admin`, `outlet`, `sales`, `driver`, `finance`);
   - current endpoint and response contract references;
   - known concurrency/idempotency cases;
   - source-of-truth boundaries;
   - baseline test commands;
   - explicit gaps and assumptions.
4. Verify documentation:
   ```bash
   test -s docs/pilot/pre-pilot-compatibility-matrix.md && \
   test -s docs/pilot/pre-pilot-compatibility-baseline.md && \
   rg -n "admin|outlet|sales|driver|finance|idempot|concurr|payment policy|WhatsApp|response|source of truth" docs/pilot/pre-pilot-compatibility-*.md
   ```
   Expected: both documents exist and every compatibility area has an evidence command or a clearly marked gap.
5. Review while green:
   - Remove any claim that a production pilot, partner activation, two-week window, or 100 valid orders has occurred.
   - Ensure “admin ops” is mapped to existing `admin`, not a new role.
6. Commit:
   ```bash
   git add docs/pilot/pre-pilot-compatibility-matrix.md docs/pilot/pre-pilot-compatibility-baseline.md
   git commit -m "docs(pre-pilot): refine feature compatibility baseline"
   ```

## REFERENCES LOADED

- `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md` — pre-pilot scope and compatibility contract.
- `apps/api/routes/api.php` — current route and middleware surface.
- `apps/api/app/Http/Controllers/OrderController.php` — order/approval response and authorization behavior.
- `apps/api/app/Services/OrderCreationService.php` — transaction, stock, credit, and order idempotency boundary.
- `apps/api/app/Services/PaymentService.php` — payment lock, cents-safe balance, policy, and replay boundary.
- `apps/api/app/Http/Controllers/DeliveryController.php` — delivery authorization and state transition boundary.
- `apps/api/app/Services/WhatsAppOutboundService.php` — provider idempotency, lease, and failure behavior.
- `apps/api/tests/Feature/OperationalReadinessTest.php` — established end-to-end evidence conventions.

## WHY THIS APPROACH

Complexity: standard. A written contract is needed before test expansion so regression tests protect real existing behavior rather than an invented pilot contract.

## SANDWICH CONTEXT

[CRITICAL: The pre-pilot phase must preserve existing feature behavior and must not activate or claim a production pilot.]
You are defining the compatibility baseline for the Digital Distribution Management Platform.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: the two documentation files listed above only.
Available after: none.
Architecture rule: document actual behavior from the repository; do not invent routes, roles, schema, or production evidence.
[RESTATE: The pre-pilot phase must preserve existing feature behavior and must not activate or claim a production pilot.]

## DELIVERABLE

Given the current repository, When an engineer reads the matrix, Then every existing feature has a compatibility promise, source-of-truth boundary, and verification command.
Given an assumption is not verifiable from code/tests, When the baseline is written, Then it is marked as a gap/assumption rather than presented as fact.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Exact existing roles/routes/test commands are cited.
- Compatibility and non-goals are explicit.
- No production pilot claim.

Must-not-have:
- No new API/schema proposal in this task.
- No invented role or tenant model.

## STOP CONDITIONS

Done when: both documents are reviewable and all areas in the spec matrix are covered.
Escalate when: current implementation and spec conflict on a compatibility promise.

---

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

---

### Task 3: Implement safe pre-pilot controls, correlation ID, and operational event journal [depends: T1] [test-risk]

## OBJECTIVE
Add independently kill-switchable pre-pilot controls and safe request correlation without changing any existing transaction behavior or response JSON body. Persist only redacted operational request outcomes in an additive journal when the feature is enabled.

Files:
- Create: `apps/api/config/pre_pilot.php`
- Create: `apps/api/app/Services/PrePilotFeatureGate.php`
- Create: `apps/api/app/Http/Middleware/AttachCorrelationId.php`
- Create: `apps/api/database/migrations/2026_09_15_000001_create_operational_events_table.php`
- Create: `apps/api/app/Models/OperationalEvent.php`
- Create: `apps/api/app/Services/OperationalEventService.php`
- Modify: `apps/api/app/Http/Kernel.php`
- Test: `apps/api/tests/Feature/PrePilotControlsTest.php`

Steps:

1. Write failing test for: correlation header behavior, JSON compatibility, and journal outcome capture.
   Test file: `apps/api/tests/Feature/PrePilotControlsTest.php`
   Level: integration
   Test intent:
   Given a request with a safe `X-Correlation-ID`, When `/api/health` or an existing authenticated endpoint completes, Then the same ID is returned in the response header, the existing JSON body/status remains unchanged, and no secret is persisted.
   Given no valid ID or an unsafe/oversized ID, When the request completes, Then a server-generated bounded ID is returned.
   Given the pre-pilot flag is enabled, When one request succeeds or fails, Then exactly one operational event stores route/action, actor when available, status, outcome, error class, timestamp, and correlation ID with redacted metadata.
   Given the journal write fails, When the original request completes, Then the original response remains unchanged and the failure is logged safely.
   Exercise through: Laravel HTTP middleware and existing public route, including an exception response boundary.
   Test doubles: no production service mocks; use real request and database; simulate journal failure only at the persistence seam.
   Expected RED: middleware/config/journal and success/failure wiring do not exist.
2. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php --filter=correlation
   ```
   Expected failure: missing middleware or header assertion.
3. Implement minimal code:
   - Add `pre_pilot.php` with `enabled` and `kill_switch` environment values defaulting safely off.
   - Add `PrePilotFeatureGate` with one public `isEnabled()` boundary; new features must use it, existing transactions must not depend on it.
   - Add `AttachCorrelationId` to the API middleware group. Accept only bounded safe characters; otherwise generate a UUID. Put the ID in request attributes/log context and response header, including exception responses.
   - Add the operational event migration/model/service with correlation ID, route/action, actor ID, HTTP status, outcome, error class, timestamps, and redacted metadata. Do not store request body, auth header, token, password, phone, or payment credential.
   - Wire the middleware/service around `$next($request)` so one success or failure outcome is recorded after response/exception classification; use a stable event identity to prevent duplicate writes.
   - Journal only when the feature gate is enabled; journal failure must not fail or roll back the original request and must emit a safe log entry.
   - Define bounded correlation input as a non-empty value of at most 100 ASCII characters from `[A-Za-z0-9._:-]`; generated IDs are UUIDs.
4. Run focused test — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php --filter=correlation
   ```
5. Write failing test for: flag and kill switch isolation.
   Test intent:
   Given the flag is off or kill switch is on, When existing order/payment/health behavior is exercised, Then existing workflow remains available and only additional journal behavior is disabled; when enabled, the new journal is written with redacted metadata.
   Exercise through: config gate, existing route, and migration.
6. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php --filter=flag
   ```
   Expected RED: the flag does not yet isolate journal capture or kill-switch behavior.
7. Implement minimal flag behavior and migration rollback without changing existing JSON bodies or source rows.
8. Run all task tests — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php
   ```
9. Refactor while green:
   - Keep redaction and event identity in `OperationalEventService`; no generic utility.
   - Ensure middleware/database failure is fail-open for existing requests but visible in safe application logs.
   - Verify migration `down()` drops only the additive operational journal table.
10. Commit:
   ```bash
   git add apps/api/config/pre_pilot.php apps/api/app/Services/PrePilotFeatureGate.php apps/api/app/Http/Middleware/AttachCorrelationId.php apps/api/database/migrations/2026_09_15_000001_create_operational_events_table.php apps/api/app/Models/OperationalEvent.php apps/api/app/Services/OperationalEventService.php apps/api/app/Http/Kernel.php apps/api/tests/Feature/PrePilotControlsTest.php
   git commit -m "feat(pre-pilot): add safe controls and correlation IDs"
   ```

## REFERENCES LOADED

- T1 compatibility contract.
- `apps/api/app/Http/Kernel.php` — middleware groups and aliases.
- `apps/api/routes/api.php` — existing API route behavior.
- `apps/api/config/orders.php`, `apps/api/config/whatsapp.php` — existing additive config conventions.
- `apps/api/tests/Feature/OperationalReadinessTest.php` — config/database test conventions.

## WHY THIS APPROACH

Complexity: deep. Middleware, configuration, persistence, redaction, and fail-open behavior can accidentally affect every API request; regression isolation is required.

## SANDWICH CONTEXT

[CRITICAL: New controls must be additive and fail-open for existing transactions; the operational journal is not transaction truth.]
You are implementing pre-pilot operational controls.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: listed config, middleware, journal, kernel, and test files only.
Available after: T1.
Architecture rule: no new dependency; do not mutate order/invoice/payment/delivery/WhatsApp/pipeline source rows; preserve existing JSON bodies.
[RESTATE: New controls must be additive and fail-open for existing transactions; the operational journal is not transaction truth.]

## DELIVERABLE

Given a valid or absent correlation header, When an API request completes, Then a safe correlation ID is returned without JSON contract changes.
Given flag off/kill switch on, When existing transactions run, Then they remain available and no new operational side effect is required.
Given flag on, When a request completes or fails, Then one redacted operational event is available without changing the request outcome.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Safe defaults and tested kill switch.
- Correlation response header.
- Redaction and journal failure isolation.
- Additive reversible migration.

Must-not-have:
- No source transaction mutation.
- No response body/status breaking change.
- No new retry/auth/observability dependency.

## STOP CONDITIONS

Done when: controls tests pass and existing compatibility suite remains green.
Escalate when: middleware or journal cannot fail open without hiding a production failure.

---

### Task 4: Expose readiness and operational issue diagnostics [depends: T2, T3] [test-risk]

## OBJECTIVE
Provide admin-only, read-only API surfaces for pre-pilot readiness and operational issues using existing authorization and the pre-pilot feature gate. Define exact disabled responses, bounded filters, pagination, severity mapping, and detail schemas; readiness checks and issue queries must not mutate source transactions or trigger retries.

Files:
- Create: `apps/api/app/Services/OperationalReadinessService.php`
- Create: `apps/api/app/Services/OperationalIssueService.php`
- Create: `apps/api/app/Http/Controllers/OperationalReadinessController.php`
- Create: `apps/api/app/Http/Requests/ListOperationalIssuesRequest.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/OperationalDiagnosticsTest.php`

Steps:

1. Write failing test for: admin-only readiness endpoint and feature-gate behavior.
   Test file: `apps/api/tests/Feature/OperationalDiagnosticsTest.php`
   Level: integration
   Test intent:
   Given an admin and a non-admin, When they request `GET /api/admin/operations/readiness`, Then admin receives HTTP 200 with `{status: "success", data: {status: "ready|warning|blocked", checks: [{name,status,evidence,remediation}], evaluated_at, correlation_id}}`; non-admin receives HTTP 403 using the existing authorization style; no order/invoice/payment/delivery/WhatsApp/pipeline source row changes.
   Checks must cover database connectivity, pre-pilot flag/kill switch, `Asia/Jakarta` scheduler convention with `withoutOverlapping`, WhatsApp required configuration when enabled, and latest data pipeline state.
   Given the flag is off or kill switch is on, When any new operations endpoint is requested, Then it returns HTTP 503 with `status: "error"` and `code: "pre_pilot_disabled"`, while existing transaction endpoints remain available.
   Test doubles: controlled config/database and existing model factories; no source-service mocks.
   Expected RED: routes, controller/service, feature-gate enforcement, and exact response contract do not exist.
2. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php --filter=readiness
   ```
   Expected failure: routes/controller/service do not exist.
3. Implement minimal readiness service/controller and admin routes. Use current `isAdmin()` authorization convention; apply `PrePilotFeatureGate` to `/api/admin/operations/readiness` and `/api/admin/operations/issues*`; do not create `admin ops` role or invoke scheduler/pipeline from HTTP. Keep readiness HTTP 200 when checks are warning/blocked, and use HTTP 503/code `pre_pilot_disabled` only when the feature gate is disabled.
4. Run focused test — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php --filter=readiness
   ```
   Expected: readiness and disabled-gate scenarios PASS; source snapshots are unchanged.
5. Write failing test for: operational issue list/detail and correlation lookup.
   Test file: `apps/api/tests/Feature/OperationalDiagnosticsTest.php`
   Level: integration
   Test intent:
   Given failed `WhatsAppMessage`, failed `InvoiceReminder`, failed `DataPipelineRun`, and operational journal rows, When admin requests `GET /api/admin/operations/issues` with `source`, `status`, `severity`, `from`, `to`, `correlation_id`, `page`, and `limit` filters, Then the response is `{status: "success", data: [...], meta: {page,limit,total,has_more}}`; each issue contains `id`, `source`, `reference`, `status`, `severity`, `attempts`, `occurred_at`, `error_class`, `correlation_id`, and `next_action`.
   Severity mapping is `critical` for failed pipeline, `warning` for failed WhatsApp/reminder, and `info` for resolved operational events. Results are chronological for a correlation ID and bounded to limit 1–100.
   Given `GET /api/admin/operations/issues/{id}` is requested, Then the same safe fields plus a redacted detail are returned; unknown IDs return 404; the source rows remain unchanged.
   Given issue data contains secrets, When list/detail is returned, Then secrets, tokens, passwords, phone numbers, payment credentials, and raw provider payloads are absent.
   Test doubles: real database rows; no source mutation and no retry transport.
   Expected RED: list/detail routes, request validation, normalization, severity mapping, and correlation lookup do not exist.
6. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php --filter=issues
   ```
   Expected failure: route/service/request absent.
7. Implement `OperationalIssueService`, request validation, controller, and routes. Register:
   - `GET /api/admin/operations/readiness`
   - `GET /api/admin/operations/issues`
   - `GET /api/admin/operations/issues/{id}`
   Read existing `WhatsAppMessage`, `InvoiceReminder`, `DataPipelineRun`, and `OperationalEvent` rows; normalize to the exact safe schema without changing those models or retry state. Validate allowlisted filters (`source`, `status`, `severity`, ISO dates, `correlation_id`, `page`, `limit`), apply severity mapping, stable ordering, and bounded pagination. Gate all three routes with HTTP 503/code `pre_pilot_disabled` when disabled.
8. Run all task tests — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php
   ```
9. Refactor while green:
   - Keep source-specific mapping in `OperationalIssueService`.
   - Keep admin authorization at the controller boundary using existing service/convention.
   - Ensure response never includes request body, auth header, token, password, phone, payment credential, or raw provider payload.
10. Commit:
   ```bash
   git add apps/api/app/Services/OperationalReadinessService.php apps/api/app/Services/OperationalIssueService.php apps/api/app/Http/Controllers/OperationalReadinessController.php apps/api/app/Http/Requests/ListOperationalIssuesRequest.php apps/api/routes/api.php apps/api/tests/Feature/OperationalDiagnosticsTest.php
   git commit -m "feat(pre-pilot): expose readiness and issue diagnostics"
   ```

## REFERENCES LOADED

- T1 compatibility baseline and T2/T3 regression/control tests.
- `apps/api/app/Http/Controllers/DataPipelineController.php` — current admin status and authorization convention.
- `apps/api/app/Models/DataPipelineRun.php`, `InvoiceReminder.php`, `WhatsAppMessage.php` — existing failure/status fields.
- `apps/api/app/Console/Kernel.php` — scheduler timezone and overlap convention.
- `apps/api/tests/Feature/OperationalReadinessTest.php`, `WhatsAppTest.php`, reminder retry tests — source behavior and fixtures.

## WHY THIS APPROACH

Complexity: standard. The API aggregates several existing failure stores but must keep them read-only and avoid recreating retry or authorization logic.

## SANDWICH CONTEXT

[CRITICAL: Readiness and issue APIs are diagnostics only; they cannot mutate or retry source workflows.]
You are implementing pre-pilot operational diagnostics.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: listed readiness/issue services, controller, request, routes, and test only.
Available after: T2 and T3.
Architecture rule: use existing admin authorization, existing model state, allowlisted fields, and redaction; no new retry or auth system.
[RESTATE: Readiness and issue APIs are diagnostics only; they cannot mutate or retry source workflows.]

## DELIVERABLE

Given readiness dependencies/configuration, When admin requests readiness, Then a deterministic status and remediation evidence are returned without mutation.
Given existing failures and journal events, When admin filters issues or correlation ID, Then safe chronological diagnostics are returned without source mutation.
[must-not] Given an issue list/detail request, When it is processed, Then no retry, status update, or financial mutation occurs.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Admin-only access using existing role.
- Read-only, bounded, redacted queries.
- Deterministic status/issue response.

Must-not-have:
- No new role or authorization bypass.
- No retry triggered by GET.
- No source row updates.

## STOP CONDITIONS

Done when: diagnostics tests and T2/T3 compatibility tests pass.
Escalate when: a required readiness signal cannot be observed without mutating or invoking a source workflow.

---

### Task 5: Build admin operational readiness web surface [depends: T4] [test-risk]

## OBJECTIVE
Add an admin-only `/operations` page that displays readiness checks, issue filters, safe issue details, and correlation references using the existing API/auth/sidebar conventions. Do not add a pilot transaction workflow or client-side source-of-truth computation.

Files:
- Create: `apps/web/src/app/operations/page.tsx`
- Create: `apps/web/src/app/operations/page.test.tsx`
- Create: `apps/web/src/lib/operations-api.ts`
- Create: `apps/web/src/lib/operations-types.ts`
- Modify: `apps/web/src/components/Sidebar.tsx`
- Test: `apps/web/src/components/Sidebar.test.tsx`

Steps:

1. Write failing test for: readiness and issue page states.
   Test file: `apps/web/src/app/operations/page.test.tsx`
   Level: component/integration
   Test intent:
   Given the readiness API returns `blocked` with remediation and the issue API returns failed WhatsApp/pipeline issues, When admin opens `/operations`, Then the page renders status, check evidence, remediation, issue source/status/severity/attempts/timestamps, and correlation ID; loading and HTTP error states are explicit.
   Given the API returns HTTP 503 with `code: "pre_pilot_disabled"`, When admin opens `/operations`, Then the page renders a disabled/activation-required state and does not expose controls that imply readiness or issue data is available.
   Exercise through: rendered page and `operations-api` against mocked fetch responses.
   Test doubles: mock only fetch responses; do not mock API client or page internals.
   Expected RED: page/types/API client do not exist.
2. Run test — verify FAIL:
   ```bash
   cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx
   ```
   Expected RED: operations page/types/API client are absent and disabled-gate state is unimplemented.
3. Implement typed `operations-types.ts`, `operations-api.ts` using `apiUrl`, `authHeaders`, and stored-token conventions, then render the page with explicit loading/error/blocked/ready states.
4. Run test — verify PASS:
   ```bash
   cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx
   ```
5. Write failing test for: filters and safe issue detail.
   Test file: `apps/web/src/app/operations/page.test.tsx`
   Level: component/integration
   Test intent:
   Given issues are displayed, When admin changes `source`, `status`, `severity`, `from`, `to`, `correlation_id`, `page`, or `limit` filters or opens a row, Then the API receives only allowlisted values, the list refreshes, safe details appear, and no raw payload/secret is rendered. Failed refresh preserves the last successful list with an alert.
   Test doubles: mock fetch responses only; do not mock `operations-api`, page state, or redaction presentation.
   Expected RED: filter serialization, detail loading, disabled response handling, and resilient refresh behavior are unimplemented.
6. Run test — verify FAIL using the same focused command.
   Expected failure: filter/detail assertions fail because the behavior does not yet exist.
7. Implement filters/detail presentation only; server remains responsible for authorization, filtering, redaction, and truth. Do not calculate readiness or severity in the browser.
8. Write failing sidebar test and run:
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: component
   Test intent:
   Given an admin, Operations is visible; given finance/outlet/sales/driver, no new admin operations link is shown according to existing navigation behavior.
   Test doubles: mock only session/role data; do not mock Sidebar internals.
   Expected RED: no Operations navigation entry or role assertion exists.
   ```bash
   cd apps/web && npm test -- --runInBand src/components/Sidebar.test.tsx
   ```
   Expected failure: Operations navigation assertions fail before implementation.
9. Add `/operations` to `Sidebar.tsx` using current role synchronization. Do not alter existing navigation labels/routes or AppShell layout.
10. Run all task tests and typecheck:
    ```bash
    cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx src/components/Sidebar.test.tsx && npm run lint
    ```
11. Refactor while green:
    - Reuse `api.ts` and existing UI primitives.
    - Keep response mapping in `operations-api.ts`; keep state in the page.
    - Never persist or recompute financial/order truth in the client.
12. Commit:
    ```bash
    git add apps/web/src/app/operations/page.tsx apps/web/src/app/operations/page.test.tsx apps/web/src/lib/operations-api.ts apps/web/src/lib/operations-types.ts apps/web/src/components/Sidebar.tsx apps/web/src/components/Sidebar.test.tsx
    git commit -m "feat(web): add pre-pilot operations surface"
    ```

## REFERENCES LOADED

- T4 operational API contract.
- `apps/web/src/lib/api.ts` — API URL, auth headers, and token conventions.
- `apps/web/src/components/Sidebar.tsx` and `AppShell.tsx` — role-aware navigation/layout.
- `apps/web/src/app/dashboard/page.tsx` — loading/error/session patterns.
- `apps/web/package.json` — Jest, Testing Library, and typecheck commands.

## WHY THIS APPROACH

Complexity: standard. The page combines readiness, filters, detail, auth-aware navigation, and resilient error states while deliberately remaining a diagnostic consumer.

## SANDWICH CONTEXT

[CRITICAL: The web surface consumes server diagnostics; it must not redefine authorization, readiness, severity, or transaction truth.]
You are implementing the admin pre-pilot operations surface.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: listed operations page/API/types/sidebar/test files only.
Available after: T4.
Architecture rule: reuse existing auth/API/UI conventions; no pilot transaction page and no client-side mutation.
[RESTATE: The web surface consumes server diagnostics; it must not redefine authorization, readiness, severity, or transaction truth.]

## DELIVERABLE

Given blocked/ready checks and operational issues, When admin opens the page, Then the server-provided status, evidence, filters, safe detail, and correlation reference are visible.
Given a non-admin role, When navigation renders, Then no new admin operations route is exposed through the sidebar.
[must-not] Given diagnostic data renders, When the browser processes it, Then it must not mutate or recompute order, invoice, payment, or readiness truth.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Existing auth/API/UI helper reuse.
- Explicit loading/error/blocked states.
- Server-owned filters/redaction/authorization.
- Web tests and typecheck green.

Must-not-have:
- No new auth abstraction.
- No pilot order/KPI workflow.
- No raw sensitive payload rendering.

## STOP CONDITIONS

Done when: page tests, sidebar tests, and typecheck pass.
Escalate when: the API contract requires client-side truth or a new role.

---

### Task 6: Publish pre-pilot runbook and enforce final compatibility gate [depends: T2, T3, T4, T5] [test-risk]

## OBJECTIVE
Document and verify the final gate that decides whether the platform is ready for a later pilot plan. The gate must prove existing compatibility, safe rollback, kill-switch behavior, diagnostics readiness, and absence of pilot activation.

Files:
- Create: `docs/pilot/pre-pilot-runbook.md`
- Create: `docs/pilot/pre-pilot-gate-checklist.md`

Steps:

1. Write failing gate checklist in `pre-pilot-gate-checklist.md` containing required evidence:
   - T2 API/authorization/idempotency/concurrency regression commands green;
   - T3 flag, kill switch, correlation, redaction, and migration rollback green;
   - T4 readiness/issues API green and read-only source snapshot unchanged;
   - T5 web tests/typecheck green;
   - no partner/territory activation, no pilot tagging, no 100-order KPI claim, no financial-ledger mutation;
   - explicit owner and sign-off fields for platform admin and product owner.
2. Write `pre-pilot-runbook.md` with:
   - how to configure safe defaults and enable/disable the pre-pilot operational surface;
   - readiness review and remediation ownership;
   - issue inbox handling and existing retry action links, without retrying from GET;
   - correlation ID investigation and redaction rules;
   - compatibility regression commands;
   - rollback/kill-switch procedure preserving transactions;
   - handoff requirements for a separate future production-pilot spec.
3. Verify documentation:
   ```bash
   test -s docs/pilot/pre-pilot-runbook.md && \
   test -s docs/pilot/pre-pilot-gate-checklist.md && \
   rg -n "READY_FOR_PILOT|kill switch|correlation|redact|idempot|concurr|rollback|source|ledger|100|partner|territory" docs/pilot/pre-pilot-*.md
   ```
   Expected: documents exist and explicitly distinguish pre-pilot readiness from pilot execution.
4. Run the final gate commands from the saved checklist:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotCompatibilityTest.php tests/Feature/PrePilotConcurrencyCompatibilityTest.php tests/Feature/PrePilotControlsTest.php tests/Feature/OperationalDiagnosticsTest.php
   cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx src/components/Sidebar.test.tsx && npm run lint
   ```
   Expected: PASS. Run the relevant existing suites from T2 as well; any failure blocks `READY_FOR_PILOT`.
5. Review while green:
   - Confirm the plan does not instruct implementation of a pilot data model or production pilot UI.
   - Confirm all new API/UI functionality is kill-switchable and source-read-only.
   - Confirm the runbook’s future pilot handoff has unresolved partner/date/KPI decisions explicitly listed.
6. Commit:
   ```bash
   git add docs/pilot/pre-pilot-runbook.md docs/pilot/pre-pilot-gate-checklist.md
   git commit -m "docs(pre-pilot): define readiness gate and rollback"
   ```

## REFERENCES LOADED

- T1–T5 packets and the pre-pilot spec.
- `docs/panduan-pengguna.md` and `docs/dokumentasi-teknis.md` — existing operator terminology.
- `apps/api/tests/Feature/OperationalReadinessTest.php` — operational evidence convention.

## WHY THIS APPROACH

Complexity: standard. The final gate combines API, middleware, persistence, diagnostics, and web evidence; documentation alone is insufficient without repeatable commands.

## SANDWICH CONTEXT

[CRITICAL: This gate can authorize only a later pilot handoff; it must never represent that a production pilot has run.]
You are closing the pre-pilot compatibility phase.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: the two runbook/checklist files only; test execution may read the repository but must not modify production code.
Available after: T2, T3, T4, and T5.
Architecture rule: all compatibility and source-immutability gates must be green; failure means `BLOCKED`, not a relaxed checklist.
[RESTATE: This gate can authorize only a later pilot handoff; it must never represent that a production pilot has run.]

## DELIVERABLE

Given all required regression, control, diagnostic, and web checks pass, When the checklist is reviewed, Then the result is `READY_FOR_PILOT` with owner/sign-off and rollback evidence.
Given any regression or source-mutation check fails, When the gate runs, Then result is `BLOCKED` and no pilot activation is permitted.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Commands are executable and tied to evidence.
- Rollback and kill switch preserve source transactions.
- Future pilot work is explicitly separated.

Must-not-have:
- No adoption claim.
- No fixture/demo treated as production evidence.
- No instruction to bypass failed tests.

## STOP CONDITIONS

Done when: runbook/checklist are validated and all T2–T5 gates pass.
Escalate when: any existing regression, redaction, kill-switch, or source-immutability check fails.

---

## Acceptance-Criteria Traceability

| Spec rule | Task and verification |
|---|---|
| Existing API/role/state/idempotency compatibility | T2 `PrePilotCompatibilityTest.php` public-boundary regression cycles |
| Same-key/different-payload returns 422; first order unchanged | T2 RED/GREEN cycle, payload fingerprint migration/service/controller assertions |
| Existing concurrency/locking/payment policy remains safe | T2 `PrePilotConcurrencyCompatibilityTest.php` with real DB/unique constraints |
| Flag default-off and kill switch isolation | T3 flag cycle; T4 disabled-route cycle; T5 disabled-page cycle |
| Correlation ID, journal, redaction, and fail-open behavior | T3 correlation/journal cycle |
| Readiness status, evidence, remediation, no mutation | T4 readiness cycle |
| Issue list/detail, filters, severity mapping, pagination, correlation lookup | T4 issue cycle and T5 filter/detail cycle |
| Admin-only operations web surface | T4 authorization cycle and T5 sidebar/page cycle |
| Rollback, ownership, `READY_FOR_PILOT`, and no pilot activation | T6 checklist/runbook validation and final gate commands |

## Plan Summary

| Task | Name | Depends | Key verification |
|---|---|---|---|
| T1 | Establish compatibility baseline and contract | prereq | Matrix and evidence baseline cover existing feature behavior and non-goals. |
| T2 | Regression coverage and idempotency conflict hardening | T1 | Existing public HTTP contracts remain green; same-key/different-payload requests return `422` without source mutation. |
| T3 | Implement safe pre-pilot controls, correlation ID, and operational event journal | T1 | Flag/kill switch are isolated; correlation is returned; journal is redacted/additive/fail-open. |
| T4 | Expose readiness and operational issue diagnostics | T2, T3 | Admin-only read-only readiness/issues API with safe filters and source immutability. |
| T5 | Build admin operational readiness web surface | T4 | Admin sees readiness/issues; existing roles/navigation and client truth remain compatible. |
| T6 | Publish pre-pilot runbook and enforce final compatibility gate | T2, T3, T4, T5 | `READY_FOR_PILOT` is evidence-backed; any regression blocks activation. |
