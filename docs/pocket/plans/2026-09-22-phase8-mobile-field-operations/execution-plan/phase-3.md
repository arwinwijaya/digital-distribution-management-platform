# Phase 8 — Mobile Field Operations — PWA Offline Shell (Phase 3 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** None (frontend-only; boleh jalan paralel dengan Phase 2)
**Contains tasks:** {T10, T11, T12}
**Unlocks next:** Phase 6

---

## Task List

Total: 3 tasks

- **T10:** Service worker + registrasi + halaman offline + indikator Topbar [prereq] → [tasks/T10-service-worker-offline-shell-topbar.md](tasks/T10-service-worker-offline-shell-topbar.md)
- **T11:** Offline order queue (IndexedDB wrapper + flush) [depends: T10] → [tasks/T11-offline-order-queue-indexeddb-flush.md](tasks/T11-offline-order-queue-indexeddb-flush.md)
- **T12:** Integrasi antrean offline ke form order + status UI [depends: T11] → [tasks/T12-offline-queue-order-form-integration.md](tasks/T12-offline-queue-order-form-integration.md)

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
