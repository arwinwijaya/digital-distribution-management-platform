# Task T7 — WhatsApp Promotion Broadcast (F6)

**Phase:** 2
**Depends:** T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: WhatsApp Promotion Broadcast (F6) [depends: T5]

## OBJECTIVE
Implement promotion broadcast to eligible outlets via WhatsApp, using existing WhatsAppOutboundService + logical_key dedup pattern.

Files:
- Modify: `apps/api/app/Services/WhatsAppOutboundService.php` — add `broadcastPromotion()` method
- Modify: `apps/api/app/Models/WhatsAppMessage.php` — add `promo_broadcast` to message_type awareness
- Create: `apps/api/app/Http/Controllers/PromotionBroadcastController.php`
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/PromotionBroadcastTest.php`

Steps:
1. Write failing tests for: Broadcast targeting
   Test file: `apps/api/tests/Feature/PromotionBroadcastTest.php`
   Level: integration
   Test intent:
   Given 50 outlets with orders in last 30d, When broadcast triggered, Then 50 WhatsAppMessage records created with type=promo_broadcast
   Given outlet with last order >30d ago, When broadcast triggered, Then excluded
   Exercise through: HTTP POST /admin/promotions/{id}/broadcast
   Test double: factory outlets with orders, mock WhatsAppClient
   Expected RED: broadcast endpoint does not exist; WhatsAppOutboundService::broadcastPromotion does not exist

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected failure: Route not defined

3. Implement WhatsAppOutboundService::broadcastPromotion(Promotion $promo) that:
   - Queries outlets with >=1 order in last 30d (using scopePurchasable to exclude inactive-supplier products)
   - Creates WhatsAppMessage records with logical_key="promo-broadcast:{promo_id}"
   - Uses insertOrIgnore for idempotency
   - Dispatches delivery for each message
   - Returns count of messages created
   Implement PromotionBroadcastController::broadcast (admin-only).
   File: `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Http/Controllers/PromotionBroadcastController.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected: PASS

5. Write failing tests for: Broadcast idempotency
   Test file: `apps/api/tests/Feature/PromotionBroadcastTest.php`
   Level: integration
   Test intent:
   Given promo already broadcast, When broadcast again, Then 200 already_sent (not error)
   Given provider failure after partial send, When retry, Then only failed messages re-sent
   Exercise through: HTTP POST /admin/promotions/{id}/broadcast (twice)
   Test doubles: mock WhatsAppClient
   Expected RED: idempotency check not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected failure: second broadcast creates duplicate messages

7. Implement idempotency check: if logical_key exists and status=sent, return already_sent.
   Implement retry: only re-send messages with status=failed.
   File: `apps/api/app/Services/WhatsAppOutboundService.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
   Expected: PASS

9. Write failing tests for: Message content
   Test file: `apps/api/tests/Feature/PromotionBroadcastTest.php`
   Level: integration
   Test intent:
   Given promo with name/discount/dates/min_order, When broadcast, Then each message body contains promo details + outlet name
   Given broadcast completes, When checking messages, Then type=promo_broadcast
   Given 0 eligible outlets, When broadcast, Then 200 with count=0
   Exercise through: broadcast flow
   Test doubles: mock WhatsAppClient
   Expected RED: message body not formatted with promo details

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
    Expected failure: message body missing promo details

11. Format message body with promo name, discount value, valid dates, min_order, outlet name.
    Set broadcast_at on promo after successful broadcast.
    File: `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Models/Promotion.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PromotionBroadcastTest`
    Expected: PASS

13. Refactor while green: Ensure broadcast uses DB transaction for atomicity.
14. Commit:
    `git add apps/api/app/Services/WhatsAppOutboundService.php apps/api/app/Models/WhatsAppMessage.php apps/api/app/Http/Controllers/PromotionBroadcastController.php apps/api/routes/api.php apps/api/tests/Feature/PromotionBroadcastTest.php apps/api/app/Models/Promotion.php`
    `git commit -m "feat(whatsapp): add promotion broadcast with targeting, idempotency, and message formatting"`

## REFERENCES LOADED
- spec — F6 WhatsApp Promotion Broadcast rules, all GWT scenarios
- apps/api/app/Services/WhatsAppOutboundService.php — existing logical_key dedup, dispatchDelivery pattern
- apps/api/app/Models/WhatsAppMessage.php — existing message_type, status fields
- apps/api/app/Models/Promotion.php — existing broadcast_at field (from T5)

## WHY THIS APPROACH
Reuses WhatsAppOutboundService + logical_key dedup pattern exactly. broadcast_at on promo tracks broadcast state. insertOrIgnore prevents duplicate messages.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Broadcast MUST use existing WhatsAppOutboundService + logical_key dedup; targeting uses scopePurchasable to exclude inactive-supplier products]
You are implementing WhatsApp Promotion Broadcast (F6) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Extend WhatsAppOutboundService with broadcastPromotion(); logical_key dedup
Files in scope: apps/api/app/Services/WhatsAppOutboundService.php, apps/api/app/Models/WhatsAppMessage.php, apps/api/app/Http/Controllers/PromotionBroadcastController.php, apps/api/routes/api.php, apps/api/tests/Feature/PromotionBroadcastTest.php, apps/api/app/Models/Promotion.php
Available after: T5 (promotions table exists)
Architecture rule: logical_key dedup; insertOrIgnore; admin-only broadcast; targeting by order history not is_active flag
[RESTATE: Broadcast MUST use existing WhatsAppOutboundService + logical_key dedup pattern]

## DELIVERABLE
Given 50 outlets with orders in last 30d, When broadcast, Then 50 messages created
Given outlet with last order >30d ago, When broadcast, Then excluded
Given promo already broadcast, When broadcast again, Then 200 already_sent
Given provider failure, When retry, Then only failed messages re-sent
Given promo details, When broadcast, Then message body contains promo info + outlet name
Given broadcast completes, When checking messages, Then type=promo_broadcast
Given 0 eligible outlets, When broadcast, Then 200 with count=0

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- logical_key = "promo-broadcast:{promo_id}" for idempotency
- Targeting by order history (last 30d), not is_active flag
- Message body includes promo name, discount, dates, min_order, outlet name
- broadcast_at set on promo after successful broadcast
- Admin-only access

Must-not-have:
- Duplicate messages on double-POST
- Targeting inactive-supplier products
- Modifications to WhatsApp inbound webhook

## STOP CONDITIONS
Done when: all F6 GWT scenarios pass, tests green, commit created
Escalate when: idempotency broken or targeting incorrect
