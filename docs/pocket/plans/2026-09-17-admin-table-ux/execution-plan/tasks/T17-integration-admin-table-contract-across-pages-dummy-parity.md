# Task T17 — Integration — admin table contract across pages + dummy parity

**Phase:** 4
**Depends:** T11, T12, T13, T14, T15, T16
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
