# Task T9 — Verify pipeline publication is consumed atomically across BI units

**Phase:** 3
**Depends:** T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Verify pipeline publication is consumed atomically across BI units [depends: T7] [test-risk]

## OBJECTIVE
Test-only verification of the scheduler/trigger, pipeline orchestrator, BI stage producers and admin consumer endpoints. Prove one complete immutable publication, prior-snapshot retention after failure, and lock-protected exclusion of competing scheduler/manual publications. No production wiring, controller, service, route or configuration file may be changed in T9; those seams are owned by T2–T7.

Files:
- Test: `apps/api/tests/Feature/DataIntelligenceIntegrationTest.php`

Steps:
1. Write failing test for: scheduled pipeline publishes a complete snapshot
   Test name: `scheduled_pipeline_publishes_a_complete_snapshot`
   Test file: `apps/api/tests/Feature/DataIntelligenceIntegrationTest.php`
   Level: integration
   Test intent: Given valid order, payment, product, outlet, supplier and delivery source data exists, When the scheduler runs at 02:00 WIB for prior-day and rolling windows, Then exactly one versioned completed run publishes one complete immutable snapshot with metric definitions, source lineage and window metadata, and the admin geographic, supplier, stock and measurement consumers all report the active snapshot version.
   Exercise through: the public scheduler/Artisan command and authenticated admin HTTP endpoints; do not call private methods or bypass the publication transaction.
   Test doubles: fake only external tile/network and the Asia/Jakarta clock; use real Eloquent services, database transactions, locks and controllers; do not mock producer/consumer units.
   Expected RED: the integration test/scenario is absent or complete snapshot/version/lineage/consumer agreement is not proven.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='scheduled_pipeline_publishes_a_complete_snapshot'`
   Expected failure: test file/scenario is missing or an exact complete-snapshot, lineage, window, active-version or consumer assertion fails.
3. Implement minimal test code only: seed the source fixtures, invoke the public scheduler/command and admin routes, and assert the single complete immutable publication and matching consumer metadata. Use only the existing T2–T7 seams; if a seam is absent, stop and update its owning T2–T7 packet rather than editing production files in T9.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='scheduled_pipeline_publishes_a_complete_snapshot'`
   Expected: PASS; exactly one complete immutable snapshot, its definitions/lineage/window are present, and all four consumers expose its active version.
5. Refactor while green (bounded): simplify only fixture builders/assertion arrangement inside `DataIntelligenceIntegrationTest.php`; run `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='scheduled_pipeline_publishes_a_complete_snapshot'` and require `Expected: PASS`.
6. Commit: `git add apps/api/tests/Feature/DataIntelligenceIntegrationTest.php && git commit -m "test(data-intelligence): verify atomic publication integration"`

7. Write failing test for: failed stage preserves prior snapshot without partial output
   Test name: `failed_stage_preserves_prior_snapshot_without_partial_output`
   Test file: `apps/api/tests/Feature/DataIntelligenceIntegrationTest.php`
   Level: integration
   Test intent: Given a prior successful snapshot is active, When one public pipeline stage fails, Then the new run is failed with recorded error lineage, the prior snapshot/version remains active, no partial snapshot or staged metric is consumer-visible, and the previous complete output is unchanged.
   Exercise through: the public pipeline command/service entry point with its injectable stage-failure seam, followed by the admin status and BI HTTP endpoints; do not update snapshot rows directly.
   Test doubles: inject only the documented stage failure callback and freeze the clock; use real database transactions, publication locks and consumer controllers; do not mock `DataPipelineService`, snapshots, locks or consumers.
   Expected RED: failure retention or no-partial-output assertions are absent or a failed stage can replace/expose partial output.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='failed_stage_preserves_prior_snapshot_without_partial_output'`
   Expected failure: the test is missing or the failed run changes the active version, leaves partial output, or omits the recorded error.
9. Implement minimal test code only: seed the prior snapshot, trigger the existing failure seam, and assert the prior active identity, complete metric set and consumer response remain byte-for-byte stable while the failed run records its error. If the existing T2 service lacks the documented failure seam, record the blocker against T2; do not add wiring in T9.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='failed_stage_preserves_prior_snapshot_without_partial_output'`
    Expected: PASS; prior snapshot/version and consumer output remain unchanged, no partial result is active or visible, and failed error lineage is persisted.
11. Refactor while green (bounded): keep failure fixtures and prior-output assertions explicit in `DataIntelligenceIntegrationTest.php`; run `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='failed_stage_preserves_prior_snapshot_without_partial_output'` and require `Expected: PASS`.
12. Commit: `git add apps/api/tests/Feature/DataIntelligenceIntegrationTest.php && git commit -m "test(data-intelligence): verify failed publication retention"`

13. Write failing test for: concurrent scheduler and manual run cannot publish a competing snapshot
   Test name: `concurrent_scheduler_and_manual_run_cannot_publish_a_competing_snapshot`
   Test file: `apps/api/tests/Feature/DataIntelligenceIntegrationTest.php`
   Level: integration
   Test intent: Given an active scheduler run holds the pipeline publication/active-run lock, When an authenticated admin manual trigger executes concurrently, Then the manual request receives the explicit conflict/status response, does not publish a snapshot, and exactly one active run/publication and one active snapshot remain; the scheduler's complete publication is the only consumer-visible version.
   Exercise through: concurrent public Artisan scheduler command and admin manual-trigger HTTP route against the real database lock; do not call private methods or bypass transactions.
   Test doubles: coordinate real workers/processes with a barrier and freeze the Asia/Jakarta clock; mock only external network/tile calls; do not mock the overlap guard, command, controller, service or database.
   Expected RED: the test is missing or the manual run publishes a competing snapshot, returns an implicit success, or leaves multiple active identities.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='concurrent_scheduler_and_manual_run_cannot_publish_a_competing_snapshot'`
    Expected failure: conflict/status assertion, active-identity count, publication count or consumer-version assertion fails.
15. Implement minimal test code only: coordinate the existing scheduler/manual public boundaries, assert the explicit conflict response and query the real active-run/publication identities after both workers finish. If T3's overlap seam cannot support this assertion, record the blocker against T3; do not add production wiring in T9.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='concurrent_scheduler_and_manual_run_cannot_publish_a_competing_snapshot'`
    Expected: PASS; one request is the publisher, the concurrent request is an explicit conflict, exactly one active run/publication remains, and consumers expose only the publisher's complete version.
