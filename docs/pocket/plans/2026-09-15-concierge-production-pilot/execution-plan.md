# EXECUTION PLAN — Concierge Production Pilot

**Date:** 2026-09-15
**Spec:** docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
**Status:** draft
**Total tasks:** 3

---

## Execution Overview

### Recommended Order
```
T1 → T2 → T3
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| (none) | — | — |

All tasks are sequential — each depends on the previous.

### Constraints Reminder
**Architecture:** Existing Laravel controllers/services and Next.js pages only. No mutations to source state, financial ledger, schema migrations, or new roles.
**Out-of-scope:** Multi-partner rollout, WhatsApp-first ordering, PWA/mobile/offline, significant new features before pilot, fixture/demo claims, Phase 7-10 execution.
**Assumptions at risk:** Pilot internet downtime assumed paused (not counted in timing), retry orders are new attempts (not duplicates), backup PIC available, only working hours 08:00-17:00 WIB, in-transit orders at pilot end excluded from KPI.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

### File Structure Map

```
Rule: Partner qualification
  Create: apps/api/app/Services/PilotQualificationService.php        (created by: T1)
  Test:   apps/api/tests/Feature/PilotQualificationTest.php          (created by: T1)

Rule: Concierge order lifecycle
  Create: apps/api/app/Services/PilotMetricsService.php              (created by: T2)
  Create: apps/api/tests/Feature/PilotWorkflowTest.php               (created by: T2)
  Create: apps/api/tests/Support/PilotWorkflowFixtures.php           (created by: T2)

Rule: KPI monitoring & guardrails
  Test:   apps/api/tests/Feature/PilotWorkflowTest.php               (extended by: T2 with reliability, guardrail, volume/extend scenarios)
  Modify: apps/api/app/Services/OperationalEventService.php          (add pilot-specific query helpers)
  Note:   KPI reliability + volume/extend + guardrail scenarios live inside PilotWorkflowTest.php
          alongside the workflow lifecycle tests — one file, one coherent RED sequence.

Rule: Evaluation & decision matrix
  Create: apps/api/app/Services/PilotEvaluationService.php           (created by: T3)
  Create: apps/api/tests/Feature/PilotEvaluationTest.php             (created by: T3)
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

### Task 1: Pilot Infrastructure — Partner Qualification & KPI Helpers [prereq]

## OBJECTIVE
Create pilot infrastructure: a partner qualification validation service and KPI calculation helpers that all downstream pilot tasks depend on. No pilot can run without confirmed partner qualification.

Files:
- Create: `apps/api/app/Services/PilotQualificationService.php`
- Test: `apps/api/tests/Feature/PilotQualificationTest.php`

Steps:
1. Write failing test for: Partner qualifies with 10+ outlets
   Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
   Level: integration

   Test intent:
   Given a partner with 15 outlets that have orders in the last 30 days
   When `PilotQualificationService::evaluate($partnerId)` is called
   Then:
   - Result contains `qualified = true`
   - Result contains `outletCount = 15`
   - Result contains `outletCheck = PASS`

   Exercise through:
   - Public method `evaluate()` on `PilotQualificationService` — drives real DB query for 30-day order history

   Test doubles:
   - do NOT mock: `PilotQualificationService`, Partner/Outlet Eloquent models, Order model (used for 30-day count)
   - mock: None — use in-memory SQLite with factory-seeded data

   Expected RED:
   - `PilotQualificationService` class does not exist → PHPUnit error: class not found

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotQualificationTest::testQualifiesWithSufficientOutlets`
   Expected failure: Class `App\Services\PilotQualificationService` not found

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Services/PilotQualificationService.php`
   Implement: `evaluate($partnerId)` method that counts active outlets with recent orders, returns qualification result array

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotQualificationTest`
   Expected: PASS

5. Write failing test for: Partner fails with <10 outlets
   Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
   Level: integration

   Test intent:
   Given a partner with 5 outlets that have orders in the last 30 days
   When `PilotQualificationService::evaluate($partnerId)` is called
   Then:
   - Result contains `qualified = false`
   - Result contains `outletCheck = FAIL`

   Exercise through:
   - Public method `evaluate()` on `PilotQualificationService` — drives real DB query for 30-day order history

   Test doubles:
   - do NOT mock: `PilotQualificationService`, Partner/Outlet Eloquent models, Order model
   - mock: None — use in-memory SQLite with factory-seeded data

   Expected RED:
   - Current implementation does not enforce 10-outlet minimum threshold

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsWithInsufficientOutlets`
   Expected failure: Assertion failed — qualified should be false for 5 outlets

7. Implement outlet threshold logic:
   File: `apps/api/app/Services/PilotQualificationService.php`
   Implement: Minimum 10 active outlets check with 30-day order history

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotQualificationTest`
   Expected: PASS

9. Write failing test for: Partner qualifies with documentation, PIC, and internet
   Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
   Level: unit

   Test intent:
   Given a partner with 15 outlets, documented manual process, assigned PIC (08:00-17:00 WIB), and stable internet
   When `PilotQualificationService::evaluate($partnerId)` is called
   Then:
   - Result contains `qualified = true`
   - Result contains `documentationCheck = PASS`
   - Result contains `picCheck = PASS`
   - Result contains `internetCheck = PASS`

   Exercise through:
   - Public method `evaluate()` on `PilotQualificationService`

   Test doubles:
   - do NOT mock: `PilotQualificationService`
   - mock: Partner Eloquent model with qualification attributes

   Expected RED:
   - Documentation, PIC, and internet checks not implemented yet

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testQualifiesWithAllChecksPass`
    Expected failure: Undefined index or assertion failures for documentation/pic/internet checks

