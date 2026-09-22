# Task T6 — Visit check-in/check-out endpoints + radius validation

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Visit check-in/check-out endpoints + radius validation

## OBJECTIVE
Tambah `POST /sales/visits/{id}/check-in` dan `POST /sales/visits/{id}/check-out` pada
`SalesController`, menyimpan GPS + waktu, memvalidasi radius ke outlet (default 200 m),
scoped ke sales pemilik visit, dengan guard idempotensi.

Steps:
1. Write failing test for: check-in dalam radius.
   Test file: `apps/api/tests/Feature/FieldOps/SalesVisitCheckinTest.php`
   Level: feature
   Test intent: Given sales + visit `planned` outlet berkoordinat / When check-in dengan
   lat/long dekat outlet / Then 200 + `check_in_at` terisi; When koordinat jauh (>radius)
   / Then 422; When visit sales lain / Then 403; When sudah check-in lalu check-in lagi /
   Then 409/422.
   Exercise through: HTTP endpoint.
   Test doubles: factory Outlet (lat/long), SalesVisit.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=SalesVisitCheckinTest`
3. Implement check-in → PASS → refactor → commit.

4. Write failing test for: check-out mengubah status.
   Test intent: Given visit sudah check-in / When check-out / Then `check_out_at` terisi +
   status `completed`; When check-out tanpa check-in / Then 422.
5. Run test — verify FAIL → implement check-out → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-2, A-1 (radius 200 m).

## WHY THIS APPROACH
Complexity: medium
Justification: memperluas `SalesController` + `sales_visits` (DD-1), radius via config `config('orders.visit_radius_m', 200)`.

## SANDWICH CONTEXT
[CRITICAL: scoped ke sales pemilik; jangan ubah endpoint visit lama; jangan tambah tabel baru]
Files in scope: `apps/api/app/Http/Controllers/SalesController.php`, `apps/api/app/Http/Requests/*VisitCheck*Request.php`, `apps/api/routes/api.php`, `apps/api/config/orders.php`, test.
Available after: T1 (kolom GPS).
Architecture rule: haversine radius; tolak bila outlet tidak punya koordinat (422 dengan pesan jelas).

## DELIVERABLE
- `POST /sales/visits/{id}/check-in` (rbac:sales:edit): simpan `check_in_*`, 200.
- `POST /sales/visits/{id}/check-out` (rbac:sales:edit): simpan `check_out_*`, status `completed`.
- Guard: 403 bukan pemilik, 422 di luar radius / tanpa koordinat outlet, 409 bila sudah check-in.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Radius & otorisasi teruji.
  - Idempotensi check-in.
Must-not-have:
  - Mengubah endpoint visit existing.
Open question risks:
  - A-1: radius 200 m; configurable.
Rollback note:
  - Hapus 2 route + method; kolom GPS tetap (nullable).

## STOP CONDITIONS
Done when: check-in/out + radius + otorisasi + idempotensi PASS.
Escalate when: butuh reverse geocoding/geo-fence kompleks.
