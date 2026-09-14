# EXECUTION PLAN — Phase 3 Data Intelligence & AI Foundation

**Date:** 2026-09-14  
**Spec:** docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md  
**Status:** draft  
**Total tasks:** 9

---

## Execution Overview

### Recommended Order
```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8, T9 (parallel)
```

Dependency order above is recommended; pocket-development enforces actual blocking and sequencing.

### Parallelizable Groups

| Group | Tasks | Unblocked After |
|---|---|---|
| Group A | T3, T4, T5, T6, T7 | T2 completes, serialized because they share `apps/api/routes/api.php` |
| Group B | T8, T9 | T7 completes |

### Constraints Reminder

**Architecture:** Use Laravel migrations/Eloquent/service/controller/request patterns, PostgreSQL transactions/locks and deterministic explainable calculations. New endpoints are admin-only at the request boundary. Keep snapshots immutable and publication atomic. Frontend consumes the shared contract and isolates Leaflet from server rendering.  
**Out-of-scope:** No Python/LLM, ML training, separate analytics platform, full campaign/cross-selling workflow, automatic retry, external map provider, or legacy AI contract/authorization change.  
**Assumptions at risk:** Asia/Jakarta defines the 02:00 WIB window; territories are explicitly admin-managed; late data appears in the next run; WAPE all-zero periods remain pending; OSM tiles require attribution.  
**Sequencing:** Dependency annotations are recommended order; do not bypass a prerequisite contract/schema or the integration verification.

### File Structure Map

Every file named in a packet is listed below. Existing files are marked `Modify`; new files are marked `Create` and identify their task.

```
Rule: Shared schema and API contracts
  Create: apps/api/database/migrations/2026_09_14_000024_create_territories_table.php (created by: T1)
  Create: apps/api/database/migrations/2026_09_14_000025_add_territory_id_to_outlets_table.php (created by: T1)
  Create: apps/api/database/migrations/2026_09_14_000026_add_lead_time_days_to_suppliers_table.php (created by: T1)
  Create: apps/api/database/migrations/2026_09_14_000027_create_data_intelligence_tables.php (created by: T1)
  Create: apps/api/database/migrations/2026_09_14_000028_create_recommendation_events_table.php (created by: T1)
  Create: apps/api/app/Models/Territory.php (created by: T1)
  Create: apps/api/app/Models/DataPipelineRun.php (created by: T1)
  Create: apps/api/app/Models/DataMetricDefinition.php (created by: T1)
  Create: apps/api/app/Models/DataSnapshot.php (created by: T1)
  Create: apps/api/app/Models/DataSnapshotValue.php (created by: T1)
  Create: apps/api/app/Models/RecommendationEvent.php (created by: T1)
  Modify: apps/api/app/Models/Outlet.php
  Modify: apps/api/app/Models/Supplier.php
  Create: apps/web/src/lib/data-intelligence-types.ts (created by: T1)
  Modify: apps/web/package.json
  Modify: package-lock.json
  Test: apps/api/tests/Feature/DataIntelligenceSchemaTest.php
  Test: apps/web/src/lib/data-intelligence-types.test.ts

Rule: Pipeline foundation, immutable publication, and active consumer seam
  Create: apps/api/app/Services/DataPipelineService.php (created by: T2)
  Create: apps/api/app/Services/ActiveDataSnapshotReader.php (created by: T2)
  Test: apps/api/tests/Feature/DataPipelineServiceTest.php

Rule: Scheduling, manual trigger, status and overlap guard
  Create: apps/api/app/Console/Commands/RunDataPipeline.php (created by: T3)
  Modify: apps/api/app/Console/Kernel.php
  Create: apps/api/app/Http/Controllers/DataPipelineController.php (created by: T3)
  Modify: apps/api/routes/api.php
  Test: apps/api/tests/Feature/DataPipelineTriggerTest.php

Rule: Territory CRUD and geographic BI
  Create: apps/api/app/Http/Requests/StoreTerritoryRequest.php (created by: T4)
  Create: apps/api/app/Http/Requests/UpdateTerritoryRequest.php (created by: T4)
  Create: apps/api/app/Http/Requests/AssignTerritoryRequest.php (created by: T4)
  Create: apps/api/app/Services/GeographicAnalyticsService.php (created by: T4)
  Create: apps/api/app/Http/Controllers/TerritoryController.php (created by: T4)
  Create: apps/api/app/Http/Controllers/GeographicAnalyticsController.php (created by: T4)
  Modify: apps/api/routes/api.php
  Test: apps/api/tests/Feature/GeographicAnalyticsTest.php

Rule: Supplier performance BI
  Create: apps/api/app/Services/SupplierPerformanceService.php (created by: T5)
  Create: apps/api/app/Http/Controllers/SupplierPerformanceController.php (created by: T5)
  Modify: apps/api/routes/api.php
  Test: apps/api/tests/Feature/SupplierPerformanceTest.php

Rule: Stock planning and replenishment
  Create: apps/api/app/Services/StockPlanningService.php (created by: T6)
  Create: apps/api/app/Http/Controllers/StockPlanningController.php (created by: T6)
  Modify: apps/api/routes/api.php
  Test: apps/api/tests/Feature/StockPlanningTest.php

Rule: Sparse AI fallback, recommendation funnel and forecast measurement
  Modify: apps/api/app/Services/RecommendationService.php
  Modify: apps/api/app/Services/ForecastService.php
  Create: apps/api/app/Services/MeasurementService.php (created by: T7)
  Create: apps/api/app/Http/Controllers/MeasurementController.php (created by: T7)
  Modify: apps/api/routes/api.php
  Test: apps/api/tests/Feature/MeasurementTest.php
  Test: apps/api/tests/Feature/AITest.php (existing compatibility suite; read/run only, do not change)

Rule: Next.js admin data-intelligence surface
  Create: apps/web/src/lib/data-intelligence-api.ts (created by: T8)
  Create: apps/web/src/app/data-intelligence/page.tsx (created by: T8)
  Create: apps/web/src/components/data-intelligence/GeoMap.tsx (created by: T8)
  Create: apps/web/src/components/data-intelligence/TerritoryTable.tsx (created by: T8)
  Create: apps/web/src/components/data-intelligence/SupplierPerformanceTable.tsx (created by: T8)
  Create: apps/web/src/components/data-intelligence/StockPlanningTable.tsx (created by: T8)
  Create: apps/web/src/components/data-intelligence/MeasurementCards.tsx (created by: T8)
  Modify: apps/web/src/components/Sidebar.tsx
  Test: apps/web/src/app/data-intelligence/page.test.tsx
  Test: apps/web/src/components/data-intelligence/GeoMap.test.tsx

Rule: Cross-unit publication verification
  Test: apps/api/tests/Feature/DataIntelligenceIntegrationTest.php
```

---

## Pocket Packets

---

### Task 1: Define shared data-intelligence schema and API contracts [prereq]

## OBJECTIVE
Create the additive database schema, Eloquent models/relations, shared frontend response/event types, and Leaflet dependency declaration that every backend and frontend task consumes. Add constraints needed for immutable snapshots, one active run, event UUID idempotency, territory assignment, and supplier lead time.

Files:
- Create: `apps/api/database/migrations/2026_09_14_000024_create_territories_table.php`
- Create: `apps/api/database/migrations/2026_09_14_000025_add_territory_id_to_outlets_table.php`
- Create: `apps/api/database/migrations/2026_09_14_000026_add_lead_time_days_to_suppliers_table.php`
- Create: `apps/api/database/migrations/2026_09_14_000027_create_data_intelligence_tables.php`
- Create: `apps/api/database/migrations/2026_09_14_000028_create_recommendation_events_table.php`
- Create: `apps/api/app/Models/Territory.php`
- Create: `apps/api/app/Models/DataPipelineRun.php`
- Create: `apps/api/app/Models/DataMetricDefinition.php`
- Create: `apps/api/app/Models/DataSnapshot.php`
- Create: `apps/api/app/Models/DataSnapshotValue.php`
- Create: `apps/api/app/Models/RecommendationEvent.php`
- Modify: `apps/api/app/Models/Outlet.php`
- Modify: `apps/api/app/Models/Supplier.php`
- Create: `apps/web/src/lib/data-intelligence-types.ts`
- Modify: `apps/web/package.json`
- Modify: `package-lock.json`
- Test: `apps/api/tests/Feature/DataIntelligenceSchemaTest.php`
- Test: `apps/web/src/lib/data-intelligence-types.test.ts`

Steps:
1. Write failing test for: schema exposes territory, supplier lead-time, immutable snapshot, pipeline-run, metric-definition, and recommendation-event constraints
   Test file: `apps/api/tests/Feature/DataIntelligenceSchemaTest.php`
   Level: integration
   Test intent: Given a fresh test database, When the data-intelligence migrations run and representative records are created, Then territory assignment, positive/nullable lead time, run status/version/window, metric definitions, immutable snapshot values, and unique event UUID fields are available; duplicate event UUIDs are rejected by the database.
   Exercise through: Laravel migrations and Eloquent model public attributes/relations; do not bypass the database constraint.
   Test doubles: mock none; use the test database and model factories/records. Do not mock migrations or models under test.
   Expected RED: tables, columns, models, and uniqueness constraints do not exist.
2. Run test — verify FAIL:
   `cd apps/api && php artisan test tests/Feature/DataIntelligenceSchemaTest.php --filter='schema_exposes_data_intelligence_constraints'`
   Expected failure: missing table/model or failed schema assertion.
3. Implement minimal code to satisfy the test: create the five additive migrations, six models, Outlet territory relation/fillable field, Supplier lead-time field/cast, and shared model casts/relations; keep snapshot values immutable by omitting update paths and enforcing database identity constraints.
4. Run test — verify PASS:
   `cd apps/api && php artisan test tests/Feature/DataIntelligenceSchemaTest.php --filter='schema_exposes_data_intelligence_constraints'`
   Expected: PASS.