11. Implement remaining qualification checks:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Documentation baseline check, PIC assignment check (availability window), internet stability check

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testQualifiesWithAllChecksPass`
    Expected: PASS

13. Write failing test for: Partner fails documentation check
    Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
    Level: unit

    Test intent:
    Given a partner with 15 outlets but no documented manual process
    When `PilotQualificationService::evaluate($partnerId)` is called
    Then:
    - Result contains `qualified = false`
    - Result contains `documentationCheck = FAIL`

    Exercise through:
    - Public method `evaluate()` on `PilotQualificationService`

    Test doubles:
    - do NOT mock: `PilotQualificationService`
    - mock: Partner Eloquent model with missing documentation attribute

    Expected RED:
    - Documentation check passes vacuously or fails to report FAIL state

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsDocumentationCheck`
    Expected failure: Assertion failed — documentationCheck should be FAIL

15. Write failing test for: Partner fails PIC check
    Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
    Level: unit

    Test intent:
    Given a partner with 15 outlets, documentation, and internet, but no assigned PIC
    When `PilotQualificationService::evaluate($partnerId)` is called
    Then:
    - Result contains `qualified = false`
    - Result contains `picCheck = FAIL`

    Exercise through:
    - Public method `evaluate()` on `PilotQualificationService`

    Test doubles:
    - do NOT mock: `PilotQualificationService`
    - mock: Partner Eloquent model with no PIC assigned

    Expected RED:
    - PIC check passes vacuously or fails to report FAIL state

16. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsPicCheck`
    Expected failure: Assertion failed — picCheck should be FAIL

17. Write failing test for: Partner fails internet stability check
    Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
    Level: unit

    Test intent:
    Given a partner with 15 outlets, documentation, and PIC, but unstable internet
    When `PilotQualificationService::evaluate($partnerId)` is called
    Then:
    - Result contains `qualified = false`
    - Result contains `internetCheck = FAIL`

    Exercise through:
    - Public method `evaluate()` on `PilotQualificationService`

    Test doubles:
    - do NOT mock: `PilotQualificationService`
    - mock: Partner Eloquent model with unstable internet attribute

    Expected RED:
    - Internet check passes vacuously or fails to report FAIL state

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsInternetCheck`
    Expected failure: Assertion failed — internetCheck should be FAIL

19. Implement documentation check — harden FAIL path for missing documentation:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Explicit `documentationCheck = FAIL` when manual process documentation is absent

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsDocumentationCheck`
    Expected: PASS

21. Implement PIC check — harden FAIL path for missing PIC:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Explicit `picCheck = FAIL` when no PIC is assigned

22. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsPicCheck`
    Expected: PASS

23. Implement internet stability check — harden FAIL path for unstable internet:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Explicit `internetCheck = FAIL` when internet stability fails

24. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsInternetCheck`
    Expected: PASS

25. Refactor while green (bounded):
    - Extract qualification check methods into clear, single-responsibility private methods
    - Ensure `evaluate()` returns a normalized result array
    - Re-run full test suite: `cd apps/api && php artisan test --filter=PilotQualificationTest` — must stay PASS

26. Commit:
    `git add apps/api/app/Services/PilotQualificationService.php apps/api/tests/Feature/PilotQualificationTest.php`
    `git commit -m "feat(pilot): add partner qualification validation service"`

## REFERENCES LOADED
- docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md — Rule 1-4: Partner Selection, GWT scenarios for partner qualification
- apps/api/app/Services/OperationalReadinessService.php — existing readiness check pattern to follow
- apps/api/app/Services/PrePilotFeatureGate.php — existing feature gate pattern

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single service with 4 qualification checks. No cross-file coordination needed. Follows existing `OperationalReadinessService` pattern for readiness validation. Foundation for all downstream pilot tasks.

## SANDWICH CONTEXT
[CRITICAL: Do not mutate partner/outlet source data. This service reads existing partner attributes to determine qualification — it does not create or modify partner records.]
You are implementing pilot infrastructure — partner qualification validation for Concierge Production Pilot.
Spec: docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
Design decision: Option A — Pilot Minimum Viable
Files in scope: `apps/api/app/Services/PilotQualificationService.php`, `apps/api/tests/Feature/PilotQualificationTest.php`
Available after: none (prereq)
Architecture rule: All DB access through Eloquent models — no raw queries, no schema mutations
[RESTATE: Do not mutate partner/outlet source data — read-only qualification check only]

## DELIVERABLE
Given partner with 15 outlets, documentation, PIC, and internet, When `evaluate($partnerId)`, Then `qualified = true` with all checks PASS
Given partner with 5 outlets, When `evaluate($partnerId)`, Then `qualified = false` with `outletCheck = FAIL`
Given partner with 15 outlets but no documentation, When `evaluate($partnerId)`, Then `qualified = false` with `documentationCheck = FAIL`
Given partner with 15 outlets, documentation, internet, but no PIC, When `evaluate($partnerId)`, Then `qualified = false` with `picCheck = FAIL`
Given partner with 15 outlets, documentation, PIC, but unstable internet, When `evaluate($partnerId)`, Then `qualified = false` with `internetCheck = FAIL`
All tests PASS. Commit exists with message matching `feat(pilot): add partner qualification validation service`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - All 4 partner qualification rules implemented (outlet count, documentation, PIC, internet)
  - Tests written BEFORE implementation (TDD — not after)
  - `evaluate()` returns normalized result array with per-check status
  - Commit message follows conventional commits format

Must-not-have:
  - Mutating partner/outlet source data
  - Modifying files outside listed scope
  - Skipping the failing test step (implement-then-test is a plan violation)

Open question risks:
  - Internet stability check may need external monitoring data → if no data source exists, report NEEDS_CONTEXT
  - PIC availability window validation may depend on timezone handling → assume WIB (UTC+7)

Rollback note:
  - New service + test files only — delete files to rollback. No schema changes.

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: internet stability check has no data source in existing codebase
Escalate when: task touches partner/outlet source data or schema migrations

---

### Task 2: Pilot Workflow Execution — Order-to-Payment Lifecycle with KPI Collection [depends: T1]

## OBJECTIVE
Implement and verify the full concierge pilot workflow: order creation → approval → delivery → invoicing → payment, with inline timing measurement and KPI collection. This is the core pilot execution task — it exercises every platform feature in the order-to-payment pipeline and validates the end-to-end flow works in production-like conditions.

Files:
- Create: `apps/api/app/Services/PilotMetricsService.php`
- Create: `apps/api/tests/Feature/PilotWorkflowTest.php`
- Create: `apps/api/tests/Support/PilotWorkflowFixtures.php`
- Modify: `apps/api/app/Services/OperationalEventService.php` (add pilot-specific event query helpers)

Steps:
1. Write failing test for: Concierge creates order as outlet
   Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
   Level: integration

   Test intent:
   Given concierge authenticated as outlet "Warung Sejahtera"
   When concierge creates order with product A, qty 10
   Then:
   - Order is created with status "New"
   - `actor_id` matches outlet "Warung Sejahtera"
   - Operational event logged: `order.created` with correlation ID
   - Order `created_at` timestamp is recorded

   Exercise through:
   - `POST /api/orders` endpoint (OrderController::store)

   Test doubles:
   - do NOT mock: OrderController, OrderCreationService, OperationalEventService
   - mock: WhatsApp notification sender (external service)
   - use: PilotWorkflowFixtures for partner/outlet/product seed data

   Expected RED:
   - PilotWorkflowFixtures class does not exist, or Order creation flow not yet exercised in pilot context

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotWorkflowTest::testConciergeCreatesOrderAsOutlet`
   Expected failure: Class not found or assertion failure on missing pilot event

3. Create test fixtures and implement order creation test support:
   File: `apps/api/tests/Support/PilotWorkflowFixtures.php`
   Implement: Seed partner, outlets, products, and user for pilot testing
   File: `apps/api/tests/Feature/PilotWorkflowTest.php`
   Implement: Login as outlet, POST order, verify status + actor_id + event

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotWorkflowTest::testConciergeCreatesOrderAsOutlet`
   Expected: PASS

5. Write failing test for: Order flows through full lifecycle to delivered
   Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
   Level: integration

   Test intent:
   Given order with status "New"
   When admin approves order → status becomes "Confirmed" and invoice is created
   When delivery is completed → status becomes "Delivered"
   Then:
   - Time from `order.created_at` to `delivered_at` is measurable
   - Status history contains: New → Confirmed → Delivered
   - Invoice exists with correct order reference

   Exercise through:
   - `PUT /api/orders/{id}/approve` (OrderController::approve)
   - `PATCH /api/deliveries/{id}/status` (DeliveryController::updateStatus)

   Test doubles:
   - do NOT mock: OrderController, DeliveryController, InvoiceService
   - mock: WhatsApp notification sender
   - use: PilotWorkflowFixtures

   Expected RED:
   - Delivery lifecycle not yet verified in pilot context, timing measurement missing

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotWorkflowTest::testOrderLifecycleToDelivered`
   Expected failure: Assertion failure on missing status transitions or timing

7. Implement order lifecycle test with timing measurement:
   File: `apps/api/tests/Feature/PilotWorkflowTest.php`
   Implement: Create order → approve → deliver → verify status chain + timing

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotWorkflowTest::testOrderLifecycleToDelivered`
   Expected: PASS

9. Write failing test for: Order cancelled before approval is excluded from valid orders
   Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
   Level: integration

   Test intent:
   Given order with status "New"
   When admin cancels order → status becomes "Cancelled"
   Then:
   - Status history contains: New → Cancelled
   - Cancelled order is NOT counted in valid order denominator
   - Delivery success denominator is NOT affected by cancelled orders

   Exercise through:
   - `PUT /api/orders/{id}/cancel` (OrderController::cancel)

   Test doubles:
   - do NOT mock: OrderController
   - use: PilotWorkflowFixtures

   Expected RED:
   - Cancellation exclusion logic not verified in pilot metrics context

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testCancelledOrderExcludedFromValidCount`
    Expected failure: Assertion failure on cancellation handling

