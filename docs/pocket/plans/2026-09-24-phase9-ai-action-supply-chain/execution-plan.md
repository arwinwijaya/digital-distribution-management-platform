# Phase 9 — AI Action & Supply Chain — Execution Plan

**Date:** 2026-09-24
**Status:** PLANNED (belum ada task yang dieksekusi)
**Spec:** docs/pocket/spec/2026-09-24-phase9-ai-action-supply-chain/phase9-ai-action-supply-chain.md
**Structure:** execution-plan/index.md (6 phases, 18 tasks) + log.json

---

## Plan Summary

Menerapkan Phase 9 (AI Action & Supply Chain) dari spec menjadi 18 task ber-TDD dalam
6 fase. MVP **deterministik di PHP** dengan **approval manusia wajib** untuk setiap mutasi
state bisnis; adapter ML/LLM hanya *seam* opsional dengan fallback yang diuji.

Fase dan dependensi utama:

- **Phase 1 — Data foundation:** migrasi & model action/audit/replenishment/kalibrasi/
  eksperimen, `RecommendationActionService` (draft-first + idempotency), seam adapter
  deterministik, RBAC catalog baru. Semua fase berikut bergantung pada phase ini.
- **Phase 2 — Draft + approval + execute API:** endpoint create draft, approve/reject
  dengan audit append-only, dan execute approval-gated yang memanggil
  `OrderCreationService` / `PromotionService` yang sudah ada.
- **Phase 3 — Replenishment, calibration, experiment:** draft PO dari
  `StockPlanningService` (tetap read-only), approve/execute replenishment, kalibrasi
  forecast deterministik, assignment A/B + revenue-lift.
- **Phase 4 — Frontend action surfaces:** inbox approval, halaman replenishment,
  dashboard eksperimen/revenue-lift.
- **Phase 5 — Parity & wiring:** fixture dummy (`withDummyRead`), `NavItem`, seeder RBAC.
- **Phase 6 — Verifikasi lintas unit:** fallback/guardrail adapter teruji end-to-end dan
  skenario lintas unit (draft→approve→execute, idempotency, audit).

## Execution Overview

Recommended order: selesai Phase 1 → 2 → 3 → 4 → 5 → 6. Di dalam phase, dependensi
dinyatakan per task; task dengan dependensi sama boleh paralel.

```
Phase 1: T1 [prereq] → T2, T3, T4 [parallel after T1]
Phase 2: T5 [depends T2,T4] → T6 [depends T5] → T7, T8 [parallel after T6]
Phase 3: T9 [depends T1,T4] → T10 [depends T9] ; T11 [depends T1,T3] ; T12 [depends T1]  (T9/T11/T12 paralel)
Phase 4: T13 [depends T7,T8] ; T14 [depends T10] ; T15 [depends T12]  (paralel)
Phase 5: T16 [depends T13,T14,T15]
Phase 6: T17 [depends T3,T5..T8] ; T18 [depends all]
```

## Parallelizable Groups

| After | Tasks dapat paralel |
|---|---|
| T1 | T2, T3, T4 |
| T6 | T7, T8 |
| Phase 2 & T1 | T9, T11, T12 |
| T7/T8, T10, T12 | T13, T14, T15 |
| T16 | T17, T18 (T18 menunggu seluruh dependensi) |

## Constraints Reminder

**Boleh disentuh (sesuai spec In-Scope):** migrasi baru additive; model/service/controller/
request baru di `apps/api`; route baru di `routes/api.php` (tambah, tanpa ubah yang lama);
`MenuDefinition::CATALOG` + `RbacMatrixSeeder` (tambah entri); halaman/hook/API client baru
di `apps/web/src`; fixture baru di `apps/web/src/dummy/*`; `NavItem`/`Sidebar`.

**DILARANG disentuh:** `OrderCreationService` inti, `PromotionService` inti,
`OrderController::runApprovalTransaction` (hanya **dipanggil** ulang); skema order/transaksi;
migrasi destruktif; jalur apapun yang memutasi state bisnis tanpa status `approved`;
memory/PII mentah di audit; dependency frontend baru tanpa alasan kuat.

**Test gates:** `cd apps/api && php artisan test`; `cd apps/web && npx jest && npx tsc --noEmit`.
`pint --test` bukan gate (tidak ada di CI).
