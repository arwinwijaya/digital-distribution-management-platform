# Task T3 — Add scheduler, admin trigger, status and overlap prevention

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Add scheduler, admin trigger, status and overlap prevention [depends: T2] [test-risk]

## OBJECTIVE
Expose the pipeline through an Artisan command scheduled for 02:00 Asia/Jakarta and admin-only status/manual-trigger endpoints. Prevent overlapping scheduler/manual runs with an explicit conflict/status response and create a new run identity for a manual rerun without automatic retry.

Files:
- Create: `apps/api/app/Console/Commands/RunDataPipeline.php`
- Modify: `apps/api/app/Console/Kernel.php`
- Create: `apps/api/app/Http/Controllers/DataPipelineController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/DataPipelineTriggerTest.php`

Steps:
1. Write failing test for: overlapping run is prevented
   Test file: `apps/api/tests/Feature/DataPipelineTriggerTest.php`
   Level: integration
   Test intent: Given one pipeline run is active, When another scheduler or admin trigger starts another run, Then the second run is rejected or skipped with an explicit conflict/status response and cannot publish a competing snapshot.
   Exercise through: authenticated admin POST manual-trigger route and the Artisan command's shared service boundary.
   Test doubles: fake only the long-running stage/clock; do not mock the overlap guard, controller, command, or database lock.
   Expected RED: routes/command/overlap guard do not exist.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='overlapping_run_is_prevented'`
   Expected failure: route or command not found, or competing run assertion fails.
3. Implement minimal code: add the command and register exactly `$schedule->command('data:pipeline')->dailyAt('02:00')->timezone('Asia/Jakarta')->withoutOverlapping();`, plus the database active-run guard and admin status/trigger controller methods and routes.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='overlapping_run_is_prevented'`
   Expected: PASS.
5. Refactor while green (bounded): keep authorization at the controller boundary and share only the named pipeline service guard; re-run `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='overlapping_run_is_prevented'` and keep PASS.
6. Commit: `git add apps/api/app/Console/Commands/RunDataPipeline.php apps/api/app/Console/Kernel.php apps/api/app/Http/Controllers/DataPipelineController.php apps/api/routes/api.php apps/api/tests/Feature/DataPipelineTriggerTest.php && git commit -m "feat(data-intelligence): schedule and guard pipeline runs"`

7. Write failing test for: admin manually triggers a failed pipeline
   Test file: `apps/api/tests/Feature/DataPipelineTriggerTest.php`
   Level: feature
   Test intent: Given the previous run failed and its error is recorded, When an authenticated admin invokes the manual trigger endpoint, Then a new run is created using a new run identity and no automatic retry is performed by the system.
   Exercise through: POST admin trigger endpoint and persisted run identities.
   Test doubles: fake the pipeline service result/error only; do not mock authorization, controller, or run identity persistence.
   Expected RED: manual route does not exist or reuses the failed run identity/automatically retries.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='admin_manually_triggers_a_failed_pipeline'`
   Expected failure: route/controller not found or duplicate run identity assertion fails.
9. Implement minimal code: return the explicit accepted/new-run response, record a fresh UUID/identity and leave automatic retry absent; enforce existing authorization error contract for unauthenticated/non-admin callers.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='admin_manually_triggers_a_failed_pipeline'`
    Expected: PASS.
11. Refactor while green (bounded): keep scheduler and HTTP trigger as thin adapters over `DataPipelineService`; re-run `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='admin_manually_triggers_a_failed_pipeline'` and keep PASS.
12. Commit: `git add apps/api/app/Console/Commands/RunDataPipeline.php apps/api/app/Http/Controllers/DataPipelineController.php apps/api/routes/api.php apps/api/tests/Feature/DataPipelineTriggerTest.php && git commit -m "feat(data-intelligence): add admin pipeline trigger"`

13. Write failing test for: scheduled pipeline runs at 02:00 WIB over prior-day and rolling windows
   Test file: `apps/api/tests/Feature/DataPipelineTriggerTest.php`
   Level: integration
   Test intent: Given the scheduler reaches 02:00 WIB, When the daily job runs, Then it invokes a versioned pipeline run using the prior-day and rolling operational windows.
   Exercise through: Laravel scheduler inspection and Artisan command invocation with Asia/Jakarta clock.
   Test doubles: fake the pipeline stage execution and freeze time; do not mock the scheduler registration itself.
   Expected RED: the command is not registered at the required timezone/time or does not pass the required window.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='scheduled_pipeline_runs_at_0200_wib'`
    Expected failure: scheduler event/window assertion fails.
15. Implement minimal code: register the command at exact 02:00 Asia/Jakarta and pass the prior-day/30-day window metadata through the service.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='scheduled_pipeline_runs_at_0200_wib'`
    Expected: PASS.
