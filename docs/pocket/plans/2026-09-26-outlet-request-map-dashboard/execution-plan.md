# EXECUTION PLAN — Outlet Request Map Dashboard

**Date:** 2026-09-26
**Spec:** docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
**Status:** draft
**Total tasks:** 11

---

## Execution Overview

### Recommended Order
```
T1, T2, T4, T5 (parallel, prereq) → T3 (after T2), T6 (after T1), T7 (after T1,T5)
→ T8 (after T7) → T9 (after T8) → T10 (after T9)
→ T11 (after T3 and T9, runs in parallel with T10)
```

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T2, T4, T5 | immediately (all prereq) |
| Group B | T3, T6, T7 | T2; T1; T1+T5 |
| Group C | T8 | T7 |
| Group D | T11 | T3, T9 |
| Group E | T10 | T9 |

### Constraints Reminder
**Architecture:** Snapshot-first enriched geographic projection (Option A). No new map library, no new RBAC/menu key, no new entity, no mutations. Core protected services (`OrderCreationService`, `PromotionService`, `StockPlanningService`, `ForecastService`) must NOT be touched. Money aggregated in integer cents (backend never casts money to float); dates normalized to `Asia/Jakarta`; deterministic logic (no `rand`/`mt_rand`); explicit insufficient-data fallbacks; no stale snapshot rendered after a fetch error.
**Out-of-scope:** new request entity, order mutations/approval workflow, WebSocket/live query, clustering library, fixing the `Cancelled`/`Canceled` vocabulary, schema changes, rebuilding historical snapshots.
**Assumptions at risk:** four canonical eligible statuses (`New`, `Confirmed`, `Delivered`, `Partially Paid`) — a rename would force a contract version bump; 100ms recompute baseline measured on CI with `ScaleFixtureSeeder` (500 total / 100 active) — a low-powered production device may need a follow-up.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules.

### File Structure Map

```
Rule: Snapshot contract & backward compatibility (v2 payload, plottable, status maps, daily, product, latest, meta)
  Modify: apps/api/app/Services/GeographicAnalyticsService.php      (T2)
  Modify: apps/api/app/Http/Controllers/GeographicAnalyticsController.php (T3)
  Test:   apps/api/tests/Feature/GeographicAnalyticsTest.php        (T2, T3)

Rule: Coordinate validation (tightened isValidCoordinate + isValidPoint parity)
  Modify: apps/api/app/Services/GeographicAnalyticsService.php      (T2)
  Modify: apps/web/src/components/data-intelligence/GeoMap.tsx      (T7)
  Test:   apps/api/tests/Feature/GeographicAnalyticsTest.php        (T2)
  Test:   apps/web/src/components/data-intelligence/GeoMap.test.tsx (T7)

Rule: Truncation metadata (meta envelope, 500KB budget, frontend warning)
  Modify: apps/api/app/Http/Controllers/GeographicAnalyticsController.php (T3, T11)
  Test:   apps/api/tests/Performance/GeographicScaleTest.php        (T11, created by T11)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T11, warning branch)
  Test:   apps/web/src/app/data-intelligence/page.test.tsx          (T11, truncation-warning contract)

Rule: outlets_without_daily_detail disclosure (production, narrow periods)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)
  Test:   apps/web/src/app/data-intelligence/page.test.tsx          (T9)

Rule: Default map (default {New,Confirmed} 30d, one marker/outlet, fitBounds initial/reset only)
  Modify: apps/web/src/components/data-intelligence/GeoMap.tsx      (T7)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)
  Test:   apps/web/src/components/data-intelligence/GeoMap.test.tsx (T7)

Rule: Status & period filtering (multi-toggle, Semua, Hari ini/7d/30d, fallback)
  Create: apps/web/src/lib/geographic-filters.ts                    (T1)
  Test:   apps/web/src/lib/geographic-filters.test.ts               (T1, created by T1)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)

Rule: Drawer & navigation (snapshot-only, freeze banner, detail fallback, order link)
  Create: apps/web/src/components/data-intelligence/OutletDrawer.tsx (T8)
  Test:   apps/web/src/components/data-intelligence/OutletDrawer.test.tsx (T8, created by T8)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)

Rule: Empty states (4 precedence states)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)
  Test:   apps/web/src/app/data-intelligence/page.test.tsx          (T9)

Rule: Error classification & retry (typed ApiError, 401/403/5xx/network)
  Create: apps/web/src/lib/api-error.ts                             (T5)
  Test:   apps/web/src/lib/api-error.test.ts                        (T5, created by T5)
  Modify: apps/web/src/lib/api.ts                                   (T5)
  Modify: apps/web/src/lib/data-intelligence-api.ts                 (T5)
  Test:   apps/web/src/lib/data-intelligence-api.test.ts            (T5, created by T5)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)

Rule: Admin-only & dummy parity (role before dummy, deterministic fixture 101–105)
  Modify: apps/web/src/app/data-intelligence/page.tsx               (T9)
  Modify: apps/web/src/dummy/aggregates.ts                          (T6)
  Test:   apps/web/src/dummy/aggregates.test.ts                     (T6)

Rule: Accessibility (aria-pressed, keyboard list, role=dialog, focus, tile fallback)
  Modify: apps/web/src/components/data-intelligence/GeoMap.tsx      (T7)
  Modify: apps/web/src/components/data-intelligence/OutletDrawer.tsx (T8)

Rule: OrderController additive filters (outlet_id/status/start/end, 422)
  Modify: apps/api/app/Http/Controllers/OrderController.php         (T4)
  Test:   apps/api/tests/Feature/OrderQueryTest.php                 (T4)

Rule: Performance baseline (ScaleFixtureSeeder, <100ms, <500KB)
  Create: apps/api/tests/Performance/GeographicScaleTest.php        (T11)
  Create: apps/web/src/lib/geographic-filters.perf.test.ts          (T11)
```

---

## Pocket Packets

---

### Task 1: Shared geographic filter helper [prereq]

## OBJECTIVE
Create `apps/web/src/lib/geographic-filters.ts` — pure, dependency-free functions used by the page, GeoMap, drawer, and tests so filter math has one source of truth: canonical status expansion, period→date-range, per-outlet filtered counts, daily bucket filtering, and integer-cents money summation.

