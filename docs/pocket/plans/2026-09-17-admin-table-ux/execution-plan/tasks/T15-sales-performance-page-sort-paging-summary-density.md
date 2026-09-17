# Task T15 — Sales-performance page — sort/paging/summary/density

**Phase:** 4
**Depends:** T4, T10
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