17. Refactor while green (bounded): keep time-zone conversion explicit and avoid server-local assumptions; re-run `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='scheduled_pipeline_runs_at_0200_wib'` and keep PASS.
18. Commit: `git add apps/api/app/Console/Kernel.php apps/api/app/Console/Commands/RunDataPipeline.php apps/api/tests/Feature/DataPipelineTriggerTest.php && git commit -m "test(data-intelligence): verify Jakarta pipeline schedule"`

19. Write failing test for: admin status returns current run, active publication version and source window
   Test file: `apps/api/tests/Feature/DataPipelineTriggerTest.php`
   Level: feature
   Test intent: Given an authenticated admin can observe a completed run and its active snapshot, When the admin requests the status endpoint, Then the JSON response contains `run.status`, `run.pipeline_version`, `snapshot.version`, `window.start`, `window.end`, and `window.timezone` (`Asia/Jakarta`), while unauthenticated and non-admin callers receive the existing 401/403 authorization contract.
   Exercise through: GET admin pipeline-status HTTP route and persisted `DataPipelineRun`/`DataSnapshot` records; issue real unauthenticated and outlet-authenticated requests to the same route.
   Test doubles: mock none; use real auth middleware and database records; do not mock the controller, authorization, or status models.
   Expected RED: status route/response is missing, omits version/window fields, or permits unauthenticated/non-admin access.
20. Run test — verify FAIL:
    `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='admin_status_returns_current_run_active_publication_version_and_source_window'`
    Expected failure: route not found, missing status/version/window JSON paths, or authorization status differs from the existing contract.
21. Implement minimal code: return the latest run status, active snapshot version, pipeline version, prior-day/30-day window start/end and explicit `Asia/Jakarta` timezone from the admin-only status endpoint.
22. Run test — verify PASS:
    `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='admin_status_returns_current_run_active_publication_version_and_source_window'`
    Expected: PASS with all status/version/window fields and 401/403 authorization assertions.
23. Refactor while green (bounded): keep status serialization in the controller response resource/boundary and preserve explicit window names; run `cd apps/api && php artisan test tests/Feature/DataPipelineTriggerTest.php --filter='admin_status_returns_current_run_active_publication_version_and_source_window'` and require PASS.
24. Commit: `git add apps/api/app/Http/Controllers/DataPipelineController.php apps/api/routes/api.php apps/api/tests/Feature/DataPipelineTriggerTest.php && git commit -m "feat(data-intelligence): expose admin pipeline status"`

## REFERENCES LOADED
- Pipeline scheduling/overlap/manual-trigger GWT scenarios and rollback plan from the spec.
- `apps/api/app/Console/Kernel.php`, `apps/api/app/Console/Commands/ProcessInvoiceReminders.php` — scheduler and command conventions.
- `apps/api/routes/api.php`, `apps/api/app/Http/Controllers/AnalyticsController.php` — authenticated admin route/controller boundary and error response conventions.
- `apps/api/tests/Feature/InvoiceReminderTimingTest.php`, `DeliveryConcurrencyTest.php` — time and concurrency test patterns.

## WHY THIS APPROACH
This task keeps operational adapters separate from the core transaction service, making scheduler and manual trigger behavior independently verifiable. Complexity: standard/deep because schedule timezone, admin authorization and concurrency interact.

## SANDWICH CONTEXT
[CRITICAL: Every new pipeline/status/BI endpoint must be admin-only while legacy AI outlet access remains unchanged.]
You are implementing operational entry points for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: Option A — orchestrated immutable snapshot.
Files in scope: only files listed in this task.
Test framework: Laravel PHPUnit feature/integration tests with real scheduler metadata and database guard.
Available after: T2.
Architecture rule: no automatic retry; all triggers use the same guarded service and Asia/Jakarta schedule.
[RESTATE: Every new pipeline/status/BI endpoint must be admin-only while legacy AI outlet access remains unchanged.]

## DELIVERABLE
Given one active run, When a second scheduler/manual trigger starts, Then it receives an explicit conflict/status result and cannot publish a competing snapshot.
Given a recorded failed run, When an admin manually triggers, Then a new run identity is created and no automatic retry occurs.
Given 02:00 WIB, When the scheduler runs, Then the command uses prior-day and rolling windows.
All tests PASS and three conventional commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Exact `Asia/Jakarta` 02:00 schedule and explicit window metadata.
- Admin-only status/manual-trigger authorization and existing JSON error contract.
- Overlap guard works across scheduler/manual paths and does not publish a competitor.
- RED before implementation and conventional commits.

Must-not-have:
- No automatic retry, non-admin access, legacy AI route changes, or scheduler-local timezone assumptions.

Open question risks:
- If the runtime does not load `App\\Console\\Kernel` schedule definitions in the existing deployment, report NEEDS_CONTEXT rather than bypassing the scheduler test.

Rollback note:
- Disable schedule and manual routes while retaining last successful snapshot.

## STOP CONDITIONS
Done when: all three scenarios pass and commits exist.
Uncertain when: schedule introspection cannot prove timezone/time or lock semantics differ by supported database.
Escalate when: any endpoint is reachable by outlet/unauthenticated callers or auto-retry is proposed.
