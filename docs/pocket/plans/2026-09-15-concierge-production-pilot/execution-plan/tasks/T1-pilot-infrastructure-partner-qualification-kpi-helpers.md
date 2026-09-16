# Task T1 — Pilot Infrastructure — Partner Qualification & KPI Helpers

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Pilot Infrastructure — Partner Qualification & KPI Helpers [prereq]

## OBJECTIVE
Create pilot infrastructure: a partner qualification validation service and KPI calculation helpers that all downstream pilot tasks depend on. No pilot can run without confirmed partner qualification.

Files:
- Create: `apps/api/app/Services/PilotQualificationService.php`
- Test: `apps/api/tests/Feature/PilotQualificationTest.php`

Steps:
1. Write failing test for: Partner qualifies with 10+ outlets
   Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
   Level: integration

   Test intent:
   Given a partner with 15 outlets that have orders in the last 30 days
   When `PilotQualificationService::evaluate($partnerId)` is called
   Then:
   - Result contains `qualified = true`
   - Result contains `outletCount = 15`
   - Result contains `outletCheck = PASS`

   Exercise through:
   - Public method `evaluate()` on `PilotQualificationService` — drives real DB query for 30-day order history

   Test doubles:
   - do NOT mock: `PilotQualificationService`, Partner/Outlet Eloquent models, Order model (used for 30-day count)
   - mock: None — use in-memory SQLite with factory-seeded data

   Expected RED:
   - `PilotQualificationService` class does not exist → PHPUnit error: class not found

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotQualificationTest::testQualifiesWithSufficientOutlets`
   Expected failure: Class `App\Services\PilotQualificationService` not found

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Services/PilotQualificationService.php`
   Implement: `evaluate($partnerId)` method that counts active outlets with recent orders, returns qualification result array

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotQualificationTest`
   Expected: PASS

5. Write failing test for: Partner fails with <10 outlets
   Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
   Level: integration

   Test intent:
   Given a partner with 5 outlets that have orders in the last 30 days
   When `PilotQualificationService::evaluate($partnerId)` is called
   Then:
   - Result contains `qualified = false`
   - Result contains `outletCheck = FAIL`

   Exercise through:
   - Public method `evaluate()` on `PilotQualificationService` — drives real DB query for 30-day order history

   Test doubles:
   - do NOT mock: `PilotQualificationService`, Partner/Outlet Eloquent models, Order model
   - mock: None — use in-memory SQLite with factory-seeded data

   Expected RED:
   - Current implementation does not enforce 10-outlet minimum threshold

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsWithInsufficientOutlets`
   Expected failure: Assertion failed — qualified should be false for 5 outlets

7. Implement outlet threshold logic:
   File: `apps/api/app/Services/PilotQualificationService.php`
   Implement: Minimum 10 active outlets check with 30-day order history

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=PilotQualificationTest`
   Expected: PASS

9. Write failing test for: Partner qualifies with documentation, PIC, and internet
   Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
   Level: unit

   Test intent:
   Given a partner with 15 outlets, documented manual process, assigned PIC (08:00-17:00 WIB), and stable internet
   When `PilotQualificationService::evaluate($partnerId)` is called
   Then:
   - Result contains `qualified = true`
   - Result contains `documentationCheck = PASS`
   - Result contains `picCheck = PASS`
   - Result contains `internetCheck = PASS`

   Exercise through:
   - Public method `evaluate()` on `PilotQualificationService`

   Test doubles:
   - do NOT mock: `PilotQualificationService`
   - mock: Partner Eloquent model with qualification attributes

   Expected RED:
   - Documentation, PIC, and internet checks not implemented yet

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testQualifiesWithAllChecksPass`
    Expected failure: Undefined index or assertion failures for documentation/pic/internet checks

11. Implement remaining qualification checks:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Documentation baseline check, PIC assignment check (availability window), internet stability check

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testQualifiesWithAllChecksPass`
    Expected: PASS

13. Write failing test for: Partner fails documentation check
    Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
    Level: unit

    Test intent:
    Given a partner with 15 outlets but no documented manual process
    When `PilotQualificationService::evaluate($partnerId)` is called
    Then:
    - Result contains `qualified = false`
    - Result contains `documentationCheck = FAIL`

    Exercise through:
    - Public method `evaluate()` on `PilotQualificationService`

    Test doubles:
    - do NOT mock: `PilotQualificationService`
    - mock: Partner Eloquent model with missing documentation attribute

    Expected RED:
    - Documentation check passes vacuously or fails to report FAIL state

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsDocumentationCheck`
    Expected failure: Assertion failed — documentationCheck should be FAIL

15. Write failing test for: Partner fails PIC check
    Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
    Level: unit

    Test intent:
    Given a partner with 15 outlets, documentation, and internet, but no assigned PIC
    When `PilotQualificationService::evaluate($partnerId)` is called
    Then:
    - Result contains `qualified = false`
    - Result contains `picCheck = FAIL`

    Exercise through:
    - Public method `evaluate()` on `PilotQualificationService`

    Test doubles:
    - do NOT mock: `PilotQualificationService`
    - mock: Partner Eloquent model with no PIC assigned

    Expected RED:
    - PIC check passes vacuously or fails to report FAIL state

16. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsPicCheck`
    Expected failure: Assertion failed — picCheck should be FAIL

17. Write failing test for: Partner fails internet stability check
    Test file: `apps/api/tests/Feature/PilotQualificationTest.php`
    Level: unit

    Test intent:
    Given a partner with 15 outlets, documentation, and PIC, but unstable internet
    When `PilotQualificationService::evaluate($partnerId)` is called
    Then:
    - Result contains `qualified = false`
    - Result contains `internetCheck = FAIL`

    Exercise through:
    - Public method `evaluate()` on `PilotQualificationService`

    Test doubles:
    - do NOT mock: `PilotQualificationService`
    - mock: Partner Eloquent model with unstable internet attribute

    Expected RED:
    - Internet check passes vacuously or fails to report FAIL state

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsInternetCheck`
    Expected failure: Assertion failed — internetCheck should be FAIL

19. Implement documentation check — harden FAIL path for missing documentation:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Explicit `documentationCheck = FAIL` when manual process documentation is absent

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsDocumentationCheck`
    Expected: PASS

21. Implement PIC check — harden FAIL path for missing PIC:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Explicit `picCheck = FAIL` when no PIC is assigned

22. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsPicCheck`
    Expected: PASS

23. Implement internet stability check — harden FAIL path for unstable internet:
    File: `apps/api/app/Services/PilotQualificationService.php`
    Implement: Explicit `internetCheck = FAIL` when internet stability fails

24. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=PilotQualificationTest::testFailsInternetCheck`
    Expected: PASS

25. Refactor while green (bounded):
    - Extract qualification check methods into clear, single-responsibility private methods
    - Ensure `evaluate()` returns a normalized result array
    - Re-run full test suite: `cd apps/api && php artisan test --filter=PilotQualificationTest` — must stay PASS

26. Commit:
    `git add apps/api/app/Services/PilotQualificationService.php apps/api/tests/Feature/PilotQualificationTest.php`
    `git commit -m "feat(pilot): add partner qualification validation service"`

## REFERENCES LOADED
- docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md — Rule 1-4: Partner Selection, GWT scenarios for partner qualification
- apps/api/app/Services/OperationalReadinessService.php — existing readiness check pattern to follow
- apps/api/app/Services/PrePilotFeatureGate.php — existing feature gate pattern

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single service with 4 qualification checks. No cross-file coordination needed. Follows existing `OperationalReadinessService` pattern for readiness validation. Foundation for all downstream pilot tasks.

## SANDWICH CONTEXT
[CRITICAL: Do not mutate partner/outlet source data. This service reads existing partner attributes to determine qualification — it does not create or modify partner records.]
You are implementing pilot infrastructure — partner qualification validation for Concierge Production Pilot.
Spec: docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
Design decision: Option A — Pilot Minimum Viable
Files in scope: `apps/api/app/Services/PilotQualificationService.php`, `apps/api/tests/Feature/PilotQualificationTest.php`
Available after: none (prereq)
Architecture rule: All DB access through Eloquent models — no raw queries, no schema mutations
[RESTATE: Do not mutate partner/outlet source data — read-only qualification check only]

## DELIVERABLE
Given partner with 15 outlets, documentation, PIC, and internet, When `evaluate($partnerId)`, Then `qualified = true` with all checks PASS
Given partner with 5 outlets, When `evaluate($partnerId)`, Then `qualified = false` with `outletCheck = FAIL`
Given partner with 15 outlets but no documentation, When `evaluate($partnerId)`, Then `qualified = false` with `documentationCheck = FAIL`
Given partner with 15 outlets, documentation, internet, but no PIC, When `evaluate($partnerId)`, Then `qualified = false` with `picCheck = FAIL`
Given partner with 15 outlets, documentation, PIC, but unstable internet, When `evaluate($partnerId)`, Then `qualified = false` with `internetCheck = FAIL`
All tests PASS. Commit exists with message matching `feat(pilot): add partner qualification validation service`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - All 4 partner qualification rules implemented (outlet count, documentation, PIC, internet)
  - Tests written BEFORE implementation (TDD — not after)
  - `evaluate()` returns normalized result array with per-check status
  - Commit message follows conventional commits format

Must-not-have:
  - Mutating partner/outlet source data
  - Modifying files outside listed scope
  - Skipping the failing test step (implement-then-test is a plan violation)

Open question risks:
  - Internet stability check may need external monitoring data → if no data source exists, report NEEDS_CONTEXT
  - PIC availability window validation may depend on timezone handling → assume WIB (UTC+7)

Rollback note:
  - New service + test files only — delete files to rollback. No schema changes.

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: internet stability check has no data source in existing codebase
Escalate when: task touches partner/outlet source data or schema migrations
