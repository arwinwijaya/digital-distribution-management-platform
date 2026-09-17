# Task T9 — `ProductController@index` — additive sort/cursor/total (public endpoint)

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: `ProductController@index` — additive sort/cursor/total (public endpoint) [depends: T1]

## OBJECTIVE
Tambah `sort`/`order`/`cursor`/`total` **aditif** pada `ProductController@index` (endpoint publik `/products`, dipakai halaman admin products). Default tetap `orderBy('id')` + hard `limit(100)` agar marketplace/catalog tidak terpengaruh. Allowlist `[created_at,updated_at,name,sku,price,stock_quantity,id]`.

Steps:
1. Write failing test for: default preserved + additive total
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given produk / When `GET /products` (tanpa param baru) / Then urutan tetap id ASC (default lama) + `meta.total` hadir (aditif); `?sort=created_at&order=desc` / Then urut created_at DESC nulls-last.
   Exercise through: `getJson('/products')`
   Test doubles: none (public endpoint; supplier filter existing)
   Expected RED: `meta.total` absent; sort param diabaikan.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
3. Implement additive sort/total/offset (default id ASC preserved) → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + search + total dengan search
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given `?sort=__proto__` / Then 200 fallback ke default produk `id ASC` (via default caller T1 — BUKAN `created_at DESC` global); `?search=xyz` / Then `meta.total` mencerminkan hasil search (filter sama).
   Exercise through: `getJson('/products?...')`
   Test doubles: none
   Expected RED: total tidak mengikuti filter search.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (products), Implementation Notes `products`.

## WHY THIS APPROACH
Complexity: standard
Justification: Endpoint publik dipakai bersama; default lama wajib dipertahankan persis, param baru aditif.

## SANDWICH CONTEXT
[CRITICAL: default orderBy id + limit 100 TIDAK berubah; param baru aditif; jangan pecah filter supplier aktif]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/ProductController.php`, `apps/api/tests/Feature/ProductTest.php`
Available after: T1
Architecture rule: tanpa `sort` → default id ASC (kompatibel); dengan `sort` → allowlist.
[RESTATE: default orderBy id + limit 100 TIDAK berubah; param baru aditif; jangan pecah filter supplier aktif]

## DELIVERABLE
Given GET /products tanpa param, When merespons, Then urutan id ASC (default lama) + meta.total aditif.
Given GET /products?sort=created_at&order=desc, When merespons, Then created_at DESC nulls-last.
Given GET /products?search=xyz, When merespons, Then meta.total mengikuti hasil search.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Filter `whereNull(supplier_id) orWhereHas(supplier active)` tetap utuh.
Must-not-have:
  - Mengubah default order/limit saat param baru absent.
  - Menyentuh `AdminProductController@update`/`prices`.
Rollback note:
  - Revert controller; consumer marketplace tidak terpengaruh.
Red flags:
  - Mengubah default `orderBy('id')` → STOP.

## STOP CONDITIONS
Done when: 2 integration test groups PASS; default lama terverifikasi tidak berubah.
Uncertain when: `sku`/`stock_quantity` bukan kolom real (allowlist) — assumed.
Escalate when: butuh endpoint admin products terpisah (scope creep).
