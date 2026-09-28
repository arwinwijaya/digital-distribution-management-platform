# Task T10 — Integration — Page + GeoMap + Drawer filter sync

**Phase:** 3
**Depends:** T9
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 10: Integration — Page + GeoMap + Drawer filter sync [depends: T9]

## OBJECTIVE
Verify end-to-end synchronization: page filter state → GeoMap marker diff (no rebuild) → drawer opening-filter freeze → order link using the opening filter.

Steps:
1. Write failing test for: cross-component filter sync
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: integration (page + GeoMap + drawer)
   Test intent: Given the page with v2 data / When the admin toggles the `Delivered` chip / Then markers update without rebuilding the `L.Map` instance, an open drawer shows the freeze banner, and the drawer's order link still uses the opening filter.
   Exercise through: full page render + interactions
   Test doubles: mocked fetch with v2 data; Leaflet mock
   Expected RED: filter change rebuilds the map or the drawer/link uses the current filter
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
3. Write failing test for: order link navigation
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: integration
   Test intent: Given the drawer open for outlet 12 with `{New,Confirmed}`+7d / When "Lihat semua order" is activated / Then navigation targets `/admin/orders` with `outlet_id=12`, `status=New,Confirmed`, `start`, `end` all URL-encoded.
   Exercise through: full page render + link activation
   Test doubles: mocked fetch + router
   Expected RED: link missing or uses the current filter
4. Run test — verify FAIL: `cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`
5. Fix any remaining wiring → verify PASS → refactor → commit `test(integration): page-geomap-drawer filter sync`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Filter update performance, Drawer freeze, Order navigation preserves context
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: cross-unit integration verification that only holds when page, GeoMap, and drawer collaborate.

## SANDWICH CONTEXT
[CRITICAL: A filter change must NOT rebuild the Leaflet map, the drawer must freeze, and the order link must use the opening filter]
You are verifying the integration of page filters, GeoMap markers, and drawer state.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/app/data-intelligence/page.test.tsx`
Available after: T9 (all components wired)
Architecture rule: stable Leaflet instance, filter-freeze banner, additive encoded order query
[RESTATE: A filter change must NOT rebuild the Leaflet map]

## DELIVERABLE
Given a filter change, When the page updates, Then markers diff on the same map instance, the drawer shows the freeze banner, and the order link uses the frozen filter
Given the order link is activated, Then the URL carries `outlet_id`, `status` (csv), `start`, `end` encoded
[must-not] Given a filter change, When the page updates, Then the map must NOT rebuild and the drawer must NOT silently update

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Map instance identity preserved across filter changes
  - Freeze banner accurate
  - Order link query matches the drawer's opening filter, not the current one
Must-not-have:
  - Map rebuild on filter change
  - Drawer silently updating
Open question risks:
  - The test environment cannot verify map instance identity → report NEEDS_CONTEXT
Rollback note:
  - N/A (test-only task)
Red flags:
  - New production code beyond wiring fixes → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: integration tests pass, no map rebuild, drawer freeze works, link correct
Uncertain when: map instance identity is unverifiable in jsdom
Escalate when: an integration defect requires a design change
