# EXECUTION PLAN — Outlet-First Login Appeal

**Date:** 2026-09-30
**Spec:** docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md
**Status:** draft
**Total tasks:** 2

---

## Execution Overview

### Recommended Order
```
T1 → T2
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| — | none | T2 depends on T1's `ctaLabel` prop contract |

### Constraints Reminder
**Architecture:** presentation layer only — no API routes, controllers, auth contract, RBAC, or data layer changes; reuse existing UI components and Tailwind utilities; no new dependencies.
**Out-of-scope:** personal stock/promo claims pre-login; product recommendations; one-click reorder; `/orders` changes; A/B testing/analytics/feature flags; new UI dependencies.
**Assumptions at risk:** none — all discovery questions resolved.
**Sequencing:** `[depends: T1]` on T2 is a genuine prerequisite because T2 passes `ctaLabel` to `LoginForm`.

### File Structure Map

```
Rule: Hero outlet-first (desktop)
  Modify: apps/web/src/app/login/page.tsx
  Test:   apps/web/src/app/login/page.test.tsx            (created by: T2)

Rule: Form-first (mobile)
  Modify: apps/web/src/app/login/page.tsx
  Test:   apps/web/src/app/login/page.test.tsx            (created by: T2)

Rule: Form universal + CTA semua role
  Modify: apps/web/src/components/LoginForm.tsx
  Modify: apps/web/src/app/login/page.tsx
  Test:   apps/web/src/components/LoginForm.test.tsx      (created by: T1)
  Test:   apps/web/src/app/login/page.test.tsx            (created by: T2)

Rule: Auth behavior preserved
  Modify: apps/web/src/components/LoginForm.tsx
  Test:   apps/web/src/components/LoginForm.test.tsx      (created by: T1)
  Test:   apps/web/src/app/login/page.test.tsx            (created by: T2)

Rule: Demo credentials subtle
  Modify: apps/web/src/app/login/page.tsx
  Test:   apps/web/src/app/login/page.test.tsx            (created by: T2)
