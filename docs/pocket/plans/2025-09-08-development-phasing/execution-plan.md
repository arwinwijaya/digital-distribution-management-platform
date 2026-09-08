# EXECUTION PLAN — Digital Distribution Management Platform Development

**Date:** 2025-09-08
**Spec:** docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
**Status:** draft
**Total tasks:**9

---

## Execution Overview

### Recommended Order
```
T1 → T2 → T3 → T4, T5 (parallel) → T6 → T7, T8 (parallel) → T9
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T4, T5 | T3 completes |
| Group B | T7, T8 | T6 starts |

### Constraints Reminder
**Architecture:** Solo developer, Next.js frontend + Laravel backend + PostgreSQL database, monorepo structure
**Out-of-scope:** Detailed technical architecture, UI/UX design, third-party integration specifics
**Assumptions at risk:** Technical debt percentage (qualitative judgment), phase transition documentation (changelog + deployment checklist)
**Sequencing:** T4 and T5 can run in parallel because Phase4 (Dashboard) only needs Phase3 dependencies (schema + API stability) to be satisfied, not Phase3 completion. T7 can start early because WhatsApp Business API has long lead time.

### File Structure Map

```
Rule: Project Foundation (T1)
  Create: package.json                    (frontend)
  Create: composer.json                   (backend)
  Create: docker-compose.yml              (infrastructure)
  Create: .github/workflows/ci.yml        (CI/CD)
  Create: apps/web/                       (Next.js app)
  Create: apps/api/                       (Laravel app)
  Create: packages/shared/                (shared types/utils)
  Test:   apps/api/tests/Feature/AuthTest.php

Rule: Outlet Management (T2)
  Create: apps/api/app/Models/Outlet.php
  Create: apps/api/app/Http/Controllers/OutletController.php
  Create: apps/web/app/outlets/           (outlet pages)
  Create: apps/web/components/OutletForm.tsx
  Test:   apps/api/tests/Feature/OutletTest.php

Rule: Product Catalog (T2)
  Create: apps/api/app/Models/Product.php
  Create: apps/api/app/Http/Controllers/ProductController.php
  Create: apps/web/app/products/          (product pages)
  Create: apps/web/components/ProductCatalog.tsx
  Test:   apps/api/tests/Feature/ProductTest.php

Rule: Order Management (T3)
  Create: apps/api/app/Models/Order.php
  Create: apps/api/app/Models/OrderItem.php
  Create: apps/api/app/Http/Controllers/OrderController.php
  Create: apps/web/app/orders/            (order pages)
  Create: apps/web/components/OrderForm.tsx
  Test:   apps/api/tests/Feature/OrderTest.php

Rule: Payment Management (T4)
  Create: apps/api/app/Models/Payment.php
  Create: apps/api/app/Models/CreditLimit.php
  Create: apps/api/app/Http/Controllers/PaymentController.php
  Create: apps/web/app/payments/          (payment pages)
  Test:   apps/api/tests/Feature/PaymentTest.php

Rule: Dashboard Analytics (T5)
  Create: apps/web/app/dashboard/         (dashboard pages)
  Create: apps/web/components/Charts.tsx
  Create: apps/api/app/Http/Controllers/AnalyticsController.php
  Test:   apps/api/tests/Feature/AnalyticsTest.php

Rule: Sales Force (T6)
  Create: apps/api/app/Models/SalesVisit.php
  Create: apps/api/app/Models/Delivery.php
  Create: apps/api/app/Http/Controllers/SalesController.php
  Create: apps/api/app/Http/Controllers/DeliveryController.php
  Create: apps/web/app/sales/             (sales pages)
  Create: apps/web/app/delivery/          (delivery pages)
  Test:   apps/api/tests/Feature/SalesTest.php
  Test:   apps/api/tests/Feature/DeliveryTest.php

Rule: WhatsApp Integration (T7)
  Create: apps/api/app/Services/WhatsAppService.php
  Create: apps/api/app/Http/Controllers/WhatsAppController.php
  Create: apps/api/app/Models/WhatsAppMessage.php
  Test:   apps/api/tests/Feature/WhatsAppTest.php

Rule: AI Intelligence (T8)
  Create: apps/api/app/Services/RecommendationService.php
  Create: apps/api/app/Services/ForecastService.php
  Create: apps/api/app/Http/Controllers/AIController.php
  Create: apps/web/app/analytics/         (AI pages)
  Test:   apps/api/tests/Feature/AITest.php

