# Task T15 — Dashboard eksperimen + revenue lift

**Phase:** 4
**Depends:** T12
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 15: Dashboard eksperimen

## OBJECTIVE
Bangun halaman admin `/admin/ai-experiments` untuk daftar eksperimen, assignment, dan
grafik/ringkasan revenue lift termasuk status `insufficient-data`.

Steps:
1. Write failing web tests.
   Test file: `apps/web/src/app/admin/ai-experiments/page.test.tsx`
   Level: component/integration
   Test intent: Given experiments + lift response / When renders / Then uplift cards +
   sample status; insufficient-data shows pending notice, not zero/perfect.
   Exercise through: page + `apps/web/src/lib/experiments-api.ts`.
   Test doubles: fetch mock.
   Expected RED: page/client belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/ai-experiments/page.test.tsx`
3. Implement page + client → PASS → refactor → commit.
4. Write failing decimal-safe test: large currency values render correctly.
5. Run test — verify FAIL → implement formatting → PASS → commit.

## REFERENCES LOADED
Spec AC-8/AC-6; T12 services; `MeasurementCards` patterns.

## WHY THIS APPROACH
Complexity: medium
Justification: dashboard menjawab klaim revenue dengan data, bukan narasi.

## SANDWICH CONTEXT
[CRITICAL: jangan tampilkan lift sebagai persen sempurna saat insufficient; format Rupiah]
Files in scope: `apps/web/src/app/admin/ai-experiments/page.tsx`, `page.test.tsx`,
`apps/web/src/lib/experiments-api.ts`.
Available after: T12.
Architecture rule: dummy parity di T16; envelope; tsc clean.

## DELIVERABLE
- Experiment list + lift cards + sample status + safe currency formatting.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: insufficient-data notice; no misleading zero lift; accessible tables.
Must-not-have: claim lift tanpa sampel/metode.
Rollback note: hide route via menu.

## STOP CONDITIONS
Done when: render/lift/insufficient/format tests PASS + tsc clean.
Escalate when: metrik lift bisnis berubah (GMV vs unit).