5. Refactor while green (bounded): keep migrations additive and reversible; extract no generic helper; re-run `cd apps/api && php artisan test tests/Feature/DataIntelligenceSchemaTest.php --filter='schema_exposes_data_intelligence_constraints'` and keep PASS.
6. Commit: `git add apps/api/database/migrations/2026_09_14_000024_create_territories_table.php apps/api/database/migrations/2026_09_14_000025_add_territory_id_to_outlets_table.php apps/api/database/migrations/2026_09_14_000026_add_lead_time_days_to_suppliers_table.php apps/api/database/migrations/2026_09_14_000027_create_data_intelligence_tables.php apps/api/database/migrations/2026_09_14_000028_create_recommendation_events_table.php apps/api/app/Models/Territory.php apps/api/app/Models/DataPipelineRun.php apps/api/app/Models/DataMetricDefinition.php apps/api/app/Models/DataSnapshot.php apps/api/app/Models/DataSnapshotValue.php apps/api/app/Models/RecommendationEvent.php apps/api/app/Models/Outlet.php apps/api/app/Models/Supplier.php apps/api/tests/Feature/DataIntelligenceSchemaTest.php && git commit -m "feat(data-intelligence): add shared persistence schema"`

7. Write failing test for: frontend shared types compile with Leaflet dependencies
   Test file: `apps/web/src/lib/data-intelligence-types.test.ts`
   Level: unit
   Test intent: Given the API contract interfaces for runs, snapshots, territories, map points, supplier scores, stock plans, funnel metrics and WAPE, When TypeScript checks representative values, Then the shared contract compiles without `any` casts and the package declares `leaflet` and `react-leaflet`.
   Exercise through: TypeScript compiler and package manifest; do not mock the types.
   Test doubles: mock none; do not mock the compiler or package manifest.
   Expected RED: the contract file and Leaflet packages are absent.
8. Run test — verify FAIL:
   `cd apps/web && npx tsc --noEmit --pretty false`
   Expected failure: cannot find `data-intelligence-types` or Leaflet module declarations.
9. Implement minimal code to satisfy the test: add `apps/web/src/lib/data-intelligence-types.ts` with the shared request/response/event contracts and add `leaflet` plus `react-leaflet` compatible with Next.js 16/React 18 to `apps/web/package.json`.
10. Run test — verify PASS:
    `cd apps/web && npx tsc --noEmit --pretty false`
    Expected: PASS.
11. Refactor while green (bounded): keep types domain-scoped and avoid duplicating API shapes; re-run `cd apps/web && npx tsc --noEmit --pretty false` and keep PASS.
12. Commit: `git add apps/web/src/lib/data-intelligence-types.ts apps/web/src/lib/data-intelligence-types.test.ts apps/web/package.json package-lock.json && git commit -m "feat(web): define data intelligence contracts"`

13. Write failing test for: snapshot immutability and one-active-run/publication uniqueness are database-enforced
   Test file: `apps/api/tests/Feature/DataIntelligenceSchemaTest.php`
   Level: integration
   Test intent: Given a published snapshot and an active pipeline run already exist, When a real database update/delete is attempted against the published snapshot and a second active run or active publication is inserted, Then the database rejects snapshot mutation and each duplicate active identity with a constraint/trigger error; the original snapshot and exactly one active run/publication remain queryable.
   Exercise through: Laravel migrations and direct `DB::table`/Eloquent writes against the real test database; assert persisted rows after each rejected write and do not bypass constraints or use mocks.
   Test doubles: mock none; use PostgreSQL-compatible test database transactions; do not mock migrations, models, locks, or the database.
   Expected RED: published snapshot mutation is possible or duplicate active run/publication writes are accepted.
14. Run test — verify FAIL:
    `cd apps/api && php artisan test tests/Feature/DataIntelligenceSchemaTest.php --filter='snapshot_immutability_and_one_active_run_publication_uniqueness_are_database_enforced'`
    Expected failure: the database trigger/unique active indexes are missing or the duplicate insert/update does not raise a database exception.
15. Implement minimal code: add database-enforced immutable snapshot protection for published snapshots and partial unique constraints for one active run and one active publication; keep safe down migrations and preserve the original rows after rejected writes.
16. Run test — verify PASS:
    `cd apps/api && php artisan test tests/Feature/DataIntelligenceSchemaTest.php --filter='snapshot_immutability_and_one_active_run_publication_uniqueness_are_database_enforced'`
    Expected: PASS with real database constraint/trigger exceptions and exactly one active run/publication remaining.
17. Refactor while green (bounded): keep immutability and active-identity rules in additive migrations with explicit names; run `cd apps/api && php artisan test tests/Feature/DataIntelligenceSchemaTest.php --filter='snapshot_immutability_and_one_active_run_publication_uniqueness_are_database_enforced'` and require PASS.
18. Commit: `git add apps/api/database/migrations/2026_09_14_000027_create_data_intelligence_tables.php apps/api/tests/Feature/DataIntelligenceSchemaTest.php && git commit -m "fix(data-intelligence): enforce snapshot and active identity invariants"`

## REFERENCES LOADED
- `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md` — architecture constraints, all GWT rules, immutable snapshot and idempotency requirements.
- `apps/api/app/Models/Outlet.php`, `apps/api/app/Models/Supplier.php`, `apps/api/app/Models/Order.php`, `apps/api/app/Models/Delivery.php` — existing Eloquent fillable/cast/relation conventions.
- `apps/api/database/migrations/2026_09_08_000004_create_suppliers_table.php`, `apps/api/database/migrations/2026_09_08_000012_create_deliveries_table.php` — additive migration and index conventions.
- `apps/api/tests/Feature/AnalyticsTest.php` — RefreshDatabase feature-test convention.
- `apps/web/package.json`, `apps/web/src/lib/api.ts` — Next/Jest/TypeScript and API-client conventions.
- Context7 React Leaflet documentation checked for Next client-only integration, required CSS/container height, and OpenStreetMap attribution; version compatibility is constrained to existing Next.js 16/React 18.

## WHY THIS APPROACH
This is the shared-interface prerequisite: backend and frontend must agree on immutable snapshot, BI, measurement, and event shapes before parallel implementation. Complexity: deep, because schema identity/immutability and contract compatibility constrain every later task.

## SANDWICH CONTEXT
[CRITICAL: The schema must preserve immutable, versioned snapshots and database-enforced idempotency; no later task may mutate a published snapshot.]
You are implementing the shared schema and contract for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: Option A — one Laravel orchestrator stages all metrics and atomically publishes an immutable snapshot.
Files in scope: only the files listed in this task.
Test framework: Laravel PHPUnit feature tests with `RefreshDatabase`; frontend TypeScript/Jest conventions.
Available after: none; this is the prerequisite.
Architecture rule: additive Eloquent migrations, service-layer consumers, admin request-boundary authorization, no separate analytics platform.
[RESTATE: The schema must preserve immutable, versioned snapshots and database-enforced idempotency; no later task may mutate a published snapshot.]

## DELIVERABLE
Verification — task is DONE when all pass:

Given a fresh test database, When migrations and representative model records are created, Then territory assignment, lead time, pipeline runs, metric definitions, immutable snapshots, snapshot values, and unique recommendation-event UUIDs are queryable with the declared relations and constraints.
Given shared frontend API payload shapes, When TypeScript checks the contract and package manifest, Then all data-intelligence types compile and Leaflet dependencies are declared.
All tests PASS. Both conventional commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Reversible additive migrations and explicit indexes/unique constraints for run identity, active publication, snapshot identity, and event UUID idempotency.
- Territory and supplier lead-time fields are nullable only where the spec permits safe insufficient-data handling.
- Shared TypeScript types describe explicit insufficient-data, coverage, WAPE, map-point and idempotency responses.
- New dependencies are exactly `leaflet` and `react-leaflet` (with only package-required typings if the selected compatible versions require them); React Leaflet client integration, CSS/height, and OSM attribution docs were checked via Context7.
- Tests are written before implementation and both commits use conventional commits.

Must-not-have:
- No Python/LLM, warehouse, provider credentials, or changes to legacy AI response/authorization contracts.
- No wildcard or generic contract file.

Open question risks:
- Asia/Jakarta, OSM availability/attribution, and compatible Leaflet typings → if unresolved, report NEEDS_CONTEXT before implementation tasks proceed.

Rollback note:
- Use safe down migrations; disable new pipeline routes/schedule while retaining legacy AI and analytics endpoints.

## STOP CONDITIONS
Done when: both RED→GREEN→refactor cycles pass and commits exist.
Uncertain when: selected Leaflet version cannot compile against Next.js 16/React 18 or a migration cannot be reversed safely.
Escalate when: a proposed schema requires mutating snapshots, changing legacy AI contracts, or adding an out-of-scope platform.

---

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

---

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

---

### Task 4: Implement territory management and geographic BI [depends: T3] [test-risk]

## OBJECTIVE
Add admin territory CRUD/assignment and the 30-day geographic BI snapshot consumer endpoint. Persist territory names and outlet assignments across requests. Keep `GeographicAnalyticsService`'s pipeline stage method as the producer of the named `geographic` section, and make the new admin BI controller/service read the active immutable snapshot through `ActiveDataSnapshotReader`, returning its `snapshot_version` and `{start,end,timezone}` window metadata rather than recomputing live results. Return complete table aggregates including `Unassigned`; return map points only for valid latitude/longitude; preserve existing authorization error behavior for non-admin callers.

Files:
- Create: `apps/api/app/Http/Requests/StoreTerritoryRequest.php`
- Create: `apps/api/app/Http/Requests/UpdateTerritoryRequest.php`
- Create: `apps/api/app/Http/Requests/AssignTerritoryRequest.php`
- Create: `apps/api/app/Services/GeographicAnalyticsService.php`
- Create: `apps/api/app/Http/Controllers/TerritoryController.php`
- Create: `apps/api/app/Http/Controllers/GeographicAnalyticsController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/GeographicAnalyticsTest.php`

