# Phase 8 — Mobile Field Operations — Frontend Field Surfaces (Phase 4 of 6)

**Date:** 2026-09-22
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 2 (backend field-ops API)
**Contains tasks:** {T13, T14, T15, T16}
**Unlocks next:** Phase 5

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T13:** Halaman admin roster driver (`/admin/drivers`) + api + kontrak tabel [depends: T5] → [tasks/T13-admin-driver-roster-page.md](tasks/T13-admin-driver-roster-page.md)
- **T14:** UI check-in/out kunjungan sales + geolokasi [depends: T6] → [tasks/T14-sales-visit-checkin-ui.md](tasks/T14-sales-visit-checkin-ui.md)
- **T15:** UI capture PoD driver (kamera + canvas tanda tangan) [depends: T8] → [tasks/T15-driver-pod-capture-ui.md](tasks/T15-driver-pod-capture-ui.md)
- **T16:** Halaman live tracking admin (`GeoMap` + polling) [depends: T7] → [tasks/T16-admin-live-tracking-page.md](tasks/T16-admin-live-tracking-page.md)

T13, T14, T15, T16 boleh paralel.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `npx jest` hijau untuk semua halaman baru + `npx tsc --noEmit` bersih
- Halaman roster memakai kontrak admin-table (sort/paging/ringkasan)
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 5 ONLY after this gate passes.
