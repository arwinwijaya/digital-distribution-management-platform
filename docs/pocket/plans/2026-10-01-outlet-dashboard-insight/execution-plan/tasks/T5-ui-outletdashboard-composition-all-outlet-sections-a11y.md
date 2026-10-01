# Task T5 — UI — OutletDashboard composition (all outlet sections + a11y)

**Phase:** 1
**Depends:** T3, T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: UI — OutletDashboard composition (all outlet sections + a11y) [depends: T3, T4]

## OBJECTIVE
Bangun komponen `OutletDashboard` yang dipakai oleh branch outlet di `apps/web/src/app/dashboard/page.tsx` (ganti `router.replace('/orders')`): ringkasan per status (StatCard), daftar 10 terbaru dengan tautan detail, shopping summary dengan selector 7/30/90 (default 30) dan label periode, kartu kredit (hide bila null), produk favorit top-5 + "Pesan lagi" → /orders, empty state vs error yang berbeda (pesan spesifik + warning Card + ikon ⚠️ untuk inline 403 `my-3`), partial render (seksi yang sukses tetap render), per-section "Coba lagi" (`aria-live="polite"`, fokus kembali, keyboard Enter/Space), truncation note di atas daftar ("Menampilkan 1.000 pesanan terbaru dari {meta.total} pesanan" + bar "Tidak ada data lebih baru"), layout collapse instan tanpa animasi saat credit hidden (`grid auto-rows`), `console.warn` per-seksi tanpa PII saat partial failure, dan `data-testid` per spec. Admin/finance branch harus tetap byte-for-byte.

Steps:
1. Write failing test for: happy render — summary + recent 10 + shopping default 30 + credit + favorites
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx` (new)
   Level: integration (React Testing Library)
   Test intent: Given outlet data (3 New 2 Delivered, 4 orders in 30d, credit set, products A10 B6) / When `<OutletDashboard data={outletData} />` rendered / Then summary shows New=3 Delivered=2, recent list 5 newest with detail links `/orders?order_id=`, shopping shows total Σ total_amount excluding Cancelled and count 4 with label "30 hari terakhir", credit shows Rp formatted, favorites shows A then B with "Pesan lagi" → /orders
   Exercise through: `<OutletDashboard />` component props (no fetch)
   Test doubles: mock outlet-helpers real, mock next/link; dummy data from T3
   Expected RED: component does not exist
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "happy render"`
3. Implement `OutletDashboard.tsx` + branch in `page.tsx`: replace outlet redirect with conditional `if (role==='outlet') return <OutletDashboard ...>`; keep admin/finance paths untouched; wire period selector state default 30, recompute shopping via helper; map statuses via helper; wire truncation note (G1/G2), inline 403 Card (G3), escape/truncate (G4), fallback Produk #id (G6), a11y (G8).
4. Write failing test for: empty vs window-empty vs error vs credit hidden + per-section retry isolation
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx`
   Level: integration
   Test intent: Given props: zero orders → "Belum ada pesanan"; given history but 7d window zero → "Tidak ada transaksi pada periode ini"; given credit_limit null → credit section not in DOM, no error, grid does not shift with animation (instant collapse); given orders fetch error + credit success → orders error Card with `outlet-section-error` + button `outlet-retry` (aria-live) while credit renders; when retry clicked → only onRetryOrders called, favorites/credit untouched
   Exercise through: component props + mock onRetry callbacks
   Test doubles: mock console.warn spy, mock retry handlers
   Expected RED: empty and error conflated or retry calls whole-page refetch
5. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "empty vs error"`
6. Write failing test for: auth 403 inline (no retry) + a11y focus + console.warn
   Test file: `apps/web/src/app/dashboard/OutletDashboard.test.tsx`
   Level: integration
   Test intent: Given 403 no outlet relation payload / When OutletDashboard receives error 403 / Then inline Card warns "Akun ini tidak terhubung ke outlet. Hubungi admin." without retry button, role alert; When retry button receives Enter/Space / Then handler fires and focus returns to button; When partial failure renders / Then console.warn called per failed section without PII
   Exercise through: same component
   Test doubles: jest spy on console.warn
   Expected RED: 403 shows retry or no warn
7. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/app/dashboard/OutletDashboard.test.tsx -t "403 and a11y"`
8. Refactor while green (extract sub-sections OrdersSummary/Shopping/Credit/Favorites inside same file if over 300 lines, still within this task), verify admin `apps/web/src/app/dashboard/page.test.tsx` still green, commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Stories 1–5 all GWT, design G1–G8, Acceptance Criteria, Implementation Notes data-testid list. `apps/web/src/app/dashboard/page.tsx` full file, `apps/web/src/components/ui` StatCard/Card/PageHeader, `apps/web/src/app/payments/page.tsx` partial-error pattern, `apps/web/src/dummy/guards.ts` withDummyRead.

## WHY THIS APPROACH
Complexity: standard
Justification: Multi-section composition with distinct empty/error/hide branches, a11y contract, and layout stability; pushes to standard per branching logic override.
[test-risk] Cross-unit GWT (API→UI) and ambiguous unit vs integration boundary for period selector recompute — auditable in Phase 6.

## SANDWICH CONTEXT
[CRITICAL: Dashboard /dashboard must branch by role client-side — outlet renders OutletDashboard, admin/finance paths remain byte-for-byte unchanged.]
You are implementing outlet UI composition for dashboard.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — same-route branch, StatCard/Card/PageHeader, reuse withDummyRead + outlet helpers.
Files in scope: apps/web/src/app/dashboard/OutletDashboard.tsx, apps/web/src/app/dashboard/page.tsx, apps/web/src/app/dashboard/OutletDashboard.test.tsx
Available after: T3 (dummy), T4 (data layer + login map)
Architecture rule: Do NOT add new menu key or RBAC; reuse dashboard:edit gate; truncation note uses meta.total; shared cache stays in api.ts.
[RESTATE: Branch is client-side in page.tsx which is already 'use client' — no parallel routes or middleware.]

## DELIVERABLE
Given outlet with data, When dashboard renders, Then summary, recent 10 with links, shopping default 30 with label, credit Rp, favorites top-5 with reorder link all visible [Story 1–5 happy]
Given zero orders, When renders, Then "Belum ada pesanan"; Given history but empty window, Then "Tidak ada transaksi pada periode ini" [Story 1 vs 3 empty]
Given 1.500 orders, When renders, Then note "Menampilkan 1.000 pesanan terbaru dari {meta.total} pesanan" above list + bar "Tidak ada data lebih baru (batas 1.000 pesanan terbaru)" and no pagination controls [Story 1 cap G1/G2]
Given Canceled or unknown status, When rendered, Then Dibatalkan vs escaped truncated + tooltip Lainnya [Story 2 G4]
Given credit_limit null, When renders, Then credit card hidden, instant grid collapse, no error [Story 4 hide G7]
Given per-section errors, When renders, Then partial render + outlet-section-error + outlet-retry per section, 403 inline without retry, retry isolated, aria-live focus-kept, keyboard operable, console.warn per-section no PII [Story 4/6 + G3/G5/G8]

## QUALITY BAR
Must-have:
  - data-testid: outlet-dashboard, outlet-orders-summary, outlet-orders-recent, outlet-shopping-summary, outlet-credit-card, outlet-favorites, outlet-section-error, outlet-retry
  - Outlet helpers imported, not duplicated (status, window, favorites)
  - Rupiah id-ID without decimals via safeNumber
  - Favorites Pesan lagi → /orders
  - Escape unknown status + truncate 20 + tooltip Lainnya, fallback Produk #id

Must-not-have:
  - Changing admin/finance dashboard output
  - New endpoint, new route, or cart→checkout logic
  - Cross-outlet benchmarking or recommendation UI

Open question risks:
  - Truncation copy "Menampilkan 1.000 ..." assumed → if wrong copy: snapshot diff, report DONE_WITH_CONCERNS

Rollback note:
  - Revert page.tsx branch to router.replace('/orders') and remove OutletDashboard import; UI inert

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, `apps/web/src/app/dashboard/page.test.tsx` admin suites still green, accessibility attributes present, console.warn spy verified
Uncertain when: viewport layout jump still perceived on hide (mobile) — visual spot check needed
Escalate when: admin path changed or dashboard:edit gate bypassed
