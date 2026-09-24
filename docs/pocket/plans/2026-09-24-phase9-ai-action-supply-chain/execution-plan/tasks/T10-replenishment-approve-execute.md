# Task T10 — Replenishment approve/execute (draft PO)

**Phase:** 3
**Depends:** T9, T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 10: Replenishment approve/execute

## OBJECTIVE
Endpoint admin untuk approve dan execute `replenishment_plans`; execute mencatat draft PO
per supplier (referensi + items) hanya bila `approved`, dengan audit dan idempotency.

Steps:
1. Write failing feature tests.
   Test file: `apps/api/tests/Feature/ReplenishmentApproveExecuteTest.php`
   Level: feature
   Test intent: Given draft plan / When approve / Then approved + audit; execute before
   approve → 422; approved execute → status executed + PO record (supplier ref + items) +
   audit; replay idempotent; non-admin 403.
   Test doubles: factories Supplier/Product + snapshot fixture.
   Expected RED: controller/route belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ReplenishmentApproveExecuteTest`
3. Implement controller + service methods + routes `rbac:supply_chain:edit` → PASS → commit.
4. Write failing test: execute gagal (item insufficient) → plan `failed`, tanpa PO parsial.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec AC-4, AC-10; DD-6; pola approve/execute T6/T7.

## WHY THIS APPROACH
Complexity: medium-high
Justification: mengulang pola approval-gated yang konsisten dan auditable.

## SANDWICH CONTEXT
[CRITICAL: no stock mutation; PO hanya catatan; execute hanya bila approved]
Files in scope: `ReplenishmentService.php`, `ReplenishmentPlanController.php`, routes, test.
Available after: T9 + T6.
Architecture rule: admin `rbac:supply_chain:edit`; transaction + lock; append-only audit.

## DELIVERABLE
- approve/execute endpoints + PO record + audit + idempotency + failure handling.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: gate approval; no partial PO; audit; admin-only.
Must-not-have: direct stock/supplier external mutation.
Rollback note: disable endpoints; drafts retained.

## STOP CONDITIONS
Done when: approve/execute/gate/failure/replay tests PASS.
Escalate when: PO record butuh tabel baru di luar `replenishment_plan_items`.
