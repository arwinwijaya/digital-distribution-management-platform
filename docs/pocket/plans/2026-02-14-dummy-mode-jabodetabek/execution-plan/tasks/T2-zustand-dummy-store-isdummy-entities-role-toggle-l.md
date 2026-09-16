# Task T2 — Zustand dummy store (isDummy, entities, role, toggle, localStorage persist)

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


## OBJECTIVE
Create the Zustand singleton `useDummyStore` holding `isDummy`, `dummyEntities` (empty until toggle-ON), `role` mirror, and actions `toggle()`, `setRole()`, `resetEntities()`, plus an **init/regenerate path** so that a refresh with `dummy:isDummy === '1'` repopulates entities (Story 1 B: refresh must render the same deterministic data, not just restore the flag). Toggle-ON generates nothing itself beyond calling the injected generator; factory wiring lands in T5/T6 (tests use a stub generator injected via `setDummyGenerator` to keep T2 independent).

DETERMINISM SEAM (LOCKED — do not redesign):
- The store's `toggle()` takes NO args and calls the injected generator with NO args: `generate()`.
- The ONLY seam by which `today` reaches the factory is `setDummyGenerator(fn)`. Tests that need a fixed date before they toggle MUST wire the composition helper from T6: `import { buildFullDummy } from '@/dummy'` and register `setDummyGenerator((today?) => buildFullDummy(today ?? new Date('2026-02-14')))` BEFORE toggling — i.e. the generator closure pins the date, not a toggle argument.
- Never call `toggle(date)`. `generate()` inside the store must not call `new Date()` itself; the date flows only from the registered generator closure.

Generator contract (fixed here so T5/T6 can implement against it): `type DummyGenerator = (today?: Date) => DummyEntities` — `today` is injectable so tests and Phase C can pin a fixed date via the `setDummyGenerator` closure without touching `Date.now()` inside the factories. The canonical implementation `buildFullDummy(today?)` is delivered by T6 as `apps/web/src/dummy/index.ts` composing `buildMasterData` + `buildTransactions` + `buildAggregates`.

Files:
- Create: `apps/web/src/dummy/store.ts`
- Test: `apps/web/src/dummy/store.test.ts`

Test hygiene (applies to this file and every test added in Phase B/C):
- `beforeEach`: `localStorage.clear()`, reset the Zustand singleton to its initial state (re-create or call a `reset()`), re-register the stub generator.
- `afterEach`: `jest.restoreAllMocks()`.
- The store is a module-level singleton — tests must never rely on a previous test's end-state.

