# Task T9 — Verify discovery order flow end-to-end (Playwright E2E)

**Phase:** 2
**Depends:** T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Verify discovery order flow end-to-end (Playwright E2E) [depends: T8] [test-risk]

## OBJECTIVE
Use the existing Playwright setup to verify discovery API → OrderForm → add-to-cart → order submit and event recording, plus offline behavior, tracking persistence, and 422 stock validation.

Steps:
1. Write failing test for: e2e_outlet_sees_discovery_adds_to_cart_submits_order
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given authenticated seeded outlet, eligible purchase history and available discovery product, When outlet opens /orders, adds the discovery item and submits, Then order succeeds and next discovery fetch excludes the purchased item
   Exercise through: Playwright browser against local web + API stack, using existing E2E seed/auth fixtures
   Test doubles: intercept only non-deterministic analytics if needed; use real discovery/order API and test database
   Expected RED: discovery UI/API flow is absent
2. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
3. Write failing test for: e2e_offline_discovery_hidden_reorder_works
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet with cached reorder data, When browser goes offline and opens /orders, Then reorder remains visible and discovery is hidden with 'tersedia saat online'
   Exercise through: Playwright browser context offline mode on /orders
   Test doubles: Playwright network offline mode only
   Expected RED: offline discovery state is absent
4. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
5. Write failing test for: e2e_tracking_events_recorded_displayed_clicked_cart
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet views 3 discovery items and adds 1, When interactions complete, Then persisted RecommendationEvent rows include displayed for the visible products and clicked + cart for the added product
   Exercise through: Playwright UI + authenticated API/database assertion using existing E2E harness
   Test doubles: none for API/database; real E2E stack
   Expected RED: event tracking not wired through the UI
6. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
7. Write failing test for: e2e_stock_exhausted_between_add_and_submit_returns_422
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet adds discovery item P3 to cart, then P3 stock becomes 0 before submit, When outlet submits order, Then response 422 with item error for P3 and order not created
   Exercise through: Playwright UI + direct DB manipulation to exhaust stock, then submit
   Test doubles: none; real DB stock update
   Expected RED: discovery flow not verified against existing stock validation
8. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
9. Write failing test for: e2e_order_succeeds_when_tracking_fails
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet adds discovery item and submits order while tracking endpoint returns 500, Then order succeeds and no error surfaced to outlet
   Exercise through: Playwright UI with route interception to fail /api/ai/discovery/events, then submit
   Test doubles: Playwright route interception for tracking endpoint
   Expected RED: tracking failure blocks order
10. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
11. Implement any integration fixes exposed by these tests; do not change OrderCreationService contract.
12. Run all 5 tests — verify PASS: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
13. Refactor while green: none expected
14. Commit: `git commit -m "test(e2e): verify outlet discovery flow, tracking, offline, stock 422, and tracking failure resilience"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — cross-unit scenarios

## WHY THIS APPROACH
Complexity: standard
Justification: Cross-unit verification (Rule 6) for scenarios spanning API + web; [test-risk] because E2E test level spans units. Uses existing Playwright setup.

## SANDWICH CONTEXT
[CRITICAL: Must verify cross-unit GWT: discovery fetch → web add → order submit → tracking recorded; offline; stock 422; tracking failure non-blocking; no new deps]
You are implementing Task 9 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/e2e/outlet-discovery.spec.ts
Available after: T8 (full integration in OrderForm)
Architecture rule: OrderCreationService contract unchanged; tracking fire-and-forget
[RESTATE: Must NOT touch auth/RBAC, DB schema, OrderCreationService]

## DELIVERABLE
Given outlet with history + catalog, When load /orders → see discovery → add item → submit, Then order created with item, next discovery excludes purchased
Given offline, When load /orders, Then reorder from cache, discovery hidden 'tersedia saat online'
Given view 3 items + add 1, When interactions, Then 3 displayed + 1 clicked + 1 cart in RecommendationEvent
Given stock exhausted between add and submit, When submit, Then 422 atomik + item error, order not created
Given tracking endpoint 500, When add + submit, Then order succeeds without outlet-facing error

## QUALITY BAR
Must-have:
  - Cross-unit verification of discovery → add → order → tracking
  - Offline behavior verified
  - Stock 422 atomik behavior verified (no regression)
  - Tracking failure non-blocking verified

Must-not-have:
  - New test framework setup (use existing Playwright)
  - Changes to OrderCreationService contract

Rollback note:
  - N/A (test only)

## STOP CONDITIONS
Done when: all 5 E2E tests pass
Escalate when: flaky tests or missing E2E infrastructure
