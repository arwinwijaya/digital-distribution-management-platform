# Task T9 — `ReplenishmentService` generate draft plan dari `StockPlanningService`

**Phase:** 3
**Depends:** T1, T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: `ReplenishmentService` generate draft plan

## OBJECTIVE
Terjemahkan output `StockPlanningService` (read-only) menjadi **draft** `replenishment_plans`
+ items per (supplier, produk), idempotent per window + supplier, tanpa mutasi stok.

Steps:
1. Write failing unit tests.
   Test file: `apps/api/tests/Unit/ReplenishmentServiceTest.php`
   Level: unit
   Test intent: Given stock snapshot items with reorder signal / When `generate()` / Then
   draft plan + items with reorder_quantity dari StockPlanningService; no stock mutation;
   regenerate same window+supplier → no duplicate; item without supplier → data_sufficiency
   `insufficient`.
   Test doubles: snapshot rows fixture / StockPlanningService stub.
   Expected RED: service belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ReplenishmentServiceTest`
3. Implement service + idempotency (unique window/supplier) → PASS → commit.
4. Write failing test: plan kosong saat tidak ada reorder signal (safe empty).
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec DD-6; AC-4; `StockPlanningService.php`; `Supplier.php`; `ActiveDataSnapshotReader.php`.

## WHY THIS APPROACH
Complexity: medium
Justification: mempertahankan StockPlanningService read-only sekaligus mengaktifkan aksi
replenishment lewat entitas draft yang auditable.

## SANDWICH CONTEXT
[CRITICAL: jangan mutasi stok; jangan panggil order/purchase eksekusi di sini]
Files in scope: `apps/api/app/Services/ReplenishmentService.php`, test.
Available after: T1 + T4.
Architecture rule: idempotency per (window_start, window_end, supplier_id); read snapshot via reader.

## DELIVERABLE
- `generate(window, actor): ReplenishmentPlan` draft + items; empty-safe; idempotent.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: no stock mutation; deterministic; idempotent; supplier grouping.
Must-not-have: calling OrderCreationService / external supplier API.
Open question risks: A-2 (PO hanya dicatat, bukan integrasi ERP).
Rollback note: hapus/disable generate; plan draft tetap sebagai riwayat.

## STOP CONDITIONS
Done when: generate/idempotency/empty/insufficient tests PASS.
Uncertain when: mapping produk→supplier ambigu (multi-supplier) → pilih supplier utama + flag.
Escalate when: perlu entitas purchase order nyata di luar scope.