Rule: Polish & Scale (T9)
  Modify: apps/web/                       (performance optimization)
  Modify: apps/api/                       (performance optimization)
  Create: apps/web/app/marketplace/       (marketplace pages)
  Test:   apps/api/tests/Performance/     (performance tests)
```

---

## Pocket Packets

---

### Task 1: Foundation & Infrastructure [prereq]

## OBJECTIVE
Set up the project foundation including monorepo structure, authentication, database schema, API structure, and deployment pipeline. This is the prerequisite for all subsequent phases.

Files:
- Create: `package.json` (root)
- Create: `composer.json` (apps/api)
- Create: `docker-compose.yml`
- Create: `.github/workflows/ci.yml`
- Create: `apps/web/` (Next.js app structure)
- Create: `apps/api/` (Laravel app structure)
- Create: `packages/shared/` (shared types)
- Test: `apps/api/tests/Feature/AuthTest.php`

Steps:
1. Write failing test for: Authentication system
   Test file: `apps/api/tests/Feature/AuthTest.php`
   Level: integration
   Test intent: Given valid credentials, When user logs in, Then JWT token is returned
   Exercise through: POST /api/auth/login
   Test doubles: mock external services
   Expected RED: Authentication endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter AuthTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Http/Controllers/AuthController.php`
   Implement: Login endpoint with JWT token generation

4. Run test — verify PASS: `cd apps/api && php artisan test --filter AuthTest`

5. Refactor while green (bounded):
   - Extract auth logic to service class
   - Re-run test: `cd apps/api && php artisan test --filter AuthTest`

6. Commit:
   `git add . && git commit -m "chore(auth): setup authentication system"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase0 Foundation
Phase0 deliverables: Project setup, authentication, database schema, API structure, deployment pipeline

## WHY THIS APPROACH
Complexity: standard
Justification: Foundation must be solid for all subsequent phases. Authentication is critical path.

## SANDWICH CONTEXT
[CRITICAL: Solo developer must be able to deploy and test the system]
You are implementing Foundation & Infrastructure for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: Root project structure, apps/api, apps/web, packages/shared
Available after: none (prereq)
Architecture rule: Monorepo with Next.js frontend + Laravel backend + PostgreSQL
[RESTATE: Solo developer must be able to deploy and test the system]

## DELIVERABLE
Given developer has access to repository, When running setup commands, Then project starts successfully
Given developer has credentials, When logging in, Then JWT token is returned
Given API is running, When making requests, Then responses are returned
Given CI/CD pipeline is configured, When pushing code, Then tests run automatically

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Authentication system works
  - Database migrations run successfully
  - API endpoints accessible
  - Deployment pipeline functional

Must-not-have:
  - Over-engineering (keep it simple for solo developer)
  - Unnecessary complexity

Open question risks:
  - None (greenfield project)

Rollback note:
  - If foundation fails, can restart from scratch

## STOP CONDITIONS
Done when: Authentication works, API accessible, deployment automated
Uncertain when: N/A (greenfield)
Escalate when: Cannot setup project structure

---

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

---

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

---

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

---

### Task 5: Dashboard & Analytics [depends: T3] [parallel: T4]

## OBJECTIVE
Implement executive dashboard and basic analytics. This provides business visibility for the owner.

Files:
- Create: `apps/web/app/dashboard/`
- Create: `apps/web/components/Charts.tsx`
- Create: `apps/api/app/Http/Controllers/AnalyticsController.php`
- Test: `apps/api/tests/Feature/AnalyticsTest.php`

Steps:
1. Write failing test for: Dashboard metrics
   Test file: `apps/api/tests/Feature/AnalyticsTest.php`
   Level: integration
   Test intent: Given data exists, When viewing dashboard, Then key metrics are displayed
   Exercise through: GET /api/analytics/dashboard
   Test doubles: mock database queries
   Expected RED: Analytics endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter AnalyticsTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Http/Controllers/AnalyticsController.php`
   Implement: Dashboard metrics endpoint

4. Run test — verify PASS: `cd apps/api && php artisan test --filter AnalyticsTest`

5. Refactor while green (bounded):
   - Optimize database queries
   - Re-run test: `cd apps/api && php artisan test --filter AnalyticsTest`

