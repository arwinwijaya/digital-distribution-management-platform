# Closeout — 2026-09-26-outlet-request-map-dashboard

- **Plan:** docs/pocket/plans/2026-09-26-outlet-request-map-dashboard
- **Type:** phased
- **Started:** 2026-09-28  ·  **Closed:** 2026-09-29
- **Baseline SHA:** 50a0252427038a35c89c3c1d65720ec5ce321fb4  ·  **Final SHA:** 05eb50468c2e8fa9f9cb12a915c914942f824100
- **Result:** CLOSED — all phases DONE, all reviewable tasks REVIEW_PASS

## Phases

### Phase 1 — execution-plan/phase-1.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T1 | Shared geographic filter helper | `55bec46` | REVIEW_PASS |
| T2 | Backend — GeographicAnalyticsService v2 payload + tightened coordinate rule | `0e541df` | REVIEW_PASS |
| T4 | Backend — OrderController additive filters | `1e63773` | REVIEW_PASS |
| T5 | API client — typed ApiError + fetchGeographicData propagation | `7c8c8a0` | REVIEW_PASS |

_SHA range: 50a0252427038a35c89c3c1d65720ec5ce321fb4..7c8c8a0_

### Phase 2 — execution-plan/phase-2.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T3 | Backend — GeographicAnalyticsController passthrough + meta | `3159a1f13fd043c2cee535975ae7297782940808` | REVIEW_PASS |
| T6 | Dummy — buildGeographic v2 + deterministic fixture parity | `31b08919c66f667e94795ebfa8e510ae6a686e2c` | REVIEW_PASS |
| T7 | Frontend — GeoMap marker-layer diff, fitBounds scope, coordinate parity, a11y list | `77e7ac17b9ce312ae4239f2a6c4a525f14b5d012` | REVIEW_PASS |

_SHA range: 7c8c8a0..77e7ac17b9ce312ae4239f2a6c4a525f14b5d012_

### Phase 3 — execution-plan/phase-3.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T8 | Frontend — OutletDrawer (snapshot-only, freeze banner, a11y) | `a8007203fbad168bfaf25824b88ecece7b517fe5` | REVIEW_PASS |
| T9 | Frontend — Page filter chips, empty states, error handling, drawer wiring | `7f8fae3050ac9a3c5d9f55d4c0debef647a8abf8` | REVIEW_PASS |
| T10 | Integration — Page + GeoMap + Drawer filter sync | `3a76bc19df168b3983de09fa4ad3dd182debbbe2` | REVIEW_PASS |
| T11 | Performance — ScaleFixtureSeeder baseline, <100ms recompute, <500KB response + truncation cap + frontend warning | `05eb50468c2e8fa9f9cb12a915c914942f824100` | REVIEW_PASS |

_SHA range: 77e7ac17b9ce312ae4239f2a6c4a525f14b5d012..05eb50468c2e8fa9f9cb12a915c914942f824100_

## Carried Forward

Non-blocking observations from review — accepted at close, recorded for follow-up.

