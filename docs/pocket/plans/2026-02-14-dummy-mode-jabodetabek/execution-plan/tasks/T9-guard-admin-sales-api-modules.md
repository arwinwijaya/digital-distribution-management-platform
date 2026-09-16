# Task T9 — Guard admin/* + sales/* API modules

**Phase:** 2
**Depends:** T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


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
   Files: same pattern for `admin/products/api.ts` (`fetchProducts`, `fetchPriceHistory`), `admin/promotions/api.ts` (`fetchPromotions`), `admin/sales-performance/api.ts` (`fetchAdminSalesPerformance`), `admin/users/api.ts` (`fetchAdminUsers`, `UsersListResult`), `sales/orders/api.ts` (`fetchSalesOutlets`, `fetchCatalogProducts` ONLY — writes are T11), `sales/performance/api.ts` (`fetchMyPerformance`).

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
[CRITICAL: In `sales/orders/api.ts` guard ONLY the READ functions — `createSalesOrder` is T11's write path]
You are wiring dummy read guards for admin/sales modules in Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: the seven listed `api.ts` files + `apps/web/src/app/admin/outlets/dummy-guard.test.ts` — no other files
Available after: Phase A T6 + T7 complete
Architecture rule: Dummy returns must match each module's EXISTING return shape exactly (array vs envelope) so pages need no change. Filters (search/category/territory/limit/cursor) must apply to dummy data so filter behavior is verifiable.
[RESTATE: In `sales/orders/api.ts` guard ONLY the READ functions — `createSalesOrder` is T11's write path]

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
  - Guarding `createSalesOrder` (T11 owns writes)
  - Editing pages/components (T10)
  - Backend changes; new dependencies

Open question risks:
  - `admin/products/api.ts#fetchProducts` returns a bare array while `fetchPriceHistory` uses a nested envelope — confirm both in the RED test before implementing

Rollback note:
  - Revert the seven module edits; delete the test.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: a module's real return shape differs from what was preflighted
Escalate when: a page must change (belongs to T10) or out-of-scope files are touched
