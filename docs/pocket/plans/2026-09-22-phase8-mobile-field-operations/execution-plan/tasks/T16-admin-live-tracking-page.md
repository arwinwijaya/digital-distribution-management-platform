# Task T16 — Halaman live tracking admin (`GeoMap` + polling)

**Phase:** 4
**Depends:** T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 16: Halaman live tracking admin

## OBJECTIVE
Buat halaman tracking delivery untuk admin: peta `GeoMap` menampilkan posisi terakhir
driver + polling berkala ke `GET /admin/deliveries/{id}/track`.

Steps:
1. Write failing test for: render posisi + polling.
   Test file: `apps/web/src/app/admin/tracking/page.test.tsx`
   Level: component
   Test intent: Given mock `getTrack` mengembalikan `last_position` / When render /
   Then `GeoMap` menerima 1 titik; When fake timer maju interval / Then `getTrack`
   dipanggil lagi.
   Exercise through: render + jest fake timers.
   Test doubles: mock `@/app/admin/tracking/api`, mock `GeoMap`.
   Expected RED: halaman belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/tracking`
3. Implement halaman + polling + GeoMap → PASS → refactor → commit.

4. Write failing test for: tanpa posisi.
   Test intent: Given `last_position` null / Then tampil state "Belum ada lokasi" tanpa
   error; polling tetap berjalan.
5. Run test — verify FAIL → implement empty state → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-4, AC-7, DD-5; `apps/web/src/components/data-intelligence/GeoMap.tsx`.

## WHY THIS APPROACH
Complexity: medium
Justification: reuse `GeoMap` (Leaflet) yang sudah ada; polling interval tetap (bukan WebSocket).

## SANDWICH CONTEXT
[CRITICAL: bersihkan interval saat unmount; guard dummy; jangan render peta bila tak ada titik]
Files in scope: `apps/web/src/app/admin/tracking/page.tsx`, `apps/web/src/app/admin/tracking/api.ts`, test.
Available after: T7 (endpoint track).
Architecture rule: `GeoMap` menerima array `{latitude,longitude,label?}`; interval mis. 15s; dummy mode → fixture statis.

## DELIVERABLE
- Halaman `/admin/tracking` (atau `/admin/deliveries/[id]/tracking`): pilih delivery → peta + info.
- Polling dengan cleanup; indikator update terakhir.
- `getTrack(deliveryId)` di `api.ts` (guard dummy).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Interval dibersihkan saat unmount.
  - Empty state tanpa error.
Must-not-have:
  - WebSocket/push.
Open question risks:
  - Rute halaman (list delivery vs deep link) — asumsi deep link `/admin/deliveries/{id}/tracking` + tautan dari daftar delivery.
Rollback note:
  - Hapus halaman + api method.

## STOP CONDITIONS
Done when: render posisi + polling + empty state PASS.
Escalate when: `GeoMap` butuh perubahan kontrak.
