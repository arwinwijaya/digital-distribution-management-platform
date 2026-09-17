# Task T8 — `OrderController@index` — offset cursor + sort + total

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: `OrderController@index` — offset cursor + sort + total [depends: T1]

## OBJECTIVE
Tambah offset cursor + sort allowlist `[created_at,updated_at,order_id,status,total_amount,id]` + top-level `meta.total` pada `index`; PERTAHANKAN cek `isAdmin()` + middleware `deny.finance`; endpoint dipakai bersama `/orders` & `/admin/orders`.

Steps:
1. Write failing test for: offset cursor + total + default sort
   Test file: `apps/api/tests/Feature/OrderTest.php`
   Level: integration
   Test intent: Given order (created_at bervariasi) / When `GET /admin/orders?limit=100&cursor=0` / Then data urut created_at DESC, `meta.total` = jumlah; `cursor=100` / Then offset halaman 2; non-admin / Then tetap 403.
   Exercise through: `getJson('/admin/orders')` (auth admin)
   Test doubles: none beyond auth
   Expected RED: `meta.total` absent + cursor diabaikan.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderTest`
3. Implement offset + total (keep `latest('created_at')` default + allowlist sort) → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + status sort + non-admin 403
   Test file: `apps/api/tests/Feature/OrderTest.php`
   Level: integration
   Test intent: Given `?sort=status&order=asc` / Then urut status asc; `?sort=__proto__` / Then 200 fallback; user non-admin / Then 403 (tidak berubah).
   Exercise through: `getJson('/admin/orders?...')`
   Test doubles: none
   Expected RED: sort diabaikan / auth tidak dipertahankan.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (orders), "Kontrak total", "Authorization", Implementation Notes `orders`.

## WHY THIS APPROACH
Complexity: standard
Justification: Endpoint shared & admin-only; perubahan harus additive agar `/orders` konsumen lain tidak terpengaruh.

## SANDWICH CONTEXT
[CRITICAL: jangan hapus cek isAdmin()/middleware deny.finance; sort via allowlist; tambahan param aditif]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/tests/Feature/OrderTest.php`
Available after: T1
Architecture rule: default `latest('created_at')` dipertahankan; offset cursor tambahan.
[RESTATE: jangan hapus cek isAdmin()/middleware deny.finance; sort via allowlist; tambahan param aditif]

## DELIVERABLE
Given GET /admin/orders, When merespons, Then created_at DESC + meta.total + offset cursor.
Given GET /admin/orders?sort=status, When merespons, Then urut status (asc/desc).
Given user non-admin, When GET /admin/orders, Then 403 (tidak berubah).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `formatOrderResponse` tidak diubah (pipeline Order tetap utuh).
Must-not-have:
  - Menyentuh approve/cancel/show logic.
  - Mengubah middleware `deny.finance`.
Rollback note:
  - Revert controller; consumer lain tidak terpengaruh (param aditif).
Red flags:
  - Mengubah response shape `data` order item → STOP (pipeline).

## STOP CONDITIONS
Done when: 2 integration test groups PASS; auth guard tetap.
Uncertain when: `total_amount` kolom real (allowlist) — assumed.
Escalate when: butuh menyentuh OrderService.
