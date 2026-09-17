# Task T12 — Products page — sort/paging/summary/timestamps/density

**Phase:** 3
**Depends:** T4, T9
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 12: Products page — sort/paging/summary/timestamps/density [depends: T4, T9]

## OBJECTIVE
Upgrade `products/page.tsx` + `api.ts`: sort default `created_at DESC` (fallback id DESC karena dummy produk mungkin tanpa created_at), cursor/page, summary `{total, out_of_stock}`, kolom `Dibuat`/`Diperbarui`, density; `api.ts` kirim `sort`/`order`/`cursor`, baca `meta.total`/`meta.summary`; dummy `listDummyProducts` parity via T2.

Steps:
1. Write failing test for: default sort + summary stok habis + timestamp null "—"
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given produk (sebagian tanpa created_at) / When load / Then baris pertama = created_at terbaru (id DESC tiebreak), summary "N produk · M stok habis", sel tanpa timestamp "—".
   Exercise through: render `<AdminProductsPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock
   Expected RED: sort/summary/kolom belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx`
3. Implement → PASS → refactor → commit.

4. Write failing test for: sort header + search reset cursor + paging
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Harga" / Then fetch `sort=price&order=desc&cursor=0`; klik header "Harga" KEDUA kali / Then `sort=price&order=asc&cursor=0`; ubah search saat halaman >1 / Then cursor=0; pindah halaman / Then sort+filter dipertahankan.
   Exercise through: render + fireEvent
   Test doubles: fetch mock
   Expected RED: param sort/cursor tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx`
6. Implement → PASS → refactor → commit.

7. Write failing test for: density + dummy parity
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given density toggle / When pilih / Then class + persist; dummy: `listDummyProducts` sort created_at DESC + offset slice + total=filtered.length + summary `{total,out_of_stock}`.
   Exercise through: render + fireEvent; dummy branch
   Test doubles: fetch mock; dummy flag
   Expected RED: density/dummy sort belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx`
9. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (products), "Summary strip source" (products).

## WHY THIS APPROACH
Complexity: standard
Justification: Produk memakai endpoint publik; frontend harus toleran total absent (fallback estimasi).

## SANDWICH CONTEXT
[CRITICAL: Table generik; dummy path parity; jangan ubah updateProductPrice/fetchPriceHistory]
You are implementing products page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/products/page.tsx`, `apps/web/src/app/admin/products/api.ts`, `apps/web/src/app/admin/products/page.test.tsx`
Available after: T4, T9
Architecture rule: reset filter(sort) → cursor=0; fallback estimasi bila total absent.
[RESTATE: Table generik; dummy path parity; jangan ubah updateProductPrice/fetchPriceHistory]

## DELIVERABLE
Given load produk, When selesai, Then default terbaru + summary {total,out_of_stock} + kolom waktu (null → "—").
Given klik header Harga, When onSort, Then fetch sort=price&order=desc&cursor=0.
Given klik header Harga kedua kali, When onSort, Then fetch sort=price&order=asc&cursor=0.
Given ubah search di halaman >1, When terapkan, Then cursor=0 + filter baru.
Given pindah halaman, When jump, Then sort+filter dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Dummy produk tanpa created_at → id DESC fallback (nulls-last).
Must-not-have:
  - Menyentuh updateProductPrice/fetchPriceHistory/dummyPriceHistory.
Rollback note:
  - Revert page+api.
Red flags:
  - Mengubah signatur `fetchProducts` return yang dipakai di halaman lain → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: `meta.summary.out_of_stock` absent → fallback total only.
Escalate when: perlu endpoint admin produk terpisah.
