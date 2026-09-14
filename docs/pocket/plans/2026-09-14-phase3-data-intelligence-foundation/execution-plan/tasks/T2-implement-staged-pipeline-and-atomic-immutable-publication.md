# Task T2 — Implement staged pipeline and atomic immutable publication

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Implement staged pipeline and atomic immutable publication [depends: T1] [test-risk]

## OBJECTIVE
Implement the deterministic pipeline service that calculates the prior-day and 30-day rolling source window, records run/version/lineage and metric definitions, registers the named geographic, supplier, stock and measurement stage outputs, stages output, and atomically promotes exactly one complete immutable snapshot only after all stages succeed. Create the shared `ActiveDataSnapshotReader` service: it reads the single active immutable snapshot, exposes typed section/version/window metadata to consumer services, and never mutates or returns staging/failed snapshots. Failed runs retain the previous active snapshot and record an error.

Files:
- Create: `apps/api/app/Services/DataPipelineService.php`
- Create: `apps/api/app/Services/ActiveDataSnapshotReader.php`
- Test: `apps/api/tests/Feature/DataPipelineServiceTest.php`

Steps:
1. Write failing test for: failed stage preserves the last successful snapshot
   Test file: `apps/api/tests/Feature/DataPipelineServiceTest.php`
   Level: integration
   Test intent: Given a previous successful snapshot is active and a new pipeline run fails during one stage, When the run finishes, Then the run is marked failed with an error record, the previous successful snapshot remains active, and no partial result is published.
   Exercise through: `DataPipelineService` public run entry point and database transaction/publication boundary.
   Test doubles: fake only the injectable stage failure/callback and freeze the clock; do not mock `DataPipelineService`, `DataPipelineRun`, or the database transaction.
   Expected RED: no pipeline service exists and partial publication/failure retention behavior is absent.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='failed_stage_preserves_last_successful_snapshot'`
   Expected failure: class/method not found or assertion that failed run published data.
3. Implement minimal code: add the prior-day/30-day window, named stage-output registration for the `geographic`, `supplier`, `stock`, and `measurement` sections, stage execution, run status/error recording, staging rows, transaction/lock-protected active publication, and immutable snapshot promotion only after all registered stages succeed. Define the consumer contract used by the reader: each published section carries its typed payload plus `snapshot_version` and window metadata; failed/staging rows are never eligible for publication.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='failed_stage_preserves_last_successful_snapshot'`
   Expected: PASS.
5. Refactor while green (bounded): keep publication in a named domain service method; extract a domain-scoped helper only if identical lineage/metric serialization appears three times; re-run `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='failed_stage_preserves_last_successful_snapshot'` and keep PASS.
6. Commit: `git add apps/api/app/Services/DataPipelineService.php apps/api/tests/Feature/DataPipelineServiceTest.php && git commit -m "feat(data-intelligence): publish immutable pipeline snapshots"`

7. Write failing test for: empty data is a valid safe run
   Test file: `apps/api/tests/Feature/DataPipelineServiceTest.php`
   Level: integration
   Test intent: Given the source window contains no eligible operational data, When the pipeline runs, Then it may publish a completed empty snapshot with zero/empty outputs and does not delete or corrupt prior audit history.
   Exercise through: `DataPipelineService` public run method against the test database.
   Test doubles: fake the source-stage query result as empty and freeze the Asia/Jakarta clock; do not mock the service or persistence models.
   Expected RED: no empty snapshot path exists and audit history may be skipped.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='empty_data_is_a_valid_safe_run'`
   Expected failure: missing service or non-empty/failed result assertion.
9. Implement minimal code: treat empty source rows as valid zero/empty stage output, persist run/definition/lineage metadata, and publish without deleting prior runs.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='empty_data_is_a_valid_safe_run'`
    Expected: PASS.
11. Refactor while green (bounded): preserve decimal-safe persisted values and explicit source-window metadata; re-run `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='empty_data_is_a_valid_safe_run'` and keep PASS.
12. Commit: `git add apps/api/app/Services/DataPipelineService.php apps/api/tests/Feature/DataPipelineServiceTest.php && git commit -m "test(data-intelligence): cover empty pipeline snapshots"`

