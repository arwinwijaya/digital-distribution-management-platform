# Task T6 — Sales Force & Delivery

**Phase:** 2
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Sales Force & Delivery [depends: T4]

## OBJECTIVE
Implement sales visit planning and delivery management. This enables field operations.

Files:
- Create: `apps/api/app/Models/SalesVisit.php`
- Create: `apps/api/app/Models/Delivery.php`
- Create: `apps/api/app/Http/Controllers/SalesController.php`
- Create: `apps/api/app/Http/Controllers/DeliveryController.php`
- Create: `apps/web/app/sales/`
- Create: `apps/web/app/delivery/`
- Test: `apps/api/tests/Feature/SalesTest.php`
- Test: `apps/api/tests/Feature/DeliveryTest.php`

Steps:
1. Write failing test for: Sales visit planning
   Test file: `apps/api/tests/Feature/SalesTest.php`
   Level: integration
   Test intent: Given sales target exists, When planning visits, Then visit schedule is created
   Exercise through: POST /api/sales/visits
   Test doubles: mock calendar service
   Expected RED: Sales visit endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter SalesTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/SalesVisit.php`
   Implement: Sales visit model and controller

4. Run test — verify PASS: `cd apps/api && php artisan test --filter SalesTest`

5. Refactor while green (bounded):
   - Extract scheduling logic
   - Re-run test: `cd apps/api && php artisan test --filter SalesTest`

6. Commit:
   `git add . && git commit -m "feat(sales): add sales visit planning"`

7. Write failing test for: Delivery management
   Test file: `apps/api/tests/Feature/DeliveryTest.php`
   Level: integration
   Test intent: Given order is confirmed, When assigning delivery, Then delivery is created with driver
   Exercise through: POST /api/deliveries
   Test doubles: mock routing service
   Expected RED: Delivery endpoint does not exist

8. Run test — verify FAIL: `cd apps/api && php artisan test --filter DeliveryTest`

9. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/Delivery.php`
   Implement: Delivery model and controller

10. Run test — verify PASS: `cd apps/api && php artisan test --filter DeliveryTest`

11. Commit:
    `git add . && git commit -m "feat(delivery): add delivery management"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase5 Sales Force
Phase5 deliverables: Sales visit planning, delivery management, proof of delivery
Success criteria: Sales productivity measurable, delivery operations automated

## WHY THIS APPROACH
Complexity: standard
Justification: Field operations efficiency

## SANDWICH CONTEXT
[CRITICAL: Must integrate with order system]
You are implementing Sales Force & Delivery for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Models/SalesVisit.php, apps/api/app/Models/Delivery.php, related controllers
Available after: T4 (Payment Management)
Architecture rule: Laravel Eloquent models, RESTful controllers, order integration
[RESTATE: Must integrate with order system]

## DELIVERABLE
Given sales target exists, When planning visits, Then visit schedule is created
Given order is confirmed, When assigning delivery, Then delivery is created with driver
Given delivery is in progress, When tracking, Then status updates in real-time

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Sales visit planning works
  - Delivery assignment works
  - Integration with order system

Must-not-have:
  - Complex routing algorithms (keep simple for MVP)

Open question risks:
  - None (spec is clear)

Rollback note:
  - Can disable sales/delivery features if issues arise

## STOP CONDITIONS
Done when: Sales visits can be planned, deliveries can be assigned
Uncertain when: N/A
Escalate when: Cannot integrate with order system
