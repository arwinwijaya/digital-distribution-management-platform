# EXECUTION PLAN — Dummy Mode JABODETABEK

**Date:** 2026-02-14
**Spec:** docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md

---

## Plan Summary

- Total tasks: 12
- Phases: 3 (Phase A: Foundation + Data, Phase B: Interception, Phase C: Verification)
- Phase A: T1-T7 | Phase B: T8-T11 | Phase C: T12

---

## Pocket Packets


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

---

### Task 8: Guard data-intelligence-api + operations-api [depends: T6, T7]

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

---

### Task 9: Guard admin/* + sales/* API modules [depends: T6, T7]

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

---

### Task 10: Consolidate and guard inline-fetch pages [depends: T6, T7]

## OBJECTIVE
Extract the inline `fetch(apiUrl(...))` calls in pages/components into per-page `api.ts` modules with typed helpers, then guard those helpers with `withDummyRead` and wire `useDummyRefresh` so pages re-fetch real data automatically when the toggle goes OFF. Covers dashboard, analytics, payments (list), delivery (list), INVOICES (page.tsx:47 inline read), operations (read path), sales (read path), ProductCatalog, MarketplaceCatalog, and OrderForm (products catalog read at :16 — POST order write stays in T11).

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
   File: `apps/web/src/app/payments/api.ts` (new) — extract list loader; guard list read ONLY (POST payment create is T11).
   File: `apps/web/src/app/delivery/api.ts` (new) — extract list loader; guard list read ONLY (PATCH status is T11).
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
   When ALL of the following are awaited in one test (LOCKED exported names — T10 must export exactly these):
   `loadInvoices` (invoices/api.ts), `loadDeliveries` (delivery/api.ts), `loadPaymentsList` (payments/api.ts),
   `loadOperations` (operations/api.ts), `loadSalesList` (sales/api.ts), `loadProductCatalog` (ProductCatalog),
   `loadMarketplaceCatalog` (MarketplaceCatalog), `loadOrderFormProducts` + `trackOrder` (OrderForm)
   Then:
   - each resolves to a non-empty, correctly-shaped payload (invoices array; deliveries array; payments list; operations issues; sales visits; products catalog; marketplace suppliers+products; OrderForm catalog; tracked order or a documented null stub for `trackOrder`)
   - global fetch was NEVER called ONCE across all calls (`expect(fetch).not.toHaveBeenCalled()`) — a single cross-loader zero-fetch assertion

   This sweep closes the loader coverage gap — T10 extracts 10 loaders but steps 1–5 only assert dashboard + analytics, and `ProductCatalog`/`MarketplaceCatalog` were previously unnamed. NOTE (audit item): parts of this sweep may already pass via the guards imported from T7; if so, mark the file's header as a regression/characterization guard and move on (do not stall waiting for RED). `loadAnalytics` is covered by step 5, not repeated here.

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
Preflight: `dashboard/page.tsx` has a ~40-line `loadForRole` with role branching + `jakartaDateString` offsets; `analytics/page.tsx` does a 3-way `Promise.all` inline; `payments/page.tsx` has `usePaymentData` (list at :46, POST at :173 → T11); `delivery/page.tsx` fetches inline twice (list + PATCH status → PATCH is T11); `invoices/page.tsx:47` has an inline list read (was MISSED in first draft — added after spec review); `operations/page.tsx` + `sales/page.tsx` (list :28 reads, POST :51 → T11) + `ProductCatalog.tsx` + `MarketplaceCatalog.tsx` each have 1 inline read; `components/OrderForm.tsx` has THREE hits: products catalog read :16, POST :28 (T11), tracking `GET /orders/:id` :36 (T10 — was MISSED in the first review pass).

## WHY THIS APPROACH
Justification: Rule 4 split — this is a distinct layer from T8/T9 (page loaders, not module wrappers) with its own verification (DOM empty-state assertions + auto re-fetch). Integration level is mandatory because the GWT spans loader→store→render.
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
  - `OrderForm.tsx` product-catalog read (:16) AND tracking read (:36) are guarded (POST :28 stays T11)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Changing rendered markup/components (only the data source)
  - Guarding write calls (T11)
  - Backend changes; new dependencies

