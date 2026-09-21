# Closeout — 2026-09-18-analytics-deeper-insight

- **Plan:** docs/pocket/plans/2026-09-18-analytics-deeper-insight
- **Spec:** docs/pocket/spec/2026-09-18-analytics-deeper-insight/analytics-deeper-insight.md
- **Type:** flat
- **Started:** 2026-09-18  ·  **Closed:** 2026-09-18
- **Baseline SHA:** 63e5d75aff3c40a2359d7bd021cd02067bc7e54e  ·  **Final SHA:** 065bf3b7a92e61291157f32649c0716d59f2e32e
- **Result:** CLOSED — all 7 tasks DONE

> Closure note: the plan artifacts (`log.json`, this closeout) were written during the
> 2026-09-21 documentation-sync pass. The implementation and its 7 commits landed on
> 2026-09-18; only the administrative closeout was outstanding.

## Tasks

| Task | Name | done_sha | Status |
|------|------|----------|--------|
| T1 | Backend `AnalyticsService::insight()` composition | 59cbc52 | DONE |
| T2 | Endpoint + route + `isAdminOrOwner` gate | 7e6e4ec | DONE |
| T3 | Dummy `analyticsInsight` fixture + window split | 448b86b | DONE |
| T4 | Frontend insight contract types + presentation helpers | 897c57b | DONE |
| T5 | Frontend insight loader with dummy guard | 68a6572 | DONE |
| T6 | Analytics page composition + scoped degradation + AI trust labels | 065bf3b | DONE |
| T7 | Sidebar `adminOnly` + `platform_owner` full nav | 432c246 | DONE |

_SHA range: 63e5d75aff3c40a2359d7bd021cd02067bc7e54e..065bf3b7a92e61291157f32649c0716d59f2e32e (7 commits)_

_Code-only delta: 20 files, +3238/-20 (the remaining +1349 in the range is the T6 commit's
plan/spec artifacts under `docs/pocket/`)._

## Verification

- **API suite:** `php artisan test` → 525 passed, 7 skipped (pgsql-only concurrency/load), 0 failed (3484 assertions).
- **Analytics-insight focus:** `php artisan test --filter=AnalyticsInsight` → 23 passed (74 assertions) across `AnalyticsInsightServiceTest` (17) + `AnalyticsInsightEndpointTest` (6).
- **Web suite:** `npx jest` → 487 passed, 0 failed (56 suites).
- **Analytics/Sidebar focus:** `npx jest src/app/analytics src/components/Sidebar.test.tsx src/dummy/aggregates.test.ts` → 66 passed (6 suites).
- **TypeScript:** `npx tsc --noEmit` → clean (exit 0).

## Delivered

- **Backend service (T1):** `AnalyticsService::insight(CarbonInterface $end)` composing the fixed-window comparison payload, reusing the class's existing private helpers (`orderQuery`, `paymentQuery`, `outstandingOrderQuery`). Adds `pointInTimeOutstandingByOutlet()`. `dashboard()` / `salesTrends()` / `outletPerformance()` behaviour untouched.
- **Endpoint (T2):** `GET /api/analytics/insight` with an `isAdminOrOwner` gate (admin + `platform_owner`); outlet user → 403 `{status:'error'}`, no token → 401, inactive owner → 403; `start_date`/`end_date` ignored (fixed 30d window).
- **Dummy fixture (T3):** `analyticsInsight` fixture with a split comparison window (current `2026-01-16..2026-02-14`, previous `2025-12-17..2026-01-15`, 30 daily buckets).
- **Frontend contract (T4):** `apps/web/src/app/analytics/types.ts` + `presentation.ts` (`formatDeltaChip`, `trustLabel`, `methodLabel`, `measurementNote`) — single percent-formatting path, id-ID copy.
- **Guarded loader (T5):** `loadAnalyticsInsight` via `withDummyRead` — dummy ON → zero network; real path → exactly one `/analytics/insight` fetch.
- **Page composition (T6):** rebuilt `/analytics` into the strategic home — `MetricStrip` (delta chips, counts carry no chip), trend chart, top-outlet ranking with "dan N outlet lain", `NeedsAttention` strip, and retained AI cards augmented with `TrustLabel`. Two independent loads with per-section error isolation; `useDummyRefresh` re-drives BOTH loaders on a dummy-flag flip.
- **Nav gate (T7):** `/analytics` marked `adminOnly`; `platform_owner` treated admin-equivalent for `adminOnly` visibility; `finance` branch unchanged.

## Decisions

- **K-A — Option A: extend `AnalyticsService` + a new controller action.** No new service/route surface. Money/percentage arithmetic stays on the existing integer-cents path (`decimalToCents`/`moneyFromCents`) — no second rounding path, no decimal library.
- **K-B — Fixed 30-day window.** `/analytics/insight` ignores `start_date`/`end_date`; comparison = current 30d vs immediately-preceding 30d. Delta direction derived from the **unrounded** ratio; `previous == 0` → `delta_percent: null`.
- **K-C — Controller/route-level `isAdminOrOwner` gate** (admin + `platform_owner`), matching the Sidebar nav gate; no RBAC-core changes.
- **K-D — Server-qualified `needs_attention`.** Qualification: sales decline ≥ 20% (evaluated on unrounded cents, so exactly −20% is inclusive) OR point-in-time outstanding > 0. Both-reason outlets collapse to a single `sales_decline` row; list capped at 5; declines ranked before outstanding, deterministic `name` asc → `id` asc tie-break.
- **K-E — Two independent frontend loads + owner-as-admin nav.** `loadAnalyticsInsight` and `loadAnalytics` degrade independently (neither blanks the other); `useDummyRefresh` reloads both sections on flag flip; `platform_owner` sees `adminOnly` items.

## Carried Forward

Non-blocking observations — recorded for follow-up.

- **Docs (Minor):** the plan's `execution-plan.md` still reads **`Status: draft`** and has no `reviews/` directory (no per-task `T*-review.json` artifacts were produced for this plan, unlike the worktree plan). Implementation is verified green; this is a documentation/process gap only.
- **`outlets_total` / `products_total` (Assumption at risk):** these are point-in-time active counts with **no** delta entry, per spec Rule 1.2. If a delta is later wanted, it must be added deliberately.
- **Copy (Minor):** the outstanding-row label `"total tunggakan"` and trust-label wording are cosmetic id-ID copy; spec accepted adjustments here.
- **Scope guard:** no date-range picker, drill-down, ML/LLM narrative, or `/data-intelligence` rebuild was delivered — all confirmed out-of-scope by the spec.

## Skipped Tasks

_None_ — all 7 tasks delivered.
