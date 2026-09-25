# Phase 1 — Data Foundation (Action, Audit, Replenishment, Calibration, Experiment)

**Date:** 2026-09-24
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T3, T4}
**Unlocks next:** Phase 2
**Status:** **DONE** — T1 `96bf91b`, T2 `2f68cf9`, T3 `bc3c456`, T4 `d66c8bf`

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T1:** Migrasi & model Phase 9 (actions, audit, replenishment, calibration, experiments) [prereq] → [tasks/T1-migrasi-model-phase9.md](tasks/T1-migrasi-model-phase9.md)
- **T2:** `RecommendationActionService` draft-first + idempotency [depends: T1] → [tasks/T2-recommendation-action-service.md](tasks/T2-recommendation-action-service.md)
- **T3:** Seam adapter `RecommendationModelAdapter` + deterministik + guardrail validator [depends: T1] → [tasks/T3-recommendation-model-adapter.md](tasks/T3-recommendation-model-adapter.md)
- **T4:** RBAC catalog + seeder (`ai_actions`, `supply_chain`) [depends: T1] → [tasks/T4-rbac-catalog-seeder-ai-actions.md](tasks/T4-rbac-catalog-seeder-ai-actions.md)

T2, T3, T4 boleh paralel setelah T1 selesai.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `php artisan test` hijau untuk test baru (`Phase9MigrationTest`, `RecommendationActionServiceTest`, `RecommendationModelAdapterTest`, `RbacAiActionsCatalogTest`)
- `php artisan migrate` + `migrate:rollback --step=4` (atau jumlah migrasi Phase 9) berjalan bersih di SQLite
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT