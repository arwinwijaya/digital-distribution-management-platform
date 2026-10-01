# Task T2 — Shared helpers — status labels, period window, favorite aggregation

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
