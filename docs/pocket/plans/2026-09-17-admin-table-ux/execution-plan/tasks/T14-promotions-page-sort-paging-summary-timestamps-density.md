# Task T14 — Promotions page — sort/paging/summary/timestamps/density

**Phase:** 4
**Depends:** T4, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