6. Commit:
   `git add . && git commit -m "feat(dashboard): add executive dashboard metrics"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase4 Dashboard
Phase4 deliverables: Executive dashboard, sales trends, outlet performance
Success criteria: Owner can monitor business health

## WHY THIS APPROACH
Complexity: lightweight
Justification: Business visibility enables data-driven decisions

## SANDWICH CONTEXT
[CRITICAL: Must be performant with large datasets]
You are implementing Dashboard & Analytics for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/web/app/dashboard/, apps/api/app/Http/Controllers/AnalyticsController.php
Available after: T3 (Order Management)
Architecture rule: Next.js frontend, Laravel API, optimized database queries
[RESTATE: Must be performant with large datasets]

## DELIVERABLE
Given data exists, When viewing dashboard, Then key metrics are displayed
Given sales data, When viewing trends, Then charts show daily/weekly/monthly trends
Given outlet data, When viewing performance, Then outlet ranking is displayed

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Key metrics displayed correctly
  - Charts render properly
  - Performance acceptable

Must-not-have:
  - Over-complex analytics (keep simple for MVP)

Open question risks:
  - None (spec is clear)

Rollback note:
  - Can disable dashboard if performance issues arise

## STOP CONDITIONS
Done when: Dashboard displays metrics, charts work, performance acceptable
Uncertain when: N/A
Escalate when: Performance issues with large datasets

---

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

---

### Task 7: WhatsApp Integration [depends: T2] [parallel: T6]

## OBJECTIVE
Implement WhatsApp Business API integration for orders and notifications. This enables mobile-first ordering.

Files:
- Create: `apps/api/app/Services/WhatsAppService.php`
- Create: `apps/api/app/Http/Controllers/WhatsAppController.php`
- Create: `apps/api/app/Models/WhatsAppMessage.php`
- Test: `apps/api/tests/Feature/WhatsAppTest.php`

Steps:
1. Write failing test for: WhatsApp order
   Test file: `apps/api/tests/Feature/WhatsAppTest.php`
   Level: integration
   Test intent: Given outlet sends WhatsApp message, When ordering, Then order is created
   Exercise through: POST /api/whatsapp/webhook
   Test doubles: mock WhatsApp API
   Expected RED: WhatsApp webhook endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter WhatsAppTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Services/WhatsAppService.php`
   Implement: WhatsApp webhook handler

4. Run test — verify PASS: `cd apps/api && php artisan test --filter WhatsAppTest`

5. Refactor while green (bounded):
   - Extract message parsing logic
   - Re-run test: `cd apps/api && php artisan test --filter WhatsAppTest`

6. Commit:
   `git add . && git commit -m "feat(whatsapp): add WhatsApp order integration"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase6 WhatsApp Integration
Phase6 deliverables: WhatsApp order, notifications, catalog sharing
Success criteria: WhatsApp adoption >50%
Risk: WhatsApp Business API approval lead time panjang

## WHY THIS APPROACH
Complexity: standard
Justification: Critical for Indonesian warung ecosystem

## SANDWICH CONTEXT
[CRITICAL: Must handle WhatsApp Business API approval delay]
You are implementing WhatsApp Integration for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Services/WhatsAppService.php, related controllers
Available after: T2 (Outlet Onboarding)
Architecture rule: Laravel service class, WhatsApp Business API integration
[RESTATE: Must handle WhatsApp Business API approval delay]

## DELIVERABLE
Given outlet sends WhatsApp message, When ordering, Then order is created
Given order is confirmed, When notifying, Then WhatsApp notification is sent
Given product catalog exists, When sharing, Then catalog is shared via WhatsApp

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - WhatsApp webhook handling
  - Order creation via WhatsApp
  - Notification system

Must-not-have:
  - Complex chatbot logic (keep simple for MVP)

Open question risks:
  - WhatsApp Business API approval timeline

Rollback note:
  - Can disable WhatsApp integration if API approval delayed

## STOP CONDITIONS
Done when: WhatsApp orders work, notifications sent
Uncertain when: WhatsApp API approval delayed
Escalate when: Cannot get WhatsApp Business API approval

---

### Task 8: AI & Intelligence [depends: T4] [parallel: T6]

## OBJECTIVE
Implement product recommendations and sales forecasting. This provides data-driven insights.

Files:
- Create: `apps/api/app/Services/RecommendationService.php`
- Create: `apps/api/app/Services/ForecastService.php`
- Create: `apps/api/app/Http/Controllers/AIController.php`
- Create: `apps/web/app/analytics/`
- Test: `apps/api/tests/Feature/AITest.php`

Steps:
1. Write failing test for: Product recommendations
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet has purchase history, When viewing catalog, Then recommendations are shown
   Exercise through: GET /api/ai/recommendations
   Test doubles: mock ML service
   Expected RED: Recommendation endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter AITest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Services/RecommendationService.php`
   Implement: Basic recommendation engine

4. Run test — verify PASS: `cd apps/api && php artisan test --filter AITest`

