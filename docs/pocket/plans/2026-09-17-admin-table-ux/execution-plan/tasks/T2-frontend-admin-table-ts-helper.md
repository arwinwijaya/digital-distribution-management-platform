# Task T2 — Frontend `admin-table.ts` helper

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
