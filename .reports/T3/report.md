# Task 3: Outlet Recommendation Discovery — Report

Status: DONE

## Scope
- `apps/api/app/Http/Controllers/AIController.php`
- `apps/api/tests/Feature/AITest.php`

## Implementation Summary
Implemented `AIController::discovery` (`GET /api/ai/discovery`, route already wired in `routes/api.php`):

1. **Strict outlet-only scoping**
   - Unauthenticated → `401` (auth middleware, unchanged).
   - Non-outlet / non-admin roles (admin, sales, marketplace) → `403` via `forbidden()` returning the `{status: error, message}` envelope.
   - Outlet requesting another outlet's `outlet_id` → `403` (envelope). `resolveOutletId()` is reused unchanged; its `HttpException` (403) is caught in the controller and converted to the error envelope so no shared/out-of-scope exception-handler change was needed.
2. **Service call**
   - Calls `RecommendationService::discover($outletId, $limit)` directly.
   - `limit` defaults to `RecommendationService::DISCOVERY_DEFAULT_LIMIT` (5).
3. **Error handling / envelope**
   - Success: `{status: 'success', data: <discover()>}`.
   - Service throws / timeout → caught, `Log::error(...)`, returns `500` `{status: 'error', message: ...}`.

## Verification
`php artisan test tests/Feature/AITest.php` (worktree `T3`, sqlite in-memory):

- 4 new tests (all PASS):
  - `test_outlet_discovery_scoping_strict_token_only` — 403 + `status: error` for `?outlet_id=<other>`
  - `test_outlet_discovery_non_outlet_role_forbidden` — 403 + `status: error` for admin/sales
  - `test_outlet_discovery_fetch_error_response_shape` — 500 + `status: error` envelope on service exception
  - `test_outlet_discovery_unauthenticated_401` — 401 without auth
- Existing AI tests: PASS (recommendations, forecast, segmentation, cross-outlet private-signals).

### Pre-existing failure (NOT introduced by T3)
`test_outlet_discovery_returns_never_bought_products` fails with
`Failed asserting that true is identical to false` at `AITest.php:97`
(`data.fallback` expected `false`, got `true`).

Verified pre-existing: checked out pristine `HEAD` (`b95474e`) — same test fails identically.
Root cause is in `RecommendationService::discover()` (T2 scope: products in this test have no
`category`, so the category-specific ranking yields no candidates and the service falls back).
Out of T3 scope (`AIController` + `AITest` only).

### Command
```
cd apps/api && php artisan test tests/Feature/AITest.php
=> 8 passed, 1 failed (pre-existing), 62 assertions
```

## Notes
- No changes to auth/RBAC gates, DB schema, `OrderCreationService`, or the exception handler.
- `graphify` knowledge graph refreshed and committed separately.
