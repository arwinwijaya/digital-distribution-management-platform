# Phase 3 Data Intelligence & AI Foundation

**Date:** 2026-09-14
**Status:** draft
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md

---

## Summary

Lengkapi Phase 3 Data Intelligence & AI yang saat ini masih partial. Perubahan menambahkan fondasi pipeline data harian yang terukur, immutable, dan dapat diaudit, lalu menggunakan fondasi tersebut untuk geographic BI, supplier performance BI, stock planning, serta measurement recommendation dan forecast.

Implementasi tetap deterministik dan explainable untuk MVP. Hasil baru dikonsumsi admin melalui API/Next.js; kontrak dan akses endpoint AI legacy yang sudah digunakan outlet harus tetap kompatibel.

---

## Context

### Current State

Repository adalah monorepo Laravel REST API + Next.js dengan PostgreSQL support. `RecommendationService`, `ForecastService`, `SegmentationService`, dan `AnalyticsService` sudah menyediakan recommendation, forecast, segmentation, dan basic sales/outlet BI berbasis heuristic/agregasi. `Outlet` sudah memiliki latitude/longitude tetapi belum memiliki territory resmi; `Supplier` belum memiliki lead time.

Endpoint AI legacy mengizinkan akses outlet ke sinyal miliknya sendiri, sedangkan dashboard analytics admin-only. Test AI dan Analytics saat ini lulus (14 tests, 97 assertions).

### Problem / Motivation

Roadmap Phase 3 berstatus `[~] PARTIAL`. Geographic analysis, supplier performance BI, stock planning/replenishment, acceptance-rate measurement, forecast accuracy validation, dan pipeline terukur belum tersedia. Perhitungan baru perlu reproducible dan aman terhadap partial failure, bukan sekadar live query yang dapat berubah tanpa jejak.

### Related Areas

- `apps/api/app/Services/AnalyticsService.php` — existing bounded aggregate dashboard queries.
- `apps/api/app/Services/RecommendationService.php` — deterministic product recommendation and data sufficiency metadata.
- `apps/api/app/Services/ForecastService.php` — deterministic historical-mean forecast.
- `apps/api/app/Services/SegmentationService.php` — existing outlet segmentation.
- `apps/api/app/Http/Controllers/AIController.php` — legacy AI access and response boundaries.
- `apps/api/app/Models/Outlet.php` — outlet location and ownership data; territory field must be added.
- `apps/api/app/Models/SalesVisit.php` — existing sales/outlet relationship pattern.
- `apps/api/app/Models/Supplier.php` and product/order/payment/delivery models — source data for pipeline metrics.
- `apps/api/tests/Feature/AITest.php` and `AnalyticsTest.php` — existing API test conventions.
- `apps/web/src/app/dashboard/`, `apps/web/src/app/analytics/`, and related API client utilities — existing frontend surfaces.
- `checklist.md` and `development-roadmap.md` — Phase 3 gap evidence.

---

## Scope

### In-Scope

- Daily pipeline foundation at 02:00 WIB, processing the prior day and 30-day rolling operational windows.
- Dataset/metric registry, pipeline run status, pipeline version, lineage metadata, and immutable snapshots.
- Staging plus atomic publish: partial or failed runs must never replace the last successful snapshot.
- Overlap prevention, failure recording, and admin manual trigger; no automatic retry.
- Admin-managed outlet territory CRUD and territory assignment.
- Geographic BI with territory table aggregates and Leaflet/OpenStreetMap map points.
- Supplier performance BI over the default 30-day window:
  - fulfillment 50%;
  - on-time 30%;
  - catalog quality 20%.
- Stock planning with stockout warning and reorder quantity.
- Recommendation funnel measurement: displayed → clicked → cart → purchased.
- Forecast measurement using WAPE and a strict accuracy target above 70% (WAPE below 30%).
- Next.js admin pages/components and Laravel feature tests for all new behavior.

### Out-of-Scope

- Python ML service, machine-learning model training, or LLM integration — deterministic PHP heuristics remain the MVP approach.
- Separate analytics warehouse/platform — PostgreSQL application storage and aggregates remain the source of truth.
- Full campaign, promotion broadcast, or cross-selling action workflow — measurement only records and reports funnel behavior.
- Automatic retry of failed pipeline runs — admin-triggered rerun is sufficient for this scope.
- Changes to legacy recommendation/forecast authorization or response contracts.
- External map provider other than public Leaflet/OpenStreetMap rendering for the MVP.