Steps:
1. Write failing test for: period→range mapping
   Test file: `apps/web/src/lib/geographic-filters.test.ts`
   Level: unit
   Test intent: Given window `{start:'2026-08-27', end:'2026-09-25', timezone:'Asia/Jakarta'}` / When `periodToRange(window, '7d')` / Then `{start:'2026-09-19', end:'2026-09-25'}`; and `'30d'` → `{start:'2026-08-27', end:'2026-09-25'}`; and `'today'` → `{start:'2026-09-25', end:'2026-09-25'}`.
   Exercise through: exported `periodToRange`
   Test doubles: none (pure)
   Expected RED: module does not exist
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.test.ts`
3. Implement `periodToRange` + status canonicalization (`ELIGIBLE_STATUSES`, `expandSemua`, `normalizeStatuses`) → verify PASS → refactor → commit `feat(lib): geographic filter helpers`
4. Write failing test for: per-outlet filtered counts compose status × period
   Test file: `apps/web/src/lib/geographic-filters.test.ts`
   Level: unit
   Test intent: Given a point with `orders_by_status {New:5, Confirmed:2, Delivered:1, 'Partially Paid':1}` and `daily_by_status` where New is 3 on 2026-09-20 and 2 on 2026-09-25 / When `computeFilteredCounts(point, ['New'], '7d', window)` / Then `filteredOrders===2` (only in-range New), `statusCounts.New===2`; and with `['New','Confirmed']` + `'30d'` → `7`.
   Exercise through: exported `computeFilteredCounts`
   Test doubles: none (pure)
   Expected RED: function absent
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.test.ts`
6. Implement `computeFilteredCounts` (status-map totals for 30d, `daily_by_status` slice for 7d/today, integer-cents `salesCents`) → verify PASS → refactor → commit `feat(lib): geographic filtered counts`
7. Write failing test for: safe fallback on unknown status/period + empty selection
   Test file: `apps/web/src/lib/geographic-filters.test.ts`
   Level: unit
   Test intent: Given `normalizeStatuses(['Bogus'])` / Then returns `['New','Confirmed']`; Given `periodToRange(window, 'banana' as Period)` / Then returns the `30d` range; Given a point missing `orders_by_status` / Then `computeFilteredCounts` returns `filteredOrders:0` and `legacyOnly:true` without throwing; Given `normalizeStatuses([])` (empty selection) / Then returns an EMPTY set — producing the no-request state, NOT reverting to `{New,Confirmed}`.
   Exercise through: exported helpers
   Test doubles: none (pure)
   Expected RED: no fallback branch; empty selection incorrectly reverts to default
8. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.test.ts`
9. Implement fallback branches → verify PASS → refactor → commit `feat(lib): geographic filter safe fallbacks`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Status & period filtering, Period semantics, Invalid filter state, Money and date rules, Deterministic dummy fixture table, Rule 1: Multi-toggle status selection (empty selection = no-request state, never silently reverts)
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: lightweight
Justification: pure functions, no I/O, spec fully specifies formulas.

## SANDWICH CONTEXT
[CRITICAL: Single source of truth for status/period filter math — page, GeoMap, drawer, and tests must import it, never re-derive it]
You are implementing the shared geographic filter helper for the Outlet Request Map Dashboard.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A — Snapshot-first enriched geographic projection
Files in scope: `apps/web/src/lib/geographic-filters.ts`, `apps/web/src/lib/geographic-filters.test.ts`
Available after: none (prereq)
Architecture rule: deterministic logic (no `Math.random`), integer-cents money (reuse the `toCents`/`fromCents` convention from `apps/web/src/dummy/aggregates.ts`), explicit insufficient-data fallback
[RESTATE: Single source of truth for status/period filter math]

## DELIVERABLE
Given window `{start,end,timezone}`, When `periodToRange(window,'7d')`, Then `[end-6d, end]` inclusive as `YYYY-MM-DD`
Given selected statuses `{New,Confirmed}` and a v2 point, When `computeFilteredCounts`, Then the sum covers only those statuses
Given `daily_by_status` entries outside the period, When filtering 7d, Then only in-range dates contribute
Given `Semua`, When expanded, Then exactly the four canonical eligible statuses
Given an unknown status or period, When normalized, Then it falls back to `{New,Confirmed}` + `30d` without throwing
Given an empty status selection, When normalized, Then it yields an EMPTY set (no-request state) and does NOT revert to `{New,Confirmed}`
[derived] Given a point without `orders_by_status`/`daily_by_status`, When counted, Then `legacyOnly:true` and no exception

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Pure functions, no side effects, no I/O
  - Integer-cents aggregation — never `Number(decimalA) + Number(decimalB)`
  - `Hari ini` uses snapshot `window.end`, never `new Date()`
  - `Semua` expands to exactly `New, Confirmed, Delivered, Partially Paid`
  - Unknown status/period → safe fallback, never throw to the page
  - Empty status selection → empty result (no-request state), must NOT revert to default `{New,Confirmed}`
Must-not-have:
  - No `Date`/`new Date()` for period boundaries (timezone-unsafe)
  - No floating-point money arithmetic
  - No import of GeoMap/page/drawer (keep the helper leaf-level)
Open question risks:
  - `daily_by_status` date format differs from `YYYY-MM-DD` → report NEEDS_CONTEXT
Rollback note:
  - Revert the helper file only; nothing else imports it yet
Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Float money math → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: `toCents`/`fromCents` convention not reusable as-is
Escalate when: an import cycle would be required to share the helper

---

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

---

### Task 3: Backend — GeographicAnalyticsController passthrough + meta [depends: T2]

## OBJECTIVE
Widen the controller projection to pass through every v2 field, keep invalid-coordinate rows in `map_points` with `plottable:false` (never drop them), and emit the envelope `meta` (`truncated`, `omitted_zero_days`, `product_summary_capped`) plus `snapshot_available` / `geographic_section_available` flags.

Steps:
1. Write failing test for: v2 passthrough + meta
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: integration (controller + snapshot reader)
   Test intent: Given an active v2 snapshot with valid and invalid-coordinate outlet rows / When `GET /admin/analytics/geographic` / Then every `map_points[i]` contains `plottable`, `orders_by_status`, `sales_by_status`, `daily_by_status`, `product_summary`, `product_summary_truncated`, `latest_request`, and the envelope has `meta.{truncated,omitted_zero_days,product_summary_capped}`.
   Exercise through: HTTP endpoint
   Test doubles: none (publish a real snapshot)
   Expected RED: v2 fields dropped by fixed-field projection; no `meta`
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicAnalyticsTest`
3. Implement explicit v2 field projection + `meta` computation → verify PASS → refactor → commit `feat(controller): geographic v2 passthrough with meta`
4. Write failing test for: invalid-coordinate retention + empty flags
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: integration
   Test intent: Given an outlet with orders but `null` coordinates / When the endpoint is called / Then the row is present in `map_points` with `plottable:false`; Given no active snapshot / Then `snapshot_available:false`, `geographic_section_available:false`, empty arrays; Given an active snapshot without geographic rows / Then `snapshot_available:true`, `geographic_section_available:false`.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: invalid rows removed; flags absent
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicAnalyticsTest`
6. Implement invalid-row retention + flags → verify PASS → refactor → commit `feat(controller): retain invalid-coordinate rows with plottable flag`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: payload envelope, `map_points` v2 row, Truncation metadata, invalid-coordinate handling, empty-state precedence
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: projection widening plus envelope metadata aggregation; backward-compatible passthrough.

## SANDWICH CONTEXT
[CRITICAL: No v2 field may be silently dropped — the projection must explicitly list every v2 field so a future addition fails loudly]
You are implementing the geographic v2 controller passthrough.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/app/Http/Controllers/GeographicAnalyticsController.php`, `apps/api/tests/Feature/GeographicAnalyticsTest.php`
Available after: T2 (service emits v2)
Architecture rule: envelope `{status,data}`; existing RBAC `data_intelligence:read`; invalid rows kept, not dropped; explicit `meta` distinguishes zero-omission from truncation
[RESTATE: No v2 field may be silently dropped]