5. Refactor while green (bounded):
   - Extract recommendation logic
   - Re-run test: `cd apps/api && php artisan test --filter AITest`

6. Commit:
   `git add . && git commit -m "feat(ai): add product recommendations"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase7 AI Intelligence
Phase7 deliverables: Product recommendations, sales forecasting, outlet segmentation
Success criteria: Recommendation acceptance >10%, forecast accuracy >70%

## WHY THIS APPROACH
Complexity: standard
Justification: Data-driven insights increase sales

## SANDWICH CONTEXT
[CRITICAL: Must work with limited data initially]
You are implementing AI & Intelligence for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Services/RecommendationService.php, apps/api/app/Services/ForecastService.php
Available after: T4 (Payment Management)
Architecture rule: Laravel service class, basic ML algorithms
[RESTATE: Must work with limited data initially]

## DELIVERABLE
Given outlet has purchase history, When viewing catalog, Then recommendations are shown
Given historical data exists, When forecasting, Then predictions are generated
Given outlet data exists, When segmenting, Then outlets are classified

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Basic recommendation engine
  - Sales forecasting
  - Outlet segmentation

Must-not-have:
  - Complex ML models (keep simple for MVP)

Open question risks:
  - Limited data initially

Rollback note:
  - Can disable AI features if accuracy too low

## STOP CONDITIONS
Done when: Recommendations work, forecasting generates predictions
Uncertain when: Data insufficient for accurate predictions
Escalate when: Cannot achieve minimum accuracy

---

### Task 9: Polish & Scale [depends: T6, T7, T8]

## OBJECTIVE
Optimize performance, add advanced features, and prepare for production scale.

Files:
- Modify: `apps/web/` (performance optimization)
- Modify: `apps/api/` (performance optimization)
- Create: `apps/web/app/marketplace/`
- Test: `apps/api/tests/Performance/`

Steps:
1. Write failing test for: Performance benchmark
   Test file: `apps/api/tests/Performance/LoadTest.php`
   Level: performance
   Test intent: Given100 concurrent users, When accessing API, Then response time <2s
   Exercise through: Load testing tools
   Test doubles: mock external services
   Expected RED: Performance benchmarks not defined

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter Performance`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Http/Controllers/` (optimize queries)
   Implement: Database query optimization, caching

4. Run test — verify PASS: `cd apps/api && php artisan test --filter Performance`

5. Refactor while green (bounded):
   - Optimize database indexes
   - Re-run test: `cd apps/api && php artisan test --filter Performance`

6. Commit:
   `git add . && git commit -m "perf(scale): optimize for production scale"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase8 Polish & Scale
Phase8 deliverables: Performance optimization, advanced BI, marketplace
Success criteria:500+ outlets,100+ active, sustainable revenue

## WHY THIS APPROACH
Complexity: standard
Justification: Production readiness

## SANDWICH CONTEXT
[CRITICAL: Must handle500+ outlets]
You are implementing Polish & Scale for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: All project files (optimization)
Available after: T6, T7, T8 (Sales, WhatsApp, AI)
Architecture rule: Performance optimization, caching, database indexing
[RESTATE: Must handle500+ outlets]

## DELIVERABLE
Given100 concurrent users, When accessing API, Then response time <2s
Given500+ outlets, When using platform, Then performance remains acceptable
Given marketplace exists, When suppliers join, Then multi-supplier support works

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Performance benchmarks met
  - Scalability verified
  - Marketplace functionality

Must-not-have:
  - Over-optimization (premature optimization)

Open question risks:
  - None (spec is clear)

Rollback note:
  - Can rollback optimizations if issues arise

## STOP CONDITIONS
Done when: Performance benchmarks met, marketplace functional
Uncertain when: N/A
Escalate when: Cannot meet performance requirements

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Foundation & Infrastructure | prereq | standard | Authentication works, API accessible |
| T2 | Outlet Onboarding & Product Discovery | T1 | standard | Outlet registration, product catalog |
| T3 | Order Management & Transaction | T2 | standard | Order creation, approval, idempotency |
| T4 | Payment & Credit Management | T3 | standard | Payment recording, credit limits |
| T5 | Dashboard & Analytics | T3 | lightweight | Dashboard metrics, charts |
| T6 | Sales Force & Delivery | T4 | standard | Sales visits, delivery management |
| T7 | WhatsApp Integration | T2 | standard | WhatsApp orders, notifications |
| T8 | AI & Intelligence | T4 | standard | Recommendations, forecasting |
| T9 | Polish & Scale | T6, T7, T8 | standard | Performance, marketplace |
