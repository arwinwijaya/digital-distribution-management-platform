# Task T4 — Table controls (Pagination/Summary/Density toggle + hook)

**Phase:** 3
**Depends:** T2, T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
