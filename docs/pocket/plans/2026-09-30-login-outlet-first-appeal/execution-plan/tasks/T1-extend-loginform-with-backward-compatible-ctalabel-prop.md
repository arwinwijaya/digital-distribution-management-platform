# Task T1 — Extend LoginForm with backward-compatible ctaLabel prop

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Extend LoginForm with backward-compatible ctaLabel prop [prereq] [test-risk]

## OBJECTIVE
Add an optional `ctaLabel?: string` prop to `apps/web/src/components/LoginForm.tsx`, defaulting to `'Masuk'`, and use it as the submit button label. Preserve auth call shape, token storage, `ddp_role`, `ddp-auth-change`, inline errors, loading state, and `expectedRole` enforcement. Because several behaviors already exist, regression-lock cycles use an explicit mutation-check RED: write the characterization test, confirm it passes against current behavior, temporarily break the relevant behavior to prove the test fails, revert the temporary mutation, then keep/fix the permanent implementation.

Steps:
1. Write failing test for: default CTA preserved and custom CTA renders
   Test file: `apps/web/src/components/LoginForm.test.tsx`
   Level: integration (React Testing Library, jsdom)
   Test intent: Given `LoginForm` with only `onLogin`, When rendered, Then the submit button name is `Masuk`; Given `LoginForm ctaLabel="Masuk & pesan ulang"`, When rendered, Then the submit button name is `Masuk & pesan ulang`
   Exercise through: rendered `LoginForm` public props
   Test doubles: mock `global.fetch`; do NOT mock `LoginForm` or `@/lib/api`
   Expected RED: custom CTA fails today because the submit label is hard-coded to `Masuk`
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/LoginForm.test.tsx`
3. Implement `ctaLabel?: string` with default `'Masuk'` and wire it into the submit button; run test — verify PASS; refactor while green; commit: `feat(web): add optional ctaLabel prop to LoginForm`

4. Write characterization test for: successful login persists auth state and request shape
   Test file: `apps/web/src/components/LoginForm.test.tsx`
   Level: integration
   Test intent: Given valid credentials and a mocked `POST /auth/login` returning `{ data: { token, user: { role } } }`, When the user submits, Then exactly one fetch call is made to `apiUrl('/auth/login')` with method `POST`, JSON headers, and body `{ email, password }`; `ddp_token` and `ddp_role` are written; `ddp-auth-change` fires with `{ token, role }`; and `onLogin(token, role)` is called once
   Exercise through: form submit
   Test doubles: mock `global.fetch`; spy on `window.dispatchEvent`; do NOT mock `@/lib/api` or `LoginForm`
   Expected RED: mutation-check RED — after writing the test, temporarily remove `storeToken`/`ddp_role`/event dispatch or change request method, run the test and verify FAIL; revert the temporary mutation before permanent work
5. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/components/LoginForm.test.tsx`
6. Keep/fix implementation so the test passes; run test — verify PASS; refactor while green; commit: `test(web): lock LoginForm auth persistence and request shape`

7. Write characterization test for: invalid credentials (non-OK response) show inline alert, retain values, and do not persist auth
   Test file: `apps/web/src/components/LoginForm.test.tsx`
   Level: integration
   Test intent: Given `POST /auth/login` resolves with non-OK and a message, When the user submits, Then `role="alert"` shows that message, email/password inputs retain values, `ddp_token` and `ddp_role` remain absent, `ddp-auth-change` is not fired, `onLogin` is not called, and exactly one `/auth/login` request with the expected shape was sent
   Exercise through: form submit
   Test doubles: mock `global.fetch`; spy on `window.dispatchEvent`; do NOT mock the unit under test
   Expected RED: mutation-check RED — temporarily call `storeToken`/`onLogin` in the error branch or clear input values, run the test and verify FAIL; revert the temporary mutation
8. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/components/LoginForm.test.tsx`
9. Keep/fix implementation; run test — verify PASS; refactor while green; commit: `test(web): lock LoginForm credential error contract`

10. Write characterization test for: network failure and 5xx show generic inline alert, retain values, and do not persist auth
    Test file: `apps/web/src/components/LoginForm.test.tsx`
    Level: integration
    Test intent: Given `POST /auth/login` rejects (network) and separately resolves with status 500, When the user submits, Then an inline `role="alert"` is shown (generic message, not a crash), email/password retain values, `ddp_token`/`ddp_role` remain absent, `ddp-auth-change` is not fired, `onLogin` is not called, and request count/shape is correct for the attempted submit
    Exercise through: form submit
    Test doubles: mock `global.fetch` with a rejected promise and with a 500 response; spy on `window.dispatchEvent`
    Expected RED: mutation-check RED — temporarily rethrow in `catch`, persist a token in `catch`, or clear inputs, run the test and verify FAIL; revert the temporary mutation
11. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/components/LoginForm.test.tsx`
12. Keep/fix implementation; run test — verify PASS; refactor while green; commit: `test(web): lock LoginForm network and server error contract`

