# Task T5 — Promotion Management (F4)

**Phase:** 2
**Depends:** T3, T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: Promotion Management (F4) [depends: T3, T4]

## OBJECTIVE
Implement promotion CRUD with overlap checking, min order threshold, promo snapshot at order creation, and immutability after broadcast.

Files:
- Create: `apps/api/app/Models/Promotion.php`
- Create: `apps/api/app/Http/Controllers/PromotionController.php`
- Create: `apps/api/app/Http/Requests/StorePromotionRequest.php`
- Create: `apps/api/app/Http/Requests/UpdatePromotionRequest.php`
- Create: `apps/api/app/Services/PromotionService.php`
- Create: `apps/api/database/migrations/2026_09_16_000007_create_promotions_table.php`
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/PromotionTest.php`

Steps:
1. Write failing tests for: Promotion CRUD
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent:
   Given admin, When creating promotion with valid data, Then created with is_active=true
   Given promotion with end_date in past, When checked, Then is_active can be false
   Given admin, When deleting a non-broadcast promotion, Then removed (200/204)
   Exercise through: HTTP POST /admin/promotions
   Test doubles: factory admin, factory product
   Expected RED: promotions table does not exist; route not defined

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected failure: table not found

3. Create promotions table migration + Promotion model + factory.
   Implement PromotionController::store, ::index (limit+1 pagination), ::show, ::update, ::destroy.
   Implement StorePromotionRequest, UpdatePromotionRequest.
   File: all new files listed above

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected: PASS

5. Write failing tests for: Single promo per product per time (overlap check)
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent:
   Given product A with active promo P1 (Sept 1-30), When creating P2 for same product with overlapping dates (Sept 15-Oct 15), Then 422 conflict
   Given product A promo P1 (Sept) and P2 (Oct, non-overlapping), When created, Then both valid
   Exercise through: HTTP POST /admin/promotions
   Test doubles: factory promotions
   Expected RED: overlap check not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected failure: P2 created without conflict error

7. Implement PromotionService::assertNoOverlappingPromotion using DB query (check for active promos on same product with overlapping date ranges).
   File: `apps/api/app/Services/PromotionService.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionTest`
   Expected: PASS

9. Write failing tests for: Min order threshold
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent:
   Given promo with min_order=5 items, When outlet orders 4 items, Then promo not applied
   Given promo with min_order=5 items, When outlet orders 5 items, Then promo applied
   Exercise through: OrderCreationService with promo_id in request
   Test doubles: factory promo, factory products
   Expected RED: promo not applied to orders; min_order not checked

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected failure: promo_id not handled in order creation

11. Implement promo application in OrderCreationService: validate min_order (item count), calculate discount, apply to total.
    Credit check uses post-discount total.
    File: `apps/api/app/Services/OrderCreationService.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected: PASS

13. Write failing tests for: Promo snapshot at order creation
    Test file: `apps/api/tests/Feature/PromotionTest.php`
    Level: integration
    Test intent:
    Given promo active at order creation, When promo later deactivated, Then order keeps promo price
    Given promo created after order, When order approved, Then no retroactive discount
    Exercise through: order creation flow with promo
    Test doubles: factory order, factory promo
    Expected RED: promo snapshot not stored on order

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected failure: promo snapshot not persisted

15. Store promo snapshot (promo_id, discount_amount) on order at creation time. Ensure approval does not re-apply promo.
    File: `apps/api/app/Services/OrderCreationService.php`, `apps/api/app/Models/Order.php`

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected: PASS

17. Write failing tests for: Promo immutability after broadcast
    Test file: `apps/api/tests/Feature/PromotionTest.php`
    Level: integration
    Test intent:
    Given promo with broadcast_at set, When admin tries to update, Then 422
    Exercise through: HTTP PATCH /admin/promotions/{id}
    Test doubles: factory promo with broadcast_at
    Expected RED: immutability check not implemented

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected failure: update succeeds when it should be blocked

19. Add broadcast_at check to PromotionService::update — if broadcast_at is set, reject update with 422.
    File: `apps/api/app/Services/PromotionService.php`

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionTest`
    Expected: PASS

21. Refactor while green: Ensure overlap check uses DB transaction for consistency.
22. Commit:
    `git add apps/api/app/Models/Promotion.php apps/api/app/Http/Controllers/PromotionController.php apps/api/app/Http/Requests/ apps/api/app/Services/PromotionService.php apps/api/database/migrations/ apps/api/routes/api.php apps/api/tests/Feature/PromotionTest.php apps/api/app/Services/OrderCreationService.php apps/api/app/Models/Order.php`
    `git commit -m "feat(promotions): add promotion CRUD, overlap check, min_order, promo snapshot, and immutability"`

## REFERENCES LOADED
- spec — F4 Promotion Management rules, all GWT scenarios
- apps/api/app/Services/OrderCreationService.php — existing order pipeline (lock, credit check, stock reserve)
- apps/api/app/Models/Order.php — existing fillable (no promo fields yet)

## WHY THIS APPROACH
PromotionService centralizes overlap check and immutability. Promo snapshot stored on order at creation (not approval) per spec. Credit check uses post-discount total.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Promo applied at order creation only — no retroactive application on approval; single promo per product per time enforced by overlap check]
You are implementing Promotion Management (F4) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: PromotionService with overlap check; promo snapshot on order at creation
Files in scope: apps/api/app/Models/Promotion.php, apps/api/app/Http/Controllers/PromotionController.php, apps/api/app/Http/Requests/, apps/api/app/Services/PromotionService.php, apps/api/database/migrations/, apps/api/routes/api.php, apps/api/app/Services/OrderCreationService.php, apps/api/app/Models/Order.php
Available after: T3 (outlet scoring), T4 (product prices)
Architecture rule: Admin-only promo CRUD; single promo per product per time; min_order threshold; promo immutability after broadcast
[RESTATE: Promo applied at order creation only — no retroactive application on approval]

## DELIVERABLE
Given admin, When creating promo with valid data, Then created with is_active=true
Given promo with past end_date, When checked, Then is_active can be false
Given active promo P1 on product A, When creating P2 with overlapping dates, Then 422
Given non-overlapping promos on same product, When created, Then both valid
Given promo min_order=5, When ordering 4 items, Then promo not applied
Given promo min_order=5, When ordering 5 items, Then promo applied
Given promo active at creation, When deactivated later, Then order keeps promo price
Given promo created after order, When approved, Then no retroactive discount
Given promo broadcast_at set, When updating, Then 422

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Overlap check uses DB transaction for consistency
- Promo snapshot stored on order at creation
- Credit check uses post-discount total
- Broadcast_at check on update
- Admin-only access

Must-not-have:
- Promo stacking (single promo only)
- Retroactive promo application on approval
- Voucher codes

Open question risks:
- Promo edit after broadcast assumed blocked → if wrong: need versioned snapshot

## STOP CONDITIONS
Done when: all F4 GWT scenarios pass, tests green, commit created
Escalate when: overlap check has race condition or promo not applied correctly
