# Task T1 — Scaffold API route + controller stubs + service stub

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Scaffold API route + controller stubs + service stub [prereq]

## OBJECTIVE
Create minimal skeleton for discovery endpoint and event endpoint, plus service stub, BEFORE writing tests. The discovery controller stub must delegate to the service stub and serialize its empty envelope, so T2 endpoint-level tests exercise the real service boundary rather than stopping at a 501 response.

Steps:
1. Create route entries in `apps/api/routes/api.php` for `GET /ai/discovery` and `POST /ai/discovery/events` within `prefix('ai')` group (no rbac middleware)
2. Create stub methods in `AIController`: `discovery(Request $request): JsonResponse` parses the optional integer `limit` query parameter (default 5), delegates to `RecommendationService::discover()` and serializes its result; `storeDiscoveryEvent(Request $request): JsonResponse` returns 501 Not Implemented
3. Create stub method in `RecommendationService`: `discover(?int $outletId, int $limit = self::DISCOVERY_DEFAULT_LIMIT): array` returning empty recommendations with fallback true, the un-clamped `$limit`, method 'discovery_popular_v1'
4. Write failing test for: test_outlet_discovery_returns_never_bought_products
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet with purchase history P1, P2, and catalog has P1, P2, P3 (active, stock 10), P4 (stock 0), When GET /api/ai/discovery, Then response 200 with recommendations containing P3 only, reason present, fallback false, limit 5, method 'discovery_popular_v1'
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: none (uses database factories, real RecommendationService)
   Expected RED: route and controller delegation work, but service stub returns empty recommendations instead of P3
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_returns_never_bought_products`
6. Commit: `git commit -m "test(api): scaffold discovery endpoint and test placeholder"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rule: eksklusi produk yang sudah dibeli + guard ketersediaan + fallback populer global

## WHY THIS APPROACH
Complexity: lightweight
Justification: Scaffold-only task with clear file boundaries; no logic yet.

## SANDWICH CONTEXT
[CRITICAL: Must NOT touch OrderCreationService contract, auth/RBAC, existing DB schema]
You are implementing Task 1 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A (extend AIController + RecommendationService)
Files in scope: apps/api/routes/api.php, apps/api/app/Http/Controllers/AIController.php, apps/api/app/Services/RecommendationService.php, apps/api/tests/Feature/AITest.php
Available after: none (prereq)
Architecture rule: Reuse existing patterns — resolveOutletId(), EXCLUDED_ORDER_STATUSES, bounded ranking, metadata envelope
[RESTATE: Must NOT touch OrderCreationService contract, auth/RBAC, existing DB schema]

## DELIVERABLE
[derived] Given scaffold exists, When test runs, Then route/controller/service stubs present and test fails with expected RED (stubs return empty/501)

## QUALITY BAR
Must-have:
  - Route entries inside `prefix('ai')` group (no rbac middleware)
  - AIController methods exist with proper signature
  - RecommendationService::discover exists with same envelope shape as recommend()
  - Test file has initial failing test that runs and fails

Must-not-have:
  - Any business logic in stubs
  - A 501 discovery controller stub that prevents later service tests from reaching RecommendationService::discover()
  - DB migration
  - Changes to auth/RBAC

Open question risks:
  - Ranking signal v1: populer per kategori — if wrong, query logic in T2 changes

Rollback note:
  - Remove route entries and stub methods

## STOP CONDITIONS
Done when: test runs through the discovery controller into the service stub and fails because recommendations are empty
Escalate when: route conflicts with existing, or test framework issues
