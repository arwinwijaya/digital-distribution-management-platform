# Task T6 — `UserRoleController@index` — offset cursor + sort + total

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: `UserRoleController@index` — offset cursor + sort + total [depends: T1]

## OBJECTIVE
Tambah offset cursor + sort allowlist `[created_at,updated_at,name,email,role,id]` + top-level `meta.total` (summary total) pada `index` users, pertahankan guard `viewAny` + validasi role/limit.

Steps:
1. Write failing test for: default sort terbaru + total
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent: Given beberapa user (created_at bervariasi) / When `GET /admin/users?limit=20` / Then `data[0]` = created_at terbaru (id DESC tiebreak), `meta.total` = jumlah user sesuai role filter.
   Exercise through: `getJson('/admin/users')` (auth admin)
   Test doubles: none beyond auth
   Expected RED: urutan masih id ASC, `meta.total` absent.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RoleManagementTest`
3. Implement sort + total + offset → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + role filter total
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent: Given `?sort=name&order=asc` / Then urut nama asc; `?sort=__proto__` / Then 200 fallback default; `?role=sales` / Then `meta.total` = jumlah sales saja.
   Exercise through: `getJson('/admin/users?...')`
   Test doubles: none
   Expected RED: param sort diabaikan/total tidak terfilter.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RoleManagementTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (users), "Kontrak total", "Authorization".

## WHY THIS APPROACH
Complexity: lightweight
Justification: Perubahan terfokus pada list endpoint; guard + validasi role/limit dipertahankan.

## SANDWICH CONTEXT
[CRITICAL: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah policy viewAny/guard]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/UserRoleController.php`, `apps/api/tests/Feature/RoleManagementTest.php`
Available after: T1
Architecture rule: total = COUNT dgn role filter sama; offset cursor.
[RESTATE: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah policy viewAny/guard]

## DELIVERABLE
Given GET /admin/users tanpa sort, When merespons, Then created_at DESC nulls-last + meta.total.
Given GET /admin/users?role=sales, When merespons, Then meta.total hanya sales + data terfilter.
Given GET /admin/users?sort=__proto__, When merespons, Then 200 fallback default.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `role` validasi `in:` tetap; `limit` min1 max100 tetap.
Must-not-have:
  - Mengubah policy `viewAny` atau role assignment endpoint (`/admin/users/{id}/role`).
Rollback note:
  - Revert controller.
Red flags:
  - Menghapus `abort_unless(viewAny)` → STOP.

## STOP CONDITIONS
Done when: 2 integration test groups PASS.
Uncertain when: role filter selain `in:` list tidak bisa dihitung totalnya.
Escalate when: perlu menyentuh UserRoleService.
