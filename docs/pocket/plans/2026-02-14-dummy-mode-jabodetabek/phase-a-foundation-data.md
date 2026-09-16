# EXECUTION PLAN — Dummy Mode JABODETABEK: Phase A (Foundation + Data)

**Date:** 2026-02-14
**Spec:** docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
**Status:** draft
**Total tasks:** 7

---

## Execution Overview

### Recommended Order
```
T1 → T2, T5, T7 (parallel) → T3, T4 (parallel after T2) → T6 (after T5)
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T2, T5, T7 | T1 completes |
| Group B | T3, T4 | T2 completes |
| Group C | T6 | T5 completes |

### Constraints Reminder
**Architecture:** Touch ONLY `apps/web/src` (lib, app/*, components/*, new `apps/web/src/dummy/*`). DO NOT touch `apps/api`, `database/*`, `docker-compose.yml`. Zustand ≥4.5.0 installed — no new prod deps. Follow `apiUrl`/`authHeaders`/`getStoredToken` conventions; `'use client'`.
**Out-of-scope:** Any backend/API endpoint changes; persisting dummy mutations across toggle-OFF/refresh; sharpening driver/sales role scopes in Sidebar (keep current `else → all non-adminOnly`); new prod dependencies (incl. Zod).
**Assumptions at risk:** Dummy op-issue detail non-navigable; best-effort discard at commit layer (no AbortController threading); generators typed to existing TS interfaces (no Zod).
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.
**Test hygiene (all phases):** the dummy store is a module-level Zustand singleton and tests share jsdom `localStorage` — every new test file MUST `beforeEach` → `localStorage.clear()`, reset the store singleton, re-register the stub/real generator; `afterEach` → `jest.restoreAllMocks()`. Tests must never depend on a previous test's end-state.

### File Structure Map
```
Story 1 — Toggle Topbar
  Create: apps/web/src/dummy/index.ts                    (created by: T1)
  Create: apps/web/src/dummy/seed.ts                     (created by: T1)
  Create: apps/web/src/dummy/rng.ts                      (created by: T1)
  Create: apps/web/src/dummy/dates.ts                    (created by: T1)
  Test:   apps/web/src/dummy/seed.test.ts                (created by: T1)
  Create: apps/web/src/dummy/store.ts                    (created by: T2)
  Test:   apps/web/src/dummy/store.test.ts               (created by: T2)
  Modify: apps/web/src/components/LoginForm.tsx
  Modify: apps/web/src/components/Sidebar.tsx
  Test:   apps/web/src/components/auth-role-offline.test.tsx
  Modify: apps/web/src/components/Topbar.tsx
  Test:   apps/web/src/components/Topbar.test.tsx

Story 2 — Read paths (foundation data only; wiring lands in Phase B)
  Create: apps/web/src/dummy/factory.ts                  (created by: T5)
  Create: apps/web/src/dummy/factory-transactions.ts     (created by: T6)
  Create: apps/web/src/dummy/aggregates.ts               (created by: T6)
  Create: apps/web/src/dummy/install.ts                  (created by: T6)
  Modify: apps/web/src/dummy/index.ts                    (created by: T1; composition export added by: T6)
  Modify: apps/web/src/app/layout.tsx                    (installDummy() call by: T6)
  Test:   apps/web/src/dummy/factory.test.ts             (created by: T5)
  Test:   apps/web/src/dummy/factory-transactions.test.ts (created by: T6)
  Test:   apps/web/src/dummy/aggregates.test.ts          (created by: T6)
  Test:   apps/web/src/dummy/install.test.ts             (created by: T6)

Story 3 — Guards plumbing (helpers only; call-site guards land in Phase B)
  Create: apps/web/src/dummy/guards.ts                   (created by: T7)
  Test:   apps/web/src/dummy/guards.test.ts              (created by: T7)
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

### Task 1: Dummy module scaffold + seeded RNG + date utils + JABODETABEK seed constants [prereq]

## OBJECTIVE
Create the `apps/web/src/dummy/` module skeleton: a deterministic seeded RNG, date-window helpers (rolling 60 days), and JABODETABEK seed constants (5 territories with bbox + anchor coords, outlet names, product SKUs, supplier names). Pure functions only — no store yet.

Files:
- Create: `apps/web/src/dummy/rng.ts`
- Create: `apps/web/src/dummy/dates.ts`
- Create: `apps/web/src/dummy/seed.ts`
- Create: `apps/web/src/dummy/index.ts`
- Test: `apps/web/src/dummy/seed.test.ts`

Steps:
1. Write failing test for: deterministic seed + rolling 60-day window
   Test file: `apps/web/src/dummy/seed.test.ts`
   Level: unit

   Test intent:
   Given the fixed seed constant (deterministic, NOT derived from Date.now())
   When the RNG generates a sequence twice
   Then:
   - both sequences are byte-identical (determinism)
   - territory seed yields exactly 5 JABODETABEK territories: Jakarta, Bogor, Depok, Tangerang, Bekasi, each with anchor lat/lon inside lat -6.9..-5.9, lon 105.9..107.3
   - date helper returns a window where end = today (Asia/Jakarta) and start = end − 60 days

   Exercise through:
   - `createSeededRng(seed)` from `apps/web/src/dummy/rng.ts`
   - `dummyWindow(today?)` from `apps/web/src/dummy/dates.ts`
   - `JABODETABEK_TERRITORIES`, `DUMMY_SEED` from `apps/web/src/dummy/seed.ts`

   Test doubles:
   - mock/fake: none (pure functions; inject explicit `today` date where needed)
   - do NOT mock: rng, dates, seed constants

   Expected RED:
   - module files do not exist → import/symbol error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/seed.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/...'` or `createSeededRng is not a function`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/rng.ts` — mulberry32-style seeded RNG exposing `next()`, `int(min,max)`, `pick(arr)`, `shuffle(arr)`
   File: `apps/web/src/dummy/dates.ts` — `dummyWindow(today = new Date())` returning `{ start, end }` ISO date strings (Asia/Jakarta day math), plus `daysBetween(start,end)` list
   File: `apps/web/src/dummy/seed.ts` — `DUMMY_SEED = 'ddp-jabodetabek-v1'` (fixed string), `JABODETABEK_TERRITORIES` (5 entries with bbox + anchor coords), outlet-name / product-SKU / supplier-name arrays
   File: `apps/web/src/dummy/index.ts` — barrel re-exports

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/seed.test.ts --runInBand`
   Expected: PASS

5. Refactor while green (bounded):
   - Rule of three: same logic appears 3+ times in the files in scope → extract a named, domain-scoped helper — never a generic `utils.ts`
   - A modified file crosses ~300 lines, or a function exceeds ~50 lines → split/extract
   - Refactor only within task-scope files
   - Re-run test: `cd apps/web && npx jest src/dummy/seed.test.ts --runInBand` — must stay PASS

6. Commit:
   `git add apps/web/src/dummy/rng.ts apps/web/src/dummy/dates.ts apps/web/src/dummy/seed.ts apps/web/src/dummy/index.ts apps/web/src/dummy/seed.test.ts`
   `git commit -m "feat(dummy): scaffold deterministic seed, RNG, and JABODETABEK constants"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Implementation Notes (generate at toggle-ON, rolling `now − 60d`, seed fixed not Date.now()); Story 2 R2 (seeded determinism); Story 2 geo scenario bbox (lat -6.9..-5.9, lon 105.9..107.3).
Preflight: jest 29 + jsdom + babel-jest; `@/` maps to `src/`; colocated `*.test.ts(x)` convention.

## WHY THIS APPROACH
Justification: Pure-function foundation with no component/store coupling — everything downstream (factory, guards, Topbar) imports these. Determinism is the acceptance linchpin (refresh must render identical numbers), so the RNG+seed is tested first.
Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: Touch ONLY apps/web/src — never apps/api, database/*, or docker-compose.yml]
You are implementing the dummy module scaffold for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard (this task is the pure-function foundation, T1 of 3 phases)
Files in scope: `apps/web/src/dummy/rng.ts`, `apps/web/src/dummy/dates.ts`, `apps/web/src/dummy/seed.ts`, `apps/web/src/dummy/index.ts`, `apps/web/src/dummy/seed.test.ts` — no other files
Available after: none (prereq)
Architecture rule: No new production dependencies; Zustand/Jest already installed. Files stay under `apps/web/src/dummy/`.
[RESTATE: Touch ONLY apps/web/src — never apps/api, database/*, or docker-compose.yml]

## DELIVERABLE
Verification — task is DONE when all pass:

Given the fixed DUMMY_SEED, When the seeded RNG generates a sequence twice, Then both sequences are identical
Given dummyWindow(), When called, Then end = today and start = end − 60 days (Asia/Jakarta)
Given JABODETABEK_TERRITORIES, When read, Then exactly 5 territories each with coords inside the JABODETABEK bbox

All tests PASS. Commit exists with message matching `feat(dummy): scaffold deterministic seed, RNG, and JABODETABEK constants`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Deterministic: same seed → identical sequence across runs
  - Seed is a FIXED constant (must NOT be derived from Date.now() — per edge-case hunter watchout)
  - 5 territories with anchor coords inside the spec bbox
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Backend/API/DB/docker changes
  - New production dependencies
  - Generating dummy at import time (generation happens at toggle-ON in T2 — this task only defines utils + constants)

Open question risks:
  - none (all assumptions documented; Zod deferred as follow-up)

Rollback note:
  - Delete `apps/web/src/dummy/*` — no migration to undo

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task touches files outside listed scope

---

### Task 2: Zustand dummy store (isDummy, entities, role, toggle, localStorage persist) [depends: T1]

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

---

### Task 3: Role persistence at login (ddp_role) + Sidebar offline role read [depends: T2]

## OBJECTIVE
Persist `role` to `localStorage('ddp_role')` at login time and make Sidebar read role from localStorage when Dummy is ON (skipping `GET /auth/me`); when OFF, current `/auth/me` behavior is unchanged.

Files:
- Modify: `apps/web/src/components/LoginForm.tsx`
- Modify: `apps/web/src/components/Sidebar.tsx`
- Test: `apps/web/src/components/auth-role-offline.test.tsx`

Steps:
1. Write failing test for: Sidebar skips /auth/me when dummy ON and uses stored role
   Test file: `apps/web/src/components/auth-role-offline.test.tsx`
   Level: integration

   Test intent:
   Given `localStorage ddp_token` + `ddp_role='finance'` and dummy store ON (stub entities)
   When Sidebar renders
   Then:
   - `fetch` is NEVER called with a URL containing `/auth/me`
   - only finance-flagged nav items render (Dasbor, Invoice, Pembayaran)

   Exercise through:
   - Rendering `<Sidebar />` (real component) with the real `useDummyStore` set ON

   Test doubles:
   - mock/fake: global fetch (assert NOT called for /auth/me); stub dummy generator
   - do NOT mock: Sidebar, the dummy store, localStorage

   Expected RED:
   - Sidebar calls `/auth/me` regardless of dummy (current behavior) → fetch mock records the call → assertion fails

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/components/auth-role-offline.test.tsx --runInBand`
   Expected failure: `Expected fetch not to have been called with /auth/me` (or nav renders empty because role unresolved)

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/components/LoginForm.tsx` — after `storeToken(token)`, also `localStorage.setItem('ddp_role', role)`; on logout paths (Topbar handleLogout — read-only here, T4 owns Topbar; LoginForm only writes) keep symmetric (note for T4: clear `ddp_role` on logout).
   File: `apps/web/src/components/Sidebar.tsx` — in `useSidebarAuth` sync: if dummy store `isDummy` is true, read `localStorage('ddp_role')`, set role from it, `authResolved=true`, and skip `fetchCurrentRole`. When OFF, keep existing flow untouched.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/components/auth-role-offline.test.tsx --runInBand`
   Expected: PASS

4b. Write failing test for: LoginForm persists ddp_role on submit (login-write seam)
   Test file: `apps/web/src/components/auth-role-offline.test.tsx` (append)
   Level: integration

   Test intent:
   Given `localStorage` empty and a mocked login POST resolving `{ data: { token: 't-token', role: 'finance' } }`
   When LoginForm is rendered and the login form is submitted
   Then:
   - `localStorage['ddp_role'] === 'finance'` AND `localStorage['ddp_token'] === 't-token'`

   Exercise through: rendering `<LoginForm />` (real component), submitting with credentials
   Test doubles: mock/fake: global fetch for `POST /auth/login`; do NOT mock: LoginForm, localStorage
   Expected RED: `ddp_role` is never written (only the token is stored today) → assertion fails

4c. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/components/auth-role-offline.test.tsx --runInBand`

5. Write CHARACTERIZATION test for: Sidebar still calls /auth/me when dummy OFF (no regression)
   Test file: `apps/web/src/components/auth-role-offline.test.tsx` (append)
   Level: integration
   NOTE: this is a REGRESSION GUARD, not a RED test — after step 3's correct OFF-path implementation it will be GREEN immediately. Do not stall waiting for a RED; write it, confirm GREEN, and proceed.

   Test intent:
   Given `ddp_token` present, dummy store OFF, `ddp_role` absent
   When Sidebar renders
   Then:
   - `fetch` IS called with `/auth/me` exactly as before
   - role from the mocked `/auth/me` response drives the nav filter

   Exercise through:
   - Rendering `<Sidebar />` with store OFF and mocked `/auth/me` → `{ data: { role: 'admin' } }`

   Test doubles:
   - mock/fake: fetch for `/auth/me` only
   - do NOT mock: Sidebar, role filter logic

   Expected RED:
   - new OFF-branch code breaks the existing call (e.g., skipped unconditionally) → fetch never called

6. Run test — verify FAIL:
   `cd apps/web && npx jest src/components/auth-role-offline.test.tsx --runInBand`
   Expected failure: `/auth/me` not called when it should be

7. Implement + verify PASS (same files, OFF path = original behavior).

8. Refactor while green (bounded) + re-run (must stay PASS).

9. Commit:
   `git add apps/web/src/components/LoginForm.tsx apps/web/src/components/Sidebar.tsx apps/web/src/components/auth-role-offline.test.tsx`
   `git commit -m "feat(dummy): persist role at login and read offline in Sidebar when dummy"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 1 rule (BLOCKING-1 resolution A: role persisted at login, Sidebar reads localStorage when ON; zero data network while ON).
Preflight: LoginForm.tsx L56-59 (`storeToken(token)` + dispatch `ddp-auth-change` with `{token, role}`); Sidebar.tsx `useSidebarAuth` + `fetchCurrentRole` (L36+) + finance/admin/else filter (keep as-is per resolution I).

## WHY THIS APPROACH
Justification: Two files, one seam (login writes, Sidebar reads). Integration level is required because the GWT is about fetch suppression across two real units — unit tests on either side would pass while the collaboration stays broken.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Sidebar role filter logic must NOT change — keep finance/admin/else branches exactly as-is (driver/sales scope fix is out-of-scope)]
You are implementing offline role resolution for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/components/LoginForm.tsx`, `apps/web/src/components/Sidebar.tsx`, `apps/web/src/components/auth-role-offline.test.tsx` — no other files
Available after: T2 (store exists; test sets it ON/OFF via getState)
Architecture rule: OFF path must behave byte-identically to today (still calls /auth/me). No backend changes.
[RESTATE: Sidebar role filter logic must NOT change — keep finance/admin/else branches exactly as-is]

## DELIVERABLE
Given token + ddp_role=finance with dummy ON, When Sidebar renders, Then fetch never called with /auth/me AND only finance nav items render
Given token with dummy OFF, When Sidebar renders, Then /auth/me IS called and its role drives the nav (no regression)

All tests PASS. Commit exists with message matching `feat(dummy): persist role at login and read offline in Sidebar when dummy`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Zero `/auth/me` calls while Dummy ON (edge-case hunter BLOCKING-1)
  - OFF path unchanged (existing tests `apps/web/src/app/admin/*/page.test.tsx` etc. stay green)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Changing the finance/admin/else filter branches (out-of-scope role fix)
  - Persisting dummy entities (store rule from T2)
  - Backend/API changes

Open question risks:
  - Logout must clear `ddp_role` — T4 (Topbar) owns `handleLogout`; if T4 is not done yet, note the follow-up in the commit body without editing Topbar here

Rollback note:
  - Revert the two file edits; delete the test. Login/Sidebar return to current behavior.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task edits files outside listed scope or alters the role filter branches

---

### Task 4: Topbar toggle UI [depends: T2]

## OBJECTIVE
Render the Mode Dummy toggle in Topbar between the Online badge and Keluar button (visible only when logged in), wired to the Zustand store, defaulting to the persisted flag; handleLogout also clears `ddp_role`.

Files:
- Modify: `apps/web/src/components/Topbar.tsx`
- Test: `apps/web/src/components/Topbar.test.tsx`

Steps:
1. Write failing test for: toggle visible when logged in, flips store, hidden when logged out
   Test file: `apps/web/src/components/Topbar.test.tsx`
   Level: integration

   Test intent:
   Given `localStorage ddp_token` present and store OFF
   When Topbar renders and the toggle is clicked
   Then:
   - a toggle labelled Mode Dummy renders BETWEEN the Online badge and the Keluar button (assert DOM order)
   - after click, `useDummyStore.getState().isDummy === true` and `localStorage['dummy:isDummy'] === '1'`
   Given NO token
   When Topbar renders
   Then:
   - no Mode Dummy toggle renders (same as Keluar being hidden)

   Exercise through:
   - Rendering `<Topbar />` (real component) with the real store

   Test doubles:
   - mock/fake: stub dummy generator (toggle-ON must not throw with empty factory — T6 not done yet); localStorage token set/remove
   - do NOT mock: Topbar, the store

   Expected RED:
   - No toggle element exists → `getByRole('switch', { name: /dummy/i })` throws

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/components/Topbar.test.tsx --runInBand`
   Expected failure: `Unable to find role="switch"` (Testing Library)

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/components/Topbar.tsx` — insert `<button role="switch" aria-checked={isDummy} aria-label="Mode Dummy">` between Online `<span>` and Keluar `<button>`; `onClick` calls `useDummyStore.getState().toggle()`; subscribe to `isDummy` for styling (ON = amber/warning pill, OFF = gray); `handleLogout` additionally removes `ddp_role` + resets store (`toggle OFF` if ON).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/components/Topbar.test.tsx --runInBand`
   Expected: PASS

