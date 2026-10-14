# EXECUTION PLAN — Outlet Fast Reorder from Purchase History

**Date:** 2026-10-02
**Spec:** docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
**Status:** draft
**Total tasks:** 5

---

## Execution Overview

### Recommended Order
```
T1 → T2 → T3 → T4 → T5
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| — | — | (sequential chain — each task depends on prior) |

### Constraints Reminder
**Architecture:** Option A (Client-Side Reorder Engine) — no new API endpoints. Only touch: `apps/web/src/components/OrderForm.tsx`, `apps/web/src/app/dashboard/api.ts`, new `apps/web/src/hooks/useOutletReorder.ts`, new `apps/web/src/lib/offline/idempotency.ts`, and `apps/web/src/lib/offline/order-queue.ts`. Must NOT touch: Auth/middleware, invoice/billing, delivery, payment, admin panel, product master, or any backend beyond existing `OrderController` and `/products` + `/credit-limit`. Follow existing patterns: client components + fetch via `api.ts` hooks, idempotency key on POST `/orders`, offline queue via `createOrderQueue`/`createDefaultAdapter`, error/loading/retry patterns in `OrderForm`, Tailwind + existing UI components (`Card`, `Button`, `Table`, `StatusBadge`, `ViewModeToggle`, `EmptyState`).
**Out-of-scope:** Product recommendations for never-purchased items (Direction B), ML personalization / cross-outlet co-purchase, DB schema changes, push notifications / realtime updates, auto-refresh via WebSocket/SSE.
**Assumptions at risk:** Cache reorder can be stale after new order until refresh/navigates dashboard (mitigation: `invalidateOutletOrdersCache` called on submit success). Client credit check = soft guard; server `OrderCreationService` is authoritative. Offline reorder is cache-only with "validasi final saat online" notice. Idempotency key inconsistency (`OrderForm` vs `order-queue`) must be fixed separately (blocker hotfix before reorder). Runtime flag `NEXT_PUBLIC_ENABLE_REORDER` requires build to toggle — rollback via revert commit.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

### File Structure Map

```
Rule: R1 — Reorder List Composition (favorit top-5 + 3 order terakhir, unique, exclude inactive)
  Create: apps/web/src/hooks/useOutletReorder.ts              (created by: T3)
  Test:   apps/web/src/hooks/useOutletReorder.test.ts         (created by: T3)
  Modify: apps/web/src/app/dashboard/api.ts                   (existing: loadOutletCachedSections, invalidateOutletOrdersCache)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)

Rule: R2 — CTA "+ Tambah" Additive per Item
  Create: apps/web/src/hooks/useOutletReorder.ts              (created by: T3)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Rule: R3 — CTA "↻ Ulangi Pesanan Terakhir" Global Additive + Skip Invalid + Notification
  Create: apps/web/src/hooks/useOutletReorder.ts              (created by: T3)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Rule: R4 — Credit Guard Per Item (label "Melebihi batas kredit", button disabled)
  Create: apps/web/src/hooks/useOutletReorder.ts              (created by: T3)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Rule: R5 — Current Price from /products (not historical price)
  Create: apps/web/src/hooks/useOutletReorder.ts              (created by: T3)
  Test:   apps/web/src/hooks/useOutletReorder.test.ts         (created by: T3)

Rule: R6 — Cache Miss Triggers Fetch Once
  Modify: apps/web/src/app/dashboard/api.ts                   (existing: fetchOutletDashboard)
  Test:   apps/web/src/app/dashboard/api.test.ts              (existing)

Rule: R7 — New Outlet Empty State (no reorder section, catalog + hint)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Rule: R8/R9 — Layered Validation (Client prefilter + Server definitive)
  Create: apps/web/src/hooks/useOutletReorder.ts              (created by: T3)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Edge: Concurrent Tabs Stale Cache
  Modify: apps/web/src/app/dashboard/api.ts                   (cache invalidation on submit)
  Test:   apps/web/e2e/outlet-fast-reorder.spec.ts            (created by: T5)

Edge: Offline Reorder (cache-only, notif "berdasarkan data terakhir")
  Create: apps/web/src/lib/offline/idempotency.ts             (created by: T1)
  Modify: apps/web/src/lib/offline/order-queue.ts             (existing)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Edge: Product State Change Between Prefill & Submit (race condition)
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Edge: Accessibility Keyboard/ARIA
  Modify: apps/web/src/components/OrderForm.tsx               (created by: T4)
  Test:   apps/web/src/components/OrderForm.test.tsx          (created by: T4)

