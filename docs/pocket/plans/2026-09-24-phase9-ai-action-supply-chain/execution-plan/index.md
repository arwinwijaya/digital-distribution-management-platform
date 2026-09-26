# Phase 9 — AI Action & Supply Chain — Execution Plan (Index)

**Date:** 2026-09-24
**Status:** IN_PROGRESS — Phase 1 (T1–T4) DONE · Phase 2 (T5–T8) DONE · Phase 3 partial (T9, T11, T12 DONE; T10 PENDING) · Phase 4–6 PENDING
**Spec:** docs/pocket/spec/2026-09-24-phase9-ai-action-supply-chain/phase9-ai-action-supply-chain.md
**Plan:** ../execution-plan.md · **Log:** ../log.json

---

## Phases

| # | Phase | Tasks | File |
|---|---|---|---|
| 1 | Data foundation (action, audit, replenishment, calibration, experiment) | T1–T4 — **DONE** (T1 `96bf91b`, T2 `2f68cf9`, T3 `bc3c456`, T4 `d66c8bf`) | [phase-1.md](phase-1.md) |
| 2 | Draft + approval + execute API | T5–T8 | [phase-2.md](phase-2.md) |
| 3 | Replenishment, forecast calibration, A/B & revenue lift | T9–T12 | [phase-3.md](phase-3.md) |
| 4 | Frontend action surfaces | T13–T15 | [phase-4.md](phase-4.md) |
| 5 | Parity dummy + RBAC wiring | T16 | [phase-5.md](phase-5.md) |
| 6 | Cross-unit integration verification | T17–T18 | [phase-6.md](phase-6.md) |

## Recommended Order

```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6
(T1 → T2/T3/T4) → (T5 → T6 → T7/T8) → (T9 → T10 | T11 | T12) → (T13 | T14 | T15) → T16 → (T17 | T18)
```

## Task List

Total: 18 tasks · dependency order bersifat rekomendasi — pocket-development yang menegakkan.

- **Phase 1**
  - **T1:** Migrasi & model Phase 9 (actions, audit, replenishment, calibration, experiments) [prereq]
  - **T2:** `RecommendationActionService` draft-first + idempotency [depends: T1]
  - **T3:** Seam adapter `RecommendationModelAdapter` + deterministik + guardrail validator [depends: T1]
  - **T4:** RBAC catalog + seeder (`ai_actions`, `supply_chain`) [depends: T1]
- **Phase 2**
  - **T5:** Endpoint create draft action (`POST /admin/recommendation-actions`) [depends: T2, T4]
  - **T6:** Approve/reject + audit append-only [depends: T5]
  - **T7:** Execute `draft_order` via `OrderCreationService` [depends: T6]
  - **T8:** Execute `draft_campaign` via `PromotionService` [depends: T6]
- **Phase 3**
  - **T9:** `ReplenishmentService` generate draft plan dari `StockPlanningService` [depends: T1, T4]
  - **T10:** Replenishment approve/execute (draft PO) [depends: T9, T6]
  - **T11:** `ForecastCalibrationService` + persistensi kalibrasi [depends: T1, T3]
  - **T12:** Assignment A/B deterministik + `RevenueLiftService` [depends: T1]
- **Phase 4**
  - **T13:** Halaman inbox approval rekomendasi [depends: T7, T8]
  - **T14:** Halaman replenishment (generate/approve/execute) [depends: T10]
  - **T15:** Dashboard eksperimen + revenue lift [depends: T12]
- **Phase 5**
  - **T16:** Fixture dummy + `NavItem` + wiring RBAC [depends: T13, T14, T15]
- **Phase 6**
  - **T17:** Fallback & guardrail adapter lintas unit [depends: T3, T5–T8] [test-risk]
  - **T18:** Cross-unit integration verification end-to-end [depends: semua] [test-risk]

## Parallelizable Groups

- Setelah T1: **T2, T3, T4** paralel.
- Setelah T6: **T7, T8** paralel.
- Setelah T1 (dan/atau setelah T6): **T9, T11, T12** paralel.
- Setelah T7+T8, T10, T12: **T13, T14, T15** paralel.
- Setelah T16: **T17, T18** (T18 menunggu seluruh dependensinya).

## Constraints Reminder

MVP deterministik di PHP; **setiap mutasi state bisnis wajib melewati status `approved`**
oleh admin; adapter ML/LLM opsional dengan fallback yang **tidak pernah** jadi prasyarat AC;
migrasi additive + reversible; envelope `{status,data}`; idempotency di setiap endpoint
mutasi; parity dummy `withDummyRead`; RBAC `auth:api` + `rbac:<menu>:<level>`.
Detail lengkap: [../execution-plan.md](../execution-plan.md).
