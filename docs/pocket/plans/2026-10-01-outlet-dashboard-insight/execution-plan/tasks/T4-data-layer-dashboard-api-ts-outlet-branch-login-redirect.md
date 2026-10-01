# Task T4 — Data layer — dashboard/api.ts outlet branch + login redirect

**Phase:** 1
**Depends:** T1, T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Data layer — dashboard/api.ts outlet branch + login redirect [depends: T1, T2]

## OBJECTIVE
Wire outlet data composition: tambah cabang `role === 'outlet'` di `apps/web/src/app/dashboard/api.ts` yang memanggil `GET /orders` (cursor pagination `limit=100` + `total`/`has_more`, cap 1.000 newest, shared cache key `(outletId, limit, sort, cursor)`), `GET /credit-limit` yang terisolasi, dan window periode N tanggal kalender dari T2; serta ubah `roleDestination` di `apps/web/src/app/login/page.tsx` dari `outlet→/orders` menjadi `outlet→/dashboard`.

Steps:
1. Write failing test for: loadDashboard outlet branch composes paginated orders with cap and meta.total note
   Test file: `apps/web/src/app/dashboard/api.test.ts` (new)
   Level: integration
   Test intent: Given token outlet, role outlet, mock fetch: GET /orders returns {data:[10], meta:{has_more:true,total:1500,limit:10,cursor:0}} paginated over 10 pages then has_more false at cursor 1000 / When `loadDashboard(token,'outlet')` with 30d window / Then loader fetches paginated (cursor increments), aggregates summary per status from fetched set (up to 1.000), exposes `{summary, recent10, shopping:{total,count,label}, credit, favorites, truncation:{capped:true,total:1500}}` and builds query `start=today-29 @00:00 Jakarta` / `end=today` as YYYY-MM-DD UTC; 90d window does not 422
   Exercise through: `loadDashboard` (withDummyRead OFF path)
   Test doubles: mock `fetch` (global.fetch), mock `useDummyStore.getState` isDummy false; do NOT mock outlet-helpers (real)
   Expected RED: outlet branch missing → falls through to admin analytics 403 or throws
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/api.test.ts -t "outlet branch"`
3. Implement: Add type `OutletDashboardData` to api.ts, add `kind: 'outlet'` to `DashboardLoadResult`, add helper `fetchOutletOrdersPaginated(token, start, end)` with limit=100, cursor offset, `withDummyRead` guard (return `dummy.dashboardOutlet` when isDummy), loop until `!has_more` or `cursor >= 1000`, cache keyed by `(tokenHash, limit, sort, start, end)` (simple Map in closure, invalidated only on new load — credit fetch does NOT invalidate it). Wire `loadDashboard` outlet case to fetch orders + credit-limit parallel, compute shopping aggregates via helper, favorites via helper, credit pass-through.
4. Write failing test for: per-section credit retry does not invalidate order cache + empty vs error vs hide mapping
   Test file: `apps/web/src/app/dashboard/api.test.ts`
   Level: integration
   Test intent: Given outlet load succeeds then credit 5xx / When credit retry succeeds / Then only credit fetch re-executes (order fetch count unchanged); Given 200 [] → empty, 200 credit_limit null → credit hidden signal, 401 → throw auth error, 403 no outlet → inline message payload, 5xx → throw retryable
   Exercise through: same loadDashboard boundary, spy fetch call count by URL
   Test doubles: mock fetch per sequence
   Expected RED: retry re-fetches orders or 200 [] treated as error
5. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/api.test.ts -t "retry isolation"`
6. Write failing test for: login roleDestination outlet → /dashboard
   Test file: `apps/web/src/app/login/page.test.tsx` (or existing login test suite)
   Level: unit
   Test intent: Given role outlet / When `roleDestination('outlet', '/dashboard')` (or login redirect effect) / Then returns '/dashboard' not '/orders'; Given no token and navigating to /dashboard / Then guard redirects to /login?redirect=%2Fdashboard
   Exercise through: exported `roleDestination` or LoginForm redirect mock
   Test doubles: mock router.replace
   Expected RED: outlet still maps to '/orders'
7. Run test — verify FAIL: `npm --prefix apps/web test -- "login" -t "outlet"`
8. Implement `roleDestination` change + `page.tsx` guard already in T5; this task only changes api.ts + login. Refactor while green, commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Story 1 cap note with `meta.total`, Story 3 window, Story 4 credit hide, Story 6 auth. `apps/web/src/app/dashboard/api.ts` full file, `apps/web/src/app/login/page.tsx` roleDestination, `apps/web/src/app/dashboard/page.tsx` guards, `apps/api/app/Http/Controllers/OrderController.php` pagination contract.

## WHY THIS APPROACH
Complexity: standard
Justification: Networking + pagination cap logic + dummy branch + cache isolation and auth mapping; multiple fetch orchestration with error semantics.

## SANDWICH CONTEXT
[CRITICAL: 1.000 cap uses pagination (limit 100 + cursor offset + has_more) and meta.total — do not invent total and do not refetch beyond 1.000.]
You are implementing outlet data layer for dashboard.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — Same-route outlet branch, compose existing endpoints, shared fetch cache `(outletId, limit, sort, cursor)` — credit retry does not invalidate order cache.
Files in scope: apps/web/src/app/dashboard/api.ts, apps/web/src/app/dashboard/api.test.ts, apps/web/src/app/login/page.tsx
Available after: T1 (backend scoping for fetch to succeed), T2 (helpers for window/favorites/status)
Architecture rule: Envelope `{status,data,meta}`; withDummyRead before network; no new endpoint; silent outlet_id override is server-side (client must not send tampered id).
[RESTATE: Pagination is cursor=offset with has_more via limit+1; never assume total equals fetched count.]

## DELIVERABLE
Given outlet token, When loadDashboard outlet, Then paginated fetch up to 1.000 newest with has_more loop and truncation note uses meta.total [Story 1 cap]
Given 200 [] for orders, When loaded, Then empty signal "Belum ada pesanan"; 200 credit_limit null → hide; 401→/login; 403 no outlet → inline no-retry; 5xx→error+retry [Story 1/4/6]
Given credit retry, When re-fetched, Then order cache not invalidated [G5]
Given login as outlet, When redirect, Then to /dashboard [Story 6]

## QUALITY BAR
Must-have:
  - limit 100 max, cursor offset, sort created_at DESC nulls last; fetch window start = today-(N-1) 00:00 Jakarta as YYYY-MM-DD
  - Shared cache key (outletId, limit, sort, cursor, window); credit retry isolated
  - 90d window = today-89 days, never 422
  - withDummyRead returns dashboardOutlet when isDummy and dummy non-null

Must-not-have:
  - New backend route or analytics change
  - Client-side outlet_id param injection (rely on server scoping)
  - Total computed as fetched length (must use meta.total when present)

Open question risks:
  - If meta.total absent in some edge, fallback to fetched count with truncation note still shown → report DONE_WITH_CONCERNS

Rollback note:
  - Revert outlet branch + roleDestination → outlet back to /orders; dummy fixture remains inert

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, fetch spy counts prove cache isolation, login mapping correct, dummy branch returns without network
Uncertain when: `has_more` false before 1.000 but total > 1.000 inconsistency (trust total)
Escalate when: Auth 403 incorrectly retried or empty/error conflated