Prereq: Idempotency Key Unification (blocker)
  Create: apps/web/src/lib/offline/idempotency.ts             (created by: T1)
  Modify: apps/web/src/components/OrderForm.tsx               (existing: submitOrder)
  Modify: apps/web/src/lib/offline/order-queue.ts             (existing: flushQueue)
  Test:   apps/web/src/lib/offline/idempotency.test.ts        (created by: T1)
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

### Task 1: Unify Idempotency Key Generation Across OrderForm and Offline Queue [prereq]

## OBJECTIVE
Buat shared utility `generateIdempotencyKey(items)` yang menghasilkan deterministik SHA-256 hash dari sorted canonical payload (items: product_id + quantity). Ganti random UUID di `OrderForm.submitOrder` (online) dan `order-queue.flushQueue` (offline replay) agar offline queue replay menggunakan key yang sama dengan online submission, mencegah duplicate order saat online/offline transition.

Steps:
1. Write failing test for: deterministic hash stability — identical sorted items produce same key; different payloads produce different keys
   Test file: `apps/web/src/lib/offline/idempotency.test.ts`
   Level: unit
   Test intent: Given items [{product_id:1,quantity:2},{product_id:3,quantity:1}] / When generateIdempotencyKey called twice / Then both calls return identical 64-char hex string; Given items with different quantities / When called / Then key differs
   Exercise through: direct function import `generateIdempotencyKey`
   Test doubles: none (pure function)
   Expected RED: function `generateIdempotencyKey` does not exist yet
2. Run test — verify FAIL: `npm run test -- apps/web/src/lib/offline/idempotency.test.ts`
3. Implement `generateIdempotencyKey` using Web Crypto `crypto.subtle.digest('SHA-256', ...)` with fallback for non-secure contexts (deterministic FNV-like or JSON-stringify + hash). Export from `apps/web/src/lib/offline/idempotency.ts`.
4. Write failing test for: OrderForm online submit uses unified key
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: unit
   Test intent: Given cart with items / When submitOrder online / Then `submitOutletOrder` receives idempotencyKey matching `generateIdempotencyKey(items)`
   Exercise through: `OrderForm` component rendered with mock `submitOutletOrder`
   Test doubles: mock `submitOutletOrder` to capture key; mock `loadOrderFormProducts`; use `act` + RTL
   Expected RED: `OrderForm` uses inline randomUUID fallback instead of shared util
6. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
7. Update `OrderForm.tsx` `submitOrder` to import and use `generateIdempotencyKey` from `@/lib/offline/idempotency`.
8. Write failing test for: order-queue flushQueue uses unified key
   Test file: `apps/web/src/lib/offline/order-queue.test.ts` (new or extend existing)
   Level: unit
   Test intent: Given queued payload with items / When flushQueue runs / Then send receives Idempotency-Key header matching `generateIdempotencyKey(payload.items)`
   Exercise through: `createOrderQueue` with mocked adapter + `flushQueue`
   Test doubles: mocked `StorageAdapter`; mock `send` function capturing headers
   Expected RED: `order-queue.ts` uses inline FNV-1a hash instead of shared util
9. Run test — verify FAIL: `npm run test -- apps/web/src/lib/offline/order-queue.test.ts`
10. Update `order-queue.ts` `flushQueue` to import and use `generateIdempotencyKey` from shared util.
11. Refactor: remove duplicate hash logic from both files; verify both test suites pass.
12. Commit: `feat(web): unify idempotency key generation across OrderForm and offline queue`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — Rules: Blocker prerequisite (idempotency key unification), Edge: Offline Reorder, Design Decision (Option A)
- Preflight: `apps/web/src/components/OrderForm.tsx` (lines 65–80, 180–210), `apps/web/src/lib/offline/order-queue.ts` (lines 25–45)

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single pure function extraction; two call sites to update; well-tested offline queue already exists with mocks; no new dependencies (Web Crypto is native).