Steps:
1. Write failing test for: territory table and map data are generated from the active snapshot
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: feature
   Test intent: Given the pipeline has published an active `geographic` section containing two assigned North outlets with three eligible in-window orders totaling 300.00 and one assigned South outlet with one eligible in-window order totaling 120.00, all with valid coordinates, When an admin requests geographic BI, Then the response contains North `{sales: 300.00, orders: 3, outlets: 2}`, South `{sales: 120.00, orders: 1, outlets: 1}`, one map point per valid outlet with its territory and aggregate values, `snapshot_version` equal to the active snapshot version, and the exact active window metadata; the endpoint does not recompute from changed live source rows or expose staging/failed output.
   Exercise through: the admin geographic BI HTTP endpoint after publishing the real snapshot via `DataPipelineService`; do not seed only live source rows and bypass publication.
   Test doubles: mock none; use real Eloquent orders/outlets/territories; do not mock the service/controller.
   Expected RED: routes/service/territory aggregation do not exist.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='territory_table_and_map_data_are_generated_from_the_active_snapshot'`
   Expected failure: route/reader contract is missing, active section/version/window fields are absent, or live/staging data is exposed.
3. Implement minimal code in the listed request/controller/service/route files: keep territory CRUD/assignment writes explicit, implement the `GeographicAnalyticsService` stage method that produces and registers the named `geographic` snapshot section, inject `ActiveDataSnapshotReader` into the geographic BI consumer, and make the new admin endpoint read that active section and return its `snapshot_version` plus `{start,end,timezone}` metadata. Apply valid-coordinate filtering to snapshot map points only; do not run live aggregate queries in the endpoint.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='territory_table_and_map_data_are_generated_from_the_active_snapshot'`
   Expected: PASS; table/map values, active `snapshot_version`, and exact window metadata are returned from the published section.
5. Refactor while green (bounded): keep stage production in `GeographicAnalyticsService` and active-snapshot reading/serialization at the consumer boundary; extract only a named geographic coordinate validator if reused three times; run `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='territory_table_and_map_data_are_generated_from_the_active_snapshot'` and require `Expected: PASS`.
6. Commit: `git add apps/api/app/Http/Requests/StoreTerritoryRequest.php apps/api/app/Http/Requests/UpdateTerritoryRequest.php apps/api/app/Http/Requests/AssignTerritoryRequest.php apps/api/app/Services/GeographicAnalyticsService.php apps/api/app/Http/Controllers/TerritoryController.php apps/api/app/Http/Controllers/GeographicAnalyticsController.php apps/api/routes/api.php apps/api/tests/Feature/GeographicAnalyticsTest.php && git commit -m "feat(analytics): add territory geographic BI"`

7. Write failing test for: missing assignment is visible but not plotted without coordinates
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: feature
   Test intent: Given the active `geographic` snapshot includes an outlet with no territory and an outlet with invalid/missing coordinates, When an admin requests geographic BI, Then the outlet with eligible data contributes to `Unassigned` table data, neither invalid-coordinate record is included in map points, and the response retains the active `snapshot_version` and window metadata.
   Exercise through: geographic BI HTTP endpoint with sparse/invalid geo fixtures published into the active snapshot; do not read live source rows from the controller.
   Test doubles: mock none; use real database records; do not mock the service under test.
   Expected RED: table/map filtering either drops the table record or plots invalid coordinates.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='missing_assignment_is_visible_but_not_plotted_without_coordinates'`
   Expected failure: wrong map-point count or missing `Unassigned` aggregate.
9. Implement minimal code: include all valid operational table records, normalize missing territory to `Unassigned`, and reject null/non-numeric/out-of-range coordinates from map points.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='missing_assignment_is_visible_but_not_plotted_without_coordinates'`
    Expected: PASS.
11. Refactor while green (bounded): keep validity rules explicit and deterministic; run `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='missing_assignment_is_visible_but_not_plotted_without_coordinates'` and require Expected: PASS.
12. Commit: `git add apps/api/app/Services/GeographicAnalyticsService.php apps/api/tests/Feature/GeographicAnalyticsTest.php && git commit -m "test(analytics): cover sparse geographic records"`

13. Write failing test for: non-admin cannot access new geographic BI or territory management
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: feature
   Test intent: Given the caller is unauthenticated or is not an admin, When the caller requests geographic BI or territory management, Then the API rejects the request with the existing authorization error contract.
   Exercise through: unauthenticated and outlet-authenticated requests to each new route.
   Test doubles: mock none; use real auth middleware; do not mock authorization/controller.
   Expected RED: new routes are absent or not admin protected.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='non_admin_cannot_access_new_geographic_bi_or_territory_management'`
   Expected failure: route/middleware status differs from existing 401/403 contract.
15. Implement minimal code: enforce admin request-boundary authorization on every territory/geographic route and preserve existing JSON status/message semantics.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='non_admin_cannot_access_new_geographic_bi_or_territory_management'`
    Expected: PASS.
17. Refactor while green (bounded): keep authorization checks at controller boundary, not in frontend or query internals; run `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='non_admin_cannot_access_new_geographic_bi_or_territory_management'` and require Expected: PASS.
18. Commit: `git add apps/api/app/Http/Controllers/TerritoryController.php apps/api/app/Http/Controllers/GeographicAnalyticsController.php apps/api/routes/api.php apps/api/tests/Feature/GeographicAnalyticsTest.php && git commit -m "fix(analytics): restrict geographic BI to admins"`

19. Write failing test for: admin can create update and assign territory persistently
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: feature
   Test intent: Given an authenticated admin and an outlet fixture with id `42`, When the admin POSTs `/api/admin/territories` with `{name: "North"}`, PATCHes the returned territory with `{name: "North Metro"}`, and POSTs `/api/admin/territories/{territory}/assign` with `{outlet_id: 42}`, Then each CRUD/assignment response succeeds and a fresh database query, performed before publication, confirms the renamed territory and `outlet.territory_id` persist; when the public pipeline publication is then invoked and BI is requested, the outlet appears under `North Metro` in the newly published snapshot version; unauthenticated and non-admin callers are not allowed to perform these writes.
   Exercise through: real admin HTTP create/update/assignment routes and Eloquent reloads first, then the public `DataPipelineService` pipeline publication entry point, then the public geographic BI route; do not call private methods, bypass publication, or mutate the database directly to simulate controller behavior.
   Test doubles: mock none; use real auth middleware, database, requests, models, `DataPipelineService` publication, and `ActiveDataSnapshotReader`; do not mock controllers, requests, persistence, the publisher, or the reader.
   Expected RED: territory CRUD/assignment routes or persistence are missing, or the test cannot prove the renamed CRUD state is published before BI reads it.
20. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='admin_can_create_update_and_assign_territory_persistently'`
   Expected failure: route not found, validation/authorization failure for admin, reloaded territory/outlet does not contain the updated name/assignment before publication, or BI does not report `North Metro` with the newly published `snapshot_version`.
21. Implement minimal code: implement `StoreTerritoryRequest`, `UpdateTerritoryRequest`, `AssignTerritoryRequest`, and `TerritoryController` create/update/assign actions with admin request-boundary authorization and durable Eloquent writes; register the exact routes exercised by the test; make the test invoke the public `DataPipelineService` publication entry point after CRUD persistence and make the BI consumer read the resulting immutable section through `ActiveDataSnapshotReader`.
22. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='admin_can_create_update_and_assign_territory_persistently'`
   Expected PASS: CRUD responses and pre-publication reloads prove durable rename/assignment, the explicit pipeline publication completes, and BI reports `North Metro` with the new active `snapshot_version`; unauthorized writes remain rejected.
23. Refactor while green (bounded): keep validation, authorization, persistence, publication invocation, and active-snapshot reading explicit at their request/service boundaries; run `cd apps/api && php artisan test tests/Feature/GeographicAnalyticsTest.php --filter='admin_can_create_update_and_assign_territory_persistently'` and require `Expected: PASS`.
24. Commit: `git add apps/api/app/Http/Requests/StoreTerritoryRequest.php apps/api/app/Http/Requests/UpdateTerritoryRequest.php apps/api/app/Http/Requests/AssignTerritoryRequest.php apps/api/app/Http/Controllers/TerritoryController.php apps/api/routes/api.php apps/api/tests/Feature/GeographicAnalyticsTest.php && git commit -m "feat(analytics): add persistent territory management"`

## REFERENCES LOADED
- Spec Geographic BI GWT scenarios and 30-day/invalid-coordinate rules.
- `apps/api/app/Models/Outlet.php`, `SalesVisit.php`, `AnalyticsService.php` — location, relation and aggregate conventions for the pipeline stage producer.
- `apps/api/app/Services/ActiveDataSnapshotReader.php` — T2 active immutable snapshot/version/window read contract.
- `apps/api/app/Http/Controllers/AnalyticsController.php`, `OutletController.php` — admin boundary and request validation.
- `apps/api/tests/Feature/AnalyticsTest.php`, `OutletTest.php` — API assertion conventions.
- `apps/api/app/Http/Requests/StoreTerritoryRequest.php`, `UpdateTerritoryRequest.php`, `AssignTerritoryRequest.php`, `TerritoryController.php` — persistent admin CRUD/assignment boundary and validation.

## WHY THIS APPROACH
Territory CRUD and BI share one explicit assignment model but separate request/controller boundaries from aggregation logic. Complexity: standard, with test-risk due to query completeness and invalid geospatial filtering.

## SANDWICH CONTEXT
[CRITICAL: Geographic territory is an explicit admin assignment; never infer territory silently from city/district or visits, and never expose the new routes to non-admin users.]
You are implementing territory and geographic BI for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: Option A snapshot architecture; geographic results are deterministic staged metrics and admin-readable API output.
Files in scope: only files listed in this task.
Test framework: Laravel PHPUnit feature tests against the real database.
Available after: T3 scheduler/trigger boundary.
Architecture rule: service-layer aggregation, request validation, admin authorization at request boundary.
[RESTATE: Geographic territory is an explicit admin assignment; never infer territory silently from city/district or visits, and never expose the new routes to non-admin users.]

