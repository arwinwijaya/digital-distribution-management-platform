# Task T6 — Implement useOutletDiscovery hook logic

**Phase:** 2
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Implement useOutletDiscovery hook logic [depends: T5]

## OBJECTIVE
Implement full hook logic: fetch discovery, handle online/offline/reconnect, send tracking events, add-to-cart with current catalog data, dismiss session-only, cart deduplication.

Steps:
1. Write failing test for: useOutletDiscovery_adds_item_with_current_catalog_price
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery item P3 with catalog price 12000 stock 5, When addToCart(P3), Then onAdd called with product_id and current catalog price
   Exercise through: hook addToCart callback
   Test doubles: mock onAdd, mock products catalog
   Expected RED: addToCart not implemented or uses stale data

2. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand --runTestsByPath src/hooks/useOutletDiscovery.test.ts`

3. Write failing test for: useOutletDiscovery_hides_item_when_stock_zero_in_catalog
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery item P3 but catalog shows stock 0, When hook returns items, Then P3 marked unavailable and add button disabled
   Exercise through: hook return value
   Test doubles: mock products catalog with stock 0
   Expected RED: stock check not implemented

4. Run test — verify FAIL

5. Write failing test for: useOutletDiscovery_dismiss_session_only
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery items, When dismiss(P3) then reload hook, Then P3 reappears (session-only)
   Exercise through: hook dismiss + re-render
   Test doubles: mock fetch response
   Expected RED: dismiss not implemented or persists

6. Run test — verify FAIL

7. Write failing test for: useOutletDiscovery_sends_tracking_events_fire_and_forget
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery items, When mount + click add, Then fetch called to /api/ai/discovery/events for displayed (once per item) + clicked+cart (on add), and fetch errors caught silently with console.warn
   Exercise through: hook lifecycle + addToCart
   Test doubles: mock fetch for both discovery + events
   Expected RED: tracking not implemented

8. Run test — verify FAIL

9. Write failing test for: useOutletDiscovery_cart_deduplication
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery items including P3, When cart already contains P3, Then P3 excluded from returned discovery items
   Exercise through: hook return value with cart param
   Test doubles: mock products catalog
   Expected RED: dedup not implemented

10. Run test — verify FAIL

11. Write failing test for: useOutletDiscovery_refetches_on_reconnect
    Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
    Level: unit
    Test intent: Given hook offline with cached items, When online status changes to true, Then fetch re-triggered and items updated
    Exercise through: hook with mocked useOnlineStatus transition
    Test doubles: mock useOnlineStatus, mock fetch
    Expected RED: reconnect listener not implemented

12. Run test — verify FAIL

13. Implement `useOutletDiscovery`:
    - useEffect: fetch `/api/ai/discovery` when online && token && !loading
    - Listen to `useOnlineStatus()` changes; refetch when transitioning offline → online
    - Map response to DiscoveryItem[] with reason from API
    - Filter against cart + dismissed Set (memory)
    - `addToCart(productId)`: find product in `products` catalog (latest price/stock), if available call `setCart` + `onAdd` + send clicked+cart events
    - `dismiss(productId)`: add to dismissed Set
    - Tracking: on mount send 'displayed' for each item; on add send 'clicked' + 'cart'; all fetch with try/catch + console.warn; event payload includes crypto.randomUUID(), product_id, occurred_at (ISO now)
    - Offline: return empty + loading false (component handles hide)

14. Run all 6 tests — verify PASS

15. Refactor while green: extract tracking helper if reused

16. Commit: `git commit -m "feat(web): implement useOutletDiscovery with catalog sync, dismiss, cart dedup, reconnect, and fire-and-forget tracking"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: tambah ke keranjang + validasi, dismiss session-only, tracking funnel, cart dedup, offline/reconnect

## WHY THIS APPROACH
Complexity: standard
Justification: Hook with multiple async behaviors (fetch, tracking, catalog sync, reconnect); parallelizable with component task.

## SANDWICH CONTEXT
[CRITICAL: Add-to-cart uses current catalog (not stale display data); dismiss session-only; tracking fire-and-forget; cart dedup; reconnect refetch]
You are implementing Task 6 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/src/hooks/useOutletDiscovery.ts, apps/web/src/hooks/useOutletDiscovery.test.ts
Available after: T5 (scaffold)
Architecture rule: Reuse useOnlineStatus; feature flag; cache invalidation after order
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
Given item P3 price 12000 stock 5, When addToCart, Then onAdd called with current price
Given catalog stock 0 for P3, When hook runs, Then P3 unavailable (button disabled)
Given dismiss P3 then reload, Then P3 reappears
Given mount + add, Then tracking events sent (displayed per item, clicked+cart on add) with console.warn on error
Given cart contains P3, When hook runs, Then P3 excluded from discovery items
Given offline→online transition, When status changes, Then fetch re-triggered

## QUALITY BAR
Must-have:
  - Catalog sync on add (price/stock from products prop)
  - Dismiss in memory Set (cleared on reload)
  - Tracking events: displayed on mount, clicked+cart on add, all try/catch + console.warn
  - Cart deduplication
  - Reconnect refetch
  - Offline: empty items, component handles hide

Must-not-have:
  - Persistent dismiss
  - Auto-add
  - Blocking tracking

Open question risks:
  - Double-click add debounce: handled in component (button disabled during pending)

Rollback note:
  - Revert hook to scaffold

## STOP CONDITIONS
Done when: all 6 tests pass
Escalate when: catalog sync race condition with products prop updates