13. Write characterization test for: loading state prevents double submit
    Test file: `apps/web/src/components/LoginForm.test.tsx`
    Level: integration
    Test intent: Given a pending login request, When the user submits twice, Then the submit button is disabled, label is `Memproses...`, and only one fetch call is made
    Exercise through: form submit and button state
    Test doubles: mock `global.fetch` with a controllable deferred promise
    Expected RED: mutation-check RED — temporarily remove `disabled={loading}` or allow repeated submit, run the test and verify FAIL; revert the temporary mutation
14. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/components/LoginForm.test.tsx`
15. Keep/fix implementation; run test — verify PASS; refactor while green; commit: `test(web): lock LoginForm loading single-submit behavior`

16. Write characterization test for: expectedRole mismatch rejects and stores nothing
    Test file: `apps/web/src/components/LoginForm.test.tsx`
    Level: integration
    Test intent: Given `LoginForm expectedRole="outlet"` and mocked login returning role `admin`, When submitted, Then inline `role="alert"` role error appears, `onLogin` is not called, `ddp_token`/`ddp_role` remain absent, `ddp-auth-change` is not fired, and the request shape to `/auth/login` remains unchanged
    Exercise through: form submit
    Test doubles: mock `global.fetch`; spy on `window.dispatchEvent`
    Expected RED: mutation-check RED — temporarily bypass the `expectedRole` check or persist before validating role, run the test and verify FAIL; revert the temporary mutation
17. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/components/LoginForm.test.tsx`
18. Keep/fix implementation; run test — verify PASS; refactor while green; commit: `test(web): lock LoginForm expectedRole mismatch guard`

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then. Temporary mutations for characterization RED must never be committed.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md` — rules: Form universal + CTA semua role; Auth behavior preserved.
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: Single shared component, but it owns the auth/request/persistence seam and is reused by marketplace; regression risk and network/localStorage behavior justify standard complexity.

## SANDWICH CONTEXT
[CRITICAL: Presentation layer only — do not touch API routes, controllers, auth contract, RBAC, or data layer. Add no dependencies.]
You are implementing the backward-compatible `ctaLabel` prop and preserving the `LoginForm` auth contract.
Spec: docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md
Design decision: Option A — `/login` owns the hero; `LoginForm` gains only optional `ctaLabel` defaulting to `"Masuk"`.
Files in scope: `apps/web/src/components/LoginForm.tsx`, `apps/web/src/components/LoginForm.test.tsx`
Available after: none (prereq)
Architecture rule: Existing `LoginForm` consumers behave identically when `ctaLabel` is absent.
[RESTATE: Presentation layer only — do not touch API routes, controllers, auth contract, RBAC, or data layer.]

## DELIVERABLE
Given default and custom CTA props, When rendered, Then default label remains `Masuk` and custom label renders `Masuk & pesan ulang`
Given valid credentials, When submitted, Then exactly one correctly-shaped `/auth/login` request is sent, auth state persists, event dispatches, and `onLogin` fires once
Given invalid credentials, When submitted, Then inline error appears, fields remain, no auth state persists, no event dispatches, and `onLogin` does not fire
Given network failure or 5xx, When submitted, Then inline generic error appears, fields remain, no auth state persists, no event dispatches, and `onLogin` does not fire
Given pending request, When submitted twice, Then the button is disabled with `Memproses...` and only one request is sent
Given `expectedRole="outlet"` plus admin role response, When submitted, Then role error appears and no auth state persists
[must-not] Given any submission, When it occurs, Then `LoginForm` must NOT introduce new network calls or change the `/auth/login` request shape

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `ctaLabel` optional with default `'Masuk'`
  - Request method/headers/body to `/auth/login` unchanged
  - Failure branches prove negative persistence (`ddp_token`, `ddp_role`, event, `onLogin`)
  - Mutation-check RED used for characterization locks; temporary mutations never committed
  - Conventional commits
Must-not-have:
  - API/auth/RBAC/data changes
  - New dependencies
  - Outlet CTA hard-coded as global default
Open question risks:
  - none
Rollback note:
  - Revert `LoginForm.tsx` and `LoginForm.test.tsx`; no migrations or contracts
Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Default label changed away from `Masuk` → STOP

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, `cd apps/web && npx jest src/components/LoginForm.test.tsx` is green, and no out-of-scope files changed
Uncertain when: the request/persistence contract cannot be preserved without touching API/auth layers
Escalate when: any layer boundary or auth contract constraint is breached