## DELIVERABLE
Given an authenticated admin creates, renames, and assigns a territory, When the records are reloaded and BI is requested, Then the territory name and outlet assignment persist and appear in BI.
Given the pipeline publishes assigned-territory aggregates and valid coordinates, When admin requests BI, Then North `{sales: 300.00, orders: 3, outlets: 2}` and South `{sales: 120.00, orders: 1, outlets: 1}` are returned for the active 30-day snapshot with valid map points, `snapshot_version`, and window metadata.
Given missing territory or invalid/missing coordinates, When admin requests BI, Then `Unassigned` table data remains visible and invalid records are not plotted.
Given unauthenticated/non-admin caller, When new BI or territory route is requested, Then existing authorization error contract is returned.
All tests PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- All valid snapshot table records included; map points require valid latitude and longitude.
- Explicit `Unassigned` behavior, 30-day default, deterministic money-safe stage output.
- New BI endpoint reads the active `geographic` snapshot section and returns `snapshot_version` plus window metadata; it never recomputes live results.
- Territory CRUD/assignment and BI are admin-only.
- RED before each implementation and conventional commits.

Must-not-have:
- No inferred territories, map provider other than Leaflet/OSM client surface, or backend authorization delegated to frontend.

Open question risks:
- Unmaintained territory assignments will intentionally surface as `Unassigned`; do not hide them.

Rollback note:
- Disable new routes while retaining outlet/order data and last active snapshot.

## STOP CONDITIONS
Done when: all four GWT cycles pass and commits exist.
Uncertain when: source coordinate validation cannot distinguish null, non-numeric and out-of-range values safely.
Escalate when: implementation drops table rows to simplify map rendering or changes legacy analytics authorization.

---

### Task 5: Implement supplier performance BI with coverage [depends: T4] [test-risk]

## OBJECTIVE
Implement the default 30-day supplier score pipeline stage and admin endpoint with fulfillment 50%, on-time 30%, catalog quality 20%, component counts, denominator coverage and explicit `insufficient-data` status for suppliers with no observations. The stage writes the named `supplier` section; the admin endpoint reads that section through `ActiveDataSnapshotReader` and returns the active `snapshot_version` and window metadata rather than recomputing live results. A valid on-time record is an existing non-excluded order with delivery completion and a stored `due_date`; exclude orders whose status is `Cancelled`, `Canceled`, `Rejected`, or `Invalid`. A missing `due_date` or missing delivery excludes only that record from the on-time denominator, not from fulfillment or catalog observations.

Files:
- Create: `apps/api/app/Services/SupplierPerformanceService.php`
- Create: `apps/api/app/Http/Controllers/SupplierPerformanceController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/SupplierPerformanceTest.php`

Steps:
1. Write failing test for: supplier score exposes component and weighted values
   Test file: `apps/api/tests/Feature/SupplierPerformanceTest.php`
   Level: feature
   Test intent: Given four existing non-excluded supplier order lines in the 30-day window produce three fulfilled lines and one unfulfilled line, three valid on-time records have two completed before/equal to stored `due_date` and one completed after it, one additional non-excluded line has a missing `due_date` and is excluded only from on-time coverage, and five supplier catalog products have four active/eligible products, When an admin requests supplier BI from a published active snapshot, Then the response reports fulfillment `3/4 = 0.7500`, on-time `2/3 = 0.6667` with the missing-due-date record excluded, catalog `4/5 = 0.8000`, weights `0.50/0.30/0.20`, weighted score `0.7350`, each numerator/denominator plus on-time coverage count, and `snapshot_version`/window metadata equal the active snapshot.
   Exercise through: the real `SupplierPerformanceService` supplier stage, the public `DataPipelineService` publication entry point, and the admin supplier BI HTTP consumer backed by `ActiveDataSnapshotReader`; seed real supplier/product/order/delivery records and do not bypass publication or call a live-query endpoint as the producer.
   Test doubles: mock none; use real supplier/product/order/delivery records, stage execution, publication, and reader; do not mock the service, orchestrator, reader, or controller.
   Expected RED: the supplier stage, publication path, reader seam, route, or weighted component output does not exist.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_score_exposes_component_and_weighted_values'`
   Expected failure: route/stage/publication/reader is missing or the consumer does not expose the published weighted component fields.
3. Implement minimal code: add the `supplier` stage method with explicit ratio/weight calculations, persist its typed output through the orchestrator, inject `ActiveDataSnapshotReader` into the new admin consumer, and add the admin route with 30-day default metadata; define valid on-time records as existing non-excluded (`Cancelled`, `Canceled`, `Rejected`, `Invalid` excluded) orders with completed delivery and stored `due_date`, and exclude missing delivery/`due_date` only from the on-time denominator. The consumer returns active `snapshot_version` and window metadata and never recomputes live results.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_score_exposes_component_and_weighted_values'`
   Expected: PASS.
5. Refactor while green (bounded): keep each ratio denominator explicit and decimal-safe; extract a named ratio calculator only when the same logic appears three times; run `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_score_exposes_component_and_weighted_values'` and require Expected: PASS.
6. Commit: `git add apps/api/app/Services/SupplierPerformanceService.php apps/api/app/Http/Controllers/SupplierPerformanceController.php apps/api/routes/api.php apps/api/tests/Feature/SupplierPerformanceTest.php && git commit -m "feat(analytics): add supplier performance scores"`

7. Write failing test for: missing delivery data does not bias on-time score
   Test file: `apps/api/tests/Feature/SupplierPerformanceTest.php`
   Level: feature
   Test intent: Given supplier lines include one `Cancelled`, one `Canceled`, one `Rejected`, one `Invalid`, one non-excluded order with missing delivery, and one non-excluded order with missing `due_date`, When supplier BI is generated, Then excluded-status orders are absent from every eligible observation, missing delivery/`due_date` rows are excluded only from the on-time numerator and denominator, and the response reports the exact reduced on-time coverage.
   Exercise through: supplier BI HTTP endpoint with missing/null/invalid delivery fixtures.
   Test doubles: mock none; use real database rows; do not mock the ratio service.
   Expected RED: missing rows are counted as late/zero or denominator is all supplier lines.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='missing_delivery_data_does_not_bias_on_time_score'`
   Expected failure: on-time ratio/coverage assertion fails.
9. Implement minimal code: filter to existing non-excluded orders with completed delivery and stored `due_date` for the on-time denominator; compare delivery completion to `due_date`, and expose eligible, excluded, numerator and denominator coverage fields separately from the score.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='missing_delivery_data_does_not_bias_on_time_score'`
    Expected: PASS.
11. Refactor while green (bounded): preserve a separate coverage field instead of overloading score; run `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='missing_delivery_data_does_not_bias_on_time_score'` and require Expected: PASS.
12. Commit: `git add apps/api/app/Services/SupplierPerformanceService.php apps/api/tests/Feature/SupplierPerformanceTest.php && git commit -m "test(analytics): cover supplier delivery coverage"`

13. Write failing test for: supplier with no observations is insufficient
   Test file: `apps/api/tests/Feature/SupplierPerformanceTest.php`
   Level: feature
   Test intent: Given a supplier has no eligible operational observations, When supplier BI is generated, Then its status is `insufficient-data` and no misleading perfect or zero performance score is presented.
   Exercise through: admin supplier BI endpoint.
   Test doubles: mock none; use a real supplier with no eligible records; do not mock service/controller.
   Expected RED: empty ratios default to zero/perfect score or status is absent.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_with_no_observations_is_insufficient'`
   Expected failure: status/score assertion fails.
15. Implement minimal code: return explicit insufficient-data status and null/non-score values where no observation denominator exists.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_with_no_observations_is_insufficient'`
    Expected: PASS.
17. Refactor while green (bounded): keep no-observation handling centralized in the service; run `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_with_no_observations_is_insufficient'` and require Expected: PASS.
18. Commit: `git add apps/api/app/Services/SupplierPerformanceService.php apps/api/tests/Feature/SupplierPerformanceTest.php && git commit -m "fix(analytics): mark empty supplier observations insufficient"`

19. Write failing test for: supplier BI is admin-only
   Test file: `apps/api/tests/Feature/SupplierPerformanceTest.php`
   Level: feature
   Test intent: Given an unauthenticated caller and an authenticated non-admin outlet user, When each requests the supplier BI endpoint, Then each receives the existing 401/403 authorization response and no supplier score data; an authenticated admin receives the BI response.
   Exercise through: the real supplier BI HTTP route and auth middleware with unauthenticated, outlet-user, and admin requests; do not call the service directly.
   Test doubles: mock none; use real auth middleware and database records; do not mock authorization, controller, or service.
   Expected RED: the endpoint is missing or permits unauthenticated/non-admin access.
20. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_bi_is_admin_only'`
   Expected failure: route not found or 401/403 response differs from the existing authorization contract.
21. Implement minimal code: apply the existing admin request-boundary middleware/policy to the supplier BI route and leave legacy AI authorization unchanged.
22. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_bi_is_admin_only'`
   Expected: PASS.
23. Refactor while green (bounded): keep authorization exclusively at the HTTP boundary and preserve the service's deterministic formula; run `cd apps/api && php artisan test tests/Feature/SupplierPerformanceTest.php --filter='supplier_bi_is_admin_only'` and require Expected: PASS.
24. Commit: `git add apps/api/app/Http/Controllers/SupplierPerformanceController.php apps/api/routes/api.php apps/api/tests/Feature/SupplierPerformanceTest.php && git commit -m "fix(analytics): restrict supplier BI to admins"`

## REFERENCES LOADED
- Supplier performance GWT scenarios and exact 50/30/20, coverage and 30-day formulas from the spec.
- Existing order/delivery status semantics: valid on-time data requires a completed delivery and stored `due_date`; `Cancelled`, `Canceled`, `Rejected`, and `Invalid` orders are excluded, while missing delivery/`due_date` affects only on-time denominator coverage.
- `apps/api/app/Models/Supplier.php`, `Product.php`, `OrderItem.php`, `Delivery.php` — source relationships and casts.
- `apps/api/app/Services/AnalyticsService.php` — aggregate query and decimal-money conventions.
- `apps/api/tests/Feature/AnalyticsTest.php`, `DeliveryTest.php` — feature fixture/assertion patterns.

## WHY THIS APPROACH
A dedicated service keeps transparent formula components and coverage visible, rather than hiding ratios inside a controller or snapshot JSON. Complexity: standard/test-risk because denominator eligibility and no-observation semantics materially affect correctness.

