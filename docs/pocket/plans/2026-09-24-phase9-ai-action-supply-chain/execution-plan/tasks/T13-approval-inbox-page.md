# Task T13 — Halaman inbox approval rekomendasi

**Phase:** 4
**Depends:** T7, T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 13: Halaman inbox approval

## OBJECTIVE
Bangun halaman admin `/admin/ai-actions` (inbox draft/pending/approved/executed) dengan
aksi approve/reject/execute mengikuti kontrak admin-table serta client API baru.

Steps:
1. Write failing web tests.
   Test file: `apps/web/src/app/admin/ai-actions/page.test.tsx`
   Level: component/integration (jest + RTL)
   Test intent: Given dummy/endpoint response / When page renders / Then table lists actions
   with approve/reject buttons for draft, execute for approved; error from API shows alert.
   Exercise through: page + `apps/web/src/lib/ai-actions-api.ts`.
   Test doubles: fetch mock / MSW.
   Expected RED: page/client belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/ai-actions/page.test.tsx`
3. Implement page + client + table → PASS → refactor → commit.
4. Write failing access test: non-admin redirects/hides actions.
5. Run test — verify FAIL → implement guard → PASS → commit.

## REFERENCES LOADED
Spec AC-8/AC-9; `/data-intelligence/page.tsx` pola; admin table contracts.

## WHY THIS APPROACH
Complexity: medium
Justification: reuse pola halaman admin existing + komponen table agar konsisten UX.

## SANDWICH CONTEXT
[CRITICAL: 'use client'; admin guard; envelope response; tsc clean]
Files in scope: `apps/web/src/app/admin/ai-actions/page.tsx`, `page.test.tsx`,
`apps/web/src/lib/ai-actions-api.ts`, test helper.
Available after: T7 + T8.
Architecture rule: dummy parity disiapkan di T16; halaman harus tetap render dengan zero-data.

## DELIVERABLE
- Inbox + approve/reject/execute actions dengan status badge dan error handling.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: sort/paging/ringkasan table; actor-safe (token admin); no direct fetch bypass client.
Must-not-have: inline business logic di page; hardcode URL API.
Rollback note: hide menu route tanpa hapus code.

## STOP CONDITIONS
Done when: render/action/access/error tests PASS + `tsc --noEmit` clean.
Escalate when: kontrak admin-table belum didefinisikan untuk module baru.
