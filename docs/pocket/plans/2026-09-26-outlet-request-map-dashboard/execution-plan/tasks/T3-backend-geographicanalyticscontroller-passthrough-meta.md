# Task T3 — Backend — GeographicAnalyticsController passthrough + meta

**Phase:** 2
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Backend — GeographicAnalyticsController passthrough + meta [depends: T2]

## OBJECTIVE
Widen the controller projection to pass through every v2 field, keep invalid-coordinate rows in `map_points` with `plottable:false` (never drop them), and emit the envelope `meta` (`truncated`, `omitted_zero_days`, `product_summary_capped`) plus `snapshot_available` / `geographic_section_available` flags.

Steps:
1. Write failing test for: v2 passthrough + meta
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: integration (controller + snapshot reader)
   Test intent: Given an active v2 snapshot with valid and invalid-coordinate outlet rows / When `GET /admin/analytics/geographic` / Then every `map_points[i]` contains `plottable`, `orders_by_status`, `sales_by_status`, `daily_by_status`, `product_summary`, `product_summary_truncated`, `latest_request`, and the envelope has `meta.{truncated,omitted_zero_days,product_summary_capped}`.
   Exercise through: HTTP endpoint
   Test doubles: none (publish a real snapshot)
   Expected RED: v2 fields dropped by fixed-field projection; no `meta`
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicAnalyticsTest`
3. Implement explicit v2 field projection + `meta` computation → verify PASS → refactor → commit `feat(controller): geographic v2 passthrough with meta`
4. Write failing test for: invalid-coordinate retention + empty flags
   Test file: `apps/api/tests/Feature/GeographicAnalyticsTest.php`
   Level: integration
   Test intent: Given an outlet with orders but `null` coordinates / When the endpoint is called / Then the row is present in `map_points` with `plottable:false`; Given no active snapshot / Then `snapshot_available:false`, `geographic_section_available:false`, empty arrays; Given an active snapshot without geographic rows / Then `snapshot_available:true`, `geographic_section_available:false`.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: invalid rows removed; flags absent
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicAnalyticsTest`
6. Implement invalid-row retention + flags → verify PASS → refactor → commit `feat(controller): retain invalid-coordinate rows with plottable flag`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: payload envelope, `map_points` v2 row, Truncation metadata, invalid-coordinate handling, empty-state precedence
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: projection widening plus envelope metadata aggregation; backward-compatible passthrough.

## SANDWICH CONTEXT
[CRITICAL: No v2 field may be silently dropped — the projection must explicitly list every v2 field so a future addition fails loudly]
You are implementing the geographic v2 controller passthrough.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/app/Http/Controllers/GeographicAnalyticsController.php`, `apps/api/tests/Feature/GeographicAnalyticsTest.php`
Available after: T2 (service emits v2)
Architecture rule: envelope `{status,data}`; existing RBAC `data_intelligence:read`; invalid rows kept, not dropped; explicit `meta` distinguishes zero-omission from truncation
[RESTATE: No v2 field may be silently dropped]

## DELIVERABLE
Given a v2 snapshot row, When the endpoint responds, Then `map_points[i]` contains every v2 field and the envelope carries `meta`
Given invalid coordinates with orders, When the endpoint responds, Then the row is present with `plottable:false`
Given a v1 row, When the endpoint responds, Then legacy fields are returned and the frontend applies per-row fallback
Given no active snapshot, When the endpoint responds, Then `snapshot_available:false` and empty arrays
[derived] Given an active snapshot with zero geographic rows, When the endpoint responds, Then `snapshot_available:true`, `geographic_section_available:false`

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Explicit v2 field projection (no silent drop)
  - `omitted_zero_days` = Σ over rows with orders of `windowDays − len(daily_by_status)`
  - `product_summary_capped` = OR of per-row `product_summary_truncated`
  - Invalid-coordinate rows retained and countable
Must-not-have:
  - Removing invalid-coordinate rows from `map_points`
  - New RBAC/menu keys
Open question risks:
  - Snapshot reader does not expose the section verbatim → report NEEDS_CONTEXT
Rollback note:
  - Revert the controller to the v1 projection and re-publish the v1 snapshot
Red flags:
  - Silent field dropping → STOP
  - New route or middleware → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all v2 fields present, `meta` correct, invalid rows retained, tests green
Uncertain when: snapshot reader shape differs from assumption
Escalate when: response size exceeds 500KB without `truncated=true` (defer cap to T11, but flag)