## SANDWICH CONTEXT
[CRITICAL: Missing delivery/due-date data must be excluded from the on-time denominator and reported as coverage; no-observation suppliers must never receive a fabricated score.]
You are implementing supplier performance BI for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: deterministic orchestrated snapshot; endpoint reads the declared 30-day metric output.
Files in scope: only files listed in this task.
Test framework: Laravel PHPUnit feature tests with real source records.
Available after: T4 supplier/territory contracts.
Architecture rule: service-layer formula, admin-only controller, decimal-safe arithmetic and explicit coverage.
[RESTATE: Missing delivery/due-date data must be excluded from the on-time denominator and reported as coverage; no-observation suppliers must never receive a fabricated score.]

## DELIVERABLE
Given 30-day supplier observations, When admin requests BI, Then component ratios, 50/30/20 weighted score, counts and coverage are returned.
Given missing delivery/due-date records, When BI is generated, Then they are excluded from on-time numerator/denominator and reduced coverage is reported.
Given no eligible observations, When BI is generated, Then status is `insufficient-data` without misleading score.
All tests PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Fulfillment, on-time and catalog quality formulas exactly match the spec.
- Coverage identifies excluded on-time records; no-observation status is explicit.
- New endpoint is admin-only and 30-day default is visible.

Must-not-have:
- No denominator inflation, opaque rating, fake perfect/zero score, automatic retry or external analytics platform.

Open question risks:
- Existing delivery/order source status semantics must be mapped explicitly; ambiguous statuses require NEEDS_CONTEXT rather than silent inclusion.

Rollback note:
- Disable supplier BI route or snapshot stage; retain prior active snapshot.

## STOP CONDITIONS
Done when: all three scenarios pass and commits exist.
Uncertain when: delivery source records cannot establish valid due/delivery dates without inventing a status.
Escalate when: score formula or denominator differs from the spec.

---

### Task 6: Implement stock planning and replenishment [depends: T5] [test-risk]

## OBJECTIVE
Implement the `stock` pipeline stage and admin stock-planning BI over a 30-day rolling demand window. The admin endpoint reads the active `stock` section through `ActiveDataSnapshotReader` and returns its `snapshot_version` and window metadata rather than recomputing live results. Demand includes only positive order-item quantities from orders whose status is not `Cancelled`, `Canceled`, `Rejected`, or `Invalid`; use supplier lead time and the exact reorder formula, clamp negative stock to zero, mark invalid lead time `insufficient-data`, and return no reorder for zero demand/sufficient stock.

Files:
- Create: `apps/api/app/Services/StockPlanningService.php`
- Create: `apps/api/app/Http/Controllers/StockPlanningController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/StockPlanningTest.php`

Steps:
1. Write failing test for: SKU receives a reorder recommendation
   Test file: `apps/api/tests/Feature/StockPlanningTest.php`
   Level: feature
   Test intent: Given a published active `stock` section for a SKU has positive order-item quantities totaling exactly 60 units from eligible orders in the rolling 30-day window, excluded-status orders (`Cancelled`, `Canceled`, `Rejected`, `Invalid`) contribute no quantity, supplier `lead_time_days` is 5, and available stock is 3, When an admin requests stock planning, Then average daily demand is `60/30 = 2 units/day`, the lead-time demand is `2 × 5 = 10`, suggested reorder quantity is `max(0, 10 − 3) = 7`, the SKU has a stockout/reorder warning, and the response includes the active `snapshot_version` and window metadata.
   Exercise through: the real `StockPlanningService` stage, the public `DataPipelineService` publication entry point, and the admin stock-planning HTTP consumer backed by `ActiveDataSnapshotReader`; do not bypass publication or recompute live data in the endpoint.
   Test doubles: mock none; use real product/supplier/order-item records and database; do not mock the stage, publisher, reader, service or controller.
   Expected RED: route/stage/reader contract is missing, active version/window fields are absent, or the exact formula output does not exist.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sku_receives_a_reorder_recommendation'`
   Expected failure: missing route or reorder fields/formula.
3. Implement minimal code: add the `stock` stage method, sum only positive quantities on order items whose parent order status is not `Cancelled`, `Canceled`, `Rejected`, or `Invalid` within the rolling 30-day window, divide by 30, apply `max(0, average daily demand × lead_time_days − available stock)`, persist the typed stage output, inject `ActiveDataSnapshotReader` into the admin consumer, and return warning/reorder fields plus active `snapshot_version`/window metadata without live recomputation.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sku_receives_a_reorder_recommendation'`
   Expected: PASS; the real stage publishes the `stock` section and the reader-backed consumer returns active `snapshot_version`/window metadata with reorder quantity 7.
5. Refactor while green (bounded): keep demand and reorder arithmetic in the service and decimal/integer-safe; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sku_receives_a_reorder_recommendation'` and require Expected: PASS.
6. Commit: `git add apps/api/app/Services/StockPlanningService.php apps/api/app/Http/Controllers/StockPlanningController.php apps/api/routes/api.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "feat(analytics): add stock replenishment planning"`

7. Write failing test for: sufficient stock or zero demand produces no reorder
   Test file: `apps/api/tests/Feature/StockPlanningTest.php`
   Level: feature
   Test intent: Given a SKU has sufficient available stock or zero demand, When stock planning is generated, Then no reorder action is recommended.
   Exercise through: stock-planning HTTP endpoint with both fixtures.
   Test doubles: mock none; use real database records; do not mock the service.
   Expected RED: implementation emits a reorder for all SKUs or treats zero demand as shortage.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sufficient_stock_or_zero_demand_produces_no_reorder'`
   Expected failure: reorder action is present.
9. Implement minimal code: return explicit no-action status and zero/non-action quantity for sufficient stock and zero demand.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sufficient_stock_or_zero_demand_produces_no_reorder'`
    Expected: PASS.
11. Refactor while green (bounded): preserve separate demand/stock/action fields; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='sufficient_stock_or_zero_demand_produces_no_reorder'` and require Expected: PASS.
12. Commit: `git add apps/api/app/Services/StockPlanningService.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "test(analytics): cover no-action stock plans"`

13. Write failing test for: invalid stock-planning input is safe
   Test file: `apps/api/tests/Feature/StockPlanningTest.php`
   Level: feature
   Test intent: Given a SKU has negative stock or a null/negative supplier lead time, When stock planning is generated, Then negative stock is treated as zero and invalid lead time produces insufficient-data with no false reorder quantity.
   Exercise through: stock-planning HTTP endpoint with invalid stock/lead-time fixtures.
   Test doubles: mock none; use real models/database; do not mock service/controller.
   Expected RED: negative stock leaks into formula or invalid lead time creates a false reorder.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='invalid_stock_planning_input_is_safe'`
   Expected failure: negative or non-null reorder quantity assertion fails.
15. Implement minimal code: clamp stock at zero, validate lead time > 0, and return insufficient-data/no recommendation for invalid lead time.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='invalid_stock_planning_input_is_safe'`
    Expected: PASS.
17. Refactor while green (bounded): centralize input normalization in `StockPlanningService` and keep null/negative semantics explicit; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='invalid_stock_planning_input_is_safe'` and require Expected: PASS.
18. Commit: `git add apps/api/app/Services/StockPlanningService.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "fix(analytics): guard invalid stock planning inputs"`

19. Write failing test for: stock-planning endpoint is admin-only
    Test name: `stock_planning_endpoint_is_admin_only`
    Test file: `apps/api/tests/Feature/StockPlanningTest.php`
    Level: feature
    Test intent: Given an unauthenticated caller, an authenticated non-admin outlet user, and an authenticated admin, When each requests the stock-planning endpoint, Then unauthenticated access returns HTTP 401, non-admin access returns HTTP 403 with the existing JSON authorization contract, and the admin receives stock-planning data; neither unauthorized request may expose stock fields.
    Exercise through: the real stock-planning HTTP route and auth middleware; do not call `StockPlanningService` directly.
    Test doubles: mock none; use real auth middleware, users, products, suppliers and database records; do not mock authorization, controller or service.
    Expected RED: the route is absent, lacks admin protection, or unauthorized responses/data differ from the existing contract.
20. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='stock_planning_endpoint_is_admin_only'`
    Expected failure: route not found, non-admin/unauthenticated status is not 401/403, or unauthorized response contains stock-planning data.
21. Implement minimal code: apply the existing admin request-boundary middleware/policy to the stock-planning route and preserve the existing 401/403 JSON authorization contract; do not change legacy AI authorization.
22. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='stock_planning_endpoint_is_admin_only'`
    Expected: PASS; unauthenticated is 401, non-admin is 403, admin succeeds, and unauthorized responses contain no stock data.
23. Refactor while green (bounded): keep authorization at the HTTP/controller boundary and leave demand/formula semantics in `StockPlanningService`; run `cd apps/api && php artisan test tests/Feature/StockPlanningTest.php --filter='stock_planning_endpoint_is_admin_only'` and require `Expected: PASS`.
24. Commit: `git add apps/api/app/Http/Controllers/StockPlanningController.php apps/api/routes/api.php apps/api/tests/Feature/StockPlanningTest.php && git commit -m "fix(analytics): restrict stock planning to admins"`

## REFERENCES LOADED
- Stock planning GWT scenarios and exact formula/invalid-input rules from the spec.
- Demand eligibility: only positive order-item quantities from non-`Cancelled`/`Canceled`/`Rejected`/`Invalid` orders inside the rolling 30-day window; fixture contract `60 units / 30 days = 2/day`, `lead_time_days = 5`, `stock = 3`, expected reorder `7`.
- `apps/api/app/Models/Product.php`, `Supplier.php`, `OrderItem.php`, `Order.php` — stock, supplier and demand source conventions.
- `apps/api/app/Services/AnalyticsService.php` — rolling aggregate and money-safe arithmetic patterns.
- `apps/api/tests/Feature/ProductTest.php`, `OrderTest.php` — product/order fixture conventions.

## WHY THIS APPROACH
The stock service is isolated from supplier scoring so formula and insufficient-data behavior can be verified without opaque coupling. Complexity: standard/test-risk due to rolling demand, invalid numeric inputs and action thresholds.