## DELIVERABLE
Given a v2 snapshot row, When the endpoint responds, Then `map_points[i]` contains every v2 field and the envelope carries `meta`
Given invalid coordinates with orders, When the endpoint responds, Then the row is present with `plottable:false`
Given a v1 row, When the endpoint responds, Then legacy fields are returned and the frontend applies per-row fallback
Given no active snapshot, When the endpoint responds, Then `snapshot_available:false` and empty arrays
[derived] Given an active snapshot with zero geographic rows, When the endpoint responds, Then `snapshot_available:true`, `geographic_section_available:false`

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Explicit v2 field projection (no silent drop)
  - `omitted_zero_days` = Σ over rows with orders of `windowDays − len(daily_by_status)`
  - `product_summary_capped` = OR of per-row `product_summary_truncated`
  - Invalid-coordinate rows retained and countable
Must-not-have:
  - Removing invalid-coordinate rows from `map_points`
  - New RBAC/menu keys
Open question risks:
  - Snapshot reader does not expose the section verbatim → report NEEDS_CONTEXT
Rollback note:
  - Revert the controller to the v1 projection and re-publish the v1 snapshot
Red flags:
  - Silent field dropping → STOP
  - New route or middleware → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all v2 fields present, `meta` correct, invalid rows retained, tests green
Uncertain when: snapshot reader shape differs from assumption
Escalate when: response size exceeds 500KB without `truncated=true` (defer cap to T11, but flag)

---

### Task 4: Backend — OrderController additive filters [prereq]

## OBJECTIVE
Add additive query parameters to `OrderController::index()`: `outlet_id` (positive int), `status` (csv of the four canonical statuses), `start`/`end` (`YYYY-MM-DD`, `start<=end`, range ≤90 days). Validate before query construction; return a 422 envelope on failure; never broaden the query when validation fails; keep existing behavior when no new params are passed.

Steps:
1. Write failing test for: additive filtering
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given orders across outlets/statuses/dates / When `GET /api/admin/orders?outlet_id=12&status=New,Confirmed&start=2026-09-19&end=2026-09-25` / Then only matching orders are returned and pagination/sort/`meta.total` reflect the filtered set.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: new params ignored
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderQueryTest`
3. Implement additive scoping (reuse `App\Support\ListQuery` for scalar parsing) → verify PASS → refactor → commit `feat(controller): order additive filters`
4. Write failing test for: 422 validation envelope + unknown query key rejection
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given `outlet_id=abc` / When the endpoint is called / Then 422 with `errors.outlet_id`; Given `status=Unknown` / Then 422 with `errors.status`; Given `start>end` or a range >90 days or a malformed date / Then 422 with a field error; Given ANY unknown additional query key (e.g. `?foo=1` or `?page_size=999` not in the allowlist) / Then 422 identifying the offending key and NO order rows are returned; and in every failure no broadened query executes.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: invalid values and unknown keys silently ignored
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderQueryTest`
6. Implement validation-before-query (including rejection of every query key outside the `outlet_id`/`status`/`start`/`end` allowlist) → verify PASS → refactor → commit `feat(controller): order filter validation with 422 envelope`
7. Write failing test for: unchanged default behavior
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given no new parameters / When the endpoint is called / Then results, pagination, and sort are byte-identical to the pre-change behavior.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: n/a — guards regression, expect PASS after step 3; keep as regression lock
8. Run test — verify PASS: `cd apps/api && php artisan test --filter=OrderQueryTest`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rule: Additive order-list filter contract (allowlisted keys; unknown keys → 422; validation before query construction); Story: Order navigation preserves context
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: validation-first with backward-compatible pagination and an explicit error contract.