```

Note: `(created by: T<N>)` marks files that do not exist until T<N> runs. T2 may import `@/components/LoginForm`, which exists and has been extended by T1.

---

## Pocket Packets

---

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

---

### Task 2: Outlet-first hero + form-first layout on /login [depends: T1] [test-risk]

## OBJECTIVE
Rewrite `apps/web/src/app/login/page.tsx` presentation into an outlet-first login: story + three benefits, honest ability-framed copy, form-first source order for mobile, responsive side-by-side desktop class contract, CTA `Masuk & pesan ulang`, and subtle demo footer. Preserve session-bypass, stale-token fallback, URL-discriminated fetch behavior, `roleDestination`, `redirect` param handling, `router.refresh()`, and Suspense wrapper. Do not mock `LoginForm` in page tests; cross-unit behavior must be exercised through the real form.

Steps:
1. Write failing test for: hero + CTA render with responsive class contract
   Test file: `apps/web/src/app/login/page.test.tsx`
   Level: integration (RTL, jsdom)
   Test intent: Given `/login` rendered with no stored token, When the page renders, Then outlet story + three benefits are shown, submit button is `Masuk & pesan ulang`, and the hero/form wrapper exposes an explicit responsive class contract (e.g. single column base + `lg:grid-cols-2`) with hero and form as siblings; note jsdom cannot prove physical scroll, so this test asserts the implementation contract that prevents displacement
   Exercise through: rendered `LoginPage` with real `LoginForm`
   Test doubles: mock `next/navigation`; mock `global.fetch` with URL-discriminating implementation; do NOT mock `LoginForm`
   Expected RED: current page has no hero, no reorder CTA, and no responsive hero/form wrapper
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
3. Implement hero, benefits, responsive wrapper, and `ctaLabel="Masuk & pesan ulang"`; run test — verify PASS; refactor while green; commit: `feat(web): outlet-first hero and reorder CTA on login page`

4. Write failing test for: honest copy, no personal claims
   Test file: `apps/web/src/app/login/page.test.tsx`
   Level: integration
   Test intent: Given unauthenticated `/login`, When rendered, Then copy uses ability framing such as `Setelah masuk Anda bisa…` and does not contain personal stock/promo/recommendation claims such as `stok Anda`, `promo Anda`, or outlet-specific inventory language
   Exercise through: rendered `LoginPage`
   Test doubles: mock `next/navigation`; URL-discriminating `global.fetch`
   Expected RED: current page has no ability-framed hero copy
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
6. Add honest ability-framed copy; run test — verify PASS; refactor while green; commit: `feat(web): honest ability-framed hero copy on login page`

7. Write failing test for: form-first mobile source order with no visual order reversal
   Test file: `apps/web/src/app/login/page.test.tsx`
   Level: integration
   Test intent: Given `/login` rendered, When DOM/source order is inspected, Then branding/form/submit appear before benefit list, and the wrapper/children do not use Tailwind `order-*` classes that would visually reverse this order on mobile
   Exercise through: rendered `LoginPage` DOM order and class contract
   Test doubles: mock `next/navigation`; URL-discriminating `global.fetch`
   Expected RED: current page does not provide outlet benefit list/order contract
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
9. Order markup form-first and avoid `order-*` reversal; run test — verify PASS; refactor while green; commit: `feat(web): form-first mobile login layout`

10. Write failing test for: demo credentials are subtle footer help
    Test file: `apps/web/src/app/login/page.test.tsx`
    Level: integration
    Test intent: Given `/login` rendered, When inspecting source order, Then demo credentials appear in a small footer/help region after the submit button, not in the primary CTA/form header region and not before the submit button
    Exercise through: rendered `LoginPage`
    Test doubles: mock `next/navigation`; URL-discriminating `global.fetch`
    Expected RED: current demo credentials are prominent below the card and not explicitly subtle footer help
11. Run test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
12. Move demo credentials to subtle footer/help text; run test — verify PASS; refactor while green; commit: `feat(web): move demo credentials to subtle login footer`

13. Write characterization test for: valid stored token bypasses login form
    Test file: `apps/web/src/app/login/page.test.tsx`
    Level: integration
    Test intent: Given a valid stored token and URL-discriminating mocked `GET /auth/me` returning role `outlet`, When `/login` renders, Then `router.replace('/orders')` fires and no usable login form is presented
    Exercise through: rendered `LoginPage` + mocked router
    Test doubles: mock `next/navigation`; mock `global.fetch` branching on `/auth/me` vs `/auth/login`
    Expected RED: mutation-check RED — temporarily disable the stored-token effect or route outlet to dashboard, run test and verify FAIL; revert temporary mutation
14. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
15. Keep/fix implementation; run test — verify PASS; refactor while green; commit: `test(web): lock login page session bypass`

16. Write characterization test for: stale/invalid stored token falls back to usable login form
    Test file: `apps/web/src/app/login/page.test.tsx`
    Level: integration
    Test intent: Given a stored token but `GET /auth/me` rejects or returns non-OK, When `/login` renders, Then no redirect occurs, loading ends, and the login form with CTA `Masuk & pesan ulang` is usable
    Exercise through: rendered `LoginPage`, mocked router, form presence
    Test doubles: mock `next/navigation`; URL-discriminating `global.fetch`
    Expected RED: mutation-check RED — temporarily leave `ready` false or redirect on failed `/auth/me`, run test and verify FAIL; revert temporary mutation
17. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
18. Keep/fix implementation; run test — verify PASS; refactor while green; commit: `test(web): lock login page stale token fallback`

19. Write integration test for: full outlet submit through page uses real LoginForm and preserves cross-unit side effects
    Test file: `apps/web/src/app/login/page.test.tsx`
    Level: integration
    Test intent: Given no stored token and mocked `POST /auth/login` returning outlet role, When the user submits through the page's real `LoginForm`, Then exactly one correctly-shaped `/auth/login` request is sent, `ddp_token`/`ddp_role` persist, `ddp-auth-change` fires, `router.replace('/orders')` is called, and `router.refresh()` is called
    Exercise through: `LoginPage` → real `LoginForm` → mocked fetch → router
    Test doubles: mock `next/navigation`; URL-discriminating `global.fetch`; spy on `window.dispatchEvent`; do NOT mock `LoginForm`
    Expected RED: current page does not pass the new CTA/hero contract and no page test proves this cross-unit submit path
20. Run test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
21. Keep/fix page wiring; run test — verify PASS; refactor while green; commit: `test(web): lock login page outlet submit integration`

22. Write characterization test for: roleDestination matrix and redirect param contract
    Test file: `apps/web/src/app/login/page.test.tsx`
    Level: integration
    Test intent: Given valid mocked login responses for `admin`, `finance`, `platform_owner`, `driver`, and `sales`, When each submits through `/login`, Then redirects are `/dashboard`, `/dashboard`, `/dashboard`, `/delivery`, and `/sales/orders`; Given `redirect=/admin/supply-chain` and admin login, Then redirect is `/admin/supply-chain` via `roleDestination(role, redirectParam)`; in every case `router.refresh()` is called after replace
    Exercise through: rendered `LoginPage` + real form submit
    Test doubles: mock `next/navigation` with configurable `useSearchParams`; URL-discriminating `global.fetch`
    Expected RED: mutation-check RED — temporarily hard-code dashboard, remove redirectParam fallback, or remove `router.refresh()`, run test and verify FAIL; revert temporary mutation
23. Run mutation-check test — verify FAIL: `cd apps/web && npx jest src/app/login/page.test.tsx`
24. Keep/fix `roleDestination`, redirectParam wiring, and refresh call; run test — verify PASS; refactor while green; commit: `test(web): lock login page role redirect matrix`

> Test **intent** only — never test source code. The implementer writes the test during the RED step. Temporary mutations for characterization RED must never be committed.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md` — all rules and GWT scenarios for hero, form-first mobile, honest copy, universal form, auth behavior, and demo credentials.
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: The task spans page presentation plus real `LoginForm` collaboration, mocked Next navigation, URL-discriminated fetch seams, and session/redirect behavior.

