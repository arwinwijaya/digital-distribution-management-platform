# Task T7 — Add sparse AI fallback and recommendation/forecast measurement

**Phase:** 3
**Depends:** T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
