# Task T3 — `Table.tsx` — density + sortable header

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: `Table.tsx` — density + sortable header [depends: T2]

## OBJECTIVE
Upgrade `Table.tsx` generik: prop `density` (padding/row height), `sortableColumns`, `sort`, `onSort`, `aria-sort` pada `th`; kolom `Aksi` inert; export `TableDensity` type via barrel.

Steps:
1. Write failing test for: sortable header render + onSort + aria-sort
   Test file: `apps/web/src/components/ui/Table.test.tsx`
   Level: unit (component)
   Test intent: Given `Table` dengan `columns=[{key:'name',...},{key:'aksi',...}]`, `sortableColumns=['name']`, `sort={column:'name',direction:'asc'}` / When dirender / Then header "name" punya `aria-sort="ascending"` + panah indikator; klik header "name" memanggil `onSort('name')`; klik header "aksi" TIDAK memanggil `onSort`.
   Exercise through: render `<Table ... />` via @testing-library/react
   Test doubles: `onSort` = jest.fn; tidak mock Table (unit under test)
   Expected RED: prop baru belum ada → `onSort` tidak dipanggil, `aria-sort` absent.
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/ui/Table.test.tsx`
3. Implement sortable header → PASS → refactor → commit.

4. Write failing test for: density prop mengubah padding class
   Test file: `apps/web/src/components/ui/Table.test.tsx`
   Level: unit (component)
   Test intent: Given `Table density="compact"` / When dirender / Then `td`/`tr` memakai class padding compact (lebih kecil dari default); `density="comfortable"` / Then padding lebih besar.
   Exercise through: render `<Table density=... />`
   Test doubles: none
   Expected RED: prop `density` belum ada → tidak ada perubahan class.
5. Run test — verify FAIL: `cd apps/web && npx jest src/components/ui/Table.test.tsx`
6. Implement density paddings + export `TableDensity` di `components/ui/index.ts` → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Table.tsx generic contract", "density toggle", "sort manual via header".

## WHY THIS APPROACH
Complexity: standard
Justification: `Table` adalah satu-satunya komponen tabel yang dipakai 6 halaman; upgrade di satu tempat menurunkan risko duplikasi.

## SANDWICH CONTEXT
[CRITICAL: Table tetap generik; kolom Aksi inert; jangan pecah contract `columns`/`rows`/`rowKey`/`empty` yang sudah ada]
You are implementing density + sortable header untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B, sortable state via props opsional.
Files in scope: `apps/web/src/components/ui/Table.tsx`, `apps/web/src/components/ui/index.ts`, `apps/web/src/components/ui/Table.test.tsx`
Available after: T2 (TableDensity type)
Architecture rule: `aria-sort` di `th` sortable; prop baru opsional agar pemakaian lama tetap valid.
[RESTATE: Table tetap generik; kolom Aksi inert; jangan pecah contract `columns`/`rows`/`rowKey`/`empty` yang sudah ada]

## DELIVERABLE
Given kolom sortable, When klik header, Then onSort dipanggil + aria-sort + indikator tampil.
Given kolom Aksi (non-sortable), When diklik, Then onSort TIDAK dipanggil.
Given density compact/default/comfortable, When dirender, Then padding/row height berbeda sesuai level.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Prop baru opsional; halaman yang belum migrasi tetap render (backward compat).
  - `aria-sort` value `ascending`/`descending`/`none`.
Must-not-have:
  - Menyentuh pipeline/API/dummy.
  - Hardcode kolom domain di dalam Table.
Open question risks:
  - Apakah `EmptyState` empty-path (rows 0) tetap tanpa `<thead>` → diterima (spec).
Rollback note:
  - Revert `Table.tsx`; pemakaian lama tidak terpengaruh.
Red flags:
  - Mengubah signature `columns`/`render` yang dipakai halaman lain → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 2 component test groups PASS, tidak ada perubahan file di luar map.
Uncertain when: RTL tidak menemukan `aria-sort` karena atribut casing.
Escalate when: perlu ubah kontrak `columns` yang memecah halaman lain.
