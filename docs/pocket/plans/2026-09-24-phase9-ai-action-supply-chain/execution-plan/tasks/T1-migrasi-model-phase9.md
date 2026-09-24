# Task T1 — Migrasi & model Phase 9 (actions, audit, replenishment, calibration, experiments)

**Phase:** 1
**Depends:** —
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Migrasi & model Phase 9

## OBJECTIVE
Buat fondasi data additive untuk seluruh Phase 9: `recommendation_actions`,
`recommendation_action_events` (audit append-only), `replenishment_plans` +
`replenishment_plan_items`, `forecast_calibrations`, `ab_experiments`,
`ab_experiment_assignments`, `revenue_lift_snapshots`, beserta model Eloquent + relasi.

Steps:
1. Write failing test untuk skema + model.
   Test file: `apps/api/tests/Feature/Phase9MigrationTest.php`
   Level: feature
   Test intent: Given `migrate:fresh` / When tabel Phase 9 dibuat / Then tabel ada dengan
   kolom kunci (`recommendation_actions.idempotency_key` unik, `status`, `type`,
   `approved_by`, `approved_at`, `execution_result`, `rejection_reason`);
   `recommendation_action_events` append-only; `replenishment_plans` unik
   (`window_start`,`window_end`,`supplier_id`); `forecast_calibrations` unik
   (`dimension_key`); `ab_experiments` unik (`experiment_key`); `ab_experiment_assignments`
   unik (`experiment_id`,`subject_key`); `revenue_lift_snapshots` menyimpan uplift + status.
   Exercise through: Schema + model create/relation.
   Test doubles: factories untuk Outlet/Product/User/Supplier.
   Expected RED: tabel belum ada → exception/assert gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=Phase9MigrationTest`
3. Implement migrasi + model + factory → PASS → refactor → commit.
4. Write failing test untuk reversible migration: `migrate:rollback --step=N` menghapus
   semua tabel Phase 9 tanpa error.
5. Run test — verify FAIL → PASS → commit.

## REFERENCES LOADED
Spec Phase 9 — DD-1, DD-3, DD-5, DD-6; AC-1, AC-2, AC-4, AC-5, AC-6, AC-10.
`apps/api/database/migrations/2026_09_14_000028_create_recommendation_events_table.php`,
`2026_09_14_000029_create_forecast_actuals_table.php`; `apps/api/app/Models/RecommendationEvent.php`.

## WHY THIS APPROACH
Complexity: medium
Justification: seluruh fase berikut bergantung pada skema ini; dibuat additive dan
reversible mengikuti pola migrasi Phase 3/8 agar aman di SQLite + PostgreSQL.

## SANDWICH CONTEXT
[CRITICAL: additive + reversible; portabel SQLite/PostgreSQL; tanpa mengubah tabel lama]
Files in scope: `apps/api/database/migrations/2026_09_24_*_create_phase9_*_table.php`,
`apps/api/app/Models/{RecommendationAction,RecommendationActionEvent,ReplenishmentPlan,ReplenishmentPlanItem,ForecastCalibration,AbExperiment,AbExperimentAssignment,RevenueLiftSnapshot}.php`,
`apps/api/database/factories/*`, `apps/api/tests/Feature/Phase9MigrationTest.php`.
Available after: Phase 3 migrations (outlets/products/suppliers/users).
Architecture rule: FK `nullOnDelete` bila referensi opsional; `idempotency_key` unik;
`json` untuk payload/metadata.

## DELIVERABLE
- 8 tabel + 8 model dengan relasi + cast json, factory, dan `down()` yang menghapus tabel.
- Tidak ada perubahan destruktif pada tabel lama.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `idempotency_key` unik pada `recommendation_actions`; unik komposit pada plan/assignment/calibration.
  - `recommendation_action_events` append-only (tidak ada update/delete di kode).
  - Migrasi reversible.
Must-not-have:
  - Perubahan pada migrasi/tabel lama.
  - Kolom berindeks yang di-`DROP` saat down (batasi ke `dropIfExists` tabel baru).
Open question risks:
  - A-4: audit disimpan di DB (bukan object storage).
Rollback note:
  - `migrate:rollback` per-step; feature flag mematikan action layer.

## STOP CONDITIONS
Done when: test skema + rollback PASS.
Uncertain when: konflik FK dengan tabel lama.
Escalate when: dibutuhkan tabel baru di luar daftar (mis. supplier PO eksternal).
