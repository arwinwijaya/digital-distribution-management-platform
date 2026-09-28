# Task T11 — Performance — ScaleFixtureSeeder baseline, <100ms recompute, <500KB response + truncation cap + frontend warning

**Phase:** 3
**Depends:** T3, T9
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 11: Performance — ScaleFixtureSeeder baseline, <100ms recompute, <500KB response + truncation cap + frontend warning [depends: T3, T9]

## OBJECTIVE
Add `GeographicScaleTest.php` (backend response size / truncation) and a frontend perf test using the `ScaleFixtureSeeder` shape (500 total / 100 active outlets): assert the response is ≤500KB or `meta.truncated=true` with cap metadata, and that client-side filter recompute is <100ms.

Steps:
1. Write failing test for: response size / truncation metadata
   Test file: `apps/api/tests/Performance/GeographicScaleTest.php`
   Level: performance (PHP)
   Test intent: Given `ScaleFixtureSeeder` data with a published v2 snapshot / When `GET /admin/analytics/geographic` / Then the serialized response is ≤500KB, or `meta.truncated=true` with `omitted_zero_days`/`product_summary_capped` set and the cap applied.
   Exercise through: HTTP endpoint + pipeline publish
   Test doubles: none (real DB)
   Expected RED: no size guard or truncation logic
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=GeographicScaleTest`
3. Implement the response-size guard + truncation cap in the controller (setting `meta.truncated=true`) → verify PASS → refactor → commit `feat(performance): geographic response size guard with truncation metadata`
4. Write failing test for: client recompute budget
   Test file: `apps/web/src/lib/geographic-filters.perf.test.ts`
   Level: performance (Jest)
   Test intent: Given the 100-active-outlet fixture shape / When a status/period chip change recomputes counts via T1 helpers / Then the elapsed time is <100ms.
   Exercise through: `computeFilteredCounts` over the scale fixture
   Test doubles: generated deterministic fixture data (no randomness)
   Expected RED: no perf test exists
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/geographic-filters.perf.test.ts`
6. Record the measured baseline in the test comment and confirm PASS → refactor → commit `test(perf): client recompute budget on scale fixture`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rules: Truncation metadata, Filter update performance, Story: Filter update performance; spec Open Questions: performance baseline
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: a performance test plus a response-size guard with explicit truncation metadata.

## SANDWICH CONTEXT
[CRITICAL: If the 500KB target cannot be met, the controller MUST apply the documented cap and set `meta.truncated=true` — never silently truncate]
You are implementing the performance baseline and truncation guard.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/tests/Performance/GeographicScaleTest.php`, `apps/web/src/lib/geographic-filters.perf.test.ts`, `apps/api/app/Http/Controllers/GeographicAnalyticsController.php`
Available after: T3 (controller v2 payload)
Architecture rule: <100ms recompute, ≤500KB response, explicit `meta.truncated` with a frontend warning, no silent truncation
[RESTATE: If the 500KB target cannot be met, apply the cap and set meta.truncated=true]

## DELIVERABLE
Given `ScaleFixtureSeeder` data, When the endpoint responds, Then the response is ≤500KB or `meta.truncated=true` with `omitted_zero_days`/`product_summary_capped`
Given 100 active outlets, When a filter changes, Then recompute is <100ms
Given a simulated oversized response, When serialized, Then `meta.truncated=true` and the frontend warning renders
[must-not] Given an oversized response, When capped, Then the response must NOT be truncated without `meta.truncated=true`

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Test uses the real `ScaleFixtureSeeder` data
  - Truncation cap applied at controller serialization
  - Frontend warning copy: "Data peta dipangkas untuk performa. Beberapa detail mungkin tidak lengkap." appears only when `meta.truncated=true`
  - `meta.omitted_zero_days>0` with `meta.truncated=false` produces no warning
  - Client recompute measured on the reference fixture; the measured CI baseline is recorded in the perf test comment
Must-not-have:
  - Silent truncation without `meta.truncated`
  - New dependencies
Open question risks:
  - The response is inherently >500KB even with zero-day omission + product cap → report NEEDS_CONTEXT
Rollback note:
  - Remove the truncation cap and revert to unbounded serialization
Red flags:
  - Truncation without metadata → STOP
  - Perf test flaky on shared CI → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: the scale test passes (size or truncation) and client recompute is <100ms
Uncertain when: the seeder shape cannot represent 100 active outlets with valid coordinates
Escalate when: the response cannot meet 500KB even with the documented cap
