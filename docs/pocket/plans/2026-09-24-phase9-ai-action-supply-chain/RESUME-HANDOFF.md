# Handoff — Resume Phase 9 (AI Action & Supply Chain)

- **Plan:** docs/pocket/plans/2026-09-24-phase9-ai-action-supply-chain
- **Type:** phased · **Pipeline:** pocket-development · **Spec:** docs/pocket/spec/2026-09-24-phase9-ai-action-supply-chain/phase9-ai-action-supply-chain.md
- **Created:** 2026-09-24  ·  **Last updated:** 2026-09-26  ·  **Baseline SHA:** 2dcd3c7
- **Status at handoff:** IN_PROGRESS — 11/18 done, 7 PENDING

## Progress Snapshot (2026-09-29)

```
DONE (11):  T1(96bf91b) T2(2f68cf9) T3(bc3c456) T4(d66c8bf)
            T5(247c93b)  T6(9db60fb) T7(5878d34) T8(20363ec)
            T9(1de9cda)  T11(f513a1a) T12(0ad6955)

PENDING (7): T10, T13, T14, T15, T16, T17, T18

Reviews:     T1–T4 PASS (4/18)
```

## Remaining Tasks & Dependencies

```
T10  Replenishment approve/execute (draft PO)          Phase 3  ── depends T9,T6           [server]
T13  Halaman inbox approval rekomendasi                Phase 4  ── depends T7,T8           [web]
T14  Halaman replenishment (generate/approve/execute)  Phase 4  ── depends T10             [web]
T15  Dashboard eksperimen + revenue lift               Phase 4  ── depends T12             [web]
T16  Fixture dummy + NavItem + wiring RBAC             Phase 5  ── depends T13,T14,T15    [web+api]
T17  Fallback & guardrail adapter lintas unit          Phase 6  ── depends T3,T5,T6,T7,T8  [test]
T18  Cross-unit integration verification end-to-end    Phase 6  ── depends *all*           [test]
```

## Unblocked After T1–T9 + T11 + T12

Yang sudah terpenuhi (semua DONE):

- **T10:** T9 DONE (1de9cda) & T6 DONE (9db60fb) → **unblocked**.
- **T13:** T7 DONE (5878d34) & T8 DONE (20363ec) → **unblocked**.
- **T15:** T12 DONE (0ad6955) → **unblocked**.
- **T17:** T3(bc3c456) + T5(247c93b) + T6(9db60fb) + T7(5878d34) + T8(20363ec) → **unblocked**.

Masih terblok:

- **T14** — butuh **T10** DONE.
- **T16** — butuh **T13 & T14 & T15** DONE.
- **T18** — butuh **semua** (termasuk T10,T13,T14,T15,T16,T17) DONE.

## Recommended Order

```
Parallel batch A (start now):   T10 + T13 + T15 + T17
Sequential:                     T14 (after T10)
Final batch:                    T16 (after T13 & T14 & T15)
Verification:                   T18 (after T16 & T17)
```

Aturan main sama seperti fase awal: STRICT RED-before-production TDD per
`execution-plan/tasks/T{10,13,14,15,16,17,18}-*.md`; worktree terisolasi per task;
mechanical gate `php artisan test` (API, `APP_ENV=testing`, SQLite) dan
`npx jest && npx tsc --noEmit` (web); re-audit read-only two-stage;
simpan `reviews/T{N}-review.json`; merge `--no-ff` dan `log update --task T{N} DONE --sha <merge_sha>`.
Jangan ubah `log.json` manual; jangan push ke origin.

## Resume Commands (template)

Disesuaikan per task — jalankan sesuai panduan execution-plan.

### Batch A-1: T10 — server

```text
Lanjutkan Phase 9. Status: T1–T9,T11,T12 DONE (T9 merge 1de9cda; log tercatat DONE; reviews T1–T4 PASS).
Kerjakan STRICT RED-before-production T10 (replenishment approve/execute — execution-plan/tasks/T10-replenishment-approve-execute.md)
secara terisolasi: tulis failing test di apps/api/tests/Feature/ReplenishmentApproveExecuteTest.php → verifikasi RED → implement controller/service/routes rbac:supply_chain:edit → PASS (php artisan test --filter=ReplenishmentApproveExecuteTest) → refactor → mechanical gate (php artisan test) → re-audit two-stage read-only dan simpan reviews/T10-review.json → merge --no-ff dan log update --task T10 DONE --sha <merge_sha>. Jangan ubah log.json manual. No push.
```

### Batch A-2: T13 — web inbox

```text
Lanjutkan Phase 9. Status: T1–T9,T11,T12 DONE. T10 boleh dikerjakan paralel.
Kerjakan T13 (inbox approval — execution-plan/tasks/T13-approval-inbox-page.md) secara terisolasi: tulis failing web test src/app/admin/ai-actions/page.test.tsx → RED (npx jest) → implement page.tsx + lib/ai-actions-api.ts → PASS → access test (non-admin guard) → PASS → npx tsc --noEmit clean → mechanical gate → re-audit two-stage → reviews/T13-review.json → merge/log. No push.
```

### Batch A-3: T15 — web experiment dashboard

```text
Kerjakan T15 (dashboard eksperimen — execution-plan/tasks/T15-experiment-dashboard.md) secara terisolasi: failing web test src/app/admin/ai-experiments/page.test.tsx (lift cards, insufficient-data, currency) → RED → implement page + lib/experiments-api.ts → PASS → decimal-safe formatting → PASS → tsc clean → mechanical gate → re-audit → reviews/T15-review.json → merge/log. No push.
```

### Batch A-4: T17 — cross-unit fallback/guardrail

```text
Kerjakan T17 (fallback & guardrail — execution-plan/tasks/T17-adapter-fallback-guardrail.md) secara terisolasi: tulis Phase9AdapterFallbackTest (FakeFailingAdapter, invalid output) → RED (php artisan test --filter=Phase9AdapterFallbackTest) → fix gap minimal di resolver/validator/service (no new schema, no business mutation) → PASS → mechanical gate → re-audit → reviews/T17-review.json → merge/log. No push.
```

### Setelah Batch A: T14 → T16 → T18

```
T14  (needs T10)                                  execution-plan/tasks/T14-replenishment-page.md   web
T16  (needs T13+T14+T15) — dummy/fixtures/RBAC     execution-plan/tasks/T16-dummy-navitem-rbac-wiring.md
T18  (needs all) — integration verification        execution-plan/tasks/T18-cross-unit-integration.md — admin-only final gate; writes closeout.md
```

## Do not include

- `nul` stray (sudah dibersihkan di commit 050de07), push ke origin, atau edit manual `log.json`.
- Jangan tandai plan DONE sebelum `T18` REVIEW_PASS + merge + `closeout.md` ditulis.

## Verifikasi sebelum `closeout.md`

`APP_ENV=testing DB_CONNECTION=sqlite php artisan test` (per-replace pgsql-only), `cd apps/web && npx jest && npx tsc --noEmit`, dan rollback migrasi yang diperkenalkan Phase 9.
