# EXECUTION PLAN — Admin Table Readability (Paging, Sort Terbaru, Ringkasan, Kepadatan)

**Date:** 2026-09-17
**Spec:** docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
**Status:** approved
**Total tasks:** 17

---

## Execution Overview

### Recommended Order
```
T1, T2 (parallel) → T3 → T4
T1 → T5, T6, T7, T8, T9, T10 (parallel)
T4 + T5..T10 → T11, T12, T13, T14, T15, T16 (parallel)
T11..T16 → T17
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism/sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T2 | (start) |
| Group B | T5, T6, T7, T8, T9, T10 | T1 completes |
| Group C | T11, T12, T13, T14, T15, T16 | T4 + each task's backend controller completes |

### Constraints Reminder
**Architecture:**
- Boleh disentuh: `components/ui/Table.tsx` + helper domain table; `app/admin/*/page.tsx` + `api.ts`; controller Laravel admin (sort + total + orderBy); `dummy/*` path.
- **TIDAK boleh** disentuh: pipeline `Order`/`Invoice`/`Payment`, `Auth`/JWT, schema/migrasi DB.
- `Table` tetap generik dan dipakai semua halaman; API tetap cursor/limit (tambahan param `sort`/`order`/`total`).
- Sort backend **hanya via allowlist** per-controller; invalid → fallback diam `created_at DESC, id DESC` (HTTP 200, bukan 422/500).
- Cursor = **offset** seragam (`cursor=0`, `limit`, `2*limit`…); `promotions` & `sales-performance` dikonversi dari id-based cursor.
- Nulls-last dua arah via ekspresi portabel `ORDER BY (<col> IS NULL) ASC, <col> <dir>, id DESC` (identik di PostgreSQL runtime dan SQLite in-memory test).

**Out-of-scope (no task may touch):** Export CSV/Excel, bulk action, column-visibility toggle, infinite scroll, filter/pencarian baru.

**Assumptions at risk:**
- `timestamps()` ada di semua model (Outlet/Product/User/Order/Promotion). Bila tidak → fallback `id DESC`, kolom render `—` (tidak crash).
- `SalesPerformanceService::calculatePerformance` tetap dipanggil per-user; `achievement` sort client-side.

**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

**Commit message convention (conventional commits):**
- T1 → `feat(api): add ListQuery sort/total helper`
- T2 → `feat(web): add admin-table sort/paginate/format helpers`
- T3 → `feat(web): add sortable header + density to Table`
- T4 → `feat(web): add TablePagination/TableSummary/TableDensityToggle`
- T5–T10 → `feat(api): add sort/total/cursor to <controller> list`
- T11–T16 → `feat(web): add sort/paging/summary/density to <page>`
- T17 → `test(web): admin table integration + dummy parity`

### File Structure Map

```
Rule: backend-list-query (shared helper)
  Create: apps/api/app/Support/ListQuery.php           (created by: T1)
  Test:   apps/api/tests/Unit/ListQueryTest.php        (created by: T1)

Rule: frontend-table-helpers (shared helper)
  Create: apps/web/src/lib/admin-table.ts              (created by: T2)
  Test:   apps/web/src/lib/admin-table.test.ts         (created by: T2)

Rule: table-generic-component
  Modify: apps/web/src/components/ui/Table.tsx
  Modify: apps/web/src/components/ui/index.ts
  Test:   apps/web/src/components/ui/Table.test.tsx    (created by: T3)

Rule: table-controls
  Create: apps/web/src/components/ui/TablePagination.tsx   (created by: T4)
  Create: apps/web/src/components/ui/TableSummary.tsx      (created by: T4)
  Create: apps/web/src/components/ui/TableDensityToggle.tsx(created by: T4)
  Create: apps/web/src/hooks/useTableDensity.ts            (created by: T4)
  Modify: apps/web/src/components/ui/index.ts
  Test:   apps/web/src/components/ui/TablePagination.test.tsx    (created by: T4)
  Test:   apps/web/src/components/ui/TableSummary.test.tsx       (created by: T4)
  Test:   apps/web/src/components/ui/TableDensityToggle.test.tsx (created by: T4)
  Test:   apps/web/src/hooks/useTableDensity.test.ts             (created by: T4)

Rule: outlets-list-api
  Modify: apps/api/app/Http/Controllers/AdminOutletController.php
  Test:   apps/api/tests/Feature/AdminOutletTest.php

Rule: users-list-api
  Modify: apps/api/app/Http/Controllers/UserRoleController.php
  Test:   apps/api/tests/Feature/RoleManagementTest.php

Rule: promotions-list-api
  Modify: apps/api/app/Http/Controllers/PromotionController.php
  Test:   apps/api/tests/Feature/PromotionTest.php

Rule: orders-list-api
  Modify: apps/api/app/Http/Controllers/OrderController.php
  Test:   apps/api/tests/Feature/OrderTest.php

Rule: products-list-api
  Modify: apps/api/app/Http/Controllers/ProductController.php
  Test:   apps/api/tests/Feature/ProductTest.php

Rule: sales-performance-list-api
  Modify: apps/api/app/Http/Controllers/SalesPerformanceController.php
  Test:   apps/api/tests/Feature/SalesPerformanceTest.php

Rule: outlets-page
  Modify: apps/web/src/app/admin/outlets/page.tsx
  Modify: apps/web/src/app/admin/outlets/api.ts
  Test:   apps/web/src/app/admin/outlets/page.test.tsx

Rule: products-page
  Modify: apps/web/src/app/admin/products/page.tsx
  Modify: apps/web/src/app/admin/products/api.ts
  Test:   apps/web/src/app/admin/products/page.test.tsx

Rule: users-page
  Modify: apps/web/src/app/admin/users/page.tsx
  Modify: apps/web/src/app/admin/users/api.ts
  Test:   apps/web/src/app/admin/users/page.test.tsx

Rule: promotions-page
  Modify: apps/web/src/app/admin/promotions/page.tsx
  Modify: apps/web/src/app/admin/promotions/api.ts
  Test:   apps/web/src/app/admin/promotions/page.test.tsx

Rule: sales-performance-page
  Modify: apps/web/src/app/admin/sales-performance/page.tsx
  Modify: apps/web/src/app/admin/sales-performance/api.ts
  Test:   apps/web/src/app/admin/sales-performance/page.test.tsx

Rule: orders-page
  Modify: apps/web/src/app/admin/orders/page.tsx
  Test:   apps/web/src/app/admin/orders/page.test.tsx   (created by: T16)

Rule: admin-table-integration
  Create: apps/web/src/__tests__/admin-table-integration.test.tsx   (created by: T17)
```

---

## Pocket Packets

---

### Task 1: Backend `ListQuery` sort/total helper [prereq]

## OBJECTIVE
Buat helper domain murni `apps/api/app/Support/ListQuery.php` yang memusatkan logika sort-allowlist, order normalisasi, ekspresi nulls-last portabel, dan offset/`meta` — dipakai 6 controller.

Steps:
1. Write failing test for: resolveSort allowlist accept + invalid fallback
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given allowlist `['created_at','name','id']` / When `resolveSort` dipanggil dengan `('name','asc')` / Then return `['name','asc']`; dengan `('__proto__','desc')` / Then return `['created_at','desc']` (default); dengan `('name','DROP')` / Then order default `desc`.
   Exercise through: `ListQuery::resolveSort()` (public static)
   Test doubles: none (pure function)
   Expected RED: `ListQuery` class belum ada → `Error: Class "App\Support\ListQuery" not found`.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
3. Implement `ListQuery::resolveSort` + `normalizeOrder` → verify PASS → refactor while green → commit.

4. Write failing test for: rawOrder nulls-last portabel
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given column `created_at`, order `desc` / When `rawOrder` dipanggil / Then return string `(created_at IS NULL) ASC, created_at DESC, id DESC`; order `asc` / Then `(created_at IS NULL) ASC, created_at ASC, id DESC`.
   Exercise through: `ListQuery::rawOrder()`
   Test doubles: none
   Expected RED: method belum ada → `Error: Call to undefined method`.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
6. Implement `rawOrder` → PASS → refactor → commit.

7. Write failing test for: offset clamp + meta shape
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given `offset(null,15)` / Then `0`; `offset(45,15)` / Then `45`; `offset(-5,15)` / Then `0`. Given `meta(48, ['active'=>32,'inactive'=>16])` / Then `['total'=>48,'summary'=>['active'=>32,'inactive'=>16]]`; `meta(12, [])` / Then `summary` key absent.
   Exercise through: `ListQuery::offset()`, `ListQuery::meta()`
   Test doubles: none
   Expected RED: methods belum ada → `Error: Call to undefined method`.
8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
9. Implement `offset` + `meta` → PASS → refactor → commit.

10. Write failing test for: default caller-supplied (untuk endpoint publik)
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given allowlist `['created_at','name','id']` + default caller `('id','asc')` / When `resolveSort($allowlist, '__proto__', 'desc', 'id', 'asc')` / Then `['id','asc']` (fallback ke default caller, BUKAN `created_at desc`); tanpa argumen default / Then `['created_at','desc']` (default global).
   Exercise through: `ListQuery::resolveSort()`
   Test doubles: none
   Expected RED: param default caller belum didukung → selalu `['created_at','desc']`.
11. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
12. Implement default opsional → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller", "Nulls-last dua arah", "Kontrak cursor", "Kontrak total", "Architecture Constraints".

## WHY THIS APPROACH
Complexity: lightweight
Justification: Shared Helper Pattern — 6 controller butuh resolusi sort + total + nulls-last yang identik. Tanpa helper, tiap controller menulis salinan logika sendiri dan tidak pernah digabung.

## SANDWICH CONTEXT
[CRITICAL: sort hanya via allowlist; invalid → fallback `created_at DESC`; jangan sentuh guard/auth/migrasi]
You are implementing backend sort/total helper untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B (Full Paging), offset cursor seragam, top-level `meta.total`.
Files in scope: `apps/api/app/Support/ListQuery.php`, `apps/api/tests/Unit/ListQueryTest.php`
Available after: none (prereq)
Architecture rule: nulls-last portabel `(col IS NULL) ASC, col DIR, id DESC` agar identik di SQLite (test) & PostgreSQL (runtime).
[RESTATE: sort hanya via allowlist; invalid → fallback `created_at DESC`; jangan sentuh guard/auth/migrasi]

## DELIVERABLE
Given allowlist valid + sort valid, When resolveSort dipanggil, Then column+order valid dikembalikan.
Given default caller disuplai, When sort invalid, Then fallback ke default caller (bukan global).
Given sort/order invalid/berbahaya, When resolveSort dipanggil, Then fallback diam `created_at DESC` (no throw).
Given column + order, When rawOrder dipanggil, Then ekspresi nulls-last portabel (id DESC tiebreak).
Given cursor negatif/null, When offset dipanggil, Then clamp ke 0.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Allowlist divalidasi ketat; kolom selain allowlist ditolak → default.
  - `order` hanya `asc`|`desc` (case-insensitive), selain itu `desc`.
  - `rawOrder` memakai kolom yang sudah lolos allowlist (no injection).
Must-not-have:
  - Menyentuh controller/route/schema/migrasi apapun.
Open question risks:
  - SQLite mendukung `(col IS NULL) ASC` → assumed ya (ekspresi boolean). Jika gagal di CI → report NEEDS_CONTEXT.
Rollback note:
  - Hapus `app/Support/ListQuery.php` + test; controller belum memakainya.
Red flags:
  - Mengubah perilaku endpoint yang ada → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 4 unit test groups PASS (allowlist, rawOrder, offset/meta, default caller), hanya 1 file source + 1 file test baru.
Uncertain when: SQLite menolak ekspresi boolean di ORDER BY.
Escalate when: dibutuhkan perubahan schema/guard.

---

### Task 2: Frontend `admin-table.ts` helper [prereq]

## OBJECTIVE
Buat `apps/web/src/lib/admin-table.ts` — helper murni: mirror allowlist, `normalizeSort`, `toggleSort`, `compareRows` (nulls-last dua arah + id tiebreak), `paginate`, `formatDateTime`, `buildCountLabel`, type `TableDensity`.

Steps:
1. Write failing test for: normalizeSort + allowlist mirror
   Test file: `apps/web/src/lib/admin-table.test.ts`
   Level: unit
   Test intent: Given `SORT_ALLOWLISTS.outlets` / When `normalizeSort(allowlist,'name','asc')` / Then `{sort:'name',order:'asc'}`; `normalizeSort(allowlist,'__proto__','desc')` / Then `{sort:'created_at',order:'desc'}`; `normalizeSort(allowlist,'name','DROP')` / Then `{sort:'name',order:'desc'}`.
   Exercise through: `normalizeSort()`, `SORT_ALLOWLISTS`
   Test doubles: none
   Expected RED: module belum ada → import error.
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/admin-table.test.ts`
3. Implement → PASS → refactor → commit.

4. Write failing test for: compareRows nulls-last dua arah + id tiebreak
   Test file: `apps/web/src/lib/admin-table.test.ts`
   Level: unit
   Test intent: Given rows `[{id:1,created_at:null},{id:2,created_at:'2026-01-01'},{id:3,created_at:'2026-02-01'}]` / When sort `created_at desc` / Then order id `3,2,1` (null last); sort `created_at asc` / Then `2,3,1` (null last).
   Exercise through: `compareRows()`
   Test doubles: none
   Expected RED: `compareRows` belum diekspor → undefined is not a function.
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/admin-table.test.ts`
6. Implement `compareRows` → PASS → refactor → commit.

7. Write failing test for: paginate + formatDateTime + buildCountLabel
   Test file: `apps/web/src/lib/admin-table.test.ts`
   Level: unit
   Test intent: Given 48 items, limit 15, cursor 30 / When `paginate` / Then `{page:[15 items], total:48, hasMore:true, nextCursor:45}`. Given `formatDateTime('2026-09-17T10:00:00+07:00')` / Then string berisi "2026" (bukan ISO mentah); `formatDateTime(null)` / Then `—`. Given `buildCountLabel({total:48,cursor:0,limit:15})` / Then `"Halaman 1 dari 4 · 48 data"`; `buildCountLabel({total:undefined,cursor:15,limit:15,hasMore:true})` / Then bentuk estimasi tanpa klaim total.
   Exercise through: `paginate()`, `formatDateTime()`, `buildCountLabel()`
   Test doubles: none (Intl asli di Node ≥ 18)
   Expected RED: fungsi belum diekspor → undefined is not a function.
8. Run test — verify FAIL: `cd apps/web && npx jest src/lib/admin-table.test.ts`
9. Implement → PASS → refactor → commit.

10. Write failing test for: toggleSort direction + new-column default
   Test file: `apps/web/src/lib/admin-table.test.ts`
   Level: unit
   Test intent: Given current sort `{column:'name',order:'desc'}` / When `toggleSort(current,'name')` / Then `{column:'name',order:'asc'}` (balik arah pada klik kedua); given current `{column:'created_at',order:'desc'}` / When `toggleSort(current,'email')` / Then `{column:'email',order:'desc'}` (kolom baru mulai desc); given current `{column:'name',order:'asc'}` / When `toggleSort(current,'name')` / Then `{column:'name',order:'desc'}`.
   Exercise through: `toggleSort()`
   Test doubles: none
   Expected RED: `toggleSort` belum diekspor → undefined is not a function.
11. Run test — verify FAIL: `cd apps/web && npx jest src/lib/admin-table.test.ts`
12. Implement `toggleSort` → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller", "Nulls-last dua arah", "Table.tsx generic contract", "Kolom created_at/updated_at", "paging label posisi".

## WHY THIS APPROACH
Complexity: lightweight
Justification: Shared Helper Pattern — 6 dummy branch `listDummy*` + 6 page komponen butuh comparator/sort/paginate/format identik. Tanpa helper, tiap page menulis copy sendiri.

## SANDWICH CONTEXT
[CRITICAL: Table tetap generik dipakai semua halaman; dummy path tetap zero-network dan lolos guard]
You are implementing frontend table helpers untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B (Full Paging), allowlist mirror = server, nulls-last dua arah.
Files in scope: `apps/web/src/lib/admin-table.ts`, `apps/web/src/lib/admin-table.test.ts`
Available after: none (prereq)
Architecture rule: comparator nulls-last dua arah + id DESC tiebreak; label fallback estimasi bila total absen.
[RESTATE: Table tetap generik dipakai semua halaman; dummy path tetap zero-network dan lolos guard]

## DELIVERABLE
Given allowlist + sort/order, When normalizeSort dipanggil, Then hasil valid / fallback default (no throw).
Given klik header kedua kali, When toggleSort dipanggil, Then arah berbalik (desc→asc→desc).
Given rows dengan timestamp null + non-null, When compareRows sort asc/desc, Then null selalu terakhir, id tiebreak.
Given data + cursor + limit, When paginate dipanggil, Then page/total/hasMore/nextCursor benar.
Given value tanggal/null, When formatDateTime dipanggil, Then lokal terformat / "—".

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `SORT_ALLOWLISTS` mirror persis allowlist backend (satu sumber kebenaran didokumentasikan).
  - `TableDensity = 'compact' | 'default' | 'comfortable'`.
  - `toggleSort` = satu-satunya sumber logika arah sort (klik kedua membalik arah).
Must-not-have:
  - Import React/komponen — file murni (testable tanpa jsdom di luar label).
  - Duplikasi format tanggal yang sudah ada di `lib` lain (reuse bila ada helper existing).
Open question risks:
  - Helper tanggal existing di `lib`? → jika ada, prefer reuse; report via DONE_WITH_CONCERNS bila menambah duplikat.
Rollback note:
  - Hapus file + test; belum dipakai komponen lain.
Red flags:
  - Allowlist tidak sinkron dengan backend → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 4 unit test groups PASS (allowlist, compareRows, paginate/format/label, toggleSort); file murni tanpa import React.
Uncertain when: Node/Intl tidak mendukung `id-ID`.
Escalate when: butuh perubahan kontrak yang menyentuh pipeline.

---

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

---

### Task 4: Table controls (Pagination/Summary/Density toggle + hook) [depends: T2, T3]

## OBJECTIVE
Buat komponen presentasional + hook: `TablePagination.tsx` (label + prev/next + jump), `TableSummary.tsx` (strip total+breakdown), `TableDensityToggle.tsx`, `useTableDensity.ts` (localStorage `admin:table-density`, baca di useEffect). Export semua via barrel.

Steps:
1. Write failing test for: TablePagination label + prev/next + jump
   Test file: `apps/web/src/components/ui/TablePagination.test.tsx`
   Level: unit (component)
   Test intent: Given `{cursor:0, limit:15, total:48}` / When dirender / Then label `"Halaman 1 dari 4 · 48 data"` + tombol 1..4 tampil; klik tombol "3" memanggil `onPageChange(2)` (index 0-based); `{cursor:45,...}` (halaman 4) / Then "Berikutnya" disabled; `{total:undefined, hasMore:true, cursor:15}` / Then label estimasi tanpa angka total palsu.
   Exercise through: render `<TablePagination />`
   Test doubles: `onPageChange` = jest.fn
   Expected RED: komponen belum ada → import error.
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/ui/TablePagination.test.tsx`
3. Implement → PASS → refactor → commit.

4. Write failing test for: TableSummary strip + zero state
   Test file: `apps/web/src/components/ui/TableSummary.test.tsx`
   Level: unit (component)
   Test intent: Given `{total:48, breakdown:[{label:'aktif',value:32},{label:'nonaktif',value:16}], noun:'outlet'}` / When dirender / Then text "48 outlet · 32 aktif · 16 nonaktif"; `{total:0}` / Then "0" (bukan blank).
   Exercise through: render `<TableSummary />`
   Test doubles: none
   Expected RED: komponen belum ada → import error.
5. Run test — verify FAIL: `cd apps/web && npx jest src/components/ui/TableSummary.test.tsx`
6. Implement → PASS → refactor → commit.

7. Write failing test for: TableDensityToggle + useTableDensity persist
   Test file: `apps/web/src/components/ui/TableDensityToggle.test.tsx` + `apps/web/src/hooks/useTableDensity.test.ts`
   Level: unit (component + hook)
   Test intent: Given density `default` / When pilih "Compact" / Then `onChange('compact')` dipanggil; hook: after mount membaca `localStorage['admin:table-density']`, set → persist; render server (localStorage undefined) → default `default` tanpa crash.
   Exercise through: render `<TableDensityToggle />` + `renderHook(useTableDensity)` via @testing-library/react
   Test doubles: `onChange` jest.fn; localStorage di-stub jsdom
   Expected RED: file belum ada → import error.
8. Run test — verify FAIL: `cd apps/web && npx jest src/components/ui/TableDensityToggle.test.tsx src/hooks/useTableDensity.test.ts`
9. Implement toggle + hook + barrel exports → PASS → refactor → commit.

10. Write failing test for: cursor beyond total — halaman kosong tanpa crash
   Test file: `apps/web/src/components/ui/TablePagination.test.tsx`
   Level: unit (component)
   Test intent: Given `{cursor:60, limit:15, total:48, hasMore:false}` (halaman 5 dari 4 — di luar rentang) / When dirender / Then tidak crash, tombol "Berikutnya" disabled, tombol "Sebelumnya" aktif, dan teks "tidak ada data lanjutan" tampil; `{cursor:45, limit:15, total:48, hasMore:false}` / Then "Berikutnya" disabled dan TIDAK menampilkan "tidak ada data lanjutan".
   Exercise through: render `<TablePagination />`
   Test doubles: `onPageChange` = jest.fn (pastikan tidak dipanggil saat disabled)
   Expected RED: kondisi `cursor >= total` belum ditangani → label/rentang salah atau crash.
11. Run test — verify FAIL: `cd apps/web && npx jest src/components/ui/TablePagination.test.tsx`
12. Implement guard `cursor >= total` + pesan "tidak ada data lanjutan" → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "paging UI", "summary strip", "density toggle", "density + SSR hydration".

## WHY THIS APPROACH
Complexity: standard
Justification: Pemisahan presentasional (pagination/summary/toggle) dari `Table` agar `Table` tidak membengkak; hook density terpisah agar bisa di-test hydration-safe.

## SANDWICH CONTEXT
[CRITICAL: density awal Default di server render; localStorage hanya dibaca di useEffect (no hydration mismatch)]
You are implementing table controls untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B, paging UI + summary strip + density persist.
Files in scope: `apps/web/src/components/ui/TablePagination.tsx`, `TableSummary.tsx`, `TableDensityToggle.tsx`, `apps/web/src/hooks/useTableDensity.ts`, `apps/web/src/components/ui/index.ts` + 4 test files.
Available after: T2 (buildCountLabel, TableDensity), T3 (barrel established)
Architecture rule: pakai `buildCountLabel` dari T2 (no reimplement); key localStorage `admin:table-density`.
[RESTATE: density awal Default di server render; localStorage hanya dibaca di useEffect (no hydration mismatch)]

## DELIVERABLE
Given total+cursor+limit, When paging dirender, Then label posisi + prev/next + jump benar; next disabled di halaman terakhir.
Given cursor di luar rentang (cursor ≥ total), When paging dirender, Then tidak crash, next disabled, pesan "tidak ada data lanjutan" tampil.
Given total absen + hasMore, When paging dirender, Then label estimasi berlabel (tidak klaim total).
Given total+breakdown, When summary dirender, Then "N kata · breakdown"; 0 → "0".
Given pilih density lalu reload, When mount, Then density dibaca dari localStorage (post-mount).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Label estimasi bila `total` undefined (jangan tampil total palsu).
  - `useTableDensity` initial = `default`; baca localStorage hanya di effect.
Must-not-have:
  - Menghitung ulang label sendiri (wajib `buildCountLabel` dari T2).
  - Overflow page — wrapper `overflow-x-auto` di Table.
Open question risks:
  - Key density dibaca global (`admin:table-density`) — assumed.
Rollback note:
  - Hapus komponen + hook + export; halaman belum memakainya.
Red flags:
  - Membaca localStorage saat render (bukan effect) → STOP (hydration).

## STOP CONDITIONS
Done when: 4 test groups PASS (pagination+prev/next/jump, pagination beyond-total, summary, toggle/hook).
Uncertain when: renderHook tidak tersedia di RTL versi terpasang.
Escalate when: perlu state global lintas halaman (bukan localStorage).

---

### Task 5: `AdminOutletController@index` — sort + total + summary + normalisasi meta [depends: T1]

## OBJECTIVE
Upgrade `index` outlet. **Offset cursor + clamp `max(cursor,0)` + `limit+1` SUDAH ada — jangan diubah.** Tambahkan: sort allowlist `[created_at,updated_at,name,id,category,score]`, `meta.total` + `meta.summary {active,inactive}` dengan filter yang sama, dan **normalisasi `meta` ke top-level** (`has_more`/`limit`/`cursor` saat ini nested di dalam object `data` → pindah ke `meta`; `data` tetap array agar `fetchAdminOutlets` yang sudah menangani dua bentuk response tetap jalan).

Steps:
1. Write failing test for: default sort terbaru + total + summary
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent: Given beberapa outlet (created_at bervariasi) / When `GET /admin/outlets?limit=15` / Then `data[0]` = created_at terbaru (id DESC tiebreak), response `meta.total` = jumlah, `meta.summary.active/inactive` konsisten.
   Exercise through: HTTP `getJson('/admin/outlets')` (auth admin)
   Test doubles: `Carbon::setTestNow()` (freeze clock — dipakai juga di `AdminOutletTest`/`PromotionTest`)
   Expected RED: `meta.total`/`meta.summary` belum ada + urutan masih id ASC.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AdminOutletTest`
3. Implement sort allowlist + orderByRaw + total + summary → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + sort by name + nulls-last
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent: Given `GET /admin/outlets?sort=__proto__&order=desc` / Then HTTP 200 + urutan default terbaru (bukan 422/500); `?sort=name&order=asc` / Then urut nama asc; baris null created_at selalu terakhir pada desc & asc.
   Exercise through: `getJson('/admin/outlets?...')`
   Test doubles: none beyond auth
   Expected RED: sort param diabaikan/tidak ada → urutan tidak berubah.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AdminOutletTest`
6. Implement (fallback sudah via T1 resolveSort) + offset cursor + meta normalization → PASS → refactor → commit.

7. Write failing test for: normalisasi `meta` top-level + cursor di luar total
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent: Given total 48 (limit 15) / When `GET /admin/outlets?limit=15` / Then `meta.has_more`, `meta.limit`, `meta.cursor` ada di **top-level `meta`** (`meta.cursor = 0`); `GET /admin/outlets?cursor=60` / Then HTTP 200, `data: []`, `meta.has_more: false`, `meta.total` tetap 48.
   Exercise through: `getJson('/admin/outlets?limit=15')` + `getJson('/admin/outlets?cursor=60')`
   Test doubles: none beyond auth
   Expected RED: `has_more`/`limit`/`cursor` saat ini hanya nested di dalam object `data` (`data.has_more`), bukan di top-level `meta` → assertion `meta.has_more` gagal.
8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AdminOutletTest`
9. Implement normalisasi `meta` top-level (`has_more`/`limit`/`cursor`/`total`/`summary`) sambil mempertahankan bentuk `data` array → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Kontrak cursor", "Kontrak total", "Sort allowlist per controller", "Summary strip source", GWT Story1/2/4.

## WHY THIS APPROACH
Complexity: standard
Justification: Outlet adalah kanonik; implementasi pertama menetapkan pola yang direplikasi controller lain.

## SANDWICH CONTEXT
[CRITICAL: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah guard/auth; tidak ada migrasi]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: offset cursor, top-level meta.total + meta.summary.
Files in scope: `apps/api/app/Http/Controllers/AdminOutletController.php`, `apps/api/tests/Feature/AdminOutletTest.php`
Available after: T1 (ListQuery)
Architecture rule: `total` = COUNT dgn filter sama; `summary` = query agregat terpisah.
[RESTATE: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah guard/auth; tidak ada migrasi]

## DELIVERABLE
Given GET /admin/outlets tanpa sort, When merespons, Then urutan created_at DESC nulls-last lalu id DESC + meta.total + summary.
Given GET /admin/outlets?sort=__proto__, When merespons, Then HTTP 200 fallback default (bukan 422/500).
Given GET /admin/outlets?category=warung, When merespons, Then meta.total hanya outlet kategori tsb.
Given GET /admin/outlets?cursor=60 (di luar total), When merespons, Then data:[] + meta.has_more:false + meta.total tetap (no crash).
Given GET /admin/outlets (halaman 1), When merespons, Then meta.has_more/meta.limit/meta.cursor ada di top-level `meta` (bukan nested di dalam `data`).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `has_more` tetap (limit+1); offset cursor + clamp `max(cursor,0)` yang sudah ada TIDAK diubah.
  - `meta.total` top-level (normalisasi dari nested `data`).
  - Bentuk `data` tetap array (kompatibel `fetchAdminOutlets`).
Must-not-have:
  - Menyentuh Outlet model fillable/schema.
  - Mengubah perilaku endpoint non-list (show/update).
Open question risks:
  - Outlet `score`/`category` kolom real → assumed (allowlist).
Rollback note:
  - Revert controller; frontend fallback estimasi bila total hilang.
Red flags:
  - orderBy kolom di luar allowlist → STOP.

## STOP CONDITIONS
Done when: 3 integration test groups PASS (default sort+total+summary, sort fallback+name+nulls, normalisasi meta+beyond-total); total konsisten dgn filter.
Uncertain when: `category` bukan kolom melainkan computed → NEEDS_CONTEXT.
Escalate when: perlu migrasi untuk index sort.

---

### Task 6: `UserRoleController@index` — offset cursor + sort + total [depends: T1]

## OBJECTIVE
Tambah offset cursor + sort allowlist `[created_at,updated_at,name,email,role,id]` + top-level `meta.total` (summary total) pada `index` users, pertahankan guard `viewAny` + validasi role/limit.

Steps:
1. Write failing test for: default sort terbaru + total
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent: Given beberapa user (created_at bervariasi) / When `GET /admin/users?limit=20` / Then `data[0]` = created_at terbaru (id DESC tiebreak), `meta.total` = jumlah user sesuai role filter.
   Exercise through: `getJson('/admin/users')` (auth admin)
   Test doubles: none beyond auth
   Expected RED: urutan masih id ASC, `meta.total` absent.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RoleManagementTest`
3. Implement sort + total + offset → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + role filter total
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent: Given `?sort=name&order=asc` / Then urut nama asc; `?sort=__proto__` / Then 200 fallback default; `?role=sales` / Then `meta.total` = jumlah sales saja.
   Exercise through: `getJson('/admin/users?...')`
   Test doubles: none
   Expected RED: param sort diabaikan/total tidak terfilter.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RoleManagementTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (users), "Kontrak total", "Authorization".

## WHY THIS APPROACH
Complexity: lightweight
Justification: Perubahan terfokus pada list endpoint; guard + validasi role/limit dipertahankan.

## SANDWICH CONTEXT
[CRITICAL: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah policy viewAny/guard]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/UserRoleController.php`, `apps/api/tests/Feature/RoleManagementTest.php`
Available after: T1
Architecture rule: total = COUNT dgn role filter sama; offset cursor.
[RESTATE: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah policy viewAny/guard]

## DELIVERABLE
Given GET /admin/users tanpa sort, When merespons, Then created_at DESC nulls-last + meta.total.
Given GET /admin/users?role=sales, When merespons, Then meta.total hanya sales + data terfilter.
Given GET /admin/users?sort=__proto__, When merespons, Then 200 fallback default.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `role` validasi `in:` tetap; `limit` min1 max100 tetap.
Must-not-have:
  - Mengubah policy `viewAny` atau role assignment endpoint (`/admin/users/{id}/role`).
Rollback note:
  - Revert controller.
Red flags:
  - Menghapus `abort_unless(viewAny)` → STOP.

## STOP CONDITIONS
Done when: 2 integration test groups PASS.
Uncertain when: role filter selain `in:` list tidak bisa dihitung totalnya.
Escalate when: perlu menyentuh UserRoleService.

---

### Task 7: `PromotionController@index` — id-cursor → offset + sort + total + summary [depends: T1]

## OBJECTIVE
Konversi `index` promosi dari id-based cursor (`where id > cursor`, `next_cursor`) ke **offset cursor**; sort allowlist `[created_at,updated_at,start_date,end_date,id]`; `meta.total` top-level + `meta.summary {total,active,scheduled,ended}`; pertahankan `assertAdminOrOwner`.

Steps:
1. Write failing test for: offset cursor + default sort + total + summary
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent: Given promosi (start/end bervariasi) / When `GET /admin/promotions?limit=15&cursor=0` / Then data urut created_at DESC (id DESC tiebreak), `meta.total` = jumlah, `meta.summary` berisi active/scheduled/ended konsisten; `cursor=15` / Then halaman kedua (bukan where id>15).
   Exercise through: `getJson('/admin/promotions?...')`
   Test doubles: `Carbon::setTestNow()` untuk freeze `now` agar klasifikasi active/scheduled/ended deterministik (restore di teardown); none beyond auth
   Expected RED: cursor masih id-based (`next_cursor` id) + `meta.total`/`summary` absent.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=PromotionTest`
3. Implement offset + sort + total + summary → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + sort start_date
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent: Given `?sort=start_date&order=asc` / Then urut start_date asc nulls-last; `?sort=__proto__` / Then 200 fallback default.
   Exercise through: `getJson('/admin/promotions?...')`
   Test doubles: none
   Expected RED: sort diabaikan.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=PromotionTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Kontrak cursor" (konversi id-based), "Sort allowlist per controller", "Summary strip source".

## WHY THIS APPROACH
Complexity: standard
Justification: Perubahan kontrak cursor (id→offset) + summary berbasis waktu; satu-satunya controller dengan 4-state summary.

## SANDWICH CONTEXT
[CRITICAL: konversi cursor id→offset; sort via allowlist; jangan ubah assertAdminOrOwner; summary {total,active,scheduled,ended}]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/PromotionController.php`, `apps/api/tests/Feature/PromotionTest.php`
Available after: T1
Architecture rule: active = now dalam [start,end]; scheduled = start>now; ended = end<now.
[RESTATE: konversi cursor id→offset; sort via allowlist; jangan ubah assertAdminOrOwner; summary {total,active,scheduled,ended}]

## DELIVERABLE
Given GET /admin/promotions?cursor=15, When merespons, Then offset halaman 2 (bukan id-based).
Given GET /admin/promotions tanpa sort, When merespons, Then created_at DESC + meta.total + summary 4-state.
Given GET /admin/promotions?sort=__proto__, When merespons, Then 200 fallback default.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `next_cursor` lama dihapus (diganti offset `cursor`); response frontend toleran.
  - Summary konsisten dgn filter owner (assertAdminOrOwner scope).
Must-not-have:
  - Menyentuh create/update/delete/broadcast promo.
Rollback note:
  - Revert controller; frontend lama yang pakai `next_cursor` tidak dipakai (dummy+real sudah offset).
Red flags:
  - `next_cursor` masih ada → DONE_WITH_CONCERNS (contract tidak bersih).

## STOP CONDITIONS
Done when: 2 integration test groups PASS; cursor offset terverifikasi.
Uncertain when: scope owner memengaruhi summary (owner filter) → pastikan summary pakai query ber-scope sama.
Escalate when: perlu mengubah schema promo.

---

### Task 8: `OrderController@index` — offset cursor + sort + total [depends: T1]

## OBJECTIVE
Tambah offset cursor + sort allowlist `[created_at,updated_at,order_id,status,total_amount,id]` + top-level `meta.total` pada `index`; PERTAHANKAN cek `isAdmin()` + middleware `deny.finance`; endpoint dipakai bersama `/orders` & `/admin/orders`.

Steps:
1. Write failing test for: offset cursor + total + default sort
   Test file: `apps/api/tests/Feature/OrderTest.php`
   Level: integration
   Test intent: Given order (created_at bervariasi) / When `GET /admin/orders?limit=100&cursor=0` / Then data urut created_at DESC, `meta.total` = jumlah; `cursor=100` / Then offset halaman 2; non-admin / Then tetap 403.
   Exercise through: `getJson('/admin/orders')` (auth admin)
   Test doubles: none beyond auth
   Expected RED: `meta.total` absent + cursor diabaikan.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderTest`
3. Implement offset + total (keep `latest('created_at')` default + allowlist sort) → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + status sort + non-admin 403
   Test file: `apps/api/tests/Feature/OrderTest.php`
   Level: integration
   Test intent: Given `?sort=status&order=asc` / Then urut status asc; `?sort=__proto__` / Then 200 fallback; user non-admin / Then 403 (tidak berubah).
   Exercise through: `getJson('/admin/orders?...')`
   Test doubles: none
   Expected RED: sort diabaikan / auth tidak dipertahankan.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (orders), "Kontrak total", "Authorization", Implementation Notes `orders`.

## WHY THIS APPROACH
Complexity: standard
Justification: Endpoint shared & admin-only; perubahan harus additive agar `/orders` konsumen lain tidak terpengaruh.

## SANDWICH CONTEXT
[CRITICAL: jangan hapus cek isAdmin()/middleware deny.finance; sort via allowlist; tambahan param aditif]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/tests/Feature/OrderTest.php`
Available after: T1
Architecture rule: default `latest('created_at')` dipertahankan; offset cursor tambahan.
[RESTATE: jangan hapus cek isAdmin()/middleware deny.finance; sort via allowlist; tambahan param aditif]

## DELIVERABLE
Given GET /admin/orders, When merespons, Then created_at DESC + meta.total + offset cursor.
Given GET /admin/orders?sort=status, When merespons, Then urut status (asc/desc).
Given user non-admin, When GET /admin/orders, Then 403 (tidak berubah).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `formatOrderResponse` tidak diubah (pipeline Order tetap utuh).
Must-not-have:
  - Menyentuh approve/cancel/show logic.
  - Mengubah middleware `deny.finance`.
Rollback note:
  - Revert controller; consumer lain tidak terpengaruh (param aditif).
Red flags:
  - Mengubah response shape `data` order item → STOP (pipeline).

## STOP CONDITIONS
Done when: 2 integration test groups PASS; auth guard tetap.
Uncertain when: `total_amount` kolom real (allowlist) — assumed.
Escalate when: butuh menyentuh OrderService.

---

### Task 9: `ProductController@index` — additive sort/cursor/total (public endpoint) [depends: T1]

## OBJECTIVE
Tambah `sort`/`order`/`cursor`/`total` **aditif** pada `ProductController@index` (endpoint publik `/products`, dipakai halaman admin products). Default tetap `orderBy('id')` + hard `limit(100)` agar marketplace/catalog tidak terpengaruh. Allowlist `[created_at,updated_at,name,sku,price,stock_quantity,id]`.

Steps:
1. Write failing test for: default preserved + additive total
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given produk / When `GET /products` (tanpa param baru) / Then urutan tetap id ASC (default lama) + `meta.total` hadir (aditif); `?sort=created_at&order=desc` / Then urut created_at DESC nulls-last.
   Exercise through: `getJson('/products')`
   Test doubles: none (public endpoint; supplier filter existing)
   Expected RED: `meta.total` absent; sort param diabaikan.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
3. Implement additive sort/total/offset (default id ASC preserved) → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + search + total dengan search
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given `?sort=__proto__` / Then 200 fallback ke default produk `id ASC` (via default caller T1 — BUKAN `created_at DESC` global); `?search=xyz` / Then `meta.total` mencerminkan hasil search (filter sama).
   Exercise through: `getJson('/products?...')`
   Test doubles: none
   Expected RED: total tidak mengikuti filter search.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (products), Implementation Notes `products`.

## WHY THIS APPROACH
Complexity: standard
Justification: Endpoint publik dipakai bersama; default lama wajib dipertahankan persis, param baru aditif.

## SANDWICH CONTEXT
[CRITICAL: default orderBy id + limit 100 TIDAK berubah; param baru aditif; jangan pecah filter supplier aktif]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/ProductController.php`, `apps/api/tests/Feature/ProductTest.php`
Available after: T1
Architecture rule: tanpa `sort` → default id ASC (kompatibel); dengan `sort` → allowlist.
[RESTATE: default orderBy id + limit 100 TIDAK berubah; param baru aditif; jangan pecah filter supplier aktif]

## DELIVERABLE
Given GET /products tanpa param, When merespons, Then urutan id ASC (default lama) + meta.total aditif.
Given GET /products?sort=created_at&order=desc, When merespons, Then created_at DESC nulls-last.
Given GET /products?search=xyz, When merespons, Then meta.total mengikuti hasil search.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Filter `whereNull(supplier_id) orWhereHas(supplier active)` tetap utuh.
Must-not-have:
  - Mengubah default order/limit saat param baru absent.
  - Menyentuh `AdminProductController@update`/`prices`.
Rollback note:
  - Revert controller; consumer marketplace tidak terpengaruh.
Red flags:
  - Mengubah default `orderBy('id')` → STOP.

## STOP CONDITIONS
Done when: 2 integration test groups PASS; default lama terverifikasi tidak berubah.
Uncertain when: `sku`/`stock_quantity` bukan kolom real (allowlist) — assumed.
Escalate when: butuh endpoint admin products terpisah (scope creep).

---

### Task 10: `SalesPerformanceController@adminPerformance` — id-cursor → offset + sort + total [depends: T1]

## OBJECTIVE
Konversi `adminPerformance` dari id-based cursor ke **offset**; sort allowlist server-side hanya `[name,id]`; `meta.total` top-level (jumlah sales user). `achievement` tetap dihitung via `performanceService` (sort achievement = client-side). Pertahankan guard `isAdminOrOwner` 403.

Steps:
1. Write failing test for: offset cursor + total + default order
   Test file: `apps/api/tests/Feature/SalesPerformanceTest.php`
   Level: integration
   Test intent: Given sales user / When `GET /admin/sales/performance?limit=100&cursor=0` / Then `meta.total` = jumlah sales, cursor offset bekerja (bukan where id>); non-admin / Then 403.
   Exercise through: `getJson('/admin/sales/performance')`
   Test doubles: none beyond auth; `performanceService` dipanggil per-user (existing)
   Expected RED: `meta.total` absent + cursor id-based (`next_cursor` id).
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=SalesPerformanceTest`
3. Implement offset + total (keep name ASC/id default order) → PASS → refactor → commit.

4. Write failing test for: sort name + invalid fallback
   Test file: `apps/api/tests/Feature/SalesPerformanceTest.php`
   Level: integration
   Test intent: Given `?sort=name&order=asc` / Then urut nama asc; `?sort=achievement` / Then 200 fallback default (achievement BUKAN allowlist server); `?sort=__proto__` / Then 200 fallback.
   Exercise through: `getJson('/admin/sales/performance?...')`
   Test doubles: none
   Expected RED: sort diabaikan.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=SalesPerformanceTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (sales-performance), Implementation Notes `sales-performance`.

## WHY THIS APPROACH
Complexity: standard
Justification: `achievement` derived → sort server hanya kolom DB; konversi cursor membutuhkan perubahan query utama tanpa mengganggu perhitungan performance.

## SANDWICH CONTEXT
[CRITICAL: sort server hanya name/id (achievement BUKAN kolom DB); jangan ubah isAdminOrOwner guard/formatAmounts]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/SalesPerformanceController.php`, `apps/api/tests/Feature/SalesPerformanceTest.php`
Available after: T1
Architecture rule: offset cursor; `calculatePerformance` dipanggil per baris hasil query.
[RESTATE: sort server hanya name/id (achievement BUKAN kolom DB); jangan ubah isAdminOrOwner guard/formatAmounts]

## DELIVERABLE
Given GET /admin/sales/performance?cursor=0, When merespons, Then offset + meta.total = jumlah sales.
Given GET /admin/sales/performance?sort=name, When merespons, Then urut name (asc/desc).
Given GET /admin/sales/performance?sort=achievement, When merespons, Then 200 fallback default (tidak 422/500).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `formatAmounts` + `calculatePerformance` tidak diubah.
  - `next_cursor` id dihapus → offset.
Must-not-have:
  - Sort achievement di backend (harus client-side).
Rollback note:
  - Revert controller; frontend fallback estimasi.
Red flags:
  - Menambah `achievement` ke allowlist server → STOP (bukan kolom DB).

## STOP CONDITIONS
Done when: 2 integration test groups PASS; guard 403 tetap.
Uncertain when: period resolve memengaruhi jumlah total.
Escalate when: butuh agregasi performance server-side.

---

### Task 11: Outlets page — sort/paging/summary/timestamps/density [depends: T4, T5]

## OBJECTIVE
Upgrade `outlets/page.tsx` + `api.ts`: sort state (default created_at DESC), cursor/page state, summary strip, `Dibuat`/`Diperbarui` kolom, density toggle; `api.ts` kirim `sort`/`order`/`cursor`, baca `meta.total`/`meta.summary`; dummy branch `listDummyOutlets` pakai `compareRows`/`paginate` dari T2 (parity).

Steps:
1. Write failing test for: default sort terbaru + summary strip + timestamp kolom
   Test file: `apps/web/src/app/admin/outlets/page.test.tsx`
   Level: integration
   Test intent: Given fetch mock return outlets (created_at bervariasi) + `meta.total`/`meta.summary` / When halaman load / Then strip "N outlet · aktif · nonaktif" tampil, kolom "Dibuat" render format lokal (bukan ISO mentah), baris pertama = created_at terbaru.
   Exercise through: render `<AdminOutletsPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken di-mock (existing harness); bukan mock page
   Expected RED: strip/sort/kolom belum ada → query gagal menemukan teks.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/outlets/page.test.tsx`
3. Implement page + api (sort default + summary + timestamps) → PASS → refactor → commit.

4. Write failing test for: sort header klik + reset cursor + paging jump
   Test file: `apps/web/src/app/admin/outlets/page.test.tsx`
   Level: integration
   Test intent: Given halaman 1 / When klik header "Nama" / Then fetch dipanggil dgn `sort=name&order=desc&cursor=0` (filter dipertahankan); klik header "Nama" KEDUA kali / Then fetch `sort=name&order=asc&cursor=0` (arah berbalik); klik halaman 3 / Then `cursor=30` + sort+filter dipertahankan; ubah filter saat halaman 3 / Then `cursor=0` + filter baru; paksa halaman 5 (cursor=60, total 48) / Then tabel kosong + next disabled + pesan "tidak ada data lanjutan" tanpa crash.
   Exercise through: render + fireEvent klik header/nomor/select filter
   Test doubles: fetch mock (assert URL query), getStoredToken mock
   Expected RED: onSort/onPageChange belum terhubung → fetch tanpa param sort/cursor.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/outlets/page.test.tsx`
6. Implement sort header + paging + reset rules → PASS → refactor → commit.

7. Write failing test for: density toggle + null timestamp "—" + dummy parity
   Test file: `apps/web/src/app/admin/outlets/page.test.tsx` + `apps/web/src/app/admin/outlets/dummy-guard.test.ts`
   Level: integration
   Test intent: Given density toggle / When pilih Compact / Then class compact + localStorage ter-set; row tanpa created_at / Then sel "—"; dummy mode: `listDummyOutlets` urut created_at DESC + offset slice sama + `total=filtered.length` + `summary {active,inactive}` konsisten.
   Exercise through: render + fireEvent toggle; dummy branch via `withDummyRead(true,...)`
   Test doubles: fetch mock; dummy store flag true
   Expected RED: density/timestamp null/dummy sort belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/outlets/page.test.tsx src/app/admin/outlets/dummy-guard.test.ts`
9. Implement density + null timestamp + dummy parity → PASS → refactor → commit.

10. Write failing test for: state sort page → prop `Table` → `aria-sort`/indikator
   Test file: `apps/web/src/app/admin/outlets/page.test.tsx`
   Level: integration
   Test intent: Given halaman termuat dengan sort default `created_at` / When dirender / Then header kolom aktif punya `aria-sort="descending"` + indikator panah; klik header "Nama" / Then `aria-sort="descending"` pindah ke header "Nama"; klik KEDUA kali / Then `aria-sort="ascending"` + panah berbalik (state dari `toggleSort` T2 benar-benar mencapai prop `sort` di `Table`, bukan hanya URL fetch).
   Exercise through: render page + `fireEvent.click` pada header + query `aria-sort`
   Test doubles: fetch mock (assert terpisah dari assert rendered)
   Expected RED: prop `sort` di `Table` belum terhubung ke state sort page → `aria-sort` tidak berpindah/berubah.
11. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/outlets/page.test.tsx`
12. Implement wiring `toggleSort` → prop `sort`/`sortableColumns` → `aria-sort` + indikator → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 semua (outlets), GWT outlets.

## WHY THIS APPROACH
Complexity: deep
Justification: Halaman paling lengkap (filter + summary breakdown + sort + paging); jadi pola kanonik untuk 5 halaman lain.

## SANDWICH CONTEXT
[CRITICAL: Table tetap generik dipakai; dummy path zero-network & parity; jangan ubah filter yang ada]
You are implementing outlets page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B; offset cursor; sort default created_at DESC.
Files in scope: `apps/web/src/app/admin/outlets/page.tsx`, `apps/web/src/app/admin/outlets/api.ts`, `apps/web/src/app/admin/outlets/page.test.tsx`, `apps/web/src/app/admin/outlets/dummy-guard.test.ts`
Available after: T4 (controls), T5 (backend outlet)
Architecture rule: reset sort/filter → cursor=0; pindah halaman → pertahankan sort+filter.
[RESTATE: Table tetap generik dipakai; dummy path zero-network & parity; jangan ubah filter yang ada]

## DELIVERABLE
Given admin buka halaman outlet, When data dimuat, Then baris pertama created_at terbaru + summary strip + kolom Dibuat terformat lokal.
Given klik header Nama, When onSort, Then fetch sort=name&order=desc&cursor=0 (filter dipertahankan).
Given klik header Nama kedua kali, When onSort, Then fetch sort=name&order=asc&cursor=0 (arah berbalik).
Given pilih halaman 3, When jump, Then cursor=(3-1)*limit + sort+filter dipertahankan.
Given cursor di luar total (halaman 5 dari 4), When dirender, Then tabel kosong + next disabled + pesan "tidak ada data lanjutan" (no crash).
Given sort aktif, When tabel dirender, Then header kolom aktif punya `aria-sort` sesuai arah + indikator panah tampil (state sort page benar-benar sampai ke `Table`).
Given ubah filter di halaman 3, When terapkan, Then cursor=0 + filter baru.
Given row tanpa created_at, When dirender, Then "—".
Given density Compact + reload, When mount, Then density dari localStorage post-mount.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Sort/filter/paging/reset rules persis spec Rule 5.
  - Dummy branch parity (compareRows/paginate dari T2, no reimplement).
Must-not-have:
  - Menyentuh fetchOutletOrders/fetchOutletSummary/updateOutlet.
  - Menambah filter baru.
Open question risks:
  - `meta.summary` absen (backend lama) → fallback tampilkan total saja tanpa crash.
Rollback note:
  - Revert page+api; fallback estimasi.
Red flags:
  - Mengubah kontrak `fetchAdminOutlets` return yang dipakai tempat lain → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 4 test groups PASS (sort/summary/timestamp, sort+paging reset, density+null+dummy, aria-sort/indikator).
Uncertain when: `aria-sort`/onSort contract berubah dari T3.
Escalate when: perlu mengubah `Table` API (keluar scope page).

---

### Task 12: Products page — sort/paging/summary/timestamps/density [depends: T4, T9]

## OBJECTIVE
Upgrade `products/page.tsx` + `api.ts`: sort default `created_at DESC` (fallback id DESC karena dummy produk mungkin tanpa created_at), cursor/page, summary `{total, out_of_stock}`, kolom `Dibuat`/`Diperbarui`, density; `api.ts` kirim `sort`/`order`/`cursor`, baca `meta.total`/`meta.summary`; dummy `listDummyProducts` parity via T2.

Steps:
1. Write failing test for: default sort + summary stok habis + timestamp null "—"
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given produk (sebagian tanpa created_at) / When load / Then baris pertama = created_at terbaru (id DESC tiebreak), summary "N produk · M stok habis", sel tanpa timestamp "—".
   Exercise through: render `<AdminProductsPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock
   Expected RED: sort/summary/kolom belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx`
3. Implement → PASS → refactor → commit.

4. Write failing test for: sort header + search reset cursor + paging
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Harga" / Then fetch `sort=price&order=desc&cursor=0`; klik header "Harga" KEDUA kali / Then `sort=price&order=asc&cursor=0`; ubah search saat halaman >1 / Then cursor=0; pindah halaman / Then sort+filter dipertahankan.
   Exercise through: render + fireEvent
   Test doubles: fetch mock
   Expected RED: param sort/cursor tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx`
6. Implement → PASS → refactor → commit.

7. Write failing test for: density + dummy parity
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given density toggle / When pilih / Then class + persist; dummy: `listDummyProducts` sort created_at DESC + offset slice + total=filtered.length + summary `{total,out_of_stock}`.
   Exercise through: render + fireEvent; dummy branch
   Test doubles: fetch mock; dummy flag
   Expected RED: density/dummy sort belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx`
9. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (products), "Summary strip source" (products).

## WHY THIS APPROACH
Complexity: standard
Justification: Produk memakai endpoint publik; frontend harus toleran total absent (fallback estimasi).

## SANDWICH CONTEXT
[CRITICAL: Table generik; dummy path parity; jangan ubah updateProductPrice/fetchPriceHistory]
You are implementing products page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/products/page.tsx`, `apps/web/src/app/admin/products/api.ts`, `apps/web/src/app/admin/products/page.test.tsx`
Available after: T4, T9
Architecture rule: reset filter(sort) → cursor=0; fallback estimasi bila total absent.
[RESTATE: Table generik; dummy path parity; jangan ubah updateProductPrice/fetchPriceHistory]

## DELIVERABLE
Given load produk, When selesai, Then default terbaru + summary {total,out_of_stock} + kolom waktu (null → "—").
Given klik header Harga, When onSort, Then fetch sort=price&order=desc&cursor=0.
Given klik header Harga kedua kali, When onSort, Then fetch sort=price&order=asc&cursor=0.
Given ubah search di halaman >1, When terapkan, Then cursor=0 + filter baru.
Given pindah halaman, When jump, Then sort+filter dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Dummy produk tanpa created_at → id DESC fallback (nulls-last).
Must-not-have:
  - Menyentuh updateProductPrice/fetchPriceHistory/dummyPriceHistory.
Rollback note:
  - Revert page+api.
Red flags:
  - Mengubah signatur `fetchProducts` return yang dipakai di halaman lain → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: `meta.summary.out_of_stock` absent → fallback total only.
Escalate when: perlu endpoint admin produk terpisah.

---

### Task 13: Users page — sort/paging/summary/timestamps/density [depends: T4, T6]

## OBJECTIVE
Upgrade `users/page.tsx` + `api.ts`: sort default created_at DESC, cursor/page, summary total, kolom `Dibuat`/`Diperbarui`, density; `api.ts` kirim `sort`/`order`/`cursor`, baca `meta.total`; dummy `listDummyUsers` parity.

Steps:
1. Write failing test for: default sort + summary total + timestamp kolom
   Test file: `apps/web/src/app/admin/users/page.test.tsx`
   Level: integration
   Test intent: Given users (created_at bervariasi) / When load / Then baris pertama terbaru, summary "N pengguna", kolom "Dibuat" terformat.
   Exercise through: render `<AdminUsersPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock
   Expected RED: sort/summary/kolom belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/users/page.test.tsx`
3. Implement → PASS → refactor → commit.

4. Write failing test for: sort header + role filter reset + paging
   Test file: `apps/web/src/app/admin/users/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Email" / Then fetch `sort=email&order=desc&cursor=0`; klik KEDUA kali / Then `sort=email&order=asc&cursor=0`; ubah role filter / Then cursor=0; pindah halaman / Then sort+filter dipertahankan.
   Exercise through: render + fireEvent
   Test doubles: fetch mock
   Expected RED: param sort/cursor tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/users/page.test.tsx`
6. Implement → PASS → refactor → commit.

7. Write failing test for: density + dummy parity
   Test file: `apps/web/src/app/admin/users/page.test.tsx`
   Level: integration
   Test intent: Given density toggle + dummy mode / When pilih + dummy fetch / Then class+persist; dummy sort created_at DESC + offset slice + total=filtered.length + summary `{total}`.
   Exercise through: render + fireEvent; dummy branch
   Test doubles: fetch mock; dummy flag
   Expected RED: density/dummy sort belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/users/page.test.tsx`
9. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (users), "Sort allowlist per controller" (users).

## WHY THIS APPROACH
Complexity: standard
Justification: Users punya role filter; reset rules harus diuji dengan filter.

## SANDWICH CONTEXT
[CRITICAL: Table generik; dummy path parity; jangan ubah assignUserRole]
You are implementing users page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/users/page.tsx`, `apps/web/src/app/admin/users/api.ts`, `apps/web/src/app/admin/users/page.test.tsx`
Available after: T4, T6
Architecture rule: reset filter(sort) → cursor=0; fallback estimasi bila total absent.
[RESTATE: Table generik; dummy path parity; jangan ubah assignUserRole]

## DELIVERABLE
Given load users, When selesai, Then default terbaru + summary total + kolom waktu.
Given klik header Email, When onSort, Then fetch sort=email&order=desc&cursor=0.
Given klik header Email kedua kali, When onSort, Then fetch sort=email&order=asc&cursor=0.
Given ubah role filter, When terapkan, Then cursor=0 + filter baru.
Given pindah halaman, When jump, Then sort+filter dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `assignUserRole` + role PATCH flow tidak berubah.
Must-not-have:
  - Menyentuh assignUserRole / assignDummyUserRole.
Rollback note:
  - Revert page+api.
Red flags:
  - Mengubah kontrak `UsersListResult` yang dipakai tempat lain → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: `meta.summary` users absent → total only.
Escalate when: perlu menambah role baru.

---

### Task 14: Promotions page — sort/paging/summary/timestamps/density [depends: T4, T7]

## OBJECTIVE
Upgrade `promotions/page.tsx` + `api.ts`: sort default created_at DESC, cursor offset (page), summary `{total,active,scheduled,ended}`, kolom `Dibuat`/`Diperbarui`, density; `api.ts` kirim `sort`/`order`/`cursor` (offset, bukan id), baca `meta.total`/`meta.summary`; dummy `listDummyPromotions` parity (already offset slice).

Steps:
1. Write failing test for: default sort + summary 4-state + timestamp
   Test file: `apps/web/src/app/admin/promotions/page.test.tsx`
   Level: integration
   Test intent: Given promosi / When load / Then baris pertama created_at terbaru, summary total/active/scheduled/ended, kolom Dibuat terformat.
   Exercise through: render `<AdminPromotionsPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock; `jest.useFakeTimers().setSystemTime()` agar status promosi dummy (`2026-01-*`/`2026-02-*`) deterministik
   Expected RED: sort/summary/kolom belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/promotions/page.test.tsx`
3. Implement → PASS → refactor → commit.

4. Write failing test for: sort header + paging offset (bukan id)
   Test file: `apps/web/src/app/admin/promotions/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Mulai" / Then fetch `sort=start_date&order=desc&cursor=0`; klik KEDUA kali / Then `sort=start_date&order=asc&cursor=0`; pindah halaman 2 / Then `cursor=15` (offset) + sort dipertahankan.
   Exercise through: render + fireEvent
   Test doubles: fetch mock (assert `cursor=15` bukan `next_cursor` id)
   Expected RED: cursor id-based/param sort tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/promotions/page.test.tsx`
6. Implement → PASS → refactor → commit.

7. Write failing test for: density + dummy parity (offset)
   Test file: `apps/web/src/app/admin/promotions/page.test.tsx`
   Level: integration
   Test intent: Given density toggle + dummy mode / Then class+persist; dummy sort created_at DESC + offset slice + total=filtered.length + summary 4-state `{total,active,scheduled,ended}` konsisten.
   Exercise through: render + fireEvent; dummy branch
   Test doubles: fetch mock; dummy flag
   Expected RED: density/dummy sort belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/promotions/page.test.tsx`
9. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (promotions), "Summary strip source" (promotions).

## WHY THIS APPROACH
Complexity: standard
Justification: Promosi satu-satunya dengan cursor id→offset + summary 4-state; frontend harus sejajar.

## SANDWICH CONTEXT
[CRITICAL: Table generik; cursor offset (bukan id); jangan ubah create/update/delete/broadcast promo]
You are implementing promotions page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/promotions/page.tsx`, `apps/web/src/app/admin/promotions/api.ts`, `apps/web/src/app/admin/promotions/page.test.tsx`
Available after: T4, T7
Architecture rule: cursor offset; dummy already offset slice.
[RESTATE: Table generik; cursor offset (bukan id); jangan ubah create/update/delete/broadcast promo]

## DELIVERABLE
Given load promosi, When selesai, Then default terbaru + summary 4-state + kolom waktu.
Given klik header Mulai, When onSort, Then fetch sort=start_date&order=desc&cursor=0.
Given klik header Mulai kedua kali, When onSort, Then fetch sort=start_date&order=asc&cursor=0.
Given pindah halaman 2, When jump, Then cursor=15 (offset) + sort dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `fetchPromotions` cursor = offset; `nextCursor` = cursor+limit (bukan id).
Must-not-have:
  - Menyentuh createPromotion/updatePromotion/deletePromotion/broadcastPromotion.
Rollback note:
  - Revert page+api.
Red flags:
  - Masih mengirim `next_cursor` id → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: `meta.summary` 4-state absent → total only fallback.
Escalate when: perlu mengubah form promo.

---

### Task 15: Sales-performance page — sort/paging/summary/density [depends: T4, T10]

## OBJECTIVE
Upgrade `sales-performance/page.tsx` + `api.ts`: default display sort `achievement DESC` (client-side, karena derived), cursor offset, summary total, density; server sort terbatas `name`/`id`; `api.ts` kirim `sort`/`order`/`cursor`, baca `meta.total`; dummy `dummySalesPerformance` parity (already offset + achievement sort).

> **Catatan Rule 7 (kolom waktu):** `SalesPerformanceRow` adalah agregat per-user per-periode (bukan entitas) sehingga TIDAK punya `created_at`/`updated_at`; kolom `Dibuat`/`Diperbarui` TIDAK ditambahkan. Kolom `Periode` yang sudah ada menjadi penanda waktu. Lihat spec Implementation Notes.

Steps:
1. Write failing test for: default achievement DESC + summary total
   Test file: `apps/web/src/app/admin/sales-performance/page.test.tsx`
   Level: integration
   Test intent: Given rows (achievement bervariasi) / When load / Then baris pertama = achievement tertinggi (client-side sort), summary "N sales".
   Exercise through: render `<AdminSalesPerformancePage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock
   Expected RED: default sort achievement belum ada/summary absent.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/sales-performance/page.test.tsx`
3. Implement → PASS → refactor → commit.

4. Write failing test for: sort header Nama (server) + paging offset + period filter reset
   Test file: `apps/web/src/app/admin/sales-performance/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Nama" / Then fetch `sort=name&order=desc&cursor=0`; klik KEDUA kali / Then `sort=name&order=asc&cursor=0`; ubah period / Then cursor=0; pindah halaman / Then sort+period dipertahankan; setelah sort Nama berubah, baris yang DIrender mengikuti urutan server (achievement client-side TIDAK meng-override hasil sort Nama) dan request TIDAK pernah mengandung `sort=achievement`.
   Exercise through: render + fireEvent
   Test doubles: fetch mock
   Expected RED: param sort/cursor tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/sales-performance/page.test.tsx`
6. Implement → PASS → refactor → commit.

7. Write failing test for: density + dummy parity
   Test file: `apps/web/src/app/admin/sales-performance/page.test.tsx`
   Level: integration
   Test intent: Given density toggle + dummy mode / Then class+persist; dummy achievement DESC + offset + total=filtered.length + summary `{total}`.
   Exercise through: render + fireEvent; dummy branch
   Test doubles: fetch mock; dummy flag
   Expected RED: density/dummy sort belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/sales-performance/page.test.tsx`
9. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (sales-performance), "Sort allowlist per controller" (sales-performance catatan).

## WHY THIS APPROACH
Complexity: deep
Justification: Sort `achievement` = client-side (derived); header Nama = server sort; dua mekanisme sort dalam satu halaman.

## SANDWICH CONTEXT
[CRITICAL: achievement sort client-side (bukan server); server sort hanya name/id; jangan ubah parseMoney/formatRupiah/formatPercentage]
You are implementing sales-performance page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/sales-performance/page.tsx`, `apps/web/src/app/admin/sales-performance/api.ts`, `apps/web/src/app/admin/sales-performance/page.test.tsx`
Available after: T4, T10
Architecture rule: period = filter param (reset → cursor=0); achievement sort client-side.
[RESTATE: achievement sort client-side (bukan server); server sort hanya name/id; jangan ubah parseMoney/formatRupiah/formatPercentage]

## DELIVERABLE
Given load, When selesai, Then achievement tertinggi pertama + summary total.
Given klik header Nama, When onSort, Then fetch sort=name&order=desc&cursor=0.
Given klik header Nama kedua kali, When onSort, Then fetch sort=name&order=asc&cursor=0.
Given ubah period, When terapkan, Then cursor=0 + period baru.
Given pindah halaman, When jump, Then sort+period dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `parseMoney` dipakai untuk banding achievement numerik (bukan string compare).
  - Tidak menambah kolom `Dibuat`/`Diperbarui` (baris agregat, bukan entitas) — kolom `Periode` tetap.
Must-not-have:
  - Mengirim `sort=achievement` ke server.
Rollback note:
  - Revert page+api.
Red flags:
  - Sort achievement ke backend → STOP.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: achievement tipe string/number → parseMoney menormalisasi (existing).
Escalate when: perlu agregasi achievement server.

---

### Task 16: Orders page — sort/paging/summary/timestamps/density [depends: T4, T8]

## OBJECTIVE
Upgrade `orders/page.tsx` (fetch inline, tanpa api.ts): sort state (default created_at DESC), cursor offset, summary total, kolom `Dibuat`, density; kirim `sort`/`order`/`cursor` ke `/admin/orders`, baca `meta.total`; Table props sortable + local sort state. Tidak ada dummy branch (orders pakai fetch langsung).

Steps:
1. Write failing test for: default sort + summary total + timestamp kolom
   Test file: `apps/web/src/app/admin/orders/page.test.tsx`
   Level: integration
   Test intent: Given fetch mock return orders + `meta.total` / When load / Then baris pertama terbaru, summary "N pesanan", kolom Dibuat terformat; approve/detail flow tetap.
   Exercise through: render `<AdminOrdersPage />` (mocked fetch)
   Test doubles: fetch + getStoredToken mock
   Expected RED: summary/kolom/sort belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/orders/page.test.tsx`
3. Implement sort default + summary + kolom + meta.total parse → PASS → refactor → commit.

4. Write failing test for: sort header + paging offset
   Test file: `apps/web/src/app/admin/orders/page.test.tsx`
   Level: integration
   Test intent: Given klik header "Status" / Then fetch `sort=status&order=desc&cursor=0`; klik KEDUA kali / Then `sort=status&order=asc&cursor=0`; pindah halaman 2 / Then `cursor=100` + sort dipertahankan.
   Exercise through: render + fireEvent
   Test doubles: fetch mock (assert URL)
   Expected RED: param sort/cursor tidak terkirim.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/orders/page.test.tsx`
6. Implement sort header + paging → PASS → refactor → commit.

7. Write failing test for: density toggle
   Test file: `apps/web/src/app/admin/orders/page.test.tsx`
   Level: integration
   Test intent: Given density toggle / When pilih Compact / Then class + persist.
   Exercise through: render + fireEvent
   Test doubles: fetch mock
   Expected RED: density belum ada.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/orders/page.test.tsx`
9. Implement density → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: Story 1–5 (orders), "Table.tsx generic contract" (orders pakai props sama).

## WHY THIS APPROACH
Complexity: standard
Justification: Halaman orders fetch inline (tanpa api.ts) — perubahan terkonsentrasi di page + komponen generik.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah approve/cancel/show logic; Table props sama dgn halaman lain; summary = total saja]
You are implementing orders page upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/web/src/app/admin/orders/page.tsx`, `apps/web/src/app/admin/orders/page.test.tsx`
Available after: T4, T8
Architecture rule: cursor offset; summary total; sort local state via Table props.
[RESTATE: jangan ubah approve/cancel/show logic; Table props sama dgn halaman lain; summary = total saja]

## DELIVERABLE
Given load orders, When selesai, Then terbaru pertama + summary total + kolom Dibuat.
Given klik header Status, When onSort, Then fetch sort=status&order=desc&cursor=0.
Given klik header Status kedua kali, When onSort, Then fetch sort=status&order=asc&cursor=0.
Given pindah halaman 2, When jump, Then cursor=100 + sort dipertahankan.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `approve()` → `loadOrders()` tetap dipanggil (refresh data).
Must-not-have:
  - Menyentuh `OrderForm` / `Order` type / statusHistory render.
Rollback note:
  - Revert page.
Red flags:
  - Mengubah approve/cancel endpoint → STOP.

## STOP CONDITIONS
Done when: 3 test groups PASS.
Uncertain when: `meta.total` absent → fallback estimasi.
Escalate when: perlu api.ts terpisah untuk orders.

---

### Task 17: Integration — admin table contract across pages + dummy parity [depends: T11, T12, T13, T14, T15, T16] [test-risk]

## OBJECTIVE
Tulis integration lintas-halaman yang membuktikan kontrak paging/sort/summary/density konsisten di dummy mode, dan dummy branch parity dengan real path. Buat `apps/web/src/__tests__/admin-table-integration.test.tsx`. (Guard `withDummyRead`/`commitIfCurrent`/`useDummyRefresh` SUDAH tercakup di `apps/web/src/dummy/guards.test.ts` + `apps/web/src/app/dashboard/dummy-guard.test.tsx` — tidak diulang.)

Steps:
1. Write failing test for: dummy mode — sort+paging+summary parity lintas halaman
   Test file: `apps/web/src/__tests__/admin-table-integration.test.tsx`
   Level: integration
   Test intent: Given dummy mode ON (store flag true) / When tiap admin page difetch via loader dummy branch / Then urutan created_at DESC (id tiebreak), `total === filtered.length`, `summary` konsisten, offset slice benar; sort invalid → fallback default tanpa throw.
   Exercise through: `fetchAdminOutlets/fetchProducts/fetchAdminUsers/fetchPromotions/fetchAdminSalesPerformance` dummy branch + render page
   Test doubles: fetch di-stub agar tetap zero-network (assert fetch TIDAK dipanggil saat dummy ON)
   Expected RED: dummy branch belum implement sort/total parity → assert gagal.
2. Run test — verify FAIL: `cd apps/web && npx jest src/__tests__/admin-table-integration.test.tsx`
3. Implement/fix parity → PASS → refactor → commit.

4. Write failing test for: real path — sort/cursor param diteruskan + total dipakai
   Test file: `apps/web/src/__tests__/admin-table-integration.test.tsx`
   Level: integration
   Test intent: Given dummy OFF + fetch mock return `meta.total` / When page difetch dgn sort/cursor / Then URL berisi `sort`/`order`/`cursor`; label paging memakai total (bukan estimasi) bila ada.
   Exercise through: loader real path (mocked fetch)
   Test doubles: fetch mock (assert query)
   Expected RED: param sort/cursor belum diteruskan.
5. Run test — verify FAIL: `cd apps/web && npx jest src/__tests__/admin-table-integration.test.tsx`
6. Implement/fix → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Dummy-mode parity", "Kontrak total", "Kontrak cursor", "Reset state", semua GWT lintas-halaman.

## WHY THIS APPROACH
Complexity: deep
Justification: Cross-Unit Verification — GWT paging/sort hanya benar saat loader + dummy + kontrak backend kolaborasi; harness sendiri (dummy-mode e2e) menjustifikasi task terpisah.

## SANDWICH CONTEXT
[CRITICAL: dummy path tetap zero-network & lolos guard; jangan reimplement sort/paginate (pakai T2)]
You are implementing integration verification untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B; parity dummy = real.
Files in scope: `apps/web/src/__tests__/admin-table-integration.test.tsx`
Available after: T11–T16 (semua page + api)
Architecture rule: assert fetch TIDAK dipanggil saat dummy ON; comparator/paginate dari T2.
[RESTATE: dummy path tetap zero-network & lolos guard; jangan reimplement sort/paginate (pakai T2)]

## DELIVERABLE
Given dummy ON, When fetch list, Then sort/total/summary/offset parity + zero-network.
Given dummy OFF + total ada, When paging dirender, Then label pakai total (bukan estimasi).
Given dummy OFF + param sort/cursor, When fetch, Then URL query berisi sort/order/cursor.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Assert fetch TIDAK dipanggil saat dummy ON (zero-network invariant).
Must-not-have:
  - Test yang mengetes source code (hanya behavior via public boundary).
  - Reimplement comparator/paginate di test.
Open question risks:
  - `dummyEntities` orders/products seeding deterministik di test — pastikan setDummyGenerator dipakai.
Rollback note:
  - Hapus file test baru; revert guard test.
Red flags:
  - Test memukul network asli → STOP.

## STOP CONDITIONS
Done when: 2 test groups PASS (dummy parity, real-path param).
Correctness note: T17 memakai jsdom + fetch stub (bukan browser) — level = integration, bukan E2E.
Uncertain when: dummy store tidak bisa di-set flag di jsdom → pakai `setDummyGenerator`/`useDummyStore.setState`.
Escalate when: parity tidak mungkin tanpa menyentuh pipeline.

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Backend ListQuery helper | prereq | lightweight | allowlist fallback + rawOrder nulls-last + offset/meta |
| T2 | Frontend admin-table helper | prereq | lightweight | normalizeSort + compareRows nulls-last + paginate + label |
| T3 | Table.tsx density + sortable header | T2 | standard | aria-sort + onSort + Aksi inert + density padding |
| T4 | Table controls (pagination/summary/density) | T2, T3 | standard | label posisi + next disabled + summary + persist |
| T5 | AdminOutletController list | T1 | standard | default terbaru + total + summary + invalid fallback |
| T6 | UserRoleController list | T1 | lightweight | total + role filter + fallback |
| T7 | PromotionController list | T1 | standard | offset cursor + summary 4-state |
| T8 | OrderController list | T1 | standard | total + offset + guard 403 tetap |
| T9 | ProductController list (public) | T1 | standard | default id ASC preserved + additive total |
| T10 | SalesPerformanceController list | T1 | standard | offset + name/id sort + achievement ditolak |
| T11 | Outlets page | T4, T5 | deep | sort+paging+summary+timestamp+density+reset |
| T12 | Products page | T4, T9 | standard | default terbaru + summary stok + search reset |
| T13 | Users page | T4, T6 | standard | role filter reset + timestamp |
| T14 | Promotions page | T4, T7 | standard | offset cursor + summary 4-state |
| T15 | Sales-performance page | T4, T10 | deep | achievement client-side + Nama server sort |
| T16 | Orders page | T4, T8 | standard | inline fetch sort + paging + density |
| T17 | Integration + dummy parity | T11–T16 | deep | zero-network parity + total label |
