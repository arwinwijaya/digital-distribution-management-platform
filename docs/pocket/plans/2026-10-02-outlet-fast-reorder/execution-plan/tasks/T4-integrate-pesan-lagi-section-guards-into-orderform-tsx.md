# Task T4 — Integrate "Pesan Lagi" Section & Guards into OrderForm.tsx

**Phase:** 1
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
