# Task T7 — Implement DiscoverySection component UI

**Phase:** 2
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: Implement DiscoverySection component UI [depends: T5]

## OBJECTIVE
Implement component rendering: items with reason, price, stock, add button (disabled when stock 0), dismiss button, offline state, fetch error state with retry, empty state.

Steps:
1. Write failing test for: DiscoverySection_renders_items_with_reason_add_dismiss
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given items with reason, When rendered, Then each item shows name, price, stock, reason, 'Tambah' button, dismiss button
   Exercise through: render(<DiscoverySection items={items} onAdd={fn} onDismiss={fn} />)
   Test doubles: mock callbacks
   Expected RED: component renders empty or missing elements

2. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand --runTestsByPath src/components/DiscoverySection.test.tsx`

3. Write failing test for: DiscoverySection_disables_add_when_stock_zero
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given item with stock 0, When rendered, Then 'Tambah' button disabled with 'Stok habis' tooltip/text
   Exercise through: render with stock 0 item
   Test doubles: none
   Expected RED: stock check UI not implemented

4. Run test — verify FAIL

5. Write failing test for: DiscoverySection_offline_shows_message
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given offline=true, When rendered, Then section shows 'tersedia saat online' without items
   Exercise through: render(<DiscoverySection items={[]} offline={true} />)
   Test doubles: none
   Expected RED: offline UI not implemented

6. Run test — verify FAIL

7. Write failing test for: DiscoverySection_fetch_error_shows_retry
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given error prop set, When rendered, Then shows error notice + 'Coba lagi' button that calls onRetry
   Exercise through: render(<DiscoverySection error="Failed" onRetry={fn} />)
   Test doubles: mock onRetry
   Expected RED: error UI not implemented

8. Run test — verify FAIL

9. Write failing test for: DiscoverySection_empty_state_shows_message
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given empty items array (no error, not offline), When rendered, Then shows 'Belum ada rekomendasi baru saat ini' without add buttons
   Exercise through: render(<DiscoverySection items={[]} offline={false} error={null} />)
   Test doubles: none
   Expected RED: empty state UI not implemented

10. Run test — verify FAIL

11. Implement `DiscoverySection.tsx`:
    - Section wrapper with title "Coba produk lain"
    - Grid of cards (match ReorderSection visual style)
    - Each card: name, price (Rp formatted), stock badge, reason text (small, muted), "Tambah" button (disabled if unavailable), dismiss button (icon only)
    - Offline: render message "tersedia saat online" in place of items
    - Error: render alert with message + "Coba lagi" button (calls onRetry)
    - Empty: "Belum ada rekomendasi baru saat ini"
    - Accessibility: proper aria-labels, semantic HTML

12. Run all 5 tests — verify PASS

13. Refactor while green: extract item card subcomponent if complex

14. Commit: `git commit -m "feat(web): implement DiscoverySection with reason, add, dismiss, offline, error, empty states"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: tambah ke keranjang, dismiss session-only, offline, fetch error, empty state

## WHY THIS APPROACH
Complexity: standard
Justification: Component with 5 UI states (items, offline, error, empty, stock 0); parallelizable with hook task.

## SANDWICH CONTEXT
[CRITICAL: Visual parity with ReorderSection; offline hides section; error non-blocking with retry; empty state message]
You are implementing Task 7 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/src/components/DiscoverySection.tsx, apps/web/src/components/DiscoverySection.test.tsx
Available after: T5 (scaffold)
Architecture rule: Feature flag; reuse StatusBadge, Card, Button components
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
Given items with reason, When rendered, Then cards show name, price, stock, reason, add, dismiss
Given item stock 0, When rendered, Then add button disabled with 'Stok habis'
Given offline=true, When rendered, Then 'tersedia saat online' message, no items
Given error prop, When rendered, Then error notice + 'Coba lagi' button
Given empty items + not offline + no error, When rendered, Then 'Belum ada rekomendasi baru saat ini'

## QUALITY BAR
Must-have:
  - Visual consistency with ReorderSection (Card, StatusBadge, Button)
  - Reason text visible per item
  - Dismiss button per item
  - Offline + error + empty states

Must-not-have:
  - Persistent dismiss
  - Auto-add
  - Blocking error

Rollback note:
  - Revert component to scaffold

## STOP CONDITIONS
Done when: all 5 tests pass
Escalate when: visual regression with ReorderSection