11. Implement cancellation test:
    File: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Implement: Create order → cancel → verify status + exclusion from valid count

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testCancelledOrderExcludedFromValidCount`
    Expected: PASS

13. Write failing test for: Full lifecycle completes through payment
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: integration

    Test intent:
    Given order that has been delivered and invoiced
    When payment is recorded via PaymentService
    Then:
    - Order status becomes "Paid" (or final paid state)
    - Invoice status reflects payment
    - Payment record exists with correct order reference
    - Full lifecycle observable: New → Confirmed → Delivered → Invoiced → Paid

    Exercise through:
    - `POST /api/payments` endpoint (PaymentController::store)

    Test doubles:
    - do NOT mock: PaymentController, PaymentService, InvoiceService
    - mock: WhatsApp notification sender
    - use: PilotWorkflowFixtures

    Expected RED:
    - Payment step not yet exercised in pilot workflow context

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testFullLifecycleCompletesThroughPayment`
    Expected failure: Assertion failure on missing payment or invoice status

15. Implement payment lifecycle test:
    File: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Implement: Create order → approve → deliver → invoice → pay → verify full status chain

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testFullLifecycleCompletesThroughPayment`
    Expected: PASS

17. Write failing test for: Cancellation excludes from valid order metrics denominator
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: integration

    Test intent:
    Given 3 orders: 2 delivered, 1 cancelled
    When valid order count is computed via PilotMetricsService
    Then:
    - Valid order count = 2 (cancelled excluded)
    - Delivery success denominator = 2 (only delivered orders counted)
    - Cancelled order does not affect error rate or delivery metrics

    Exercise through:
    - `PilotMetricsService::countValidOrders($orders)` (public method)

    Test doubles:
    - do NOT mock: PilotMetricsService
    - mock: None — use real order data from PilotWorkflowFixtures

    Expected RED:
    - `countValidOrders()` does not yet exist or does not exclude cancelled orders

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testCancellationExcludesFromMetricsDenominator`
    Expected failure: Method not found or assertion failure on count

19. Implement valid order counting:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: `countValidOrders($orders)` — filters out cancelled orders, returns count and breakdown

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testCancellationExcludesFromMetricsDenominator`
    Expected: PASS

21. Write failing test for: Correlation ID propagates across full lifecycle events
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: integration

    Test intent:
    Given order created via pilot workflow with correlation ID X
    When order goes through approve → deliver → invoice → pay
    Then:
    - All operational events (order.created, order.approved, delivery.completed, invoice.created, payment.recorded) share correlation ID X
    - `getEventsByCorrelation(X)` returns events from every lifecycle stage

    Exercise through:
    - Full lifecycle via API endpoints, then `OperationalEventService::getEventsByCorrelation()`

    Test doubles:
    - do NOT mock: OrderController, DeliveryController, InvoiceService, PaymentService, OperationalEventService
    - mock: WhatsApp notification sender

    Expected RED:
    - Correlation ID propagation across all lifecycle events not verified

22. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testCorrelationIdPropagatesAcrossLifecycle`
    Expected failure: Missing events or correlation ID mismatch

23. Implement correlation propagation test:
    File: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Implement: Full lifecycle with correlation ID, verify all events share it

24. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testCorrelationIdPropagatesAcrossLifecycle`
    Expected: PASS

25. Write failing test for: Volume below target triggers extend recommendation
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: unit

    Test intent:
    Given pilot running for 7 days with only 14 valid orders completed
    When `PilotMetricsService::evaluateVolume($validOrderCount, $pilotDays)` is called
    Then:
    - Result contains `targetMet = false`
    - Result contains `validOrderCount = 14`
    - Result contains `extendDays = 3`
    - Result contains `newDeadline = day 10`

    Exercise through:
    - Public method `evaluateVolume()` on `PilotMetricsService`

    Test doubles:
    - do NOT mock: `PilotMetricsService`
    - mock: None — pure calculation

    Expected RED:
    - `evaluateVolume()` method does not exist yet

26. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testVolumeBelowTargetTriggersExtend`
    Expected failure: Method `App\Services\PilotMetricsService::evaluateVolume` not found

27. Implement volume evaluation:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: `evaluateVolume($validOrderCount, $pilotDays)` — checks minimum 20 valid orders within pilot duration, recommends +3 day extension if not met

28. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testVolumeBelowTargetTriggersExtend`
    Expected: PASS

29. Write failing test for: Volume meets target
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: unit

    Test intent:
    Given pilot running for 7 days with 22 valid orders completed
    When `PilotMetricsService::evaluateVolume($validOrderCount, $pilotDays)` is called
    Then:
    - Result contains `targetMet = true`
    - Result contains `validOrderCount = 22`
    - Result contains `extendDays = 0`

    Exercise through:
    - Public method `evaluateVolume()` on `PilotMetricsService`

    Test doubles:
    - do NOT mock: `PilotMetricsService`
    - mock: None — pure calculation

    Expected RED:
    - `evaluateVolume()` may not correctly return `targetMet = true` for sufficient orders

30. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testVolumeMeetsTarget`
    Expected failure: Assertion failure — targetMet should be true

