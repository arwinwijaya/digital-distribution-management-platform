# Task T7 — Delivery location ping endpoints (driver POST + admin track GET)

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: Delivery location ping endpoints

## OBJECTIVE
Tambah `POST /deliveries/{id}/location` (driver pemilik mengirim ping GPS) dan
`GET /admin/deliveries/{id}/track` (admin melihat posisi terakhir + N ping terbaru).

Steps:
1. Write failing test for: driver kirim ping.
   Test file: `apps/api/tests/Feature/FieldOps/DeliveryTrackingTest.php`
   Level: feature
   Test intent: Given driver dengan delivery miliknya / When `POST /deliveries/{id}/location`
   lat/long / Then 201 + ping tersimpan; When driver lain / Then 403; When lat/long invalid
   / Then 422.
   Exercise through: HTTP endpoint.
   Test doubles: factory Delivery + driver User.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DeliveryTrackingTest`
3. Implement POST location → PASS → refactor → commit.

4. Write failing test for: admin track read.
   Test intent: Given 5 ping untuk delivery / When `GET /admin/deliveries/{id}/track` sebagai
   admin / Then `last_position` = ping terbaru + `pings` maks N (terbaru dulu); When sales /
   Then 403.
5. Run test — verify FAIL → implement GET track → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-4, DD-5 (polling).

## WHY THIS APPROACH
Complexity: medium
Justification: tabel ping + 2 endpoint minimal; polling agar tidak perlu WebSocket.

## SANDWICH CONTEXT
[CRITICAL: driver hanya boleh ping delivery miliknya; admin-only untuk track; jangan ubah endpoint delivery lama]
Files in scope: `apps/api/app/Http/Controllers/DeliveryController.php` (tambah method), `apps/api/app/Http/Requests/StoreLocationPingRequest.php`, `apps/api/routes/api.php`, test.
Available after: T1 (tabel ping).
Architecture rule: simpan `recorded_at = now()`; `pings` cap (mis. 50) order `recorded_at` desc.

## DELIVERABLE
- `POST /deliveries/{id}/location` (rbac:field_ops:edit atau delivery:edit) → 201.
- `GET /admin/deliveries/{id}/track` (rbac:field_ops:read) → `{last_position, pings}`.
- Validasi lat (-90..90) / long (-180..180).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Ownership driver teruji.
  - Cap ping teruji.
Must-not-have:
  - Menyimpan ping tanpa validasi koordinat.
Open question risks:
  - Menu mana yang menggerbangi tracking (field_ops vs delivery) — asumsi `field_ops`.
Rollback note:
  - Hapus 2 route + method; tabel ping di-rollback di T1.

## STOP CONDITIONS
Done when: ping + track + otorisasi + validasi PASS.
Escalate when: butuh WebSocket/push.
