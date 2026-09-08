# Task T3 — Order Management & Transaction

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Order Management & Transaction [depends: T2]

## OBJECTIVE
Implement order creation, approval, and tracking. This enables the core transaction flow: outlet can order products and admin can manage orders.

Files:
- Create: `apps/api/app/Models/Order.php`
- Create: `apps/api/app/Models/OrderItem.php`
- Create: `apps/api/app/Http/Controllers/OrderController.php`
- Create: `apps/web/app/orders/`
- Create: `apps/web/components/OrderForm.tsx`
- Test: `apps/api/tests/Feature/OrderTest.php`

Steps:
1. Write failing test for: Order creation
   Test file: `apps/api/tests/Feature/OrderTest.php`
   Level: integration
   Test intent: Given outlet has products in cart, When placing order, Then order is created with status "New"
   Exercise through: POST /api/orders
   Test doubles: mock payment gateway
   Expected RED: Order creation endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter OrderTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/Order.php`
   Implement: Order model with unique order ID generation

4. Run test — verify PASS: `cd apps/api && php artisan test --filter OrderTest`

5. Refactor while green (bounded):
   - Extract order ID generation logic
   - Re-run test: `cd apps/api && php artisan test --filter OrderTest`

6. Commit:
   `git add . && git commit -m "feat(orders): add order creation with unique ID"`

7. Write failing test for: Order approval
   Test file: `apps/api/tests/Feature/OrderTest.php`
   Level: integration
   Test intent: Given order exists with status "New", When admin approves, Then status updates to "Confirmed"
   Exercise through: PUT /api/orders/{id}/approve
   Test doubles: mock notification service
   Expected RED: Order approval endpoint does not exist

8. Run test — verify FAIL: `cd apps/api && php artisan test --filter OrderTest`

9. Implement minimal code to satisfy the test:
   File: `apps/api/app/Http/Controllers/OrderController.php`
   Implement: Order approval endpoint

10. Run test — verify PASS: `cd apps/api && php artisan test --filter OrderTest`

11. Commit:
    `git add . && git commit -m "feat(orders): add order approval flow"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase2 Order Management
Phase2 deliverables: Order creation, approval, tracking
Success criteria:50+ orders processed, order flow end-to-end lancar
Revenue hook: Commission per transaction (commission_percentage)

## WHY THIS APPROACH
Complexity: standard
Justification: Core transaction flow, must be production-ready

## SANDWICH CONTEXT
[CRITICAL: Must support idempotency (server-generated order ID)]
You are implementing Order Management & Transaction for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Models/Order.php, apps/api/app/Models/OrderItem.php, related controllers
Available after: T2 (Outlet Onboarding)
Architecture rule: Laravel Eloquent models, RESTful controllers, unique order ID generation
[RESTATE: Must support idempotency (server-generated order ID)]

## DELIVERABLE
Given outlet has products in cart, When placing order, Then order is created with unique order ID
Given duplicate request, When placing order, Then only one order is created (idempotency)
Given order exists, When admin approves, Then status updates to "Confirmed"
Given order exists, When tracking, Then status history is maintained

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Unique order ID generation
  - Idempotency protection
  - Order status tracking
  - Automated tests (critical path)

Must-not-have:
  - Complex order workflows (keep simple for MVP)

Open question risks:
  - None (spec is clear)

Rollback note:
  - If order system fails, can rollback to Phase2 state

## STOP CONDITIONS
Done when: Order creation works, approval flow complete, idempotency protected
Uncertain when: N/A
Escalate when: Cannot implement idempotency
