# Task T8 — Build Next.js admin data-intelligence surfaces and Leaflet map

**Phase:** 3
**Depends:** T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Build Next.js admin data-intelligence surfaces and Leaflet map [depends: T7] [test-risk]

## OBJECTIVE
Build the admin-only Next.js data-intelligence page, typed API client and table/map/measurement components. `GeoMap.tsx` is a client component whose file begins with `'use client'`; the page dynamically imports `GeoMap` with `{ ssr: false }` because `react-leaflet` uses browser globals. Render tables independently before and regardless of map success, with required CSS/container height and OpenStreetMap attribution. Send frontend funnel events with UUID idempotency keys.

Files:
- Create: `apps/web/src/lib/data-intelligence-api.ts`
- Create: `apps/web/src/app/data-intelligence/page.tsx`
- Create: `apps/web/src/components/data-intelligence/GeoMap.tsx`
- Create: `apps/web/src/components/data-intelligence/TerritoryTable.tsx`
- Create: `apps/web/src/components/data-intelligence/SupplierPerformanceTable.tsx`
- Create: `apps/web/src/components/data-intelligence/StockPlanningTable.tsx`
- Create: `apps/web/src/components/data-intelligence/MeasurementCards.tsx`
- Modify: `apps/web/src/components/Sidebar.tsx`
- Test: `apps/web/src/app/data-intelligence/page.test.tsx`
- Test: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`

Steps:
1. Write failing test for: admin page consumes shared API contract and retains table fallback
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: unit
   Test intent: Given an authenticated admin and typed geographic/supplier/stock/measurement responses, When the data-intelligence page loads, Then it renders territory table data, supplier coverage/status, stock actions, WAPE/pending status and handles an API error without hiding the table fallback.
   Exercise through: page component and typed API client boundary.
   Test doubles: mock `fetch`/API responses and local storage; do not mock the page components under test.
   Expected RED: page/API client/components do not exist.
2. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'admin page consumes shared API contract'`
   Expected failure: module not found or missing rendered contract fields.
3. Implement minimal code: add typed API functions, admin session handling, page composition and table components using the shared types.
4. Run test — verify PASS: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'admin page consumes shared API contract'`
   Expected: PASS.
5. Refactor while green (bounded): keep API parsing in `data-intelligence-api.ts`, split components only at existing UI boundaries, and run `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'admin page consumes shared API contract'`; Expected: PASS.
6. Commit: `git add apps/web/src/lib/data-intelligence-api.ts apps/web/src/app/data-intelligence/page.tsx apps/web/src/components/data-intelligence/TerritoryTable.tsx apps/web/src/components/data-intelligence/SupplierPerformanceTable.tsx apps/web/src/components/data-intelligence/StockPlanningTable.tsx apps/web/src/components/data-intelligence/MeasurementCards.tsx apps/web/src/app/data-intelligence/page.test.tsx && git commit -m "feat(web): add data intelligence admin page"`

7. Write failing test for: Leaflet map renders client-only with attribution and stable height
   Test name: `leaflet_map_renders_client_only_with_attribution_and_stable_height`
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: unit
   Test intent: Given valid and invalid map points, When the geographic component renders in the browser, Then only valid points are rendered client-side, the map container has explicit non-zero height, the sibling tables remain usable when map rendering is unavailable, and OpenStreetMap attribution is present. The test also asserts `GeoMap.tsx` begins with `'use client'` and the page's dynamic import sets `ssr: false`.
   Exercise through: `GeoMap` component boundary and page dynamic-import boundary; test SSR import/render behavior and browser rendering separately.
   Test doubles: mock `react-leaflet` map primitives and tile network; do not mock `GeoMap` itself or sibling table components.
   Expected RED: no client-only boundary, `ssr: false` dynamic import, CSS height, coordinate filter or attribution exists.
8. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand src/components/data-intelligence/GeoMap.test.tsx -t 'leaflet_map_renders_client_only_with_attribution_and_stable_height'`
   Expected failure: module not found, SSR `window` error, missing `ssr: false`, no height, invalid-point, sibling-table or attribution assertion.
9. Implement minimal code: make `apps/web/src/components/data-intelligence/GeoMap.tsx` begin with exactly `'use client'`; in `apps/web/src/app/data-intelligence/page.tsx`, dynamically import `GeoMap` with exactly `{ ssr: false }` because `react-leaflet` uses browser globals; import Leaflet CSS, set an explicit non-zero map container height, filter null/non-numeric/out-of-range coordinates, configure the OSM tile URL with visible attribution, and render TerritoryTable, SupplierPerformanceTable, StockPlanningTable and MeasurementCards outside the map conditional so they render before the map and remain rendered when a map error/unavailable state occurs.
10. Run test — verify PASS: `cd apps/web && npm test -- --runInBand src/components/data-intelligence/GeoMap.test.tsx -t 'leaflet_map_renders_client_only_with_attribution_and_stable_height'`
    Expected: PASS; client-only assertions, `ssr: false`, valid-point filtering, non-zero height, OSM attribution and independent-table fallback assertions all pass.
