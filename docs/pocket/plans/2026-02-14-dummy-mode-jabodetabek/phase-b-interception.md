# EXECUTION PLAN — Dummy Mode JABODETABEK: Phase B (Interception)

**Date:** 2026-02-14
**Spec:** docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
**Status:** draft
**Total tasks:** 4

---

## Execution Overview

### Recommended Order
```
Phase A complete (T6 aggregates + T7 guard helpers) → B-T1, B-T2, B-T3, B-T4 (all parallel)
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | B-T1, B-T2, B-T3, B-T4 | Phase A T6 + T7 complete |
| Group B | Phase C integration verification | All Phase B tasks complete |

### Constraints Reminder
**Architecture:** Touch ONLY `apps/web/src`. Every call-site guard MUST import `withDummyRead` / `commitIfCurrent` / `useDummyRefresh` from `apps/web/src/dummy/guards.ts` (Phase A T7) — reimplementing the check locally is a QUALITY BAR violation. DO NOT touch `apps/api`, `database/*`, `docker-compose.yml`. No new prod deps.
**Out-of-scope:** Backend/API endpoint changes; persisting dummy mutations across toggle-OFF/refresh; sharpening driver/sales role scopes in Sidebar; new prod dependencies.
**Assumptions at risk:** Best-effort discard at commit layer (no AbortController); operations dummy issue detail non-navigable; generators typed to existing TS interfaces (no Zod).
**Sequencing:** The real prerequisite is Phase A. Within this phase B-T4 shares files with B-T1/B-T2/B-T3 (see STOP CONDITIONS per task) — B-T4 must land AFTER the file it shares with has committed its read-guard. Either serialize (B-T1/B-T2/B-T3 commit-first, then B-T4) or explicitly sequence commits per-file so no two tasks write the same file concurrently.
**Test hygiene (all phases):** the dummy store is a module-level Zustand singleton and tests share jsdom `localStorage` — every new test file MUST `beforeEach` → `localStorage.clear()`, re-seed `ddp_token`, reset the store singleton, re-register the generator; `afterEach` → `jest.restoreAllMocks()`. Tests must never depend on a previous test's end-state. NOTE: DI read functions call `getStoredToken()` internally and `adminFetch` throws before any fetch if the token is absent — seed `ddp_token` in OFF-path regression tests or the RED fails for the wrong reason (auth throw, not a recorded fetch).

### File Structure Map
```
Story 2 — Read paths (DI + operations)
  Modify: apps/web/src/lib/data-intelligence-api.ts
  Modify: apps/web/src/lib/operations-api.ts
  Test:   apps/web/src/lib/dummy-guards-api.test.ts        (created by: B-T1)

Story 2 — Read paths (admin/* + sales/*)
  Modify: apps/web/src/app/admin/outlets/api.ts
  Modify: apps/web/src/app/admin/products/api.ts
  Modify: apps/web/src/app/admin/promotions/api.ts
  Modify: apps/web/src/app/admin/sales-performance/api.ts
  Modify: apps/web/src/app/admin/users/api.ts
  Modify: apps/web/src/app/sales/orders/api.ts
  Modify: apps/web/src/app/sales/performance/api.ts
  Test:   apps/web/src/app/admin/outlets/dummy-guard.test.ts   (created by: B-T2)

Story 2 — Read paths (inline-fetch pages)
  Create: apps/web/src/app/dashboard/api.ts                 (created by: B-T3)
  Create: apps/web/src/app/analytics/api.ts                 (created by: B-T3)
  Create: apps/web/src/app/payments/api.ts                  (created by: B-T3)
  Create: apps/web/src/app/delivery/api.ts                  (created by: B-T3)
  Create: apps/web/src/app/invoices/api.ts                  (created by: B-T3)
  Modify: apps/web/src/app/dashboard/page.tsx
  Modify: apps/web/src/app/analytics/page.tsx
  Modify: apps/web/src/app/payments/page.tsx   (READ path only; POST is B-T4)
  Modify: apps/web/src/app/delivery/page.tsx   (READ path only; PATCH is B-T4)
  Modify: apps/web/src/app/invoices/page.tsx
  Modify: apps/web/src/app/operations/page.tsx
  Modify: apps/web/src/app/sales/page.tsx      (READ path in B-T3; POST is B-T4)
  Modify: apps/web/src/components/ProductCatalog.tsx
  Modify: apps/web/src/components/MarketplaceCatalog.tsx
  Modify: apps/web/src/components/OrderForm.tsx (READ paths in B-T3: products `/products` :16 + tracking `GET /orders/:id` :36; POST :28 is B-T4)
  Test:   apps/web/src/app/dashboard/dummy-guard.test.tsx      (created by: B-T3)

Story 3 — Fake writes
  Create: apps/web/src/dummy/mutations.ts                   (created by: B-T4)
  Test:   apps/web/src/dummy/mutations.test.ts              (created by: B-T4)
  Modify: apps/web/src/app/sales/orders/api.ts              (write path — coordinate with B-T2)
  Modify: apps/web/src/app/payments/page.tsx                (POST payment — new in review fix)
  Modify: apps/web/src/components/OrderForm.tsx              (POST order — new in review fix)
  Modify: apps/web/src/app/sales/page.tsx                   (POST visit — new in review fix)
  Modify: apps/web/src/lib/data-intelligence-api.ts         (sendFunnelEvent POST — owned by B-T4)
```

Note: `(created by: B-T<N>)` annotations mark files that do not exist until B-T<N> runs. `apps/web/src/app/sales/orders/api.ts` is touched by two tasks — B-T2 owns the READ guard (`fetchSalesOutlets`, `fetchCatalogProducts`) and B-T4 owns the WRITE guard (`createSalesOrder`); they must commit in that order to avoid conflicts.

---

## Pocket Packets

---

### Task 1: Guard data-intelligence-api + operations-api [prereq]

## OBJECTIVE
Wire `isDummy` guards into every read function of `data-intelligence-api.ts` and `operations-api.ts`, returning Phase A T6 dummy aggregates and never calling the backend while ON.

Files:
- Modify: `apps/web/src/lib/data-intelligence-api.ts`
- Modify: `apps/web/src/lib/operations-api.ts`
- Test: `apps/web/src/lib/dummy-guards-api.test.ts`

Steps:
1. Write failing test for: DI reads return dummy aggregates with zero network while ON
   Test file: `apps/web/src/lib/dummy-guards-api.test.ts`
   Level: integration

   Test intent:
   Given the dummy store ON (populated with T6 aggregates via `setDummyGenerator`), `localStorage['ddp_token'] = 't-token'` seeded (otherwise `adminFetch` throws on auth BEFORE any fetch), and a `jest.fn()` global fetch
   When `fetchGeographicData()`, `fetchSupplierPerformanceData()`, `fetchStockPlanningData()`, `fetchRecommendationMeasurementData()`, `fetchForecastMeasurementData()` are each awaited
   Then:
   - each resolves to the dummy aggregate shape (map_points 40..60 for geographic; non-empty suppliers/items/funnel)
   - global fetch was NEVER called
   Given the store OFF and `ddp_token` present
   When `fetchGeographicData()` is awaited
   Then:
   - global fetch IS called with the `/admin/analytics/geographic` URL (existing behavior preserved)

   Suite hygiene: `beforeEach` must `localStorage.clear()` + `localStorage.setItem('ddp_token','t-token')` + reset the dummy store singleton + re-register the stub generator; `afterEach` restores fetch.

   Exercise through:
   - The exported fetch functions (public module boundary) — not `adminFetch`

   Test doubles:
   - mock/fake: global fetch (assert not called when ON); dummy generator stub feeding T6-shaped aggregates
   - do NOT mock: the API module functions, the guards, the store

   Expected RED:
   - every function calls `adminFetch` unconditionally → fetch mock recorded → assertion fails

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/lib/dummy-guards-api.test.ts --runInBand`
   Expected failure: `Expected fetch not to have been called`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/lib/data-intelligence-api.ts` — at the top of each read export, `if (selectIsDummy()) return withDummyRead(true, dummy.<x>, () => adminFetch<...>(...))`. NOTE: `sendFunnelEvent` is a WRITE (POST measurement event) — it is owned by Phase B B-T4, DO NOT guard it here.
   File: `apps/web/src/lib/operations-api.ts` — same pattern, but dummy results must be wrapped in the existing `FetchResult<T>` envelope (`{ ok: true, data, error: null, code: null, status: 200, raw: null }`) so page code is untouched.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/lib/dummy-guards-api.test.ts --runInBand`
   Expected: PASS

5. Write failing test for: operations reads (incl. issue detail) never hit backend while ON
   Test file: `apps/web/src/lib/dummy-guards-api.test.ts` (append)
   Level: integration

   Test intent:
   Given the store ON with dummy issues present, `ddp_token` seeded, and global fetch mocked
   When `fetchReadiness()`, `fetchIssues({})`, and `fetchIssueDetail('dummy-1')` are awaited
   Then:
   - each returns `ok: true` with non-null data
   - global fetch was NEVER called

   Exercise through:
   - the three exported operations functions

   Test doubles:
   - mock/fake: global fetch
   - do NOT mock: operations-api, guards, store

   Expected RED:
   - `fetchIssueDetail` still calls `/admin/operations/issues/:id` → fetch recorded

6. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/lib/dummy-guards-api.test.ts --runInBand`

7. Refactor while green (bounded) + re-run (must stay PASS).

8. Commit:
   `git add apps/web/src/lib/data-intelligence-api.ts apps/web/src/lib/operations-api.ts apps/web/src/lib/dummy-guards-api.test.ts`
   `git commit -m "feat(dummy): guard data-intelligence and operations read APIs"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R1 (guard per API function; zero backend network), R4 (DI populated), R6 (funnel faked); Story 3 D (dummy op-issue detail non-navigable).
Preflight: `data-intelligence-api.ts` uses `adminFetch<T>` (throws on error) and has `sendFunnelEvent` POST; `operations-api.ts` uses `FetchResult<T>` envelope with `parseErrorCode`/`parseErrorMessage`. Existing test `apps/web/src/lib/data-intelligence-types.test.ts` is in Jest's `testPathIgnorePatterns`.

## WHY THIS APPROACH
Justification: Two modules, one shared mechanism (T7 guards). Unit level suffices — the collaboration under test is module→store, both real; only the network is doubled.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Import the guards from `apps/web/src/dummy/guards.ts` — NEVER reimplement the isDummy check locally]
You are wiring dummy read guards for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/lib/data-intelligence-api.ts`, `apps/web/src/lib/operations-api.ts`, `apps/web/src/lib/dummy-guards-api.test.ts` — no other files
Available after: Phase A T6 (aggregates) + T7 (guards) complete
Architecture rule: OFF path must remain byte-identical (same URLs, same error handling). Operations dummies must keep the `FetchResult<T>` envelope so `apps/web/src/app/operations/page.tsx` needs no change.
[RESTATE: Import the guards from `apps/web/src/dummy/guards.ts` — NEVER reimplement the isDummy check locally]

## DELIVERABLE
Given store ON, When any DI read function is awaited, Then dummy aggregate returned AND zero fetch
Given store OFF, When any DI read function is awaited, Then real fetch with the original URL (no regression)
NOTE: sendFunnelEvent is B-T4's (write path) — NOT asserted here
Given store ON, When readiness/issues/issue-detail are awaited, Then `ok: true` with data AND zero fetch

All tests PASS. Commit exists with message matching `feat(dummy): guard data-intelligence and operations read APIs`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Guards imported from `@/dummy/guards` (no local reimplementation)
  - Operations dummy responses keep the `FetchResult<T>` envelope
  - `sendFunnelEvent` is NOT guarded here (B-T4 write path owns it) — avoids merge conflict on this file
  - `sendFunnelEvent` fake asserts its `FunnelEventPayload` shape (`event_uuid`, `event_type`, `outlet_id`, `product_id`) — not just truthiness
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - AbortController plumbing
  - Changing page components (B-T3 owns pages)
  - Backend changes; new dependencies

Open question risks:
  - Dummy op-issue detail is non-navigable by design → if a page links to `/operations/:dummyId`, T-C1 must catch it; do not add routing logic here

Rollback note:
  - Revert both module edits; delete the test. Phase A dummy data stays inert.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: a page component must change to satisfy this task (it belongs to B-T3) or out-of-scope files are touched

---

### Task 2: Guard admin/* + sales/* API modules [prereq]

## OBJECTIVE
Wire `isDummy` read guards into the seven admin/sales API modules so every read returns T5/T6 dummy data with zero network while ON.

Files:
- Modify: `apps/web/src/app/admin/outlets/api.ts`
- Modify: `apps/web/src/app/admin/products/api.ts`
- Modify: `apps/web/src/app/admin/promotions/api.ts`
- Modify: `apps/web/src/app/admin/sales-performance/api.ts`
- Modify: `apps/web/src/app/admin/users/api.ts`
- Modify: `apps/web/src/app/sales/orders/api.ts` (READ functions only)
- Modify: `apps/web/src/app/sales/performance/api.ts`
- Test: `apps/web/src/app/admin/outlets/dummy-guard.test.ts`

Steps:
1. Write failing test for: admin outlet reads return dummy with zero network while ON
   Test file: `apps/web/src/app/admin/outlets/dummy-guard.test.ts`
   Level: integration

   Test intent:
   Given the store ON (T5/T6 data via `setDummyGenerator`) and jest.fn() global fetch
   When `fetchAdminOutlets('token')`, `fetchOutletOrders('token', -1)`, and `fetchOutletSummary('token', -1)` are awaited
   Then:
   - `outlets.length` is 15 by default limit AND `hasMore === true` (pagination exercisable)
   - outlet orders and summary are non-null and reference a real dummy outlet id
   - global fetch was NEVER called
   Given the store OFF
   When `fetchAdminOutlets('token')` is awaited
   Then:
   - global fetch IS called with `/admin/outlets?...` (existing behavior)

   Suite hygiene: `beforeEach` → `localStorage.clear()` + `setItem('ddp_token','t-token')` + reset dummy store + re-register stub generator; `afterEach` → restore fetch.

   Exercise through:
   - exported functions of `apps/web/src/app/admin/outlets/api.ts`

   Test doubles:
   - mock/fake: global fetch; dummy generator stub with enough outlets to paginate
   - do NOT mock: the api module, guards, store

   Expected RED:
   - unconditional fetch → recorded → assertion fails

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/admin/outlets/dummy-guard.test.ts --runInBand`
   Expected failure: `Expected fetch not to have been called`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/app/admin/outlets/api.ts` — guard each read (`fetchAdminOutlets`, `fetchOutletOrders`, `fetchOutletSummary`) with `withDummyRead(...)` returning the EXISTING result shapes (`OutletsListResult`, `{orders, hasMore}`, `OutletSummary`). Also apply search/category/territory filters against dummy data so the filter UI is exercised (filter parity, not a passthrough).
   Files: same pattern for `admin/products/api.ts` (`fetchProducts`, `fetchPriceHistory`), `admin/promotions/api.ts` (`fetchPromotions`), `admin/sales-performance/api.ts` (`fetchAdminSalesPerformance`), `admin/users/api.ts` (`fetchAdminUsers`, `UsersListResult`), `sales/orders/api.ts` (`fetchSalesOutlets`, `fetchCatalogProducts` ONLY — writes are B-T4), `sales/performance/api.ts` (`fetchMyPerformance`).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/app/admin/outlets/dummy-guard.test.ts --runInBand`
   Expected: PASS

5. Write failing test for: EVERY remaining guarded module returns typed dummies offline (zero-fetch sweep)
   Test file: `apps/web/src/app/admin/outlets/dummy-guard.test.ts` (append)
   Level: integration

   Test intent:
   Given the store ON, `ddp_token` seeded, and global fetch mocked
   When ALL of the following are awaited in one test:
   `fetchAdminUsers('token', {})`, `fetchMyPerformance('token')`,
   `fetchProducts('token')`, `fetchPriceHistory('token', <productId>)`,
   `fetchPromotions('token')`, `fetchAdminSalesPerformance('token', {})`,
   `fetchSalesOutlets('token')`, `fetchCatalogProducts('token')`
   Then:
   - each resolves to its module's declared shape (`UsersListResult` with ≥1 user; `MyPerformance` with non-zero period totals; products array non-empty; price history array; promotions array; sales-performance object; outlets array; catalog products array)
   - global fetch was NEVER called ONCE across all eight calls (`expect(fetch).not.toHaveBeenCalled()`)

   This is the sweep that closes the coverage gap — DELIVERABLE says "any guarded admin/sales read", so every guarded module must appear here (not just three of seven).

   Exercise through:
   - exported functions of `admin/products/api.ts`, `admin/promotions/api.ts`, `admin/sales-performance/api.ts`, `admin/users/api.ts`, `sales/orders/api.ts` (reads), `sales/performance/api.ts`

   Test doubles:
   - mock/fake: global fetch
   - do NOT mock: api modules, guards, store

   Expected RED:
   - any of the eight hits the backend → fetch recorded → `not.toHaveBeenCalled()` fails

6. Run test — verify FAIL, then implement (same pattern, all remaining modules), then verify PASS:
   `cd apps/web && npx jest src/app/admin/outlets/dummy-guard.test.ts --runInBand`

7. Refactor while green (bounded) + re-run (must stay PASS).

8. Commit:
   `git add apps/web/src/app/admin/outlets/api.ts apps/web/src/app/admin/products/api.ts apps/web/src/app/admin/promotions/api.ts apps/web/src/app/admin/sales-performance/api.ts apps/web/src/app/admin/users/api.ts apps/web/src/app/sales/orders/api.ts apps/web/src/app/sales/performance/api.ts apps/web/src/app/admin/outlets/dummy-guard.test.ts`
   `git commit -m "feat(dummy): guard admin and sales read APIs"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R1 (every API function guarded; zero network), R2 (relational determinism); Story 2 R5 (finance role menus populated).
Preflight: `admin/outlets/api.ts` returns `OutletsListResult` with nested-envelope unwrapping (array vs `{data, has_more}`); `admin/products/api.ts` returns a bare array from `fetchProducts`; `admin/users/api.ts` returns `UsersListResult`; `sales/orders/api.ts` exports both read (`fetchSalesOutlets`, `fetchCatalogProducts`) and write (`createSalesOrder`) functions.

## WHY THIS APPROACH
Justification: Seven small modules, one mechanism. Grouped because each edit is 1–3 guard lines and splitting would create seven near-identical packets (over-split signal).
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: In `sales/orders/api.ts` guard ONLY the READ functions — `createSalesOrder` is B-T4's write path]
You are wiring dummy read guards for admin/sales modules in Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: the seven listed `api.ts` files + `apps/web/src/app/admin/outlets/dummy-guard.test.ts` — no other files
Available after: Phase A T6 + T7 complete
Architecture rule: Dummy returns must match each module's EXISTING return shape exactly (array vs envelope) so pages need no change. Filters (search/category/territory/limit/cursor) must apply to dummy data so filter behavior is verifiable.
[RESTATE: In `sales/orders/api.ts` guard ONLY the READ functions — `createSalesOrder` is B-T4's write path]

## DELIVERABLE
Given store ON, When ANY of the guarded admin/sales reads is awaited (3 in step 1 + 8 in the step 5 sweep = 11 total), Then correctly-shaped dummy returned AND global fetch never called (sweep assertion across all seven modules)
Given store ON with `search`/`category` filters, When `fetchAdminOutlets` runs, Then the filter is applied to dummy data (result subset changes)
Given store OFF, When any guarded read is awaited, Then real fetch with the original URL and error handling (no regression)

All tests PASS. Commit exists with message matching `feat(dummy): guard admin and sales read APIs`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Guards imported from `@/dummy/guards`
  - Return-shape parity per module (bare array vs envelope) so no page changes are required
  - Filter/pagination parameters applied to dummy data (not ignored)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Guarding `createSalesOrder` (B-T4 owns writes)
  - Editing pages/components (B-T3)
  - Backend changes; new dependencies

Open question risks:
  - `admin/products/api.ts#fetchProducts` returns a bare array while `fetchPriceHistory` uses a nested envelope — confirm both in the RED test before implementing

Rollback note:
  - Revert the seven module edits; delete the test.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: a module's real return shape differs from what was preflighted
Escalate when: a page must change (belongs to B-T3) or out-of-scope files are touched

---

### Task 3: Consolidate and guard inline-fetch pages [prereq]

## OBJECTIVE
Extract the inline `fetch(apiUrl(...))` calls in pages/components into per-page `api.ts` modules with typed helpers, then guard those helpers with `withDummyRead` and wire `useDummyRefresh` so pages re-fetch real data automatically when the toggle goes OFF. Covers dashboard, analytics, payments (list), delivery (list), INVOICES (page.tsx:47 inline read), operations (read path), sales (read path), ProductCatalog, MarketplaceCatalog, and OrderForm (products catalog read at :16 — POST order write stays in B-T4).

Files:
- Create: `apps/web/src/app/dashboard/api.ts`
- Create: `apps/web/src/app/analytics/api.ts`
- Create: `apps/web/src/app/payments/api.ts`
- Create: `apps/web/src/app/delivery/api.ts`
- Create: `apps/web/src/app/invoices/api.ts`
- Modify: `apps/web/src/app/dashboard/page.tsx`
- Modify: `apps/web/src/app/analytics/page.tsx`
- Modify: `apps/web/src/app/payments/page.tsx`
- Modify: `apps/web/src/app/delivery/page.tsx`
- Modify: `apps/web/src/app/invoices/page.tsx`
- Modify: `apps/web/src/app/operations/page.tsx`
- Modify: `apps/web/src/app/sales/page.tsx`
- Modify: `apps/web/src/components/ProductCatalog.tsx`
- Modify: `apps/web/src/components/MarketplaceCatalog.tsx`
- Modify: `apps/web/src/components/OrderForm.tsx`
- Test: `apps/web/src/app/dashboard/dummy-guard.test.tsx`

Steps:
1. Write failing test for: dashboard analytics populated while ON; real re-fetch on toggle OFF
   Test file: `apps/web/src/app/dashboard/dummy-guard.test.tsx`
   Level: integration

   Test intent:
   Given the store ON with T6 aggregates and jest.fn() global fetch
   When the dashboard loader runs
   Then:
   - metrics/trends/outlet-performance are populated with dummy values
   - global fetch was NEVER called
   Given the store ON while rendered, When `toggle()` is called OFF
   Then:
   - `useDummyRefresh` fires and the loader re-runs against real fetch (fetch IS called now)

   Exercise through:
   - the page-level loader helper (public entry point of `apps/web/src/app/dashboard/api.ts`) and the `useDummyRefresh` subscription — not the component internals

   Test doubles:
   - mock/fake: global fetch (assert not-called when ON, called after OFF); dummy generator stub
   - do NOT mock: the api helper, guards, store

   Expected RED:
   - page still fetches inline on mount → fetch recorded while ON

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`
   Expected failure: `Expected fetch not to have been called`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/app/dashboard/api.ts` (new) — extract `loadDashboard(token, role, startDate?, endDate?)` and the finance variant out of `page.tsx`, preserving the existing role-branching and `jakartaDateString` offsets; guard each branch with `withDummyRead` returning T6 `DashboardData` / `FinanceMetrics`.
   File: `apps/web/src/app/dashboard/page.tsx` — import the loader; add `useDummyRefresh(() => token && load(...))` so toggle OFF re-fetches; keep all rendering untouched.
   File: `apps/web/src/app/analytics/api.ts` (new) — extract the 3-way `Promise.all` (recommendations/forecast/segmentation) into `loadAnalytics(token)`, guarded to return T6 `AIData`.
   File: `apps/web/src/app/payments/api.ts` (new) — extract list loader; guard list read ONLY (POST payment create is B-T4).
   File: `apps/web/src/app/delivery/api.ts` (new) — extract list loader; guard list read ONLY (PATCH status is B-T4).
   File: `apps/web/src/app/invoices/api.ts` (new) — extract `page.tsx:47` invoices list loader; guard with `withDummyRead` returning T6-linked invoice rows (derived from tx, never hardcoded).
   File: `apps/web/src/app/analytics/page.tsx`, `payments/page.tsx`, `delivery/page.tsx`, `invoices/page.tsx`, `operations/page.tsx`, `sales/page.tsx`, `components/ProductCatalog.tsx`, `components/MarketplaceCatalog.tsx`, `components/OrderForm.tsx` (reads at :16 + :36) — same extract-then-guard pattern; add `useDummyRefresh` to each page that owns a loader.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`
   Expected: PASS

5. Write failing test for: analytics non-empty + no empty-state text while ON
   Test file: `apps/web/src/app/dashboard/dummy-guard.test.tsx` (append — analytics case)
   Level: integration

   Test intent:
   Given the store ON as admin
   When `loadAnalytics('token')` runs
   Then:
   - recommendations length > 0 AND forecast.predictions length === 4 AND segmentation.segments length > 0
   - global fetch was NEVER called
   And when the analytics page renders with that data, `Belum ada rekomendasi` and `Data belum cukup` are NOT present in the DOM

   Exercise through:
   - `loadAnalytics` + rendering `apps/web/src/app/analytics/page.tsx` with the dummy payload

   Test doubles:
   - mock/fake: global fetch; localStorage token
   - do NOT mock: analytics page, loader, guards, store

   Expected RED:
   - loader unimplemented / empty arrays → length assertions or DOM text assertion fails

6. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`

6b. Write failing test for: EVERY extracted page loader returns dummy with zero network (zero-fetch sweep)
   Test file: `apps/web/src/app/dashboard/dummy-guard.test.tsx` (append — loader sweep)
   Level: integration

   Test intent:
   Given the store ON with T6 aggregates, `ddp_token` seeded, and global fetch mocked
   When ALL of the following are awaited in one test (LOCKED exported names — B-T3 must export exactly these):
   `loadInvoices` (invoices/api.ts), `loadDeliveries` (delivery/api.ts), `loadPaymentsList` (payments/api.ts),
   `loadOperations` (operations/api.ts), `loadSalesList` (sales/api.ts), `loadProductCatalog` (ProductCatalog),
   `loadMarketplaceCatalog` (MarketplaceCatalog), `loadOrderFormProducts` + `trackOrder` (OrderForm)
   Then:
   - each resolves to a non-empty, correctly-shaped payload (invoices array; deliveries array; payments list; operations issues; sales visits; products catalog; marketplace suppliers+products; OrderForm catalog; tracked order or a documented null stub for `trackOrder`)
   - global fetch was NEVER called ONCE across all calls (`expect(fetch).not.toHaveBeenCalled()`) — a single cross-loader zero-fetch assertion

   This sweep closes the loader coverage gap — B-T3 extracts 10 loaders but steps 1–5 only assert dashboard + analytics, and `ProductCatalog`/`MarketplaceCatalog` were previously unnamed. NOTE (audit item): parts of this sweep may already pass via the guards imported from T7; if so, mark the file's header as a regression/characterization guard and move on (do not stall waiting for RED). `loadAnalytics` is covered by step 5, not repeated here.

6c. Run test — verify FAIL (or PASS-if-guarded-already, annotated as regression), then implement, then verify PASS:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`

7. Run the full web suite to prove no page regressions:
   `cd apps/web && npx jest --runInBand`
   Expected: all pre-existing tests still PASS

8. Refactor while green (bounded) + re-run (must stay PASS).

9. Commit:
   `git add apps/web/src/app/dashboard/api.ts apps/web/src/app/analytics/api.ts apps/web/src/app/payments/api.ts apps/web/src/app/delivery/api.ts apps/web/src/app/invoices/api.ts apps/web/src/app/dashboard/page.tsx apps/web/src/app/analytics/page.tsx apps/web/src/app/payments/page.tsx apps/web/src/app/delivery/page.tsx apps/web/src/app/invoices/page.tsx apps/web/src/app/operations/page.tsx apps/web/src/app/sales/page.tsx apps/web/src/components/ProductCatalog.tsx apps/web/src/components/MarketplaceCatalog.tsx apps/web/src/components/OrderForm.tsx apps/web/src/app/dashboard/dummy-guard.test.tsx`
   `git commit -m "feat(dummy): consolidate page data loaders and add dummy guards"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R1 (inline-fetch pages consolidated to helpers with the same guard), R3 (analytics populated; empty states must NOT show), R5 (BOTH dashboard shapes); Story 1 (toggle OFF auto re-fetch, no navigation).
Preflight: `dashboard/page.tsx` has a ~40-line `loadForRole` with role branching + `jakartaDateString` offsets; `analytics/page.tsx` does a 3-way `Promise.all` inline; `payments/page.tsx` has `usePaymentData` (list at :46, POST at :173 → B-T4); `delivery/page.tsx` fetches inline twice (list + PATCH status → PATCH is B-T4); `invoices/page.tsx:47` has an inline list read (was MISSED in first draft — added after spec review); `operations/page.tsx` + `sales/page.tsx` (list :28 reads, POST :51 → B-T4) + `ProductCatalog.tsx` + `MarketplaceCatalog.tsx` each have 1 inline read; `components/OrderForm.tsx` has THREE hits: products catalog read :16, POST :28 (B-T4), tracking `GET /orders/:id` :36 (B-T3 — was MISSED in the first review pass).

## WHY THIS APPROACH
Justification: Rule 4 split — this is a distinct layer from B-T1/B-T2 (page loaders, not module wrappers) with its own verification (DOM empty-state assertions + auto re-fetch). Integration level is mandatory because the GWT spans loader→store→render.
Complexity: deep

## SANDWICH CONTEXT
[CRITICAL: Rendering components must NOT change — only the data source moves behind a guarded loader]
You are consolidating inline-fetch pages for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: the four new `api.ts` files, the eight listed page/component files, and `apps/web/src/app/dashboard/dummy-guard.test.tsx` — no other files
Available after: Phase A T6 + T7 complete
Architecture rule: Preserve every existing render path, role branch, and date-offset helper exactly; the only behavioral change is where data comes from. `useDummyRefresh` must trigger the real re-fetch on toggle OFF without requiring navigation.
[RESTATE: Rendering components must NOT change — only the data source moves behind a guarded loader]

## DELIVERABLE
Given store ON, When dashboard/analytics loaders run, Then populated dummy data AND zero fetch
Given store ON as admin, When analytics renders, Then recommendations non-empty AND forecast 4 periods AND segmentation non-empty AND neither empty-state string appears
Given store ON as finance, When the dashboard loader runs, Then FinanceMetrics-shaped data is returned
Given store ON, When EVERY extracted loader (invoices, operations, sales, payments, delivery, ProductCatalog, MarketplaceCatalog, OrderForm catalog + tracking) runs, Then non-empty correctly-shaped dummy AND zero fetch across all of them (single sweep assertion)
Given store ON while rendered, When toggle goes OFF, Then the loader re-runs against real fetch with no navigation
Given store OFF, When the full web suite runs, Then all pre-existing tests still PASS

All tests PASS. Commit exists with message matching `feat(dummy): consolidate page data loaders and add dummy guards`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Loaders extracted into `api.ts` before guarding (not guarded inline in JSX)
  - `useDummyRefresh` wired on every page that owns a loader (auto re-fetch on OFF)
  - Empty-state strings absent while ON
  - Zero-fetch sweep across ALL 10 extracted loaders (incl. ProductCatalog + MarketplaceCatalog + OrderForm tracking) — not just dashboard/analytics
  - Whole `npx jest` suite green (regression guard)
  - `invoices/page.tsx` inline read is extracted and guarded (spec in-scope: Invoice menu)
  - `OrderForm.tsx` product-catalog read (:16) AND tracking read (:36) are guarded (POST :28 stays B-T4)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Changing rendered markup/components (only the data source)
  - Guarding write calls (B-T4)
  - Backend changes; new dependencies

Open question risks:
  - `delivery/page.tsx` PATCH is a write → leave it for B-T4 and guard only the list read here
  - Some pages may use local non-exported types → mirror them minimally in the new `api.ts` and note it in the commit body

Rollback note:
  - Revert the page edits and delete the new `api.ts` files; delete the test.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, commit created
Uncertain when: a page's role branching cannot be preserved without touching markup (flag NEEDS_CONTEXT)
Escalate when: out-of-scope files are touched or a write path is guarded here

---

### Task 4: Fake mutation mutators + write-path guards [prereq]

## OBJECTIVE
Add write-path fake mutations to the dummy store — order creation with relational side-effects, delivery status with proof, product price, promotions, user role, outlet, PLUS previously-missed writes: `payments/page.tsx` POST, `OrderForm.tsx` POST, `sales/page.tsx` POST visits, and `sendFunnelEvent` — plus a `mutations.ts` module of pure mutator functions and the write guards at every POST/PATCH call-site — zero network while ON, `dummy-`/negative IDs, ephemeral.

Files:
- Create: `apps/web/src/dummy/mutations.ts`
- Test: `apps/web/src/dummy/mutations.test.ts`
- Modify: `apps/web/src/app/sales/orders/api.ts` (`createSalesOrder` write path)
- Modify: `apps/web/src/app/admin/products/api.ts` (`updateProductPrice`)
- Modify: `apps/web/src/app/admin/promotions/api.ts` (`createPromotion`, `updatePromotion`, `deletePromotion`, `broadcastPromotion`)
- Modify: `apps/web/src/app/admin/users/api.ts` (`assignUserRole`)
- Modify: `apps/web/src/app/admin/outlets/api.ts` (`updateOutlet`)
- Modify: `apps/web/src/app/delivery/page.tsx` (PATCH status call-site)
- Modify: `apps/web/src/app/payments/page.tsx` (POST payment call-site :173)
- Modify: `apps/web/src/components/OrderForm.tsx` (POST order call-site :28)
- Modify: `apps/web/src/app/sales/page.tsx` (POST visit call-site :51)
- Modify: `apps/web/src/lib/data-intelligence-api.ts` (`sendFunnelEvent` POST only — reads are B-T1's)

Steps:
1. Write failing test for: order creation applies relational side-effects with prefixed IDs
   Test file: `apps/web/src/dummy/mutations.ts` → `apps/web/src/dummy/mutations.test.ts`
   Level: unit

   Test intent:
   Given store ON with T6 entities loaded
   When `createDummyOrder({ outlet_id, items })` runs
   Then:
   - a new order exists with a `dummy-` prefixed string id (and/or negative numeric id) that collides with no existing id
   - exactly one new payment, one new invoice, and one new delivery exist, each referencing the new order id
   - the new order appears in the orders list returned by subsequent reads
   - orders count increased by exactly 1

   Exercise through:
   - `createDummyOrder` from `apps/web/src/dummy/mutations.ts` and the store read selectors

   Test doubles:
   - mock/fake: global fetch (assert never called)
   - do NOT mock: mutations module, store

   Expected RED:
   - file does not exist → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/mutations.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/mutations'`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/mutations.ts` — pure-ish mutators that take the current entities and return the next entities (plus store-bound wrappers): `createDummyOrder`, `updateDummyDelivery(deliveryId, status, proof?)`, `updateDummyProductPrice`, `createDummyPromotion` / `updateDummyPromotion` / `deleteDummyPromotion` / `broadcastDummyPromotion`, `assignDummyUserRole`, `updateDummyOutlet`, `sendDummyFunnelEvent` (B-T1's read counterpart left unguarded here — THIS task owns the funnel POST), `createDummyPayment` (for `payments/page.tsx:173`), `createDummyVisit` (for `sales/page.tsx:51`), `createDummyOutletOrder` (for `OrderForm.tsx:28`; wraps the same 1:1 order → payment/invoice/delivery linkage). IDs: string prefixed `dummy-`; numeric → negative counter.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/mutations.test.ts --runInBand`
   Expected: PASS

5. Write failing test for: write guards stop network and mutations stay ephemeral
   Test file: `apps/web/src/dummy/mutations.test.ts` (append)
   Level: integration

   Test intent:
   Given store ON with a jest.fn() global fetch and `ddp_token` seeded
   When the following are ALL invoked while ON (single test, sequential awaits):
   `createSalesOrder(token, payload)` (sales/orders/api.ts), `updateProductPrice` (admin/products/api.ts),
   `createPromotion/updatePromotion/deletePromotion/broadcastPromotion` (admin/promotions/api.ts; 4 calls),
   `assignUserRole` (admin/users/api.ts), `updateOutlet` (admin/outlets/api.ts),
   the delivery PATCH path (delivery/page.tsx), `sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 })` (data-intelligence-api.ts — real signature `FunnelEventType = 'displayed'|'clicked'|'cart'`, NOT `(token,payload)`),
   the payments POST path (payments/page.tsx:173), the sales POST visits path (sales/page.tsx:51), and the OrderForm POST (OrderForm.tsx:28)
   Then:
   - each resolves to a fake success matching its real response shape (including `FunnelEventPayload { event_uuid, event_type: 'clicked', outlet_id: 1, product_id: 2 }` for the funnel call)
   - global fetch was NEVER called ONCE across ALL of them (`expect(fetch).not.toHaveBeenCalled()`) — a single cross-writes zero-fetch assertion
   Given store OFF, When `createSalesOrder(token, payload)` is invoked, Then global fetch IS called with `POST` (no regression)
   Given those mutations were made, When `toggle()` OFF then ON (or `resetEntities()`), Then the real/dummy baseline contains none of the created `dummy-` records

   Exercise through:
   - every write guard listed above (read exports + inline POST/PATCH call-sites via their modules/pages) + store `toggle()`

   Test doubles:
   - mock/fake: global fetch; localStorage token; dummy generator stub
   - do NOT mock: mutations module, store, api modules

   Expected RED:
   - `createSalesOrder` POSTs to the backend while ON → fetch recorded

6. Run test — verify FAIL, then implement (wire the guard into all write call-sites; delivery page PATCH becomes `updateDummyDelivery` when ON), then verify PASS:
   `cd apps/web && npx jest src/dummy/mutations.test.ts --runInBand`

7. Run the full web suite to prove no write-path regressions:
   `cd apps/web && npx jest --runInBand`
   Expected: all pre-existing tests still PASS

8. Refactor while green (bounded) + re-run (must stay PASS).

9. Commit (mutations module first, then the call-site wiring, so the shared helper lands before its consumers):
   `git add apps/web/src/dummy/mutations.ts apps/web/src/dummy/mutations.test.ts`
   `git commit -m "feat(dummy): add fake mutation mutators with relational side-effects"`
   `git add apps/web/src/app/sales/orders/api.ts apps/web/src/app/admin/products/api.ts apps/web/src/app/admin/promotions/api.ts apps/web/src/app/admin/users/api.ts apps/web/src/app/admin/outlets/api.ts apps/web/src/app/delivery/page.tsx apps/web/src/app/payments/page.tsx apps/web/src/components/OrderForm.tsx apps/web/src/app/sales/page.tsx apps/web/src/lib/data-intelligence-api.ts`
   `git commit -m "feat(dummy): guard write call-sites to fake mutations"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 3 R1 (POST/PATCH to dummy store only, zero network, identical success UI), R2 (`dummy-` prefix / negative ints, no collisions), R3 (relational side-effects: order → payment/invoice/delivery; delivered status), R4 (ephemeral), R5 (tracking faked), R6 (dummy op-issue ids non-navigable).
Preflight: write functions found — `createSalesOrder` (`sales/orders/api.ts`), `updateProductPrice` (`admin/products/api.ts`), `createPromotion`/`updatePromotion`/`deletePromotion`/`broadcastPromotion` (`admin/promotions/api.ts`), `assignUserRole` (`admin/users/api.ts`), `updateOutlet` (`admin/outlets/api.ts`), `sendFunnelEvent` (`lib/data-intelligence-api.ts:196`), delivery PATCH inline in `app/delivery/page.tsx`, `payments/page.tsx:173` POST, `components/OrderForm.tsx:28` POST, `sales/page.tsx:51` POST `/sales/visits`.

## WHY THIS APPROACH
Justification: Mutators (shared helper, own tests) + call-site wiring are one deliverable: the wiring cannot pass without the mutators, and the mutators are useless unwired. Two commits keep the helper reviewable before its consumers. Rule of three applies — eight call-sites share one guard pattern.
Complexity: deep

## SANDWICH CONTEXT
[CRITICAL: While Dummy is ON, a write must NEVER reach the backend — no POST/PATCH under any circumstance]
You are implementing fake write mutations for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/mutations.ts`, `apps/web/src/dummy/mutations.test.ts`, and the ten listed call-site files (`sales/orders/api.ts`, `admin/products/api.ts`, `admin/promotions/api.ts`, `admin/users/api.ts`, `admin/outlets/api.ts`, `delivery/page.tsx`, `payments/page.tsx`, `components/OrderForm.tsx`, `sales/page.tsx`, `lib/data-intelligence-api.ts`) — no other files
Available after: Phase A T6 + T7 complete
Architecture rule: New IDs must never collide with real auto-increment ids (`dummy-` prefix for strings, negative integers for numbers). Mutations are in-memory only — never persisted. Success UI must be identical to the real path (same resolved values, same absence of errors).
[RESTATE: While Dummy is ON, a write must NEVER reach the backend — no POST/PATCH under any circumstance]

## DELIVERABLE
Given store ON at /orders, When submitting an order, Then it appears with a negative integer `id` AND a `dummy-`-prefixed `order_id` string (spec: numeric ids → negative, string ids → `dummy-` prefix; e.g. order `id: -1`, `order_id: 'dummy-ORD-001'`) plus one linked payment/invoice/delivery each, AND zero fetch
Given store ON at /orders (OrderForm path), When the order form submits, Then the same 1:1 linkage holds AND zero fetch to `/orders` or `/products` (catalog read was guarded in B-T3)
Given store ON at /payments, When creating a payment, Then it records locally AND zero fetch
Given store ON at /sales, When creating a visit, Then it records locally AND zero fetch
Given store ON at /delivery, When marking delivered with proof fields, Then status updates locally AND no PATCH fetch
Given store ON with mutations made, When toggle OFF then ON, Then no `dummy-` record is present
Given store ON, When sendFunnelEvent runs, Then fake success AND zero fetch
Given store OFF, When a write function is invoked, Then the real POST/PATCH is sent (no regression)
Scope note — admin/orders — NOT in-scope: spec Scope/In-Scope enumerates Dasbor, Invoice, Pesanan (sales orders), Produk, Outlet, Marketplace, Pembayaran, Pengiriman, Sales, Analitik, Data Intelligence, Operasi — it does NOT list `admin/orders` (`/admin/orders` + `PUT /orders/:id/approve`). Leave those hits on the backend (no guard). Confirm with the user in Phase C before guarding.

All tests PASS. Commit exists with messages matching `feat(dummy): add fake mutation mutators with relational side-effects` and `feat(dummy): guard write call-sites to fake mutations`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Zero network on every write path while ON (verified by fetch-never-called assertions) INCLUDING payments POST, OrderForm POST, sales-visit POST, and sendFunnelEvent
  - 1:1 side-effects on order creation from BOTH order entry points (`createSalesOrder` and `OrderForm`)
  - ID collision safety (`dummy-` / negative)
  - Ephemerality verified by toggle cycle
  - Whole `npx jest` suite green (write-path regression guard)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit messages follow conventional commits format

Must-not-have:
  - Persisting mutations to localStorage
  - Guarding read functions or page loaders (B-T2/B-T3 own those)
  - Backend changes; new dependencies

Open question risks:
  - Dummy operations issue detail is non-navigable by design → if the operations page links by id, route to a client-side no-op; do not add backend calls

Rollback note:
  - Delete `mutations.ts` + test; revert the ten call-site edits. Pages fall back to real writes.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, both commits created
Uncertain when: a write call-site's returned shape cannot be faked identically to the real response (flag NEEDS_CONTEXT)
Escalate when: out-of-scope files are touched or a read path is guarded here

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| B-T1 | Guard DI + operations APIs | Phase A T6/T7 | standard | Dummy aggregates returned, zero fetch; `FetchResult` envelope preserved |
| B-T2 | Guard admin/* + sales/* reads | Phase A T6/T7 | standard | Shape parity + filters applied; zero fetch |
| B-T3 | Consolidate + guard inline pages | Phase A T6/T7 | deep | Analytics non-empty, no empty-state text, auto re-fetch on OFF, invoices + OrderForm catalog guarded |
| B-T4 | Fake mutations + write guards | Phase A T6/T7 | deep | `dummy-` ids, 1:1 side-effects, ephemeral, zero network across 10 write call-sites |