4b. Write failing test for: logout clears ddp_role and resets dummy OFF
   Test file: `apps/web/src/components/Topbar.test.tsx` (append)
   Level: integration

   Test intent:
   Given `ddp_token` + `ddp_role='admin'` present and store toggled ON
   When the Keluar button is clicked
   Then:
   - `localStorage['ddp_role'] === null` AND `localStorage['ddp_token'] === null`
   - `useDummyStore.getState().isDummy === false` and `localStorage['dummy:isDummy']` cleared

   Exercise through: rendering `<Topbar />` with the real store ON, clicking Keluar
   Test doubles: mock/fake: stub generator; localStorage seed; do NOT mock: Topbar, store
   Expected RED: `ddp_role` survives logout (current handleLogout only clears the token) → assertion fails

4c. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/components/Topbar.test.tsx --runInBand`

5. Refactor while green (bounded) + re-run (must stay PASS).

6. Commit:
   `git add apps/web/src/components/Topbar.tsx apps/web/src/components/Topbar.test.tsx`
   `git commit -m "feat(dummy): add Mode Dummy toggle to Topbar"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 1 (toggle between Online & Keluar, all roles, all envs, persisted, logged-out hidden; logout clears ddp_role per T3 note).
Preflight: Topbar.tsx current markup (Online badge `hidden sm:inline-flex` + conditional Keluar `{hasToken && ...}`).