---

## Architecture Constraints

- **Layers this work may touch:** Laravel migrations/models/services/controllers/console scheduling and feature tests; Next.js admin pages/components/API client; existing shared frontend types where required.
- **Layers this work must NOT touch:** Python/LLM infrastructure, a separate analytics platform, or unrelated legacy AI behavior.
- **Patterns that must be followed:** Eloquent models and migrations; service-layer business logic; REST controllers with request validation; admin authorization at the request boundary; database transactions/locks for publication and idempotency; deterministic, explainable calculations; existing Laravel and Jest/Next.js test conventions.
- **Data integrity:** New snapshots are immutable and versioned. Publish is atomic. Failed runs preserve the last successful snapshot. Concurrent runs cannot publish competing results.
- **Compatibility:** Existing `/api/ai/recommendations`, `/api/ai/forecast`, and segmentation behavior must remain compatible for authorized outlet users. New pipeline and BI endpoints are admin-only.
- **Architecture validation result:** PASS.

---

## Dependencies

### Existing (to leverage)

- Laravel, Eloquent, Artisan scheduler, and database transactions — API, scheduling, persistence, and atomic publication.
- PostgreSQL support — immutable snapshots, registry, indexes, and unique idempotency constraints.
- Existing `AnalyticsService`, `RecommendationService`, `ForecastService`, and `SegmentationService` — reuse domain semantics and deterministic methods rather than duplicating legacy behavior.
- Existing Next.js/React, Axios, Tailwind, and Jest/testing-library dependencies — admin surfaces, API calls, and frontend tests.

### New (proposed)

- `leaflet` and `react-leaflet` with compatible TypeScript typings — interactive geographic table/map view. Version selection must be validated during planning against the existing Next.js/React versions; alternatives rejected: Mapbox/Google Maps because they require credentials/provider cost, CSS-only heatmap because it is not a geographic map.

---

## Stories + Scenarios

### Story: Daily measurable pipeline

> As an admin, I want a scheduled pipeline with versioned metric definitions and snapshots, so that Phase 3 results are reproducible and auditable.

**Rules:**

- Pipeline runs daily at 02:00 WIB and uses the prior-day/rolling operational data window.
- Each run has a status, pipeline version, metric/feature definitions, source window, and queryable snapshot.
- Snapshots are immutable; a successful run publishes atomically only after all stages succeed.
- Only one run may be active. Failed runs are recorded without automatic retry; admin may trigger a new run.

```gherkin
Scenario: Scheduled pipeline publishes a complete snapshot
  Given valid order, payment, product, outlet, supplier, and delivery source data exists
  When the scheduler runs at 02:00 WIB for the prior-day and rolling windows
  Then the pipeline creates a versioned completed run
  And it publishes one complete immutable snapshot with metric definitions and lineage

Scenario: Failed stage preserves the last successful snapshot
  Given a previous successful snapshot is active
  And a new pipeline run fails during one stage
  When the run finishes
  Then the run is marked failed with an error record
  And the previous successful snapshot remains active
  And no partial result is published

Scenario: Empty data is a valid safe run
  Given the source window contains no eligible operational data
  When the pipeline runs
  Then it may publish a completed empty snapshot with zero/empty outputs
  And it does not delete or corrupt prior audit history

Scenario: Overlapping run is prevented
  Given one pipeline run is active
  When a scheduler or admin trigger starts another run
  Then the second run is rejected or skipped with an explicit conflict/status response
  And it cannot publish a competing snapshot

Scenario: Admin manually triggers a failed pipeline
  Given the previous run failed and its error is recorded
  When an authenticated admin invokes the manual trigger endpoint
  Then a new run is created using a new run identity
  And no automatic retry is performed by the system
```

### Story: Sparse data fallback

> As an admin, I want safe results for new outlets and products, so that sparse history does not break BI screens or create misleading certainty.

**Rules:**

- Normal history requires 30 days of data.
- Below that baseline, recommendation and forecast use a recent-average fallback and expose `low-confidence`/limited-data metadata.
- Empty data returns safe zero/empty output.

