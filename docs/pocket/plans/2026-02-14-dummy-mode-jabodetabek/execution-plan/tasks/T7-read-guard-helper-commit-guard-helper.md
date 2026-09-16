# Task T7 — Read-guard helper + commit-guard helper

**Phase:** 1
**Depends:** T1] [parallel: T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


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
