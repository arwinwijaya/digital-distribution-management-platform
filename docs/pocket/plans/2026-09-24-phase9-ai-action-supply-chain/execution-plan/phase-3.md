# Phase 3 — Replenishment, Forecast Calibration, A/B & Revenue Lift

**Date:** 2026-09-24
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 1 DONE (dan T6 dari Phase 2 untuk pattern approve/execute)
**Contains tasks:** {T9, T10, T11, T12}
**Unlocks next:** Phase 4

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T9:** `ReplenishmentService` generate draft plan dari `StockPlanningService` [depends: T1, T4] → [tasks/T9-replenishment-service-generate.md](tasks/T9-replenishment-service-generate.md)
- **T10:** Replenishment approve/execute (draft PO) [depends: T9, T6] → [tasks/T10-replenishment-approve-execute.md](tasks/T10-replenishment-approve-execute.md)
- **T11:** `ForecastCalibrationService` + persistensi kalibrasi [depends: T1, T3] → [tasks/T11-forecast-calibration-service.md](tasks/T11-forecast-calibration-service.md)
- **T12:** Assignment A/B deterministik + `RevenueLiftService` [depends: T1] → [tasks/T12-ab-assignment-revenue-lift.md](tasks/T12-ab-assignment-revenue-lift.md)

T9, T11, T12 boleh paralel (semuanya hanya bergantung pada T1 + T3/T6). T10 bergantung T9 + T6.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `php artisan test` hijau untuk test baru (`ReplenishmentServiceTest`, `ReplenishmentApproveExecuteTest`, `ForecastCalibrationServiceTest`, `AbAssignmentRevenueLiftTest`)
- `php artisan migrate` + rollback bersih
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT