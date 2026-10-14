# Task T3 — Implement `useOutletReorder` Hook & Computation Logic

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Implement `useOutletReorder` Hook & Computation Logic [depends: T2]

## OBJECTIVE
Implement custom hook `useOutletReorder(token, products)` yang mengencapsulasi logika reorder: memuat cache outlet orders (via `fetchOutletDashboard` atau `loadOutletCachedSections`), menghitung favorit top-5 (via `topFavorites` dari `outlet-helpers.ts`), mengekstrak item unik dari 3 order terakhir, memfilter produk nonaktif, enriching dengan data `products` param (harga, stok, is_active), dan mengembalikan computed reorder items + actions `addToCart(productId, lastQty)` dan `reorderLastOrder()`.

Steps:
1. Write failing test for: reorder list composition (R1) — favorites + 3 recent unique, exclude inactive
   Test file: `apps/web/src/hooks/useOutletReorder.test.ts`
   Level: unit
   Test intent: Given outletOrdersCache with favorites [A,B,C,D,E] and 3 recent orders items [A×2, F, G, B, H(inactive)] / When useOutletReorder loads with products param / Then reorderItems = [A,B,C,D,E,F,G] (H excluded, dedup)
   Exercise through: `renderHook` from `@testing-library/react` with mocked `fetchOutletDashboard` / `loadOutletCachedSections` returning cached orders; provide `products` array directly as param (do NOT mock loadOrderFormProducts or /products fetch)
   Test doubles: mock `fetchOutletDashboard` / `loadOutletCachedSections`; provide `products` array fixture directly; do NOT mock `loadOrderFormProducts`
   Expected RED: assertion that reorderItems equals [A,B,C,D,E,F,G] with H excluded and dedup fails because the aggregation path currently returns an empty list via loadOutletCachedSections fallback
2. Run test — verify FAIL: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
3. Implement R1: hook scaffold + `fetchOutletDashboard`/`loadOutletCachedSections` loading + `topFavorites` + dedup/filter inactive. Return `{ reorderItems: [], addToCart, reorderLastOrder, loading }` with composition logic only (no enrichment, no credit yet).
3b. Run test — verify PASS: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
4. Write failing test for: current price enrichment (R5) — uses products param price, not historical
   Test file: `apps/web/src/hooks/useOutletReorder.test.ts`
   Level: unit
   Test intent: Given historical order with product A price 10000 / When products param returns A price 12000 / Then reorderItem.current_price = 12000 (and last_qty from history)
   Exercise through: hook with `products` array fixture containing current price (no loadOrderFormProducts mock)
   Test doubles: mock `fetchOutletDashboard` / `loadOutletCachedSections`; provide `products` array directly
   Expected RED: enrichment logic not implemented
5. Run test — verify FAIL: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
6. Implement R5: enrich each reorderItem with current_price, stock_quantity, is_active from `products` param matched by product_id.
6b. Run test — verify PASS: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
7. Write failing test for: cache miss triggers fetch once (R6)
   Test file: `apps/web/src/hooks/useOutletReorder.test.ts`
   Level: unit
   Test intent: Given outletOrdersCache empty for token / When hook mounts / Then fetchOutletDashboard called exactly once, then cache populated
   Exercise through: hook with mocked `fetchOutletDashboard` (loadOutletCachedSections returns empty)
   Test doubles: mock `fetchOutletDashboard`; spy call count; provide `products` array
   Expected RED: hook does not call fetch on empty cache
8. Run test — verify FAIL: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
9. Implement R6: conditional fetchOutletDashboard only when loadOutletCachedSections returns empty; cache via module-level Map.
9b. Run test — verify PASS: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
10. Write failing test for: credit guard per item (R4) — computes credit_exceeded flag
    Test file: `apps/web/src/hooks/useOutletReorder.test.ts`
    Level: unit
    Test intent: Given available_credit = 50000 / When product D price 30000 × last_qty 2 = 60000 / Then reorderItem.credit_exceeded = true
    Exercise through: hook with credit data from `fetchOutletDashboard` result (available_credit)
    Test doubles: mock `fetchOutletDashboard` returning credit with available_credit; provide `products` array
    Expected RED: credit_exceeded not computed
11. Run test — verify FAIL: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
12. Implement R4: credit_exceeded = (current_price * last_qty) > available_credit (when available_credit not null).
12b. Run test — verify PASS: `npm run test -- apps/web/src/hooks/useOutletReorder.test.ts`
13. Refactor: memoize computations with `useMemo`; ensure cleanup on token change; ensure hook does NOT call loadOrderFormProducts or /products fetch internally.
14. Commit: `feat(web): add useOutletReorder hook for history aggregation and price enrichment`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — Rules R1, R4, R5, R6; Design Decision (Option A)
- Preflight: `apps/web/src/app/dashboard/api.ts` (fetchOutletDashboard, loadOutletCachedSections, outletOrdersCache), `apps/web/src/app/dashboard/outlet-helpers.ts` (topFavorites, periodWindow), `apps/web/src/components/OrderForm.tsx` (Product interface, loadOrderFormProducts)

## WHY THIS APPROACH
Complexity: standard
Justification: Multi-source data composition (cache + products), several derived computations (favorites, recent unique, enrichment, credit check), multiple test scenarios. Hook pattern fits React architecture; reusable by OrderForm.

## SANDWICH CONTEXT
[CRITICAL: Hook must be pure client-side — no new API calls beyond existing `fetchOutletDashboard`, `loadOrderFormProducts`, and `/products`. Enrichment uses `products` param passed from OrderForm (already loaded). Must reuse `topFavorites` from outlet-helpers.ts.]
You are implementing T3 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine
Files in scope: `apps/web/src/hooks/useOutletReorder.ts` (new), `apps/web/src/hooks/useOutletReorder.test.ts` (new), `apps/web/src/app/dashboard/outlet-helpers.ts` (reuse topFavorites), `apps/web/src/app/dashboard/api.ts` (reuse fetchOutletDashboard, loadOutletCachedSections)
Available after: T2 (cache invalidation ensures fresh data)
Architecture rule: Client components + fetch via api.ts hooks; no new deps; reuse topFavorites
[RESTATE: Hook must be pure client-side — no new API calls beyond existing fetchOutletDashboard, loadOrderFormProducts, and /products. Enrichment uses products param passed from OrderForm.]

## DELIVERABLE
Given favorites + 3 recent orders with inactive item, When hook computes, Then reorderItems unique, inactive excluded, enriched with current price/stock
Given product price changed since last order, When reorderItems rendered, Then current_price from /products, last_qty from history
Given empty cache, When hook mounts, Then fetchOutletDashboard called exactly once
Given available_credit, When computing credit_exceeded, Then flag = (price × last_qty > available_credit)

## QUALITY BAR
Must-have:
  - Reuses `topFavorites` and `loadOutletCachedSections` — no duplicate logic
  - All enrichment from `products` param (current catalog), not historical order data
  - Credit check uses `available_credit` from dashboard credit data
  - Loading state handled

Must-not-have:
  - New API endpoints
  - Direct mutation of outletOrdersCache
  - Hardcoded product data

Open question risks:
  - Order of list: spec assumes favorites first then recent — if UX wants different: NEEDS_CONTEXT
  - Product rename handling: spec assumes `/products` name is source of truth

Rollback note:
  - If hook causes performance issues, remove and inline in OrderForm

Red flags:
  - Calling `/products` inside hook instead of using passed `products` param → STOP
  - Not reusing `topFavorites` → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, hook tests green, no out-of-scope files
Uncertain when: Cache loading timing causes flicker
Escalate when: Hook re-fetches excessively or memory leak