## WHY THIS APPROACH
Justification: Single component, single seam (store). Integration test proves DOM order requirement (between Online and Keluar) which a unit test on the store cannot.
Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: Toggle renders ONLY when logged in (hasToken) — never for logged-out users]
You are implementing the Topbar toggle for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/components/Topbar.tsx`, `apps/web/src/components/Topbar.test.tsx` — no other files
Available after: T2 (store); parallel with T3
Architecture rule: No new dependencies; Tailwind classes consistent with existing badge/button styles.
[RESTATE: Toggle renders ONLY when logged in (hasToken) — never for logged-out users]

## DELIVERABLE
Given logged in with store OFF, When Topbar renders, Then Mode Dummy switch renders between Online badge and Keluar button
Given the switch clicked, When ON, Then store isDummy true AND localStorage flag set
Given logged out, When Topbar renders, Then no Mode Dummy switch renders

All tests PASS. Commit exists with message matching `feat(dummy): add Mode Dummy toggle to Topbar`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - DOM order: Online badge → toggle → Keluar (asserted in test)
  - `role="switch"` + `aria-checked` for a11y
  - Logout clears `ddp_token`, `ddp_role`, and resets dummy store OFF
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Showing the toggle when logged out
  - Backend calls; new dependencies

Open question risks:
  - none

Rollback note:
  - Revert Topbar.tsx edit; delete test. Store (T2) remains inert without a UI trigger.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task touches files outside listed scope

---

### Task 5: DummyFactory — master data (territories/outlets/products/suppliers) [depends: T1] [parallel: T2]

## OBJECTIVE
Build the deterministic master-data factory: 5 JABODETABEK territories (from T1 seed), ~48 outlets (~9–10 per territory with names/addresses/coords inside territory bbox), ~30 products (SKU, name, category, price), ~8 suppliers. Pure functions taking `(rng, window)`; no store import. This task produces the leaf module; T6's `buildFullDummy(today?)` (in `apps/web/src/dummy/index.ts`) will compose it, so keep the types file-agnostic.

Files:
- Create: `apps/web/src/dummy/factory.ts`
- Test: `apps/web/src/dummy/factory.test.ts`

Steps:
1. Write failing test for: counts, bbox containment, determinism
   Test file: `apps/web/src/dummy/factory.test.ts`
   Level: unit

   Test intent:
   Given `DUMMY_SEED` + a fixed `today` (e.g. 2026-02-14) via T1 utils
   When `buildMasterData()` runs twice
   Then:
   - outlet count is 44..52 (≈9–10 per territory × 5)
   - every outlet has territory in the 5 + lat/lon inside the JABODETABEK bbox + `city` one of the 5 names
   - product count is 28..32, each with unique SKU
   - supplier count is 7..9
   - both runs are deep-equal (determinism)
   - outlet id `dummy-001` is "Toko Bogor Indah" (canonical example from spec Story 2 R2)

   Exercise through:
   - `buildMasterData(rng, window)` from `apps/web/src/dummy/factory.ts`

   Test doubles:
   - mock/fake: none (inject fixed `today`; RNG from T1)
   - do NOT mock: factory, rng, seed

   Expected RED:
   - `apps/web/src/dummy/factory.ts` does not exist → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/factory.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/factory'`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/factory.ts` — `buildMasterData()` composing T1 seed lists; per-outlet jittered coords around territory anchors (still inside bbox); stable string ids `dummy-001...`; types exported (`DummyOutlet`, `DummyProduct`, `DummySupplier`, `DummyTerritory`, `MasterData`).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/factory.test.ts --runInBand`
   Expected: PASS

