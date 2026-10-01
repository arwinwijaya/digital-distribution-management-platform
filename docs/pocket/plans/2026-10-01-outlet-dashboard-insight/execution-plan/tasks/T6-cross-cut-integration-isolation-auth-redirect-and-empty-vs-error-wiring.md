# Task T6 — Cross-cut integration — isolation, auth redirect, and empty-vs-error wiring

**Phase:** 1
**Depends:** T1, T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Cross-cut integration — isolation, auth redirect, and empty-vs-error wiring [depends: T1, T4, T5]

## OBJECTIVE
Verify the collaboration that only holds when backend + data layer + UI collaborate: (a) outlet A token never sees outlet B orders (server override), (b) unauthenticated → /login, (c) outlet without outlet relation → inline 403 no-retry across the real page flow, (d) login outlet → /dashboard, (e) /orders direct access still works for ordering. This is the sole E2E/integration-owned verification task for cross-unit GWT (Rule 6 decision: YES → own task).

Steps:
1. Write failing test for: outlet isolation E2E (server + client)
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx` (integration, extends T5) or `apps/web/e2e/siklus-pemesanan.spec.ts` (E2E) — pick one, document choice
   Level: integration (preferred) — uses MSW/mock fetch + real backend seeded via factory if E2E
   Test intent: Given outlet A logged in with token, B orders exist / When user opens /dashboard / Then only A ids appear in summary and list (verify fetch URL has no tampered outlet_id effect)
   Exercise through: `loadDashboard` + `OutletDashboard` wired via `DashboardPage` (mock token store + router)
   Test doubles: For integration level, mock fetch for orders/credit but assert query param ignored; for E2E level, real Laravel seeded DB. Do NOT mock OrderListFilters or outlet-helpers.
   Expected RED: B ids appear or test harness not wired
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "isolation"` or `npx --prefix apps/web playwright test --grep "outlet isolation"`
3. Implement wiring fix if needed (guard ordering in page.tsx, auth header forwarding in api.ts), verify isolation holds under pagination (cursor pages also filtered).
4. Write failing test for: auth redirects — unauth → /login and login outlet → /dashboard, /orders still works
   Test file: same file as above
   Level: integration (router spy) or E2E
   Test intent: Given no token / When visiting /dashboard / Then router.replace('/login?redirect=%2Fdashboard'); Given outlet login success / When roleDestination resolved / Then '/dashboard'; Given outlet at /orders / When token present / Then orders page still renders OrderForm
   Exercise through: DashboardPage session effect + login page
   Test doubles: mock getStoredToken, mock fetch /auth/me
   Expected RED: redirects to /orders
5. Run test — verify FAIL: same command as above
6. Refactor while green, ensure `console.warn` no PII (hash outlet id if logged), commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Story 6 all GWT + Acceptance Criteria isolation & auth rules. Cross-Unit Verification Decision (references/task-decomposition.md): YES → own integration task. `apps/web/src/app/dashboard/page.tsx` session guard, `apps/web/src/app/login/page.tsx`, `apps/api/app/Http/Controllers/OrderController.php` guard.

## WHY THIS APPROACH
Complexity: standard
Justification: Cross-unit seams (backend scoping + api fetch + page routing) — needs harnessed integration test that would not be caught by per-unit green.
[test-risk] Integration seam + persistence/network + ambiguous level — triggers Phase 6 audit.

## SANDWICH CONTEXT
[CRITICAL: Isolation is server-enforced — client must never rely on browser filtering; prove it at the HTTP seam.]
You are implementing cross-cut integration verification for outlet dashboard.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — same-route branch, server override outlet_id, withDummyRead isolation
Files in scope: apps/web/src/app/dashboard/OutletDashboard.test.tsx (or e2e spec), apps/web/e2e/siklus-pemesanan.spec.ts, apps/web/src/app/dashboard/page.tsx (if wiring fix needed)
Available after: T1 (backend), T4 (data layer), T5 (UI)
Architecture rule: No new RBAC key, no analytics change, no new endpoint; prove isolation at API seam not UI filter.
[RESTATE: An integration test passing must mean outlet A truly cannot observe outlet B via API, not just via hidden DOM.]

## DELIVERABLE
Given outlet A token and outlet B orders exist, When opening /dashboard, Then only A orders appear (paginated pages also scoped) [Story 6 isolation]
Given no token, When opening /dashboard, Then redirect to /login [Story 6 auth]
Given token without outlet relation, When loading dashboard, Then inline "Akun ini tidak terhubung ke outlet. Hubungi admin." without retry [Story 6 403]
Given outlet login, When redirect resolves, Then to /dashboard; Given direct /orders, Then still renders ordering UI [Story 6 login + legacy]

## QUALITY BAR
Must-have:
  - Verify at HTTP seam (mocked fetch URL assertion or real E2E DB isolation), not DOM filter alone
  - Auth redirects via router spy or E2E navigation
  - Persist isolation across pagination cursors
  - Console.warn without PII (hashed or omitted outlet_id)

Must-not-have:
  - New auth service or RBAC matrix change
  - Flaky time-dependent E2E without fake timer control
  - Verifying cross-outlet analytics

Open question risks:
  - E2E DB seed flakiness → prefer integration level with mocked fetch + real guard logic

Rollback note:
  - Remove integration test; prereq tasks still independently shippable

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass at chosen level (integration or E2E), isolation holds across pages, redirects correct, /orders still works
Uncertain when: E2E harness unavailable (CI no backend) — fall back to integration mock seam with HTTP assertion
Escalate when: outlet without outlet relation incorrectly shows retry or redirects to login loop