## SANDWICH CONTEXT
[CRITICAL: Validation runs BEFORE query construction — on failure no broadened/unfiltered query executes]
You are implementing additive filters for the admin order list.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/tests/Feature/OrderQueryTest.php`
Available after: none (prereq)
Architecture rule: scalar-safe parsing (`ListQuery`), allowlist validation, 422 envelope `{status,message,errors}`, existing behavior unchanged when no new params
[RESTATE: Validation runs BEFORE query construction]

## DELIVERABLE
Given valid `outlet_id`/`status`/`start`/`end`, When `GET /api/admin/orders`, Then filtered results with preserved pagination/sort/meta
Given `outlet_id=abc` or `status=Unknown` or `start>end` or range>90d or malformed date, When called, Then 422 `{status,message,errors:{field}}` and no broadened query
Given any query key outside the `outlet_id`/`status`/`start`/`end` allowlist, When called, Then 422 identifying the unknown key and no order rows are returned
Given no new parameters, When called, Then identical pre-change behavior
[must-not] Given an invalid filter, When called, Then the endpoint must NOT return all orders

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `outlet_id` positive integer
  - `status` csv normalized to unique values, each in the four canonical statuses
  - `start`/`end` valid `YYYY-MM-DD`, `start<=end`, range ≤90 days
  - Every query key outside the `outlet_id`/`status`/`start`/`end` allowlist → 422
  - 422 with field-level errors; never a 500
Must-not-have:
  - Broadened query on validation failure
  - New RBAC/menu keys
Open question risks:
  - Existing `SORT_ALLOWLIST`/cursor pagination interacts with filters → report NEEDS_CONTEXT if sort breaks
Rollback note:
  - Remove the additive filter handling; existing unfiltered listing is unchanged
Red flags:
  - Validation after query construction → STOP
  - Touching protected services → STOP

## STOP CONDITIONS
Done when: all filter combos pass, 422 contract correct, default behavior unchanged
Uncertain when: cursor pagination cannot compose with the filters
Escalate when: filtered queries break the existing pagination contract

---

### Task 5: API client — typed ApiError + fetchGeographicData propagation [prereq]

## OBJECTIVE
Introduce a typed `ApiError` (`{status: number|null, retryable: boolean, message: string}`), make `adminFetch` throw it with the real HTTP status and retryable classification (5xx/network retryable; 401/403 not), and propagate it through `fetchGeographicData`. Error classification must never rely on message strings.

Steps:
1. Write failing test for: HTTP status propagation
   Test file: `apps/web/src/lib/api-error.test.ts`
   Level: unit
   Test intent: Given `fetch` resolves with HTTP 500 / When `adminFetch` runs / Then it throws `ApiError` with `status:500` and `retryable:true`; Given HTTP 401 or 403 / Then `retryable:false`; Given a network rejection / Then `status:null` and `retryable:true`.
   Exercise through: `adminFetch` (mocked `global.fetch`)
   Test doubles: mock `fetch`; do NOT mock the unit under test
   Expected RED: module/class does not exist; current code throws a plain `Error`
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/api-error.test.ts`
3. Implement `ApiError` + update `adminFetch` to attach `res.status` and classify → verify PASS → refactor → commit `feat(api): typed ApiError with status and retryable`
4. Write failing test for: geographic fetch propagation
   Test file: `apps/web/src/lib/data-intelligence-api.test.ts`
   Level: unit
   Test intent: Given `adminFetch` rejects with a 403 `ApiError` / When `fetchGeographicData` runs / Then the same `ApiError` (with `status` and `retryable`) propagates unchanged and no dummy fallback masks it.
   Exercise through: `fetchGeographicData`
   Test doubles: mock `adminFetch`; do NOT mock the unit under test
   Expected RED: error is swallowed or rethrown as a plain `Error`
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/data-intelligence-api.test.ts`
6. Implement propagation → verify PASS → refactor → commit `feat(api): propagate typed error through geographic fetch`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rule: Response errors and stale-data rule; Story: Error classification and retry
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: lightweight
Justification: an error class plus a fetch wrapper — pure logic, no new dependencies.

## SANDWICH CONTEXT
[CRITICAL: Error classification branches on `ApiError.status`, never on localized message text]
You are implementing typed API error propagation.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/lib/api-error.ts`, `apps/web/src/lib/api-error.test.ts`, `apps/web/src/lib/api.ts`, `apps/web/src/lib/data-intelligence-api.ts`, `apps/web/src/lib/data-intelligence-api.test.ts`
Available after: none (prereq)
Architecture rule: 5xx/network retryable; 401/403 not; existing `withDummyRead` short-circuit preserved; no stale snapshot after failure
[RESTATE: Error classification branches on `ApiError.status`]

## DELIVERABLE
Given HTTP 500, When `adminFetch`, Then throws `ApiError{status:500,retryable:true}`
Given a network rejection, When `adminFetch`, Then throws `ApiError{status:null,retryable:true}`
Given HTTP 401/403, When `adminFetch`, Then `retryable:false`
Given `fetchGeographicData` fails, When called, Then the typed `ApiError` propagates unchanged
[must-not] Given any failure, When handled, Then classification must NOT read the message string

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `ApiError` exposes `status`, `retryable`, `message`
  - `adminFetch` attaches the real HTTP status
  - Network failure → `status:null`, `retryable:true`
  - Existing `withDummyRead` behavior preserved
Must-not-have:
  - String-based error classification
  - New dependencies
Open question risks:
  - `fetch` mock in the test env does not expose `status` → report NEEDS_CONTEXT
Rollback note:
  - Revert to the plain `Error` throw
Red flags:
  - Message-string branching → STOP
  - Dummy path bypassed for admin reads → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all error scenarios yield the correct `ApiError` and the page can branch on `status`
Uncertain when: the test environment cannot surface HTTP status
Escalate when: `withDummyRead` semantics would change

---

### Task 6: Dummy — buildGeographic v2 + deterministic fixture parity [depends: T1]

## OBJECTIVE
Extend `buildGeographic()` in `apps/web/src/dummy/aggregates.ts` to the v2 shape with a deterministic fixture matching the spec table (outlet IDs 101–105), and update `aggregates.test.ts` so the JABODETABEK bbox assertion checks plottable points only plus literal per-status/period expectations.

Steps:
1. Write failing test for: deterministic v2 fixture values
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit
   Test intent: Given the deterministic master/tx fixture (101=H valid, 102=E `null` lat, 103=F `(0,0)`, 104=C zero orders, 105=V1 legacy-only) / When `buildGeographic(master, tx, window)` / Then `map_points` carry the exact literal `orders_by_status`, `sales_by_status`, `daily_by_status` (H buckets 09-20/09-22/09-25), `product_summary`, `product_summary_truncated`, `latest_request`, and `plottable` per the spec table; V1 has no v2 fields.
   Exercise through: `buildGeographic`
   Test doubles: fixed master+tx fixture (NO RNG / no randomness in the geographic fixture)
   Expected RED: v1 shape only; fixture IDs absent
2. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/aggregates.test.ts`
3. Implement `buildGeographic` v2 + deterministic fixture for 101–105 → verify PASS → refactor → commit `feat(dummy): geographic v2 fixture with deterministic parity`
4. Write failing test for: literal filter outcomes + bbox update
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit
   Test intent: Given the fixture, When `computeFilteredCounts` (from T1) runs for the documented combos / Then the literals hold: Semua+30d H=10; Semua+7d H=8; {New,Conf}+7d H=5; {New,Conf}+Hari ini H=3; invalid count=2 (E,F); C hidden; V1 excluded from 7d with `outlets_without_daily_detail=1`; and the bbox assertion now covers plottable points only (E/F excluded).
   Exercise through: `buildGeographic` + `computeFilteredCounts`
   Test doubles: fixed fixture
   Expected RED: v2 fields absent so counts cannot be literal-asserted
5. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/aggregates.test.ts`
6. Verify literal assertions pass and update the bbox test → refactor → commit `test(dummy): literal parity assertions for geographic fixture`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rule: Dummy fixture matrix (deterministic table, NO RNG), Story: Dummy parity; spec section: Deterministic dummy fixture table
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: deterministic fixture construction plus v2 field parity and literal assertions.

