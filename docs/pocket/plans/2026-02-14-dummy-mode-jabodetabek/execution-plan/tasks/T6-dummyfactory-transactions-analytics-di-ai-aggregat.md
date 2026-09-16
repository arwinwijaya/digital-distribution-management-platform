# Task T6 — DummyFactory — transactions + analytics/DI/AI aggregates

**Phase:** 1
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


## OBJECTIVE
Build deterministic transactions (~900 orders across the 60-day window with items, 1:1 payment/invoice/delivery) plus ready-shaped aggregates: analytics (`AIData`: recommendations non-empty, 4-period forecast, segmentation), data-intelligence (`GeographicData` with 40–60 map_points, `SupplierPerformanceData`, `StockPlanningData`, measurement funnel/rates + forecast measurement), dashboard shapes (admin `DashboardData` + `FinanceMetrics`), and operations (`ReadinessData`, issues list). Pure functions over T5 master data; shapes typed to the EXISTING TS interfaces. Final composition: `buildFullDummy(today?) => DummyEntities` exported from `apps/web/src/dummy/index.ts` (extending T1's barrel) — this is the canonical `DummyGenerator` the store and Phase C call via `setDummyGenerator((today?) => buildFullDummy(fixedDate))`. Plus register the production default generator once at app entry: `installDummy()` in `apps/web/src/app/layout.tsx` so toggle works in production without tests.

Files:
- Create: `apps/web/src/dummy/factory-transactions.ts`
- Create: `apps/web/src/dummy/aggregates.ts`
- Create: `apps/web/src/dummy/install.ts` (export `installDummy()` which calls `setDummyGenerator((today?) => buildFullDummy(today))` — so layout.tsx never touches the factory directly)
- Modify: `apps/web/src/dummy/index.ts` (add `buildFullDummy` composition export — T1 created the barrel; T6 owns the composition)
- Modify: `apps/web/src/app/layout.tsx` (call `installDummy()` once at app bootstrap)
- Test: `apps/web/src/dummy/index.test.ts` (unit: composition returns non-empty DummyEntities)
- Test: `apps/web/src/dummy/factory-transactions.test.ts`
- Test: `apps/web/src/dummy/aggregates.test.ts`
- Test: `apps/web/src/dummy/install.test.ts` (integration: installDummy registers buildFullDummy on the real store singleton)

Steps:
1. Write failing test for: `buildFullDummy(today?)` composes master+transactions+aggregates into DummyEntities
   Test file: `apps/web/src/dummy/index.test.ts`
   Level: unit

   Test intent:
   Given fixed today (2026-02-14)
   When `buildFullDummy(new Date('2026-02-14'))` is called
   Then:
   - returns an object with keys `outlets`, `products`, `suppliers`, `territories` (non-empty arrays), `orders` (800..1000), `payments`, `invoices`, `deliveries` (1:1 with orders), `analytics` (recommendations non-empty), `geographic` (map_points 40..60)
   - calling twice with the same date yields deep-equal results (deterministic)

   Exercise through: `buildFullDummy` from `apps/web/src/dummy/index.ts`
   Test doubles: none (pure function, real T1/T5/T6)
   Expected RED: `index.ts` does not export `buildFullDummy` → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/index.test.ts --runInBand`
   Expected failure: `buildFullDummy is not a function` or missing

3. Implement — update `apps/web/src/dummy/index.ts`:
   ```ts
   export function buildFullDummy(today?: Date): DummyEntities {
     const d = today ?? new Date();
     const window = dummyWindow(d);
     const rng = createRng(DUMMY_SEED);
     const master = buildMasterData(rng, window);
     const tx = buildTransactions(master, window);
     const agg = buildAggregates(master, tx);
     return { ...master, ...tx, ...agg };
   }
   ```
   Export `DummyEntities` type (union of T5 `MasterData` + T6 `Transactions` + T6 `Aggregates`).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/index.test.ts --runInBand`

5. Write failing test for: transaction volumes + relational integrity + 60-day window
   Test file: `apps/web/src/dummy/factory-transactions.test.ts`
   Level: unit

   Test intent:
   Given T5 `MasterData` + fixed today via T1 utils
   When `buildTransactions(master, window)` runs
   Then:
   - order count is 800..1000 spanning the full 60-day window (min date ≥ start, max date ≤ end)
   - every order references an existing outlet id + ≥1 item referencing an existing product id
   - every order has exactly one payment, one invoice, one delivery (1:1), all referencing the order id
   - two runs are deep-equal (determinism)

   Exercise through:
   - `buildTransactions(master, window)` from `apps/web/src/dummy/factory-transactions.ts`

   Test doubles:
   - mock/fake: none (real T5 master data via `buildMasterData` with fixed today)
   - do NOT mock: factory-transactions, T5 factory, T1 utils

   Expected RED:
   - file does not exist → import error

6. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/factory-transactions.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/factory-transactions'`

7. Implement minimal code to satisfy the test (same file; trend-shaped daily volumes ~15/day with weekday/weekend modulation, NOT uniform noise — charts must show meaningful patterns).

8. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/factory-transactions.test.ts --runInBand`
   Expected: PASS

9. Write failing test for: aggregates populated + never-empty + typed to existing interfaces
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit

   Test intent:
   Given master + transactions from the steps above
   When `buildAggregates(master, tx)` runs
   Then:
   - `analytics.recommendations.length > 0` AND `forecast.predictions.length === 4` AND `segmentation.segments.length > 0`
   - `geographic.map_points.length` is 40..60 AND all inside JABODETABEK bbox AND `table.length === 5` (one row per territory)
   - `suppliers.suppliers.length > 0`, `stock.items.length > 0`, `measurement.recommendations.funnel.length > 0` with rates 0..1
   - `dashboardAdmin.metrics.orders_total === tx.orders.length` (aggregate, not hardcoded)
   - `dashboardFinance` has ALL FinanceMetrics keys: issued_invoices, outstanding_balance, overdue_rate, collection_time, payment_status_breakdown, reminders
   - `operations.issues.length > 0`, `readiness` non-null
   - TypeScript compiles against the existing interfaces (`tsc --noEmit` passes)

   Exercise through:
   - `buildAggregates(master, tx)` from `apps/web/src/dummy/aggregates.ts`

   Test doubles:
   - mock/fake: none
   - do NOT mock: aggregates, factories

   Expected RED:
   - file does not exist → import error; OR aggregates empty (e.g. `recommendations: []`) → length assertions fail

10. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/aggregates.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/aggregates'` (or empty-array assertions)

11. Implement minimal code to satisfy the test: derive aggregates FROM the transactions (counts, sums, per-territory grouping, per-supplier fulfillment ratios, funnel derived from order states, forecast = last-4-weeks extrapolation with `method: 'dummy-heuristic'`, stock = demand vs lead-time math). Import existing types from `@/lib/data-intelligence-api`, `@/lib/operations-types`, and the page-local types where canonical (copy the minimal shape locally ONLY if the page type is not exported — prefer importing).

12. Run tests — verify PASS:
   `cd apps/web && npx jest src/dummy/factory-transactions.test.ts src/dummy/aggregates.test.ts --runInBand && npx tsc --noEmit`
   Expected: PASS + clean typecheck

13. Write failing test for: `installDummy()` registers the canonical generator on the store
   Test file: `apps/web/src/dummy/install.test.ts`
   Level: integration (touches the real Zustand singleton via `setDummyGenerator`)

   Test intent:
   Given a fresh store singleton and `installDummy` NOT yet called
   When `installDummy()` runs, then `useDummyStore.getState().toggle()` is called
   Then:
   - entities become non-null with outlets/products/orders/map_points populated (real `buildFullDummy`, no stub)
   - calling `toggle()` OFF → ON again reproduces the same data (generator registered once, deterministic)

   Test doubles: none (real buildFullDummy + real store; fixed via `new Date()` is acceptable since this only asserts non-empty + determinism, not exact dates)
   Expected RED: no generator registered → `toggle()` throws or entities stay empty

14. Run test — verify FAIL, then implement `install.ts` + `layout.tsx` bootstrap, then verify PASS:
   `cd apps/web && npx jest src/dummy/install.test.ts --runInBand`

15. Refactor while green (bounded) + re-run (must stay PASS).

16. Commit (single commit for the task; aggregates + transactions + wiring ship together):
   `git add apps/web/src/dummy/factory-transactions.ts apps/web/src/dummy/aggregates.ts apps/web/src/dummy/install.ts apps/web/src/dummy/index.ts apps/web/src/app/layout.tsx apps/web/src/dummy/factory-transactions.test.ts apps/web/src/dummy/aggregates.test.ts apps/web/src/dummy/index.test.ts apps/web/src/dummy/install.test.ts`
   `git commit -m "feat(dummy): add transactional factory, aggregates, and install wiring"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R3/R4/R5 (analytics non-empty + 4-period forecast; geographic 40–60 + suppliers + stock + measurement; BOTH dashboard shapes incl. FinanceMetrics keys); Story 2 geo bbox; Story 3 R3 (order → payment/invoice/delivery side-effects — the 1:1 linkage built here is what T11 mutates).
Preflight: `lib/data-intelligence-api.ts` exports `GeographicData`, `SupplierPerformanceData`, `StockPlanningData`, `RecommendationMeasurementData`, `ForecastMeasurementData`; `lib/operations-types.ts` exports `ReadinessData`, `OperationIssue`; page-local types: analytics `AIData`, dashboard `Metrics`/`FinanceMetrics`.

## WHY THIS APPROACH
Justification: Transactions + aggregates are one bounded deliverable (aggregates are pure derivations of the transactions — splitting them produces a task that cannot fail independently). Typing to existing interfaces is the anti-drift mechanism (spec assumption: no Zod).
Complexity: deep

## SANDWICH CONTEXT
[CRITICAL: Aggregates must be DERIVED from the transactions (counts/sums/groupings) — never hardcoded numbers; analytics generators must NEVER return empty arrays]
You are implementing transactions + aggregates for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/factory-transactions.ts`, `apps/web/src/dummy/aggregates.ts`, `apps/web/src/dummy/install.ts`, `apps/web/src/dummy/index.ts`, `apps/web/src/app/layout.tsx`, `apps/web/src/dummy/factory-transactions.test.ts`, `apps/web/src/dummy/aggregates.test.ts`, `apps/web/src/dummy/index.test.ts`, `apps/web/src/dummy/install.test.ts` — no other files
Available after: T5 (master data); T1 utils
Architecture rule: Pure functions only for factory-transactions/aggregates (no store/fetch/Date.now inside — inject today). The only store touch is `install.ts` (registers the canonical generator via `setDummyGenerator`); `layout.tsx` calls `installDummy()` once. Types imported from existing interfaces; `tsc --noEmit` must pass.
[RESTATE: Aggregates must be DERIVED from the transactions — never hardcoded numbers; analytics generators must NEVER return empty arrays]

## DELIVERABLE
Given master + fixed today, When buildTransactions runs, Then 800..1000 orders across the full 60-day window AND every order → existing outlet + products AND exactly one payment/invoice/delivery each AND deep-equal across runs
Given master + transactions, When buildAggregates runs, Then analytics recommendations non-empty AND forecast 4 periods AND segmentation non-empty AND map_points 40..60 in bbox AND table 5 rows AND suppliers/stock/measurement present AND BOTH dashboard shapes (incl. all FinanceMetrics keys) AND operations present AND tsc clean
Given fixed today, When `buildFullDummy(new Date('2026-02-14'))` is called, Then DummyEntities object with non-empty outlets/products/suppliers + 800..1000 orders + 40..60 map_points AND deterministic across calls (this is the composition exported from T1's barrel by T6)
Given app bootstrap, When `installDummy()` is called once, Then the store's generator is the real `buildFullDummy` AND toggling ON yields populated entities (production wiring — no stub)

All tests PASS. Commit exists with message matching `feat(dummy): add transactional factory, aggregates, and install wiring`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 1:1 order→payment/invoice/delivery linkage (T11 relies on this)
  - Trend-shaped volumes (weekday/weekend modulation), not uniform noise
  - No empty analytics arrays by construction
  - `tsc --noEmit` clean (shape-drift guard in lieu of Zod)
  - `buildFullDummy` composition works with no args (defaults to `new Date()`) and is the sole entry point the store/Phase C wires via `setDummyGenerator`
  - `installDummy()` registered once at app bootstrap (layout.tsx) so production toggle works without a test stub
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Hardcoded aggregate numbers (must derive from tx)
  - Store/fetch/network imports; Date.now() inside (inject today)
  - Backend changes; new dependencies

Open question risks:
  - Page-local types (analytics AIData, dashboard Metrics) may not be exported — if so, define minimal local mirrors in aggregates.ts and note the drift risk in the commit body

Rollback note:
  - Delete the two files + tests; T5 remains valid alone.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, typecheck clean, commit created
Uncertain when: n/a
Escalate when: aggregates import from files outside `apps/web/src/dummy/*` + the existing type modules, or task touches out-of-scope files
