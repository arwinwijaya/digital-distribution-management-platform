# Task T3 — Pilot Evaluation — Decision Matrix & Phase 7 Evidence Documentation

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Pilot Evaluation — Decision Matrix & Phase 7 Evidence Documentation [depends: T2]

## OBJECTIVE
Create the pilot evaluation service that implements the decision matrix (scale-up / iterate / stop) based on measured KPIs, and produces documented evidence for Phase 7. This task completes the pilot loop: measure → evaluate → decide → document.

Files:
- Create: `apps/api/app/Services/PilotEvaluationService.php`
- Create: `apps/api/tests/Feature/PilotEvaluationTest.php`

Steps:
1. Write failing test for: Scale-up decision when all KPIs met
   Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
   Level: unit

   Test intent:
   Given pilot results: 25 valid orders, speed delta -91.7%, error rate 4%, delivery success 96%, payment completion 95.8%
   When `PilotEvaluationService::evaluate($pilotResults)` is called
   Then:
   - Result contains `decision = "scale-up"`
   - Result contains `kpiSummary` with all metrics
   - Result contains `phase7Evidence` with documented case study

   Exercise through:
   - Public method `evaluate()` on `PilotEvaluationService`

   Test doubles:
   - do NOT mock: `PilotEvaluationService`
   - mock: None — pure evaluation logic

   Expected RED:
   - `PilotEvaluationService` class does not exist

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testScaleUpDecision`
   Expected failure: Class `App\Services\PilotEvaluationService` not found

3. Implement PilotEvaluationService with decision matrix:
   File: `apps/api/app/Services/PilotEvaluationService.php`
   Implement: `evaluate($pilotResults)` — evaluates KPIs against thresholds, returns decision + evidence

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testScaleUpDecision`
   Expected: PASS

5. Write failing test for: Iterate decision when some KPIs not met
   Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
   Level: unit

   Test intent:
   Given pilot results: 20 valid orders, speed delta -25% (target not met), error rate 3%, delivery success 97%, payment completion 92%
   When `PilotEvaluationService::evaluate($pilotResults)` is called
   Then:
   - Result contains `decision = "iterate"`
   - Result contains `kpiSummary.speedDelta.targetMet = false`
   - Result contains `lessonsLearned` with improvement recommendations

   Exercise through:
   - Public method `evaluate()` on `PilotEvaluationService`

   Test doubles:
   - do NOT mock: `PilotEvaluationService`
   - mock: None

   Expected RED:
   - Iterate decision logic not implemented

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testIterateDecision`
   Expected failure: Assertion failure on decision or missing lessonsLearned

7. Implement iterate decision logic:
   File: `apps/api/app/Services/PilotEvaluationService.php`
   Implement: Iterate decision when KPIs partially met, with improvement recommendations

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotEvaluationTest::testIterateDecision`
   Expected: PASS

9. Write failing test for: Stop decision when guardrails violated
   Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
   Level: unit

   Test intent:
   Given pilot results: 18 valid orders, speed delta -20%, error rate 8% (>5%), delivery success 89% (<95%)
   When `PilotEvaluationService::evaluate($pilotResults)` is called
   Then:
   - Result contains `decision = "stop"`
   - Result contains `rootCauseAnalysis` with identified issues
   - Result contains `recommendations` for significant iteration

   Exercise through:
   - Public method `evaluate()` on `PilotEvaluationService`

   Test doubles:
   - do NOT mock: `PilotEvaluationService`
   - mock: None

   Expected RED:
   - Stop decision logic not implemented

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testStopDecision`
    Expected failure: Assertion failure on decision or missing rootCauseAnalysis

11. Implement stop decision logic:
    File: `apps/api/app/Services/PilotEvaluationService.php`
    Implement: Stop decision when guardrails violated, with root cause analysis

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testStopDecision`
    Expected: PASS