5. Refactor while green (bounded) + re-run (must stay PASS).

6. Commit:
   `git add apps/web/src/dummy/factory.ts apps/web/src/dummy/factory.test.ts`
   `git commit -m "feat(dummy): add deterministic master-data factory (outlets, products, suppliers)"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R2 (deterministic relational; canonical `dummy-001` "Toko Bogor Indah"); geo bbox; ~48 outlets / ~30 products / ~8 suppliers (Phase 3 locked volumes).
Preflight: T1 (`rng.ts`, `dates.ts`, `seed.ts`) is the only available-after input.

## WHY THIS APPROACH
Justification: Master data is the referential anchor for every transaction and aggregate — transactions (T6) cannot exist without stable outlet/product/supplier ids. Pure functions keep it unit-testable without the store.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Factory must be pure — no store imports, no fetch, no Date.now() inside (today is injected)]
You are implementing the master-data factory for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/factory.ts`, `apps/web/src/dummy/factory.test.ts` — no other files
Available after: T1
Architecture rule: Import ONLY from `apps/web/src/dummy/*` (T1 utils). No backend, no new deps.
[RESTATE: Factory must be pure — no store imports, no fetch, no Date.now() inside (today is injected)]

## DELIVERABLE
Given fixed seed + fixed today, When buildMasterData() runs twice, Then deep-equal results with 44..52 outlets AND 28..32 unique-SKU products AND 7..9 suppliers
Given outlets built, When inspected, Then every outlet inside JABODETABEK bbox AND dummy-001 is Toko Bogor Indah