```gherkin
Scenario: Recent-average fallback is returned for sparse history
  Given an outlet or product has fewer than 30 days of history but has recent positive observations
  When recommendation or forecast output is generated
  Then the system returns the recent-average heuristic fallback
  And marks the result low-confidence/limited-data

Scenario: Empty history returns safe output
  Given an outlet or product has no eligible history
  When the pipeline generates AI-derived metrics
  Then it returns empty or zero output with insufficient-data metadata
  And does not expose a fabricated perfect score or forecast
```

### Story: Geographic BI

> As an admin, I want sales and outlet performance grouped by assigned sales territory, so that I can identify regional opportunities.

**Rules:**

- Territory is an explicit outlet assignment managed through admin CRUD, not inferred silently from city/district or visits.
- Default analysis window is 30 days.
- Table includes all valid records. Map points require valid latitude and longitude.
- Missing territory is `Unassigned`; missing/invalid coordinates remain in the table but are not plotted.

```gherkin
Scenario: Territory table and map data are generated
  Given active outlets have official territory assignments and valid coordinates
  And valid order data exists in the 30-day window
  When an admin requests geographic BI
  Then the response returns territory sales/order/outlet aggregates
  And returns map points with territory and aggregate values

Scenario: Missing assignment is visible but not plotted without coordinates
  Given an outlet has no territory or has invalid/missing coordinates
  When an admin requests geographic BI
  Then the outlet contributes to the `Unassigned` table data where applicable
  And it is not included in the map points

Scenario: Non-admin cannot access new geographic BI
  Given the caller is unauthenticated or is not an admin
  When the caller requests geographic BI or territory management
  Then the API rejects the request with the existing authorization contract
```

### Story: Supplier performance BI

> As an admin, I want transparent supplier scores, so that supplier performance can be compared without opaque ratings.

**Rules:**

- Default window is 30 days.
- Fulfillment ratio = fully fulfilled supplier order-item lines ÷ all supplier order-item lines; weight 50%.
- On-time ratio = completed on or before stored due date ÷ records with valid delivery/due-date data; weight 30%. Missing delivery/due-date records are excluded from that denominator and coverage is reported.
- Catalog quality ratio = active eligible supplier products ÷ all supplier products; weight 20%.
- No-observation suppliers are `insufficient-data`, not assigned a misleading zero or perfect score.

```gherkin
Scenario: Supplier score exposes component and weighted values
  Given a supplier has fulfillment, due-date, delivery, and catalog observations in the 30-day window
  When an admin requests supplier BI
  Then the response includes the three component ratios
  And applies weights 50%, 30%, and 20%
  And includes observation counts and coverage

Scenario: Missing delivery data does not bias on-time score
  Given some supplier lines have no valid delivery or due-date data
  When supplier BI is generated
  Then those lines are excluded from the on-time numerator and denominator
  And the response reports reduced on-time coverage

Scenario: Supplier with no observations is insufficient
  Given a supplier has no eligible operational observations
  When supplier BI is generated
  Then its status is insufficient-data
  And no misleading perfect or zero performance score is presented
```

### Story: Stock planning and replenishment

> As an admin, I want stockout warnings and reorder quantities, so that replenishment decisions are supported by demand.

**Rules:**

- Demand uses the 30-day rolling window.
- Reorder quantity = `max(0, average daily demand × supplier lead_time_days − available stock)`.
- `lead_time_days` is stored on the supplier.
- Negative stock is clamped to zero. Null/negative lead time makes a SKU insufficient-data with no reorder recommendation.
- Zero demand or sufficient stock produces no reorder action.

```gherkin
Scenario: SKU receives a reorder recommendation
  Given a supplier has a valid positive lead time
  And a SKU has positive 30-day demand and available stock below demand during lead time
  When stock planning is generated
  Then the SKU has a stockout/reorder warning
  And the response includes a non-negative suggested reorder quantity

Scenario: Sufficient stock or zero demand produces no reorder
  Given a SKU has sufficient available stock or zero demand
  When stock planning is generated
  Then no reorder action is recommended

Scenario: Invalid stock planning input is safe
  Given a SKU has negative stock or a null/negative supplier lead time
  When stock planning is generated
  Then negative stock is treated as zero
  And an invalid lead time produces insufficient-data with no false reorder quantity
```

### Story: Recommendation and forecast measurement

