# Task T1 — Define shared data-intelligence schema and API contracts

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
