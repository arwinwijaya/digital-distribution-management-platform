# Task T5 — Implement supplier performance BI with coverage

**Phase:** 2
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
