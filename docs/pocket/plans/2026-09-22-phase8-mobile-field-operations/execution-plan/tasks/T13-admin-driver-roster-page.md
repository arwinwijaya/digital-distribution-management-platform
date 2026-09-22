# Task T13 — Halaman admin roster driver (`/admin/drivers`) + api + kontrak tabel

**Phase:** 4
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 13: Halaman admin roster driver

## OBJECTIVE
Buat halaman `/admin/drivers` dengan tabel roster (kontrak admin-table: sort, paging,
ringkasan, density), form create/edit, dan modul `api.ts` yang memakai `withDummyRead`.

Steps:
1. Write failing test for: loader + tabel.
   Test file: `apps/web/src/app/admin/drivers/page.test.tsx`
   Level: component
   Test intent: Given mock `listDrivers` mengembalikan 25 baris / When render halaman /
   Then tabel menampilkan baris + `TablePagination` + `TableSummary`; When klik kolom
   "Plat" / Then `listDrivers` dipanggil dengan sort param.
   Exercise through: render page + `@testing-library`.
   Test doubles: mock `@/app/admin/drivers/api`.
   Expected RED: halaman belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/drivers`
3. Implement `api.ts` + `page.tsx` (pakai `admin-table.ts` + `Table*`) → PASS → refactor → commit.

4. Write failing test for: create/edit form.
   Test intent: Given form create / When submit valid / Then `createDriver` dipanggil;
   Given error 422 / Then pesan validasi tampil.
5. Run test — verify FAIL → implement form → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-7; pola `apps/web/src/app/admin/outlets/page.tsx` + `admin-table.ts` + `Table*` (plan `2026-09-17-admin-table-ux`).

## WHY THIS APPROACH
Complexity: medium
Justification: mengikuti pola halaman admin yang sudah ada (kontrak tabel + api module) agar konsisten.

## SANDWICH CONTEXT
[CRITICAL: pakai helper `admin-table.ts` + komponen `Table*`; jangan reinvent sort/paging]
Files in scope: `apps/web/src/app/admin/drivers/page.tsx`, `apps/web/src/app/admin/drivers/api.ts`, test.
Available after: T5 (endpoint).
Architecture rule: `withDummyRead` untuk read; dummy fixture disediakan di T17.

## DELIVERABLE
- `api.ts`: `listDrivers(params)`, `createDriver`, `updateDriver`, `deleteDriver` (guard dummy).
- `page.tsx`: tabel + pagination + summary + density + modal form.
- Kolom: nama driver, kendaraan, plat, wilayah, kapasitas, shift, status ketersediaan.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Kontrak admin-table (sort allowlist, offset paging, meta.total).
  - Loading/error/empty state.
Must-not-have:
  - Fetch langsung tanpa guard dummy.
Open question risks:
  - Field opsional (kapasitas/shift) tampil "-" bila null.
Rollback note:
  - Hapus folder halaman.

## STOP CONDITIONS
Done when: loader + tabel + form PASS; `tsc --noEmit` bersih.
Escalate when: endpoint T5 belum final.
