# Task T4 — Implement territory management and geographic BI

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