## SANDWICH CONTEXT
[CRITICAL: Reorder quantity is exactly `max(0, average daily demand × supplier lead_time_days − available stock)`; null/negative lead time must never produce a recommendation.]
You are implementing stock planning for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: deterministic orchestrated snapshot with admin-readable BI output.
Files in scope: only files listed in this task.
Test framework: Laravel PHPUnit feature tests against real demand/product/supplier records.
Available after: T5 supplier contracts.
Architecture rule: 30-day window, decimal/integer-safe arithmetic, admin authorization at controller boundary.
[RESTATE: Reorder quantity is exactly `max(0, average daily demand × supplier lead_time_days − available stock)`; null/negative lead time must never produce a recommendation.]

## DELIVERABLE
Given positive demand, valid lead time and insufficient stock, When planning runs, Then warning and non-negative formula quantity are returned.
Given sufficient stock or zero demand, When planning runs, Then no reorder action is returned.
Given negative stock or invalid lead time, When planning runs, Then stock is clamped and invalid lead time is insufficient-data with no false quantity.
All four scenario cycles PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Exact 30-day demand and reorder formula.
- Negative stock clamp; null/negative lead time insufficient-data.
- Zero-demand/sufficient-stock no-action behavior.
- Admin-only endpoint and TDD order.

Must-not-have:
- No negative reorder quantity, fabricated lead-time fallback, automatic reorder execution, or changes to product ordering behavior.

Open question risks:
- If the source order-item status eligibility is ambiguous, stop for context rather than counting cancelled/invalid demand.

Rollback note:
- Disable stock-planning route/stage and retain last successful snapshot.

## STOP CONDITIONS
Done when: all four scenario cycles pass, including unauthenticated/non-admin 401/403 assertions, and commits exist.
Uncertain when: demand source status eligibility cannot be determined from existing semantics.
Escalate when: implementation sends orders or silently defaults invalid lead time.

---

### Task 7: Add sparse AI fallback and recommendation/forecast measurement [depends: T6] [test-risk]

## OBJECTIVE
Update recommendation/forecast heuristics to the 30-day sparse-data contract and add the `measurement` pipeline stage plus admin-only funnel event ingestion/measurement with UUID idempotency and WAPE measurement. The new admin measurement endpoint reads the active `measurement` section through `ActiveDataSnapshotReader` and returns its `snapshot_version` and window metadata. Define purchased attribution exactly as: match the recommendation event `product_id` and `outlet_id` to a successful, non-excluded `OrderItem`/parent `Order` in the 30-day window; no campaign attribution is used. Preserve legacy AI endpoint authorization and response shapes while exposing measurement metadata through new endpoints.

Files:
- Modify: `apps/api/app/Services/RecommendationService.php`
- Modify: `apps/api/app/Services/ForecastService.php`
- Create: `apps/api/app/Services/MeasurementService.php`
- Create: `apps/api/app/Http/Controllers/MeasurementController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/MeasurementTest.php`
- Test: `apps/api/tests/Feature/AITest.php` (existing compatibility suite; read/run only, do not change)

Steps:
1. Write failing test for: recent-average fallback is returned for sparse history
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given an outlet or product has fewer than 30 days of history but recent positive observations, When recommendation or forecast output is generated, Then the system returns the recent-average heuristic fallback and marks the result low-confidence/limited-data.
   Exercise through: existing `/api/ai/recommendations` and `/api/ai/forecast` public legacy routes.
   Test doubles: mock none; use real orders/items and freeze the clock; do not mock legacy controllers/services under test.
   Expected RED: current services use a three-point threshold and do not guarantee 30-day limited-data metadata.
2. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='recent_average_fallback_is_returned_for_sparse_history'`
   Expected failure: fallback/limited-data assertion fails.
3. Implement minimal code: add recent-average fallback and explicit limited-data metadata while preserving existing legacy response keys and authorization.
4. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='recent_average_fallback_is_returned_for_sparse_history'`
   Expected: PASS.
5. Refactor while green (bounded): reuse existing deterministic methods and extract only a domain-scoped rolling-window helper when repeated three times; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='recent_average_fallback_is_returned_for_sparse_history'` and require Expected: PASS.
6. Commit: `git add apps/api/app/Services/RecommendationService.php apps/api/app/Services/ForecastService.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "feat(ai): add 30-day sparse fallback metadata"`

7. Write failing test for: empty history returns safe output
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given an outlet or product has no eligible history, When the pipeline generates AI-derived metrics, Then it returns empty or zero output with insufficient-data metadata and does not expose a fabricated perfect score or forecast.
   Exercise through: legacy AI endpoints and new measurement response where applicable.
   Test doubles: mock none; use an empty real test database; do not mock the services/controllers.
   Expected RED: empty output lacks explicit insufficient-data semantics or reports a perfect result.
8. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='empty_history_returns_safe_output'`
   Expected failure: insufficient-data/false-perfect assertion fails.
9. Implement minimal code: return safe empty/zero values and insufficient-data metadata without changing the legacy response envelope.
10. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='empty_history_returns_safe_output'`
    Expected: PASS.
11. Refactor while green (bounded): keep fallback metadata explainable and deterministic; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='empty_history_returns_safe_output'` and require Expected: PASS.
12. Commit: `git add apps/api/app/Services/RecommendationService.php apps/api/app/Services/ForecastService.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "test(ai): cover empty sparse output"`

13. Write failing test for: recommendation funnel is measured
   Test name: `recommendation_funnel_is_measured`
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given recommendation events and successful order data exist for the 30-day measurement window and the active published snapshot contains the `measurement` section, When an admin requests recommendation measurement, Then the response returns displayed, clicked, cart and purchased counts in funnel order, safe conversion rates, attribution metadata, and active `snapshot_version`/window metadata. A purchased event is counted only when the recommendation event `product_id` and `outlet_id` match a successful, non-excluded `OrderItem`/parent `Order` in that 30-day window; no campaign attribution is counted or inferred.
   Exercise through: the real `MeasurementService` measurement stage, the public `DataPipelineService` publication entry point, and the admin measurement HTTP consumer backed by `ActiveDataSnapshotReader`; seed real event/order/order-item records, including matching and non-matching product/outlet pairs and excluded orders, and do not bypass publication.
   Test doubles: mock none; use real database records; do not mock `MeasurementService`.
   Expected RED: event model/service/controller/route do not exist, or purchased attribution/funnel fields are absent.
14. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='recommendation_funnel_is_measured'`
   Expected failure: route/stage/reader contract is missing, funnel output lacks active version/window metadata, or purchased count does not enforce the exact product/outlet/order-window match.
15. Implement minimal code: add `MeasurementService`, controller and admin routes for event ingestion and funnel reporting; register the `measurement` stage with the orchestrator, make the new measurement consumer read it through `ActiveDataSnapshotReader` and return active version/window metadata; count purchased only for a successful, non-excluded `OrderItem`/parent `Order` whose `product_id` and `outlet_id` match the recommendation event within 30 days; do not add campaign attribution; make zero denominators safe.
16. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='recommendation_funnel_is_measured'`
   Expected: PASS; the real stage publishes the measurement section, the reader-backed consumer returns the active version/window, and displayed→clicked→cart→purchased ordering, safe rates, exact matching attribution, excluded-order exclusion, 30-day boundary and no-campaign attribution assertions all pass.
17. Refactor while green (bounded): keep the exact purchased-match predicate and rate calculation in `MeasurementService`; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='recommendation_funnel_is_measured'` and require `Expected: PASS`.
18. Commit: `git add apps/api/app/Services/MeasurementService.php apps/api/app/Http/Controllers/MeasurementController.php apps/api/routes/api.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "feat(analytics): measure recommendation funnel"`

19. Write failing test for: duplicate event retry is idempotent
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: integration
   Test intent: Given an event UUID has already been recorded, When the same event UUID is submitted again, Then the API returns an idempotent success/replay response and funnel count remains unchanged; conflicting reuse is rejected.
   Exercise through: event-ingestion HTTP endpoint and database unique/idempotency boundary.
   Test doubles: mock none; use real database constraint/transaction; do not mock the event model or controller.
   Expected RED: duplicate requests increment counts or conflicting payload is accepted.
20. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='duplicate_event_retry_is_idempotent'`
   Expected failure: count changes or conflict status assertion fails.
21. Implement minimal code: use transaction/unique UUID lookup, replay identical payloads without incrementing, and reject conflicting UUID reuse explicitly.
22. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='duplicate_event_retry_is_idempotent'`
    Expected: PASS.
23. Refactor while green (bounded): retain the database uniqueness guard as source of truth; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='duplicate_event_retry_is_idempotent'` and require Expected: PASS.
24. Commit: `git add apps/api/app/Services/MeasurementService.php apps/api/app/Http/Controllers/MeasurementController.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "fix(analytics): make recommendation events idempotent"`

25. Write failing test for: forecast WAPE and strict target boundary are calculated
   Test name: `forecast_wape_and_strict_target_boundary_are_calculated`
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given 30 forecast/actual periods with positive actual demand, one fixture has absolute errors totaling 20% of actual demand and another has errors totaling exactly 30%, When an admin requests forecast measurement through the active measurement snapshot, Then the first response returns WAPE `0.2000`, accuracy 80%, and target achieved, while the boundary response returns WAPE `0.3000`, accuracy 70%, and target not achieved because the requirement is strictly above 70%.
   Exercise through: the real measurement stage, public `DataPipelineService` publication, `ActiveDataSnapshotReader`, and admin forecast-measurement HTTP endpoint; do not calculate only in a unit helper or bypass publication.
   Test doubles: mock none; use deterministic real forecast/actual fixtures and freeze the clock; do not mock the WAPE calculator, publisher, reader, or controller.
   Expected RED: no WAPE endpoint/calculation, active-snapshot measurement contract, exact numeric output, or strict boundary status exists.
26. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='forecast_wape_and_strict_target_boundary_are_calculated'`
   Expected failure: route/stage/reader/target status is missing, numeric WAPE differs from `0.2000`/`0.3000`, or exactly 30% incorrectly passes.
27. Implement minimal code: calculate decimal-safe WAPE from the published measurement section, expose actual coverage and strict `< 30%` WAPE target status, and return active snapshot version/window metadata.
28. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='forecast_wape_and_strict_target_boundary_are_calculated'`
    Expected: PASS; WAPE `0.2000` passes, WAPE `0.3000` does not pass, and active snapshot metadata is returned.
29. Refactor while green (bounded): isolate WAPE calculation in a named measurement method and keep the boundary comparison explicit; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='forecast_wape_and_strict_target_boundary_are_calculated'` and require Expected: PASS.
30. Commit: `git add apps/api/app/Services/MeasurementService.php apps/api/app/Http/Controllers/MeasurementController.php apps/api/routes/api.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "feat(analytics): add forecast WAPE measurement"`

31. Write failing test for: all-zero or insufficient actuals remain pending
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given actual sales are all zero or fewer than 30 days are available, When forecast measurement is generated, Then WAPE is not treated as a perfect score and result is marked pending/insufficient-data.
   Exercise through: admin forecast-measurement endpoint.
   Test doubles: mock none; use real actual fixtures; do not mock service/controller.
   Expected RED: division-by-zero becomes zero WAPE/perfect status or short history passes.
32. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='all_zero_or_insufficient_actuals_remain_pending'`
   Expected failure: pending/insufficient assertion fails.
33. Implement minimal code: make all-zero denominator pending and require at least 30 actual days before accuracy status.
34. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='all_zero_or_insufficient_actuals_remain_pending'`
    Expected: PASS.
35. Refactor while green (bounded): keep pending distinct from numeric WAPE; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='all_zero_or_insufficient_actuals_remain_pending'` and require Expected: PASS.
36. Commit: `git add apps/api/app/Services/MeasurementService.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "fix(analytics): keep insufficient WAPE pending"`

37. Write failing test for: legacy AI access remains compatible
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given an authorized outlet user requests its existing recommendation or forecast endpoint, When the request is processed, Then the existing outlet-scoped contract remains available and new pipeline/BI snapshot endpoints remain admin-only.
   Exercise through: existing `/api/ai/recommendations`, `/api/ai/forecast`, and each new admin-only measurement route for event ingestion, funnel reporting, and forecast measurement.
   Test doubles: mock none; use real auth and existing AITest fixtures; do not mock `AIController` or legacy services.
   Expected RED: new work may alter response keys/authorization or expose any new measurement endpoint or snapshot data to an outlet.
38. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='legacy_ai_access_remains_compatible'`
   Expected failure: compatibility or admin-only assertion fails.
39. Implement minimal code: preserve `AIController` routes/authorization/response envelope, enforce admin authorization on all new measurement routes, and run the existing compatibility suite without modifying it.
40. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='legacy_ai_access_remains_compatible' && php artisan test tests/Feature/AITest.php`
    Expected: both PASS.
41. Refactor while green (bounded): remove any accidental legacy contract changes; re-run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='legacy_ai_access_remains_compatible' && php artisan test tests/Feature/AITest.php` and keep PASS.
42. Commit: `git add apps/api/app/Services/RecommendationService.php apps/api/app/Services/ForecastService.php apps/api/app/Services/MeasurementService.php apps/api/app/Http/Controllers/MeasurementController.php apps/api/routes/api.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "test(ai): preserve legacy access during measurement"`

43. Write failing test for: measurement event ingestion is admin-only
   Test name: `measurement_event_ingestion_is_admin_only`
   Test file: `apps/api/tests/Feature/MeasurementTest.php`
   Level: feature
   Test intent: Given an unauthenticated caller, an authenticated non-admin outlet user, and an authenticated admin, When each submits a displayed/clicked/cart event to the measurement event-ingestion endpoint, Then unauthenticated access returns HTTP 401, non-admin access returns HTTP 403 with the existing JSON authorization contract and persists no event, and the admin request succeeds with exactly one event persisted.
   Exercise through: the real event-ingestion HTTP route and auth middleware; do not call `MeasurementService` directly.
   Test doubles: mock none; use real auth, validation, database and controller; do not mock authorization, controller, service or persistence.
   Expected RED: event-ingestion route is absent, permits non-admin access, or unauthorized requests persist/expose an event.
44. Run test — verify FAIL: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='measurement_event_ingestion_is_admin_only'`
   Expected failure: route not found or unauthorized request does not produce 401/403 with no persisted event.
45. Implement minimal code: enforce admin authorization at the event-ingestion request boundary while preserving the existing legacy AI access rules; accept the validated event only for admins and persist it through the idempotent service.
46. Run test — verify PASS: `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='measurement_event_ingestion_is_admin_only'`
   Expected: PASS; unauthenticated is 401, non-admin is 403 with no persisted event, and admin succeeds.
47. Refactor while green (bounded): keep the authorization check at the controller/route boundary and event persistence in `MeasurementService`; run `cd apps/api && php artisan test tests/Feature/MeasurementTest.php --filter='measurement_event_ingestion_is_admin_only'` and require `Expected: PASS`.
48. Commit: `git add apps/api/app/Http/Controllers/MeasurementController.php apps/api/routes/api.php apps/api/tests/Feature/MeasurementTest.php && git commit -m "fix(analytics): restrict measurement ingestion to admins"`

## REFERENCES LOADED
- Spec sparse-data, funnel, idempotency, WAPE and legacy compatibility GWT scenarios.
- `apps/api/app/Services/RecommendationService.php`, `ForecastService.php`, `AIController.php` — existing legacy behavior that must remain compatible.
- `apps/api/app/Models/Order.php`, `OrderItem.php`, `RecommendationEvent.php` (T1 contract) — event/order sources.
- `apps/api/tests/Feature/AITest.php` — existing 14-test AI compatibility convention; run unchanged.
- `apps/api/tests/Feature/PaymentConcurrencyTest.php` — idempotency/transaction test patterns.

## WHY THIS APPROACH
Sparse fallback and measurement share deterministic AI semantics but remain separated behind new measurement routes; this task explicitly tests the legacy boundary before allowing later frontend consumption. Complexity: deep, due to compatibility, idempotency, network boundary and WAPE edge cases.

## SANDWICH CONTEXT
[CRITICAL: Existing `/api/ai/recommendations`, `/api/ai/forecast` and segmentation authorization/response behavior must remain compatible; only new measurement/pipeline endpoints are admin-only.]
You are implementing sparse AI fallback and measurement for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: deterministic heuristics with immutable snapshot publication; measurement is evidence, not a new model.
Files in scope: only files listed in this task.
Test framework: Laravel PHPUnit feature/integration tests; real DB for UUID idempotency and WAPE fixtures.
Available after: T6 stock contracts.
Architecture rule: no Python/LLM, no automatic retry, strict WAPE `<30%`, minimum 30 days, all-zero pending.
[RESTATE: Existing `/api/ai/recommendations`, `/api/ai/forecast` and segmentation authorization/response behavior must remain compatible; only new measurement/pipeline endpoints are admin-only.]

## DELIVERABLE
Given fewer than 30 days with recent positive observations, When AI output is generated, Then recent-average fallback and limited/low-confidence metadata are returned.
Given no eligible history, When AI output is generated, Then safe empty/zero insufficient-data output is returned.
Given events/orders, When admin requests funnel, Then displayed→clicked→cart→purchased counts, safe rates and attribution are returned.
Given repeated/conflicting UUID, When submitted, Then identical replay is idempotent and conflicting reuse is rejected.
Given 30 positive actual days, When forecast measurement runs, Then WAPE `0.2000` passes as 80% accuracy while exactly WAPE `0.3000` (70% accuracy) does not meet the strict target.
Given all-zero or short actual history, When measurement runs, Then status is pending/insufficient, never a false perfect score.
Given authorized outlet legacy requests, When processed, Then old contracts remain available and new endpoints remain admin-only.
All tests PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- 30-day baseline/fallback metadata, safe empty output, UUID idempotency and conflict handling.
- WAPE formula and strict target semantics; all-zero/short history pending.
- Existing AITest remains green without source-contract changes.
- New measurement routes, including event ingestion, are admin-only and frontend event contract supports UUID.

Must-not-have:
- No Python/LLM, fabricated perfect scores, automatic retries, legacy route/response/auth changes, or full campaign/cross-selling workflow.

Open question risks:
- Successful-order attribution fields must be mapped to existing order semantics; do not invent campaign attribution.

Rollback note:
- Disable measurement routes/stages while retaining immutable history; legacy AI remains independently available.

## STOP CONDITIONS
Done when: all eight scenario cycles pass, including unchanged AITest and measurement-ingestion authorization, and commits exist.
Uncertain when: successful purchase attribution cannot be identified from existing order/item data.
Escalate when: any legacy response key/authorization changes or WAPE all-zero is coerced to a pass.

---

### Task 8: Build Next.js admin data-intelligence surfaces and Leaflet map [depends: T7] [test-risk]

## OBJECTIVE
Build the admin-only Next.js data-intelligence page, typed API client and table/map/measurement components. `GeoMap.tsx` is a client component whose file begins with `'use client'`; the page dynamically imports `GeoMap` with `{ ssr: false }` because `react-leaflet` uses browser globals. Render tables independently before and regardless of map success, with required CSS/container height and OpenStreetMap attribution. Send frontend funnel events with UUID idempotency keys.

Files:
- Create: `apps/web/src/lib/data-intelligence-api.ts`
- Create: `apps/web/src/app/data-intelligence/page.tsx`
- Create: `apps/web/src/components/data-intelligence/GeoMap.tsx`
- Create: `apps/web/src/components/data-intelligence/TerritoryTable.tsx`
- Create: `apps/web/src/components/data-intelligence/SupplierPerformanceTable.tsx`
- Create: `apps/web/src/components/data-intelligence/StockPlanningTable.tsx`
- Create: `apps/web/src/components/data-intelligence/MeasurementCards.tsx`
- Modify: `apps/web/src/components/Sidebar.tsx`
- Test: `apps/web/src/app/data-intelligence/page.test.tsx`
- Test: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`

Steps:
1. Write failing test for: admin page consumes shared API contract and retains table fallback
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: unit
   Test intent: Given an authenticated admin and typed geographic/supplier/stock/measurement responses, When the data-intelligence page loads, Then it renders territory table data, supplier coverage/status, stock actions, WAPE/pending status and handles an API error without hiding the table fallback.
   Exercise through: page component and typed API client boundary.
   Test doubles: mock `fetch`/API responses and local storage; do not mock the page components under test.
   Expected RED: page/API client/components do not exist.
2. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'admin page consumes shared API contract'`
   Expected failure: module not found or missing rendered contract fields.
