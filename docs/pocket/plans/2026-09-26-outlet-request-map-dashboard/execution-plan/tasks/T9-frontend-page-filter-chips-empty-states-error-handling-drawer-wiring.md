# Task T9 — Frontend — Page filter chips, empty states, error handling, drawer wiring

**Phase:** 3
**Depends:** T1, T5, T7, T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Frontend — Page filter chips, empty states, error handling, drawer wiring [depends: T1, T5, T7, T8]

## OBJECTIVE
Update `page.tsx` with status multi-toggle chips (`New`, `Confirmed`, `Delivered`, `Partially Paid`, `Semua`) and period chips (`Hari ini`, `7 hari`, `30 hari`) using `aria-pressed`, default `{New,Confirmed}`+30d, filter math via T1, the four precedence-ordered empty states, the production `outlets_without_daily_detail` count (v1/partial rows excluded from narrow-period views), typed-error classification by `status`, a retry action, an admin role check before any dummy read, drawer wiring with a frozen filter snapshot, and a freshness label.

Steps:
1. Write failing test for: default state + filter chips
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: component (page)
   Test intent: Given a v2 snapshot / When the page loads / Then the chips `{New,Confirmed}`+30d are pressed and markers reflect the filtered counts; Given a chip is toggled / Then `aria-pressed` and the marker counts update; Given all statuses are deselected (empty selection) / Then the page renders the no-request empty state and does NOT silently revert to `{New,Confirmed}`.
   Exercise through: `DataIntelligencePage`
   Test doubles: mocked `fetchGeographicData`; dummy store
   Expected RED: no filter chips or filter math
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
3. Implement chips + default + filter math wiring → verify PASS → refactor → commit `feat(page): status and period filter chips`
4. Write failing test for: four empty-state precedence
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: component
   Test intent: Given no active snapshot / Then "Data peta belum tersedia" only; Given `geographic_section_available:false` / Then the same; Given filtered orders >0 and all matching coordinates invalid / Then "Outlet memiliki request tetapi koordinat belum tersedia" + count; Given filtered orders = 0 / Then "Tidak ada request pada periode ini" with no coordinate warning; Given a snapshot mixing v2 rows with v1/legacy rows / When period is 7d or Hari ini / Then the page computes and displays `outlets_without_daily_detail` = the count of outlets with filtered orders that lack `daily_by_status` (excluded from the narrow-period marker set), and the count is 0 for the 30d period.
   Exercise through: `DataIntelligencePage`
   Test doubles: mocked fetch per state
   Expected RED: no empty states
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
6. Implement the four precedence states → verify PASS → refactor → commit `feat(page): four precedence-ordered empty states`
7. Write failing test for: error classification + retry + role gate + freshness
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: component
   Test intent: Given `ApiError{status:500}` or a network error / Then alert + "Coba lagi", visible data cleared, no stale snapshot; Given 403 / Then "Akses ditolak" with no retry; Given 401 / Then the token is cleared and "Sesi berakhir. Silakan masuk kembali" shows; Given a non-admin with dummy mode on / Then access is denied before any dummy read; Given a loaded snapshot / Then the freshness label shows "Data per {window.end} (Asia/Jakarta)" and the version.
   Exercise through: `DataIntelligencePage`
   Test doubles: mocked `fetchGeographicData` rejecting with typed errors; mocked `/auth/me`
   Expected RED: no error classification/retry/role gate/freshness label
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
9. Implement error classification, retry, role gate, freshness label → verify PASS → refactor → commit `feat(page): error classification, retry, role gate, freshness label`
10. Write failing test for: drawer wiring with frozen filter
    Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
    Level: component
    Test intent: Given a marker click / When the drawer opens / Then it receives the opening filter snapshot; Given the filter then changes / Then the drawer keeps its frozen data and shows the banner.
    Exercise through: `DataIntelligencePage`
    Test doubles: mocked fetch + Leaflet mock
    Expected RED: drawer not wired
11. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
12. Implement drawer wiring → verify PASS → refactor → commit `feat(page): wire drawer with frozen filter snapshot`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Default active filter, Status & period filtering, Empty-state precedence, Error classification and retry, Admin-only visibility, Freshness
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: multiple UI states, error classification, drawer wiring, and role gating in one page component.

## SANDWICH CONTEXT
[CRITICAL: Empty-state precedence is strict — no snapshot > no geographic section > filtered orders>0 with all-invalid coords > filtered orders=0]
You are implementing the Data Intelligence page filter/empty/error/drawer integration.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/app/data-intelligence/page.tsx`, `apps/web/src/app/data-intelligence/page.test.tsx`
Available after: T1 (filter math), T5 (typed error), T7 (GeoMap click→drawer), T8 (drawer component)
Architecture rule: admin-only checked before any dummy read; typed-error `status` branching; no stale data after error; explicit empty-state messages
[RESTATE: Empty-state precedence is strict]

## DELIVERABLE
Given default load, Then `{New,Confirmed}`+30d chips are active and markers are filtered
Given no active snapshot, Then "Data peta belum tersedia" (no other messages)
Given filtered orders>0 with all-invalid coords, Then "Outlet memiliki request tetapi koordinat belum tersedia" + count
Given filtered orders=0, Then "Tidak ada request pada periode ini" with no coordinate warning
Given a snapshot with v1/legacy rows, When period is 7d/Hari ini, Then the page shows the `outlets_without_daily_detail` count for outlets with filtered orders but no `daily_by_status`
Given all statuses deselected, Then the no-request state renders and the selection is NOT reverted to `{New,Confirmed}`
Given a 5xx/network error, Then alert + retry, data cleared, no stale snapshot
Given 403, Then "Akses ditolak" with no retry; Given 401, Then token cleared + "Sesi berakhir"
Given a non-admin in dummy mode, Then denial happens before any dummy read
Given a loaded snapshot, Then the freshness label uses `window.end` + timezone and shows the version
[derived] Given an unknown deep-link status/period, Then it falls back to `{New,Confirmed}`+30d without a 500

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Chips are real `<button>`s with `aria-pressed`; `Semua` toggles all four
  - Empty status selection → no-request state (never silently reverted)
  - `outlets_without_daily_detail` computed in production for narrow periods and disclosed to the admin
  - Unknown deep-link status/period → safe fallback
  - Retry button only for retryable errors
  - Freshness label from `window.end`, never `new Date()`
  - Role checked before `withDummyRead`
Must-not-have:
  - Stale snapshot shown after a fetch error
  - Dummy data rendered before the role check
Open question risks:
  - `withDummyRead` timing conflicts with the role check → report NEEDS_CONTEXT
Rollback note:
  - Revert the page to the legacy layout (no filters/drawer/classified errors)
Red flags:
  - Dummy read before role check → STOP
  - Stale data rendered after error → STOP

## STOP CONDITIONS
Done when: all filter/empty/error/role/freshness scenarios pass
Uncertain when: the role source (`/auth/me`) is unavailable in the page context
Escalate when: the role gate cannot precede the dummy read
