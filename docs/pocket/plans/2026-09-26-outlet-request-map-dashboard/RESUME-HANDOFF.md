# Handoff — Resume Remaining Work

Feature: Outlet Request Map Dashboard (`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md`)
Plan: `docs/pocket/plans/2026-09-26-outlet-request-map-dashboard/` (packet files under `execution-plan/tasks/`, progress in `log.json`, reviews in `reviews/`)

## State when stopped

- Phases 1 and 2 are DONE (T1–T8); T8 DONE is recorded in `log.json` with merge SHA `a800720`.
- T9–T11 remain WAITING. The PKT contract forbids starting T10/T11 before T9.
- T9 implementer was stopped mid-run with work **partially complete and uncommitted on purpose**.

T9 checkpoint (worktree `.worktree/T9`, branch `task/T9`, baseline merge `a800720`):

- Four commits already exist on `task/T9` (`a800720..HEAD`): `e10c7f9` filter chips → `e075679` empty states → `d6f6eef` error classification/retry/role gate/freshness → `d06f837` drawer wiring.
- Uncommitted changes still present in the same worktree: `apps/web/src/app/data-intelligence/page.test.tsx` and `apps/web/src/app/data-intelligence/page.tsx`. Do not discard them; verify, finish, and commit the final state.
- Scope on T9 must stay limited to those two files.

## Resume command (jalankan ini)

Lanjutkan dan selesaikan T9, lalu lanjutkan T10, T11, dan final gate:

```text
Lanjutkan sisa plan outlet request map dashboard. Status: T1–T8 DONE (T8 merge a800720, tercatat DONE di log.json).
Worktree T9 masih hidup di .worktree/T9 pada branch task/T9 dengan baseline a800720 dan berisi 4 commit (e10c7f9, e075679, d6f6eef, d06f837)
plus perubahan belum di-commit di page.tsx dan page.test.tsx — JANGAN reset; verifikasi, selesaikan, lalu commit hasil akhirnya.
Aturan main: STRICT RED-before-production TDD sesuai pocket packet phases
docs/pocket/plans/2026-09-26-outlet-request-map-dashboard/execution-plan/tasks/T9-frontend-page-filter-chips-empty-states-error-handling-drawer-wiring.md;
setelah implementasi T9: mechanical gate (git branch --show-current == task/T9,
`cd apps/web && npx jest src/app/data-intelligence/page.test.tsx`, plus `npx tsc --noEmit`),
lalu re-audit read-only two-stage per references/two-stage-review.md
dan simpan reviews/T9-review.json; merge dengan `git merge --no-ff`
dan update log hanya lewat CLI log update --task T9 DONE --sha <merge_sha>.
Baru setelah T9 REVIEW_PASS + merge + DONE: jalankan T10 lalu T11 secara berurutan sesuai dependensi
(T10 depends T9; T11 depends T3,T9),
gunakan worktree terisolasi per task, jangan ubah log.json manual, dan jangan hapus/stash perubahan yang belum di-commit.
```

## Do not include in the delegated work

- `M apps/api/app/Http/Controllers/ProductController.php`, `M apps/api/tests/Feature/ProductTest.php`,
  `M docs/pocket/plans/2026-09-26-outlet-request-map-dashboard/log.json`,
  untracked `docs/pocket/plans/2026-09-26-barang-menu-clarity/`,
  untracked `docs/pocket/spec/2026-09-26-barang-menu-clarity/`,
  stray `nul`, and the existing `stash@{0}` product change are unrelated/concurrent items — leave untouched.
- No push to origin.

## Remaining acceptance anchor for T9

- Chips: real `<button>`s with `aria-pressed`; default `{New,Confirmed}`+30d; empty status selection renders no-request state and never silently reverts.
- Empty-state precedence: no snapshot → no geographic section → filtered orders>0 with all coords invalid → filtered orders=0 (exact required strings).
- Production `outlets_without_daily_detail` in narrow periods, disclosed to the admin.
- Error classification: 5xx/network retry with cleared data (no stale snapshot); 403 no retry; 401 token cleared; non-admin denied BEFORE any dummy read; freshness label uses `window.end (Asia/Jakarta)` + version.
- Drawer wiring receives the frozen opening filter; banner appears when the map filter diverges.
- Unknown deep-link status/period falls back safely to `{New,Confirmed}`+30d.
