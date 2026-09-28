# Task T2 — Backend — GeographicAnalyticsService v2 payload + tightened coordinate rule

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Backend — GeographicAnalyticsService v2 payload + tightened coordinate rule [prereq]

## OBJECTIVE
Extend `GeographicAnalyticsService::produce()` to emit v2 outlet rows (`plottable`, `orders_by_status`, `sales_by_status`, `daily_by_status`, `product_summary`, `product_summary_truncated`, `latest_request`) while keeping territory aggregates unchanged; tighten `isValidCoordinate()` to the canonical rule (reject `null`, non-`int`/`float` incl. numeric strings, non-finite, out-of-range, and `(0,0)`).

Steps:
1. Write failing test for: v2 outlet row composition
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: integration (service + DB)
   Test intent: Given seeded orders across the four statuses/dates/products in the window / When `GeographicAnalyticsService::produce($window)` / Then each outlet row has `orders_by_status` with four zero-filled keys, `sales_by_status` as 2-dp decimal strings, `daily_by_status` ascending `YYYY-MM-DD`, `product_summary` top-5 by qty desc then product_id asc with `product_summary_truncated`, `latest_request` (latest eligible order, `null` when none), and `plottable`.
   Exercise through: `GeographicAnalyticsService::produce()`
   Test doubles: none (real DB, `RefreshDatabase`)
   Expected RED: v2 fields absent from payload
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicAnalyticsTest`
3. Implement v2 aggregation (zero-fill statuses, ascending daily, bounded product summary, latest request) → verify PASS → refactor → commit `feat(service): geographic v2 payload with status/daily/product detail`
4. Write failing test for: canonical coordinate rule (shared case table with T7)
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: unit (pure helper, exercised via feature file for locality)
   Test intent: Given the case table — `(null,106.8)`, `("-6.2",106.8)`, `(NAN,106.8)`, `(INF,106.8)`, `(91,106.8)`, `(-6.2,181)`, `(0,0)` / When `isValidCoordinate` / Then `false`; Given `(-6.2,106.8)` as `float`/`int` / Then `true`.
   Exercise through: `GeographicAnalyticsService::isValidCoordinate()`
   Test doubles: none
   Expected RED: current helper casts via `(float)` and accepts `"-6.2"` and `(0,0)`
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicAnalyticsTest`
6. Implement tightened `isValidCoordinate` (no cast; `is_int`/`is_float`, `is_finite`, range, `(0,0)` rejection) and have the stage cast DB decimals to `float` before calling it → verify PASS → refactor → commit `feat(service): canonical coordinate validation rule`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Snapshot contract, Coordinate rule (`plottable`), Money and date rules, `daily_by_status` ordering, `product_summary` bound, `latest_request`, Deterministic dummy fixture table
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: multi-table aggregation with status/day/product composition plus a rule that must match a second implementation (frontend) exactly.

## SANDWICH CONTEXT
[CRITICAL: `isValidCoordinate()` is the single coordinate authority; the frontend `isValidPoint()` must mirror it with the identical case table]
You are implementing the v2 geographic stage payload for the Outlet Request Map Dashboard.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/app/Services/GeographicAnalyticsService.php`, `apps/api/tests/Feature/GeographicAnalyticsTest.php`
Available after: none (prereq)
Architecture rule: no float for money aggregates (use DB decimal/integer cents); `Asia/Jakarta` boundaries; deterministic output; explicit fallback for old rows
[RESTATE: `isValidCoordinate()` is the single coordinate authority]

## DELIVERABLE
Given orders with New/Confirmed/Delivered/Partially Paid across dates in the window, When `produce($window)`, Then each outlet row carries `orders_by_status` (four zero-filled keys), `sales_by_status` (2-dp strings), `daily_by_status` (ascending, sparse), `product_summary` (≤5, deterministic ties), `product_summary_truncated`, `latest_request`, `plottable`
Given `(null|"-6.2"|NAN|INF|91|181|(0,0))` as coordinates, When `isValidCoordinate`, Then `false`
Given `(-6.2, 106.8)` as finite numbers, When `isValidCoordinate`, Then `true`
[must-not] Given a v1 snapshot row, When the stage runs, Then no fabricated status/daily values are produced
[derived] Given no eligible orders for an outlet, When `produce`, Then `latest_request` is `null` and status maps are zero-filled

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Backend never casts money to float before summing
  - `daily_by_status` sorted strictly ascending by `YYYY-MM-DD`
  - `product_summary` bounded to 5, tie-break by `product_id` asc
  - `latest_request` `null` when absent — never fabricated
  - Territory aggregates unchanged
Must-not-have:
  - No new dependencies, no schema changes
  - No modification of `OrderCreationService`, `PromotionService`, `StockPlanningService`, `ForecastService`
Open question risks:
  - Order status vocabulary differs from the four canonical statuses → report NEEDS_CONTEXT
Rollback note:
  - Revert `produce()` to v1 and re-run the pipeline to publish the v1 payload
Red flags:
  - Protected service touched → STOP
  - Float money aggregation → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, coordinate case table green, no float money in aggregation
Uncertain when: canonical statuses differ from the service's eligible set
Escalate when: pipeline publication fails or memory exceeds budget on the scale fixture
