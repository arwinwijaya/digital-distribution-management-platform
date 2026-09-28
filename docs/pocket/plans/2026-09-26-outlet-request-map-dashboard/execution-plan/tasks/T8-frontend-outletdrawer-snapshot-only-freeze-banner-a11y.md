# Task T8 — Frontend — OutletDrawer (snapshot-only, freeze banner, a11y)

**Phase:** 3
**Depends:** T1, T5, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Frontend — OutletDrawer (snapshot-only, freeze banner, a11y) [depends: T1, T5, T7]

## OBJECTIVE
Create `OutletDrawer.tsx`: a labelled modal dialog showing outlet name/territory/filtered count, status counts for the opening filter, daily breakdown, `latest_request`, and `product_summary` (labelled "Top 5 produk" when `product_summary_truncated`, snapshot-window scope when the period is <30d, and an explicit unavailable message for v1/partial rows); a filter-freeze banner when the map filter diverges; an encoded "Lihat semua order" link; and full keyboard semantics.

Steps:
1. Write failing test for: full v2 detail render
   Test file: `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
   Level: component
   Test intent: Given a v2 point with detail and opening filter `{New,Confirmed}`+30d / When the drawer opens / Then it shows the outlet/territory/count, status breakdown, daily data, `latest_request`, a product summary labelled correctly, and an order link whose query is `outlet_id`/`status`/`start`/`end` URL-encoded.
   Exercise through: `OutletDrawer` component
   Test doubles: none (props-only)
   Expected RED: component does not exist
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/OutletDrawer.test.tsx`
3. Implement the drawer render + order link → verify PASS → refactor → commit `feat(drawer): outlet detail drawer`
4. Write failing test for: v1/partial fallback + product labelling
   Test file: `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
   Level: component
   Test intent: Given a v1 row without status/daily / When the drawer opens / Then it shows "Detail produk belum tersedia — jalankan pipeline data" and invents no values; Given a v2 row with `product_summary_truncated:true` / Then the list is labelled "Top 5 produk"; Given a period <30d / Then the product section is labelled snapshot-window scope.
   Exercise through: `OutletDrawer`
   Test doubles: none
   Expected RED: no fallback/label branches
5. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/OutletDrawer.test.tsx`
6. Implement fallback + labelling → verify PASS → refactor → commit `feat(drawer): explicit detail fallback and product labelling`
7. Write failing test for: filter-freeze + keyboard semantics
   Test file: `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
   Level: component
   Test intent: Given the drawer open with opening filter F1 / When the map filter changes to F2 / Then the banner "Filter berubah — tutup dan buka ulang untuk memuat data terbaru" appears and the drawer data stays frozen at F1; Given `role="dialog"`+`aria-modal="true"` / When opened / Then focus moves inside and is contained; Given Escape/close/backdrop / Then it closes and focus returns to the exact opening trigger.
   Exercise through: `OutletDrawer`
   Test doubles: none
   Expected RED: no banner, no dialog semantics, no focus management
8. Run test — verify FAIL: `cd apps/web && npx jest src/components/data-intelligence/OutletDrawer.test.tsx`
9. Implement freeze banner + dialog a11y + focus trap/restore → verify PASS → refactor → commit `feat(drawer): filter-freeze banner and dialog accessibility`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Drawer is snapshot-only and filter-aware, Detail fallback is explicit, Order navigation preserves context, Drawer is a labelled modal dialog
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: a new component with multiple a11y requirements and opening-filter snapshot logic.

## SANDWICH CONTEXT
[CRITICAL: The drawer freezes its opening filter — it must never silently rewrite when the map filter changes]
You are implementing the outlet detail drawer.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/components/data-intelligence/OutletDrawer.tsx`, `apps/web/src/components/data-intelligence/OutletDrawer.test.tsx`
Available after: T1 (filter helpers), T5 (typed error), T7 (GeoMap click handler)
Architecture rule: snapshot-only data, filter-freeze banner, explicit fallback messages, order link with additive query, `role="dialog"`/`aria-modal`
[RESTATE: The drawer freezes its opening filter]

## DELIVERABLE
Given a v2 point, When the drawer opens, Then all detail sections render with the correct labels (Top 5 / snapshot-window / unavailable)
Given a v1/partial row, When the drawer opens, Then explicit unavailable messages render with no invented data
Given the drawer open and the map filter changed, Then the banner shows and the data stays frozen
Given keyboard interaction, Then Escape/close/backdrop closes and focus returns to the exact trigger
[must-not] Given a filter change, When the drawer is open, Then it must NOT silently rewrite its data

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `role="dialog"`, `aria-modal="true"`, accessible name = outlet name
  - Focus trap inside the drawer
  - Freeze banner compares opening filter vs current filter (status set + period)
  - Order link URL-encodes every value and joins statuses with a comma
  - Product labelling: "Top 5 produk" only when truncated; snapshot-window scope when period <30d; unavailable when absent
Must-not-have:
  - Silent data rewrite on filter change
  - Fabricated status/daily values for v1 rows
Open question risks:
  - Focus restoration to a Leaflet marker trigger is not feasible → fall back to the list alternative (report in DELIVERABLE)
Rollback note:
  - Remove the drawer component and revert to the marker popup
Red flags:
  - Drawer auto-updates on filter change → STOP
  - Focus not restored → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all drawer scenarios pass and focus restore is verified
Uncertain when: the opening trigger is not a stable focus target
Escalate when: a11y semantics cannot be satisfied without a new dependency
