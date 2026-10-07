# Task T8 — Integrate DiscoverySection into OrderForm

**Phase:** 2
**Depends:** T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Integrate DiscoverySection into OrderForm [depends: T6, T7]

## OBJECTIVE
Render DiscoverySection below ReorderSection in OrderForm, pass props from useOutletDiscovery, handle feature flag, dummy-mode hide, refresh after order.

Steps:
1. Write failing test for: OrderForm_renders_discovery_below_reorder
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given token + products + online + feature flag enabled, When OrderForm mounts, Then ReorderSection renders, then DiscoverySection renders below it
   Exercise through: render <OrderForm token="..." products={[...]} />
   Test doubles: mock useOutletReorder, useOutletDiscovery, useOnlineStatus
   Expected RED: DiscoverySection not rendered

2. Run test — verify FAIL: `npm test -- --runInBand --runTestsByPath src/components/OrderForm.test.tsx`
   (amended from `npm test -t OrderForm_renders_discovery_below_reorder` — see T5/T6/T7 packet amendments)

3. Write failing test for: OrderForm_discovery_hidden_when_feature_flag_disabled
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given NEXT_PUBLIC_ENABLE_DISCOVERY=false, When OrderForm mounts, Then DiscoverySection not rendered
   Exercise through: render with env mock
   Test doubles: mock env
   Expected RED: feature flag not checked

4. Run test — verify FAIL: `npm test -- --runInBand --runTestsByPath src/components/OrderForm.test.tsx`
   (file-targeted gate; see T5/T6/T7 amendments)

5. Write failing test for: OrderForm_discovery_hidden_in_dummy_mode
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given useDummyStore returns isDummy=true, When OrderForm mounts, Then DiscoverySection not rendered
   Exercise through: render OrderForm with dummy mock
   Test doubles: mock useDummyStore
   Expected RED: dummy guard not implemented

6. Run test — verify FAIL: `npm test -- --runInBand --runTestsByPath src/components/OrderForm.test.tsx`
   (file-targeted gate; see T5/T6/T7 amendments)

7. Write failing test for: OrderForm_refreshes_discovery_after_order_success
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given discovery items showing, When order submitted successfully, Then discovery refreshes (items purchased removed)
   Exercise through: render OrderForm, submit order mock success
   Test doubles: mock submitOutletOrder, mock useOutletDiscovery refresh
   Expected RED: refresh not triggered

8. Run test — verify FAIL: `npm test -- --runInBand --runTestsByPath src/components/OrderForm.test.tsx`
   (file-targeted gate; see T5/T6/T7 amendments)

9. Modify `OrderForm.tsx`:
   - Import `useOutletDiscovery` and `DiscoverySection`
   - Add `NEXT_PUBLIC_ENABLE_DISCOVERY !== 'false'` guard (like `reorderEnabled`)
   - Initialize `const dismissedRef = useRef(new Set<number>());` once in component body; pass `dismissedRef.current` and `addDismissed: (id) => dismissedRef.current.add(id)` to hook
   - Render `<DiscoverySection />` below `<ReorderSection />` with props from hook
   - Pass `offline={!isOnline}` and `error` + `onRetry` from hook
   - Add `isDummy` guard (mirror `showReorderSection`: hide discovery when dummy mode)
   - After successful submit, increment `discoveryRefreshKey` (like `reorderRefreshKey`) to trigger refetch

10. Run all 4 tests — verify PASS: `npm test -- --runInBand --runTestsByPath src/components/OrderForm.test.tsx` (gate: 28 passed)

11. Refactor while green: extract hook options setup to avoid inline ref

12. Commit: `git commit -m "feat(web): integrate DiscoverySection into OrderForm with feature flag, dummy guard, and post-order refresh"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — all web integration rules

## WHY THIS APPROACH
Complexity: standard
Justification: Integration task wiring hook + component into existing OrderForm; follows existing ReorderSection integration pattern exactly.

## SANDWICH CONTEXT
[CRITICAL: DiscoverySection below ReorderSection; feature flag guard; dummy-mode hide; refresh after order like reorder]
You are implementing Task 8 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/src/components/OrderForm.tsx, apps/web/src/components/OrderForm.test.tsx
Available after: T6 (hook works), T7 (component works)
Architecture rule: Feature flag NEXT_PUBLIC_ENABLE_DISCOVERY; dummy-mode guard; cache invalidation after order; placement below "Pesan Lagi"
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
Given token + products + online + flag enabled + not dummy, When OrderForm mounts, Then DiscoverySection renders below ReorderSection
Given flag disabled, When OrderForm mounts, Then DiscoverySection not rendered
Given dummy mode, When OrderForm mounts, Then DiscoverySection not rendered
Given order submitted successfully, When order completes, Then discovery refetches and purchased items excluded

## QUALITY BAR
Must-have:
  - Placement exactly below ReorderSection (same section wrapper)
  - Feature flag guard matching reorderEnabled pattern
  - Dummy-mode guard matching showReorderSection pattern
  - Refresh after order (increment key)
  - Props: offline, error, onRetry from hook

Must-not-have:
  - Changes to ReorderSection behavior
  - Changes to OrderCreationService
  - Auto-add

Rollback note:
  - Remove DiscoverySection render and hook call from OrderForm

## STOP CONDITIONS
Done when: all 4 tests pass, visual integration correct
Escalate when: OrderForm test setup missing or complex
