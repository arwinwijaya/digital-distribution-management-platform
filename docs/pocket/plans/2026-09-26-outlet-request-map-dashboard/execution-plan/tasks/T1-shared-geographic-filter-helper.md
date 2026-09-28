# Task T1 — Shared geographic filter helper

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Shared geographic filter helper [prereq]

## OBJECTIVE
Create `apps/web/src/lib/geographic-filters.ts` — pure, dependency-free functions used by the page, GeoMap, drawer, and tests so filter math has one source of truth: canonical status expansion, period→date-range, per-outlet filtered counts, daily bucket filtering, and integer-cents money summation.

Steps:
1. Write failing test for: period→range mapping
   Test file: `apps/web/src/lib/geographic-filters.test.ts`
   Level: unit
   Test intent: Given window `{start:'2026-08-27', end:'2026-09-25', timezone:'Asia/Jakarta'}` / When `periodToRange(window, '7d')` / Then `{start:'2026-09-19', end:'2026-09-25'}`; and `'30d'` → `{start:'2026-08-27', end:'2026-09-25'}`; and `'today'` → `{start:'2026-09-25', end:'2026-09-25'}`.
   Exercise through: exported `periodToRange`
   Test doubles: none (pure)
   Expected RED: module does not exist
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.test.ts`
3. Implement `periodToRange` + status canonicalization (`ELIGIBLE_STATUSES`, `expandSemua`, `normalizeStatuses`) → verify PASS → refactor → commit `feat(lib): geographic filter helpers`
4. Write failing test for: per-outlet filtered counts compose status × period
   Test file: `apps/web/src/lib/geographic-filters.test.ts`
   Level: unit
   Test intent: Given a point with `orders_by_status {New:5, Confirmed:2, Delivered:1, 'Partially Paid':1}` and `daily_by_status` where New is 3 on 2026-09-20 and 2 on 2026-09-25 / When `computeFilteredCounts(point, ['New'], '7d', window)` / Then `filteredOrders===2` (only in-range New), `statusCounts.New===2`; and with `['New','Confirmed']` + `'30d'` → `7`.
   Exercise through: exported `computeFilteredCounts`
   Test doubles: none (pure)
   Expected RED: function absent
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.test.ts`
6. Implement `computeFilteredCounts` (status-map totals for 30d, `daily_by_status` slice for 7d/today, integer-cents `salesCents`) → verify PASS → refactor → commit `feat(lib): geographic filtered counts`
7. Write failing test for: safe fallback on unknown status/period + empty selection
   Test file: `apps/web/src/lib/geographic-filters.test.ts`
   Level: unit
   Test intent: Given `normalizeStatuses(['Bogus'])` / Then returns `['New','Confirmed']`; Given `periodToRange(window, 'banana' as Period)` / Then returns the `30d` range; Given a point missing `orders_by_status` / Then `computeFilteredCounts` returns `filteredOrders:0` and `legacyOnly:true` without throwing; Given `normalizeStatuses([])` (empty selection) / Then returns an EMPTY set — producing the no-request state, NOT reverting to `{New,Confirmed}`.
   Exercise through: exported helpers
   Test doubles: none (pure)
   Expected RED: no fallback branch; empty selection incorrectly reverts to default
8. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.test.ts`
9. Implement fallback branches → verify PASS → refactor → commit `feat(lib): geographic filter safe fallbacks`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Status & period filtering, Period semantics, Invalid filter state, Money and date rules, Deterministic dummy fixture table, Rule 1: Multi-toggle status selection (empty selection = no-request state, never silently reverts)
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: lightweight
Justification: pure functions, no I/O, spec fully specifies formulas.

## SANDWICH CONTEXT
[CRITICAL: Single source of truth for status/period filter math — page, GeoMap, drawer, and tests must import it, never re-derive it]
You are implementing the shared geographic filter helper for the Outlet Request Map Dashboard.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A — Snapshot-first enriched geographic projection
Files in scope: `apps/web/src/lib/geographic-filters.ts`, `apps/web/src/lib/geographic-filters.test.ts`
Available after: none (prereq)
Architecture rule: deterministic logic (no `Math.random`), integer-cents money (reuse the `toCents`/`fromCents` convention from `apps/web/src/dummy/aggregates.ts`), explicit insufficient-data fallback
[RESTATE: Single source of truth for status/period filter math]

## DELIVERABLE
Given window `{start,end,timezone}`, When `periodToRange(window,'7d')`, Then `[end-6d, end]` inclusive as `YYYY-MM-DD`
Given selected statuses `{New,Confirmed}` and a v2 point, When `computeFilteredCounts`, Then the sum covers only those statuses
Given `daily_by_status` entries outside the period, When filtering 7d, Then only in-range dates contribute
Given `Semua`, When expanded, Then exactly the four canonical eligible statuses
Given an unknown status or period, When normalized, Then it falls back to `{New,Confirmed}` + `30d` without throwing
Given an empty status selection, When normalized, Then it yields an EMPTY set (no-request state) and does NOT revert to `{New,Confirmed}`
[derived] Given a point without `orders_by_status`/`daily_by_status`, When counted, Then `legacyOnly:true` and no exception

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Pure functions, no side effects, no I/O
  - Integer-cents aggregation — never `Number(decimalA) + Number(decimalB)`
  - `Hari ini` uses snapshot `window.end`, never `new Date()`
  - `Semua` expands to exactly `New, Confirmed, Delivered, Partially Paid`
  - Unknown status/period → safe fallback, never throw to the page
  - Empty status selection → empty result (no-request state), must NOT revert to default `{New,Confirmed}`
Must-not-have:
  - No `Date`/`new Date()` for period boundaries (timezone-unsafe)
  - No floating-point money arithmetic
  - No import of GeoMap/page/drawer (keep the helper leaf-level)
Open question risks:
  - `daily_by_status` date format differs from `YYYY-MM-DD` → report NEEDS_CONTEXT
Rollback note:
  - Revert the helper file only; nothing else imports it yet
Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Float money math → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: `toCents`/`fromCents` convention not reusable as-is
Escalate when: an import cycle would be required to share the helper
