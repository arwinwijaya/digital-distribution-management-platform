# Task T5 — `DriverRosterController` CRUD + requests + routes

**Phase:** 2
**Depends:** T2, T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: `DriverRosterController` CRUD + requests + routes

## OBJECTIVE
Endpoint CRUD roster driver di `/admin/drivers` (index dengan kontrak `ListQuery`,
store, update, destroy/soft-toggle) dengan validasi user harus role `driver` & belum
punya profil, otorisasi `rbac:driver_roster:<level>`.

Steps:
1. Write failing test for: create + validasi + otorisasi.
   Test file: `apps/api/tests/Feature/FieldOps/DriverRosterTest.php`
   Level: feature
   Test intent: Given admin + user driver tanpa profil / When `POST /admin/drivers` /
   Then 201 + record; When `user_id` bukan driver / Then 422; When sudah punya profil /
   Then 422; When sales / Then 403; When tanpa token / Then 401.
   Exercise through: HTTP endpoint.
   Test doubles: factory User (driver/sales/admin), Territory.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DriverRosterTest`
3. Implement controller + requests + routes → PASS → refactor → commit.

4. Write failing test for: index sort/paging/total + update.
   Test intent: Given 25 roster / When `GET /admin/drivers?per_page=10` / Then
   `meta.total=25` + 10 baris; When `PATCH /admin/drivers/{id}` ubah `vehicle_type` /
   Then 200 + berubah.
5. Run test — verify FAIL → implement `ListQuery` → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-1; `apps/api/app/Support/ListQuery.php` (kontrak tabel admin); `AdminOutletController` sebagai preseden pola.

## WHY THIS APPROACH
Complexity: medium
Justification: mengikuti pola controller admin yang sudah ada (ListQuery + FormRequest + rbac middleware).

## SANDWICH CONTEXT
[CRITICAL: rbac:driver_roster:* di semua route; jangan duplikasi logika ListQuery]
Files in scope: `apps/api/app/Http/Controllers/DriverRosterController.php`, `apps/api/app/Http/Requests/{Store,Update}DriverProfileRequest.php`, `apps/api/routes/api.php`, test.
Available after: T2 (model), T4 (menu+level).
Architecture rule: envelope `{status,data}`; `meta.total` top-level; sort allowlist `['created_at','plate_number','id']`.

## DELIVERABLE
- `GET /admin/drivers` (rbac:driver_roster:read) dengan sort/paging/total/summary.
- `POST /admin/drivers`, `PATCH /admin/drivers/{id}`, `DELETE /admin/drivers/{id}` (rbac:driver_roster:edit).
- Validasi: `user_id` exists + role driver + belum punya profil; `plate_number` unik.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 401/403 teruji untuk tiap route.
  - Validasi driver + duplikat profil.
  - `platform_owner` boleh (setara admin via policy/level).
Must-not-have:
  - Menghapus user; destroy hanya menghapus profil (atau soft `is_available=false`).
Open question risks:
  - Delete vs deactivate — asumsi: DELETE menghapus profil roster, user tetap.
Rollback note:
  - Hapus controller/requests/routes.

## STOP CONDITIONS
Done when: CRUD + validasi + otorisasi + index kontrak PASS.
Escalate when: butuh ubah model User/route guard.
