# Task T2 — Implement RecommendationService::discover() core logic

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Implement RecommendationService::discover() core logic [depends: T1]

## OBJECTIVE
Implement the core discovery query: never-bought products, availability guards, ranking by popularity per outlet category (with global fallback), bounded limit 5 (clamp max 5), tie-breaker id ASC, metadata envelope including category reason string.

Steps:
1. Write failing test for: test_outlet_discovery_excludes_purchased_products
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet purchased P1, P2 via non-cancelled orders, and catalog has P1, P2, P3 (active, stock 10), When GET /api/ai/discovery, Then response contains P3 only, not P1 or P2
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: none (database factories)
   Expected RED: query not implemented, returns empty

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_excludes_purchased_products`

3. Write failing test for: test_outlet_discovery_guards_availability
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given candidate P3 (active, stock 10, supplier active) and P4 (stock 0 / inactive / supplier inactive), When GET /api/ai/discovery, Then response contains P3 only
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: none
   Expected RED: service stub returns empty instead of P3 (guard not applied)

4. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_guards_availability`

5. Write failing test for: test_outlet_discovery_fallback_global_popular_new_outlet
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet with NO purchase history, and 8 active available products, When GET /api/ai/discovery, Then response contains max 5 products with reason 'Populer di platform', fallback true
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: none
   Expected RED: service stub returns empty instead of the 5 global-popular fallback items

6. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_fallback_global_popular_new_outlet`

7. Write failing test for: test_outlet_discovery_ranking_deterministic_tie_breaker
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given 8 products with identical popularity score, When GET /api/ai/discovery called repeatedly, Then same 5 products returned each time (tie-breaker id ASC)
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: none
   Expected RED: service stub returns empty, so no stable 5-item ordering to assert

8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_ranking_deterministic_tie_breaker`

9. Write failing test for: test_outlet_discovery_cancelled_only_product_not_excluded
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet with product P1 only in Cancelled order, and catalog has P1 (active, stock 10), When GET /api/ai/discovery, Then response contains P1 (cancelled-only does NOT exclude)
   Exercise through: `GET /api/ai/discovery` via authenticated outlet client
   Test doubles: none
   Expected RED: service stub returns empty instead of P1 (cancelled-only must NOT exclude)

10. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_cancelled_only_product_not_excluded`

11. Write failing test for: test_outlet_discovery_limit_clamp_max_5
    Test file: `apps/api/tests/Feature/AITest.php`
    Level: integration
    Test intent: Given outlet with many eligible products, When GET /api/ai/discovery?limit=10, Then response limit is 5 (clamped to DISCOVERY_DEFAULT_LIMIT)
    Exercise through: `GET /api/ai/discovery?limit=10` via authenticated outlet client
    Test doubles: none
    Expected RED: controller passes requested limit 10 through to the service stub, which returns 10 instead of clamping to 5

12. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_limit_clamp_max_5`

13. Write failing test for: test_outlet_discovery_category_reason_string
    Test file: `apps/api/tests/Feature/AITest.php`
    Level: integration
    Test intent: Given outlet with history in category 'Minuman', When GET /api/ai/discovery returns product P3 from that category, Then reason contains 'Populer di kategori Minuman'
    Exercise through: `GET /api/ai/discovery` via authenticated outlet client
    Test doubles: none
    Expected RED: service stub returns empty, so no reason string to assert

14. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_category_reason_string`

15. Implement `RecommendationService::discover()`:
    - Add constant `DISCOVERY_DEFAULT_LIMIT = 5` (distinct from `DEFAULT_LIMIT = 10`)
    - Query: products WHERE is_active=true AND stock_quantity>0 AND (supplier_id IS NULL OR suppliers.subscription_status='active')
    - Exclude: product_ids from order_items JOIN orders WHERE orders.outlet_id=? AND orders.status NOT IN EXCLUDED_ORDER_STATUSES
    - If outlet has purchase history: filter candidates to categories of products in that outlet's eligible history, then rank those candidates by SUM(quantity) of their eligible orders across ALL outlets (tie by product_id ASC); if none, fallback to globally popular eligible products
    - If no history: global popularity (SUM(quantity) across all non-cancelled orders)
    - Clamp limit: `min(max(1, $limit), self::DISCOVERY_DEFAULT_LIMIT)`
    - Return envelope: recommendations (rank, product_id, name, sku, category, price, reason), limit, data_points (eligible orders count), data_sufficiency, fallback, method: 'discovery_popular_v1', method_version, measurement, low_confidence

16. Run all 7 tests — verify PASS: `cd apps/api && php artisan test --filter=test_outlet_discovery_`

17. Refactor while green: extract helper for availability guard if duplicated from recommend()

18. Commit: `git commit -m "feat(api): implement RecommendationService::discover with popularity ranking, availability guards, limit clamp, category reason, and cancelled-only inclusion"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: eksklusi produk yang sudah dibeli, guard ketersediaan, fallback populer global, cancelled-only inclusion, limit 5 clamp

## WHY THIS APPROACH
Complexity: standard
Justification: Core business logic with 6 GWT scenarios; ranking query requires judgment on popularity aggregation and category filtering.

## SANDWICH CONTEXT
[CRITICAL: Must reuse EXCLUDED_ORDER_STATUSES, availability guards, bounded ranking pattern, metadata envelope; limit clamp max 5]
You are implementing Task 2 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A (extend RecommendationService)
Files in scope: apps/api/app/Services/RecommendationService.php, apps/api/tests/Feature/AITest.php
Available after: T1 (stubs exist)
Architecture rule: Deterministic heuristic + bounded (limit 5, clamp max 5, tie-breaker id ASC), TrustLabel/sufficiency, guard reuse from AITest
[RESTATE: Must NOT touch OrderCreationService contract, auth/RBAC, existing DB schema]

## DELIVERABLE
Given outlet with history P1,P2 + catalog P1,P2,P3(active,stock10),P4(stock0), When GET /api/ai/discovery, Then response contains P3 with reason, not P1,P2,P4
Given outlet with history P1,P2 + catalog P3(active),P4(inactive), When GET /api/ai/discovery, Then response contains P3 only
Given new outlet + 8 active products, When GET /api/ai/discovery, Then max 5 products with reason 'Populer di platform', fallback true
Given 8 products identical popularity, When GET /api/ai/discovery repeated, Then same 5 products (id ASC tie-breaker)
Given outlet with P1 only in Cancelled order + catalog P1 active, When GET /api/ai/discovery, Then response contains P1
Given limit=10 request, When GET /api/ai/discovery?limit=10, Then response limit=5
Given outlet with history in category 'Minuman', When GET /api/ai/discovery, Then a returned product from that category has reason 'Populer di kategori Minuman'

## QUALITY BAR
Must-have:
  - Query uses NOT IN / NOT EXISTS for purchased exclusion with EXCLUDED_ORDER_STATUSES
  - Availability guards: is_active, stock_quantity>0, supplier active (or null)
  - Ranking: popularity per outlet category → fallback global
  - Envelope matches recommend() shape with method='discovery_popular_v1'
  - Reason string: 'Populer di kategori <X>' or 'Populer di platform'
  - Limit clamped to 5; constant DISCOVERY_DEFAULT_LIMIT used

Must-not-have:
  - ML/probabilistic ranking
  - Auto-add logic
  - Changes to recommend() method
  - DB migration

Open question risks:
  - Category-based popularity: union of categories from outlet's purchased products (assumed)

Rollback note:
  - Revert RecommendationService::discover to stub

## STOP CONDITIONS
Done when: all 7 tests pass, envelope correct, limit clamp works
Escalate when: query performance issue, or popularity aggregation ambiguity