31. Implement volume target threshold:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: Ensure `evaluateVolume()` returns `targetMet = true` and `extendDays = 0` when validOrderCount >= 20

32. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testVolumeMeetsTarget`
    Expected: PASS

33. Write failing test for: PilotMetricsService calculates speed delta
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: unit

    Test intent:
    Given baseline manual time of 48 hours and platform average of 4 hours
    When `PilotMetricsService::calculateSpeedDelta($baselineHours, $platformHours)` is called
    Then:
    - Result contains `deltaPercent = -91.7` (approximately)
    - Result contains `targetMet = true` (target was -30%)

    Exercise through:
    - Public method `calculateSpeedDelta()` on `PilotMetricsService`

    Test doubles:
    - do NOT mock: `PilotMetricsService`
    - mock: None — pure calculation

    Expected RED:
    - `PilotMetricsService` exists (from `evaluateVolume`) but `calculateSpeedDelta` method not yet implemented

34. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testSpeedDeltaCalculation`
    Expected failure: Method `App\Services\PilotMetricsService::calculateSpeedDelta` not found

35. Implement speed delta calculation:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: `calculateSpeedDelta($baselineHours, $platformHours)` — returns delta percentage and target-met boolean

36. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testSpeedDeltaCalculation`
    Expected: PASS

37. Write failing test for: PilotMetricsService calculates reliability guardrails
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: unit

    Test intent:
    Given 25 valid orders, 1 error, 24 delivered, 23 paid
    When `PilotMetricsService::calculateReliability($metrics)` is called
    Then:
    - `errorRate = 4%` (< 5% guardrail PASS)
    - `deliverySuccess = 96%` (> 95% guardrail PASS)
    - `paymentCompletion = 95.8%` (> 90% guardrail PASS)
    - `guardrailsPassed = true`

    Exercise through:
    - Public method `calculateReliability()` on `PilotMetricsService`

    Test doubles:
    - do NOT mock: `PilotMetricsService`
    - mock: None — pure calculation

    Expected RED:
    - Reliability calculation not implemented yet

38. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testReliabilityGuardrails`
    Expected failure: Undefined method or assertion failure

39. Implement reliability calculation:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: `calculateReliability($metrics)` — returns error rate, delivery success, payment completion, guardrail status

40. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testReliabilityGuardrails`
    Expected: PASS

41. Write failing test for: Guardrail violation detected
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: unit

    Test intent:
    Given 25 valid orders with 2 errors (8% error rate)
    When `PilotMetricsService::calculateReliability($metrics)` is called
    Then:
    - `errorRate = 8%` (> 5% guardrail FAIL)
    - `guardrailsPassed = false`

    Exercise through:
    - Public method `calculateReliability()` on `PilotMetricsService`

    Test doubles:
    - do NOT mock: `PilotMetricsService`
    - mock: None

    Expected RED:
    - Guardrail violation detection not implemented yet

42. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testGuardrailViolationDetected`
    Expected failure: Assertion failure on guardrail detection

43. Implement guardrail violation detection:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: Guardrail threshold checks with PASS/FAIL per metric

44. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testGuardrailViolationDetected`
    Expected: PASS

45. Write failing test for: OperationalEventService pilot query helpers
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: integration

    Test intent:
    Given an order was created via pilot workflow with a correlation ID and operational events logged
    When `OperationalEventService::getEventsByCorrelation($correlationId)` is called
    Then:
    - Result contains events matching the correlation ID
    - At least one event with type `order.created` is returned

    Exercise through:
    - Public method `getEventsByCorrelation()` on `OperationalEventService`

    Test doubles:
    - do NOT mock: OperationalEventService
    - mock: None — use pilot workflow seeded data

    Expected RED:
    - `getEventsByCorrelation()` method does not exist yet

46. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testOperationalEventPilotQuery`
    Expected failure: Method `App\Services\OperationalEventService::getEventsByCorrelation` not found

47. Write failing test for: OperationalEventService getPilotEvents helper
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: integration

    Test intent:
    Given multiple orders created in a pilot with pilotId "pilot-2026-09-15"
    When `OperationalEventService::getPilotEvents($pilotId)` is called
    Then:
    - Result contains only events for the specified pilot
    - Events are ordered by creation time
    - At least events for order.created and order.approved are present

    Exercise through:
    - Public method `getPilotEvents()` on `OperationalEventService`

    Test doubles:
    - do NOT mock: OperationalEventService
    - mock: None — use pilot workflow seeded data with pilotId tag

    Expected RED:
    - `getPilotEvents()` method does not exist yet

48. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testGetPilotEvents`
    Expected failure: Method `App\Services\OperationalEventService::getPilotEvents` not found

49. Implement OperationalEventService query helpers:
    File: `apps/api/app/Services/OperationalEventService.php`
    Implement: `getEventsByCorrelation($correlationId)` and `getPilotEvents($pilotId)` helper methods for pilot event retrieval

50. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testOperationalEventPilotQuery`
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testGetPilotEvents`
    Expected: BOTH PASS

51. Write failing test for: DB-measured lifecycle duration feeds into speed delta calculation
    Test file: `apps/api/tests/Feature/PilotWorkflowTest.php`
    Level: integration

    Test intent:
    Given an order created at T=0, delivered at T=4h, baseline manual time 48h
    When lifecycle timing is measured via PilotMetricsService::measureLifecycleTiming($orderId)
    And result is passed to `calculateSpeedDelta()`
    Then:
    - Measured platform hours ≈ 4h
    - Speed delta vs 48h baseline is ≈ -91.7%
    - `targetMet = true` (target was -30%)

    Exercise through:
    - `PilotMetricsService::measureLifecycleTiming()` + `calculateSpeedDelta()` chaining

    Test doubles:
    - do NOT mock: PilotMetricsService
    - mock: None — use real DB-seeded order with controlled timestamps

    Expected RED:
    - `measureLifecycleTiming()` does not exist yet, or timing not wired to speed delta

52. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testDbTimingFeedsIntoSpeedDelta`
    Expected failure: Method not found or timing mismatch

53. Implement lifecycle timing measurement:
    File: `apps/api/app/Services/PilotMetricsService.php`
    Implement: `measureLifecycleTiming($orderId)` — reads order/delivery timestamps, returns platform hours for speed delta input

54. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotWorkflowTest::testDbTimingFeedsIntoSpeedDelta`
    Expected: PASS

55. Refactor while green (bounded):
    - Rule of three: extract common test setup into `PilotWorkflowFixtures` if duplicated
    - Ensure `PilotMetricsService` methods are pure functions (no side effects)
    - Re-run full test suite: `cd apps/api && php artisan test --filter=PilotWorkflowTest` — must stay PASS

56. Commit:
    `git add apps/api/app/Services/PilotMetricsService.php apps/api/app/Services/OperationalEventService.php apps/api/tests/Feature/PilotWorkflowTest.php apps/api/tests/Support/PilotWorkflowFixtures.php`
    `git commit -m "feat(pilot): add order-to-payment workflow with KPI metrics collection"`

## REFERENCES LOADED
- docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md — Rules 1-4: Concierge Execution + Rules 1-4: KPI Monitoring, GWT scenarios for order lifecycle and KPI calculation
- apps/api/app/Http/Controllers/OrderController.php — existing order creation flow
- apps/api/app/Services/OrderCreationService.php — existing order creation with idempotency
- apps/api/app/Http/Controllers/DeliveryController.php — existing delivery confirmation
- apps/api/app/Services/InvoiceService.php — existing invoice creation
- apps/api/app/Services/PaymentService.php — existing payment processing
- apps/api/app/Services/OperationalEventService.php — existing event logging pattern
- apps/api/app/Http/Middleware/AttachCorrelationId.php — existing correlation ID pattern

## WHY THIS APPROACH
Complexity: standard
Justification: Multi-file task spanning 3+ services (Order, Delivery, Invoice, Payment) plus a new metrics service. Cross-layer integration (API controllers → services → events) requires integration-level testing. Timing measurement and KPI calculations are bundled here because they are implementation details of the workflow, not separate features.

## SANDWICH CONTEXT
[CRITICAL: Do not mutate order/invoice/payment/delivery source state beyond the standard workflow (create → approve → deliver → invoice → pay). No schema migrations, no new roles.]
You are implementing the core concierge pilot workflow for Concierge Production Pilot.
Spec: docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
Design decision: Option A — Pilot Minimum Viable
Files in scope: `apps/api/app/Services/PilotMetricsService.php`, `apps/api/app/Services/OperationalEventService.php`, `apps/api/tests/Feature/PilotWorkflowTest.php`, `apps/api/tests/Support/PilotWorkflowFixtures.php`
Available after: T1 (PilotQualificationService must exist for partner context)
Architecture rule: All DB access through Eloquent models, existing order/delivery/invoice/payment flows must not be modified — only exercised and measured
[RESTATE: Do not modify existing order/invoice/payment/delivery source state — exercise existing flows and measure timing only]

## DELIVERABLE
Given concierge authenticated as outlet, When creates order, Then order has status "New" and actor_id = outlet
Given order in "New" status, When admin approves → delivers, Then status chain is New → Confirmed → Delivered with measurable timing
Given order in "New" status, When admin cancels, Then status is "Cancelled" (excluded from valid order denominator in subsequent metrics cycle)
Given delivered+invoiced order, When payment recorded via PaymentService, Then full chain New → Confirmed → Delivered → Invoiced → Paid observable
Given 3 orders (2 delivered, 1 cancelled), When countValidOrders, Then count = 2 (cancelled excluded from denominator)
Given correlation ID X, When order goes through full lifecycle → getEventsByCorrelation(X), Then every stage event shares X
Given pilot running 7 days with 14 valid orders, When evaluateVolume, Then targetMet = false with extendDays = 3 and newDeadline = day 10
Given pilot with 22 valid orders over 7 days, When evaluateVolume, Then targetMet = true with extendDays = 0
Given baseline 48h and platform 4h, When calculateSpeedDelta, Then delta = -91.7% and targetMet = true
Given 25 orders with 1 error/24 delivered/23 paid, When calculateReliability, Then all guardrails PASS
Given 25 orders with 2 errors, When calculateReliability, Then errorRate guardrail FAILS
Given order with DB lifecycle timing, When measureLifecycleTiming → calculateSpeedDelta, Then DB-measured hours feed into speed delta
Given order created via pilot workflow with correlation ID, When getEventsByCorrelation, Then events returned including `order.created`
Given pilot pilot-2026-09-15 with multiple orders, When getPilotEvents, Then scoped events ordered by creation time
All tests PASS. Commit exists with message matching `feat(pilot): add order-to-payment workflow with KPI metrics collection`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Full order lifecycle tested: create → approve → deliver → invoice → pay
  - Cancellation excluded from valid order count
  - Volume below target triggers extend recommendation (20 minimum, +3 days)
  - Speed delta calculation with target comparison
  - Reliability guardrail calculation (error rate, delivery success, payment completion)
  - Guardrail violation detection
  - OperationalEventService pilot query helpers tested with correlation ID
  - Tests written BEFORE implementation (TDD — not after)
  - PilotWorkflowFixtures provides reusable seed data
  - Commit message follows conventional commits format

Must-not-have:
  - Modifying existing OrderController, DeliveryController, InvoiceService, or PaymentService logic
  - Schema migrations or new database tables
  - Skipping the failing test step
  - Modifications to files outside listed scope

Open question risks:
  - Pilot timing depends on real order → delivery → payment flow — if any step is blocked in test environment, timing data may be synthetic → report NEEDS_CONTEXT
  - Retry orders assumed to be new attempts (not duplicates) per spec assumption → if wrong, error rate could be inflated

Rollback note:
  - New files only + one additive modification to OperationalEventService. Delete new files and revert OperationalEventService change to rollback.

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: timing measurement cannot be validated without real pilot data
Escalate when: task touches existing order/invoice/payment/delivery source state

---

### Task 3: Pilot Evaluation — Decision Matrix & Phase 7 Evidence Documentation [depends: T2]

## OBJECTIVE
Create the pilot evaluation service that implements the decision matrix (scale-up / iterate / stop) based on measured KPIs, and produces documented evidence for Phase 7. This task completes the pilot loop: measure → evaluate → decide → document.

Files:
- Create: `apps/api/app/Services/PilotEvaluationService.php`
- Create: `apps/api/tests/Feature/PilotEvaluationTest.php`

Steps:
1. Write failing test for: Scale-up decision when all KPIs met
   Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
   Level: unit

   Test intent:
   Given pilot results: 25 valid orders, speed delta -91.7%, error rate 4%, delivery success 96%, payment completion 95.8%
   When `PilotEvaluationService::evaluate($pilotResults)` is called
   Then:
   - Result contains `decision = "scale-up"`
   - Result contains `kpiSummary` with all metrics
   - Result contains `phase7Evidence` with documented case study

   Exercise through:
   - Public method `evaluate()` on `PilotEvaluationService`

   Test doubles:
   - do NOT mock: `PilotEvaluationService`
   - mock: None — pure evaluation logic

   Expected RED:
   - `PilotEvaluationService` class does not exist

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testScaleUpDecision`
   Expected failure: Class `App\Services\PilotEvaluationService` not found