17. Refactor while green (bounded): retain real lock/process coordination and explicit active-identity queries; run `cd apps/api && php artisan test tests/Feature/DataIntelligenceIntegrationTest.php --filter='concurrent_scheduler_and_manual_run_cannot_publish_a_competing_snapshot'` and require `Expected: PASS`.
18. Commit: `git add apps/api/tests/Feature/DataIntelligenceIntegrationTest.php && git commit -m "test(data-intelligence): verify concurrent publication guard"`

19. `[no-tdd — final verification task]` Run the exact full API and web verification commands after T9 integration tests pass:
    Files: `apps/api/tests/Feature/DataIntelligenceIntegrationTest.php` (the sole T9 file; no verification report or source artifact is created).
    Test: `cd apps/api && php artisan test`
    Expected: PASS for the complete API suite, including existing AI, analytics and authorization tests.
    Test: `cd apps/web && npm test -- --runInBand`
    Expected: PASS for the complete web test suite.
    Test: `cd apps/web && npx tsc --noEmit --pretty false`
    Expected: PASS with no TypeScript diagnostics.
    Test: `cd apps/web && npm run build`
    Expected: PASS with a production build.
    Non-testable/final-verification exception: these commands validate the integrated repository and do not introduce a source artifact; no additional TDD implementation step or meaningless verification commit is allowed. If a verification report is required by release operations, create it outside this plan and outside T9.

## REFERENCES LOADED
- Spec scheduled complete snapshot, atomic publication, failure retention and auditability GWT scenarios.
- T2–T7 packet contracts and their public scheduler, service, route and consumer seams; T9 may edit only its integration test.
- `apps/api/tests/Feature/DeliveryConcurrencyTest.php`, `InvoiceReminderPostgresConcurrencyTest.php` — real database concurrency/integration conventions.
- `apps/api/routes/api.php` — admin endpoint boundary.

## WHY THIS APPROACH
The scenario spans scheduler/command, orchestrator, multiple metric producers and HTTP consumers; unit/feature tests can all pass while cross-unit publication behavior remains unproven. A test-only integration task verifies the public collaboration after T2–T7 provide the production seams. Complexity: deep/test-risk.

## SANDWICH CONTEXT
[CRITICAL: The integration test must prove one atomic complete publication across producer/consumer units; no partial stage or competing snapshot may be visible to admin consumers.]
You are verifying the cross-unit pipeline publication for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: Option A — orchestrated immutable snapshot.
Files in scope: only `apps/api/tests/Feature/DataIntelligenceIntegrationTest.php`.
Test framework: Laravel PHPUnit integration tests with real database/transactions, real locks/process coordination and frozen Asia/Jakarta clock.
Available after: T7 backend contracts/routes.
Architecture rule: verify producer/consumer collaboration through public command/HTTP boundaries; do not mock units whose collaboration is under test; do not modify production files in T9.
[RESTATE: The integration test must prove one atomic complete publication across producer/consumer units; no partial stage or competing snapshot may be visible to admin consumers.]

## DELIVERABLE
Given valid source data, When the 02:00 scheduler runs, Then one versioned completed run publishes one complete immutable snapshot with metric definitions, lineage, source windows and consumer-visible BI outputs.
Given a stage failure, When the public pipeline finishes, Then the prior snapshot remains active with no partial output and failed error lineage is recorded.
Given concurrent scheduler and manual execution, When both public boundaries run, Then only one complete snapshot is published and the other receives an explicit conflict/status result.
Given the integrated repository, When the exact API and web verification commands run, Then every command passes.
All integration tests PASS and the test-only conventional commits exist; the final verification produces no artifact or commit.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Public-boundary integration verification, not mocked service collaboration.
- Complete snapshot/version/lineage and all producer consumers agree on the active publication.
- Failed stages preserve the prior active snapshot with no partial consumer output.
- Real scheduler/manual lock contention proves no competing publication.
- Exact API full-suite and web test/typecheck/build commands pass.

Must-not-have:
- No production-file edits in T9, test-only bypass of transaction/lock behavior, partial publication acceptance, legacy endpoint change, automatic retry, or separate analytics system.

Open question risks:
- Database engine locking behavior must be proven in the supported test/runtime configuration; if SQLite cannot prove the production PostgreSQL guarantee, report NEEDS_CONTEXT and retain the PostgreSQL-targeted assertion rather than weakening it.

Rollback note:
- If integration fails after deployment, disable schedule/trigger and retain last successful snapshot; correct the owning T2–T7 implementation and rerun T9.

## STOP CONDITIONS
Done when: all three integration TDD cycles PASS, the exact API/web final-verification commands PASS, and the three test-only commits exist; the no-TDD final verification has no commit.
Uncertain when: test database cannot exercise the production lock/atomicity guarantee or the full-suite commands fail.
Escalate when: any producer publishes directly outside the orchestrator, a consumer reads partial staging data, or T9 requires a production-file edit.
