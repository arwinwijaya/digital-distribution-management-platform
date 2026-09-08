# Task T4 — Payment & Credit Management

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Payment & Credit Management [depends: T3]

## OBJECTIVE
Implement payment recording, credit limits, and outstanding balance tracking. This enables financial management for the platform.

Files:
- Create: `apps/api/app/Models/Payment.php`
- Create: `apps/api/app/Models/CreditLimit.php`
- Create: `apps/api/app/Http/Controllers/PaymentController.php`
- Create: `apps/web/app/payments/`
- Test: `apps/api/tests/Feature/PaymentTest.php`

Steps:
1. Write failing test for: Payment recording
   Test file: `apps/api/tests/Feature/PaymentTest.php`
   Level: integration
   Test intent: Given order is delivered, When recording payment, Then payment is recorded and balance updated
   Exercise through: POST /api/payments
   Test doubles: mock receipt generation
   Expected RED: Payment recording endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter PaymentTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/Payment.php`
   Implement: Payment model with partial payment support

4. Run test — verify PASS: `cd apps/api && php artisan test --filter PaymentTest`

5. Refactor while green (bounded):
   - Extract payment calculation logic
   - Re-run test: `cd apps/api && php artisan test --filter PaymentTest`

6. Commit:
   `git add . && git commit -m "feat(payments): add payment recording with partial support"`

7. Write failing test for: Credit limit enforcement
   Test file: `apps/api/tests/Feature/PaymentTest.php`
   Level: integration
   Test intent: Given outlet exceeds credit limit, When placing order, Then order is blocked
   Exercise through: POST /api/orders (with credit check)
   Test doubles: mock credit service
   Expected RED: Credit limit check does not exist

8. Run test — verify FAIL: `cd apps/api && php artisan test --filter PaymentTest`

9. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/CreditLimit.php`
   Implement: Credit limit model and enforcement

10. Run test — verify PASS: `cd apps/api && php artisan test --filter PaymentTest`

11. Commit:
    `git add . && git commit -m "feat(credits): add credit limit enforcement"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase3 Payment Management
Phase3 deliverables: Payment recording, credit limits, outstanding tracking
Success criteria: Payment recording for all orders, credit management prevents over-limit

## WHY THIS APPROACH
Complexity: standard
Justification: Financial integrity is critical for business

## SANDWICH CONTEXT
[CRITICAL: Must support partial payments and credit limit validation at submission time]
You are implementing Payment & Credit Management for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Models/Payment.php, apps/api/app/Models/CreditLimit.php, related controllers
Available after: T3 (Order Management)
Architecture rule: Laravel Eloquent models, RESTful controllers, credit validation at order submission
[RESTATE: Must support partial payments and credit limit validation at submission time]

## DELIVERABLE
Given order is delivered, When recording payment, Then payment is recorded and balance updated
Given partial payment, When recording, Then remaining balance is tracked
Given outlet exceeds credit limit, When placing order, Then order is blocked
Given pending orders exist, When calculating outstanding, Then pending orders are counted
Given credit limit is zero, When placing order, Then order is blocked

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Partial payment support
  - Credit limit enforcement at submission time
  - Outstanding balance calculation includes pending orders
  - Automated tests (critical path)

Must-not-have:
  - Complex payment workflows (keep simple for MVP)

Open question risks:
  - None (spec is clear)

Rollback note:
  - If payment system fails, can rollback to Phase3 state

## STOP CONDITIONS
Done when: Payment recording works, credit limits enforced, partial payments supported
Uncertain when: N/A
Escalate when: Cannot implement credit limit validation
