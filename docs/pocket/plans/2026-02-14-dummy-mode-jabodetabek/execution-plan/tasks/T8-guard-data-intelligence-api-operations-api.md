# Task T8 — Guard data-intelligence-api + operations-api

**Phase:** 2
**Depends:** T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


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
   File: `apps/web/src/lib/data-intelligence-api.ts` — at the top of each read export, `if (selectIsDummy()) return withDummyRead(true, dummy.<x>, () => adminFetch<...>(...))`. NOTE: `sendFunnelEvent` is a WRITE (POST measurement event) — it is owned by Phase B T11, DO NOT guard it here.
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
NOTE: sendFunnelEvent is T11's (write path) — NOT asserted here
Given store ON, When readiness/issues/issue-detail are awaited, Then `ok: true` with data AND zero fetch

All tests PASS. Commit exists with message matching `feat(dummy): guard data-intelligence and operations read APIs`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Guards imported from `@/dummy/guards` (no local reimplementation)
  - Operations dummy responses keep the `FetchResult<T>` envelope
  - `sendFunnelEvent` is NOT guarded here (T11 write path owns it) — avoids merge conflict on this file
  - `sendFunnelEvent` fake asserts its `FunnelEventPayload` shape (`event_uuid`, `event_type`, `outlet_id`, `product_id`) — not just truthiness
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - AbortController plumbing
  - Changing page components (T10 owns pages)
  - Backend changes; new dependencies

Open question risks:
  - Dummy op-issue detail is non-navigable by design → if a page links to `/operations/:dummyId`, T-C1 must catch it; do not add routing logic here

Rollback note:
  - Revert both module edits; delete the test. Phase A dummy data stays inert.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: a page component must change to satisfy this task (it belongs to T10) or out-of-scope files are touched
