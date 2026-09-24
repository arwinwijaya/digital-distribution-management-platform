# Task T8 — Execute `draft_campaign` via `PromotionService`

**Phase:** 2
**Depends:** T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Execute `draft_campaign`

## OBJECTIVE
Tambahkan jalur execute campaign yang hanya approved dan memanggil `PromotionService::create()`
untuk overlap check + actor audit; simpan promotion id dan replay idempotent.

Steps:
1. Write failing feature tests.
   Test file: `apps/api/tests/Feature/ExecuteDraftCampaignTest.php`
   Level: feature/integration
   Test intent: Given approved draft_campaign / When execute / Then exactly one Promotion
   via service, action executed + promotion_id; replay no duplicate. Unapproved/invalid
   transition → 422; overlap/validation failure → action failed tanpa promo parsial.
   Test doubles: factories Product/User + mock/spied PromotionService.
   Expected RED: execute campaign branch belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ExecuteDraftCampaignTest`
3. Implement branch orchestration + route reuse T7 → PASS → commit.
4. Write failing test: actor request field diabaikan dan `created_by` session diteruskan.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec DD-2/DD-3; AC-3; `PromotionService.php` create/overlap/audit.

## WHY THIS APPROACH
Complexity: medium-high
Justification: reuse service existing mencegah bypass aturan overlap dan created_by.

## SANDWICH CONTEXT
[CRITICAL: hanya approved; panggil PromotionService, jangan `Promotion::create` langsung]
Files in scope: `RecommendationActionService.php`, controller/routes, test.
Available after: T6 (approval endpoint; T7 boleh paralel).
Architecture rule: transaction + action idempotency + actor session.

## DELIVERABLE
- Campaign execute dengan promotion result, audit, idempotent replay, failure status.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: overlap rejection; no partial promo; actor attribution.
Must-not-have: direct Promotion model mutation bypass service.
Rollback note: disable execute branch without affecting existing promotions.

## STOP CONDITIONS
Done when: success/gate/replay/overlap/actor tests PASS.
Escalate when: PromotionService return type tidak cocok untuk result persist.
