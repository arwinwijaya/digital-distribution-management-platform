# Task T3 — Role persistence at login (ddp_role) + Sidebar offline role read

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


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
