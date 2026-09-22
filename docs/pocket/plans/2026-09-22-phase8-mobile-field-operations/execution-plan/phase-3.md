# Phase 8 — Mobile Field Operations — PWA Offline Shell (Phase 3 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** None (frontend-only; boleh jalan paralel dengan Phase 2)
**Contains tasks:** {T10, T11, T12}
**Unlocks next:** Phase 6
**Status:** IN_PROGRESS (T10 DONE, 2026-09-22)

---

## Status

**T10 DONE** — `e6c3bd6`

- `sw.js` precache: `/`, `/offline`, icons. Fetch: skip `/api/`, network-first nav, cache-first static, offline fallback `/offline`.
- `registerServiceWorker()` idempotent, guards `NEXT_PUBLIC_PWA_ENABLED`, SSR-safe (`typeof navigator`).
- `useOnlineStatus()` hook reads `navigator.onLine` + window `online`/`offline` events, cleans up listeners.
- Topbar badge: green "Online" / red "Offline" (extends existing tests).
- `/offline` page minimal, dependency-free.
- `PwaBootstrap` registered in root layout.
- Rollback: set `NEXT_PUBLIC_PWA_ENABLED=false` → registration no-op, delete `sw.js`.
- Web suite 496 passed; `tsc --noEmit` clean; backend 592 passed.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `npx jest` hijau untuk test service worker/queue/order-form
- `npx tsc --noEmit` bersih
- PWA dapat dinonaktifkan via `NEXT_PUBLIC_PWA_ENABLED=false` tanpa error
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 6 ONLY after this gate passes.