## SANDWICH CONTEXT
[CRITICAL: The dummy fixture must assert the exact literals from the spec table — never recompute expected values with the filter code under test]
You are implementing the dummy geographic aggregate v2 with deterministic fixture parity.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/dummy/aggregates.ts`, `apps/web/src/dummy/aggregates.test.ts`
Available after: T1 (filter helpers for literal assertions)
Architecture rule: `withDummyRead` parity, deterministic output (no randomness in the geographic fixture), integer-cents money, explicit fallback for v1 rows
[RESTATE: Assert exact literals from the spec table]

## DELIVERABLE
Given the deterministic fixture (101–105), When `buildGeographic`, Then `map_points` carry v2 fields matching the spec's Expected literals table
Given Semua+7d, Then H=8, invalid=2 (E,F), C hidden, V1 excluded with `outlets_without_daily_detail=1`
Given V1 (legacy-only), When 30d, Then the legacy marker renders; when 7d/Hari ini, Then it is excluded and counted
Given the updated bbox test, Then it asserts plottable points only
[must-not] Given the fixture, When built, Then the test must NOT derive expected values from the filter implementation

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Fixture IDs 101–105 stable across runs
  - Every asserted value is a fixed literal from the spec table
  - `daily_by_status` buckets match the spec (H: 09-20, 09-22, 09-25)
  - `product_summary` deterministic top-5
  - `latest_request` present for v2 rows, absent for V1
Must-not-have:
  - Random generation in the geographic fixture
  - Recomputing expected values with the filter code under test
Open question risks:
  - Existing dummy master schema cannot host IDs 101–105 → report NEEDS_CONTEXT
Rollback note:
  - Revert to the v1 dummy geographic and restore the previous bbox test
Red flags:
  - Non-deterministic fixture → STOP
  - Existing unrelated aggregate tests broken → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all literal assertions pass, bbox test updated, parity with the production filter math
Uncertain when: fixture data conflicts with the existing dummy master schema
Escalate when: the fixture cannot be made deterministic

---

### Task 7: Frontend — GeoMap marker-layer diff, fitBounds scope, coordinate parity, a11y list [depends: T1, T5]

## OBJECTIVE
Refactor `GeoMap.tsx` so the Leaflet map instance is stable across data/filter changes, markers are diffed by `outlet_id`, `fitBounds` runs only on initial valid data or explicit reset, center/zoom are preserved on filter change, a keyboard-accessible outlet list is exposed, tile failure shows a fallback, and `isValidPoint()` mirrors the backend rule exactly.

Steps:
1. Write failing test for: marker-layer diff with stable instance
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: component (Jest + Leaflet mock)
   Test intent: Given points A+B rendered / When points change to A+C / Then the `L.Map` instance is identical, B's marker is removed, C's marker is added, and center/zoom are unchanged.
   Exercise through: `GeoMap` rerender
   Test doubles: existing Leaflet mock
   Expected RED: `useEffect([points])` rebuilds the map
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/GeoMap.test.tsx`
3. Implement stable instance + marker diff → verify PASS → refactor → commit `feat(geomap): stable map with marker-layer diff`
4. Write failing test for: fitBounds scope
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: component
   Test intent: Given an initial valid-data render / Then `fitBounds` is called once with the valid points; Given a later filter change / Then `fitBounds` is NOT called and center/zoom are preserved.
   Exercise through: `GeoMap` rerender
   Test doubles: Leaflet mock with `fitBounds` spy
   Expected RED: fitBounds re-applied on every render
5. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/GeoMap.test.tsx`
6. Implement fitBounds guard + viewport preservation → verify PASS → refactor → commit `feat(geomap): fitBounds only on initial load or reset`
7. Write failing test for: coordinate parity + a11y list + tile fallback
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: component
   Test intent: Given the shared case table (`null`, `"-6.2"`, `NaN`, `Infinity`, `91`, `181`, `(0,0)` vs `(-6.2,106.8)`) / When `isValidPoint` / Then it matches the backend booleans; Given markers rendered / Then a focusable outlet list exposes every visible outlet and Enter opens the drawer; Given a tile-layer error / Then fallback text renders and the list stays usable.
   Exercise through: `GeoMap` + exported `isValidPoint`
   Test doubles: Leaflet mock
   Expected RED: `isValidPoint` accepts numeric strings; no list; no tile fallback
8. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/GeoMap.test.tsx`
9. Implement tightened `isValidPoint`, keyboard list, tile-error fallback → verify PASS → refactor → commit `feat(geomap): coordinate parity, keyboard list, tile fallback`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Coordinate safety and bounds, Filter update performance, Coordinate rule (plottable), Accessibility
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: Leaflet lifecycle management, marker-diff algorithm, and a11y additions with a mock-driven test harness.

## SANDWICH CONTEXT
[CRITICAL: The Leaflet map instance must remain stable — filter changes update markers only, never rebuild the map or tiles]
You are implementing the GeoMap marker-layer diff, fitBounds scope, coordinate parity, and a11y list.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/components/data-intelligence/GeoMap.tsx`, `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
Available after: T1 (filter helpers), T5 (typed error/points shape)
Architecture rule: no new map library (direct Leaflet only), one marker per outlet, marker only when filtered count > 0, stable instance, fitBounds initial/reset only, `isValidPoint` mirrors `isValidCoordinate`
[RESTATE: The Leaflet map instance must remain stable]

## DELIVERABLE
Given points change, When the component rerenders, Then the same `L.Map` instance is reused, markers diff by `outlet_id`, center/zoom preserved
Given initial valid points, When first render, Then `fitBounds(validPoints)` runs once
Given a filter change, When rerendering, Then `fitBounds` does NOT run
Given tile-layer failure, When rendering, Then fallback text shows and the outlet list remains usable
Given keyboard-only use, When tabbing the map section, Then the outlet list focuses and Enter opens the drawer
[derived] Given zero valid points, When the map initializes, Then the Jakarta default center/zoom is used and no Null Island marker renders

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Single `L.Map` instance across filter changes
  - Marker add/remove/update keyed by `outlet_id`
  - `fitBounds` only on mount-with-valid-data or explicit reset
  - `isValidPoint` rejects numeric strings, `NaN`, `Infinity`, `(0,0)` — parity with backend
  - Keyboard outlet list with an accessible label; Enter opens the drawer
  - Tile error fallback text with the list still usable
Must-not-have:
  - `react-leaflet` or any new map library
  - Map rebuild on filter change
Open question risks:
  - The Leaflet mock cannot spy on `fitBounds` → report NEEDS_CONTEXT
Rollback note:
  - Revert to the legacy `useEffect([points])` rebuild pattern
Red flags:
  - New dependency added → STOP
  - Map instance recreated on filter change → STOP

## STOP CONDITIONS
Done when: marker diff works, fitBounds preserved, a11y list functional, tile fallback renders
Uncertain when: the Leaflet mock lacks the needed API
Escalate when: viewport preservation is impossible without rebuilding

---

### Task 8: Frontend — OutletDrawer (snapshot-only, freeze banner, a11y) [depends: T1, T5, T7]