All tests PASS. Commit exists with message matching `feat(dummy): add deterministic master-data factory (outlets, products, suppliers)`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Canonical `dummy-001` = "Toko Bogor Indah" (spec example other tasks rely on)
  - All coords inside spec bbox; all `city` values one of the 5 names
  - Unique SKUs; deterministic across runs
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Store/fetch/network imports
  - Date.now() inside the factory (inject `today`)
  - Backend changes; new dependencies

Open question risks:
  - none

Rollback note:
  - Delete `factory.ts` + test; T6 depends on it (do not merge T6 without T5).

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task imports outside `apps/web/src/dummy/*` or touches out-of-scope files

---

### Task 6: DummyFactory — transactions + analytics/DI/AI aggregates [depends: T5]

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

---

### Task 7: Read-guard helper + commit-guard helper [depends: T1] [parallel: T2]

## OBJECTIVE
Create the two centralized guard helpers every Phase B call-site uses: `withDummyRead(isDummy, dummyValue, realFetch)` (returns dummy without calling realFetch when ON) and `commitIfCurrent(getIsDummy, expectedFlag, setter, data)` (4 args — best-effort discard: drops data if the flag changed before commit). Plus a `useDummyRefresh` hook so pages re-fetch automatically on toggle (Phase B subscribes to it).