3. Implement PilotEvaluationService with decision matrix:
   File: `apps/api/app/Services/PilotEvaluationService.php`
   Implement: `evaluate($pilotResults)` — evaluates KPIs against thresholds, returns decision + evidence

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testScaleUpDecision`
   Expected: PASS

5. Write failing test for: Iterate decision when some KPIs not met
   Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
   Level: unit

   Test intent:
   Given pilot results: 20 valid orders, speed delta -25% (target not met), error rate 3%, delivery success 97%, payment completion 92%
   When `PilotEvaluationService::evaluate($pilotResults)` is called
   Then:
   - Result contains `decision = "iterate"`
   - Result contains `kpiSummary.speedDelta.targetMet = false`
   - Result contains `lessonsLearned` with improvement recommendations

   Exercise through:
   - Public method `evaluate()` on `PilotEvaluationService`

   Test doubles:
   - do NOT mock: `PilotEvaluationService`
   - mock: None

   Expected RED:
   - Iterate decision logic not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testIterateDecision`
   Expected failure: Assertion failure on decision or missing lessonsLearned

7. Implement iterate decision logic:
   File: `apps/api/app/Services/PilotEvaluationService.php`
   Implement: Iterate decision when KPIs partially met, with improvement recommendations

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testIterateDecision`
   Expected: PASS

9. Write failing test for: Stop decision when guardrails violated
   Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
   Level: unit

   Test intent:
   Given pilot results: 18 valid orders, speed delta -20%, error rate 8% (>5%), delivery success 89% (<95%)
   When `PilotEvaluationService::evaluate($pilotResults)` is called
   Then:
   - Result contains `decision = "stop"`
   - Result contains `rootCauseAnalysis` with identified issues
   - Result contains `recommendations` for significant iteration

   Exercise through:
   - Public method `evaluate()` on `PilotEvaluationService`

   Test doubles:
   - do NOT mock: `PilotEvaluationService`
   - mock: None

   Expected RED:
   - Stop decision logic not implemented

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testStopDecision`
    Expected failure: Assertion failure on decision or missing rootCauseAnalysis

