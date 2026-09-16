# Task T4 — Topbar toggle UI

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


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
