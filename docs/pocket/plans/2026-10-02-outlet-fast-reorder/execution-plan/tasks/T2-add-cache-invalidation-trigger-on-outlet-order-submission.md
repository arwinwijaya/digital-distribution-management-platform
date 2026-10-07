# Task T2 — Add Cache Invalidation Trigger on Outlet Order Submission

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Add Cache Invalidation Trigger on Outlet Order Submission [depends: T1]

## OBJECTIVE
Panggil `invalidateOutletOrdersCache(token)` otomatis setelah `submitOutletOrder` sukses (online atau offline flush) agar cache order outlet ter-invalidate dan reorder section bisa refresh tanpa manual reload. Ganti pola manual `loadOutletCachedSections` dengan invalidasi cache sehingga fetchOutletDashboard dipanggil lagi saat dibutuhkan.

Steps:
1. Write failing test for: online submit success triggers cache invalidation (observed from OrderForm)
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration (component)
   Test intent: Given outletOrdersCache populated for token / When OrderForm submitOrder succeeds online (POST /orders 201) / Then invalidateOutletOrdersCache called once with same token; Given submit fails / When error caught / Then invalidate NOT called
   Exercise through: `render(<OrderForm token="test" />)` with mocked `fetch` for `/orders` POST success then failure; spy on `invalidateOutletOrdersCache` imported from `@/app/dashboard/api`
   Test doubles: mock `fetch` for `/orders`; spy on `invalidateOutletOrdersCache`; mock `loadOrderFormProducts` for catalog; do NOT mock OrderForm itself
   Expected RED: OrderForm `submitOrder` does not call `invalidateOutletOrdersCache` after success (spy call count 0)
2. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
3. Implement: Update `OrderForm.tsx` `submitOrder` to call `invalidateOutletOrdersCache(token)` on success (online path) only — guard with `if (response.ok)` / try success branch; ensure NOT called in catch.
3b. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx` (online success invalidates, failure does not)
4. Write failing test for: offline flush success also invalidates cache (observed from OrderForm)
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration (component)
   Test intent: Given queue has items and offline then online event fires with flushQueue sent>0 / When flushQueue succeeds / Then invalidateOutletOrdersCache called once; Given flushQueue sent==0 / Then NOT called
   Exercise through: `OrderForm` with mocked `createOrderQueue`/`flushQueue` and `useOnlineStatus` toggle via `window.dispatchEvent(new Event('online'))`
   Test doubles: mock queue `flushQueue` resolving `{sent:1}` then `{sent:0}`; spy `invalidateOutletOrdersCache`; mock `fetch` for catalog
   Expected RED: offline auto-flush useEffect does not call invalidation after flushQueue success
5. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
6. Update `OrderForm.tsx` offline auto-flush `useEffect` to call `invalidateOutletOrdersCache(token)` after `flushQueue` completes with `sent > 0` (and NOT on sent==0 or error).
6b. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx` (both online + offline flush paths)
7. Refactor: ensure dummy mode handles gracefully (no-op or mocked); ensure OrderForm preserves unified idempotency key from T1.
8. Commit: `fix(web): invalidate outlet orders cache on successful order submission (online + offline flush)`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — Rule R6 (cache miss triggers fetch), Edge: Concurrent Tabs Stale Cache, Implementation Notes (invalidateOutletOrdersCache call)
- Preflight: `apps/web/src/app/dashboard/api.ts` (lines 340–350: `invalidateOutletOrdersCache`, `loadOutletCachedSections`), `apps/web/src/components/OrderForm.tsx` (lines 180–210: `submitOrder`)

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single function call insertion at two success paths (online submit + offline flush); existing `invalidateOutletOrdersCache` already exports and works; test pattern established in `api.test.ts`.

## SANDWICH CONTEXT
[CRITICAL: Cache invalidation must happen AFTER successful order creation — both online submit and offline flush. Do NOT invalidate on error.]
You are implementing T2 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine
Files in scope: `apps/web/src/app/dashboard/api.ts`, `apps/web/src/components/OrderForm.tsx`, `apps/web/src/app/dashboard/api.test.ts`
Available after: T1 (idempotency unified so offline flush path is safe)
Architecture rule: Existing `outletOrdersCache` module-level Map; `invalidateOutletOrdersCache` clears by token prefix; offline queue flush in `OrderForm` useEffect
[RESTATE: Cache invalidation must happen AFTER successful order creation — both online submit and offline flush. Do NOT invalidate on error.]

## DELIVERABLE
Given outletOrdersCache populated, When online order submits successfully, Then cache invalidated for that token
Given queue has pending items, When online and flushQueue succeeds, Then cache invalidated for that token
Given order submission fails, When error caught, Then cache NOT invalidated

## QUALITY BAR
Must-have:
  - `invalidateOutletOrdersCache` called exactly once per successful order creation
  - Works for both online and offline flush paths
  - Dummy mode does not break

Must-not-have:
  - Cache invalidation on order failure
  - Direct mutation of `outletOrdersCache` from outside `api.ts`

Open question risks:
  - Spec assumes call in submitOutletOrder wrapper — if architecture requires different hook: NEEDS_CONTEXT

Rollback note:
  - If cache invalidation causes dashboard bugs, revert call; manual refresh remains workaround

Red flags:
  - Modifying `outletOrdersCache` directly from OrderForm → STOP
  - Not handling offline flush path → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, cache invalidation verified for both paths
Uncertain when: Dummy mode flush path behaves differently
Escalate when: Cache invalidation triggers infinite loop or double fetch
