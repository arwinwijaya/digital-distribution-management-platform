# Task T4 — Implement AIController::storeDiscoveryEvent() + tracking idempotency

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Implement AIController::storeDiscoveryEvent() + tracking idempotency [depends: T3]

## OBJECTIVE
Implement outlet tracking endpoint reusing RecommendationEvent model, with idempotent replay logic matching MeasurementController pattern.

Steps:
1. Write failing test for: test_outlet_discovery_event_tracking_recorded
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet authenticated, When POST /api/ai/discovery/events with event_type=displayed + product_id, Then 201 with event recorded
   Exercise through: `POST /api/ai/discovery/events` via authenticated outlet client
   Test doubles: none
   Expected RED: endpoint missing

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_event_tracking_recorded`

3. Write failing test for: test_outlet_discovery_event_idempotent_replay
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given same event_uuid sent twice with identical payload, When POST twice, Then second returns 200 with idempotent_replay=true
   Exercise through: `POST /api/ai/discovery/events` via authenticated outlet client
   Test doubles: none
   Expected RED: idempotency not implemented

4. Run test — verify FAIL

5. Write failing test for: test_outlet_discovery_event_conflict_409
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given same event_uuid sent with different attributes, When POST twice, Then second returns 409
   Exercise through: `POST /api/ai/discovery/events` via authenticated outlet client
   Test doubles: none
   Expected RED: conflict detection not implemented

6. Run test — verify FAIL

7. Write failing test for: test_outlet_discovery_event_non_outlet_forbidden
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given admin authenticated, When POST /api/ai/discovery/events, Then 403
   Exercise through: `POST /api/ai/discovery/events` via authenticated admin client
   Test doubles: none
   Expected RED: role check not implemented

8. Run test — verify FAIL

9. Implement `AIController::storeDiscoveryEvent()`:
   - Derive `outlet_id` from token (outlet-only, reuse explicit isOutlet() check like discovery())
   - Validate: event_uuid (required, string, max 128), event_type (required, in: displayed,clicked,cart), product_id (required, exists:products), occurred_at (required, date)
   - Idempotent replay logic: check existing RecommendationEvent by event_uuid; if identical → 200 with idempotent_replay=true; if conflicting → 409
   - Create RecommendationEvent with outlet_id from token, metadata.source='discovery_v1'
   - Return 201 on create, 200 on replay

10. Run all 4 tests — verify PASS

11. Refactor while green: extract idempotency logic to shared service/helper if MeasurementController and this share code (avoid duplication)

12. Commit: `git commit -m "feat(api): implement outlet discovery event tracking with idempotent replay"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rule: tracking funnel outlet

## WHY THIS APPROACH
Complexity: standard
Justification: Reuses existing RecommendationEvent model + MeasurementController idempotency pattern; outlet-only scoping; multiple GWT scenarios. Sequential after T3 because both modify AIController.php.

## SANDWICH CONTEXT
[CRITICAL: Must reuse RecommendationEvent model without migration; outlet-only scoping; fire-and-forget from client perspective]
You are implementing Task 4 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A (new endpoint under /api/ai/discovery/events)
Files in scope: apps/api/app/Http/Controllers/AIController.php, apps/api/tests/Feature/AITest.php
Available after: T3 (AIController scoping pattern established)
Architecture rule: Fire-and-forget tracking; idempotent replay per MeasurementController pattern
[RESTATE: Must NOT touch auth/RBAC, existing DB schema]

## DELIVERABLE
Given outlet authenticated + valid payload, When POST /api/ai/discovery/events, Then 201 event recorded
Given same event_uuid identical payload, When POST twice, Then 200 with idempotent_replay=true
Given same event_uuid conflicting payload, When POST twice, Then 409
Given non-outlet authenticated, When POST /api/ai/discovery/events, Then 403

## QUALITY BAR
Must-have:
  - outlet_id derived from token (not body)
  - Idempotent replay matches MeasurementController logic
  - metadata.source = 'discovery_v1' for analytics filtering
  - Validation: event_type in displayed/clicked/cart, product_id exists

Must-not-have:
  - Auto-add to cart
  - Blocking on tracking failure
  - DB migration

Open question risks:
  - If MeasurementController idempotency extracted to helper, both must use it

Rollback note:
  - Revert AIController::storeDiscoveryEvent to stub

## STOP CONDITIONS
Done when: all 4 tests pass, idempotency works
Escalate when: RecommendationEvent schema change needed