13. Write failing test for: active snapshot reader returns only the published snapshot and version
   Test name: `active_snapshot_reader_returns_published_snapshot_version_and_hides_staging_or_failed_data`
   Test file: `apps/api/tests/Feature/DataPipelineServiceTest.php`
   Level: integration
   Test intent: Given one published immutable snapshot is active, plus staged output for a newer run and failed output for another run, When `ActiveDataSnapshotReader` reads the active snapshot and each registered section, Then it returns the active snapshot version, typed `geographic`/`supplier`/`stock`/`measurement` section payloads, and the active window metadata, while exposing neither staging nor failed rows; the reader exposes no snapshot mutation operation.
   Exercise through: the public `ActiveDataSnapshotReader` contract and real `DataSnapshot`/`DataSnapshotValue` records; do not query or select staging/failed rows in the test as a substitute for the reader.
   Test doubles: mock none; use the real test database and immutable snapshot records; do not mock the reader, snapshots, or publication state.
   Expected RED: `ActiveDataSnapshotReader` or its active/version/typed-section contract is absent, or staging/failed data can be returned.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='active_snapshot_reader_returns_published_snapshot_version_and_hides_staging_or_failed_data'`
   Expected failure: reader class/contract is missing, active version/section metadata is absent, or non-published output is visible.
15. Implement minimal code: create `apps/api/app/Services/ActiveDataSnapshotReader.php` with a read-only public contract that selects exactly the one active immutable `DataSnapshot`, returns typed section payloads plus `snapshot_version` and `{start,end,timezone}` window metadata, and excludes staging/failed snapshots. Wire `DataPipelineService` to publish the registered stage sections with that metadata; the reader must contain no create/update/delete/publish behavior.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='active_snapshot_reader_returns_published_snapshot_version_and_hides_staging_or_failed_data'`
    Expected: PASS; the reader returns the active version and all four typed sections/window fields and never returns staged or failed output.
17. Refactor while green (bounded): keep the reader as the single named read seam and keep stage registration/publication in `DataPipelineService`; re-run `cd apps/api && php artisan test tests/Feature/DataPipelineServiceTest.php --filter='active_snapshot_reader_returns_published_snapshot_version_and_hides_staging_or_failed_data'` and require `Expected: PASS`.
18. Commit: `git add apps/api/app/Services/DataPipelineService.php apps/api/app/Services/ActiveDataSnapshotReader.php apps/api/tests/Feature/DataPipelineServiceTest.php && git commit -m "feat(data-intelligence): expose active snapshot reader"`

## REFERENCES LOADED
- Spec pipeline GWT scenarios and implementation notes on Asia/Jakarta windows, staging, atomic publish, method versions and lineage.
- `apps/api/app/Services/AnalyticsService.php`, `RecommendationService.php`, `ForecastService.php`, `SegmentationService.php` — deterministic source semantics to reuse rather than duplicate.
- `apps/api/app/Models/Order.php`, `Payment.php`, `Delivery.php` — source relations and casts.
- `apps/api/tests/Feature/AnalyticsTest.php`, `apps/api/tests/Feature/InvoiceReminderPostgresConcurrencyTest.php` — feature and concurrency test patterns.

## WHY THIS APPROACH
The service owns one bounded transaction/publication seam and keeps failed stages from leaking into the active snapshot. Complexity: deep, due to persistence, transaction, locking, versioning and partial-failure behavior.

## SANDWICH CONTEXT
[CRITICAL: Publish only a complete staged result inside a transaction protected against competing active publications; failed runs must never replace the last successful snapshot.]
You are implementing staged pipeline publication for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: Option A — orchestrated immutable snapshot.
Files in scope: `apps/api/app/Services/DataPipelineService.php`, `apps/api/tests/Feature/DataPipelineServiceTest.php`.
Test framework: Laravel PHPUnit integration tests with real test database, transaction/lock behavior, and faked clock/stages only.
Available after: T1.
Architecture rule: all writes use Eloquent/database transactions; no live-query replacement of snapshots and no automatic retry.
[RESTATE: Publish only a complete staged result inside a transaction protected against competing active publications; failed runs must never replace the last successful snapshot.]

## DELIVERABLE
Given a previous active snapshot and a stage failure, When the pipeline finishes, Then the run is failed, error lineage is recorded, no partial result is active, and the previous snapshot remains active.
Given no eligible source data, When the pipeline runs, Then a completed empty snapshot with safe zero/empty outputs may publish and prior audit history remains.
All tests PASS and two conventional commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Prior-day and rolling 30-day source windows use Asia/Jakarta boundaries.
- Runs have status, pipeline version, metric definitions, source window, queryable lineage and immutable snapshot values.
- Publication is atomic; partial/failed runs cannot become active.
- No automatic retry.
- RED is observed before implementation for each GWT.

Must-not-have:
- No mutation of a published snapshot, binary-floating persisted money, Python/LLM, or separate warehouse.
- No changes to legacy AI endpoint behavior.

Open question risks:
- Late data is included only on the next run; if operational requirements demand same-day correction, stop for re-planning.

Rollback note:
- Disable scheduler/manual trigger and retain the last successful snapshot; revert additive migration/deployment if publication is defective.

## STOP CONDITIONS
Done when: both scenarios pass with atomic publication and commits exist.
Uncertain when: the database driver cannot provide the required active-run/publish lock semantics.
Escalate when: implementation needs automatic retry or mutates historical snapshots.
