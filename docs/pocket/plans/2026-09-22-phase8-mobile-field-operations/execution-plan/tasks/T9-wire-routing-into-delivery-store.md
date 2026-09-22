# Task T9 — Wire `RoutingService` ke `DeliveryController@store` + route admin

**Phase:** 2
**Depends:** T3, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Wire `RoutingService` ke `DeliveryController@store` + route admin

## OBJECTIVE
Pastikan `DeliveryController@store` menyimpan hasil `RoutingService::plan()` yang sudah
berisi stop ke `route_data`, dan tambah endpoint `GET /admin/deliveries/{id}/route`
untuk melihat rute terjadwal.

Steps:
1. Write failing test for: route_data terisi saat assignment.
   Test file: `apps/api/tests/Feature/FieldOps/DeliveryRouteTest.php`
   Level: feature
   Test intent: Given admin assign delivery ke driver untuk order confirmed dengan outlet
   berkoordinat / When `POST /deliveries` / Then `route_data.stops` tidak kosong; When
   outlet tanpa koordinat / Then `route_data` null tanpa error.
   Exercise through: HTTP endpoint.
   Test doubles: factory Order/Outlet/Delivery/User.
   Expected RED: `route_data` masih `null` (plan mengembalikan `[]` lama) atau route baru 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DeliveryRouteTest`
3. Wire plan() → route_data + tambah `GET /admin/deliveries/{id}/route` → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-3, DD-2; `DeliveryController@store` (memanggil `$this->routing->plan()`).

## WHY THIS APPROACH
Complexity: lightweight
Justification: `store()` sudah memanggil `plan()`; T9 hanya memastikan output T3 benar-benar
tersimpan & terekspos, plus route baca untuk admin.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah validasi/transaksi assignment; route_data nullable]
Files in scope: `apps/api/app/Http/Controllers/DeliveryController.php`, `apps/api/routes/api.php`, test.
Available after: T3 (plan nyata), T5 (pola rbac admin).
Architecture rule: `route_data` null bila tak ada stop; endpoint baca `rbac:field_ops:read`.

## DELIVERABLE
- `store()` menyimpan `route_data` hasil plan (sudah ada — verifikasi perilaku).
- `GET /admin/deliveries/{id}/route` mengembalikan `route_data` (atau `null`).
- Test memverifikasi kedua jalur (ada/tanpa koordinat).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Tidak mengubah kontrak `store()` yang ada (status/error).
  - `route_data` null-safe.
Must-not-have:
  - Menambah kolom baru (sudah ada `route_data`).
Open question risks:
  - Format `route_data` — ikuti output T3 (`{stops,total_distance_km}`).
Rollback note:
  - Hapus route baca; `store()` tetap kompatibel.

## STOP CONDITIONS
Done when: route_data tersimpan + endpoint baca PASS.
Escalate when: format plan T3 tidak sesuai.
