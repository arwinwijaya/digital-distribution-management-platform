# Task T8 — AI & Intelligence

**Phase:** 2
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