Contract (locked here — must match tests):
- `withDummyRead(isDummy: boolean, dummyValue: T, realFetch: () => Promise<T>): Promise<T>`
- `commitIfCurrent(getIsDummy: () => boolean, expectedFlag: boolean, setter: (data: T) => void, data: T): boolean` (returns whether commit proceeded; calls setter only if `getIsDummy() === expectedFlag`)
- `useDummyRefresh(onChange: () => void): void` (subscribes to `isDummy` flips)

Files:
- Create: `apps/web/src/dummy/guards.ts`
- Test: `apps/web/src/dummy/guards.test.ts`

Steps:
1. Write failing test for: withDummyRead never calls realFetch when ON; commit guard discards on flip
   Test file: `apps/web/src/dummy/guards.test.ts`
   Level: unit (pure helpers — no store/localStorage collaborators)

   Test intent:
   Given `isDummy = true`, a dummy value D, and a realFetch jest.fn() resolving R
   When `withDummyRead(true, D, realFetch)` is awaited
   Then:
   - result deep-equals D
   - realFetch was NEVER called
   Given `getFlag` returning false but `expectedFlag` was `true` (flag flipped since the fetch started)
   When `commitIfCurrent(getFlag, true, setter, data)` runs
   Then:
   - setter was NEVER called (in-flight result discarded)

   Exercise through:
   - `withDummyRead`, `commitIfCurrent` from `apps/web/src/dummy/guards.ts`

   Test doubles:
   - mock/fake: jest.fn() for realFetch and setter
   - do NOT mock: the guard helpers

   Expected RED:
   - file does not exist → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/guards.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/guards'`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/guards.ts` — `withDummyRead(isDummy, dummyValue, realFetch)` (if ON return dummyValue, else return realFetch()); `commitIfCurrent(getIsDummy: () => boolean, expectedFlag: boolean, setter: (data: T) => void, data: T)` (returns boolean; setter only if `getIsDummy() === expectedFlag` — the 4-arg cross-check; this IS the spec's `commitIfDummy` requirement, spec L266); `useDummyRefresh(onChange)` hook subscribing to the store's `isDummy` and invoking `onChange` on flips (used by Phase B pages for auto re-fetch on toggle OFF).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/guards.test.ts --runInBand`
   Expected: PASS

