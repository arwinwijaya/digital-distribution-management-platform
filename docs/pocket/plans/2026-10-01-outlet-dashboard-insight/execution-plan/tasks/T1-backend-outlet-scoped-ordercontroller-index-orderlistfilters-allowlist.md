# Task T1 — Backend — outlet-scoped OrderController index + OrderListFilters allowlist

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Backend — outlet-scoped OrderController index + OrderListFilters allowlist [prereq]

## OBJECTIVE
Perluas `OrderController::index` agar outlet dapat melist order miliknya sendiri (server-side scoping via token, abaikan/override `outlet_id` klien tanpa 403 leak) dan perluas allowlist status `OrderListFilters` ke 7 nilai dengan normalisasi `Canceled→Cancelled`. Jaga perilaku admin tetap byte-for-byte, termasuk paginasi `limit (default 100, max 100) + cursor (offset)` dan envelope `{status,data,meta:{has_more,limit,cursor,total}}`, serta validasi rentang filter aditif tetap menghasilkan 422.

Steps:
1. Write failing test for: IDOR outlet_id silently overridden (Story 1 — IDOR)
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given outlet A token + outlet B exists with orders / When GET /api/admin/orders?outlet_id=B (or alias GET /orders if aliased) / Then only outlet A orders returned, total reflects filtered A orders, no 403, no B ids
   Exercise through: `GET /api/admin/orders` HTTP boundary (Laravel test client with outlet user token)
   Test doubles: Real DB (RefreshDatabase), real OrderListFilters — do NOT mock OrderController or filters; only seed factories
   Expected RED: `assertJson` finds B ids or 403 "Only admins can list orders" before guard change
2. Run test — verify FAIL: `php artisan test --filter=test_outlet_scoped_order_list_ignores_client_outlet_id`
3. Implement: Update `OrderController::indexGuard` to allow outlet role when user has outlet relation (admin still passes through); before `resolve()`, inject/override `outlet_id` from `$request->user()->outlet->id` for outlet role (admin untouched); ensure `GET /credit-limit` already isolates via token (no change, just verify). Update `OrderListFilters::CANONICAL_STATUSES` to `['New','Confirmed','Delivered','Partially Paid','Paid','Cancelled','Canceled']` and normalize `Canceled→Cancelled` after `parseStatuses`.
4. Write failing test for: Allowlist expansion accepts Paid/Cancelled/Canceled and normalizes (Story 2 — Canceled variant)
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given outlet A has orders with status Paid, Cancelled, Canceled / When GET /api/admin/orders?status=Paid,Cancelled&outlet_id=A and status=Canceled / Then 200, filtered sets correct, and Canceled rows map to Cancelled label path
   Exercise through: same HTTP boundary
   Test doubles: Real DB; do NOT mock filters
   Expected RED: 422 `Status must be one of: New,Confirmed,Delivered,Partially Paid` before allowlist change
5. Run test — verify FAIL: `php artisan test --filter=test_order_status_allowlist_includes_paid_and_cancelled`
6. Write failing test for: Outlet without outlet relation returns 403 inline (Story 6 — 403 no outlet)
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given user with outlet role but no outlet relation / When GET /api/admin/orders / Then 403 with JSON `{status:"error", message:"The authenticated user is not associated with an outlet."}` (reuse show() pattern, not admin guard leak)
   Exercise through: HTTP boundary with user factory without outlet
   Test doubles: Real DB
   Expected RED: 200 or 403 with wrong message before guard outlets check
7. Run test — verify FAIL: `php artisan test --filter=test_outlet_without_outlet_returns_403`
8. Implement remaining guard/filter changes, run all three filters until green, refactor to keep `buildOrderList` pagination (limit+1 has_more) intact, commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Rules: Ringkasan+daftar, Pemetaan status (C1), Isolasi (Story 6), Acceptance Criteria — Contract Polluter (Region A) pattern applies: API client + parser are peers, must test together not isolated. `apps/api/app/Http/Controllers/OrderController.php` L26-160, `apps/api/app/Support/OrderListFilters.php` L15-110, `apps/api/tests/Feature/OrderQueryTest.php` existing suite.

## WHY THIS APPROACH
Complexity: standard
Justification: Multi-file backend change with security implication (IDOR silent override) and filter contract change that must not break existing 422/unknown-query tests. Requires cross-file coordination and judgment on guard ordering vs `test_non_admin_cannot_list_orders` precedent.

## SANDWICH CONTEXT
[CRITICAL: Server must silently override outlet_id from token for outlet role; never trust client outlet_id — IDOR leak requires redo.]
You are implementing backend outlet scoping for Outlet Dashboard & Insight.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — Same-route outlet branch, minimal backend extension (OrderController guard + OrderListFilters allowlist), no new endpoint.
Files in scope: apps/api/app/Http/Controllers/OrderController.php, apps/api/app/Support/OrderListFilters.php, apps/api/tests/Feature/OrderQueryTest.php
Available after: none (prereq)
Architecture rule: Do NOT create new endpoint, do NOT touch AnalyticsService/FinanceMetricsController, do NOT add RBAC key. Preserve admin behavior byte-for-byte including pagination meta and 422 validation.
[RESTATE: Server silently overrides outlet_id for outlet — never leak other outlet ids and never return 403 on tampered outlet_id param.]

## DELIVERABLE
Given outlet A token with query outlet_id=B, When GET /orders (scoped list) is called, Then only outlet A orders are returned (no 403 leak) [Story 1 — IDOR]
Given status "Paid" or "Cancelled" or "Canceled", When list filter is applied, Then it is accepted and Canceled is normalized to Cancelled [Story 2 — Canceled]
Given user with outlet role but no outlet relation, When listing orders, Then 403 with message "The authenticated user is not associated with an outlet." [Story 6]
Given admin token, When listing with any filters, Then behavior is unchanged (has_more, total, 422 on invalid) [regression]

## QUALITY BAR
Must-have:
  - Silent override: outlet `outlet_id` from token; client param ignored, not rejected
  - Allowlist = 7 values, Canceled normalized to Cancelled before query apply
  - Pagination preserved: limit default 100 max 100, cursor offset, has_more via limit+1, meta total from cloned filtered builder
  - 422 on unknown query key, invalid outlet_id, invalid status (outside 7), start>end, range >90 dates still works
  - Admin listing unchanged

Must-not-have:
  - New route or new controller method
  - 403 leak on tampered outlet_id (must not expose existence of B)
  - Loosening admin-only fields or touching analytics code
  - Cross-outlet totals or benchmarking logic

Open question risks:
  - N tanggal kalender window relies on client building correct start; this task only ensures range validation accepts 90 dates inclusive → if wrong: outlet 90-day selector could 422

Rollback note:
  - Revert guard + allowlist → admin-only listing restored; no migration involved

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, existing OrderQueryTest suite still green (no regression), no out-of-scope files modified
Uncertain when: outlet scope conflicts with future admin filter expectations
Escalate when: admin contract broken (has_more/total shape change) or IDOR not silently overridden