Open question risks:
  - `delivery/page.tsx` PATCH is a write → leave it for T11 and guard only the list read here
  - Some pages may use local non-exported types → mirror them minimally in the new `api.ts` and note it in the commit body

Rollback note:
  - Revert the page edits and delete the new `api.ts` files; delete the test.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, commit created
Uncertain when: a page's role branching cannot be preserved without touching markup (flag NEEDS_CONTEXT)
Escalate when: out-of-scope files are touched or a write path is guarded here

---

### Task 11: Fake mutation mutators + write-path guards [depends: T6, T7]

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
- Modify: `apps/web/src/lib/data-intelligence-api.ts` (`sendFunnelEvent` POST only — reads are T8's)

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
   File: `apps/web/src/dummy/mutations.ts` — pure-ish mutators that take the current entities and return the next entities (plus store-bound wrappers): `createDummyOrder`, `updateDummyDelivery(deliveryId, status, proof?)`, `updateDummyProductPrice`, `createDummyPromotion` / `updateDummyPromotion` / `deleteDummyPromotion` / `broadcastDummyPromotion`, `assignDummyUserRole`, `updateDummyOutlet`, `sendDummyFunnelEvent` (T8's read counterpart left unguarded here — THIS task owns the funnel POST), `createDummyPayment` (for `payments/page.tsx:173`), `createDummyVisit` (for `sales/page.tsx:51`), `createDummyOutletOrder` (for `OrderForm.tsx:28`; wraps the same 1:1 order → payment/invoice/delivery linkage). IDs: string prefixed `dummy-`; numeric → negative counter.

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
Given store ON at /orders (OrderForm path), When the order form submits, Then the same 1:1 linkage holds AND zero fetch to `/orders` or `/products` (catalog read was guarded in T10)
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
  - Guarding read functions or page loaders (T9/T10 own those)
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
| T8 | Guard DI + operations APIs | Phase A T6/T7 | standard | Dummy aggregates returned, zero fetch; `FetchResult` envelope preserved |
| T9 | Guard admin/* + sales/* reads | Phase A T6/T7 | standard | Shape parity + filters applied; zero fetch |
| T10 | Consolidate + guard inline pages | Phase A T6/T7 | deep | Analytics non-empty, no empty-state text, auto re-fetch on OFF, invoices + OrderForm catalog guarded |
| T11 | Fake mutations + write guards | Phase A T6/T7 | deep | `dummy-` ids, 1:1 side-effects, ephemeral, zero network across 10 write call-sites |

---

### Task 12: Cross-unit Dummy Mode integration verification [depends: T6, T7, T8, T9, T10, T11]

## OBJECTIVE
Prove the three story acceptance criteria end-to-end (GWT level) by toggling real components, loading every guarded helper against the real Zustand store, and asserting zero network + no empty-state text + ephemeral mutations — all without reaching the backend. Also verify funnel tracking is faked (Story 2-F / Story 3-E) and finance dashboard is populated (Story 2-E).

Files:
- Create: `apps/web/__tests__/dummy-mode.e2e.test.tsx`
- Modify (only if needed): `apps/web/src/components/Topbar.tsx` — accessible label fix

Steps:
1. Write failing test for: analytics populated + geo points + order→payment→invoice side-effects
   Test file: `apps/web/__tests__/dummy-mode.e2e.test.tsx`
   Level: integration

   Test intent:
   Given `localStorage ddp_token` + `ddp_role='admin'`, dummy store OFF, jest.fn() global fetch returning a stub, and the real `Topbar` rendered
   When the Mode Dummy switch is toggled ON
   Then:
   - Analytics loaders return non-empty recommendations, 4-period forecast, and non-empty segmentation; rendering the analytics page shows NEITHER `Belum ada rekomendasi` NOR `Data belum cukup`
   - Data-Intelligence GeoMap `map_points` length is 40..60 with every point inside the JABODETABEK bbox and `table.length === 5`
   - Finance dashboard (`loadDashboard(token, 'finance')`) returns ALL FinanceMetrics keys (issued_invoices, outstanding_balance, overdue_rate, collection_time, payment_status_breakdown, reminders) — not just the admin shape
   - Submitting an order via `createSalesOrder(token, payload)` creates exactly one order with a `dummy-` id, one payment, one invoice, one delivery, and global fetch was NEVER called
   - `sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 })` returns fake success with shape `{ event_uuid: string, event_type: 'clicked', outlet_id: 1, product_id: 2 }` AND global fetch was NEVER called (Acceptance Criteria: Story 2, sendFunnelEvent fake success). NOTE: real signature is `(event: FunnelEventType, context?: FunnelEventContext, eventUuid?: string)` where `FunnelEventType = 'displayed' | 'clicked' | 'cart'` and `FunnelEventContext = { outlet_id?: number|null, product_id?: number|null, occurred_at?: string }`. Not `(token, payload)`.

   Suite hygiene: `beforeEach` clears localStorage, re-seeds `ddp_token`/`ddp_role`, resets the dummy store + registers a fixed-today generator (`setDummyGenerator((today?) => buildFullDummy(fixedToday ?? new Date('2026-02-14')))`) so the determinism assertions below have a known pin.

   Exercise through:
   - Real `Topbar` (goal-level UI entry point per spec — Topbar toggle is the user's affordance)
   - Real loader functions `loadAnalytics` (from T10) + `fetchGeographicData` (from T8) + `createSalesOrder` (from T11)
   - Real `useDummyStore` (toggled by the switch click, not by `getState().toggle()` directly)

   Test doubles:
   - mock/fake: global fetch (assert NEVER called while ON); localStorage `ddp_token`/`ddp_role`; jsdom
   - do NOT mock: Topbar, loaders, api modules, guards, store, factory

   Expected RED:
   - Any success signal unmet (e.g. `map_points` empty, analytics empty, missing side-effects) → assertion fails

2. Run test — verify FAIL:
   `cd apps/web && npx jest __tests__/dummy-mode.e2e.test.tsx --runInBand`
   Expected failure: any signal assertion fails (or the modules are not yet wired in a local checkout — still FAIL before GREEN)

3. Implement app fixes ONLY to make the signals pass, scoped to the described gap:
   - If the Topbar has no Dummy affordance at all (no switch containing 'dummy' text/label while logged in), fix by adding the label per Phase A T4's design — this must NOT happen if T4 passed. Otherwise leave Topbar untouched (do NOT invent an exact badge string — spec only says the toggle sits 'antara Online & Keluar').
   - Any other signal failure that surfaces as "real data leaked while ON" or "empty analytics array" or "side-effect missing" must already be covered by Phase A/Phase B helpers — do NOT re-edit helpers here beyond re-running those tasks; if the helper fix scope grows beyond one file, escalate NEEDS_CONTEXT and leave the test RED with an inline comment naming the responsible helper and its spec line.

4. Run test — verify PASS:
   `cd apps/web && npx jest __tests__/dummy-mode.e2e.test.tsx --runInBand`
   Expected: PASS

5. Write failing test for: refresh persistence, auto re-fetch on OFF, zero network invariant
   Test file: `apps/web/__tests__/dummy-mode.e2e.test.tsx` (append)
   Level: integration

   Test intent:
   Given the store toggled ON and a mutated dummy order exists
   When "refresh" is simulated (remount `Topbar` with `localStorage['dummy:isDummy']` still set, clear the store singleton, and re-init) and then toggle OFF
   Then:
   - after remount, the store restores `isDummy === true` from localStorage AND `dummyEntities` is non-null (regenerated by the init path, per T2 store spec) — this is Story 1 B: refresh renders the same deterministic data, not just an empty state
   - calling `loadAnalytics(token)` after the remount returns non-empty recommendations (verifying data renders, not just the flag)
   - after toggle OFF, `dummyEntities === null` and the subsequent loader re-fetch DOES call global fetch (real data return — spec: toggle OFF auto re-fetches without navigation, entities never persisted)
   Given store ON with jest.fn() global fetch, When ALL guarded reads are issued in a single cross-unit sweep:
   `loadAnalytics`, `loadDashboard(t, 'admin')`, `loadDashboard(t, 'finance')`, `fetchGeographicData`, `fetchSupplierPerformanceData`, `fetchStockPlanningData`, `fetchRecommendationMeasurementData`, `fetchForecastMeasurementData`,
   `fetchReadiness`, `fetchIssues`, `fetchIssueDetail`,
   `fetchAdminOutlets`, `fetchAdminUsers`, `fetchProducts`, `fetchPromotions`, `fetchAdminSalesPerformance`,
   `fetchSalesOutlets`, `fetchCatalogProducts`, `fetchMyPerformance`,
   plus page loaders: `loadInvoices`, `loadDeliveries`, `loadPaymentsList`, `loadOperations`, `loadSalesList`, `loadProductCatalog`, `loadMarketplaceCatalog`, `loadOrderFormProducts`, `trackOrder`, `fetchOutletOrders(t, 1)`, `fetchOutletSummary(t, 1)`, `fetchPriceHistory(t, 1)`,
   Then:
   - global fetch call count is exactly 0 (cross-unit zero-network invariant covering every guarded call-site in Phases A+B)

   Exercise through:
   - Same real components/loaders + a fresh jsdom mount (simulated refresh) + the store's `persist`-to-localStorage path

   Test doubles:
   - mock/fake: global fetch; localStorage; jsdom remount
   - do NOT mock: Topbar, loaders, helpers, store

   Expected RED:
   - Refresh loses the flag (reads `ddp_role` but not `isDummy`), or mutations survive toggle OFF

6. Run test — verify FAIL / then fix the described gap (scoped like step 3) / verify PASS.

7. Write failing test for: determinism after toggle cycle (refresh-persistent identity)
   Test file: `apps/web/__tests__/dummy-mode.e2e.test.tsx` (append)
   Level: integration

   Test intent:
   Given a fixed `today` (2026-02-14) pinned by registering `setDummyGenerator((today?) => buildFullDummy(today ?? new Date('2026-02-14')))` BEFORE the first toggle — this is the ONLY seam by which `today` reaches the factory through the real `toggle()` path (the store's `toggle()` calls `generate()` with no args; the generator itself defaults to the pinned date)
   When ON → OFF → ON is toggled
   Then:
   - the second ON produces byte-identical aggregates to the first ON (deterministic per the fixed seed + rolling window contract)
   - outlet `dummy-001` still reads "Toko Bogor Indah" on the second cycle

   Exercise through:
   - `useDummyStore.getState().toggle()` cycles; compare the two `dummyEntities` snapshots

   Test doubles:
   - mock/fake: localStorage; the real factory wrapped by the fixed-today generator (NOT a fake of the factory's output)
   - do NOT mock: store, factory

   Expected RED:
   - Second cycle differs (Date.now()-seeded RNG or non-deterministic window) → equality fails

8. Run full web suite to confirm zero regressions:
   `cd apps/web && npx jest --runInBand`
   Expected: all pre-existing + new tests PASS

9. Refactor `dummy-mode.e2e.test.tsx` only (bounded — no app refactors in this phase) + re-run (must stay PASS).

10. Commit:
   `git add apps/web/__tests__/dummy-mode.e2e.test.tsx`
   `git commit -m "test(dummy): add cross-unit dummy-mode integration coverage"`
   If Topbar label was fixed:
   `git add apps/web/src/components/Topbar.tsx`
   `git commit -m "fix(dummy): add accessible label to Mode Dummy toggle"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Acceptance Criteria (Story 1 GWT, Story 2 GWT, Story 3 GWT) and Stories + Scenarios; Story 3 R1/R3/R4 (zero network, 1:1 side-effects, ephemeral); Story 1 (toggle OFF auto re-fetch, refresh persistence, localStorage survival; Story 1 LOGOUT clears ddp_role); Story 2 R2/R3 (determinism, analytics non-empty) — see also Phase A `T1`/`T2`/`T6` semantics registered as blocker-driven bridges.
Preflight: goal-level UI entry point is `Topbar.tsx` (per spec); Phase B `T10` owns `loadAnalytics`; Phase A `T1` owns the fixed `DUMMY_SEED` (determinism); Phase A `T2` owns persist-`isDummy`-only. Jest reads `apps/web/jest.config.js`.

## WHY THIS APPROACH
Justification: System C-2 cross-unit integration (real Topbar + real loaders/store/helpers) is the only level where the three story acceptance criteria compose — unit tests on helpers pass while end-to-end toggles stay broken. The "fix scope bounded to one label" constraint prevents this verifier from turning into a second Phase B; deeper failures escalate as NEEDS_CONTEXT so the full suite confirms unit-test quality.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Do NOT edit helpers/pages here — if the test fails because a helper is wrong, flag NEEDS_CONTEXT naming the helper + spec line instead of patching Phase B inline]
You are writing the cross-unit verifier for Dummy Mode JABODETABEK — the final gate before HANDOFF TO DELIVERY.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/__tests__/dummy-mode.e2e.test.tsx` and — ONLY if the badge text deviates — `apps/web/src/components/Topbar.tsx` (one-line label fix) — no other file
Available after: Phase A T6 + T7 and every Phase B task (T1–T4) complete and green
Architecture rule: Keep helpers/pages read-only in this phase. Escalate with NEEDS_CONTEXT rather than bulk-fixing Phase A/B inline.
[RESTATE: Do NOT edit helpers/pages here — if the test fails because a helper is wrong, flag NEEDS_CONTEXT naming the helper + spec line instead of patching Phase B inline]

## DELIVERABLE
Given store toggled ON via the real Topbar switch, When analytics + GeoMap + order submit + funnel loaders run, Then ALL three story acceptance criteria pass AND global fetch count is 0
Given store ON with a mutation, When refresh simulated then toggle OFF, Then flag restored AND dummyEntities deep-equals the first ON entities (not just non-null) after remount, with analytics populated AND entities null after OFF AND subsequent loader real-fetches
Given all guarded reads (analytics + DI reads + ops + admin/sales + all page loaders ≈ 27 call-sites), When inspected, Then global fetch call count is 0 (cross-unit zero-network invariant)
Given fixed today (pinned via generator registration), When ON → OFF → ON toggled, Then second ON produces deep-equal entities to first ON AND dummy-001 is Toko Bogor Indah (determinism)

All tests PASS. Full `npx jest` suite green. Commit(s) exist with messages matching `test(dummy): add cross-unit dummy-mode integration coverage` (+ optional `fix(dummy): add accessible label to Mode Dummy toggle`).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Goal-level entry point: real `Topbar` rendering + switch click
  - Three story acceptance criteria verified against real loaders/modules (not stubs)
  - Zero-network asserted with a `jest.fn()` global fetch at the cross-unit level
  - Ephemerality proven by the toggle cycle (no `dummy-` records survive)
  - Refresh persistence proven by remount reading `localStorage['dummy:isDummy']` AND producing deep-equal entities (not just non-null)
  - Zero-network sweep covers all guarded read call-sites (not just 4 loaders) — aim for ≥25 in a single `expect(fetch).toHaveBeenCalledTimes(0)`
  - Determinism asserted across the toggle cycle (same seed + same today → equal)
  - Full suite green (regression guard for earlier phases)
  - Tests written BEFORE app fixes (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Bulk edits to helpers/pages (escalate instead)
  - Backend/API/DB changes; new dependencies
  - `Date.now()` inside test determinism assertions (inject today's value)

Open question risks:
  - Topbar badge text is NOT spec-prescribed (spec only positions the toggle "antara Online & Keluar") — so the "only if needed" branch rarely fires; if the affordance exists, make no Topbar edit at all
  - Dummy operations issue detail remains non-navigable by design (do NOT test navigating to it as part of story acceptance criteria; leave it as a D/CARVE-OUT validated at the T8 unit level)

Rollback note:
  - Delete `apps/web/__tests__/dummy-mode.e2e.test.tsx`; revert the optional Topbar line. Phase A/B behavior is unaffected.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, commit created
Uncertain when: the story acceptance criteria compose differently than phrased because a loader name differs (adapt the loader target, note it in the test header comment, and proceed)
Escalate when: a failure points to a Phase A/B helper bug that would require editing `apps/web/src/dummy/*` or `apps/web/src/lib/*` or `apps/web/src/app/*/*` beyond the allowed one-line Topbar label fix — return NEEDS_CONTEXT with helper path + spec line + observed vs expected

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Cross-unit integration verifier | Phase A T6+T7, Phase B T8..T11 | standard | 3 story acceptance criteria + funnel + finance dashboard, zero network, ephemerality, refresh persistence, determinism |

---

## Plan Summary

12 tasks across 3 phases.
