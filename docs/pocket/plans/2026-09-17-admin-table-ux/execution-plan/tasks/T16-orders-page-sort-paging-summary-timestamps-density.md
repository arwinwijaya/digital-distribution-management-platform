# Task T16 — Orders page — sort/paging/summary/timestamps/density

**Phase:** 4
**Depends:** T4, T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 16: Orders page — sort/paging/summary/timestamps/density [depends: T4, T8]

## OBJECTIVE
Upgrade `orders/page.tsx` (fetch inline, tanpa api.ts): sort state (default created_at DESC), cursor offset, summary total, kolom `Dibuat`, density; kirim `sort`/`order`/`cursor` ke `/admin/orders`, baca `meta.total`; Table props sortable + local sort state. Tidak ada dummy branch (orders pakai fetch langsung).

Steps:
1. Write failing test for: default sort + summary total + timestamp kolom
   Test file: `apps/web/src/app/admin/orders/page.test.tsx`
   Level: integration
   Test intent: Given fetch mock return orders + `meta.total` / When load / Then baris pertama terbaru, summary "N pesanan", kolom Dibuat terformat; approve/detail flow tetap.
   Exercise through: render `<AdminOrdersPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock
   Expected RED: summary/kolom/sort belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/orders/page.test.tsx`
3. Implement sort default + summary + kolom + meta.total parse → PASS → refactor → commit.

4. Write failing test for: sort header + paging offset
   Test file: `apps/web/src/app/admin/orders/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Status" / Then fetch `sort=status&order=desc&cursor=0`; klik KEDUA kali / Then `sort=status&order=asc&cursor=0`; pindah halaman 2 / Then `cursor=100` + sort dipertahankan.
   Exercise through: render + fireEvent
   Test doubles: fetch mock (assert URL)
   Expected RED: param sort/cursor tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/orders/page.test.tsx`
6. Implement sort header + paging → PASS → refactor → commit.

7. Write failing test for: density toggle
   Test file: `apps/web/src/app/admin/orders/page.test.tsx`
   Level: integration
   Test intent: Given density toggle / When pilih Compact / Then class + persist.
   Exercise through: render + fireEvent
   Test doubles: fetch mock
   Expected RED: density belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/orders/page.test.tsx`
9. Implement density → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (orders), "Table.tsx generic contract" (orders pakai props sama).

## WHY THIS APPROACH
Complexity: standard
Justification: Halaman orders fetch inline (tanpa api.ts) — perubahan terkonsentrasi di page + komponen generik.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah approve/cancel/show logic; Table props sama dgn halaman lain; summary = total saja]
You are implementing orders page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/orders/page.tsx`, `apps/web/src/app/admin/orders/page.test.tsx`
Available after: T4, T8
Architecture rule: cursor offset; summary total; sort local state via Table props.
[RESTATE: jangan ubah approve/cancel/show logic; Table props sama dgn halaman lain; summary = total saja]

## DELIVERABLE
Given load orders, When selesai, Then terbaru pertama + summary total + kolom Dibuat.
Given klik header Status, When onSort, Then fetch sort=status&order=desc&cursor=0.
Given klik header Status kedua kali, When onSort, Then fetch sort=status&order=asc&cursor=0.
Given pindah halaman 2, When jump, Then cursor=100 + sort dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `approve()` → `loadOrders()` tetap dipanggil (refresh data).
Must-not-have:
  - Menyentuh `OrderForm` / `Order` type / statusHistory render.
Rollback note:
  - Revert page.
Red flags:
  - Mengubah approve/cancel endpoint → STOP.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: `meta.total` absent → fallback estimasi.
Escalate when: perlu api.ts terpisah untuk orders.