## OBJECTIVE
Create `OutletDrawer.tsx`: a labelled modal dialog showing outlet name/territory/filtered count, status counts for the opening filter, daily breakdown, `latest_request`, and `product_summary` (labelled "Top 5 produk" when `product_summary_truncated`, snapshot-window scope when the period is <30d, and an explicit unavailable message for v1/partial rows); a filter-freeze banner when the map filter diverges; an encoded "Lihat semua order" link; and full keyboard semantics.

Steps:
1. Write failing test for: full v2 detail render
   Test file: `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
   Level: component
   Test intent: Given a v2 point with detail and opening filter `{New,Confirmed}`+30d / When the drawer opens / Then it shows the outlet/territory/count, status breakdown, daily data, `latest_request`, a product summary labelled correctly, and an order link whose query is `outlet_id`/`status`/`start`/`end` URL-encoded.
   Exercise through: `OutletDrawer` component
   Test doubles: none (props-only)
   Expected RED: component does not exist
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/OutletDrawer.test.tsx`
3. Implement the drawer render + order link → verify PASS → refactor → commit `feat(drawer): outlet detail drawer`
4. Write failing test for: v1/partial fallback + product labelling
   Test file: `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
   Level: component
   Test intent: Given a v1 row without status/daily / When the drawer opens / Then it shows "Detail produk belum tersedia — jalankan pipeline data" and invents no values; Given a v2 row with `product_summary_truncated:true` / Then the list is labelled "Top 5 produk"; Given a period <30d / Then the product section is labelled snapshot-window scope.
   Exercise through: `OutletDrawer`
   Test doubles: none
   Expected RED: no fallback/label branches
5. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/OutletDrawer.test.tsx`
6. Implement fallback + labelling → verify PASS → refactor → commit `feat(drawer): explicit detail fallback and product labelling`
7. Write failing test for: filter-freeze + keyboard semantics
   Test file: `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
   Level: component
   Test intent: Given the drawer open with opening filter F1 / When the map filter changes to F2 / Then the banner "Filter berubah — tutup dan buka ulang untuk memuat data terbaru" appears and the drawer data stays frozen at F1; Given `role="dialog"`+`aria-modal="true"` / When opened / Then focus moves inside and is contained; Given Escape/close/backdrop / Then it closes and focus returns to the exact opening trigger.
   Exercise through: `OutletDrawer`
   Test doubles: none
   Expected RED: no banner, no dialog semantics, no focus management
8. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/OutletDrawer.test.tsx`
9. Implement freeze banner + dialog a11y + focus trap/restore → verify PASS → refactor → commit `feat(drawer): filter-freeze banner and dialog accessibility`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Drawer is snapshot-only and filter-aware, Detail fallback is explicit, Order navigation preserves context, Drawer is a labelled modal dialog
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: a new component with multiple a11y requirements and opening-filter snapshot logic.

## SANDWICH CONTEXT
[CRITICAL: The drawer freezes its opening filter — it must never silently rewrite when the map filter changes]
You are implementing the outlet detail drawer.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/components/data-intelligence/OutletDrawer.tsx`, `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
Available after: T1 (filter helpers), T5 (typed error), T7 (GeoMap click handler)
Architecture rule: snapshot-only data, filter-freeze banner, explicit fallback messages, order link with additive query, `role="dialog"`/`aria-modal`
[RESTATE: The drawer freezes its opening filter]

## DELIVERABLE
Given a v2 point, When the drawer opens, Then all detail sections render with the correct labels (Top 5 / snapshot-window / unavailable)
Given a v1/partial row, When the drawer opens, Then explicit unavailable messages render with no invented data
Given the drawer open and the map filter changed, Then the banner shows and the data stays frozen
Given keyboard interaction, Then Escape/close/backdrop closes and focus returns to the exact trigger
[must-not] Given a filter change, When the drawer is open, Then it must NOT silently rewrite its data

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `role="dialog"`, `aria-modal="true"`, accessible name = outlet name
  - Focus trap inside the drawer
  - Freeze banner compares opening filter vs current filter (status set + period)
  - Order link URL-encodes every value and joins statuses with a comma
  - Product labelling: "Top 5 produk" only when truncated; snapshot-window scope when period <30d; unavailable when absent
Must-not-have:
  - Silent data rewrite on filter change
  - Fabricated status/daily values for v1 rows
Open question risks:
  - Focus restoration to a Leaflet marker trigger is not feasible → fall back to the list alternative (report in DELIVERABLE)
Rollback note:
  - Remove the drawer component and revert to the marker popup
Red flags:
  - Drawer auto-updates on filter change → STOP
  - Focus not restored → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all drawer scenarios pass and focus restore is verified
Uncertain when: the opening trigger is not a stable focus target
Escalate when: a11y semantics cannot be satisfied without a new dependency

---

### Task 9: Frontend — Page filter chips, empty states, error handling, drawer wiring [depends: T1, T5, T7, T8]

## OBJECTIVE
Update `page.tsx` with status multi-toggle chips (`New`, `Confirmed`, `Delivered`, `Partially Paid`, `Semua`) and period chips (`Hari ini`, `7 hari`, `30 hari`) using `aria-pressed`, default `{New,Confirmed}`+30d, filter math via T1, the four precedence-ordered empty states, the production `outlets_without_daily_detail` count (v1/partial rows excluded from narrow-period views), typed-error classification by `status`, a retry action, an admin role check before any dummy read, drawer wiring with a frozen filter snapshot, and a freshness label.