## SANDWICH CONTEXT
[CRITICAL: Must use Web Crypto `crypto.subtle.digest('SHA-256')` or deterministic stable hash — no new deps. Key must be stable across online/offline for same canonical payload.]
You are implementing T1 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine (no new API)
Files in scope: `apps/web/src/lib/offline/idempotency.ts` (new), `apps/web/src/components/OrderForm.tsx`, `apps/web/src/lib/offline/order-queue.ts`, test files
Available after: none (prereq)
Architecture rule: Reuse native Web Crypto; idempotency key scoped to outlet + canonical payload items; offline queue replay must match online submission key.
[RESTATE: Must use Web Crypto SHA-256 or deterministic stable hash — no new deps. Key must be stable across online/offline for same canonical payload.]

## DELIVERABLE
Given identical sorted items, When `generateIdempotencyKey` called multiple times, Then returns identical key
Given different item payloads, When called, Then returns different keys
Given OrderForm online submit, When `submitOrder` executes, Then `submitOutletOrder` receives unified key
Given offline queue flush, When `flushQueue` replays, Then `send` receives unified key matching online

## QUALITY BAR
Must-have:
  - Single source of truth for idempotency key generation
  - Both online and offline paths produce identical key for same canonical items
  - Tests cover hash stability and integration at both call sites

Must-not-have:
  - Duplicate hash implementations in OrderForm and order-queue
  - Any new npm dependency

Open question risks:
  - Spec assumes unify in shared util — if wrong: NEEDS_CONTEXT
  - SHA-256 hex length (64 chars) vs backend expects 64-char — align with backend `OrderCreationService::payloadFingerprint`

Rollback note:
  - Revert commit if duplicate order issues arise

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - New npm dependency added → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified, both test suites green
Uncertain when: Hash format mismatches backend expectations (64-char hex vs other)
Escalate when: Backend returns 422 idempotency conflict for identical payloads

---

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

---

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

---

### Task 4: Integrate "Pesan Lagi" Section & Guards into OrderForm.tsx [depends: T3] [test-risk]

## OBJECTIVE
Integrasikan seksi "Pesan Lagi" ke `OrderForm.tsx` di atas katalog: render daftar `reorderItems` dari `useOutletReorder` (real hook, NOT mocked), CTA "+ Tambah" (additif ke keranjang: qty dijumlahkan), CTA global "↻ Ulangi Pesanan Terakhir" (batch additif, skip invalid + notifikasi), credit guard per item (label + disabled button), empty state untuk outlet baru (sembunyikan seccion, tampilkan hint), aksesibilitas keyboard/ARIA, feature flag `NEXT_PUBLIC_ENABLE_REORDER`, rollback via env. Semua validasi client-side (prefilter) — server tetap definitive.

Steps:
1. Write failing test for: reorder section renders with items (R1 happy path) — REAL HOOK
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration (component)
   Test intent: Given outlet with history / When OrderForm mounts / Then "Pesan Lagi" section visible above catalog with reorder items showing name, current price, stock, last_qty, last_order_date
   Exercise through: `render(<OrderForm token="test" />)` with REAL `useOutletReorder` hook; mock `fetchOutletDashboard` / `loadOutletCachedSections` via `api.ts` boundary; provide `products` param via mocked `loadOrderFormProducts`
   Test doubles: mock `fetchOutletDashboard` / `loadOutletCachedSections`; mock `loadOrderFormProducts` returning catalog; do NOT mock `useOutletReorder` itself; mock `submitOutletOrder` for submit path
   Expected RED: OrderForm does not render "Pesan Lagi" section (hook not imported/used)
2. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
3. Implement section rendering in `OrderForm.tsx`: import `useOutletReorder`, render `Card` + `Table`/grid view with reorder items, place before catalog section.
3b. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
4. Write failing test for: "+ Tambah" additive add to cart (R2)
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given cart has product A qty 2 / When click "+ Tambah" on A (last_qty=3) / Then cart A qty becomes 5, total updates
   Exercise through: RTL click on button with aria-label "Tambah Produk A, qty 3"; REAL hook with seeded `outletOrdersCache` + `products` param
   Test doubles: mock `fetchOutletDashboard`/`loadOutletCachedSections`; mock `loadOrderFormProducts`; spy `setCart` via re-render
   Expected RED: button not present or does not update cart
5. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
5b. Implement `addToCart` action: use `changeQuantity` logic but additive (current + last_qty).
5c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
6. Write failing test for: "Ulangi Pesanan Terakhir" batch add with mixed valid/invalid skip + notification (R3)
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given last order items A(valid), B(valid), C(stok 0) / When click "↻ Ulangi Pesanan Terakhir" / Then cart +A, +B; C skipped; notification "Produk C tidak ditambahkan: Stok habis" visible
   Exercise through: RTL click on global button; check cart + notification banner (role="alert")
   Test doubles: REAL hook; mock `fetchOutletDashboard` returning lastOrderItems with mixed validity; provide `products` param
   Expected RED: global button not present or notification missing
7. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
7b. Implement `reorderLastOrder`: iterate lastOrderItems, filter valid, add to cart additive, collect skipped with reasons, show notification banner.
7c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
8. Write failing test for: "Ulangi Pesanan Terakhir" batch all-invalid (R3 edge) — cart unchanged, all skipped notification
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given last order items A(stok 0), B(inactive), C(credit exceeded) / When click "↻ Ulangi Pesanan Terakhir" / Then cart unchanged; notification lists all skipped with reasons
   Exercise through: REAL hook; mock `fetchOutletDashboard` returning all-invalid lastOrderItems
   Test doubles: REAL hook; mock `fetchOutletDashboard`; provide `products` param
   Expected RED: batch all-invalid not handled (button missing or no notification)
9. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
9b. Implement all-invalid case in `reorderLastOrder`: if validItems.length===0, show collective notification, no cart change.
9c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
10. Write failing test for: credit guard per item (R4) — label + disabled when exceeded
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given available_credit = 50000 / When reorder item D price 30000 × last_qty 2 = 60000 > credit / Then button disabled, label "Melebihi batas kredit" visible
    Exercise through: REAL hook returns `credit_exceeded: true` for item; render checks prop
    Test doubles: REAL hook; mock `fetchOutletDashboard` with low available_credit; provide `products` param
    Expected RED: no credit guard UI
11. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
11b. Implement credit guard UI: conditional disabled + badge/label.
11c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
12. Write failing test for: credit guard per item (R4 positive) — button enabled, no label when within limit
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given available_credit = 100000 / When reorder item D price 30000 × last_qty 2 = 60000 < credit / Then button enabled, no "Melebihi batas kredit" label; other products unaffected
    Exercise through: REAL hook; mock `fetchOutletDashboard` with high available_credit
    Test doubles: REAL hook; mock `fetchOutletDashboard`; provide `products` param
    Expected RED: button incorrectly disabled or label shown when within limit
13. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
13b. Implement positive guard: button enabled when credit_exceeded=false; ensure no label rendered.
13c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
14. Write failing test for: new outlet empty state (R7) — no reorder section, hint visible
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given outlet with 0 orders / When OrderForm mounts / Then no "Pesan Lagi" section; catalog visible; hint "Belum ada riwayat pesanan — pilih dari katalog di bawah untuk pesanan pertama Anda." visible
    Exercise through: REAL hook; mock `fetchOutletDashboard` returning empty orders; provide `products` param
    Test doubles: REAL hook; mock `fetchOutletDashboard` empty; mock `loadOrderFormProducts`
    Expected RED: reorder section still renders or hint missing
15. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
15b. Implement conditional rendering: `if (reorderItems.length === 0 && lastOrderItems.length === 0)` show hint instead of section.
15c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
16. Write failing test for: feature flag off — section hidden (Rollback Plan)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given NEXT_PUBLIC_ENABLE_REORDER=false / When OrderForm mounts / Then no "Pesan Lagi" section rendered; only catalog visible
    Exercise through: `process.env.NEXT_PUBLIC_ENABLE_REORDER = 'false'`; REAL hook; mock `fetchOutletDashboard`
    Test doubles: REAL hook; mock `fetchOutletDashboard`; mock `loadOrderFormProducts`
    Expected RED: section still renders despite flag false
17. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
17b. Implement flag guard: `if (process.env.NEXT_PUBLIC_ENABLE_REORDER === 'false') return null` for reorder section.
17c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
18. Write failing test for: client prefilter rendering — inactive/stock-0 items shown disabled with reason (R8)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given hook returns item with stock_quantity=0 or is_active=false / When rendered / Then "+ Tambah" button disabled + reason label ("Stok habis" / "Produk tidak aktif") visible
    Exercise through: REAL hook; mock `fetchOutletDashboard` returning item with stock=0/inactive; provide `products` param with same state
    Test doubles: REAL hook; mock `fetchOutletDashboard`; provide `products` param
    Expected RED: inactive/stock-0 items render with enabled button or no reason label
19. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
19b. Implement client prefilter rendering: disabled button + reason label based on `stock_quantity===0` or `is_active===false`.
19c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
20. Write failing test for: accessibility keyboard/ARIA (Edge)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given keyboard navigation / When Tab through reorder section / Then all "+ Tambah" and "↻ Ulangi Pesanan Terakhir" reachable; Enter/Space triggers action; aria-label describes action + guard status (e.g., "Tambah Produk A, qty 3 — Stok: 50", "Melebihi batas kredit — tidak dapat ditambahkan")
    Exercise through: RTL `fireEvent.keyDown` + `focus` checks; REAL hook
    Test doubles: REAL hook; mock `fetchOutletDashboard`; provide `products` param
    Expected RED: buttons lack aria-labels or not keyboard reachable
21. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
21b. Implement ARIA: `aria-label` on buttons with dynamic text including guard status; ensure `tabIndex` and native `<button>` semantics.
21c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
22. Write failing test for: offline reorder with mixed states (Edge) — cache-only prefill + notice + queue enqueue with unified idempotency key (T1 seam)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given offline (navigator.onLine=false) / When click "Ulangi Pesanan Terakhir" / Then cart prefilled from cache only; notification "berdasarkan data terakhir — validasi final saat online"; no network fetch for /products; items enqueued via `createOrderQueue` with Idempotency-Key == generateIdempotencyKey(payload.items) pending flush
    Exercise through: mock `useOnlineStatus` false; REAL hook; mock `fetchOutletDashboard` with cached items (some inactive); provide `products` param; spy `createOrderQueue` enqueue; spy `fetch`
    Test doubles: mock `useOnlineStatus`; REAL hook; mock `fetchOutletDashboard`; provide `products` param; mock `createOrderQueue`/`createDefaultAdapter`; spy `fetch`
    Expected RED: offline behavior not implemented (no notice, network called, or queue missing key)
23. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
23b. Implement offline path: use `useOnlineStatus`, show cache-only notice, skip `/products` enrichment, enqueue via `createOrderQueue` with unified idempotency key from T1.
23c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
24. Write failing test for: product state change race (Edge) — backend error, valid items retained
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given prefill valid items / When submit and backend returns per-item errors (inactive, stock, credit) / Then error displayed per item; valid items remain in cart; user can edit and re-submit
    Exercise through: mock `submitOutletOrder` to reject with structured error; check cart state after error
    Test doubles: mock `submitOutletOrder` throwing error with item details; REAL hook; mock `fetchOutletDashboard`; provide `products` param
    Expected RED: error handling does not retain valid items
25. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
25b. Implement error handling: parse backend error, show inline, keep valid cart items.
25c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
26. EXTRA INTEGRATION CYCLE: cache invalidation → refetch → render (T2+T3+T4 seam)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given OrderForm with real hook + seeded outletOrdersCache / When submitOrder succeeds (T2 invalidates) / Then invalidateOutletOrdersCache called; hook refetches via fetchOutletDashboard; "Pesan Lagi" section updates with new favorite without manual reload
    Exercise through: REAL hook + OrderForm; mock `fetchOutletDashboard` (two responses: before + after invalidate); spy `invalidateOutletOrdersCache`; mock `loadOrderFormProducts`; mock `submitOutletOrder` success
    Test doubles: REAL hook; mock `fetchOutletDashboard` twice; spy `invalidateOutletOrdersCache`; mock `loadOrderFormProducts`; mock `submitOutletOrder`
    Expected RED: submit does not trigger refetch/update
27. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
27b. Ensure T2 invalidation + T3 cache-miss fetch + T4 re-render all wired.
27c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
28. EXTRA INTEGRATION CYCLE: hook + OrderForm composition (R1,R4,R5) — real end-to-end
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given seeded outletOrdersCache + products / When OrderForm renders with real hook / Then reorderItems show dedup favorites+recent, current_price from products param, credit_exceeded computed correctly, disabled buttons for exceeded
    Exercise through: REAL hook + OrderForm; seed `outletOrdersCache` Map; provide `products` param via mocked `loadOrderFormProducts`
    Test doubles: REAL hook; seed `outletOrdersCache`; mock `loadOrderFormProducts`; no fetchOutletDashboard call needed
    Expected RED: composition not wired (mocked hook bypassed in prior tests)
29. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
29b. Verify end-to-end composition works with real hook.
29c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
30. EXTRA INTEGRATION CYCLE: unified idempotency across online submit and offline queue (T1+T2+T4 seam)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given offline reorder submitted (enqueued) / When online and flushQueue runs / Then Idempotency-Key header in flushQueue == generateIdempotencyKey(payload.items) from T1; cache invalidated (T2)
    Exercise through: REAL hook; mock `useOnlineStatus` toggle; mock `createOrderQueue`/`flushQueue`; spy `invalidateOutletOrdersCache`; spy `generateIdempotencyKey`
    Test doubles: REAL hook; mock `useOnlineStatus`; mock `createOrderQueue`/`flushQueue` capturing header; spy `invalidateOutletOrdersCache`; spy `generateIdempotencyKey`
    Expected RED: offline enqueue lacks unified key or flush doesn't invalidate cache
31. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
31b. Verify T1 unified key + T2 invalidation + T4 offline queue all wired.
31c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
32. EXTRA INTEGRATION CYCLE: offline cache-only + IndexedDB queue + online transition (T4+T2 persistence/networking)
    Test file: `apps/web/src/components/OrderForm.test.tsx`
    Level: integration
    Test intent: Given offline / When "Ulangi" prefill + submit enqueues / Then online event fires, flushQueue replays with unified key, invalidateOutletOrdersCache called
    Exercise through: REAL hook + REAL `createOrderQueue` bound to `createDefaultAdapter` backed by the real IndexedDB fake (`fake-indexeddb`); mock `useOnlineStatus` toggle; dispatch `online` event so the REAL `flushQueue` replays;.spy only observes `flushQueue` without replacing its implementation; spy `invalidateOutletOrdersCache`
    Test doubles: REAL hook; REAL `createOrderQueue` + REAL `createDefaultAdapter` over `fake-indexeddb`; mock `useOnlineStatus` only; spy `flushQueue` (call-through, no mock impl); spy `invalidateOutletOrdersCache`
    Expected RED: offline enqueue not persisted or online flush misses invalidation
33. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
33b. Verify full offline→online transition with real IndexedDB fake.
33c. Run test — verify PASS: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
34. Refactor: extract subcomponents if `OrderForm.tsx` exceeds ~300 lines (consider `ReorderSection.tsx`).
35. Commit: `feat(web): integrate Pesanan Lagi fast reorder section into OrderForm with guards`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — Rules R1–R4, R7, R8/R9, Edge cases (Concurrent Tabs, Offline, Race Condition, Accessibility)
- Preflight: `apps/web/src/components/OrderForm.tsx` (full), existing UI components: `Card`, `Button`, `Table`, `StatusBadge`, `ViewModeToggle`, `EmptyState`, `useViewMode`

## WHY THIS APPROACH
Complexity: standard
Justification: [test-risk] — this task spans multiple GWT scenarios across UI, state, offline, and accessibility boundaries. Test level is integration (component) because behavior requires React rendering + hook + API mocks. Multiple RED cycles inside one task (Rule 2 vs Rule 4 tiebreaker: second piece not independently verifiable without first — all UI in same component).

## SANDWICH CONTEXT
[CRITICAL: No new API. Use existing OrderForm patterns: `changeQuantity` for cart additive, `submitOutletOrder` for submit, `order-queue` for offline. Must call `invalidateOutletOrdersCache` on success (T2) and preserve the T2 call in subsequent OrderForm edits. Accessibility: native buttons, aria-labels dynamic, keyboard focus order.]
You are implementing T4 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine
Files in scope: `apps/web/src/components/OrderForm.tsx`, `apps/web/src/hooks/useOutletReorder.ts` (from T3), `apps/web/src/app/dashboard/api.ts` (invalidateOutletOrdersCache from T2), `apps/web/src/lib/offline/order-queue.ts` (offline path), test file
Available after: T3 (hook ready), T2 (cache invalidation)
Architecture rule: Client components + fetch via api.ts hooks; idempotency key on POST; offline queue support; existing UI components; Tailwind
[RESTATE: No new API. Use existing OrderForm patterns. Must call invalidateOutletOrdersCache on success. Accessibility: native buttons, dynamic aria-labels, keyboard focus order.]

