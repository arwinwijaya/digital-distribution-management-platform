# Task T7 — Execute `draft_order` via `OrderCreationService`

**Phase:** 2
**Depends:** T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: Execute `draft_order`

## OBJECTIVE
Implementasikan endpoint execute yang hanya menerima action `approved`, memanggil
`OrderCreationService::create()` dengan idempotency identity action, menyimpan result,
dan menjaga no-partial-state saat gagal.

Steps:
1. Write failing feature tests.
   Test file: `apps/api/tests/Feature/ExecuteDraftOrderTest.php`
   Level: feature/integration
   Test intent: Given approved draft_order / When execute / Then exactly one Order via
   existing service, action `executed`, result order_id, audit `executed`; replay tidak
   tambah order. Draft/pending/rejected → 422. Insufficient stock → action `failed`, no
   partial action/order state.
   Test doubles: factories Product/Outlet/User + mock/spied OrderCreationService where needed.
   Expected RED: execute route/service belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ExecuteDraftOrderTest`
3. Implement transaction + route `POST /{id}/execute`; jangan edit OrderCreationService → PASS → commit.
4. Write failing race/idempotency test (two execute requests) memakai barrier/pola existing.
5. Run test — verify FAIL → implement lock/retry → PASS → commit.

## REFERENCES LOADED
Spec DD-2/DD-3; AC-3; `OrderCreationService.php`; `OrderController::runApprovalTransaction()`.

## WHY THIS APPROACH
Complexity: high
Justification: reuse menjaga credit/stock/idempotency invariant; action menjadi orchestration layer.

## SANDWICH CONTEXT
[CRITICAL: status harus approved; jangan reserve stock sendiri; actor audit]
Files in scope: `RecommendationActionService.php`, controller/routes, test.
Available after: T6.
Architecture rule: transaction, row lock, existing service, `idempotency_key` derived/stored.

## DELIVERABLE
- Approved draft_order execute + result + audit + replay/race-safe.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: no execute before approval; exactly one order; failure no partial state.
Must-not-have: modify `OrderCreationService`; direct Product stock mutation.
Rollback note: action execution endpoint can be disabled; existing orders unaffected.

## STOP CONDITIONS
Done when: approval gate, success, failure, replay, race tests PASS.
Escalate when: one transaction tidak dapat mencakup action + existing service tanpa deadlock.
