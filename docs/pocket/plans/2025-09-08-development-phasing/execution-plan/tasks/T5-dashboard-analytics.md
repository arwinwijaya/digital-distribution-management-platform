# Task T5 — Dashboard & Analytics

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
