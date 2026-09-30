# Closeout — 2026-09-30-login-outlet-first-appeal

- **Plan:** docs/pocket/plans/2026-09-30-login-outlet-first-appeal
- **Type:** flat
- **Started:** 2026-09-30  ·  **Closed:** 2026-09-30
- **Baseline SHA:** 6dee0cdf46e30dc4ef22b9682ecdf5b72feb0d0d  ·  **Final SHA:** 6a541e610fc49f5102a3ccc5747aa99cd3c88177
- **Result:** CLOSED — all phases DONE, all reviewable tasks REVIEW_PASS

## Phases

### Phase 1 — execution-plan/index.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T1 | Extend LoginForm with backward-compatible ctaLabel prop | 35f829838ba5f8914840204eded66914c590b34d | REVIEW_PASS |
| T2 | Outlet-first hero + form-first layout on /login | 6a541e610fc49f5102a3ccc5747aa99cd3c88177 | REVIEW_PASS |

_SHA range: 6dee0cdf46e30dc4ef22b9682ecdf5b72feb0d0d..6a541e610fc49f5102a3ccc5747aa99cd3c88177_

## Carried Forward

- **T1** (strength): ctaLabel optional with default 'Masuk' preserves backward compatibility for marketplace and other consumers; request/auth/error/loading contract fully locked.
- **T2** (strength): Outlet-first hero (story + 3 benefits), ability-framed honest copy, form-first mobile, responsive desktop contract, subtle demo footer, session/stale-token handling, cross-unit submit, and role redirect/redirect-param matrix all preserved and tested.
- **Note:** Visual viewport behavior cannot be proven by jsdom unit layout; a manual visual/E2E check on mobile/desktop is a non-blocking follow-up.

## Skipped Tasks

_None_