- **T1** (Minor): Dead branch in normalizeStatuses: expanded.includes(SEMUA) is always false because expandSemua never emits 'Semua'; the Semua case is already handled by expandSemua. Harmless but misleading. — apps/web/src/lib/geographic-filters.ts:185
- **T1** (Minor): O(n²) deduplication using indexOf; a Set-based dedupe would be O(n) and clearer. — apps/web/src/lib/geographic-filters.ts:190
- **T1** (Minor): Test file re-declares SnapshotWindow inline instead of importing the exported type from geographic-filters.ts. — apps/web/src/lib/geographic-filters.test.ts:3
- **T1** (Minor): Local toCents adds defensive guards (empty/'-'/'.' strings) not present in the aggregates.ts convention; functionally safer but not a shared import. — apps/web/src/lib/geographic-filters.ts:116-123
- **T1** (strength): All 14 unit tests pass, covering every DELIVERABLE scenario and edge case
- **T1** (strength): Pure functions, no side effects or I/O, deterministic string-based date math
- **T1** (strength): Integer-cents aggregation matches the dummy aggregates convention; avoids float precision bugs
- **T1** (strength): Snapshot window.end used for Hari ini — no machine-date leakage
- **T1** (strength): Explicit v1/partial-row handling via legacyOnly and hasDailyDetail
- **T1** (strength): Empty status selection correctly yields no-request state (no silent default revert)
- **T1** (strength): Zero out-of-scope file changes; helper remains leaf-level
- **T2** (Minor): Docblock compression removed the @return/param annotations on produce() and pickLatest(); brief one-liners remain but IDE/static-analysis detail was lost in exchange for line count. — apps/api/app/Services/GeographicAnalyticsService.php:24
- **T2** (strength): Refactor cleared both cycle-1 Important findings: file 370→294 lines, produce() 217→18 lines, longest method accumulateOrders 42 lines
- **T2** (strength): produce() is now a pure 18-line orchestrator (fetch → accumulate → fetchProducts → build → merge), each step single-purpose
- **T2** (strength): Zero-float money aggregation preserved via integer cents (decimalToCents/formatCents bodies unchanged)
- **T2** (strength): Daily buckets strictly ascending by YYYY-MM-DD via ksort(SORT_STRING); sparse omission preserved
- **T2** (strength): Product summary bounded to 5 via named PRODUCT_SUMMARY_LIMIT constant with qty-desc/product_id-asc tie-break
- **T2** (strength): latest_request correctly nullable with no fabrication; deterministic created_at-then-id tie-break preserved
- **T2** (strength): Canonical coordinate rule byte-identical (rejects null, numeric strings, NaN, INF, out-of-range, (0,0); accepts finite int/float); float cast stays at the stage boundary per spec
- **T2** (strength): Territory aggregates equivalent in shape and value via integer cents; diff touches only the two in-scope files, protected services untouched
- **T2** (strength): All 6 tests pass (94 assertions)
- **T4** (strength): Refactor heuristics cleared: OrderController.php 291 lines (<~300, was 485); index() 26 lines (was 67); buildOrderList() 40 lines; longest OrderListFilters method 24 lines; longest OrderFormatter method 37 lines — no method >~50 anywhere
- **T4** (strength): Validation-before-query preserved: index() resolves via OrderListFilters::resolve() and returns 422 before buildOrderList() constructs any query
- **T4** (strength): Exact 422 envelope preserved: {status:'error', message:'Validation failed', errors:{field}}
- **T4** (strength): Unknown-key allowlist preserved verbatim: limit,cursor,sort,order,outlet_id,status,start,end; unknown keys rejected with field-level error
- **T4** (strength): outlet_id positive-int regex preserved; canonical status CSV with exact-match + dedup preserved
- **T4** (strength): YYYY-MM-DD start<=end inclusive ≤90-day range preserved
- **T4** (strength): Byte-identical default behavior preserved: apply() only narrows on isset() filters; exact-regression test passes
- **T4** (strength): Both cycle-1 Minors verified fixed: stale buildListMeta comment replaced; literal '23:59:59' replaced with Carbon::parse(end)->endOfDay()
- **T4** (strength): Extraction into App\Support\OrderListFilters / OrderFormatter is the in-scope refactor mandated by cycle-1 fix instructions; protected services untouched
- **T4** (strength): Mechanical gate re-verified: OrderQueryTest 11 passed / 72 assertions; OrderTest+PilotWorkflowTest+SalesOrderTest 46 passed / 254 assertions; php -l clean
- **T5** (strength): ApiError exposes status, retryable, and message through Error inheritance.
- **T5** (strength): Retry classification is status-based and independent of localized message text.
- **T5** (strength): HTTP status is preserved from the Response object.
- **T5** (strength): Network failures receive status null and retryable true.
- **T5** (strength): ApiError instances are rethrown unchanged through adminFetch and fetchGeographicData.
- **T5** (strength): Dummy-mode short-circuit behavior is preserved and covered by tests.
- **T5** (strength): No new dependencies or out-of-scope file changes were introduced.
- **T5** (strength): No refactor-threshold violations detected.
- **T3** (Moderate): RED-before-production not independently verifiable from history: single squashed commit e06ae3c bundles tests+implementation; packet specified two commits. Mitigated: test content is genuinely RED-capable against the pre-image controller; all tests green. — git log task/T3
- **T3** (Minor): meta.truncated hardcoded false with no payload-size check; cap explicitly deferred to T11. — apps/api/app/Http/Controllers/GeographicAnalyticsController.php:121
- **T3** (Minor): index() ~115 lines; extracting projectMapPoint()/computeMeta() helpers would match T2/T4 discipline; advisory only. — apps/api/app/Http/Controllers/GeographicAnalyticsController.php:15-134
- **T3** (Minor): max(0, windowDays - count) silently clamps an impossible-but-corrupt state. — apps/api/app/Http/Controllers/GeographicAnalyticsController.php:112
- **T3** (strength): Self-documenting projection block; strict === true and ?? fallbacks for tri-state fields
- **T3** (strength): windowDays() defensive against malformed snapshot windows
- **T3** (strength): Money untouched (string passthrough); deterministic order preserved
- **T3** (strength): Zero out-of-scope changes; php -l clean
- **T6** (Minor): Out-of-scope additive optional v2 fields added to data-intelligence-api.ts. Acceptable, enables T7/T8 type parity. — apps/web/src/lib/data-intelligence-api.ts:61-107
- **T6** (Minor): Status-vocabulary mapping bridges factory to snapshot vocabulary; correct and commented. — apps/web/src/dummy/aggregates.ts:433-440
- **T6** (Minor): Spec Expected-literals Default ({New,Conf},30d) H=7 not asserted as its own test; only Semua+30d H=10. Carry forward. — apps/web/src/dummy/aggregates.test.ts
- **T6** (Minor): E/F v2 status-map literals not asserted; only plottable=false + filtered counts. Carry forward. — apps/web/src/dummy/aggregates.test.ts
- **T6** (Minor): buildGeographic ~110 lines exceeds 50-line heuristic but is cohesive with extracted helpers; no refactor warranted. — apps/web/src/dummy/aggregates.ts:448-568
- **T6** (strength): 30/30 tests green; tsc clean
- **T6** (strength): Deterministic fixture: zero RNG, sequential order IDs, fixed window
- **T6** (strength): Coordinate authority matches T2 contract
- **T6** (strength): Daily buckets ascending; product_summary deterministic top-5 capped
- **T6** (strength): Territory table uses ID-lookup Map, preserving legacy counting
- **T6** (strength): buildGeographic exported for direct unit test without signature change
- **T7** (strength): Minimal targeted fix (only GeoMap.tsx + GeoMap.test.tsx, +37/-1 lines)
- **T7** (strength): Behavioral regression test (marker reuse, setLatLng, fresh click payload)
- **T7** (strength): Click-time ref lookup with ?? fallback is null-safe
- **T7** (strength): Full suite 14/14 green; tsc clean; direct leaflet only; no new deps
- **T11** (Minor): No recorded RED-before-GREEN evidence in commit bodies; RED is logically substantiated by parent-code inspection.
- **T11** (Minor): Guard comment originally described zero-order rows, while implementation clears daily detail for all rows. Fixed in b0cfb9e.
- **T11** (Minor): GeographicData.meta was required but dummy buildGeographic originally emitted no meta. Fixed in b0cfb9e by emitting meta and extending the Aggregates type.
- **T11** (Minor): The 50-iteration soak budget is broad; the two single-recompute assertions carry the meaningful <100ms gate.
- **T11** (Minor): Oversized fixture originally used unseeded mt_rand. Fixed in b0cfb9e with mt_srand(1109).
- **T11** (Minor): Full gate evidence was initially partial during audit; subsequently addressed by the main agent's recorded full-suite results and post-fix smoke gates.
- **T11** (Concern): Implementation includes required deliverable support files page.tsx, data-intelligence-api.ts, page.test.tsx, and dummy/aggregates.ts in addition to the packet's three primary files. No CLI DONE_WITH_CONCERNS state exists, so this is documented rather than represented as a task status.

## Skipped Tasks

_None — every task was reviewable._
