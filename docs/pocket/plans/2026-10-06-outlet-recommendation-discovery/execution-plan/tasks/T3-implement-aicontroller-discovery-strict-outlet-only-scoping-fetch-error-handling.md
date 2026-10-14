# Task T3 — Implement AIController::discovery() + strict outlet-only scoping + fetch error handling

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Implement AIController::discovery() + strict outlet-only scoping + fetch error handling [depends: T2] [test-risk]

## OBJECTIVE
Wire AIController discovery method with strict token-only scoping (403 for non-outlet including admin, 403 for outlet_id mismatch, 401 unauthenticated), proper error response for 500/timeout, and correct envelope. Call RecommendationService::discover directly (no adapter resolver extension).

Steps:
1. Write failing test for: test_outlet_discovery_scoping_strict_token_only
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet A authenticated, When GET /api/ai/discovery?outlet_id=B, Then 403
   Exercise through: `GET /api/ai/discovery?outlet_id=<other outlet>` via authenticated outlet client
   Test doubles: none
   Expected RED: scoping not implemented

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_scoping_strict_token_only`

3. Write failing test for: test_outlet_discovery_non_outlet_role_forbidden
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given admin/sales/marketplace authenticated, When GET /api/ai/discovery, Then 403
   Exercise through: `GET /api/ai/discovery` via authenticated non-outlet client
   Test doubles: none
   Expected RED: role check not implemented

4. Run test — verify FAIL

5. Write failing test for: test_outlet_discovery_fetch_error_response_shape
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given discovery service throws exception (simulate 500), When GET /api/ai/discovery, Then response 500 with error structure (not blocking order)
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: mock RecommendationService::discover to throw exception
   Expected RED: error handling not implemented

6. Run test — verify FAIL

7. Write failing test for: test_outlet_discovery_unauthenticated_401
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given no authentication, When GET /api/ai/discovery, Then 401
   Exercise through: `GET /api/ai/discovery` without auth headers
   Test doubles: none
   Expected RED: auth middleware not applied (should be)

8. Run test — verify FAIL

9. Implement `AIController::discovery()`:
   - Get authenticated user: `$user = $request->user()`
   - **Explicit outlet-only check**: `if (! $user->isOutlet()) return $this->forbidden('Outlet access only.');` (mirrors `segmentation()` pattern)
   - Call `resolveOutletId()` for outlet_id from token (will pass outlet's own id)
   - Call `RecommendationService::discover($outletId, $limit)` directly via container binding (`$this->recommendationAdapter->resolve()` not used for discovery)
   - Return envelope with status 'success' on 200, or error structure on exception (log + return 500)

10. Run all 4 tests — verify PASS

11. Refactor while green: none expected

12. Commit: `git commit -m "feat(api): implement AIController::discovery with strict outlet-only scoping and error handling"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: strict token-only scoping, fetch error handling

## WHY THIS APPROACH
Complexity: standard
Justification: Controller wiring with strict scoping, error handling, and direct service call; [test-risk] because adapter integration boundary decision resolved (direct service call).

## SANDWICH CONTEXT
[CRITICAL: Must add explicit isOutlet() check — resolveOutletId() allows admin; no rbac middleware change]
You are implementing Task 3 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/api/app/Http/Controllers/AIController.php, apps/api/tests/Feature/AITest.php
Available after: T2 (service logic works)
Architecture rule: Dual-audience group, authorization in controller, bounded response
[RESTATE: Must NOT touch auth/RBAC, OrderCreationService contract]

## DELIVERABLE
Given outlet A authenticated + ?outlet_id=B, When GET /api/ai/discovery, Then 403
Given admin/sales authenticated, When GET /api/ai/discovery, Then 403
Given unauthenticated request, When GET /api/ai/discovery, Then 401
Given service throws exception, When GET /api/ai/discovery, Then 500 with error structure

## QUALITY BAR
Must-have:
  - Strict token scoping: outlet_id from token only, ignore query param
  - Non-outlet roles (including admin) → 403 via explicit isOutlet() check
  - Unauthenticated → 401 via auth middleware
  - Error response does not leak stack trace
  - Envelope shape consistent with recommendations endpoint

Must-not-have:
  - Changes to rbac middleware
  - Changes to resolveOutletId() logic
  - Model adapter resolver extension for discovery

Open question risks:
  - None (adapter resolver not used)

Rollback note:
  - Revert AIController::discovery to stub

## STOP CONDITIONS
Done when: all 4 tests pass, scoping enforced
Escalate when: existing recommendations broken by route change
