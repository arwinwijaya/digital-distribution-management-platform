# Task T2 — `DriverProfile` model + relasi + factory + seeder

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: `DriverProfile` model + relasi + factory + seeder

## OBJECTIVE
Buat model `DriverProfile` dengan relasi ke `User` dan `Territory`, factory, dan
tambahkan relasi `driverProfile()` pada `User`.

Steps:
1. Write failing test for: relasi + fillable + cast.
   Test file: `apps/api/tests/Unit/DriverProfileTest.php`
   Level: unit
   Test intent: Given `DriverProfile::factory()->create(['user_id' => $driver->id])` /
   When `$driver->driverProfile` / Then instance `DriverProfile`; When
   `$profile->user` / Then instance `User`; When `$profile->capacity_kg` / Then `int`.
   Exercise through: Eloquent relasi.
   Test doubles: none.
   Expected RED: class `App\Models\DriverProfile` belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DriverProfileTest`
3. Implement model + factory + relasi User → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 8 — In-Scope (driver roster CRUD), AC-1.

## WHY THIS APPROACH
Complexity: lightweight
Justification: Roster butuh entitas profil terpisah dari `users` agar assignment tidak
memaksa kolom kendaraan di tabel user.

## SANDWICH CONTEXT
[CRITICAL: `user_id` unique; jangan ubah model User lain selain menambah relasi]
Files in scope: `apps/api/app/Models/DriverProfile.php`, `apps/api/app/Models/User.php` (tambah relasi), `apps/api/database/factories/DriverProfileFactory.php`, `apps/api/database/seeders/*`.
Available after: T1 (tabel ada).
Architecture rule: `$fillable` eksplisit; cast `is_available` bool, `capacity_kg` int.

## DELIVERABLE
- `DriverProfile` model: fillable `user_id, vehicle_type, plate_number, capacity_kg, service_territory_id, shift_start, shift_end, is_available`; relasi `user()`, `serviceTerritory()`.
- `User::driverProfile()` hasOne.
- `DriverProfileFactory` deterministik.
- Seeder opsional: 2–3 profil untuk driver di `DatabaseSeeder` (idempotent).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Relasi dua arah teruji.
  - Factory menghasilkan `plate_number` unik-format valid.
Must-not-have:
  - Mengubah kolom `users`.
Open question risks:
  - A-5: driver tanpa profil tetap valid (relasi nullable).
Rollback note:
  - Hapus model/factory/relasi.

## STOP CONDITIONS
Done when: unit test relasi PASS + factory bisa dipakai di test lain.
Escalate when: butuh kolom user tambahan.