13. Write failing test for: Phase 7 evidence documentation generation
    Test file: `apps/api/tests/Feature/PilotEvaluationTest.php`
    Level: unit

    Test intent:
    Given evaluated pilot results with decision "scale-up"
    When `PilotEvaluationService::generateEvidence($evaluation)` is called
    Then:
    - Evidence contains `executiveSummary` with key metrics
    - Evidence contains `kpiDetail` with per-metric breakdown
    - Evidence contains `recommendations` with next steps
    - Evidence contains `dataCollection` noting pilot duration and order count

    Exercise through:
    - Public method `generateEvidence()` on `PilotEvaluationService`

    Test doubles:
    - do NOT mock: `PilotEvaluationService`
    - mock: None

    Expected RED:
    - Evidence generation not implemented

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testEvidenceGeneration`
    Expected failure: Undefined method or assertion failure

15. Implement evidence generation:
    File: `apps/api/app/Services/PilotEvaluationService.php`
    Implement: `generateEvidence($evaluation)` — produces structured evidence document for Phase 7

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotEvaluationTest::testEvidenceGeneration`
    Expected: PASS

17. Refactor while green (bounded):
    - Extract decision threshold constants into class constants
    - Ensure `evaluate()` handles edge cases (missing metrics, zero orders)
    - Re-run full test suite: `cd apps/api && php artisan test --filter=PilotEvaluationTest` — must stay PASS

18. Commit:
    `git add apps/api/app/Services/PilotEvaluationService.php apps/api/tests/Feature/PilotEvaluationTest.php`
    `git commit -m "feat(pilot): add evaluation service with decision matrix and Phase 7 evidence"`

## REFERENCES LOADED
- docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md — Rules 1-3: Evaluation & Decision, GWT scenarios for scale-up/iterate/stop decisions
- apps/api/app/Services/PilotMetricsService.php (from T2) — metrics output consumed by evaluation

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single service with 3 decision paths and 1 evidence generator. Pure business logic with no external dependencies. Straightforward TDD with clear GWT scenarios.

## SANDWICH CONTEXT
[CRITICAL: Evaluation logic must not access or modify pilot order data directly — it receives pre-computed metrics as input.]
You are implementing pilot evaluation and decision matrix for Concierge Production Pilot.
Spec: docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
Design decision: Option A — Pilot Minimum Viable
Files in scope: `apps/api/app/Services/PilotEvaluationService.php`, `apps/api/tests/Feature/PilotEvaluationTest.php`
Available after: T2 (PilotMetricsService must exist for metrics input)
Architecture rule: Evaluation service receives metrics as plain array/DTO — no direct DB access
[RESTATE: Evaluation logic must not access pilot order data directly — receive pre-computed metrics only]

## DELIVERABLE
Given all KPIs met (25 orders, -91.7% speed, 4% error, 96% delivery, 95.8% payment), When evaluate, Then decision = "scale-up" with case study evidence
Given KPIs partially met (20 orders, -25% speed, 3% error, 97% delivery, 92% payment), When evaluate, Then decision = "iterate" with lessons learned
Given guardrails violated (18 orders, 8% error, 89% delivery), When evaluate, Then decision = "stop" with root cause analysis
Given evaluated results, When generateEvidence, Then evidence document contains executiveSummary, kpiDetail, recommendations, dataCollection
All tests PASS. Commit exists with message matching `feat(pilot): add evaluation service with decision matrix and Phase 7 evidence`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - All 3 decision paths implemented (scale-up, iterate, stop)
  - Evidence document generation with structured output
  - Tests written BEFORE implementation (TDD — not after)
  - Decision thresholds clearly defined as class constants
  - Commit message follows conventional commits format

Must-not-have:
  - Direct DB access in evaluation logic
  - Modifying files outside listed scope
  - Skipping the failing test step

Open question risks:
  - Decision thresholds may need adjustment after real pilot data → if thresholds prove wrong, report NEEDS_CONTEXT
  - Evidence format may need alignment with Phase 7 requirements → assume current structure is sufficient

Rollback note:
  - New files only — delete to rollback. No existing code modified.

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: decision thresholds need recalibration after real pilot data
Escalate when: evaluation logic accesses pilot order data directly