11. Refactor while green (bounded): keep browser-global Leaflet code isolated to `GeoMap.tsx` and keep tables outside map error handling; run `cd apps/web && npm test -- --runInBand src/components/data-intelligence/GeoMap.test.tsx -t 'leaflet_map_renders_client_only_with_attribution_and_stable_height'` and require `Expected: PASS`; then run `cd apps/web && npx tsc --noEmit --pretty false` and require `Expected: PASS`.
12. Commit: `git add apps/web/src/components/data-intelligence/GeoMap.tsx apps/web/src/components/data-intelligence/GeoMap.test.tsx apps/web/src/app/data-intelligence/page.tsx && git commit -m "feat(web): render client-only OSM map"`

13. Write failing test for: frontend funnel event carries a UUID and replay-safe request
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: integration
   Test intent: Given an admin views recommendation results, When displayed/clicked/cart events are sent, Then the API client sends the event type, recommendation context and frontend UUID idempotency key to the admin measurement endpoint and does not generate a new key for the same retry.
   Exercise through: public `data-intelligence-api.ts` event method and fetch boundary.
   Test doubles: mock network `fetch` and UUID generator; do not mock the API client under test.
   Expected RED: event API method and stable UUID handling do not exist.
14. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'frontend funnel event carries a UUID'`
   Expected failure: missing method or request body/header assertion fails.
15. Implement minimal code: generate/store a UUID per event attempt, send it with the typed event payload, and handle replay/conflict response without inflating local counts.
16. Run test — verify PASS: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'frontend funnel event carries a UUID'`
    Expected: PASS.
17. Refactor while green (bounded): keep UUID generation/replay behavior in the named API client module; run `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'frontend funnel event carries a UUID'` and `cd apps/web && npx tsc --noEmit --pretty false`; require Expected: PASS for both.
18. Commit: `git add apps/web/src/lib/data-intelligence-api.ts apps/web/src/app/data-intelligence/page.tsx apps/web/src/app/data-intelligence/page.test.tsx apps/web/src/components/Sidebar.tsx && git commit -m "feat(web): add measurable intelligence interactions"`

## REFERENCES LOADED
- Spec frontend/admin, map and funnel requirements plus Leaflet/OpenStreetMap decision.
- `apps/web/src/app/dashboard/page.tsx`, `apps/web/src/app/analytics/page.tsx`, `apps/web/src/lib/api.ts`, `apps/web/src/components/Sidebar.tsx` — authenticated page/fetch/navigation conventions.
- `apps/web/src/components/ui/*` — existing table/card/loading/error presentation components.
- `apps/web/package.json` — Jest/Testing Library/Next/React versions.
- Context7 React Leaflet docs checked for client-only rendering under Next.js, CSS import and explicit map height, tile-layer attribution and OSM usage constraints.

## WHY THIS APPROACH
The frontend is one bounded admin surface but map rendering is isolated as a separate component/test boundary so SSR failures cannot break table BI. Complexity: standard/test-risk due to Next client/server boundaries, network contracts and Leaflet browser globals.

## SANDWICH CONTEXT
[CRITICAL: Leaflet must never execute during server rendering; the table is the required fallback and every OSM tile layer must carry attribution.]
You are implementing the Next.js admin data-intelligence surface for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: deterministic backend snapshots consumed by an admin-only Next.js surface; Leaflet + OSM for MVP.
Files in scope: only files listed in this task.
Test framework: Jest + Testing Library and TypeScript compiler.
Available after: T7 backend contracts/routes.
Architecture rule: use shared types/API client, keep admin auth at request boundary, isolate Leaflet client-only.
[RESTATE: Leaflet must never execute during server rendering; the table is the required fallback and every OSM tile layer must carry attribution.]

## DELIVERABLE
Given typed admin BI responses, When page loads, Then territory/supplier/stock/measurement cards render and table fallback remains available on error/map failure.
Given valid/invalid coordinates, When map renders in browser, Then only valid points render in a client-only map with explicit height and OSM attribution.
Given an event interaction, When frontend sends measurement event, Then a stable UUID idempotency key is included and replay does not create a new key.
All tests PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- New admin navigation/page consumes exact shared API types.
- Leaflet + react-leaflet client-only integration, CSS and explicit height, valid-coordinate filtering, OSM attribution and table fallback.
- Funnel events carry UUID idempotency keys.
- `npm test` targeted commands and `npx tsc --noEmit` pass.

Must-not-have:
- No server-rendered Leaflet access, map provider credentials, hidden table fallback, non-admin data surface, or new backend contract invented in frontend.

Open question risks:
- Public OSM tile availability is accepted for MVP; if provider policy changes, report concern rather than adding Mapbox/Google.

Rollback note:
- Remove navigation/page/map bundle while backend snapshots and legacy routes remain available.

## STOP CONDITIONS
Done when: all three frontend scenarios pass, TypeScript passes and commits exist.
Uncertain when: selected react-leaflet version cannot satisfy the existing Next/React toolchain.
Escalate when: map code requires server globals, attribution is absent, or table fallback is removed.