3. Implement minimal code: add typed API functions, admin session handling, page composition and table components using the shared types.
4. Run test — verify PASS: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'admin page consumes shared API contract'`
   Expected: PASS.
5. Refactor while green (bounded): keep API parsing in `data-intelligence-api.ts`, split components only at existing UI boundaries, and run `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'admin page consumes shared API contract'`; Expected: PASS.
6. Commit: `git add apps/web/src/lib/data-intelligence-api.ts apps/web/src/app/data-intelligence/page.tsx apps/web/src/components/data-intelligence/TerritoryTable.tsx apps/web/src/components/data-intelligence/SupplierPerformanceTable.tsx apps/web/src/components/data-intelligence/StockPlanningTable.tsx apps/web/src/components/data-intelligence/MeasurementCards.tsx apps/web/src/app/data-intelligence/page.test.tsx && git commit -m "feat(web): add data intelligence admin page"`

7. Write failing test for: Leaflet map renders client-only with attribution and stable height
   Test name: `leaflet_map_renders_client_only_with_attribution_and_stable_height`
   Test file: `apps/web/src/components/data-intelligence/GeoMap.test.tsx`
   Level: unit
   Test intent: Given valid and invalid map points, When the geographic component renders in the browser, Then only valid points are rendered client-side, the map container has explicit non-zero height, the sibling tables remain usable when map rendering is unavailable, and OpenStreetMap attribution is present. The test also asserts `GeoMap.tsx` begins with `'use client'` and the page's dynamic import sets `ssr: false`.
   Exercise through: `GeoMap` component boundary and page dynamic-import boundary; test SSR import/render behavior and browser rendering separately.
   Test doubles: mock `react-leaflet` map primitives and tile network; do not mock `GeoMap` itself or sibling table components.
   Expected RED: no client-only boundary, `ssr: false` dynamic import, CSS height, coordinate filter or attribution exists.
8. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand src/components/data-intelligence/GeoMap.test.tsx -t 'leaflet_map_renders_client_only_with_attribution_and_stable_height'`
   Expected failure: module not found, SSR `window` error, missing `ssr: false`, no height, invalid-point, sibling-table or attribution assertion.
9. Implement minimal code: make `apps/web/src/components/data-intelligence/GeoMap.tsx` begin with exactly `'use client'`; in `apps/web/src/app/data-intelligence/page.tsx`, dynamically import `GeoMap` with exactly `{ ssr: false }` because `react-leaflet` uses browser globals; import Leaflet CSS, set an explicit non-zero map container height, filter null/non-numeric/out-of-range coordinates, configure the OSM tile URL with visible attribution, and render TerritoryTable, SupplierPerformanceTable, StockPlanningTable and MeasurementCards outside the map conditional so they render before the map and remain rendered when a map error/unavailable state occurs.
10. Run test — verify PASS: `cd apps/web && npm test -- --runInBand src/components/data-intelligence/GeoMap.test.tsx -t 'leaflet_map_renders_client_only_with_attribution_and_stable_height'`
    Expected: PASS; client-only assertions, `ssr: false`, valid-point filtering, non-zero height, OSM attribution and independent-table fallback assertions all pass.
11. Refactor while green (bounded): keep browser-global Leaflet code isolated to `GeoMap.tsx` and keep tables outside map error handling; run `cd apps/web && npm test -- --runInBand src/components/data-intelligence/GeoMap.test.tsx -t 'leaflet_map_renders_client_only_with_attribution_and_stable_height'` and require `Expected: PASS`; then run `cd apps/web && npx tsc --noEmit --pretty false` and require `Expected: PASS`.
12. Commit: `git add apps/web/src/components/data-intelligence/GeoMap.tsx apps/web/src/components/data-intelligence/GeoMap.test.tsx apps/web/src/app/data-intelligence/page.tsx && git commit -m "feat(web): render client-only OSM map"`

13. Write failing test for: frontend funnel event carries a UUID and replay-safe request
   Test file: `apps/web/src/app/data-intelligence/page.test.tsx`
   Level: integration
   Test intent: Given an admin views recommendation results, When displayed/clicked/cart events are sent, Then the API client sends the event type, recommendation context and frontend UUID idempotency key to the admin measurement endpoint and does not generate a new key for the same retry.
   Exercise through: public `data-intelligence-api.ts` event method and fetch boundary.
   Test doubles: mock network `fetch` and UUID generator; do not mock the API client under test.
   Expected RED: event API method and stable UUID handling do not exist.
14. Run test — verify FAIL: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'frontend funnel event carries a UUID'`
   Expected failure: missing method or request body/header assertion fails.
15. Implement minimal code: generate/store a UUID per event attempt, send it with the typed event payload, and handle replay/conflict response without inflating local counts.
16. Run test — verify PASS: `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'frontend funnel event carries a UUID'`
    Expected: PASS.
17. Refactor while green (bounded): keep UUID generation/replay behavior in the named API client module; run `cd apps/web && npm test -- --runInBand src/app/data-intelligence/page.test.tsx -t 'frontend funnel event carries a UUID'` and `cd apps/web && npx tsc --noEmit --pretty false`; require Expected: PASS for both.
18. Commit: `git add apps/web/src/lib/data-intelligence-api.ts apps/web/src/app/data-intelligence/page.tsx apps/web/src/app/data-intelligence/page.test.tsx apps/web/src/components/Sidebar.tsx && git commit -m "feat(web): add measurable intelligence interactions"`

## REFERENCES LOADED
- Spec frontend/admin, map and funnel requirements plus Leaflet/OpenStreetMap decision.
- `apps/web/src/app/dashboard/page.tsx`, `apps/web/src/app/analytics/page.tsx`, `apps/web/src/lib/api.ts`, `apps/web/src/components/Sidebar.tsx` — authenticated page/fetch/navigation conventions.
- `apps/web/src/components/ui/*` — existing table/card/loading/error presentation components.
- `apps/web/package.json` — Jest/Testing Library/Next/React versions.
- Context7 React Leaflet docs checked for client-only rendering under Next.js, CSS import and explicit map height, tile-layer attribution and OSM usage constraints.

## WHY THIS APPROACH
The frontend is one bounded admin surface but map rendering is isolated as a separate component/test boundary so SSR failures cannot break table BI. Complexity: standard/test-risk due to Next client/server boundaries, network contracts and Leaflet browser globals.

## SANDWICH CONTEXT
[CRITICAL: Leaflet must never execute during server rendering; the table is the required fallback and every OSM tile layer must carry attribution.]
You are implementing the Next.js admin data-intelligence surface for Phase 3 Data Intelligence & AI Foundation.
Spec: `docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md`
Design decision: deterministic backend snapshots consumed by an admin-only Next.js surface; Leaflet + OSM for MVP.
Files in scope: only files listed in this task.
Test framework: Jest + Testing Library and TypeScript compiler.
Available after: T7 backend contracts/routes.
Architecture rule: use shared types/API client, keep admin auth at request boundary, isolate Leaflet client-only.
[RESTATE: Leaflet must never execute during server rendering; the table is the required fallback and every OSM tile layer must carry attribution.]

## DELIVERABLE
Given typed admin BI responses, When page loads, Then territory/supplier/stock/measurement cards render and table fallback remains available on error/map failure.
Given valid/invalid coordinates, When map renders in browser, Then only valid points render in a client-only map with explicit height and OSM attribution.
Given an event interaction, When frontend sends measurement event, Then a stable UUID idempotency key is included and replay does not create a new key.
All tests PASS and commits exist.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- New admin navigation/page consumes exact shared API types.
- Leaflet + react-leaflet client-only integration, CSS and explicit height, valid-coordinate filtering, OSM attribution and table fallback.
- Funnel events carry UUID idempotency keys.
- `npm test` targeted commands and `npx tsc --noEmit` pass.

Must-not-have:
- No server-rendered Leaflet access, map provider credentials, hidden table fallback, non-admin data surface, or new backend contract invented in frontend.

Open question risks:
- Public OSM tile availability is accepted for MVP; if provider policy changes, report concern rather than adding Mapbox/Google.

Rollback note:
- Remove navigation/page/map bundle while backend snapshots and legacy routes remain available.

## STOP CONDITIONS
Done when: all three frontend scenarios pass, TypeScript passes and commits exist.
Uncertain when: selected react-leaflet version cannot satisfy the existing Next/React toolchain.
Escalate when: map code requires server globals, attribution is absent, or table fallback is removed.

---

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

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|---|---|---|---|---|
| T1 | Shared data-intelligence schema and API contracts | prereq | deep | schema constraints and TypeScript contracts compile |
| T2 | Staged pipeline and atomic immutable publication | T1 | deep | failed stage retains active snapshot; empty run safe |
| T3 | Scheduler, admin trigger, status and overlap prevention | T2 | standard/deep | 02:00 WIB, overlap conflict, fresh manual run identity |
| T4 | Territory management and geographic BI | T3 | standard | aggregates/map filtering/admin authorization |
| T5 | Supplier performance BI with coverage | T4 | standard | 50/30/20 score, coverage, insufficient-data |
| T6 | Stock planning and replenishment | T5 | standard | exact reorder formula and invalid-input safety |
| T7 | Sparse AI fallback and recommendation/forecast measurement | T6 | deep | fallback, UUID idempotency, WAPE, legacy compatibility |
| T8 | Next.js admin data-intelligence surfaces and Leaflet map | T7 | standard/deep | typed admin UI, client-only map, OSM attribution, event UUID |
| T9 | Cross-unit atomic publication integration verification | T7 | deep | complete snapshot consumed consistently by BI endpoints |