> As an admin, I want measurable recommendation and forecast outcomes, so that heuristic quality can be improved based on evidence.

**Rules:**

- Frontend sends displayed, clicked, and cart events through an API; purchased is linked to successful orders.
- Every event carries a frontend UUID idempotency key. Duplicate keys do not increment counts; conflicting reuse is rejected.
- Funnel is displayed → clicked → cart → purchased and rates are safe when denominators are zero.
- Forecast accuracy uses WAPE. All-zero actual periods are pending. Minimum actual history is 30 days. Accuracy target is strictly above 70%, represented as WAPE below 30%.

```gherkin
Scenario: Recommendation funnel is measured
  Given recommendation events and successful order data exist for the 30-day measurement window
  When an admin requests recommendation measurement
  Then the response returns displayed, clicked, cart, and purchased counts in funnel order
  And returns safe conversion rates and attribution metadata

Scenario: Duplicate event retry is idempotent
  Given an event UUID has already been recorded
  When the same event UUID is submitted again
  Then the API returns an idempotent success/replay response
  And the funnel count remains unchanged

Scenario: Forecast WAPE and target status are calculated
  Given at least 30 days of forecast actuals exist with some positive actual demand
  When an admin requests forecast measurement
  Then the response returns WAPE
  And marks the target achieved only when accuracy is strictly above 70% (WAPE below 30%)

Scenario: All-zero or insufficient actuals remain pending
  Given actual sales are all zero or fewer than 30 days are available
  When forecast measurement is generated
  Then WAPE is not treated as a perfect score
  And the result is marked pending/insufficient-data

Scenario: Legacy AI access remains compatible
  Given an authorized outlet user requests its existing recommendation or forecast endpoint
  When the request is processed
  Then the existing outlet-scoped contract remains available
  And the new pipeline/BI snapshot endpoints remain admin-only
```

---

## Acceptance Criteria

```
Rule: Pipeline foundation
  ✓ Given the scheduler reaches 02:00 WIB, When the daily job runs, Then a versioned metric/feature snapshot is built from the prior-day and rolling windows.
  ✓ Given a previous snapshot is active, When a stage fails, Then the run is recorded as failed and the prior snapshot remains active with no partial publication.
  ✓ Given one run is active, When another scheduler/manual trigger starts, Then overlap is prevented and no competing snapshot is published.
  ✓ Given a failed run exists, When an admin triggers a rerun, Then a new run identity is created; no automatic retry occurs.
  ✓ Given an empty source window, When the pipeline completes, Then safe zero/empty output may be published without destroying audit history.

Rule: Sparse data
  ✓ Given fewer than 30 days of recent observations, When AI-derived output is generated, Then recent-average fallback is returned with low-confidence/limited-data metadata.
  ✓ Given no eligible history, When output is generated, Then safe empty/zero output with insufficient-data metadata is returned.

Rule: Geographic BI
  ✓ Given admin-managed territory assignments and valid coordinates, When an admin requests geographic BI, Then territory aggregates and Leaflet-compatible map points are returned.
  ✓ Given missing territory or invalid coordinates, When geographic BI is generated, Then the record remains in table data as Unassigned where applicable and is not plotted without valid coordinates.
  ✗ Given an unauthenticated/non-admin caller, When new geographic BI or territory management is requested, Then the API returns the existing authorization error contract.

Rule: Supplier performance
  ✓ Given 30-day supplier observations, When BI is generated, Then fulfillment/on-time/catalog ratios and weighted 50/30/20 score are returned with coverage.
  ✓ Given missing delivery/due-date records, When on-time score is generated, Then missing records are excluded from its denominator and coverage is reported.
  ✓ Given no supplier observations, When BI is generated, Then status is insufficient-data rather than a misleading score.

Rule: Stock planning
  ✓ Given 30-day demand, valid supplier lead time, and insufficient stock, When planning runs, Then warning and `max(0, average daily demand × lead time − available stock)` are returned.
  ✓ Given zero demand or sufficient stock, When planning runs, Then no reorder action is recommended.
  ✗ Given null/negative lead time, When planning runs, Then the SKU is insufficient-data with no false reorder quantity; negative stock is clamped to zero.

Rule: Measurement
  ✓ Given frontend funnel events and successful orders, When measurement runs, Then displayed → clicked → cart → purchased counts and safe rates are returned.
  ✓ Given a repeated event UUID, When it is submitted again, Then it is idempotent and does not inflate counts.
  ✓ Given at least 30 days of positive actuals, When forecast accuracy is generated, Then WAPE and strict >70% target status are returned.
  ✓ Given all-zero or fewer-than-30-day actuals, When accuracy is generated, Then status is pending/insufficient-data and not a false pass.
  ✓ Given an outlet uses legacy AI endpoints, When it requests its own signal, Then legacy authorization and response behavior remain compatible.

Rule: Quality and compatibility
  ✓ All new behavior has Laravel feature coverage and frontend/API contract coverage where applicable.
  ✓ Existing AI, analytics, payment/order, and authorization tests remain green.
  ✓ New pipeline/BI outputs are admin-only and no Python/LLM/separate analytics platform is introduced.
```

