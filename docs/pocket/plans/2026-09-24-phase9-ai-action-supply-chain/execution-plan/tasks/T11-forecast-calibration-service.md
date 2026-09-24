# Task T11 — `ForecastCalibrationService` + persistensi kalibrasi

**Phase:** 3
**Depends:** T1, T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 11: `ForecastCalibrationService`

## OBJECTIVE
Hitung faktor kalibrasi (bias) deterministik per dimensi dari histori actual/forecast,
persist ke `forecast_calibrations`, dan sediakan metode menerapkan faktor ke hasil
`ForecastService`, dengan fallback netral saat data kurang.

Steps:
1. Write failing unit tests.
   Test file: `apps/api/tests/Unit/ForecastCalibrationServiceTest.php`
   Level: unit
   Test intent: Given actual/forecast pairs ≥ MIN / When calibrate / Then factor stored
   with method_version and calibrated forecast = base × factor; insufficient data → fallback
   true + factor 1.0; deterministic.
   Test doubles: forecast_actuals fixture rows / ForecastService stub.
   Expected RED: service belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ForecastCalibrationServiceTest`
3. Implement + upsert calibration → PASS → commit.
4. Write failing test: faktor diterapkan konsisten pada repeated call (idempotent upsert).
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec AC-5, A-5; `ForecastService.php`; `forecast_actuals` migration; `MeasurementService::decimalToCents`.

## WHY THIS APPROACH
Complexity: medium
Justification: kalibrasi sederhana berbasis rasio dapat diuji deterministik tanpa ML.

## SANDWICH CONTEXT
[CRITICAL: deterministik; fallback netral; jangan ubah signature ForecastService]
Files in scope: `apps/api/app/Services/ForecastCalibrationService.php`, test.
Available after: T1 + T3.
Architecture rule: `dimension_key` unik; method_version; decimal-safe (cents).

## DELIVERABLE
- calibrate + apply(factor) + persist; insufficient-data safe.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: deterministic; decimal-safe; fallback 1.0; method_version.
Must-not-have: mutate ForecastService contract; use floats for money without cents.
Open question risks: A-5 (bias sederhana; seasonality lanjutan out-of-scope).
Rollback note: hapus calibration rows / disable apply.

## STOP CONDITIONS
Done when: calibrate/apply/insufficient/idempotent tests PASS.
Escalate when: butuh dimension granularity baru (mis. per-kategori).
