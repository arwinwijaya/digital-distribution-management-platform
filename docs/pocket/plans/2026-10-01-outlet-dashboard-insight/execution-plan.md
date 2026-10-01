# EXECUTION PLAN — Outlet Dashboard & Insight

**Date:** 2026-10-01
**Spec:** docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
**Status:** draft
**Total tasks:** 6

---

## Execution Overview

### Recommended Order
```
T1, T2 (parallel) → T3, T4 (parallel) → T5 → T6
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T2 | start (both prereq) |
| Group B | T3, T4 | T1 and T2 complete |
| Group C | T5 | T3 and T4 complete |
| Group D | T6 | T5 complete (plus T1, T4) |

### Constraints Reminder
**Architecture:** Only touch `apps/web/src/app/dashboard/*`, `apps/web/src/app/login/page.tsx`, `apps/web/src/dummy/*`, and `apps/api/app/Http/Controllers/OrderController.php` + `apps/api/app/Support/OrderListFilters.php`. Must NOT touch `AnalyticsController`/`AnalyticsService`, `OrderCreationService`, `FinanceMetricsController`, RBAC seeder, or create a new backend endpoint in v1. Follow envelope `{status,data,meta}`, `withDummyRead` parity, `StatCard/Card/PageHeader`, and per-section `console.warn` without PII.
**Out-of-scope:** Cross-outlet analytics/benchmarking, auto recommendations/ETA, ordering flow changes (cart→checkout), admin/finance dashboard changes, new backend endpoint v1. No task may touch these.
**Assumptions at risk:** Period window = N calendar dates inclusive (`start = today-(N-1) @00:00 Asia/Jakarta`, `end = now`, converted to UTC) — 90 days = 90 dates, never 422. Truncation note uses `meta.total` from `GET /orders`. Cap = 1,000 newest orders with subtle note. Shared fetch cache key `(outletId, limit=100, sort=created_at DESC, cursor)` — credit retry must not invalidate order cache.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

### File Structure Map

```
Rule: Ringkasan + daftar pesanan (Story 1) — cap 1.000 + IDOR outlet_id
  Modify: apps/api/app/Http/Controllers/OrderController.php
  Modify: apps/api/app/Support/OrderListFilters.php
  Test:   apps/api/tests/Feature/OrderQueryTest.php
  Create: apps/web/src/app/dashboard/outlet-helpers.ts        (created by: T2)
  Modify: apps/web/src/app/dashboard/api.ts                  (created by: T4)
  Modify: apps/web/src/app/dashboard/page.tsx                (created by: T5)
  Create: apps/web/src/app/dashboard/OutletDashboard.tsx     (created by: T5)
  Test:   apps/web/src/app/dashboard/OutletDashboard.test.tsx (created by: T5)

Rule: Pemetaan status ramah outlet (Story 2) — kanonik 6 + Canceled→Cancelled + fallback
  Modify: apps/api/app/Support/OrderListFilters.php           (created by: T1)
  Create: apps/web/src/app/dashboard/outlet-helpers.ts        (created by: T2)
  Test:   apps/web/src/app/dashboard/outlet-helpers.test.ts   (created by: T2)
  Modify: apps/web/src/app/dashboard/OutletDashboard.tsx       (created by: T5)

Rule: Ringkasan belanja dengan periode (Story 3) — 7/30/90 selector, N tanggal kalender
  Create: apps/web/src/app/dashboard/outlet-helpers.ts
  Test:   apps/web/src/app/dashboard/outlet-helpers.test.ts
  Modify: apps/web/src/app/dashboard/api.ts
  Create: apps/web/src/app/dashboard/OutletDashboard.tsx
  Test:   apps/web/src/app/dashboard/OutletDashboard.test.tsx

Rule: Status kredit hide-when-null + per-section retry (Story 4)
  Modify: apps/web/src/app/dashboard/api.ts
  Create: apps/web/src/app/dashboard/OutletDashboard.tsx
  Test:   apps/web/src/app/dashboard/OutletDashboard.test.tsx
  Modify: apps/web/src/dummy/aggregates.ts                  (created by: T3)

Rule: Produk favorit top-5 agregasi klien (Story 5) — key product_id, tie-break name asc
  Create: apps/web/src/app/dashboard/outlet-helpers.ts
  Test:   apps/web/src/app/dashboard/outlet-helpers.test.ts
  Create: apps/web/src/app/dashboard/OutletDashboard.tsx
  Test:   apps/web/src/app/dashboard/OutletDashboard.test.tsx
  Modify: apps/web/src/dummy/aggregates.ts

Rule: Isolasi data & autentikasi (Story 6) — server override outlet_id, login→/dashboard
  Modify: apps/api/app/Http/Controllers/OrderController.php   (created by: T1)
  Modify: apps/web/src/app/login/page.tsx                    (created by: T4)
  Modify: apps/web/src/app/dashboard/page.tsx                (created by: T5)
  Test:   apps/api/tests/Feature/OrderQueryTest.php           (created by: T1)
  Test:   apps/web/src/app/dashboard/OutletDashboard.test.tsx
  Test:   apps/web/e2e/siklus-pemesanan.spec.ts              (created by: T6, if extended)

Rule: Dummy parity + observability
  Modify: apps/web/src/dummy/aggregates.ts                  (created by: T3)
  Modify: apps/web/src/dummy/index.ts                       (created by: T3)
  Test:   apps/web/src/dummy/aggregates.test.ts              (created by: T3)
  Modify: apps/web/src/app/dashboard/OutletDashboard.tsx       (console.warn per-section)
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

### Task 1: Backend — outlet-scoped OrderController index + OrderListFilters allowlist [prereq]

## OBJECTIVE
Perluas `OrderController::index` agar outlet dapat melist order miliknya sendiri (server-side scoping via token, abaikan/override `outlet_id` klien tanpa 403 leak) dan perluas allowlist status `OrderListFilters` ke 7 nilai dengan normalisasi `Canceled→Cancelled`. Jaga perilaku admin tetap byte-for-byte, termasuk paginasi `limit (default 100, max 100) + cursor (offset)` dan envelope `{status,data,meta:{has_more,limit,cursor,total}}`, serta validasi rentang filter aditif tetap menghasilkan 422.

Steps:
1. Write failing test for: IDOR outlet_id silently overridden (Story 1 — IDOR)
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given outlet A token + outlet B exists with orders / When GET /api/admin/orders?outlet_id=B (or alias GET /orders if aliased) / Then only outlet A orders returned, total reflects filtered A orders, no 403, no B ids
   Exercise through: `GET /api/admin/orders` HTTP boundary (Laravel test client with outlet user token)
   Test doubles: Real DB (RefreshDatabase), real OrderListFilters — do NOT mock OrderController or filters; only seed factories
   Expected RED: `assertJson` finds B ids or 403 "Only admins can list orders" before guard change
2. Run test — verify FAIL: `php artisan test --filter=test_outlet_scoped_order_list_ignores_client_outlet_id`
3. Implement: Update `OrderController::indexGuard` to allow outlet role when user has outlet relation (admin still passes through); before `resolve()`, inject/override `outlet_id` from `$request->user()->outlet->id` for outlet role (admin untouched); ensure `GET /credit-limit` already isolates via token (no change, just verify). Update `OrderListFilters::CANONICAL_STATUSES` to `['New','Confirmed','Delivered','Partially Paid','Paid','Cancelled','Canceled']` and normalize `Canceled→Cancelled` after `parseStatuses`.
4. Write failing test for: Allowlist expansion accepts Paid/Cancelled/Canceled and normalizes (Story 2 — Canceled variant)
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given outlet A has orders with status Paid, Cancelled, Canceled / When GET /api/admin/orders?status=Paid,Cancelled&outlet_id=A and status=Canceled / Then 200, filtered sets correct, and Canceled rows map to Cancelled label path
   Exercise through: same HTTP boundary
   Test doubles: Real DB; do NOT mock filters
   Expected RED: 422 `Status must be one of: New,Confirmed,Delivered,Partially Paid` before allowlist change
5. Run test — verify FAIL: `php artisan test --filter=test_order_status_allowlist_includes_paid_and_cancelled`
6. Write failing test for: Outlet without outlet relation returns 403 inline (Story 6 — 403 no outlet)
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given user with outlet role but no outlet relation / When GET /api/admin/orders / Then 403 with JSON `{status:"error", message:"The authenticated user is not associated with an outlet."}` (reuse show() pattern, not admin guard leak)
   Exercise through: HTTP boundary with user factory without outlet
   Test doubles: Real DB
   Expected RED: 200 or 403 with wrong message before guard outlets check
7. Run test — verify FAIL: `php artisan test --filter=test_outlet_without_outlet_returns_403`
8. Implement remaining guard/filter changes, run all three filters until green, refactor to keep `buildOrderList` pagination (limit+1 has_more) intact, commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Rules: Ringkasan+daftar, Pemetaan status (C1), Isolasi (Story 6), Acceptance Criteria — Contract Polluter (Region A) pattern applies: API client + parser are peers, must test together not isolated. `apps/api/app/Http/Controllers/OrderController.php` L26-160, `apps/api/app/Support/OrderListFilters.php` L15-110, `apps/api/tests/Feature/OrderQueryTest.php` existing suite.

## WHY THIS APPROACH
Complexity: standard
Justification: Multi-file backend change with security implication (IDOR silent override) and filter contract change that must not break existing 422/unknown-query tests. Requires cross-file coordination and judgment on guard ordering vs `test_non_admin_cannot_list_orders` precedent.

## SANDWICH CONTEXT
[CRITICAL: Server must silently override outlet_id from token for outlet role; never trust client outlet_id — IDOR leak requires redo.]
You are implementing backend outlet scoping for Outlet Dashboard & Insight.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — Same-route outlet branch, minimal backend extension (OrderController guard + OrderListFilters allowlist), no new endpoint.
Files in scope: apps/api/app/Http/Controllers/OrderController.php, apps/api/app/Support/OrderListFilters.php, apps/api/tests/Feature/OrderQueryTest.php
Available after: none (prereq)
Architecture rule: Do NOT create new endpoint, do NOT touch AnalyticsService/FinanceMetricsController, do NOT add RBAC key. Preserve admin behavior byte-for-byte including pagination meta and 422 validation.
[RESTATE: Server silently overrides outlet_id for outlet — never leak other outlet ids and never return 403 on tampered outlet_id param.]

## DELIVERABLE
Given outlet A token with query outlet_id=B, When GET /orders (scoped list) is called, Then only outlet A orders are returned (no 403 leak) [Story 1 — IDOR]
Given status "Paid" or "Cancelled" or "Canceled", When list filter is applied, Then it is accepted and Canceled is normalized to Cancelled [Story 2 — Canceled]
Given user with outlet role but no outlet relation, When listing orders, Then 403 with message "The authenticated user is not associated with an outlet." [Story 6]
Given admin token, When listing with any filters, Then behavior is unchanged (has_more, total, 422 on invalid) [regression]

## QUALITY BAR
Must-have:
  - Silent override: outlet `outlet_id` from token; client param ignored, not rejected
  - Allowlist = 7 values, Canceled normalized to Cancelled before query apply
  - Pagination preserved: limit default 100 max 100, cursor offset, has_more via limit+1, meta total from cloned filtered builder
  - 422 on unknown query key, invalid outlet_id, invalid status (outside 7), start>end, range >90 dates still works
  - Admin listing unchanged

Must-not-have:
  - New route or new controller method
  - 403 leak on tampered outlet_id (must not expose existence of B)
  - Loosening admin-only fields or touching analytics code
  - Cross-outlet totals or benchmarking logic

Open question risks:
  - N tanggal kalender window relies on client building correct start; this task only ensures range validation accepts 90 dates inclusive → if wrong: outlet 90-day selector could 422

Rollback note:
  - Revert guard + allowlist → admin-only listing restored; no migration involved

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, existing OrderQueryTest suite still green (no regression), no out-of-scope files modified
Uncertain when: outlet scope conflicts with future admin filter expectations
Escalate when: admin contract broken (has_more/total shape change) or IDOR not silently overridden

---

### Task 2: Shared helpers — status labels, period window, favorite aggregation [prereq]

## OBJECTIVE
Buat domain-scoped helper murni tanpa side-effect untuk tiga komputasi yang dipakai lintas seksi: (a) pemetaan status internal → label/hint/CTA outlet, (b) window periode N tanggal kalender inklusif dengan zona Asia/Jakarta, (c) agregasi favorit top-5 dari `orders[].items[]`. Helper ini menjadi prereq agar T3–T5 tidak menduplikasi logika (Shared Helper Pattern).

Steps:
1. Write failing test for: Status label kanonik + Canceled normalization + fallback unknown
   Test file: `apps/web/src/app/dashboard/outlet-helpers.test.ts`
   Level: unit
   Test intent: Given statuses New, Confirmed, Delivered, Partially Paid, Paid, Cancelled, Canceled / When `toOutletStatus(status)` / Then correct label/hint/CTA per spec; Given "Super-Pending-Review!!!" / When mapped / Then escaped truncated to 20 chars + … and tooltip "Lainnya" (no blank, no crash)
   Exercise through: exported `toOutletStatus(status: string) => {label,hint,ctaKey}`
   Test doubles: none — pure function; do NOT mock
   Expected RED: helper does not exist or returns raw status
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/outlet-helpers.test.ts -t "toOutletStatus"`
3. Implement `toOutletStatus` in `outlet-helpers.ts`: map 6 kanonik + alias Canceled→Dibatalkan, normalize helper `normalizeStatus`, escape HTML (no innerHTML), truncate 20 + …, fallback type `{isFallback:true}`.
4. Write failing test for: Period window N tanggal kalender inclusive
   Test file: `apps/web/src/app/dashboard/outlet-helpers.test.ts`
   Level: unit
   Test intent: Given now = 2026-05-13T23:55 Asia/Jakarta / When `periodWindow(7, now)` and `periodWindow(90, now)` / Then 7d start = 2026-05-07 00:00 Asia/Jakarta, end = now, 7d window excludes order 2026-05-06T16:59Z (23:59 6 Mei) and includes order 2026-05-06T17:00Z (00:00 7 Mei); 90d start = today-89 days inclusive, converted to UTC YYYY-MM-DD strings for query
   Exercise through: `periodWindow(days: 7|30|90, now: Date) => {start,end,startUTC,endUTC,label}`
   Test doubles: fake now via param (no clock mock needed)
   Expected RED: helper missing or off-by-one (90 = 91 dates)
5. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/outlet-helpers.test.ts -t "periodWindow"`
6. Implement `periodWindow` using `toLocaleDateString('sv-SE',{timeZone:'Asia/Jakarta'})` pattern already in dashboard/page.tsx; compute start calendar date, return both Jakarta strings and UTC YYYY-MM-DD for API.
7. Write failing test for: Favorite aggregation top-5 + fallback + tie-break
   Test file: `apps/web/src/app/dashboard/outlet-helpers.test.ts`
   Level: unit
   Test intent: Given orders with items [{product_id:1,name:"A",qty:10,created_at:...},{product_id:2,name:"B",qty:6},{product_id:1,name:"A-renamed",qty:2,created_at later}] plus null product_id and null product_name / When `topFavorites(orders,5)` / Then A total 12 with display name "A-renamed" (most recent), B 6, null product_id skipped, product_name null → "Produk #5", no crash, tie-break name asc
   Exercise through: `topFavorites(orders,limit) => [{product_id,displayName,totalQty}]`
   Test doubles: none; fixture orders built inline
   Expected RED: helper missing or wrong key/name
8. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/outlet-helpers.test.ts -t "topFavorites"`
9. Implement `topFavorites`: key product_id (skip null), sum quantity integers, track most recent product_name by created_at/order index, fallback `Produk #id`, sort by totalQty desc then displayName asc, take 5.
10. Refactor while green, commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Story 2 Rule 1/2, Story 3 Rule 1/2, Story 5 Rule 1/2 + G8 design details (escape/truncate, N tanggal kalender, tie-break). Existing pattern `jakartaDateString` in `apps/web/src/app/dashboard/page.tsx` L12-20.

## WHY THIS APPROACH
Complexity: standard
Justification: Three pure computations with branching logic and timezone nuance; promotable to standard due to N-tanggal kalender inclusive semantics and XSS truncation rule.

## SANDWICH CONTEXT
[CRITICAL: Window must be N calendar dates inclusive (today-(N-1)), not rolling N×24h — 90 must not 422.]
You are implementing shared outlet helpers for dashboard composition.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — compose client-side from existing endpoints; helpers are reused across dummy, api, and UI tasks.
Files in scope: apps/web/src/app/dashboard/outlet-helpers.ts, apps/web/src/app/dashboard/outlet-helpers.test.ts
Available after: none (prereq) — T1 runs in parallel, no import dependency
Architecture rule: No new dependencies, hand-roll safe; reuse existing Jakarta date pattern, do not introduce date-fns/luxon.
[RESTATE: Helpers are pure — no fetch, no store import, no side effects.]

## DELIVERABLE
Given status "New", When toOutletStatus, Then label "Menunggu konfirmasi admin" + hint "Tidak ada aksi" [Story 2]
Given status "Canceled", When toOutletStatus, Then label "Dibatalkan" same as "Cancelled" [Story 2]
Given unknown "Super-Pending-Review!!!", When toOutletStatus, Then escaped truncated 20+… with tooltip "Lainnya" [Story 2]
Given 2026-05-13T23:55 Asia/Jakarta, When periodWindow(7), Then start 7 Mei 00:00, excludes 6 Mei 23:59, includes 7 Mei 00:00, 90d = 90 dates [Story 3]
Given orders with items, When topFavorites, Then top-5 correct, null skipped, fallback "Produk #id", tie-break name asc [Story 5]

## QUALITY BAR
Must-have:
  - No network, no Zustand, no React import
  - Escape + truncate 20 chars for unknown statuses
  - Jakarta 00:00 inclusive math matches spec (90 never 422)
  - Favorite key = product_id, name = most recent, qty sum integer, skip null product_id

Must-not-have:
  - Cross-outlet logic or analytics totals
  - New date library dependency
  - Duplicated helper copies in other files (must import this module)

Open question risks:
  - Fallback "Produk #id" assumed acceptable → if wrong: label mismatch in UI test

Rollback note:
  - Delete helper file; no downstream yet at this stage

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, linter `npm --prefix apps/web run lint` (tsc --noEmit) would pass on this module, no out-of-scope files
Uncertain when: window math disagrees with OrderListFilters validation (range inclusive) — re-check diffDays+1 logic
Escalate when: new dependency introduced or helper mutates inputs

---

### Task 3: Dummy parity — dashboardOutlet fixture [depends: T2]

## OBJECTIVE
Tambahkan fixture `dashboardOutlet` di `apps/web/src/dummy/aggregates.ts` (dan tipe di `apps/web/src/dummy/index.ts` jika diperlukan) yang memenuhi parity untuk story 1–5: counts per status (untuk ringkasan), orders terbaru, credit-limit snapshot, dan data favorit. Guard `withDummyRead` harus mengembalikan outlet dashboard tanpa network saat dummy mode ON, mengikuti pola `dashboardAdmin`/`dashboardFinance` di `apps/web/src/app/dashboard/api.ts`.

Steps:
1. Write failing test for: dashboardOutlet aggregates parity
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit
   Test intent: Given seed with 5 orders (New×3 Delivered×2), 2 products, credit_limit set / When `buildAggregates(seed)` / Then `aggregates.dashboardOutlet` exists, metrics counts match orders per status, recent list length matches, credit fields present and formatted, favorites top-k matches helper `topFavorites`
   Exercise through: `buildAggregates` / `buildDashboardOutlet` exported builder
   Test doubles: Use existing seed factory (no network)
   Expected RED: `dashboardOutlet` is undefined
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/dummy/aggregates.test.ts -t "dashboardOutlet"`
3. Implement: Define type `OutletDashboardData` in dummy, add `dashboardOutlet` to `Aggregates` interface, implement `buildDashboardOutlet(master, tx, window)` reusing helpers from T2 (import allowed after T2). Use `money()` for rupiah strings, reuse `safeNumber` pattern. Seed deterministic via existing RNG.
4. Run test — verify PASS, extend test to cover empty-state (zero orders → empty arrays, not zeros-as-error) and credit_limit null case (field null triggers hide branch).
5. Refactor while green (extract shared builder if needed), commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Story 1–5 parity, architecture constraint withDummyRead, file `apps/web/src/dummy/aggregates.ts` L225-230 + L1306, `apps/web/src/dummy/guards.ts`, `apps/web/src/app/dashboard/api.ts` dummy branch.

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single module change with clear precedent (dashboardAdmin/dashboardFinance); judgment limited to fixture shape that satisfies UI contracts.

## SANDWICH CONTEXT
[CRITICAL: Dummy outlet must satisfy withDummyRead — zero network when isDummy=true.]
You are implementing dummy fixture parity for outlet dashboard.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — v1 composes existing endpoints; dummy follows same shape so outlet UI is testable without backend.
Files in scope: apps/web/src/dummy/aggregates.ts, apps/web/src/dummy/index.ts, apps/web/src/dummy/aggregates.test.ts
Available after: T2 (helpers) — import helpers for aggregation; T1 not needed for dummy
Architecture rule: Follow existing aggregates pattern (money(), StatCard props), do not change analytics/finance fixtures.
[RESTATE: Dummy parity must enable same empty/error branches testable without backend.]

## DELIVERABLE
Given dummy mode ON with outlet seed, When loadDashboard for outlet is called, Then withDummyRead returns dashboardOutlet without fetch [Story 1–5 parity]
Given zero orders seed, When aggregates built, Then outlet sections expose empty arrays for UI empty branch [Story 1 empty]
Given credit_limit null seed, When aggregates built, Then credit_limit null is present [Story 4 hide]

## QUALITY BAR
Must-have:
  - withDummyRead path for outlet added in aggregates and wired in api.ts test (task 4 handles wiring; this task just builds fixture)
  - Parity counts: sum of per-status counts = total, recent 10 newest-first, credit fields Rupiah strings, favorites via helper
  - No network, no new menu key

Must-not-have:
  - Changing dashboardAdmin/dashboardFinance metrics
  - Cross-outlet data
  - New backend call in dummy

Open question risks:
  - Fixture shape assumed `{summary, recent, shopping, credit, favorites}` — wire-up in T4 will finalize; if shape wrong: T4 test will fail → NEEDS_CONTEXT

Rollback note:
  - Remove dashboardOutlet field; outlet dummy path falls back to network

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, existing aggregates.test still green, no out-of-scope files modified
Uncertain when: helper import creates cycle (dummy imports dashboard helper) — keep helper in `outlet-helpers.ts` which imports nothing
Escalate when: dummy store toggle breaks existing guards.test

---

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

---

### Task 5: UI — OutletDashboard composition (all outlet sections + a11y) [depends: T3, T4]

## OBJECTIVE
Bangun komponen `OutletDashboard` yang dipakai oleh branch outlet di `apps/web/src/app/dashboard/page.tsx` (ganti `router.replace('/orders')`): ringkasan per status (StatCard), daftar 10 terbaru dengan tautan detail, shopping summary dengan selector 7/30/90 (default 30) dan label periode, kartu kredit (hide bila null), produk favorit top-5 + "Pesan lagi" → /orders, empty state vs error yang berbeda (pesan spesifik + warning Card + ikon ⚠️ untuk inline 403 `my-3`), partial render (seksi yang sukses tetap render), per-section "Coba lagi" (`aria-live="polite"`, fokus kembali, keyboard Enter/Space), truncation note di atas daftar ("Menampilkan 1.000 pesanan terbaru dari {meta.total} pesanan" + bar "Tidak ada data lebih baru"), layout collapse instan tanpa animasi saat credit hidden (`grid auto-rows`), `console.warn` per-seksi tanpa PII saat partial failure, dan `data-testid` per spec. Admin/finance branch harus tetap byte-for-byte.

Steps:
1. Write failing test for: happy render — summary + recent 10 + shopping default 30 + credit + favorites
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx` (new)
   Level: integration (React Testing Library)
   Test intent: Given outlet data (3 New 2 Delivered, 4 orders in 30d, credit set, products A10 B6) / When `<OutletDashboard data={outletData} />` rendered / Then summary shows New=3 Delivered=2, recent list 5 newest with detail links `/orders?order_id=`, shopping shows total Σ total_amount excluding Cancelled and count 4 with label "30 hari terakhir", credit shows Rp formatted, favorites shows A then B with "Pesan lagi" → /orders
   Exercise through: `<OutletDashboard />` component props (no fetch)
   Test doubles: mock outlet-helpers real, mock next/link; dummy data from T3
   Expected RED: component does not exist
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "happy render"`
3. Implement `OutletDashboard.tsx` + branch in `page.tsx`: replace outlet redirect with conditional `if (role==='outlet') return <OutletDashboard ...>`; keep admin/finance paths untouched; wire period selector state default 30, recompute shopping via helper; map statuses via helper; wire truncation note (G1/G2), inline 403 Card (G3), escape/truncate (G4), fallback Produk #id (G6), a11y (G8).
4. Write failing test for: empty vs window-empty vs error vs credit hidden + per-section retry isolation
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx`
   Level: integration
   Test intent: Given props: zero orders → "Belum ada pesanan"; given history but 7d window zero → "Tidak ada transaksi pada periode ini"; given credit_limit null → credit section not in DOM, no error, grid does not shift with animation (instant collapse); given orders fetch error + credit success → orders error Card with `outlet-section-error` + button `outlet-retry` (aria-live) while credit renders; when retry clicked → only onRetryOrders called, favorites/credit untouched
   Exercise through: component props + mock onRetry callbacks
   Test doubles: mock console.warn spy, mock retry handlers
   Expected RED: empty and error conflated or retry calls whole-page refetch
5. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "empty vs error"`
6. Write failing test for: auth 403 inline (no retry) + a11y focus + console.warn
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx`
   Level: integration
   Test intent: Given 403 no outlet relation payload / When OutletDashboard receives error 403 / Then inline Card warns "Akun ini tidak terhubung ke outlet. Hubungi admin." without retry button, role alert; When retry button receives Enter/Space / Then handler fires and focus returns to button; When partial failure renders / Then console.warn called per failed section without PII
   Exercise through: same component
   Test doubles: jest spy on console.warn
   Expected RED: 403 shows retry or no warn
7. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "403 and a11y"`
8. Refactor while green (extract sub-sections OrdersSummary/Shopping/Credit/Favorites inside same file if over 300 lines, still within this task), verify admin `apps/web/src/app/dashboard/page.test.tsx` still green, commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Stories 1–5 all GWT, design G1–G8, Acceptance Criteria, Implementation Notes data-testid list. `apps/web/src/app/dashboard/page.tsx` full file, `apps/web/src/components/ui` StatCard/Card/PageHeader, `apps/web/src/app/payments/page.tsx` partial-error pattern, `apps/web/src/dummy/guards.ts` withDummyRead.

## WHY THIS APPROACH
Complexity: standard
Justification: Multi-section composition with distinct empty/error/hide branches, a11y contract, and layout stability; pushes to standard per branching logic override.
[test-risk] Cross-unit GWT (API→UI) and ambiguous unit vs integration boundary for period selector recompute — auditable in Phase 6.

## SANDWICH CONTEXT
[CRITICAL: Dashboard /dashboard must branch by role client-side — outlet renders OutletDashboard, admin/finance paths remain byte-for-byte unchanged.]
You are implementing outlet UI composition for dashboard.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — same-route branch, StatCard/Card/PageHeader, reuse withDummyRead + outlet helpers.
Files in scope: apps/web/src/app/dashboard/OutletDashboard.tsx, apps/web/src/app/dashboard/page.tsx, apps/web/src/app/dashboard/OutletDashboard.test.tsx
Available after: T3 (dummy), T4 (data layer + login map)
Architecture rule: Do NOT add new menu key or RBAC; reuse dashboard:edit gate; truncation note uses meta.total; shared cache stays in api.ts.
[RESTATE: Branch is client-side in page.tsx which is already 'use client' — no parallel routes or middleware.]

## DELIVERABLE
Given outlet with data, When dashboard renders, Then summary, recent 10 with links, shopping default 30 with label, credit Rp, favorites top-5 with reorder link all visible [Story 1–5 happy]
Given zero orders, When renders, Then "Belum ada pesanan"; Given history but empty window, Then "Tidak ada transaksi pada periode ini" [Story 1 vs 3 empty]
Given 1.500 orders, When renders, Then note "Menampilkan 1.000 pesanan terbaru dari {meta.total} pesanan" above list + bar "Tidak ada data lebih baru (batas 1.000 pesanan terbaru)" and no pagination controls [Story 1 cap G1/G2]
Given Canceled or unknown status, When rendered, Then Dibatalkan vs escaped truncated + tooltip Lainnya [Story 2 G4]
Given credit_limit null, When renders, Then credit card hidden, instant grid collapse, no error [Story 4 hide G7]
Given per-section errors, When renders, Then partial render + outlet-section-error + outlet-retry per section, 403 inline without retry, retry isolated, aria-live focus-kept, keyboard operable, console.warn per-section no PII [Story 4/6 + G3/G5/G8]

## QUALITY BAR
Must-have:
  - data-testid: outlet-dashboard, outlet-orders-summary, outlet-orders-recent, outlet-shopping-summary, outlet-credit-card, outlet-favorites, outlet-section-error, outlet-retry
  - Outlet helpers imported, not duplicated (status, window, favorites)
  - Rupiah id-ID without decimals via safeNumber
  - Favorites Pesan lagi → /orders
  - Escape unknown status + truncate 20 + tooltip Lainnya, fallback Produk #id

Must-not-have:
  - Changing admin/finance dashboard output
  - New endpoint, new route, or cart→checkout logic
  - Cross-outlet benchmarking or recommendation UI

Open question risks:
  - Truncation copy "Menampilkan 1.000 ..." assumed → if wrong copy: snapshot diff, report DONE_WITH_CONCERNS

Rollback note:
  - Revert page.tsx branch to router.replace('/orders') and remove OutletDashboard import; UI inert

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, `apps/web/src/app/dashboard/page.test.tsx` admin suites still green, accessibility attributes present, console.warn spy verified
Uncertain when: viewport layout jump still perceived on hide (mobile) — visual spot check needed
Escalate when: admin path changed or dashboard:edit gate bypassed

---

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

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Backend — outlet-scoped OrderController index + OrderListFilters allowlist | prereq | standard | IDOR outlet_id ignored + 7-status allowlist + 403 no-outlet |
| T2 | Shared helpers — status labels, period window, favorite aggregation | prereq | standard | Status map + 20-char escape + N-date window + top-5 tie-break |
| T3 | Dummy parity — dashboardOutlet fixture | T2 | lightweight | withDummyRead zero-network + empty + credit null parity |
| T4 | Data layer — dashboard/api.ts outlet branch + login redirect | T1, T2 | standard | 1.000 paginated cap with meta.total + per-section retry isolation |
| T5 | UI — OutletDashboard composition | T3, T4 | standard | Summary/recent/shopping/credit/favorites + empty/error/hide + a11y |
| T6 | Cross-cut integration — isolation & auth wiring | T1, T4, T5 | standard | Server isolation E2E + redirects + /orders still works |
