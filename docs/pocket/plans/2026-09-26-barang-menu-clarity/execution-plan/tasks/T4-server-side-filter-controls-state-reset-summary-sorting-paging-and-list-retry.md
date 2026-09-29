# Task T4 — Server-side filter controls, state reset, summary, sorting, paging, and list retry

**Phase:** 1
**Depends:** T2, T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Server-side filter controls, state reset, summary, sorting, paging, and list retry [depends: T2, T3] [test-risk]

## OBJECTIVE
Add Products-local category/status/stock-health controls and wire them to `fetchAdminProducts`. Use AND semantics through the adapter, preserve the selected sort after filter changes, reset cursor to 0, close all expanded rows, and recompute summary from the filtered response. Render complete category metadata including `Tanpa kategori`; retain search/paging behavior; display a retryable error state for list timeout/database failures. Ensure filter controls do not change the legacy request when new values are absent.

Steps:
1. Write failing test for: combined filter URL, cursor reset, sort preservation, and expansion close
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration (rendered page + mocked HTTP boundary)
   Test intent: Given a selected status/category/stock filter, an active non-default sort, page cursor 15, and an expanded row / When a filter changes / Then the next request contains all filters with cursor 0 and existing sort/order, the expanded detail closes, the rendered summary comes from filtered `meta.summary`, category options are `Semua kategori` + all backend categories + `Tanpa kategori`, and selecting `Tanpa kategori` sends the explicit no-category request and returns only null/empty-category rows.
   Exercise through: Products page controls and fetch boundary
   Test doubles: mock global `fetch` responses; do not mock page state, adapter, or Table
   Expected RED: current page has no filter controls and filter changes cannot reset cursor/close expansion while preserving sort.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
3. Implement Select controls, filter state, reset/close orchestration, and summary/paging wiring → verify PASS → refactor while green → commit `feat(web): add server-side product filters`
4. Write failing test for: complete category/status/stock options and API values
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given backend `meta.categories` containing only distinct non-empty categories / When the category filter opens and each health/status option is selected / Then options are `Semua kategori` + all backend categories + frontend-added `Tanpa kategori`; selecting `Tanpa kategori` sends the explicit no-category request; status labels are `Semua/Aktif/Nonaktif/Tidak bisa dibeli`, health labels map to `out/low/ok`, and the request sends the backend values exactly.
   Exercise through: rendered Select controls and request URL
   Test doubles: mock fetch only
   Expected RED: no category/status/health controls or API value mapping exists.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
6. Implement metadata-driven category options, explicit filter labels, and API value mapping; keep invalid values out of client state → verify PASS → refactor while green → commit `feat(web): add product filter options`
7. Write failing test for: list failure and retry
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given `GET /products` rejects/times out / When the page loads / Then an alert/error state and `Coba lagi` action appear; when retry is activated / Then a new request is made and a successful response renders.
   Exercise through: page initial load and retry button
   Test doubles: mock/reject global fetch; do not mock error state or retry callback
   Expected RED: current error is plain text without retry action and page has no explicit retry path.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
9. Implement retryable list error state, loading-safe controls, legacy search behavior, and filter-change lifecycle cleanup → verify PASS → refactor while green → commit `feat(web): add product list retry state`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rules: Filter contract; Status filters overlap; Filtered summary and paging; Product list failure; Implementation Notes. Code: Products page/API, Select, TableSummary, TablePagination, existing page tests.
[CRITICAL: filters are server-side; filter/sort changes reset cursor and close expanded rows while sort remains selected]

## WHY THIS APPROACH
Complexity: standard
Justification: this task coordinates several page state machines (filters, cursor, sort, expansion, summary, and retry) across the adapter seam and must preserve existing pagination/search behavior.

## SANDWICH CONTEXT
[CRITICAL: never implement category/status/health filtering by slicing the current page; every filter request must reach the server adapter]
You are implementing the Products filter and list-state layer.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `apps/web/src/app/admin/products/page.tsx`, `api.ts`, and `page.test.tsx`; use T2 helper/types and T3 local expansion.
Available after: T2 and T3.
Architecture rule: AND semantics are expressed in request params; filter/sort changes reset cursor to 0, preserve sort, close expanded rows, and summary uses filtered server metadata.
[RESTATE: Filtering is server-side and state changes must reset cursor/close rows without losing sort.]

## DELIVERABLE
Given category/status/health selections / When the list reloads / Then all selected values are sent together, only matching server results render, cursor is 0, expanded rows are closed, and sort is preserved.
Given backend category metadata / When the category filter opens / Then distinct categories and Tanpa kategori are available.
Given stock options / When selected / Then Habis/Rendah/Aman map to out/low/ok.
Given filtered response summary / When rendered / Then `{total,out_of_stock}` is shown for the filtered set.
Given request timeout/failure / When retry is clicked / Then the list request runs again and success replaces the error state.
Given no new params / When legacy search/list loads / Then existing default request behavior remains.

## QUALITY BAR
Must-have:
  - No client-side filtering of paginated product rows.
  - Filter changes reset cursor to 0, close all expanded rows, and preserve selected sort/order.
  - Summary and `has_more` are taken from the filtered server response.
  - Retry action is keyboard accessible and does not duplicate requests while loading.
Must-not-have:
  - No changes to public catalog behavior, shared Table, or backend semantics in this frontend task.
Open question risks:
  - Existing response metadata may omit categories on legacy callers → treat missing metadata as empty options while preserving list rendering; report only if backend T1 contract cannot supply it.
Rollback note:
  - Revert page filter/state changes; adapter remains backward-compatible.
Red flags:
  - Filtering current rows instead of sending server params → STOP.

## STOP CONDITIONS
Done when: all page tests pass for filters/options/reset/summary/retry and existing sort/search/paging tests remain green.
Uncertain when: a filter interaction causes duplicate request races; add request identity/cleanup before proceeding.
Escalate when: satisfying filters requires changing shared Table or public catalog behavior.
