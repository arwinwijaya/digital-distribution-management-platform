# Task T5 — API negative checks — extend order-flow.test.js

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: API negative checks — extend order-flow.test.js [prereq]

## OBJECTIVE
Extend `apps/web/order-flow.test.js` to cover §8 guards + idempotency deterministically, reusing existing real HTTP+sqlite harness.

Steps:
1. Write failing test for: Assign rejected before Confirmed
   Test file: `apps/web/order-flow.test.js`
   Level: integration (real HTTP against php -S + sqlite, via existing harness)
   Test intent: Given outlet order newly created with status New (no admin approve) / When API POST /deliveries for that order (admin token, driver Joko) / Then HTTP 422 and body contains "Only confirmed orders can be assigned" and no delivery created (GET /deliveries still empty for that order)
   Exercise through: request('/api/deliveries', {method:'POST', headers: authHeaders(adminToken), body: {order_id}})
   Test doubles: None — use real server/DB from harness; do NOT mock Order/Delivery models
   Expected RED: Test not present or assertion fails (today only happy path is covered)
2. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Assign rejected before Confirmed" --runInBand`
3. Write failing test for: Payment rejected before Delivered
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given order Confirmed but delivery not delivered (assigned/in_progress) / When POST /payments amount=TOTAL / Then 422 "Payments can only be recorded for delivered orders" and order remains not Paid, invoice still unpaid
   Exercise through: request('/api/payments', {method:'POST', headers: authHeaders(financeToken)})
   Test doubles: Real DB
   Expected RED: Missing test
4. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Payment rejected before Delivered" --runInBand`
5. Write failing test for: Overpayment rejected
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given order Delivered with outstanding O / When POST /payments amount = O + 1 / Then 422 "Payment cannot exceed the outstanding balance" and no payment created, outstanding still O
   Exercise through: POST /payments over outstanding
   Test doubles: Real DB
   Expected RED: Missing test
6. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Overpayment rejected" --runInBand`
7. Write failing test for: Idempotency replay identical returns 200 created:false
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given first POST /orders or /payments with Idempotency-Key K succeeded (201 created:true) / When identical payload re-sent with same K / Then HTTP 200, body created=false, same id/reference, no duplicate row (count unchanged)
   Exercise through: Two POSTs with same header + body
   Test doubles: Real DB
   Expected RED: Currently harness asserts 200 but spec expects created:false distinction — test missing
8. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Idempotency replay identical" --runInBand`
9. Write failing test for: Idempotency same key different payload → 422
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given first request with K succeeded / When different payload (e.g., different quantity or amount) is sent with the same K / Then HTTP 422, and a subsequent GET or list query proves the first record has the exact original id/reference, payload amount/quantity, status, and count unchanged
   Exercise through: POST with same K different body, then GET the original order/payment and compare a snapshot captured before the second request
   Test doubles: Real DB
   Expected RED: Missing test
10. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Idempotency different payload" --runInBand`
11. Implement: Extend order-flow.test.js with 5 new `it(...)` blocks using existing `request()`/`authHeaders()` helpers, reusing `makeRuntime`/`migrate:fresh --seed` harness but asserting status codes/messages above. For the different-payload case, capture the complete first record snapshot and row count before replay, assert HTTP 422, then fetch again and assert id/reference, payload amount/quantity, status, and count equal the snapshot. Import PaymentService replay logic expectation: `expect(body.created).toBe(false)` and `expect(response.status).toBe(200)` for identical replay; `expect(response.status).toBe(422)` for different payload. Verify all PASS: `npx --prefix apps/web jest order-flow.test.js --runInBand` → refactor while green (extract helper `expectNoDelivery(orderId)` if duplicated, commit separately as refactor if over 3 duplications) → Commit: `git commit -m "test(api): cover order negative guards and idempotency (§8)"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 2 Rules 1-4, Acceptance Criteria Negative checks, Spec Context (routes, PaymentService::replayExistingPayment)

## WHY THIS APPROACH
Complexity: standard
Justification: Reuses proven harness (php -S + sqlite, 120s timeout); 5 independent GWT scenarios each need own RED cycle; must verify 200 vs 201 distinction without mocking domain.

## SANDWICH CONTEXT
[CRITICAL: Must NOT change backend business rules, routes, or DB schema]
You are implementing API negative checks for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Hybrid — guards stay in API test (fast, deterministic)
Files in scope: apps/web/order-flow.test.js
Available after: T2 (seed script); logger is intentionally not imported because this task verifies API business rules only
Architecture rule: Real HTTP server only; no mocks of Order/Payment models; assertions on HTTP status + DB state
[RESTATE: Must NOT change backend business rules or schema]

## DELIVERABLE
Given order New, When POST /deliveries, Then 422 + no delivery
Given order not Delivered, When POST /payments, Then 422 + no mutation
Given outstanding O, When pay > O, Then 422 + no payment + outstanding remains O
Given key K succeeded, When replay identical K, Then 200 created:false + no duplicate
Given key K succeeded, When payload differ + same K, Then 422 + first record intact

## QUALITY BAR
Must-have:
  - All 5 negative scenarios with own test + own command
  - Assertions on both HTTP status and persistence (no duplicate / no mutation)
  - Reuse existing harness helpers (findFreePort, waitForServer, authHeaders)

Must-not-have:
  - Mocking Order/Payment/Delivery domain
  - Changing apps/api/**
  - Adding new test framework

Open question risks:
  - Replay identical assumed 200 created:false → if actual is 201, report NEEDS_CONTEXT and adjust assertion

Rollback note:
  - Remove the 5 new `it` blocks (restore file to prior)

Red flags:
  - Work outside listed file → DONE_WITH_CONCERNS
  - Backend contract changed → STOP

## STOP CONDITIONS
Done when: All 5 new tests plus existing happy-path test PASS via jest order-flow.test.js --runInBand
Uncertain when: 422 message wording differs → NEEDS_CONTEXT (assert status only, message contains)
Escalate when: Harness cannot reproduce PaymentService replay → STOP
