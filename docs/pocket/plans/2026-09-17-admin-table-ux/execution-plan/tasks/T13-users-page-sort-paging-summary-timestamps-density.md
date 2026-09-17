# Task T13 — Users page — sort/paging/summary/timestamps/density

**Phase:** 4
**Depends:** T4, T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