## SANDWICH CONTEXT
[CRITICAL: Presentation layer only — do not touch API routes, controllers, auth contract, RBAC, or data layer. Preserve session-bypass, stale-token fallback, `roleDestination`, `redirect` param, and `router.refresh()`.]
You are implementing the outlet-first hero and form-first layout for `/login`.
Spec: docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md
Design decision: Option A — inline hero in `/login/page.tsx`; `LoginForm` receives only `ctaLabel`.
Files in scope: `apps/web/src/app/login/page.tsx`, `apps/web/src/app/login/page.test.tsx`
Available after: T1 (`ctaLabel` prop on `LoginForm`)
Architecture rule: Same single form serves all roles; no new pre-login fetches beyond existing `/auth/me` token check.
[RESTATE: Presentation layer only — do not touch API routes, controllers, auth contract, RBAC, or data layer.]

## DELIVERABLE
Given `/login` renders, Then story + three benefits + reorder CTA appear and hero/form are siblings under a responsive layout class contract
Given unauthenticated `/login`, Then hero copy uses ability framing and has no personal stock/promo/recommendation claims
Given `/login` renders, Then form/source order precedes benefits and no `order-*` class reverses it on mobile
Given `/login` renders, Then demo credentials appear as subtle footer help after the submit path
Given valid stored token, Then `/auth/me` redirects by role and login form is not usable
Given stale/invalid stored token, Then loading ends and login form becomes usable without redirect
Given outlet submit through page, Then request shape, auth persistence, event dispatch, `/orders` redirect, and refresh all occur
Given admin/finance/platform_owner/driver/sales submits, Then each redirects to the existing role destination and refreshes
Given admin submit with `redirect=/admin/supply-chain`, Then redirect uses the redirect param via `roleDestination(role, redirectParam)`
[must-not] Given unauthenticated `/login`, Then no personal stock, promo, recommendation, or outlet-specific inventory claim is displayed

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Hero has one story + three benefits
  - Form-first source order on mobile; responsive desktop class contract
  - `LoginForm` is real in page tests; do not mock it
  - URL-discriminating fetch mocks distinguish `/auth/me` from `/auth/login`
  - Stale-token fallback tested
  - Role matrix + `router.refresh()` tested
  - Mutation-check RED used for existing behavior locks; temporary mutations never committed
Must-not-have:
  - API/auth/RBAC/data changes
  - New dependencies or new pre-login data fetches
  - Personal stock/promo/recommendation claims before authentication
  - Changes to `/orders` or other destination pages
Open question risks:
  - none
Rollback note:
  - Revert `login/page.tsx`, `login/page.test.tsx`, and optionally T1 if reverting whole feature
Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Mocking `LoginForm` in page tests → STOP
  - Session/redirect behavior changed → STOP

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, `cd apps/web && npx jest src/app/login/page.test.tsx` is green, and the full `apps/web` suite remains green
Uncertain when: jsdom layout limitation blocks confidence; report DONE_WITH_CONCERNS and request visual/E2E follow-up
Escalate when: any auth-contract, session, redirect, or layer-boundary constraint is breached

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Extend LoginForm with backward-compatible ctaLabel prop | prereq | standard | Default/custom CTA; request shape; auth persistence; failure negative persistence; loading; expectedRole guard |
| T2 | Outlet-first hero + form-first layout on /login | T1 | standard | Hero/copy/layout contract; subtle demo; valid/stale token handling; full submit integration; role matrix redirects |