11. Implement stop decision logic:
    File: `apps/api/app/Services/PilotEvaluationService.php`
    Implement: Stop decision when guardrails violated, with root cause analysis

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testStopDecision`
    Expected: PASS

13. Write failing test for: Phase 7 evidence documentation generation
    Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
    Level: unit

    Test intent:
    Given evaluated pilot results with decision "scale-up"
    When `PilotEvaluationService::generateEvidence($evaluation)` is called
    Then:
    - Evidence contains `executiveSummary` with key metrics
    - Evidence contains `kpiDetail` with per-metric breakdown
    - Evidence contains `recommendations` with next steps
    - Evidence contains `dataCollection` noting pilot duration and order count

    Exercise through:
    - Public method `generateEvidence()` on `PilotEvaluationService`

    Test doubles:
    - do NOT mock: `PilotEvaluationService`
    - mock: None

    Expected RED:
    - Evidence generation not implemented

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testEvidenceGeneration`
    Expected failure: Undefined method or assertion failure

15. Implement evidence generation:
    File: `apps/api/app/Services/PilotEvaluationService.php`
    Implement: `generateEvidence($evaluation)` — produces structured evidence document for Phase 7

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testEvidenceGeneration`
    Expected: PASS

17. Refactor while green (bounded):
    - Extract decision threshold constants into class constants
    - Ensure `evaluate()` handles edge cases (missing metrics, zero orders)
    - Re-run full test suite: `cd apps/api && php artisan test --filter=PilotEvaluationTest` — must stay PASS

18. Commit:
    `git add apps/api/app/Services/PilotEvaluationService.php apps/api/tests/Feature/PilotEvaluationTest.php`
    `git commit -m "feat(pilot): add evaluation service with decision matrix and Phase 7 evidence"`

## REFERENCES LOADED
- docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md — Rules 1-3: Evaluation & Decision, GWT scenarios for scale-up/iterate/stop decisions
- apps/api/app/Services/PilotMetricsService.php (from T2) — metrics output consumed by evaluation

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single service with 3 decision paths and 1 evidence generator. Pure business logic with no external dependencies. Straightforward TDD with clear GWT scenarios.

## SANDWICH CONTEXT
[CRITICAL: Evaluation logic must not access or modify pilot order data directly — it receives pre-computed metrics as input.]
You are implementing pilot evaluation and decision matrix for Concierge Production Pilot.
Spec: docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
Design decision: Option A — Pilot Minimum Viable
Files in scope: `apps/api/app/Services/PilotEvaluationService.php`, `apps/api/tests/Feature/PilotEvaluationTest.php`
Available after: T2 (PilotMetricsService must exist for metrics input)
Architecture rule: Evaluation service receives metrics as plain array/DTO — no direct DB access
[RESTATE: Evaluation logic must not access pilot order data directly — receive pre-computed metrics only]

## DELIVERABLE
Given all KPIs met (25 orders, -91.7% speed, 4% error, 96% delivery, 95.8% payment), When evaluate, Then decision = "scale-up" with case study evidence
Given KPIs partially met (20 orders, -25% speed, 3% error, 97% delivery, 92% payment), When evaluate, Then decision = "iterate" with lessons learned
Given guardrails violated (18 orders, 8% error, 89% delivery), When evaluate, Then decision = "stop" with root cause analysis
Given evaluated results, When generateEvidence, Then evidence document contains executiveSummary, kpiDetail, recommendations, dataCollection
All tests PASS. Commit exists with message matching `feat(pilot): add evaluation service with decision matrix and Phase 7 evidence`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - All 3 decision paths implemented (scale-up, iterate, stop)
  - Evidence document generation with structured output
  - Tests written BEFORE implementation (TDD — not after)
  - Decision thresholds clearly defined as class constants
  - Commit message follows conventional commits format

Must-not-have:
  - Direct DB access in evaluation logic
  - Modifying files outside listed scope
  - Skipping the failing test step

Open question risks:
  - Decision thresholds may need adjustment after real pilot data → if thresholds prove wrong, report NEEDS_CONTEXT
  - Evidence format may need alignment with Phase 7 requirements → assume current structure is sufficient

Rollback note:
  - New files only — delete to rollback. No existing code modified.

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: decision thresholds need recalibration after real pilot data
Escalate when: evaluation logic accesses pilot order data directly

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Pilot Infrastructure — Partner Qualification & KPI Helpers | prereq | lightweight | Partner qualifies/fails based on outlet count, documentation, PIC, internet |
| T2 | Pilot Workflow Execution — Order-to-Payment Lifecycle with KPI Collection | T1 | standard | Full order lifecycle tested with timing measurement and reliability guardrails |
| T3 | Pilot Evaluation — Decision Matrix & Phase 7 Evidence Documentation | T2 | lightweight | Scale-up/iterate/stop decisions based on KPI thresholds, evidence generated |