Steps:
1. Write failing test for: default state + filter chips
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: component (page)
   Test intent: Given a v2 snapshot / When the page loads / Then the chips `{New,Confirmed}`+30d are pressed and markers reflect the filtered counts; Given a chip is toggled / Then `aria-pressed` and the marker counts update; Given all statuses are deselected (empty selection) / Then the page renders the no-request empty state and does NOT silently revert to `{New,Confirmed}`.
   Exercise through: `DataIntelligencePage`
   Test doubles: mocked `fetchGeographicData`; dummy store
   Expected RED: no filter chips or filter math
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
3. Implement chips + default + filter math wiring → verify PASS → refactor → commit `feat(page): status and period filter chips`
4. Write failing test for: four empty-state precedence
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: component
   Test intent: Given no active snapshot / Then "Data peta belum tersedia" only; Given `geographic_section_available:false` / Then the same; Given filtered orders >0 and all matching coordinates invalid / Then "Outlet memiliki request tetapi koordinat belum tersedia" + count; Given filtered orders = 0 / Then "Tidak ada request pada periode ini" with no coordinate warning; Given a snapshot mixing v2 rows with v1/legacy rows / When period is 7d or Hari ini / Then the page computes and displays `outlets_without_daily_detail` = the count of outlets with filtered orders that lack `daily_by_status` (excluded from the narrow-period marker set), and the count is 0 for the 30d period.
   Exercise through: `DataIntelligencePage`
   Test doubles: mocked fetch per state
   Expected RED: no empty states
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
6. Implement the four precedence states → verify PASS → refactor → commit `feat(page): four precedence-ordered empty states`
7. Write failing test for: error classification + retry + role gate + freshness
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: component
   Test intent: Given `ApiError{status:500}` or a network error / Then alert + "Coba lagi", visible data cleared, no stale snapshot; Given 403 / Then "Akses ditolak" with no retry; Given 401 / Then the token is cleared and "Sesi berakhir. Silakan masuk kembali" shows; Given a non-admin with dummy mode on / Then access is denied before any dummy read; Given a loaded snapshot / Then the freshness label shows "Data per {window.end} (Asia/Jakarta)" and the version.
   Exercise through: `DataIntelligencePage`
   Test doubles: mocked `fetchGeographicData` rejecting with typed errors; mocked `/auth/me`
   Expected RED: no error classification/retry/role gate/freshness label
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
9. Implement error classification, retry, role gate, freshness label → verify PASS → refactor → commit `feat(page): error classification, retry, role gate, freshness label`
10. Write failing test for: drawer wiring with frozen filter
    Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
    Level: component
    Test intent: Given a marker click / When the drawer opens / Then it receives the opening filter snapshot; Given the filter then changes / Then the drawer keeps its frozen data and shows the banner.
    Exercise through: `DataIntelligencePage`
    Test doubles: mocked fetch + Leaflet mock
    Expected RED: drawer not wired
11. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
12. Implement drawer wiring → verify PASS → refactor → commit `feat(page): wire drawer with frozen filter snapshot`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Default active filter, Status & period filtering, Empty-state precedence, Error classification and retry, Admin-only visibility, Freshness
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: multiple UI states, error classification, drawer wiring, and role gating in one page component.

## SANDWICH CONTEXT
[CRITICAL: Empty-state precedence is strict — no snapshot > no geographic section > filtered orders>0 with all-invalid coords > filtered orders=0]
You are implementing the Data Intelligence page filter/empty/error/drawer integration.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/app/data-intelligence/page.tsx`, `apps/web/src/app/data-intelligence/page.test.tsx`
Available after: T1 (filter math), T5 (typed error), T7 (GeoMap click→drawer), T8 (drawer component)
Architecture rule: admin-only checked before any dummy read; typed-error `status` branching; no stale data after error; explicit empty-state messages
[RESTATE: Empty-state precedence is strict]

## DELIVERABLE
Given default load, Then `{New,Confirmed}`+30d chips are active and markers are filtered
Given no active snapshot, Then "Data peta belum tersedia" (no other messages)
Given filtered orders>0 with all-invalid coords, Then "Outlet memiliki request tetapi koordinat belum tersedia" + count
Given filtered orders=0, Then "Tidak ada request pada periode ini" with no coordinate warning
Given a snapshot with v1/legacy rows, When period is 7d/Hari ini, Then the page shows the `outlets_without_daily_detail` count for outlets with filtered orders but no `daily_by_status`
Given all statuses deselected, Then the no-request state renders and the selection is NOT reverted to `{New,Confirmed}`
Given a 5xx/network error, Then alert + retry, data cleared, no stale snapshot
Given 403, Then "Akses ditolak" with no retry; Given 401, Then token cleared + "Sesi berakhir"
Given a non-admin in dummy mode, Then denial happens before any dummy read
Given a loaded snapshot, Then the freshness label uses `window.end` + timezone and shows the version
[derived] Given an unknown deep-link status/period, Then it falls back to `{New,Confirmed}`+30d without a 500

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Chips are real `<button>`s with `aria-pressed`; `Semua` toggles all four
  - Empty status selection → no-request state (never silently reverted)
  - `outlets_without_daily_detail` computed in production for narrow periods and disclosed to the admin
  - Unknown deep-link status/period → safe fallback
  - Retry button only for retryable errors
  - Freshness label from `window.end`, never `new Date()`
  - Role checked before `withDummyRead`
Must-not-have:
  - Stale snapshot shown after a fetch error
  - Dummy data rendered before the role check
Open question risks:
  - `withDummyRead` timing conflicts with the role check → report NEEDS_CONTEXT
Rollback note:
  - Revert the page to the legacy layout (no filters/drawer/classified errors)
Red flags:
  - Dummy read before role check → STOP
  - Stale data rendered after error → STOP

## STOP CONDITIONS
Done when: all filter/empty/error/role/freshness scenarios pass
Uncertain when: the role source (`/auth/me`) is unavailable in the page context
Escalate when: the role gate cannot precede the dummy read

---

### Task 10: Integration — Page + GeoMap + Drawer filter sync [depends: T9]

## OBJECTIVE
Verify end-to-end synchronization: page filter state → GeoMap marker diff (no rebuild) → drawer opening-filter freeze → order link using the opening filter.

Steps:
1. Write failing test for: cross-component filter sync
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: integration (page + GeoMap + drawer)
   Test intent: Given the page with v2 data / When the admin toggles the `Delivered` chip / Then markers update without rebuilding the `L.Map` instance, an open drawer shows the freeze banner, and the drawer's order link still uses the opening filter.
   Exercise through: full page render + interactions
   Test doubles: mocked fetch with v2 data; Leaflet mock
   Expected RED: filter change rebuilds the map or the drawer/link uses the current filter
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
3. Write failing test for: order link navigation
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: integration
   Test intent: Given the drawer open for outlet 12 with `{New,Confirmed}`+7d / When "Lihat semua order" is activated / Then navigation targets `/admin/orders` with `outlet_id=12`, `status=New,Confirmed`, `start`, `end` all URL-encoded.
   Exercise through: full page render + link activation
   Test doubles: mocked fetch + router
   Expected RED: link missing or uses the current filter
4. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
5. Fix any remaining wiring → verify PASS → refactor → commit `test(integration): page-geomap-drawer filter sync`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Filter update performance, Drawer freeze, Order navigation preserves context
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: cross-unit integration verification that only holds when page, GeoMap, and drawer collaborate.

## SANDWICH CONTEXT
[CRITICAL: A filter change must NOT rebuild the Leaflet map, the drawer must freeze, and the order link must use the opening filter]
You are verifying the integration of page filters, GeoMap markers, and drawer state.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/app/data-intelligence/page.test.tsx`
Available after: T9 (all components wired)
Architecture rule: stable Leaflet instance, filter-freeze banner, additive encoded order query
[RESTATE: A filter change must NOT rebuild the Leaflet map]

## DELIVERABLE
Given a filter change, When the page updates, Then markers diff on the same map instance, the drawer shows the freeze banner, and the order link uses the frozen filter
Given the order link is activated, Then the URL carries `outlet_id`, `status` (csv), `start`, `end` encoded
[must-not] Given a filter change, When the page updates, Then the map must NOT rebuild and the drawer must NOT silently update

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Map instance identity preserved across filter changes
  - Freeze banner accurate
  - Order link query matches the drawer's opening filter, not the current one
Must-not-have:
  - Map rebuild on filter change
  - Drawer silently updating
Open question risks:
  - The test environment cannot verify map instance identity → report NEEDS_CONTEXT
Rollback note:
  - N/A (test-only task)
Red flags:
  - New production code beyond wiring fixes → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: integration tests pass, no map rebuild, drawer freeze works, link correct
Uncertain when: map instance identity is unverifiable in jsdom
Escalate when: an integration defect requires a design change

---

### Task 11: Performance — ScaleFixtureSeeder baseline, <100ms recompute, <500KB response + truncation cap + frontend warning [depends: T3, T9]

## OBJECTIVE
Add `GeographicScaleTest.php` (backend response size / truncation) and a frontend perf test using the `ScaleFixtureSeeder` shape (500 total / 100 active outlets): assert the response is ≤500KB or `meta.truncated=true` with cap metadata, and that client-side filter recompute is <100ms.

Steps:
1. Write failing test for: response size / truncation metadata
   Test file: `apps/api/tests/Performance/GeographicScaleTest.php`
   Level: performance (PHP)
   Test intent: Given `ScaleFixtureSeeder` data with a published v2 snapshot / When `GET /admin/analytics/geographic` / Then the serialized response is ≤500KB, or `meta.truncated=true` with `omitted_zero_days`/`product_summary_capped` set and the cap applied.
   Exercise through: HTTP endpoint + pipeline publish
   Test doubles: none (real DB)
   Expected RED: no size guard or truncation logic
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicScaleTest`
3. Implement the response-size guard + truncation cap in the controller (setting `meta.truncated=true`) → verify PASS → refactor → commit `feat(performance): geographic response size guard with truncation metadata`
4. Write failing test for: client recompute budget
   Test file: `apps/web/src/lib/geographic-filters.perf.test.ts`
   Level: performance (Jest)
   Test intent: Given the 100-active-outlet fixture shape / When a status/period chip change recomputes counts via T1 helpers / Then the elapsed time is <100ms.
   Exercise through: `computeFilteredCounts` over the scale fixture
   Test doubles: generated deterministic fixture data (no randomness)
   Expected RED: no perf test exists
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.perf.test.ts`
6. Record the measured baseline in the test comment and confirm PASS → refactor → commit `test(perf): client recompute budget on scale fixture`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Truncation metadata, Filter update performance, Story: Filter update performance; spec Open Questions: performance baseline
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: a performance test plus a response-size guard with explicit truncation metadata.

## SANDWICH CONTEXT
[CRITICAL: If the 500KB target cannot be met, the controller MUST apply the documented cap and set `meta.truncated=true` — never silently truncate]
You are implementing the performance baseline and truncation guard.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/tests/Performance/GeographicScaleTest.php`, `apps/web/src/lib/geographic-filters.perf.test.ts`, `apps/api/app/Http/Controllers/GeographicAnalyticsController.php`
Available after: T3 (controller v2 payload)
Architecture rule: <100ms recompute, ≤500KB response, explicit `meta.truncated` with a frontend warning, no silent truncation
[RESTATE: If the 500KB target cannot be met, apply the cap and set meta.truncated=true]

## DELIVERABLE
Given `ScaleFixtureSeeder` data, When the endpoint responds, Then the response is ≤500KB or `meta.truncated=true` with `omitted_zero_days`/`product_summary_capped`
Given 100 active outlets, When a filter changes, Then recompute is <100ms
Given a simulated oversized response, When serialized, Then `meta.truncated=true` and the frontend warning renders
[must-not] Given an oversized response, When capped, Then the response must NOT be truncated without `meta.truncated=true`

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Test uses the real `ScaleFixtureSeeder` data
  - Truncation cap applied at controller serialization
  - Frontend warning copy: "Data peta dipangkas untuk performa. Beberapa detail mungkin tidak lengkap." appears only when `meta.truncated=true`
  - `meta.omitted_zero_days>0` with `meta.truncated=false` produces no warning
  - Client recompute measured on the reference fixture; the measured CI baseline is recorded in the perf test comment
Must-not-have:
  - Silent truncation without `meta.truncated`
  - New dependencies
Open question risks:
  - The response is inherently >500KB even with zero-day omission + product cap → report NEEDS_CONTEXT
Rollback note:
  - Remove the truncation cap and revert to unbounded serialization
Red flags:
  - Truncation without metadata → STOP
  - Perf test flaky on shared CI → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: the scale test passes (size or truncation) and client recompute is <100ms
Uncertain when: the seeder shape cannot represent 100 active outlets with valid coordinates
Escalate when: the response cannot meet 500KB even with the documented cap

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Shared geographic filter helper | prereq | lightweight | `periodToRange`/`computeFilteredCounts` compose status×period with integer cents; safe fallback |
| T2 | Backend service v2 payload + coordinate rule | prereq | standard | v2 row composition; `isValidCoordinate` case table |
| T3 | Backend controller passthrough + meta | T2 | standard | Every v2 field passes through; invalid rows retained; `meta` correct |
| T4 | Backend OrderController additive filters | prereq | standard | Filtered results + 422 envelope; default behavior unchanged |
| T5 | API client typed ApiError | prereq | lightweight | `status`/`retryable` classification; propagation through `fetchGeographicData` |
| T6 | Dummy buildGeographic v2 + fixture | T1 | standard | Literal fixture outcomes (H=8 on Semua+7d, invalid=2, V1 excluded) |
| T7 | GeoMap marker diff + fitBounds + a11y | T1, T5 | standard | Stable instance, marker diff, fitBounds scope, `isValidPoint` parity, keyboard list, tile fallback |
| T8 | OutletDrawer | T1, T5, T7 | standard | Detail render + fallback, freeze banner, `role=dialog` focus trap/restore, encoded order link |
| T9 | Page filters/empty/error/drawer wiring | T1, T5, T7, T8 | standard | Default chips, 4 precedence states, typed error classification + retry, role-before-dummy, freshness |
| T10 | Integration filter sync | T9 | standard | No map rebuild on filter change; drawer freeze; link uses opening filter |
| T11 | Performance baseline + truncation | T3, T9 | standard | ≤500KB or `meta.truncated`; frontend warning on `truncated=true` only; <100ms recompute on 100-active fixture |
