# Task T2 — Pilot Workflow Execution — Order-to-Payment Lifecycle with KPI Collection

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
