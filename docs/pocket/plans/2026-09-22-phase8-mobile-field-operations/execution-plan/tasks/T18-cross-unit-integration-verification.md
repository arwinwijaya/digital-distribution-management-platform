# Task T18 — Integrasi lintas unit + verifikasi suite penuh

**Phase:** 6
**Depends:** T12, T17
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 18: Integrasi lintas unit + verifikasi suite penuh [test-risk]

## OBJECTIVE
Verifikasi skenario lintas unit end-to-end Phase 8 dan jalankan seluruh suite (API + web +
TypeScript) untuk memastikan tidak ada regresi.

Steps:
1. Write failing test for: skenario lintas unit (offline → flush → tracking → PoD).
   Test file: `apps/api/tests/Feature/FieldOps/FieldOpsIntegrationTest.php` +
   `apps/web/src/app/__tests__/field-ops-integration.test.tsx`
   Level: integration
   Test intent:
   - API: Given sales check-in visit lalu driver kirim ping lalu upload PoD / When alur
     dijalankan berurutan / Then semua state tersimpan konsisten dan endpoint track
     mengembalikan posisi terakhir.
   - Web: Given offline order di antrean / When online + flush / Then order terkirim dan
     muncul di daftar (mock fetch end-to-end).
   Exercise through: HTTP (API) + render (web).
   Test doubles: `Storage::fake`, mock geolocation, mock online event.
   Expected RED: skenario belum tertutup (fails karena wiring belum lengkap).
2. Run test — verify FAIL.
3. Perbaiki wiring yang kurang → PASS → refactor → commit.

4. Jalankan suite penuh dan catat hasil.
   - `cd apps/api && php artisan test`
   - `cd apps/web && npx jest`
   - `cd apps/web && npx tsc --noEmit`
5. Jika ada regresi → perbaiki; ulangi sampai 0 failed.
6. Tulis ringkasan verifikasi (jumlah test, skip pgsql-only, hasil tsc) → commit.

## REFERENCES LOADED
Spec Phase 8 — seluruh Acceptance Criteria; pola verifikasi closeout plan lain (mis. `2026-09-18-rbac-menu-matrix/closeout.md`).

## WHY THIS APPROACH
Complexity: medium
Justification: fitur Phase 8 menyentuh 3 unit (API, PWA client, dummy parity); integrasi
lintas unit adalah satu-satunya cara membuktikan alur lapangan end-to-end.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah fitur untuk "mempermudah" test; perbaiki wiring nyata]
Files in scope: `apps/api/tests/Feature/FieldOps/FieldOpsIntegrationTest.php`, `apps/web/src/app/__tests__/field-ops-integration.test.tsx`.
Available after: T12 (offline), T17 (dummy/RBAC).
Architecture rule: test integrasi tidak boleh bergantung pada layanan eksternal; pgsql-only skip diizinkan.

## DELIVERABLE
- 1 test integrasi API + 1 test integrasi web (lintas unit).
- Laporan hasil suite: API (passed/skipped/failed), web (passed/failed), tsc clean.
- Tidak ada regresi pada test lama.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Alur offline→flush→tracking→PoD terverifikasi.
  - Suite penuh 0 failed (skip pgsql-only diperbolehkan).
Must-not-have:
  - Menonaktifkan/menghapus test lama untuk hijau.
Open question risks:
  - Flakiness timer (polling/flush) → pakai fake timers deterministik.
Rollback note:
  - Test bersifat additive.

## STOP CONDITIONS
Done when: integrasi lintas unit PASS + suite penuh 0 failed + tsc clean.
Uncertain when: regresi pada test lama yang tidak terkait Phase 8.
Escalate when: dibutuhkan perubahan kontrak lintas fitur.