Steps:
1. Write failing test for: toggle ON persists flag + generates once; OFF clears entities; entities never persisted
   Test file: `apps/web/src/dummy/store.test.ts`
   Level: integration (collaborates with Zustand singleton + localStorage — not an isolated unit)

   Test intent:
   Given a reset store with a stub `generate()` returning `{ outlets: 1 }`-shaped entities
   When `toggle()` is called ON
   Then:
   - `isDummy === true`
   - `localStorage['dummy:isDummy'] === '1'` (chosen key; replaces the spec's example `dummy_is_dummy` — locked here)
   - `dummyEntities` equals the stub output (generate called exactly once)
   When `toggle()` is then called OFF
   Then:
   - `isDummy === false` AND flag cleared/`'0'` AND `dummyEntities === null`
   - `localStorage` contains NO entities key (assert the key count / that no value serializes the entities)

   Exercise through:
   - `useDummyStore` hook + `useDummyStore.getState()` actions from `apps/web/src/dummy/store.ts`

   Test doubles:
   - mock/fake: stub `generate` via `setDummyGenerator`; jsdom localStorage
   - do NOT mock: the Zustand store itself

   Expected RED:
   - `apps/web/src/dummy/store.ts` does not exist → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/store.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/store'`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/store.ts` — `create<DummyState>()(...)`, manual localStorage sync (isDummy only; entities in-memory), `toggle()` (false→true runs `generate()` once with no args — the production wiring that registers the default generator MUST be an exported `installDummy(store)` or `registerDummyGenerator(factoryFn)` called once in `apps/web/src/app/layout.tsx` (keeping `store.ts` free of the factory import), not hidden inside `store.ts`; T6 delivers that helper), `setRole(role)`, `resetEntities()`, `reset()` for tests. Export `useDummyStore`, `selectIsDummy`, `setDummyGenerator(fn)`, and the `DummyGenerator` type.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/store.test.ts --runInBand`
   Expected: PASS

5. Write failing test for: refresh-with-persisted-flag repopulates entities (Story 1 B)
   Test file: `apps/web/src/dummy/store.test.ts` (append)
   Level: integration (store init reads localStorage and calls the injected generator)

   Test intent:
   Given `localStorage['dummy:isDummy'] === '1'` was set by a previous session, localStorage cleared of entities, and a fresh store module instance (simulated refresh) with a stub generator registered via `setDummyGenerator(() => stubEntities)` (T6's real `buildFullDummy(today)` form is `setDummyGenerator((today?) => buildFullDummy(today ?? fixedDate))` — same closure shape, stub used here to keep T2 independent)
   When the store initializes (`useDummyStore.getState()` is read)
   Then:
   - `isDummy === true` (restored from localStorage)
   - `dummyEntities` is NOT null — the generator ran on init with the persisted flag
   - re-registering `setDummyGenerator` with the same stub and re-initializing yields deep-equal entities (deterministic across refresh)

   Exercise through:
   - module init path + `useDummyStore.getState()`

   Test doubles:
   - mock/fake: stub generator; jsdom localStorage pre-seeded with the flag
   - do NOT mock: the store

   Expected RED:
   - `dummyEntities` is null after init (flag restored but data missing) → assertion fails

6. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/store.test.ts --runInBand`
   Expected failure: `expect(entities).not.toBeNull()` fails

7. Implement minimal code to satisfy the test (init effect: if persisted flag is `'1'`, run `generate(today)` once).

8. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/store.test.ts --runInBand`
   Expected: PASS

9. Write failing test for: toggle ON again regenerates (fresh rolling window)
   Test file: `apps/web/src/dummy/store.test.ts` (append)
   Level: integration (asserts call counts across two toggle cycles via the store singleton)

   Test intent:
   Given the store went ON (registered `setDummyGenerator(() => stubT1)` where stubT1 is pinned to date T1), then OFF,
   When the generator is re-registered via `setDummyGenerator(() => stubT2)` (pinned to a later date T2, same shape as `setDummyGenerator((today?) => buildFullDummy(today ?? fixedT2))` in T6/Phase C) and `toggle()` turns ON again
   Then:
   - the T2 stub has been called exactly once since registration AND total generations across both cycles equal two
   - `dummyEntities` reflects the second (T2) generation, not a cached T1 value

   Exercise through:
   - `useDummyStore.getState().toggle()` twice + re-registration via `setDummyGenerator` between cycles

   Test doubles:
   - mock/fake: stub generator (jest.fn) with call assertions; jsdom localStorage
   - do NOT mock: the store

   Expected RED:
   - second ON reuses the cached first-generation entities, or generate is called only once → assertion fails

   Note: this is the ONE DELIVERABLE scenario that the first draft omitted — do not skip it.

10. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/dummy/store.test.ts --runInBand`

11. Refactor while green (bounded) + re-run (must stay PASS).

12. Commit:
   `git add apps/web/src/dummy/store.ts apps/web/src/dummy/store.test.ts`
   `git commit -m "feat(dummy): add Zustand dummy store with persisted toggle"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 1 (toggle visible when logged in, persisted in localStorage default OFF; OFF auto re-fetch; refresh preserves; logged-out hidden); Story 3 R4 (mutations ephemeral — entities never persisted).
Preflight: Zustand ^4.5.0 installed, zero existing stores (first store sets the convention); LoginForm dispatches `ddp-auth-change` with `{token, role}`.

## WHY THIS APPROACH
Justification: Store is the seam every Phase B guard reads (`selectIsDummy`). Generator injection slot keeps T2 testable without the factory, so T5/T6 can run in parallel against a stable interface.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Dummy entities must NEVER be persisted — isDummy flag only; entities are in-memory and cleared on toggle OFF]
You are implementing the Zustand dummy store for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/store.ts`, `apps/web/src/dummy/store.test.ts` — no other files
Available after: T1 (seed/RNG/dates exist, though this task only needs the module dir convention)
Architecture rule: No new dependencies; persist ONLY the flag; never call the backend from the store.
[RESTATE: Dummy entities must NEVER be persisted — isDummy flag only; entities are in-memory and cleared on toggle OFF]

## DELIVERABLE
Given store OFF with stub generate, When toggle() ON, Then isDummy true AND localStorage flag set AND entities generated exactly once
Given store ON, When toggle() OFF, Then isDummy false AND flag cleared AND entities null AND no entities key in localStorage
Given a persisted flag from a previous session, When the store initializes, Then isDummy true AND entities repopulated (not null) AND deterministic for the same injected today
   Given store ON (registered `setDummyGenerator(() => buildFullDummy(fixedT1))`), When toggle OFF then ON after re-registering `setDummyGenerator(() => buildFullDummy(fixedT2))`, Then two total generations AND entities reflect T2 (fresh rolling window — no cached reuse)

All tests PASS. Commit exists with message matching `feat(dummy): add Zustand dummy store with persisted toggle`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Only the flag persists; entities always in-memory
  - Refresh-with-persisted-flag repopulates entities (Story 1 B) — not just the flag
  - Generator contract is `(today?: Date) => DummyEntities` and `toggle()` calls `generate()` with no args — the date flows ONLY through the `setDummyGenerator` closure (locked seam; never `toggle(date)`)
  - Generator injection slot (`setDummyGenerator`) so T5/T6 plug the real factory without editing this file
  - Test hygiene: `beforeEach` clears localStorage + resets the singleton shop
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Backend calls from the store
  - Persisting entities or mutations to localStorage (spec: ephemeral)
  - Touching Topbar/Sidebar/LoginForm (T3/T4 own those)

Open question risks:
  - none

Rollback note:
  - Delete `apps/web/src/dummy/store.ts`; nothing else imports it yet in Phase A (Topbar wiring is T4)

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: store imports the real factory directly (must use injection slot) or task touches out-of-scope files