## DELIVERABLE
Given history, When OrderForm renders, Then "Pesan Lagi" section visible with enriched items
Given cart has item, When "+ Tambah" clicked, Then cart qty additive, total updates
Given last order with mixed valid/invalid, When "Ulangi Pesanan Terakhir" clicked, Then valid added, invalid skipped + notification
Given credit exceeded, When viewing item, Then button disabled + label "Melebihi batas kredit"
Given 0 orders, When OrderForm renders, Then no reorder section; hint visible
Given keyboard nav, When Tab through reorder, Then all buttons reachable, Enter/Space works, aria-labels descriptive
Given offline, When reorder last order, Then cache-only prefill + "berdasarkan data terakhir" notice
Given backend race error, When submit, Then per-item errors shown, valid items retained

## QUALITY BAR
Must-have:
  - All R1–R4, R7, Edge scenarios implemented
  - Additive cart logic reuses `changeQuantity` pattern
  - Notifications use existing error/alert pattern (role="alert")
  - Accessibility: native `<button>`, `aria-label` with guard status, focus order
  - Offline path uses `useOnlineStatus` + queue (no network call)

Must-not-have:
  - New backend endpoints
  - Bypass server validation (client guard only)
  - Duplicate cart logic

Open question risks:
  - Order of list: favorites first then recent — if UX changes: NEEDS_CONTEXT
  - Offline: spec assumes cache-only with notice — if total disable: NEEDS_CONTEXT

Rollback note:
  - Feature flag via env: `NEXT_PUBLIC_ENABLE_REORDER=false` hides section (no deploy)
  - Or revert commit adding reorder section in OrderForm

Red flags:
  - Adding new API call in OrderForm → STOP
  - Not calling `invalidateOutletOrdersCache` on success → DONE_WITH_CONCERNS
  - Breaking existing OrderForm tests → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, all OrderForm tests green (existing + new), no out-of-scope files
Uncertain when: Offline cache staleness causes misleading UI
Escalate when: Accessibility audit fails or cart state corruption

---

### Task 5: End-to-End & Edge-Case Verification [depends: T4]

## OBJECTIVE
Verifikasi end-to-end dan edge-case: concurrent tabs stale cache (acceptable v1 behavior, refresh updates), offline reorder dengan mixed product states (cache-only, notif, queue), product state change race condition antara prefill dan submit (backend error per-item, valid items retained), accessibility keyboard/ARIA compliance. Menulis test E2E/Playwright atau integration yang mencakup skenario lintas tab, offline/online transition, dan race condition.

Steps:
1. Write failing test for: concurrent tabs stale cache (Edge)
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts` (Playwright)
   Level: E2E
   Test intent: Given two tabs open /orders / When Tab A submits new order successfully / Then Tab B reorder section shows stale data (no new favorite) until refresh / When Tab B refreshes / Then reorder section updated with new favorite
   Exercise through: Playwright multi-page context; real backend or seeded test DB
   Test doubles: Real network; test DB seeded via `scripts/e2e-seed.sh`
   Expected RED: assertion that Tab B remains stale until refresh, then updates after refresh, fails because reorder UI/cache behavior is not implemented yet.
2. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
3. Implement test scenario; verify stale cache behavior documented (acceptable v1 — no auto-sync).
3b. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (concurrent-tabs scenario)
4. Write failing test for: offline reorder with mixed product states (Edge)
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts`
   Level: E2E
   Test intent: Given offline / When click "Ulangi Pesanan Terakhir" with cached items (A active, B inactive since order, C stock 0, D exceed credit) / Then cart prefilled A only; notifications for B/C/D "berdasarkan data terakhir — validasi final saat online"; no network; items queued on submit
   Exercise through: Playwright offline mode (`page.setOffline(true)`); seeded cache via localStorage/IndexedDB
   Test doubles: Real IndexedDB; mock network offline
   Expected RED: assertion that only eligible cached items are prefilled, cache-only notice appears, no network request occurs, and queued payload carries unified idempotency key fails before offline reorder behavior is implemented.
5. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
5b. Implement offline test flow including queue persistence and replay behavior.
5c. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (offline scenario)
6. Write failing test for: product state change race condition (Edge)
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts`
   Level: E2E
   Test intent: Given online, reorder last order fills cart / Before submit, admin deactivates product B / When submit / Then backend returns per-item error "Produk B tidak aktif" / And valid items (A, C) remain in cart / And user can edit and re-submit
   Exercise through: Playwright + test DB manipulation (direct SQL or API) between prefill and submit
   Test doubles: Real backend; test DB
   Expected RED: assertion that server rejects newly inactive item while retaining valid cart items fails before race handling is implemented.
7. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
7b. Implement race condition test.
7c. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (race scenario)
8. Write failing test for: accessibility keyboard/ARIA (Edge) — automated aXe or manual checklist
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts` or unit test in `OrderForm.test.tsx`
   Level: E2E / integration
   Test intent: Given keyboard-only navigation / When Tab through reorder section / Then all buttons reachable, Enter/Space triggers, aria-labels include guard status
   Exercise through: Playwright keyboard API or RTL focus/keyDown
   Test doubles: Same mocks as T4
   Expected RED: keyboard interaction or descriptive aria-label assertion fails before reorder controls are accessible.
9. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
9b. Implement accessibility verification.
9c. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (accessibility scenario)
10. Refactor: consolidate test fixtures; ensure deterministic seeding.
11. Commit: `test(web): add edge-case and end-to-end verification for outlet fast reorder`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — All Edge scenarios (Concurrent Tabs, Offline Reorder, Race Condition, Accessibility)
- Preflight: `apps/web/e2e/siklus-pemesanan.spec.ts` (existing E2E pattern), `scripts/e2e-seed.sh`, `apps/web/src/components/OrderForm.offline.test.tsx` (offline test pattern)

## WHY THIS APPROACH
Complexity: standard
Justification: E2E tests require test DB, multi-tab Playwright, offline simulation, and backend state manipulation — separate from unit/integration tests. Covers cross-cutting edge cases that unit tests cannot.

## SANDWICH CONTEXT
[CRITICAL: E2E tests use real backend + test DB (seeded). Do NOT mock network in E2E. Stale cache behavior is acceptable v1 — test documents it, does not fix it. Offline test requires Playwright offline mode + IndexedDB and verifies queued idempotency key plus replay. Race condition test requires backend state mutation between prefill and submit.]
You are implementing T5 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine
Files in scope: `apps/web/e2e/outlet-fast-reorder.spec.ts` (new), `scripts/e2e-seed.sh` (may extend)
Available after: T4 (OrderForm fully integrated)
Architecture rule: E2E uses Playwright; test DB via `e2e-seed.sh`; no mocks for network
[RESTATE: E2E tests use real backend + test DB. Do NOT mock network in E2E. Stale cache = acceptable v1 (documented). Offline = Playwright offline + IndexedDB. Race = backend mutation between prefill/submit.]

## DELIVERABLE
Given two tabs, When order in Tab A, Then Tab B stale until refresh (documented acceptable)
Given offline, When reorder last order, Then cache-only prefill + "berdasarkan data terakhir" notice + queue on submit
Given race condition, When submit after admin change, Then per-item backend error, valid items retained
Given keyboard-only, When navigate reorder, Then all buttons reachable, Enter/Space works, aria-labels descriptive

## QUALITY BAR
Must-have:
  - All 4 Edge scenarios from spec have automated verification
  - E2E tests use real backend (seeded)
  - Tests are deterministic (fixed seeds, cleanup)

Must-not-have:
  - Flaky tests (timing-dependent)
  - Mocked network in E2E layer

Open question risks:
  - E2E environment availability in CI — if not available: NEEDS_CONTEXT (run locally only)

Rollback note:
  - If E2E tests flaky, mark `@skip` and run locally only

Red flags:
  - Mocking fetch in E2E → STOP
  - Test depends on timing/sleep → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: All Edge scenario tests pass (or documented skipped with reason), deterministic
Uncertain when: CI environment lacks Playwright / test DB
Escalate when: Race condition test cannot reliably trigger backend state change

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Unify Idempotency Key Generation | prereq | lightweight | Deterministic hash + both call sites use it |
| T2 | Cache Invalidation on Submit | T1 | lightweight | invalidateOutletOrdersCache called on online + offline success |
| T3 | useOutletReorder Hook | T2 | standard | R1, R4, R5, R6 GWT covered in hook unit tests |
| T4 | OrderForm Integration & UI | T3 | standard | R2, R3, R4, R7, Edge (offline, race, a11y) in component tests |
| T5 | E2E & Edge-Case Verification | T4 | standard | 4 Edge scenarios automated in Playwright |