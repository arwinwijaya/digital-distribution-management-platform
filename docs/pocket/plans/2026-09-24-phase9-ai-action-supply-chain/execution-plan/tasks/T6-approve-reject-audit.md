# Task T6 — Approve/reject + audit append-only

**Phase:** 2
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Approve/reject workflow

## OBJECTIVE
Buat service/controller endpoint approve dan reject dengan row lock, transisi legal,
actor attribution, idempotent retry, serta satu audit event per transisi.

Steps:
1. Write failing feature tests.
   Test file: `apps/api/tests/Feature/RecommendationActionApprovalTest.php`
   Level: feature + transaction/concurrency
   Test intent: Given `draft` / When approve / Then `approved`, actor/time terisi,
   satu event `approved`; reject membutuhkan reason → `rejected` + event. Approve ulang
   status approved tidak menggandakan event; status rejected/executed → 422; non-admin 403.
   Test doubles: factory action, actingAs admin/non-admin.
   Expected RED: endpoints/service belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RecommendationActionApprovalTest`
3. Implement `approve()`/`reject()` dalam service + controller route dengan `lockForUpdate`/
   retry QueryException mengikuti `OrderController::runApprovalTransaction` → PASS → commit.
4. Write failing test actor tidak dapat dipalsukan dari request body + reason wajib.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec DD-5; AC-2, AC-10; `OrderController.php:runApprovalTransaction`; `ConcurrencyTestBarrier`.

## WHY THIS APPROACH
Complexity: high
Justification: transisi approval adalah critical section; pola order approval sudah terbukti.

## SANDWICH CONTEXT
[CRITICAL: status transition + audit dalam transaksi yang sama; append-only]
Files in scope: `RecommendationActionService.php`, `RecommendationActionController.php`,
`routes/api.php`, test. Available after: T5.
Architecture rule: admin-only `rbac:ai_actions:edit`; lock row; retry unique/transaction errors.

## DELIVERABLE
- `POST /{id}/approve` + `/reject`, legal transitions, audit, idempotent replay.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: no duplicate audit on replay; rejection reason; actor session.
Must-not-have: execute during approval; update/delete audit row.
Rollback note: disable endpoints via config/menu.

## STOP CONDITIONS
Done when: transition, invalid, auth, replay, concurrency tests PASS.
Escalate when: SQLite locking behavior membuat test tidak deterministik.
