# Task T14 — UI check-in/out kunjungan sales + geolokasi

**Phase:** 4
**Depends:** T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 14: UI check-in/out kunjungan sales

## OBJECTIVE
Tambahkan UI check-in/check-out pada halaman kunjungan sales (`/sales`) yang meminta izin
geolokasi, menampilkan status, dan memanggil endpoint T6.

Steps:
1. Write failing test for: tombol check-in meminta geolokasi.
   Test file: `apps/web/src/app/sales/checkin.test.tsx`
   Level: component
   Test intent: Given visit `planned` / When klik "Check-in" / Then
   `navigator.geolocation.getCurrentPosition` dipanggil; When sukses / Then
   `checkInVisit(id, coords)` dipanggil + status berubah.
   Exercise through: render + click + mock geolocation.
   Test doubles: mock `navigator.geolocation`, mock api.
   Expected RED: UI belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/sales/checkin.test.tsx`
3. Implement tombol + geolocation + panggilan api → PASS → refactor → commit.

4. Write failing test for: error geolokasi + di luar radius.
   Test intent: Given geolocation error (permission denied) / Then pesan error tampil,
   tidak memanggil api; Given api 422 radius / Then pesan "Di luar radius outlet".
5. Run test — verify FAIL → implement error handling → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-2, AC-7, A-1.

## WHY THIS APPROACH
Complexity: medium
Justification: memakai `navigator.geolocation` bawaan browser; tidak ada dependency peta baru untuk check-in.

## SANDWICH CONTEXT
[CRITICAL: jangan panggil api bila geolokasi gagal; tampilkan pesan jelas]
Files in scope: `apps/web/src/app/sales/page.tsx` (atau komponen baru `VisitCheckin.tsx`), `apps/web/src/app/sales/api.ts`, test.
Available after: T6 (endpoint).
Architecture rule: guard dummy (check-in dummy = fake sukses); timeout geolokasi 10s.

## DELIVERABLE
- Tombol Check-in/Check-out dengan status loading/disabled.
- Permintaan izin geolokasi + fallback error.
- Tampilan `check_in_at`/`check_out_at` bila sudah terisi.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Geolokasi gagal → tidak memanggil API.
  - Pesan error radius ditampilkan.
Must-not-have:
  - Menambah library peta untuk check-in.
Open question risks:
  - Izin lokasi ditolak permanen → sediakan pesan panduan.
Rollback note:
  - Hapus UI + api method.

## STOP CONDITIONS
Done when: check-in sukses + error geolokasi + error radius PASS.
Escalate when: kontrak endpoint T6 berubah.
