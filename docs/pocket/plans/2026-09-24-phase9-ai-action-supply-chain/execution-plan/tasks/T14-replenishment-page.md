# Task T14 — Halaman replenishment (generate/approve/execute)

**Phase:** 4
**Depends:** T10
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 14: Halaman replenishment

## OBJECTIVE
Bangun halaman admin `/admin/supply-chain` untuk generate draft plan, approve, execute,
dan melihat draft PO per supplier.

Steps:
1. Write failing web tests.
   Test file: `apps/web/src/app/admin/supply-chain/page.test.tsx`
   Level: component/integration
   Test intent: Given plans response / When page renders / Then generate button + plan
   table with approve/execute by status; executed plan shows PO reference; API error → alert.
   Exercise through: page + `apps/web/src/lib/replenishment-api.ts`.
   Test doubles: fetch mock.
   Expected RED: page/client belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/supply-chain/page.test.tsx`
3. Implement page + client → PASS → refactor → commit.
4. Write failing empty-data test: no plans → informative empty state.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec AC-8/AC-9; T10 endpoints; admin table contracts.

## WHY THIS APPROACH
Complexity: medium
Justification: surface approval keputusan replenishment yang auditable.

## SANDWICH CONTEXT
[CRITICAL: 'use client'; admin guard; no direct stock mutation from FE]
Files in scope: `apps/web/src/app/admin/supply-chain/page.tsx`, `page.test.tsx`,
`apps/web/src/lib/replenishment-api.ts`.
Available after: T10.
Architecture rule: dummy parity di T16; envelope API; tsc clean.

## DELIVERABLE
- Generate/approve/execute UI + PO reference view + empty/error states.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: status-aware actions; error alert; zero-data safe.
Must-not-have: bypass approval (hidden execute button tanpa approved).
Rollback note: hide route via menu.

## STOP CONDITIONS
Done when: render/generate/approve/execute/empty tests PASS + tsc clean.
Escalate when: PO reference format belum ditentukan backend.
