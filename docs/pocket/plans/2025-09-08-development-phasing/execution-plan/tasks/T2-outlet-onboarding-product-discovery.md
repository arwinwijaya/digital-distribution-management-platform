# Task T2 — Outlet Onboarding & Product Discovery

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Outlet Onboarding & Product Discovery [depends: T1]

## OBJECTIVE
Implement outlet registration, product catalog, and basic search functionality. This enables the first user journey: outlet can register and browse products.

Files:
- Create: `apps/api/app/Models/Outlet.php`
- Create: `apps/api/app/Http/Controllers/OutletController.php`
- Create: `apps/web/app/outlets/`
- Create: `apps/web/components/OutletForm.tsx`
- Create: `apps/api/app/Models/Product.php`
- Create: `apps/api/app/Http/Controllers/ProductController.php`
- Create: `apps/web/app/products/`
- Create: `apps/web/components/ProductCatalog.tsx`
- Test: `apps/api/tests/Feature/OutletTest.php`
- Test: `apps/api/tests/Feature/ProductTest.php`

Steps:
1. Write failing test for: Outlet registration
   Test file: `apps/api/tests/Feature/OutletTest.php`
   Level: integration
   Test intent: Given valid outlet data, When registering, Then outlet profile is created
   Exercise through: POST /api/outlets
   Test doubles: mock external services
   Expected RED: Outlet registration endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter OutletTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/Outlet.php`
   Implement: Outlet model with migration and controller

4. Run test — verify PASS: `cd apps/api && php artisan test --filter OutletTest`

5. Refactor while green (bounded):
   - Extract validation logic
   - Re-run test: `cd apps/api && php artisan test --filter OutletTest`

6. Commit:
   `git add . && git commit -m "feat(outlets): add outlet registration"`

7. Write failing test for: Product catalog browsing
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given products exist, When browsing catalog, Then products are displayed with prices
   Exercise through: GET /api/products
   Test doubles: mock external services
   Expected RED: Product catalog endpoint does not exist

8. Run test — verify FAIL: `cd apps/api && php artisan test --filter ProductTest`

9. Implement minimal code to satisfy the test:
   File: `apps/api/app/Models/Product.php`
   Implement: Product model with migration and controller

10. Run test — verify PASS: `cd apps/api && php artisan test --filter ProductTest`

11. Commit:
    `git add . && git commit -m "feat(products): add product catalog"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase1 Outlet Onboarding
Phase1 deliverables: Outlet registration, product catalog, basic search
Success criteria:10-20 pilot outlets registered,1 supplier with50+ products

## WHY THIS APPROACH
Complexity: standard
Justification: Enables first user journey and pilot validation

## SANDWICH CONTEXT
[CRITICAL: Must support duplicate phone number rejection]
You are implementing Outlet Onboarding & Product Discovery for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Models/Outlet.php, apps/api/app/Models/Product.php, related controllers and views
Available after: T1 (Foundation)
Architecture rule: Laravel Eloquent models, RESTful controllers
[RESTATE: Must support duplicate phone number rejection]

## DELIVERABLE
Given valid outlet data, When registering, Then outlet profile is created and visible
Given duplicate phone number, When registering, Then registration is rejected with error message
Given products exist, When browsing catalog, Then products are displayed with prices and availability
Given outlet has registered, When browsing empty catalog, Then empty state message is shown

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Duplicate phone number rejection works
  - Product catalog displays correctly
  - Basic search functionality

Must-not-have:
  - Over-complex search (keep simple for MVP)

Open question risks:
  - None (spec is clear)

Rollback note:
  - Can rollback to Phase1 state if issues arise

## STOP CONDITIONS
Done when: Outlet registration works, product catalog browsable, duplicate prevention active
Uncertain when: N/A
Escalate when: Cannot implement duplicate phone rejection
