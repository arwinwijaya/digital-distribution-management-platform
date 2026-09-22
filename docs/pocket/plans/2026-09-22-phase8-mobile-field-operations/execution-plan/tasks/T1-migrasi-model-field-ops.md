# Task T1 — Migrasi & model field-ops (driver_profiles, visit GPS, PoD meta, location pings)

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Migrasi & model field-ops [prereq]

## OBJECTIVE
Tambahkan skema additive untuk Phase 8: tabel `driver_profiles` dan
`delivery_location_pings`, kolom GPS pada `sales_visits`, dan kolom metadata PoD pada
`deliveries`. Semua migrasi reversible dan portabel SQLite + PostgreSQL.

Steps:
1. Write failing test for: skema tabel baru + kolom baru ada.
   Test file: `apps/api/tests/Feature/FieldOpsMigrationTest.php`
   Level: feature
   Test intent: Given fresh migrated DB / When `Schema::hasTable('driver_profiles')` dan
   `Schema::hasTable('delivery_location_pings')` / Then `true`; When
   `Schema::hasColumns('sales_visits', ['check_in_at','check_in_latitude','check_in_longitude','check_in_accuracy_m','check_out_at','check_out_latitude','check_out_longitude'])` /
   Then `true`; When `Schema::hasColumns('deliveries', ['pod_captured_at','pod_latitude','pod_longitude'])` / Then `true`.
   Exercise through: `RefreshDatabase` + `Schema`.
   Test doubles: none.
   Expected RED: tabel/kolom belum ada → assertion gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=FieldOpsMigrationTest`
3. Buat 4 migrasi additive; implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md — Scope In-Scope (roster, GPS, tracking, PoD), Architecture Constraints (portable SQLite/PostgreSQL, additive+reversible).

## WHY THIS APPROACH
Complexity: lightweight
Justification: Fondasi data untuk seluruh Phase 8; memperluas model yang ada (DD-1) alih-alih membuat entitas visit/delivery baru.

## SANDWICH CONTEXT
[CRITICAL: migrasi additive + reversible; jangan ubah kolom lama; jangan sentuh guard/auth]
You are implementing the data foundation untuk Phase 8 mobile field operations.
Spec: docs/pocket/spec/2026-09-22-phase8-mobile-field-operations/phase8-mobile-field-operations.md
Design decision: DD-1 (additive seam-preserving).
Files in scope: `apps/api/database/migrations/*` (4 file baru), `apps/api/tests/Feature/FieldOpsMigrationTest.php`
Available after: none (prereq)
Architecture rule: tidak ada fitur Postgres-only; gunakan tipe portabel (`decimal(10,7)`, `unsignedInteger`).
[RESTATE: migrasi additive + reversible; jangan ubah kolom lama]

## DELIVERABLE
- `driver_profiles`: `id`, `user_id` (unique, FK users restrict), `vehicle_type`, `plate_number`, `capacity_kg` (unsigned int nullable), `service_territory_id` (FK territories nullable), `shift_start` (time nullable), `shift_end` (time nullable), `is_available` (bool default true), timestamps.
- `delivery_location_pings`: `id`, `delivery_id` (FK deliveries cascade), `latitude` (decimal 10,7), `longitude` (decimal 10,7), `accuracy_m` (unsigned int nullable), `recorded_at` (datetime), timestamps; index `['delivery_id','recorded_at']`.
- `sales_visits` +7 kolom GPS (semua nullable).
- `deliveries` +3 kolom PoD meta (nullable).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Setiap migrasi punya `down()` yang membalik tepat (drop kolom/tabel).
  - `migrate` + `migrate:rollback` bersih di SQLite.
  - FK pakai `restrictOnDelete` (profil) / `cascadeOnDelete` (ping).
Must-not-have:
  - Mengubah/menghapus kolom `sales_visits`/`deliveries` yang ada.
  - Menambah dependency DB baru.
Open question risks:
  - A-2: penyimpanan PoD memakai disk lokal (bukan S3).
Rollback note:
  - `migrate:rollback` menghapus semua perubahan Phase 8.
Red flags:
  - Migrasi gagal di SQLite → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: test skema PASS + migrate/rollback bersih + hanya 4 migrasi + 1 test baru.
Uncertain when: tipe kolom tidak portabel di SQLite.
Escalate when: dibutuhkan perubahan skema transaksi order.
