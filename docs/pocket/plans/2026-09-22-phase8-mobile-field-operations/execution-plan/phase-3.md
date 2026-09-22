# Phase 8 — Mobile Field Operations — PWA Offline Shell (Phase 3 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** None (frontend-only; boleh jalan paralel dengan Phase 2)
**Contains tasks:** {T10, T11, T12}
**Unlocks next:** Phase 6
**Status:** DONE (2026-09-22)

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

**T11 DONE** — `868e7f5`

- `StorageAdapter` interface + `IndexedDbAdapter` (native, no deps) + `LocalStorageAdapter` fallback.
- Queue API: `enqueue(payload)`, `list()`, `remove(id)`, `clear()`, `flushQueue(send)` → `{sent, failed}`.
- Idempotency key: deterministic FNV-1a hash of payload JSON (32-char hex). Stable for identical payloads.
- On flush: successful items removed, failed items retained for retry. Header `Idempotency-Key` sent.
- 8 unit tests pass (in-memory adapter).
- Web suite 504 passed; `tsc --noEmit` clean.

**T12 DONE** — `da5db44`

- `OrderForm` integrates `useOnlineStatus` + queue: offline submit → `enqueue()` with idempotency key.
- Pending count badge: "N pesanan menunggu sinkronisasi (offline)" / "(menyinkronkan...)".
- Auto-flush on `online` event via effect; dummy mode bypasses queue entirely.
- 4 component tests pass (offline enqueue, dummy bypass, auto-flush, online unchanged).
- Web suite 508 passed; `tsc --noEmit` clean.

---

## Phase Completion Gate

Phase 3 gate **PASSED**:
- All 3 tasks DONE (T10, T11, T12).
- `npx jest` → 508 passed.
- `npx tsc --noEmit` clean.
- PWA disabled via `NEXT_PUBLIC_PWA_ENABLED=false` verified no-op.
- Commits: T10 `e6c3bd6`, T11 `868e7f5`, T12 `da5db44`.
- No BLOCKED or NEEDS_CONTEXT.

Hand off to Phase 4.

DONE when ALL of the following:
- Every task in this phase: status DONE
- `npx jest` hijau untuk test service worker/queue/order-form
- `npx tsc --noEmit` bersih
- PWA dapat dinonaktifkan via `NEXT_PUBLIC_PWA_ENABLED=false` tanpa error
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 6 ONLY after this gate passes.
