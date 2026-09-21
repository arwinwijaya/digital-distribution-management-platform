# EXECUTION PLAN — Analitik Deeper Insight

**Date:** 2026-09-18
**Spec:** docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
**Status:** approved
**Total tasks:** 7

---

## Execution Overview

### Recommended Order
```
T1, T3, T4, T7 (parallel) → T2 → T5 → T6
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T3, T4, T7 | start immediately (all `[prereq]`) |
| Group B | T2 | T1 completes |
| Group C | T5 | T2, T3, T4 complete |
| Group D | T6 | T5 completes |

### Constraints Reminder
**Architecture:** Reuse `AnalyticsService` private helpers — do NOT refactor the Dashboard path
(`dashboard()`, `salesTrends()`, `outletPerformance()` keep their exact behavior). Money/percentage
arithmetic MUST go through the existing integer-cents path (`decimalToCents`/`moneyFromCents`) — no
second rounding path, no decimal library. Bounded aggregates only (366d / top-10). `withDummyRead`
guard + `{status,data}` envelope + id-ID formatting. Do NOT touch `DataPipelineService`/snapshot
publication, forecast/recommendation/segmentation internals, or auth/RBAC core.
**Out-of-scope:** ML/LLM production; BI suite/report builder; date-range picker (insight window is
FIXED 30d — `/analytics/insight` must ignore `start_date`/`end_date`); rebuild `/data-intelligence`
or `/admin/analytics/*`; new pipeline/schema migration; replacing Dashboard; LLM narrative;
drill-down/clickable rows; outlet-user self-scoped analytics view.
**Assumptions at risk:** (a) no delta for `outlets_total`/`products_total` — if wrong, add later;
(b) id-ID trust-label copy wording; (c) `platform_owner` treated admin-equivalent in Sidebar globally.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules.

### File Structure Map

```
Rule 1.1/1.2 (window + delta)      → Modify apps/api/app/Services/AnalyticsService.php   (created by: T1)
Rule 2.1/2.2 (needs attention)     → Modify apps/api/app/Services/AnalyticsService.php   (T1)
Rule 3.1/3.2 (trend + ranking)     → Modify apps/api/app/Services/AnalyticsService.php   (T1)
Rule 5.1 (auth gate)               → Modify apps/api/app/Http/Controllers/AnalyticsController.php (T2)
                                     Modify apps/api/routes/api.php                        (T2)
Rule 6.1 (dummy parity)            → Modify apps/web/src/dummy/aggregates.ts              (T3)
Contract (types + presentation)    → Create apps/web/src/app/analytics/types.ts           (T4)
                                     Create apps/web/src/app/analytics/presentation.ts     (T4)
Rules 1-3 (loader)                 → Modify apps/web/src/app/analytics/api.ts             (T5)
Rules 1-4,7 (page)                 → Create apps/web/src/components/analytics/MetricStrip.tsx   (T6)
                                     Create apps/web/src/components/analytics/NeedsAttention.tsx (T6)
                                     Create apps/web/src/components/analytics/TrustLabel.tsx     (T6)
                                     Modify apps/web/src/app/analytics/page.tsx            (T6)
Rule 5.1 (nav)                     → Modify apps/web/src/components/Sidebar.tsx            (T7)

Tests:
  Create apps/api/tests/Feature/AnalyticsInsightServiceTest.php     (T1)
  Create apps/api/tests/Feature/AnalyticsInsightEndpointTest.php    (T2)
  Modify apps/web/src/dummy/aggregates.test.ts                      (T3)
  Create apps/web/src/app/analytics/presentation.test.ts            (T4)
  Create apps/web/src/app/analytics/api.test.ts                     (T5)
  Create apps/web/src/app/analytics/page.test.tsx                   (T6)
  Modify apps/web/src/components/Sidebar.test.tsx                   (T7)
```

Note: `apps/web/src/app/analytics/types.ts` and `presentation.ts` do not exist until T4 runs. T5's
RED test may import from them; T6's may import from T4 + T5 outputs. No test imports a file a *later*
task creates.

---

## Pocket Packets

---

### Task 1: Backend AnalyticsService::insight() composition [prereq]

## OBJECTIVE
Add a public `insight(CarbonInterface $end): array` method to `AnalyticsService` that composes the
fixed-window comparison payload, reusing the class's existing private helpers. No changes to
`dashboard()`, `salesTrends()`, or `outletPerformance()` behavior.

Files:
- Modify: `apps/api/app/Services/AnalyticsService.php`
- Test: `apps/api/tests/Feature/AnalyticsInsightServiceTest.php` (new)

Steps:
1. Write failing test for: **window math + delta up/down/null/zero**
   Test file: `apps/api/tests/Feature/AnalyticsInsightServiceTest.php`
   Level: integration (service under test against sqlite :memory:)

   Test intent:
   Given orders exist so that current-window sales = "120.00" and previous-window sales = "100.00"
   When  `app(AnalyticsService::class)->insight(Carbon::parse('2026-09-18'))` is called
   Then:
   - `comparison.period` = {start_date: "2026-08-20", end_date: "2026-09-18"}
   - `comparison.previous_period` = {start_date: "2026-07-21", end_date: "2026-08-19"}
   - `metrics_delta.sales_total.delta_percent` === 20.0 and `direction` === "up"
   And when current = "80.00" → delta_percent === -20.0, direction "down"
   And when previous = "0.00" → delta_percent === null, direction "neutral"
   And when current === previous === "100.00" → delta_percent === 0.0, direction "neutral"

   Exercise through: the public `insight()` method only — never call private helpers directly.
   Test doubles: none — real sqlite :memory: + `RefreshDatabase`. Do NOT mock AnalyticsService.
   Expected RED: `insight()` does not exist → `BadMethodCallException`/`Error`.

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
   Expected failure: call to undefined method `AnalyticsService::insight()`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Services/AnalyticsService.php`
   Implement: `public function insight(CarbonInterface $end): array` computing
   `$currentStart = $end->copy()->subDays(29)->startOfDay()`, `$currentEnd = $end->copy()->endOfDay()`,
   `$prevEnd = $end->copy()->subDays(30)->endOfDay()`, `$prevStart = $end->copy()->subDays(59)->startOfDay()`.
   Reuse `orderQuery`, `paymentQuery`, `outstandingOrderQuery` for both windows. Delta via
   `(cur − prev)/prev × 100` rounded 1 decimal; direction from the **unrounded** ratio; previous == 0 → null.
   `outlets_total`/`products_total` are point-in-time active counts with **no** delta entry.

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
   Expected: PASS

5. Write failing test for: **excluded statuses + needs_attention qualification**
   Test file: `apps/api/tests/Feature/AnalyticsInsightServiceTest.php`
   Level: integration

   Test intent:
   Given an outlet whose only previous-window order is status "Cancelled"
   Then  that outlet's previous sales contribution is "0.00"
   And Given outlet X sells "1000.00" prev → "700.00" current
   Then  X appears in `needs_attention` with reason "sales_decline" and delta_percent -30.0
   And Given exactly "1000.00" → "800.00" (exactly -20%) → X is INCLUDED
   And Given "1000.00" → "800.04" → X is EXCLUDED (threshold evaluated on unrounded value)
   And Given an outlet with an unpaid order ("New"/"Confirmed"/"Delivered"/"Partially Paid") created
        90 days ago totaling "5000.00" → appears with reason "outstanding_risk"
   And Given prev "0.00", current "500.00", no outstanding → EXCLUDED

   Exercise through: `insight()`.
   Test doubles: none.
   Expected RED: `needs_attention` key absent / empty.

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
   Expected failure: assertion — `needs_attention` missing/empty.

7. Implement minimal code:
   File: `apps/api/app/Services/AnalyticsService.php`
   Implement: qualification only — a point-in-time outstanding query (reuse the existing
   `OUTSTANDING_ORDER_STATUSES` allow-list; because the existing `outstandingOrderQuery($start, $end)`
   requires a date bound, add a private `pointInTimeOutstandingByOutlet(): Collection` that applies the
   same allow-list with **no** `created_at` bound, summed per outlet via
   `CASE WHEN total_amount > paid_amount THEN total_amount - paid_amount ELSE 0 END`), sales-decline
   qualification (≥ 20% on **cents** so exactly −20% is inclusive, with previous > 0) OR point-in-time
   outstanding > 0, and each item's shape
   `{outlet_id, outlet_name, reason, delta_percent, outstanding_total}`.
   Deliberately do NOT yet cap the list or order it — that is Step 11. Return the items in
   insertion/qualification order at this step.

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
   Expected: PASS

9. Write failing test for: **cap 5 + deterministic ordering + both-reasons collapse**
   Test file: `apps/api/tests/Feature/AnalyticsInsightServiceTest.php`
   Level: integration

   Test intent:
   Given 8 outlets qualify (mixed decline + outstanding)
   Then  `needs_attention` has exactly 5 items
   And Given one outlet declines -35% and another has outstanding "900000.00"
   Then  the decline item precedes the outstanding item
   And Given "Alpha Outlet" and "Beta Outlet" have equal severity
   Then  "Alpha Outlet" precedes "Beta Outlet"
   And Given one outlet both declines 30% and has outstanding "5000.00"
   Then  it appears exactly once with reason "sales_decline"

   Exercise through: `insight()`.
   Test doubles: none.
   Expected RED: `needs_attention` is uncapped / unordered / duplicates the both-reason outlet.

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
    Expected failure: count is 8 (not 5) or ordering/tie-break assertions fail.

11. Implement minimal code:
    File: `apps/api/app/Services/AnalyticsService.php`
    Implement: cap at 5; sort all `sales_decline` items first by `|unrounded delta_percent|` desc, then
    `outstanding_risk` items by `outstanding_total` desc, each tie-break `outlet_name` asc then
    `outlet_id` asc; collapse both-reason outlets to a single row with `sales_decline` winning. (Step 7
    deliberately left these out so this cycle has a genuine RED.)

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
    Expected: PASS

13. Write failing test for: **trend zero-fill + ranking total + has_more**
    Test file: `apps/api/tests/Feature/AnalyticsInsightServiceTest.php`
    Level: integration

    Test intent:
    Given the current window has orders on only 12 of 30 days
    Then  `sales_trends` has exactly 30 entries, one per date 2026-08-20..2026-09-18
    And   each day without orders has orders_total 0, sales_total "0.00", payments_total "0.00"
    And Given 15 distinct outlets have sales in the current window
    Then  `outlet_performance_total` === 15, `outlet_performance_has_more` === true, 10 ranked rows
    And Given only 7 outlets → `outlet_performance_total` === 7 and `outlet_performance_has_more` === false

    Exercise through: `insight()`.
    Test doubles: none.
    Expected RED: `sales_trends` sparse (12) and `outlet_performance_total`/`has_more` missing.

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
    Expected failure: expected 30 trend buckets, got 12.

15. Implement minimal code:
    File: `apps/api/app/Services/AnalyticsService.php`
    Implement: zero-fill by calling `salesTrends($currentStart, $currentEnd, 'daily')` then padding every
    date in the current window with a zero bucket when absent (sorted ascending); compute
    `outlet_performance_total` as `COUNT(DISTINCT outlet_id)` over the current-window non-excluded orders
    and `outlet_performance_has_more` = total > OUTLET_PERFORMANCE_LIMIT; reuse
    `outletPerformance($currentStart, $currentEnd)` for the top-10 rows. Do NOT modify `salesTrends()` itself.

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest`
    Expected: PASS

17. Refactor while green (bounded):
    - Rule of three: if the delta computation or the cents-threshold comparison appears 3+ times in
      `AnalyticsService.php`, extract a private helper (e.g. `deltaPercent(int $cur, int $prev): ?float`,
      `directionFor(?float $delta): string`) on the same class — do NOT create a generic `utils.php`.
    - `AnalyticsService.php` will exceed ~300 lines; keep new private helpers grouped with an explicit
      comment block; do not split the class (splitting would risk the Dashboard path).
    - Re-run test: `cd apps/api && php artisan test --filter=AnalyticsInsightServiceTest` — must stay PASS
    - Also re-run the Dashboard suite to prove no regression:
      `cd apps/api && php artisan test --filter=AnalyticsTest` — must stay PASS

18. Commit:
    `git add apps/api/app/Services/AnalyticsService.php apps/api/tests/Feature/AnalyticsInsightServiceTest.php`
    `git commit -m "feat(analytics): add insight aggregate composition"`
    If Step 17 extracted helpers, commit them separately as `refactor(analytics): extract delta helpers`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Story 1 (Rules 1.1, 1.2),
  Story 2 (Rules 2.1, 2.2), Story 3 (Rules 3.1, 3.2) GWT scenarios used as verification.
apps/api/app/Services/AnalyticsService.php — existing private helpers `orderQuery`, `paymentQuery`,
  `outstandingOrderQuery`, `salesTrends`, `outletPerformance`, `periodKey`, `decimalToCents`,
  `moneyFromCents`; consts `MAX_DATE_RANGE_DAYS`, `OUTLET_PERFORMANCE_LIMIT`,
  `EXCLUDED_ORDER_STATUSES`, `OUTSTANDING_ORDER_STATUSES`.
apps/api/tests/Feature/AnalyticsTest.php — `RefreshDatabase`, `User::factory()->admin()`, `order()` /
  `payment()` helpers that backdate `created_at` via `DB::table(...)->update()`.
Carbon docs (/briannesbitt/carbon) — `copy()`, `subDays()`, `startOfDay()`, `endOfDay()`, `gt()`.

## WHY THIS APPROACH
Complexity: standard
Justification: 1 implementation file but non-trivial branching (two windows, cents thresholds, deterministic
  ordering, zero-fill). Novel composition logic → promoted from lightweight to standard.

## SANDWICH CONTEXT
[CRITICAL: Do NOT change the behavior of `dashboard()`, `salesTrends()`, or `outletPerformance()` — the
Dashboard contract and `AnalyticsTest.php` must stay green. All money/percentage math goes through the
existing `decimalToCents`/`moneyFromCents` path.]
You are implementing the backend insight aggregate for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A — extend `AnalyticsService` with a public `insight()` method, reuse private helpers.
Files in scope: `apps/api/app/Services/AnalyticsService.php`,
  `apps/api/tests/Feature/AnalyticsInsightServiceTest.php`
Test framework: PHPUnit via `php artisan test`, sqlite :memory:, `RefreshDatabase`.
Available after: none (prereq).
Architecture rule: bounded aggregates; no new dependency; no decimal library.
[RESTATE: Do NOT change the behavior of `dashboard()`, `salesTrends()`, or `outletPerformance()` — the
Dashboard contract and `AnalyticsTest.php` must stay green.]

## DELIVERABLE
Given today is 2026-09-18, When `insight()` runs, Then period = 2026-08-20..2026-09-18 and
  previous_period = 2026-07-21..2026-08-19
Given previous "100.00", current "120.00", Then delta 20.0 / direction "up"
Given previous "100.00", current "80.00", Then delta -20.0 / direction "down"
Given previous "0.00", current "50.00", Then delta null / direction "neutral"
Given previous "100.00", current "100.00", Then delta 0.0 / direction "neutral"
Given only "Cancelled" orders in previous window, Then previous sales "0.00"
Given decline "1000.00"→"700.00", Then needs_attention has reason "sales_decline", delta -30.0
Given decline "1000.00"→"800.00" (exactly -20%), Then INCLUDED
Given decline "1000.00"→"800.04", Then EXCLUDED
Given point-in-time outstanding "5000.00" (order 90 days old), Then reason "outstanding_risk"
Given prev "0.00", current "500.00", no outstanding, Then EXCLUDED
Given 8 qualifying outlets, Then needs_attention has exactly 5
Given decline -35% and outstanding 900000, Then the decline item precedes the outstanding item
Given "Alpha"/"Beta" equal severity, Then "Alpha" precedes "Beta"
Given an outlet meets both reasons, Then it appears once with reason "sales_decline"
Given orders on 12 of 30 days, Then sales_trends has exactly 30 buckets (zero-filled)
Given 15 outlets, Then outlet_performance_total 15, outlet_performance_has_more true, 10 ranked rows
Given 7 outlets, Then outlet_performance_total 7 and outlet_performance_has_more false
[must-not] Given previous "0.00", current "50.00", Then delta must NOT be 100.0
[must-not] Given any input, Then `dashboard()` output must NOT change

All tests PASS. `AnalyticsTest.php` PASS. Commit exists with message matching
`feat(analytics): add insight aggregate composition`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Exactly 30 zero-filled daily buckets for the fixed 30-day window
  - Threshold evaluated on integer cents (exactly −20% inclusive)
  - Direction derived from the unrounded delta
  - Deterministic ordering (decline-first, then outstanding, tie-break name→id)
  - Cap 5, both-reasons collapsed to one row
  - `outlet_performance_has_more` = total > OUTLET_PERFORMANCE_LIMIT exposed in the payload
  - Tests written BEFORE implementation (TDD — not after)
  - Rule of three enforced — no logic duplicated 3+ times in the file in scope
  - Commit message follows conventional commits format

Must-not-have:
  - Any change to `dashboard()`, `salesTrends()`, or `outletPerformance()` output
  - A second money/rounding path (no `floatval` arithmetic on money, no decimal library)
  - Adding `start_date`/`end_date` parameters to `insight()`
  - ML/LLM, narrative generation
  - Modifications to files outside the listed scope

Open question risks:
  - `outlets_total`/`products_total` intentionally carry no delta → if wrong: report NEEDS_CONTEXT
  - id-ID copy is a frontend concern, not this task's → do not add labels here

Rollback note:
  - Rollback = revert the `insight()` method; no schema/migration involved.

Red flags:
  - `AnalyticsTest.php` failing → STOP (Dashboard path regression)
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, `AnalyticsInsightServiceTest` + `AnalyticsTest` green, commit created
Uncertain when: the no-delta assumption for count metrics proves wrong
Escalate when: `dashboard()`/Dashboard contract would have to change, or out-of-scope files are needed

---

### Task 2: Backend endpoint + route + isAdminOrOwner gate [depends: T1]

## OBJECTIVE
Expose `GET /api/analytics/insight` via a new `AnalyticsController::insight()` action that authorizes
`admin` OR `platform_owner` through `FinanceAuthorizationService::isAdminOrOwner()` and returns the
`{status:'success', data:<insight payload>}` envelope.

Files:
- Modify: `apps/api/app/Http/Controllers/AnalyticsController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/AnalyticsInsightEndpointTest.php` (new)

Steps:
1. Write failing test for: **route + auth gate**
   Test file: `apps/api/tests/Feature/AnalyticsInsightEndpointTest.php`
   Level: integration (HTTP)

   Test intent:
   Given an active admin is authenticated
   When  GET /api/analytics/insight
   Then  status 200, body.status "success", body.data.comparison.period.end_date is today's date
   And Given an active platform_owner → 200
   And Given an authenticated outlet user → 403 with body.status "error"
   And Given no token → 401
   And Given a platform_owner with `is_active` false → 403

   Exercise through: the HTTP boundary (`$this->getJson('/api/analytics/insight', $headers)`).
   Test doubles: none — real `RefreshDatabase`, real login endpoint for tokens.
   Expected RED: route not defined → 404.

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=AnalyticsInsightEndpointTest`
   Expected failure: 404 (route not found)

3. Implement minimal code:
   Files: `apps/api/app/Http/Controllers/AnalyticsController.php`, `apps/api/routes/api.php`
   Implement: `public function insight(Request $request, FinanceAuthorizationService $auth): JsonResponse`
   that returns a 403 `{status:'error', message:...}` envelope unless
   `$auth->isAdminOrOwner($request->user())`, then `$this->analyticsService->insight(now())` wrapped in
   the success envelope. Register `Route::get('/analytics/insight', [AnalyticsController::class, 'insight']);`
   next to the existing `/analytics/dashboard` route. Do NOT add `start_date`/`end_date` validation —
   the window is fixed.

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AnalyticsInsightEndpointTest`
   Expected: PASS

5. Add a **characterization/regression test** (NOT a RED cycle — no failing claim):
   Test file: `apps/api/tests/Feature/AnalyticsInsightEndpointTest.php`
   Level: integration

   Test intent:
   Given the same auth, GET /api/analytics/dashboard still returns its existing
         `metrics`/`sales_trends`/`outlet_performance` shape with no `metrics_delta` key
   And   GET /api/analytics/insight is unaffected by `?start_date=2020-01-01&end_date=2020-01-31`

   Exercise through: HTTP boundary for both endpoints.
   Test doubles: none.
   Note: expected to PASS immediately — the fixed-window behavior was implemented in Step 3 and the
   Dashboard contract is already pinned by `AnalyticsTest`. Its purpose is to lock the contract, not to
   drive new code. Do NOT present it as a failing test.

6. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=AnalyticsInsightEndpointTest`
   Expected: PASS (characterization test passes immediately by design)

7. Refactor while green (bounded):
   - If the 403 envelope is duplicated across `dashboard()` and `insight()`, extract a private
     `deniedAnalyticsResponse(): JsonResponse` on the controller.
   - Re-run: `cd apps/api && php artisan test --filter=AnalyticsInsightEndpointTest` — must stay PASS
   - Re-run regression: `cd apps/api && php artisan test --filter=AnalyticsTest` — must stay PASS

8. Commit:
    `git add apps/api/app/Http/Controllers/AnalyticsController.php apps/api/routes/api.php apps/api/tests/Feature/AnalyticsInsightEndpointTest.php`
    `git commit -m "feat(analytics): expose GET /api/analytics/insight for admin and owner"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Story 5 (Rule 5.1) GWT
  scenarios used as verification.
apps/api/app/Http/Controllers/AnalyticsController.php — existing `dashboard()` gate uses raw
  `$request->user()->isAdmin()`; new action must use the superset helper instead.
apps/api/app/Services/FinanceAuthorizationService.php — `isAdminOrOwner()` = `isAdmin() || isPlatformOwner()`,
  each `hasCurrentRole()` including an `is_active` check.
apps/api/routes/api.php — `Route::get('/analytics/dashboard', ...)` sits at the "Owner analytics routes" block.
apps/api/tests/Feature/AnalyticsTest.php — `adminHeaders()` helper pattern for authenticated requests.

## WHY THIS APPROACH
Complexity: standard
Justification: 3 files (controller, route, test) with an authorization boundary change; touches a route
  contract → standard.

## SANDWICH CONTEXT
[CRITICAL: Authorize via `FinanceAuthorizationService::isAdminOrOwner()` — NOT raw `isAdmin()`. Do NOT
change the existing `dashboard()` gate behavior for its own tests, and do NOT add date-range params to
the insight endpoint.]
You are implementing the HTTP endpoint for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A — new `AnalyticsController::insight()` action on a new route.
Files in scope: `apps/api/app/Http/Controllers/AnalyticsController.php`, `apps/api/routes/api.php`,
  `apps/api/tests/Feature/AnalyticsInsightEndpointTest.php`
Test framework: PHPUnit via `php artisan test`, sqlite :memory:, `RefreshDatabase`.
Available after: T1 (`AnalyticsService::insight()`).
Architecture rule: `{status,data}` envelope; RBAC core untouched; reuse existing authz service.
[RESTATE: Authorize via `FinanceAuthorizationService::isAdminOrOwner()` — NOT raw `isAdmin()`.]

## DELIVERABLE
Given active admin, When GET /api/analytics/insight, Then 200 with `status:"success"`
Given active platform_owner, When GET /api/analytics/insight, Then 200
Given outlet user, When GET /api/analytics/insight, Then 403 with `status:"error"`
Given no token, When GET /api/analytics/insight, Then 401
Given inactive platform_owner, When GET /api/analytics/insight, Then 403
Given `?start_date&end_date` present, When GET /api/analytics/insight, Then the fixed 30-day window is used
[must-not] Given any request, Then `/api/analytics/dashboard` response shape must NOT change

All tests PASS. `AnalyticsTest.php` PASS. Commit exists with message matching
`feat(analytics): expose GET /api/analytics/insight for admin and owner`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `isAdminOrOwner()` gate (includes the `is_active` guard)
  - `{status:'success'|'error', data|message}` envelope consistent with the codebase
  - Fixed 30-day window; date params ignored
  - Tests written BEFORE implementation (TDD — not after); the Dashboard-contract test in Step 5 is an
    explicit characterization/regression carve-out (locks existing behavior, no failing claim)
  - Commit message follows conventional commits format

Must-not-have:
  - Raw `isAdmin()`-only gate on the new endpoint
  - Any modification to `dashboard()`'s response contract
  - Date-range validation on the insight endpoint
  - Modifications to files outside the listed scope

Open question risks:
  - None blocking. `platform_owner` semantics come from the existing service.

Rollback note:
  - Rollback = remove the route + action; no schema/migration involved.

Red flags:
  - `AnalyticsTest.php` failing → STOP
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, both test files green, commit created
Uncertain when: authz semantics for `platform_owner` differ from the spec
Escalate when: RBAC core would have to change

---

### Task 3: Dummy analyticsInsight fixture + window split [prereq] [parallel: T1]

## OBJECTIVE
Add an `analyticsInsight` aggregate to the dummy graph matching the `/analytics/insight` contract, and
split the existing 61-date dummy window into current-30 + previous-30 slices — without modifying
`dummyWindow()`.

Files:
- Modify: `apps/web/src/dummy/aggregates.ts`
- Test: `apps/web/src/dummy/aggregates.test.ts`

Steps:
1. Write failing test for: **fixture shape + window split**
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit

   Test intent:
   Given a `FullDummy` built with `today = new Date('2026-02-14T10:00:00+07:00')`
   When  `dummy.analyticsInsight` is read
   Then:
   - `comparison.period` = {start_date: "2026-01-16", end_date: "2026-02-14"}
   - `comparison.previous_period` = {start_date: "2025-12-17", end_date: "2026-01-15"}
   - `sales_trends` has exactly 30 entries
   - `metrics_delta` has keys orders_total, sales_total, payments_total, outstanding_total and
     NOT outlets_total/products_total
   - `outlet_performance_total` is a number ≥ `outlet_performance.length`
   - every `needs_attention` item has `reason` in {"sales_decline","outstanding_risk"} and length ≤ 5
   - the fixture deterministically contains **at least one** `needs_attention` row for the pinned
     `FIXED_TODAY` (so downstream dummy-render tests are not vacuous on an empty strip)

   Exercise through: `buildAggregates(master, tx)` / `buildFullDummy(today)` public output.
   Test doubles: none.
   Expected RED: `analyticsInsight` is undefined on the aggregates object.

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/aggregates.test.ts`
   Expected failure: `analyticsInsight` undefined

3. Implement minimal code:
   File: `apps/web/src/dummy/aggregates.ts`
   Implement: an `AnalyticsInsightData` interface + `buildAnalyticsInsight(master, tx, window)` deriving the
   current slice `[end−29 .. end]` and previous slice `[end−59 .. end−30]` from the existing 61-date window
   (via `daysBetween`), computing the same delta/needs_attention/zero-fill shapes as the API contract, and
   adding `analyticsInsight: buildAnalyticsInsight(master, tx, window)` to the returned `Aggregates` object.
   Leave `dummyWindow()` in `dates.ts` untouched — only *slice* its 61 dates here. Ensure the generated
   transactions (deterministic seeded RNG) yield at least one qualifying `needs_attention` outlet for the
   pinned date; if the raw generator does not naturally produce one, derive it deterministically from the
   existing outlet sales spread (do NOT weaken the criteria or hard-code a fake row).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/aggregates.test.ts`
   Expected: PASS

5. Refactor while green (bounded):
   - Rule of three: if money/percent formatting repeats 3+ times, reuse the existing `money()` helper in
     the file rather than adding a new local copy.
   - Re-run: `cd apps/web && npx jest src/dummy/aggregates.test.ts` — must stay PASS
   - Re-run neighbors: `cd apps/web && npx jest src/dummy` — must stay PASS

6. Commit:
   `git add apps/web/src/dummy/aggregates.ts apps/web/src/dummy/aggregates.test.ts`
   `git commit -m "feat(dummy): add analyticsInsight fixture with split comparison window"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Story 6 (Rule 6.1) GWT
  scenarios used as verification.
apps/web/src/dummy/aggregates.ts — `interface Aggregates`, `buildAnalytics`, `buildDashboardAdmin`,
  `money()` helper, `buildAggregates(master, tx)` deriving window from tx min/max dates.
apps/web/src/dummy/dates.ts — `dummyWindow(today)` returns 61 dates (`end−60 .. end`); `daysBetween`.
apps/web/src/dummy/index.ts — `buildFullDummy(today)`; `FullDummy` intersection type.
apps/web/__tests__/dummy-mode.e2e.test.tsx — pins `FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00')`.

## WHY THIS APPROACH
Complexity: standard
Justification: one implementation file, but mirrors a multi-branch backend contract and adds a new
  interface to a shared type — requires cross-file coordination.

## SANDWICH CONTEXT
[CRITICAL: Do NOT change `dummyWindow()` or `daysBetween()` behavior — other dummy aggregates and their
tests depend on the 61-date window. Only add a new aggregate and slice dates inside it.]
You are implementing the dummy-mode fixture for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A; dummy parity is a hard release contract.
Files in scope: `apps/web/src/dummy/aggregates.ts`, `apps/web/src/dummy/aggregates.test.ts`
Test framework: Jest (jsdom) via `npx jest`, `@/` path alias.
Available after: none (prereq) — runs parallel with T1.
Architecture rule: dummy parity — every new API field needs a dummy fixture.
[RESTATE: Do NOT change `dummyWindow()` or `daysBetween()` behavior.]

## DELIVERABLE
Given dummyWindow end "2026-02-14", Then current slice = 2026-01-16..2026-02-14 and
  previous = 2025-12-17..2026-01-15
Given the fixture, Then sales_trends has exactly 30 entries
Given the fixture, Then metrics_delta excludes outlets_total/products_total
Given the fixture, Then needs_attention length ≤ 5 with valid `reason` values and ≥ 1 row for FIXED_TODAY
Given the fixture, Then outlet_performance_total ≥ outlet_performance.length
[must-not] Given any dummy build, Then `dummyWindow()` output must NOT change

All tests PASS. `npx jest src/dummy` PASS. Commit exists with message matching
`feat(dummy): add analyticsInsight fixture with split comparison window`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `analyticsInsight` added to the `Aggregates` interface and the returned object
  - Exactly 30 zero-filled trend buckets in the fixture
  - Window split derived by slicing the existing 61 dates
  - Deterministically ≥ 1 `needs_attention` row for the pinned `FIXED_TODAY` (so dummy-render tests are non-vacuous)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Any modification to `dates.ts` / `dummyWindow()` / `daysBetween()`
  - Real network calls in dummy mode
  - Modifications to files outside the listed scope

Open question risks:
  - Dummy role is always admin → the fixture need not model non-admin states.
  - If a required field is absent from the spec contract → report NEEDS_CONTEXT.

Rollback note:
  - Rollback = remove the `analyticsInsight` fixture; dummy layer has no persistence.

Red flags:
  - Any `dates.ts` change → STOP
  - Other `src/dummy` tests failing → STOP

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, `npx jest src/dummy` green, commit created
Uncertain when: the insight contract shape differs from the spec
Escalate when: `dates.ts` would have to change

---

### Task 4: Frontend insight contract types + presentation helpers [prereq]

## OBJECTIVE
Create the shared insight TypeScript contract and pure presentation helpers (delta chip text/type,
trust-label copy) that the loader and page consume.

Files:
- Create: `apps/web/src/app/analytics/types.ts`
- Create: `apps/web/src/app/analytics/presentation.ts`
- Test: `apps/web/src/app/analytics/presentation.test.ts`

Steps:
1. Write failing test for: **delta chip formatting**
   Test file: `apps/web/src/app/analytics/presentation.test.ts`
   Level: unit

   Test intent:
   Given `{delta_percent: 20.0, direction: 'up'}`
   Then  the chip text is "+20,0%" and chip type "up"
   And Given `{delta_percent: -20.0, direction: 'down'}` → "-20,0%" / "down"
   And Given `{delta_percent: null, direction: 'neutral'}` → "belum ada pembanding" / "neutral"
   And Given `{delta_percent: 0.0, direction: 'neutral'}` → "0,0%" / "neutral"
   And Given `direction: 'up'` with `delta_percent: 0.0` → type must NOT be "up" (never show an up chip at 0,0%)

   Exercise through: the exported `formatDeltaChip()` function.
   Test doubles: none.
   Expected RED: module does not exist.

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/analytics/presentation.test.ts`
   Expected failure: cannot resolve module

3. Implement minimal code:
   Files: `apps/web/src/app/analytics/types.ts`, `apps/web/src/app/analytics/presentation.ts`
   Implement: `AnalyticsInsight` types mirroring the API contract (comparison, metrics, metrics_delta,
   sales_trends, outlet_performance, outlet_performance_total, outlet_performance_has_more,
   needs_attention) and
   `formatDeltaChip(delta): { text: string; type: 'up'|'down'|'neutral' }` using id-ID decimal comma and
   `parseMoney` from `@/lib/format` for numeric parsing — do NOT hand-roll a second number parser.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/app/analytics/presentation.test.ts`
   Expected: PASS

5. Write failing test for: **trust-label mapping + absent metadata**
   Test file: `apps/web/src/app/analytics/presentation.test.ts`
   Level: unit

   Test intent:
   Given `data_sufficiency.level = "insufficient"` → label "Data belum cukup"
   And `"limited"` → a distinct id-ID label; `"adequate"` → a distinct id-ID label
   And Given an unknown/undefined level → the helper returns null (label omitted)
   And Given a forecast `method` string → the helper returns it as the method label
   And Given a present `measurement` with a `note` → the helper returns the note text
   And Given absent `measurement` → the helper returns null (no "undefined" text)

   Exercise through: exported `trustLabel()` / `methodLabel()` / `measurementNote()` helpers.
   Test doubles: none.
   Expected RED: helpers missing.

6. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/analytics/presentation.test.ts`
   Expected failure: helper not exported

7. Implement minimal code:
   File: `apps/web/src/app/analytics/presentation.ts`
   Implement: `LEVEL_LABELS` map for `insufficient|limited|adequate` and `trustLabel(level?: string): string|null`
   plus `methodLabel(method?: string): string|null` and `measurementNote(measurement?: {measured?: boolean; note?: string}): string|null`,
   returning null for unknown/absent input.

8. Run test — verify PASS:
   `cd apps/web && npx jest src/app/analytics/presentation.test.ts`
   Expected: PASS

9. Refactor while green (bounded):
   - Rule of three: if numeric formatting repeats 3+ times, route it through `parseMoney`/`formatCount`
     from `@/lib/format` instead of a local copy.
   - Re-run: `cd apps/web && npx jest src/app/analytics/presentation.test.ts` — must stay PASS

10. Commit:
    `git add apps/web/src/app/analytics/types.ts apps/web/src/app/analytics/presentation.ts apps/web/src/app/analytics/presentation.test.ts`
    `git commit -m "feat(analytics): add insight contract types and presentation helpers"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Story 1 (Rule 1.2 delta
  chips), Story 4 (Rule 4.1 trust labels) GWT scenarios used as verification.
apps/web/src/app/analytics/api.ts — existing `DataSufficiency`/`Measurement` local types and `AIData` shape.
apps/web/src/components/ui/StatCard.tsx — `change` + `changeType: 'up'|'down'|'neutral'` contract the chip
  must satisfy.
apps/web/src/lib/format.ts — `parseMoney`, `formatCount`, `formatRupiah` — reuse instead of new parsers.

## WHY THIS APPROACH
Complexity: lightweight
Justification: 2 small new files, pure functions, no judgment beyond copy mapping. Tested in isolation.

## SANDWICH CONTEXT
[CRITICAL: The delta chip must satisfy `StatCard`'s `changeType: 'up'|'down'|'neutral'` contract exactly,
and direction must follow the API's `direction` field (already derived from the unrounded delta) so an
"up" chip can never render "0,0%".]
You are implementing the shared frontend insight contract for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A; shared contract extracted so loader and page agree.
Files in scope: `apps/web/src/app/analytics/types.ts`, `apps/web/src/app/analytics/presentation.ts`,
  `apps/web/src/app/analytics/presentation.test.ts`
Test framework: Jest (jsdom) via `npx jest`, `@/` path alias.
Available after: none (prereq).
Architecture rule: id-ID formatting; reuse `@/lib/format` — no second number parser.
[RESTATE: Direction must follow the API's `direction` field so an "up" chip can never render "0,0%".]

## DELIVERABLE
Given delta 20.0/up, Then chip "+20,0%" type "up"
Given delta -20.0/down, Then chip "-20,0%" type "down"
Given delta null, Then chip "belum ada pembanding" type "neutral"
Given delta 0.0, Then chip "0,0%" type "neutral"
Given level "insufficient", Then label "Data belum cukup"
Given a present measurement note, Then the note text is returned
Given unknown/absent level, Then label null (omitted)
Given absent measurement, Then no "undefined" text is produced
[must-not] Given direction "up" and delta 0.0, Then chip type must NOT be "up"

All tests PASS. Commit exists with message matching
`feat(analytics): add insight contract types and presentation helpers`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `AnalyticsInsight` type matches the backend contract field-for-field
  - Chip helper consumes `direction` from the API payload
  - id-ID decimal comma formatting
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - A second money/number parser beside `@/lib/format`
  - Hard-coded "undefined"/"null" label strings
  - Modifications to files outside the listed scope

Open question risks:
  - Exact id-ID wording for `limited`/`adequate` → if copy is wrong: DONE_WITH_CONCERNS (cosmetic only).

Rollback note:
  - Rollback = delete the two new modules; nothing else imports them until T5/T6.

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, commit created
Uncertain when: the insight contract shape differs from the spec
Escalate when: a shared type must change shape after T5/T6 begin (coordinate before proceeding)

---

### Task 5: Frontend insight loader with dummy guard [depends: T2, T3, T4]

## OBJECTIVE
Add `loadAnalyticsInsight(token)` to the analytics API module: real fetch of `GET /api/analytics/insight`,
guarded by `withDummyRead` so dummy mode performs zero network. The existing `loadAnalytics(token)` stays
untouched and continues to own the three `/ai/*` calls — the page calls both loaders independently so each
section can fail alone.

Files:
- Modify: `apps/web/src/app/analytics/api.ts`
- Test: `apps/web/src/app/analytics/api.test.ts` (new)

Steps:
1. Write failing test for: **real fetch path**
   Test file: `apps/web/src/app/analytics/api.test.ts`
   Level: integration (loader against a mocked `fetch`)

   Test intent:
   Given dummy mode is OFF and a token is stored
   When  `loadAnalyticsInsight(token)` is called
   Then  it fetches `/analytics/insight` exactly once and resolves the insight payload with
         `metrics_delta`, `sales_trends`, and `needs_attention` populated from the mocked response
   And Given the insight response has `status:"error"` → the call rejects with the server message

   Exercise through: the exported `loadAnalyticsInsight()`.
   Test doubles: global `fetch` mock, `getStoredToken` mock, `useDummyStore` state = OFF.
     Do NOT mock `withDummyRead` — it must run for real.
   Expected RED: `loadAnalyticsInsight` is not exported.

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/analytics/api.test.ts`
   Expected failure: `loadAnalyticsInsight is not a function`

3. Implement minimal code:
   File: `apps/web/src/app/analytics/api.ts`
   Implement: `loadAnalyticsInsight(token): Promise<AnalyticsInsight>` issuing a single
   `fetch(apiUrl('/analytics/insight'))`, checking the `{status,data}` envelope (throwing on non-ok or
   `status:"error"`), and returning `body.data`. Do NOT fold the three `/ai/*` calls into this function.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/app/analytics/api.test.ts`
   Expected: PASS

5. Write failing test for: **dummy path = zero network + parity**
   Test file: `apps/web/src/app/analytics/api.test.ts`
   Level: integration (loader ↔ dummy store)

   Test intent:
   Given dummy mode is ON with a `FullDummy` built for 2026-02-14
   When  `loadAnalyticsInsight(token)` is called
   Then  `global.fetch` is NEVER called
   And   the returned `sales_trends` has exactly 30 entries
   And   `metrics_delta` and `needs_attention` are present

   Exercise through: `loadAnalyticsInsight()` with a real `useDummyStore` toggled ON.
   Test doubles: `fetch` spy asserted never called. Do NOT mock the store, guards, or the dummy factory.
   Expected RED: loader hits the network in dummy mode, or insight fields are undefined.

6. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/analytics/api.test.ts`
   Expected failure: `fetch` was called (dummy short-circuit missing)

7. Implement minimal code:
   File: `apps/web/src/app/analytics/api.ts`
   Implement: pass `dummyEntities.analyticsInsight` as the `withDummyRead(isDummy, dummyValue, realFetch)`
   second argument so dummy mode returns before any fetch.

8. Run test — verify PASS:
   `cd apps/web && npx jest src/app/analytics/api.test.ts`
   Expected: PASS

9. Refactor while green (bounded):
   - Rule of three: if the `{status,data}` envelope check repeats 3+ times across loaders, extract a local
     `adminFetch<T>()`-style helper in this module (mirroring `lib/data-intelligence-api.ts`) rather than
     copy-pasting the check.
   - Re-run: `cd apps/web && npx jest src/app/analytics/api.test.ts` — must stay PASS

10. Commit:
    `git add apps/web/src/app/analytics/api.ts apps/web/src/app/analytics/api.test.ts`
    `git commit -m "feat(analytics): add guarded insight loader"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Story 6 (Rule 6.1 dummy
  parity) and Story 7 (Rule 7.1 scoped degradation, loader half) used as verification.
apps/web/src/app/analytics/api.ts — existing `loadAnalytics` with `withDummyRead`, 3-way `Promise.all`,
  `useDummyStore.getState()` read, `dummy.analytics` mapping.
apps/web/src/dummy/guards.ts — `withDummyRead(isDummy, dummyValue, realFetch)` short-circuits before network.
apps/web/src/lib/data-intelligence-api.ts — `adminFetch<T>()` envelope-check precedent.
apps/web/__tests__/dummy-mode.e2e.test.tsx — `setDummyGenerator`/`buildFullDummy` + `FIXED_TODAY` usage.

## WHY THIS APPROACH
Complexity: standard
Justification: cross-unit seam (loader ↔ dummy store ↔ backend contract); integration verification
  required, not just a unit assertion.

## SANDWICH CONTEXT
[CRITICAL: Dummy mode must perform ZERO network calls — the `withDummyRead` short-circuit must run before
any `fetch`. The insight request must be a single call to `/analytics/insight`; do NOT compose it from two
`/analytics/dashboard` calls.]
You are implementing the insight data loader for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A — one new endpoint; the page calls `loadAnalyticsInsight` and `loadAnalytics`
  independently so each section degrades on its own.
Files in scope: `apps/web/src/app/analytics/api.ts`, `apps/web/src/app/analytics/api.test.ts`
Test framework: Jest (jsdom) via `npx jest`, `@/` path alias.
Available after: T2 (endpoint contract), T3 (dummy fixture), T4 (types).
Architecture rule: `withDummyRead` guard; `{status,data}` envelope.
[RESTATE: Dummy mode must perform ZERO network calls.]

## DELIVERABLE
Given dummy OFF + token, When loadAnalyticsInsight, Then exactly one `/analytics/insight` fetch and the
  insight payload with `metrics_delta`/`sales_trends`/`needs_attention` populated
Given insight `status:"error"`, When loadAnalyticsInsight, Then the call rejects with the server message
Given dummy ON, When loadAnalyticsInsight, Then `fetch` is never called
Given dummy ON, Then `sales_trends.length === 30` and `metrics_delta`/`needs_attention` present
[must-not] Given dummy ON, Then any network request must NOT occur
[must-not] Given any call, Then `loadAnalyticsInsight` must NOT fetch the three `/ai/*` endpoints

All tests PASS. Commit exists with message matching `feat(analytics): add guarded insight loader`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `withDummyRead` short-circuit proven by a `fetch`-never-called assertion
  - Insight-only loader (the page calls `loadAnalytics` separately for AI) so sections can fail alone
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Folding the three `/ai/*` calls into `loadAnalyticsInsight` (that would couple the two sections'
    failure modes and break Rule 7.1)
  - Two `/analytics/dashboard` calls composed into insight
  - Network in dummy mode
  - Modifications to files outside the listed scope

Open question risks:
  - If the dummy fixture lacks a field the loader maps → report NEEDS_CONTEXT (T3 owns the fixture).

Rollback note:
  - Rollback = remove `loadAnalyticsInsight`; the existing `loadAnalytics` stays untouched.

Red flags:
  - `fetch` called while dummy ON → STOP
  - Existing `loadAnalytics` behavior changed → STOP

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, commit created
Uncertain when: the backend payload shape differs from `types.ts`
Escalate when: composing insight would require a second endpoint

---

### Task 6: Analytics page composition + scoped degradation + AI trust labels [depends: T5]

## OBJECTIVE
Rebuild `apps/web/src/app/analytics/page.tsx` into the strategic analytics home: metric strip with delta
chips, daily trend chart, top-outlet ranking with "dan N outlet lain", "Needs attention" strip, and the
retained AI cards with inline trust labels — each section degrading independently. The page calls
`loadAnalyticsInsight` and `loadAnalytics` as two independent loads so one can fail without the other.

Files:
- Create: `apps/web/src/components/analytics/MetricStrip.tsx`
- Create: `apps/web/src/components/analytics/NeedsAttention.tsx`
- Create: `apps/web/src/components/analytics/TrustLabel.tsx`
- Modify: `apps/web/src/app/analytics/page.tsx`
- Test: `apps/web/src/app/analytics/page.test.tsx` (new)
- Test: `apps/web/src/app/analytics/dummy-guard.test.tsx` (new — real-loader dummy cycle, no api mock)

Steps:
1. Write failing test for: **metric strip renders deltas**
   Test file: `apps/web/src/app/analytics/page.test.tsx`
   Level: integration (page + loader)

   Test intent:
   Given `loadAnalyticsInsight` resolves with sales delta 20.0/up, delta null for a zero-baseline metric,
     and 0.0/neutral for a flat metric
   When  `<AnalyticsPage />` renders
   Then  a "+20,0%" chip is visible, "belum ada pembanding" is visible, and "0,0%" is visible
   And   `outlets_total`/`products_total` render with NO delta chip

   Exercise through: rendering the real page component.
   Test doubles: mock `@/app/analytics/api` `loadAnalyticsInsight` (resolve) and `loadAnalytics` (resolve),
     plus `getStoredToken` and `next/navigation` if required. Do NOT mock the child components under test.
   Expected RED: page still renders only the 3 AI cards → chips absent.

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/analytics/page.test.tsx`
   Expected failure: "belum ada pembanding" / chip text not found

3. Implement minimal code:
   Files: `apps/web/src/components/analytics/MetricStrip.tsx`, `apps/web/src/app/analytics/page.tsx`
   Implement: `MetricStrip` mapping metrics → `StatCard` with `change`/`changeType` from
   `formatDeltaChip()`; wire it into the page above the AI cards.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/app/analytics/page.test.tsx`
   Expected: PASS

5. Write failing test for: **trend + ranking with "dan N outlet lain"**
   Test file: `apps/web/src/app/analytics/page.test.tsx`
   Level: integration

   Test intent:
   Given `outlet_performance_total` 15 with 10 ranked rows
   Then  the text "dan 5 outlet lain" is visible
   And Given `outlet_performance_total` 7
   Then  the closing line is absent
   And Given 30 trend points, Then the trend chart section renders

   Exercise through: rendering the page.
   Test doubles: as Step 1.
   Expected RED: closing line missing.

6. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/analytics/page.test.tsx`
   Expected failure: "dan 5 outlet lain" not found

7. Implement minimal code:
   Files: `apps/web/src/app/analytics/page.tsx` (reuse `SalesTrendChart`, `OutletPerformanceChart` from
   `@/components/Charts`)
   Implement: trend + ranking sections; compute `N = outlet_performance_total − outlet_performance.length`
   and render the closing line only when `N > 0`.

8. Run test — verify PASS:
   `cd apps/web && npx jest src/app/analytics/page.test.tsx`
   Expected: PASS

9. Write failing test for: **needs attention strip + AI trust labels**
   Test file: `apps/web/src/app/analytics/page.test.tsx`
   Level: integration

   Test intent:
   Given `needs_attention` has a "sales_decline" row and an "outstanding_risk" row
   Then  both are listed with the correct reason label and the outstanding row shows "total tunggakan"
   And Given `ai.recommendationMeta.data_sufficiency.level = "insufficient"`
   Then  the recommendations card shows "Data belum cukup"
   And Given `ai.recommendationMeta.measurement.note` is present, Then that note is visible
   And Given the forecast `method` is present, Then the method label is visible
   And Given `measurement` is absent, Then no "undefined" text appears

   Exercise through: rendering the page.
   Test doubles: as Step 1.
   Expected RED: needs-attention section + trust labels absent.

10. Run test — verify FAIL:
    `cd apps/web && npx jest src/app/analytics/page.test.tsx`
    Expected failure: "total tunggakan" / "Data belum cukup" not found

11. Implement minimal code:
    Files: `apps/web/src/components/analytics/NeedsAttention.tsx`,
    `apps/web/src/components/analytics/TrustLabel.tsx`, `apps/web/src/app/analytics/page.tsx`
    Implement: needs-attention list (empty state when none) and inline trust labels on the AI cards using
    `trustLabel()`/`methodLabel()`/`measurementNote()`; omit labels when the helper returns null.

12. Run test — verify PASS:
    `cd apps/web && npx jest src/app/analytics/page.test.tsx`
    Expected: PASS

13. Write failing test for: **scoped degradation both directions** `[derived — spec Rule 7.1 has GWT]`
    Test file: `apps/web/src/app/analytics/page.test.tsx`
    Level: integration

    Test intent:
    Given `loadAnalyticsInsight` rejects but `loadAnalytics` resolves
    Then  the insight sections show a scoped error and the AI cards still render
    And Given `loadAnalytics` rejects but `loadAnalyticsInsight` resolves
    Then  the insight sections render normally and the AI section shows a scoped error

    Exercise through: rendering the page with each loader mock rejecting independently.
    Test doubles: the two loader mocks (`loadAnalyticsInsight`, `loadAnalytics`) rejecting selectively.
      Do NOT mock the child components.
    Expected RED: a single error state blanks the whole page.

14. Run test — verify FAIL:
    `cd apps/web && npx jest src/app/analytics/page.test.tsx`
    Expected failure: whole page shows one error / AI cards absent

15. Implement minimal code:
    File: `apps/web/src/app/analytics/page.tsx`
    Implement: keep two independent loads — `loadAnalyticsInsight(token)` for the BI sections and
    `loadAnalytics(token)` for the AI cards — each with its own loading/error state, so neither can blank the
    other (no shared top-level error).

16. Run test — verify PASS:
    `cd apps/web && npx jest src/app/analytics/page.test.tsx`
    Expected: PASS

17. Write failing test for: **dummy-flag flip reloads insight through the real loader**
    `[cross-unit — spans T3 fixture + T5 loader + T6 page]`
    Test file: `apps/web/src/app/analytics/dummy-guard.test.tsx` (NEW, separate file)
    Level: integration (page ↔ real dummy store ↔ real loaders)

    Test intent:
    Given dummy mode is ON (real `useDummyStore` toggled on with a `FullDummy` built for 2026-02-14)
    When  `<AnalyticsPage />` renders with the REAL loaders (no `jest.mock('@/app/analytics/api')`)
    Then  a delta chip is visible and a `needs_attention` row is visible
    And   `global.fetch` is NEVER called during this ON render
    And   when the dummy flag flips OFF→ON via the store, the page re-renders the insight sections —
          proving `useDummyRefresh` reloads **insight** (today it only drives `loadAnalytics`)
    Discriminator (make the flip observable): during the OFF leg the `fetch` spy must record a call whose
      URL contains `/analytics/insight` (today it records only `/ai/*`), or the insight section must show a
      transient loading→loaded transition. Assert the discriminator — not merely re-asserting the chip/row,
      which are visible before and after and would pass even without the fix.

    Exercise through: rendering the real page with the real store + real loaders.
    Test doubles: `fetch` spy — asserted never called for the ON render, and stubbed to a benign resolved
      response for the flip phase (the OFF leg of OFF→ON legitimately fetches). localStorage for the token.
      Do NOT mock the store, the guards, the loaders, or the child components under test.
    Expected RED: the page's `useDummyRefresh` reloads only `loadAnalytics`, not `loadAnalyticsInsight`,
      so after the flag flip the insight sections are not refreshed — the flip assertion fails today.

18. Run test — verify FAIL:
    `cd apps/web && npx jest src/app/analytics/dummy-guard.test.tsx`
    Expected failure: after the flag flip the insight strip is not refreshed / stale (useDummyRefresh does
    not re-drive `loadAnalyticsInsight`).

19. Implement minimal code:
    File: `apps/web/src/app/analytics/page.tsx`
    Implement: register `useDummyRefresh` so a dummy-flag flip reloads BOTH sections — the insight load
    (`loadAnalyticsInsight`) as well as the AI load (`loadAnalytics`). Render an explicit empty-state for an
    empty `needs_attention`.

20. Run test — verify PASS:
    `cd apps/web && npx jest src/app/analytics/dummy-guard.test.tsx`
    Expected: PASS

21. Refactor while green (bounded):
    - Rule of three: if the section-card wrapper markup repeats 3+ times, extract a small local
      `Section` component inside the page module or `components/analytics/`.
    - `page.tsx` currently a single-line monolith — keep it readable, but do NOT extract beyond what the
      three repetitions justify.
    - Re-run: `cd apps/web && npx jest src/app/analytics/page.test.tsx src/app/analytics/dummy-guard.test.tsx` — must stay PASS
    - Re-run the dummy e2e suite that renders this page:
      `cd apps/web && npx jest __tests__/dummy-mode.e2e.test.tsx` — must stay PASS

22. Commit:
    `git add apps/web/src/components/analytics/ apps/web/src/app/analytics/page.tsx apps/web/src/app/analytics/page.test.tsx apps/web/src/app/analytics/dummy-guard.test.tsx`
    `git commit -m "feat(analytics): compose strategic analytics home with scoped degradation"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Stories 1, 2, 3, 4, 7
  (all GWT scenarios) used as verification.
apps/web/src/app/analytics/page.tsx — current single-line monolith rendering 3 AI cards + `LoginForm` gate.
apps/web/src/app/analytics/api.ts — `loadAnalytics` (retained, owns the three `/ai/*` calls) and new
  `loadAnalyticsInsight` (owns the single `/analytics/insight` call). The page consumes both independently.
apps/web/src/components/Charts.tsx — `SalesTrendChart`, `OutletPerformanceChart`, `TrendPoint`, `OutletPoint`.
apps/web/src/components/ui/index.ts — `StatCard`, `Badge`, `Card`, `EmptyState`, `PageHeader` exports.
apps/web/src/app/analytics/presentation.ts — `formatDeltaChip`, `trustLabel`, `methodLabel`, `measurementNote` (T4).
apps/web/src/dummy/guards.ts — `withDummyRead`, `useDummyRefresh` (the flag-flip reload seam).
apps/web/__tests__/dummy-mode.e2e.test.tsx — renders `AnalyticsPage` and asserts no empty-state strings.

## WHY THIS APPROACH
Complexity: deep
Justification: composes a cross-unit seam (page ↔ loader ↔ dummy fixture ↔ backend contract), introduces
  four independent degradation states, and rebuilds a monolith page — high judgment, broad blast radius.

## SANDWICH CONTEXT
[CRITICAL: A failure in the insight section must NOT blank the AI cards and vice versa — scoped
degradation is a hard requirement. The AI cards and their existing content must be preserved, only
repositioned and augmented with trust labels.]
You are implementing the analytics page composition for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A — Analytics home composing BI insight + retained AI cards.
Files in scope: `apps/web/src/components/analytics/MetricStrip.tsx`,
  `apps/web/src/components/analytics/NeedsAttention.tsx`,
  `apps/web/src/components/analytics/TrustLabel.tsx`,
  `apps/web/src/app/analytics/page.tsx`, `apps/web/src/app/analytics/page.test.tsx`,
  `apps/web/src/app/analytics/dummy-guard.test.tsx`
Test framework: Jest (jsdom) + @testing-library/react via `npx jest`, `@/` path alias.
Available after: T5 (loader).
Architecture rule: reuse `components/ui` + `components/Charts`; two independent loads; no drill-down.
[RESTATE: A failure in the insight section must NOT blank the AI cards and vice versa.]

## DELIVERABLE
Given sales delta 20.0/up, Then chip "+20,0%" visible
Given delta null, Then "belum ada pembanding" visible
Given delta 0.0, Then "0,0%" visible
Given count metrics, Then no delta chip rendered
Given outlet_performance_total 15, Then "dan 5 outlet lain" visible
Given outlet_performance_total 7, Then closing line absent
Given needs_attention rows, Then decline + outstanding rows listed, outstanding labeled "total tunggakan"
Given level "insufficient", Then "Data belum cukup" visible on the recommendations card
Given a present measurement note, Then the note text is visible on the card
Given absent measurement, Then no "undefined" text
Given insight fails + AI ok, Then AI cards still render and the insight sections show a scoped error
Given AI fails + insight ok, Then the strip renders and the AI section shows a scoped error
Given dummy ON, When the page renders, Then a delta chip and a needs-attention row are visible with zero
  network calls for that ON render
Given the dummy flag flips OFF→ON, Then the page reloads the insight sections via `useDummyRefresh`
[must-not] Given one section failing, Then the whole page must NOT blank
[must-not] Given any render, Then the page must NOT use a single shared error state for both loads

All tests PASS. `dummy-mode.e2e.test.tsx` PASS. Commit exists with message matching
`feat(analytics): compose strategic analytics home with scoped degradation`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Two independent loads (`loadAnalyticsInsight` + `loadAnalytics`) with scoped degradation (both directions proven)
  - Dummy-mode ON renders the insight sections through the real loader with zero network, and a dummy-flag
    flip reloads insight (cross-unit cycle in `dummy-guard.test.tsx`, no `jest.mock` of the loaders)
  - AI cards preserved (recommendations, segmentation, forecast) + trust labels added
  - Delta chips sourced from `formatDeltaChip()` (no inline percent math)
  - `N = total − ranked.length`, closing line only when N > 0
  - Tests written BEFORE implementation (TDD — not after)
  - Rule of three enforced for repeated section markup
  - Commit message follows conventional commits format

Must-not-have:
  - Drill-down / clickable rows / dynamic routes
  - Blanking the whole page on one section's failure
  - A second percent-formatting path beside `presentation.ts`
  - Modifications to files outside the listed scope

Open question risks:
  - Exact id-ID copy for outstanding ("total tunggakan") → cosmetic; DONE_WITH_CONCERNS if adjusted.
  - Trust-label copy wording → cosmetic.

Rollback note:
  - Rollback = restore the 3-card `page.tsx` and delete `components/analytics/`; loader/endpoint removal
    is independent (see spec Rollback Plan).

Red flags:
  - AI cards removed or their content altered → STOP
  - `dummy-mode.e2e.test.tsx` failing → STOP
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, `page.test.tsx` + `dummy-mode.e2e.test.tsx` green, commit created
Uncertain when: the loader payload shape differs from the contract
Escalate when: drill-down or a new route would be required to satisfy a scenario

---

### Task 7: Sidebar adminOnly + platform_owner full nav [prereq] [parallel: T1]

## OBJECTIVE
Mark the `/analytics` nav item `adminOnly` and treat `platform_owner` as admin-equivalent in the Sidebar
so `adminOnly` items (including Analitik) remain visible to owners.

Files:
- Modify: `apps/web/src/components/Sidebar.tsx`
- Test: `apps/web/src/components/Sidebar.test.tsx`

Steps:
1. Write failing test for: **analytics is adminOnly + owner sees adminOnly items**
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: unit (render)

   Test intent:
   Given the sidebar is rendered for role "outlet"
   Then  the "Analitik" link is NOT visible
   And Given the sidebar is rendered for role "platform_owner"
   Then  the "Analitik" link IS visible
   And Given role "platform_owner", Then another `adminOnly` item (e.g. "Data Intelligence") is visible
   And Given role "admin", Then all items remain visible (unchanged behavior)

   Exercise through: rendering `<Sidebar />` with the role-resolution seam the existing test uses.
   Test doubles: mock the role fetch (`fetchCurrentRole`) and localStorage `ddp_role`, as the existing
     `Sidebar.test.tsx` already does. Do NOT mock `NAV_ITEMS`.
   Expected RED: "Analitik" visible to outlet (not marked adminOnly), and platform_owner sees only
     non-adminOnly items.

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/components/Sidebar.test.tsx`
   Expected failure: outlet sees "Analitik", or platform_owner does not see adminOnly items

3. Implement minimal code:
   File: `apps/web/src/components/Sidebar.tsx`
   Implement: add `adminOnly: true` to the `{ href: '/analytics', ... }` NAV_ITEMS entry; change the
   visibility branch so `role === 'admin' || role === 'platform_owner'` selects the full `NAV_ITEMS`
   (finance branch stays as-is; everyone else keeps the `!item.adminOnly` filter).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/components/Sidebar.test.tsx`
   Expected: PASS

5. Refactor while green (bounded):
   - If the role branch grows, extract a `visibleItemsFor(role)` pure helper inside the module.
   - Re-run: `cd apps/web && npx jest src/components/Sidebar.test.tsx` — must stay PASS
   - Re-run neighbor: `cd apps/web && npx jest src/components/auth-role-offline.test.tsx` — must stay PASS

6. Commit:
   `git add apps/web/src/components/Sidebar.tsx apps/web/src/components/Sidebar.test.tsx`
   `git commit -m "feat(analytics): gate Analitik nav to admin and platform owner"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md — Story 5 (Rule 5.1 nav)
  GWT scenario used as verification.
apps/web/src/components/Sidebar.tsx — `NavItem` type has `adminOnly?`; `NAV_ITEMS` entry
  `{ href: '/analytics', label: 'Analitik', icon: '📈' }` (no `adminOnly` yet); `SidebarNavigation`
  currently branches `role === 'admin' ? NAV_ITEMS : NAV_ITEMS.filter(!adminOnly)`.
apps/web/src/components/Sidebar.test.tsx — existing role-mocking seam for render tests.

## WHY THIS APPROACH
Complexity: lightweight
Justification: 1 implementation file + 1 test file, a flag and a one-line branch change.

## SANDWICH CONTEXT
[CRITICAL: `platform_owner` must see `adminOnly` items — but the `finance` branch must remain exactly as
it is. Do NOT change any other nav item's visibility.]
You are implementing the navigation gate for Analitik Deeper Insight.
Spec: docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
Design decision: Option A; nav matches the backend `isAdminOrOwner()` gate.
Files in scope: `apps/web/src/components/Sidebar.tsx`, `apps/web/src/components/Sidebar.test.tsx`
Test framework: Jest (jsdom) + @testing-library/react via `npx jest`, `@/` path alias.
Available after: none (prereq).
Architecture rule: no auth/RBAC core changes — presentation-only nav filtering.
[RESTATE: `platform_owner` must see `adminOnly` items; the `finance` branch must remain unchanged.]

## DELIVERABLE
Given role "outlet", Then "Analitik" is NOT visible
Given role "platform_owner", Then "Analitik" IS visible
Given role "platform_owner", Then other `adminOnly` items are visible
Given role "admin", Then all items remain visible
Given role "finance", Then only finance items are visible (unchanged)
[must-not] Given role "finance", Then its item set must NOT change

All tests PASS. `auth-role-offline.test.tsx` PASS. Commit exists with message matching
`feat(analytics): gate Analitik nav to admin and platform owner`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `/analytics` marked `adminOnly`
  - `platform_owner` treated as admin-equivalent for `adminOnly` visibility
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Changes to the `finance` branch
  - Changes to any other nav item's visibility
  - Modifications to files outside the listed scope

Open question risks:
  - Whether `platform_owner` should be admin-equivalent globally (spec assumes yes) → if wrong: NEEDS_CONTEXT.

Rollback note:
  - Rollback = revert the `adminOnly` flag and the role branch; no persistence involved.

Red flags:
  - `finance` item set changed → STOP
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, commit created
Uncertain when: the global owner-as-admin assumption proves wrong
Escalate when: RBAC core would have to change

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Backend `AnalyticsService::insight()` | prereq | standard | 30-bucket zero-fill; cents-inclusive −20%; decline-first cap 5 |
| T2 | Endpoint + route + `isAdminOrOwner` gate | T1 | standard | owner 200 / outlet 403 / no-token 401; fixed window |
| T3 | Dummy `analyticsInsight` + window split | prereq | standard | current 01-16..02-14, previous 12-17..01-15; 30 buckets |
| T4 | Insight types + presentation helpers | prereq | lightweight | chip `+20,0%`/`0,0%`/`belum ada pembanding`; trust labels |
| T5 | Guarded insight loader | T2, T3, T4 | standard | dummy ON → zero network; real path → 1 insight fetch |
| T6 | Page composition + scoped degradation | T5 | deep | delta chips, "dan N outlet lain", per-section error isolation |
| T7 | Sidebar adminOnly + owner nav | prereq | lightweight | outlet hides Analitik; platform_owner sees adminOnly items |