---

## Design Decision

**Chosen option:** Option A — Orchestrated immutable snapshot

**Summary:** Satu orchestrator Laravel menjalankan tahap dataset, metric registry, dan BI ke staging storage, lalu mempublikasikan snapshot baru secara atomic setelah seluruh tahap sukses. Snapshot diberi pipeline version/run identity dan tidak dimutasi, sehingga hasil dapat diaudit dan run gagal tidak merusak data aktif.

**Rejected options:**

- Option B — Live query + cache: rejected because it does not provide reliable immutable snapshots or reproducible historical results after source data changes.
- Option C — Event-driven analytics store: rejected because it introduces a separate analytics platform and complexity outside the MVP scope.

**Key tradeoffs accepted:**

- Snapshot storage and orchestration add migrations and job state compared with live queries.
- Daily freshness is accepted instead of real-time analytics.
- Deterministic heuristics are accepted instead of a Python/ML system.
- Public OpenStreetMap tiles are accepted for MVP map rendering, subject to provider availability and attribution requirements.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| What is the schedule timezone? | resolved: 02:00 WIB using Asia/Jakarta semantics | A wrong timezone shifts the source window and daily metrics. |
| How are territories maintained? | resolved: admin CRUD with an official outlet assignment | Unmaintained assignments produce Unassigned geographic data. |
| What is the supplier score formula? | resolved: ratio-based fulfillment 50%, on-time 30%, catalog quality 20% | Incomplete source records reduce coverage and produce insufficient-data. |
| What happens on pipeline failure/overlap? | resolved: atomic publish, retain last success, no automatic retry, prevent overlap | Admin must manually rerun failed jobs. |
| What is the sparse-data strategy? | resolved: recent-average fallback, low-confidence, 30-day baseline | Fallback quality may be weak for new products/outlets. |
| What map implementation is used? | resolved: Leaflet + OpenStreetMap | Tile availability or attribution constraints may require a later provider change. |
| How is forecast accuracy measured? | resolved: WAPE, minimum 30 actual days, all-zero pending, strict accuracy >70% | Sparse/seasonal data may delay calibration evidence. |
| How is late data handled? | resolved: included in the next scheduled run and produces a new snapshot | Same-day corrections are not reflected until the next run. |

---

## Implementation Notes

- Use Asia/Jakarta for date-window boundaries; avoid server-local timezone assumptions.
- Publish through a staging-to-active transition protected by a transaction and a single active-run guard.
- Keep source lineage and metric `method_version` in the registry/snapshot so formula changes are auditable.
- New admin endpoints should expose run status, active snapshot/version, date-window metadata, coverage, and explicit insufficient-data states.
- New frontend map code must be isolated from server rendering as required by Leaflet and must preserve a table fallback.
- Existing AI endpoint authorization and response shapes must not be changed as part of adding measurement.
- Use integer/cents or decimal-safe arithmetic for monetary metrics; never use binary floating point for persisted money values.

---

## Rollback Plan

- Disable the scheduler and manual pipeline trigger if a run or publication defect is detected.
- Keep the last successful immutable snapshot active; failed or suspect snapshots are never promoted.
- Revert the latest pipeline/BI deployment and roll back additive migrations using their safe down paths if necessary.
- Keep legacy AI and analytics endpoints available independently of the new snapshot endpoints.
- Re-run the pipeline after correction to produce a new immutable version rather than mutating historical results.