5. Write failing test for: OFF path calls realFetch; commit passes through when flag unchanged
   Test file: `apps/web/src/dummy/guards.test.ts` (append)
   Level: unit (pure helpers; the useDummyRefresh SUBSCRIPTION test in step 6a is the integration one)

   Test intent:
   Given `isDummy = false`
   When `withDummyRead(false, D, realFetch)` is awaited
   Then:
   - result equals R (realFetch's resolution) AND realFetch called exactly once
   Given `getFlag` returning false and `expectedFlag` is false (unchanged)
   When `commitIfCurrent(getFlag, false, setter, data)` runs
   Then:
   - setter called exactly once with data

   Exercise through:
   - same helpers

   Test doubles:
   - mock/fake: jest.fn() for realFetch/setter
   - do NOT mock: helpers

   Expected RED:
   - OFF path returns dummy, or setter skipped despite unchanged flag

6a. Write failing test for: useDummyRefresh fires on flip (subscription to the real store)
   Test file: `apps/web/src/dummy/guards.test.ts` (append)
   Level: integration (subscribes to the Zustand store singleton)

   Test intent:
   Given store OFF and a subscriber registered via `renderHook(() => useDummyRefresh(onChange))`
   When `useDummyStore.getState().toggle()` flips ON then OFF
   Then `onChange` was called exactly twice (once per flip) and no more without a flip

   Exercise through: the real `useDummyRefresh` hook + the real store toggle
   Test doubles: mock/fake: callback jest.fn(); do NOT mock: store, hook
   Expected RED: hook not wired to the store subscription → call count 0

6b. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/dummy/guards.test.ts --runInBand`

7. Refactor while green (bounded) + re-run (must stay PASS).

8. Commit:
   `git add apps/web/src/dummy/guards.ts apps/web/src/dummy/guards.test.ts`
   `git commit -m "feat(dummy): add read-guard and commit-guard helpers"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Implementation Notes (centralized `withDummyRead` + `commitIfDummy` so isDummy is not copy-pasted); Story 1 (auto re-fetch on toggle OFF); BLOCKING-3 resolution P (best-effort discard at commit layer, no AbortController threading).
Preflight: zero `AbortController` in codebase (helpers keep it that way).

## WHY THIS APPROACH
Justification: Shared Helper Pattern (mandatory extraction — Phase B's 4 tasks would otherwise each hand-roll the same two checks; parallel subagents cannot dedupe). The hook gives Phase B pages the auto re-fetch subscription point.
Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: No AbortController threading — discard happens ONLY at the commit layer via the flag comparison]
You are implementing the guard helpers for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/guards.ts`, `apps/web/src/dummy/guards.test.ts` — no other files
Available after: T1; parallel with T2/T5
Architecture rule: Pure helpers + one tiny hook; no backend; no new deps. Phase B tasks MUST import these (QUALITY BAR must-not: reimplementing locally).
[RESTATE: No AbortController threading — discard happens ONLY at the commit layer via the flag comparison]

## DELIVERABLE
Given isDummy true, When withDummyRead runs, Then dummy returned AND realFetch never called
Given isDummy false, When withDummyRead runs, Then real result returned AND realFetch called once
Given flag flipped before commit, When commitIfCurrent runs, Then setter never called
Given flag unchanged, When commitIfCurrent runs, Then setter called once with data

All tests PASS. Commit exists with message matching `feat(dummy): add read-guard and commit-guard helpers`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `withDummyRead` short-circuits BEFORE any network (realFetch never invoked when ON)
  - `useDummyRefresh` subscribes to store `isDummy` and fires on flips
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - AbortController plumbing
  - Backend changes; new dependencies; touching pages/stores

Open question risks:
  - none

Rollback note:
  - Delete `guards.ts` + test; Phase B cannot start without it (declared dependency).

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task touches files outside listed scope

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Scaffold + RNG + dates + seed | prereq | lightweight | Deterministic RNG; 5 territories in bbox; 60-day window |
| T2 | Zustand dummy store | T1 | standard | Toggle persists flag only; OFF clears entities |
| T3 | Role persist + Sidebar offline | T2 | standard | No /auth/me when ON; OFF unchanged |
| T4 | Topbar toggle UI | T2 | lightweight | Switch between Online & Keluar; hidden logged-out |
| T5 | Factory master data | T1 | standard | 44–52 outlets, 28–32 SKUs, 7–9 suppliers; dummy-001 canonical |
| T6 | Factory transactions + aggregates | T5 | deep | 800–1000 orders 1:1 linked; analytics/DI/finance aggregates non-empty; tsc clean |
| T7 | Guard helpers | T1 | lightweight | withDummyRead short-circuits; commit guard discards on flip |
