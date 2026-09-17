# Task T11 — Outlets page — sort/paging/summary/timestamps/density

**Phase:** 3
**Depends:** T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
