# Task T5 — Scaffold web hook + component stubs + test files

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: Scaffold web hook + component stubs + test files [depends: T2]

## OBJECTIVE
Create minimal `useOutletDiscovery` hook and `DiscoverySection` component stubs BEFORE writing tests.

Steps:
1. Create stub for `apps/web/src/hooks/useOutletDiscovery.ts` returning empty discovery array and loading false
2. Create stub for `apps/web/src/components/DiscoverySection.tsx` returning null or empty container
3. Write failing test for: useOutletDiscovery_fetches_discovery_when_online
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit (React hook test with Jest + React Testing Library)
   Test intent: Given token + products + online=true, When hook runs, Then fetch called to /api/ai/discovery and returns discovery items
   Exercise through: renderHook with mocked fetch
   Test doubles: mock fetch to return sample discovery items
   Expected RED: fetch not called / stub returns empty
4. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand --runTestsByPath src/hooks/useOutletDiscovery.test.ts`
5. Write failing test for: DiscoverySection_renders_items_with_reason_and_add_button
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit (React component test)
   Test intent: Given discovery items with reason, When rendered, Then shows items with reason text and 'Tambah' button
   Exercise through: render(<DiscoverySection items={...} onAdd={...} />)
   Test doubles: mock onAdd callback
   Expected RED: items not rendered by stub
6. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand --runTestsByPath src/components/DiscoverySection.test.tsx`
7. Commit: `git commit -m "test(web): scaffold useOutletDiscovery hook and DiscoverySection component with stubs"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: tambah ke keranjang, dismiss session-only, offline

## WHY THIS APPROACH
Complexity: lightweight
Justification: Scaffold-only web task mirroring useOutletReorder/ReorderSection patterns; stubs first so tests don't hit import errors.

## SANDWICH CONTEXT
[CRITICAL: Must follow useOutletReorder pattern; dismiss session-only (memory); offline hides section]
You are implementing Task 5 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A (web integration in OrderForm)
Files in scope: apps/web/src/hooks/useOutletDiscovery.ts, apps/web/src/components/DiscoverySection.tsx, apps/web/src/hooks/useOutletDiscovery.test.ts, apps/web/src/components/DiscoverySection.test.tsx
Available after: T2 (API contract stable)
Architecture rule: Feature flag NEXT_PUBLIC_ENABLE_DISCOVERY !== 'false'; reuse useOnlineStatus; cache invalidation after order
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
[derived] Given scaffold exists, When tests run, Then hook/component files present and tests fail with expected RED (fetch not called, empty render)

## QUALITY BAR
Must-have:
  - Hook signature mirrors useOutletReorder
  - Component renders items with reason + add + dismiss
  - Offline handling: hide section + message
  - Feature flag guard

Must-not-have:
  - Persistent dismiss
  - Auto-add
  - Changes to ReorderSection

Open question risks:
  - Event tracking: hook should send displayed/clicked/cart via fetch to /api/ai/discovery/events (fire-and-forget with console.warn)

Rollback note:
  - Delete hook and component files

## STOP CONDITIONS
Done when: test files exist and fail with expected RED
Escalate when: test framework missing or config issue
