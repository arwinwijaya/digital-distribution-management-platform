# Task T7 — Frontend — GeoMap marker-layer diff, fitBounds scope, coordinate parity, a11y list

**Phase:** 2
**Depends:** T1, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: Frontend — GeoMap marker-layer diff, fitBounds scope, coordinate parity, a11y list [depends: T1, T5]

## OBJECTIVE
Refactor `GeoMap.tsx` so the Leaflet map instance is stable across data/filter changes, markers are diffed by `outlet_id`, `fitBounds` runs only on initial valid data or explicit reset, center/zoom are preserved on filter change, a keyboard-accessible outlet list is exposed, tile failure shows a fallback, and `isValidPoint()` mirrors the backend rule exactly.

Steps:
1. Write failing test for: marker-layer diff with stable instance
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: component (Jest + Leaflet mock)
   Test intent: Given points A+B rendered / When points change to A+C / Then the `L.Map` instance is identical, B's marker is removed, C's marker is added, and center/zoom are unchanged.
   Exercise through: `GeoMap` rerender
   Test doubles: existing Leaflet mock
   Expected RED: `useEffect([points])` rebuilds the map
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/GeoMap.test.tsx`
3. Implement stable instance + marker diff → verify PASS → refactor → commit `feat(geomap): stable map with marker-layer diff`
4. Write failing test for: fitBounds scope
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: component
   Test intent: Given an initial valid-data render / Then `fitBounds` is called once with the valid points; Given a later filter change / Then `fitBounds` is NOT called and center/zoom are preserved.
   Exercise through: `GeoMap` rerender
   Test doubles: Leaflet mock with `fitBounds` spy
   Expected RED: fitBounds re-applied on every render
5. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/GeoMap.test.tsx`
6. Implement fitBounds guard + viewport preservation → verify PASS → refactor → commit `feat(geomap): fitBounds only on initial load or reset`
7. Write failing test for: coordinate parity + a11y list + tile fallback
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: component
   Test intent: Given the shared case table (`null`, `"-6.2"`, `NaN`, `Infinity`, `91`, `181`, `(0,0)` vs `(-6.2,106.8)`) / When `isValidPoint` / Then it matches the backend booleans; Given markers rendered / Then a focusable outlet list exposes every visible outlet and Enter opens the drawer; Given a tile-layer error / Then fallback text renders and the list stays usable.
   Exercise through: `GeoMap` + exported `isValidPoint`
   Test doubles: Leaflet mock
   Expected RED: `isValidPoint` accepts numeric strings; no list; no tile fallback
8. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/GeoMap.test.tsx`
9. Implement tightened `isValidPoint`, keyboard list, tile-error fallback → verify PASS → refactor → commit `feat(geomap): coordinate parity, keyboard list, tile fallback`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Coordinate safety and bounds, Filter update performance, Coordinate rule (plottable), Accessibility
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: Leaflet lifecycle management, marker-diff algorithm, and a11y additions with a mock-driven test harness.

## SANDWICH CONTEXT
[CRITICAL: The Leaflet map instance must remain stable — filter changes update markers only, never rebuild the map or tiles]
You are implementing the GeoMap marker-layer diff, fitBounds scope, coordinate parity, and a11y list.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/components/data-intelligence/GeoMap.tsx`, `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
Available after: T1 (filter helpers), T5 (typed error/points shape)
Architecture rule: no new map library (direct Leaflet only), one marker per outlet, marker only when filtered count > 0, stable instance, fitBounds initial/reset only, `isValidPoint` mirrors `isValidCoordinate`
[RESTATE: The Leaflet map instance must remain stable]

## DELIVERABLE
Given points change, When the component rerenders, Then the same `L.Map` instance is reused, markers diff by `outlet_id`, center/zoom preserved
Given initial valid points, When first render, Then `fitBounds(validPoints)` runs once
Given a filter change, When rerendering, Then `fitBounds` does NOT run
Given tile-layer failure, When rendering, Then fallback text shows and the outlet list remains usable
Given keyboard-only use, When tabbing the map section, Then the outlet list focuses and Enter opens the drawer
[derived] Given zero valid points, When the map initializes, Then the Jakarta default center/zoom is used and no Null Island marker renders

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Single `L.Map` instance across filter changes
  - Marker add/remove/update keyed by `outlet_id`
  - `fitBounds` only on mount-with-valid-data or explicit reset
  - `isValidPoint` rejects numeric strings, `NaN`, `Infinity`, `(0,0)` — parity with backend
  - Keyboard outlet list with an accessible label; Enter opens the drawer
  - Tile error fallback text with the list still usable
Must-not-have:
  - `react-leaflet` or any new map library
  - Map rebuild on filter change
Open question risks:
  - The Leaflet mock cannot spy on `fitBounds` → report NEEDS_CONTEXT
Rollback note:
  - Revert to the legacy `useEffect([points])` rebuild pattern
Red flags:
  - New dependency added → STOP
  - Map instance recreated on filter change → STOP

## STOP CONDITIONS
Done when: marker diff works, fitBounds preserved, a11y list functional, tile fallback renders
Uncertain when: the Leaflet mock lacks the needed API
Escalate when: viewport preservation is impossible without rebuilding
