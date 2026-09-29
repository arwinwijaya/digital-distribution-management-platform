# Task T5 — Price history panel pagination, retry, abort, and stale-response guard

**Phase:** 1
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: Price history panel pagination, retry, abort, and stale-response guard [depends: T4] [test-risk]

## OBJECTIVE
Create a Products-local `PriceHistoryPanel` used inside expanded detail that imports T2's exported response/API helpers and display helpers instead of reimplementing detail logic. Load five latest entries on expansion, hide load-more for exactly five/zero entries, support offset-cursor load more with duplicate-request protection, show empty/error/retry states, and safely handle deleted-product 404. Thread an `AbortController` and request identity/token guard from page table-state changes (filter, sort, page) so in-flight history cannot paint a different row; close expanded rows and abort history when table state changes. Keep dummy history behavior at full parity.

Steps:
1. Write failing test for: five latest entries and load-more visibility
   Test file: `apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx`
   Level: integration (panel + mocked history adapter boundary)
   Test intent: Given 12 entries / When the panel opens / Then five latest entries render and `Muat lebih banyak` is available; given exactly five entries / Then five render and load-more is hidden; given zero / Then empty history appears and load-more is hidden.
   Exercise through: `PriceHistoryPanel` rendered component and supplied fetch callback
   Test doubles: mock callback response; do not mock panel state or pagination logic
   Expected RED: no local panel exists; current page renders all fetched entries in a side card and has no load-more control.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/PriceHistoryPanel.test.tsx --runInBand`
3. Implement five-entry initial page, empty state, and conditional offset-cursor load-more UI → verify PASS → refactor while green → commit `feat(web): add expandable price history panel`
4. Write failing test for: history failure, retry, and deleted product 404
   Test file: `apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx`
   Level: integration
   Test intent: Given history callback rejects with a normal error / When panel opens / Then clear error and `Coba lagi` appear; retry invokes one new request; given a 404/deleted-product error / Then a clear deleted-product message appears and the close callback is called safely.
   Exercise through: panel fetch/retry/close callbacks
   Test doubles: rejected promises and callback spies; do not mock error rendering or close state
   Expected RED: no panel retry/404 classification or safe close behavior exists.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/PriceHistoryPanel.test.tsx --runInBand`
6. Implement typed error classification, retry, and safe 404 close handling → verify PASS → refactor while green → commit `feat(web): add price history retry handling`
7. Write failing test for: duplicate load-more protection and stale response suppression
   Test file: `apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx`
   Level: integration (async/concurrency seam)
   Test intent: Given more entries and a pending load-more request / When the button is clicked repeatedly / Then it is disabled and only one callback is processed; given a slower first-page request for product A that resolves after the panel has already switched to product B / Then A's rows never render in B; given request A resolves after the panel closed or unmounted / Then A's rows are ignored and no stale state is set.
   Exercise through: panel async public callbacks and request identity behavior
   Test doubles: deferred promises and AbortSignal-aware callback spies; do not mock the panel reducer/state under test
   Expected RED: current page has no in-flight guard, abort signal, or stale-response token.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/PriceHistoryPanel.test.tsx --runInBand`
9. Implement in-flight guard, request identity/token check, AbortController threading, and cleanup on unmount/product change → verify PASS → refactor while green → commit `fix(web): guard stale product price history responses`
10. Write failing test for: table state change aborts history and closes rows
    Test file: `apps/web/src/app/admin/products/page.test.tsx`
    Level: integration (Products page + adapter/network seam)
    Test intent: Given row A history is loading / When filter, sort, or page changes / Then expanded rows close, the request receives an aborted signal, and a late A response cannot render in another row; dummy mode follows the same visible lifecycle without network.
    Exercise through: rendered Products page state changes and mocked fetch signal
    Test doubles: deferred global fetch with an inspectable AbortSignal; do not mock page lifecycle or expansion state
    Expected RED: current page has no expanded-row lifecycle or abort cleanup when table state changes.
11. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
12. Integrate panel into ProductRowDetail/page and abort/reset history on filter/sort/page changes; preserve five-item offset contract and dummy parity → verify PASS → refactor while green → commit `feat(web): integrate safe expandable price history`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rule: Price history; Rules: Expanded detail, filter/sort changes, dummy parity. Code: existing `fetchPriceHistory`, `AdminProductController::prices`, page tests, dummy guards.
[CRITICAL: abort in-flight history and reject stale responses before any stale row state can render]

## WHY THIS APPROACH
Complexity: deep
Justification: this is a networked asynchronous UI seam with pagination, retry, 404 lifecycle, duplicate-click protection, AbortController cleanup, and cross-row stale-response hazards; a dedicated local panel keeps the concurrency logic testable without touching shared UI.

## SANDWICH CONTEXT
[CRITICAL: an aborted or stale history response must never paint another expanded row]
You are implementing Products-local price history behavior.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `PriceHistoryPanel.tsx`, its test, `api.ts`, `page.tsx`, `ProductRowDetail.tsx`, and mapped page tests.
Available after: T4 filter/state lifecycle.
Architecture rule: reuse existing offset cursor endpoint, no edit/rollback history, AbortController plus request identity guard, duplicate-load-more disabled, and dummy parity.
[RESTATE: Abort and stale-response guards are mandatory at the Products history boundary.]

## DELIVERABLE
Given 12 history rows / When expanding / Then five latest rows and load-more appear.
Given exactly five or zero rows / When expanding / Then load-more is hidden and the correct empty state appears.
Given a history failure / When displayed / Then retry is available; a 404 gives a clear deleted-product message and safe close.
Given load-more pending / When clicked repeatedly / Then exactly one request runs and button is disabled.
Given row A history pending / When filter, sort, page, product, or unmount state changes / Then the request is aborted/invalidated, rows close, and stale A data cannot paint another row.
Given dummy mode / When history is requested / Then no network is used and pagination/empty/error presentation remains equivalent.

## QUALITY BAR
Must-have:
  - Initial history page is five entries, using existing offset cursor response fields.
  - AbortController and a monotonically changing request identity/token both guard state updates.
  - Abort errors are silent; non-abort failures are retryable; 404 is explicit and closes safely.
  - Load-more cannot issue duplicate requests.
Must-not-have:
  - No changes to history edit/rollback semantics, endpoint shape, schema, or shared Table.
Open question risks:
  - Existing dummy history always has five rows → extend only the local dummy projection/test seam to exercise zero/more states, without changing global seed semantics.
Rollback note:
  - Revert local panel/page integration; existing side-history behavior can be restored without database changes.
Red flags:
  - A stale response updates shared `priceHistory` after product/state identity changes → STOP.

## STOP CONDITIONS
Done when: panel/page concurrency tests pass, signal abort is observed, no stale data paints, and dummy mode remains zero-network.
Uncertain when: browser abort behavior differs from Jest deferred signal behavior; preserve identity guard and report environment discrepancy.
Escalate when: satisfying the requirement requires changing the backend history endpoint or adding a dependency.
