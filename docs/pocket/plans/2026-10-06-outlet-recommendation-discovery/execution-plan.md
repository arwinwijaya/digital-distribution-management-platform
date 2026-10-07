# EXECUTION PLAN — Outlet Recommendation Discovery

**Date:** 2026-10-06
**Spec:** docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
**Status:** draft
**Total tasks:** 9

---

## Execution Overview

### Recommended Order
```
T1 → T2 → T3 → T4 → T5 → T6, T7 (parallel) → T8 → T9
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T6, T7 | T5 completes |
| Group B | (none) | — |

### Constraints Reminder
**Architecture:** Must NOT touch `OrderCreationService` contract, auth/RBAC, existing DB schema (no migrations). Must follow existing patterns: `resolveOutletId()` scoping + explicit `isOutlet()` check, `EXCLUDED_ORDER_STATUSES` reuse, guard availability (`is_active`, `stock_quantity > 0`, supplier `subscription_status`), deterministic bounded ranking (limit 5, clamp max 5, tie-breaker `id ASC`), metadata fallback envelope, idempotency order, fire-and-forget tracking.

**Out-of-scope:** ML/probabilistic ranking, auto-add to cart, changes to reorder ("Pesan Lagi"), non-outlet roles, "Optimal/AI" claims, DB migrations.

**Assumptions at risk:** Ranking signal v1 = populer per kategori outlet + fallback populer global; limit 5 item; dismiss session-only; tracking `console.warn` on failure.

**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

### File Structure Map

```
Rule: eksklusi produk yang sudah dibeli + guard ketersediaan + fallback populer global + cancelled-only inclusion
  Create: apps/api/app/Services/RecommendationService.php (method discover)
  Create: apps/api/app/Http/Controllers/AIController.php (method discovery)
  Modify: apps/api/routes/api.php (add GET /ai/discovery)
  Test:   apps/api/tests/Feature/AITest.php (test_outlet_discovery_*)

Rule: tracking funnel outlet (record, replay, conflict, non-outlet 403)
  Create: apps/api/app/Http/Controllers/AIController.php (method storeDiscoveryEvent)
  Modify: apps/api/routes/api.php (add POST /ai/discovery/events)
  Test:   apps/api/tests/Feature/AITest.php (test_outlet_discovery_event_*)

Rule: tambah ke keranjang + validasi harga/stok + dismiss session-only + cart dedup + offline/reconnect
  Create: apps/web/src/hooks/useOutletDiscovery.ts
  Create: apps/web/src/components/DiscoverySection.tsx
  Modify: apps/web/src/components/OrderForm.tsx (render DiscoverySection below ReorderSection)
  Test:   apps/web/src/hooks/useOutletDiscovery.test.ts
  Test:   apps/web/src/components/DiscoverySection.test.tsx
  Test:   apps/web/src/components/OrderForm.test.tsx

Rule: feature flag guard + dummy-mode hide + post-order refresh + offline/reconnect
  Covered by: T8 (OrderForm integration)
  Test:   apps/web/src/components/OrderForm.test.tsx
  Test:   apps/web/e2e/outlet-discovery.spec.ts

Rule: end-to-end cross-unit (full flow + offline + tracking + 422 stock)
  Test:   apps/web/e2e/outlet-discovery.spec.ts
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

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

---

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

---

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

---

### Task 4: Implement AIController::storeDiscoveryEvent() + tracking idempotency [depends: T3]

## OBJECTIVE
Implement outlet tracking endpoint reusing RecommendationEvent model, with idempotent replay logic matching MeasurementController pattern.

Steps:
1. Write failing test for: test_outlet_discovery_event_tracking_recorded
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given outlet authenticated, When POST /api/ai/discovery/events with event_type=displayed + product_id, Then 201 with event recorded
   Exercise through: `POST /api/ai/discovery/events` via authenticated outlet client
   Test doubles: none
   Expected RED: endpoint missing

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=test_outlet_discovery_event_tracking_recorded`

3. Write failing test for: test_outlet_discovery_event_idempotent_replay
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given same event_uuid sent twice with identical payload, When POST twice, Then second returns 200 with idempotent_replay=true
   Exercise through: `POST /api/ai/discovery/events` via authenticated outlet client
   Test doubles: none
   Expected RED: idempotency not implemented

4. Run test — verify FAIL

5. Write failing test for: test_outlet_discovery_event_conflict_409
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given same event_uuid sent with different attributes, When POST twice, Then second returns 409
   Exercise through: `POST /api/ai/discovery/events` via authenticated outlet client
   Test doubles: none
   Expected RED: conflict detection not implemented

6. Run test — verify FAIL

7. Write failing test for: test_outlet_discovery_event_non_outlet_forbidden
   Test file: `apps/api/tests/Feature/AITest.php`
   Level: integration
   Test intent: Given admin authenticated, When POST /api/ai/discovery/events, Then 403
   Exercise through: `POST /api/ai/discovery/events` via authenticated admin client
   Test doubles: none
   Expected RED: role check not implemented

8. Run test — verify FAIL

9. Implement `AIController::storeDiscoveryEvent()`:
   - Derive `outlet_id` from token (outlet-only, reuse explicit isOutlet() check like discovery())
   - Validate: event_uuid (required, string, max 128), event_type (required, in: displayed,clicked,cart), product_id (required, exists:products), occurred_at (required, date)
   - Idempotent replay logic: check existing RecommendationEvent by event_uuid; if identical → 200 with idempotent_replay=true; if conflicting → 409
   - Create RecommendationEvent with outlet_id from token, metadata.source='discovery_v1'
   - Return 201 on create, 200 on replay

10. Run all 4 tests — verify PASS

11. Refactor while green: extract idempotency logic to shared service/helper if MeasurementController and this share code (avoid duplication)

12. Commit: `git commit -m "feat(api): implement outlet discovery event tracking with idempotent replay"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rule: tracking funnel outlet

## WHY THIS APPROACH
Complexity: standard
Justification: Reuses existing RecommendationEvent model + MeasurementController idempotency pattern; outlet-only scoping; multiple GWT scenarios. Sequential after T3 because both modify AIController.php.

## SANDWICH CONTEXT
[CRITICAL: Must reuse RecommendationEvent model without migration; outlet-only scoping; fire-and-forget from client perspective]
You are implementing Task 4 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A (new endpoint under /api/ai/discovery/events)
Files in scope: apps/api/app/Http/Controllers/AIController.php, apps/api/tests/Feature/AITest.php
Available after: T3 (AIController scoping pattern established)
Architecture rule: Fire-and-forget tracking; idempotent replay per MeasurementController pattern
[RESTATE: Must NOT touch auth/RBAC, existing DB schema]

## DELIVERABLE
Given outlet authenticated + valid payload, When POST /api/ai/discovery/events, Then 201 event recorded
Given same event_uuid identical payload, When POST twice, Then 200 with idempotent_replay=true
Given same event_uuid conflicting payload, When POST twice, Then 409
Given non-outlet authenticated, When POST /api/ai/discovery/events, Then 403

## QUALITY BAR
Must-have:
  - outlet_id derived from token (not body)
  - Idempotent replay matches MeasurementController logic
  - metadata.source = 'discovery_v1' for analytics filtering
  - Validation: event_type in displayed/clicked/cart, product_id exists

Must-not-have:
  - Auto-add to cart
  - Blocking on tracking failure
  - DB migration

Open question risks:
  - If MeasurementController idempotency extracted to helper, both must use it

Rollback note:
  - Revert AIController::storeDiscoveryEvent to stub

## STOP CONDITIONS
Done when: all 4 tests pass, idempotency works
Escalate when: RecommendationEvent schema change needed

---

### Task 5: Scaffold web hook + component stubs + test files [depends: T2]

## OBJECTIVE
Create minimal `useOutletDiscovery` hook and `DiscoverySection` component stubs BEFORE writing tests.

Steps:
1. Create stub for `apps/web/src/hooks/useOutletDiscovery.ts` returning empty discovery array and loading false
2. Create stub for `apps/web/src/components/DiscoverySection.tsx` returning null or empty container
3. Write failing test for: useOutletDiscovery_fetches_discovery_when_online
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit (React hook test with Jest + React Testing Library)
   Test intent: Given token + products + online=true, When hook runs, Then fetch called to /api/ai/discovery and returns discovery items
   Exercise through: renderHook with mocked fetch
   Test doubles: mock fetch to return sample discovery items
   Expected RED: fetch not called / stub returns empty
4. Run test — verify FAIL: `cd apps/web && npm test -t useOutletDiscovery_fetches_discovery_when_online`
5. Write failing test for: DiscoverySection_renders_items_with_reason_and_add_button
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit (React component test)
   Test intent: Given discovery items with reason, When rendered, Then shows items with reason text and 'Tambah' button
   Exercise through: render(<DiscoverySection items={...} onAdd={...} />)
   Test doubles: mock onAdd callback
   Expected RED: items not rendered by stub
6. Run test — verify FAIL: `cd apps/web && npm test -t DiscoverySection_renders_items_with_reason_and_add_button`
7. Commit: `git commit -m "test(web): scaffold useOutletDiscovery hook and DiscoverySection component with stubs"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: tambah ke keranjang, dismiss session-only, offline

## WHY THIS APPROACH
Complexity: lightweight
Justification: Scaffold-only web task mirroring useOutletReorder/ReorderSection patterns; stubs first so tests don't hit import errors.

## SANDWICH CONTEXT
[CRITICAL: Must follow useOutletReorder pattern; dismiss session-only (memory); offline hides section]
You are implementing Task 5 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A (web integration in OrderForm)
Files in scope: apps/web/src/hooks/useOutletDiscovery.ts, apps/web/src/components/DiscoverySection.tsx, apps/web/src/hooks/useOutletDiscovery.test.ts, apps/web/src/components/DiscoverySection.test.tsx
Available after: T2 (API contract stable)
Architecture rule: Feature flag NEXT_PUBLIC_ENABLE_DISCOVERY !== 'false'; reuse useOnlineStatus; cache invalidation after order
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
[derived] Given scaffold exists, When tests run, Then hook/component files present and tests fail with expected RED (fetch not called, empty render)

## QUALITY BAR
Must-have:
  - Hook signature mirrors useOutletReorder
  - Component renders items with reason + add + dismiss
  - Offline handling: hide section + message
  - Feature flag guard

Must-not-have:
  - Persistent dismiss
  - Auto-add
  - Changes to ReorderSection

Open question risks:
  - Event tracking: hook should send displayed/clicked/cart via fetch to /api/ai/discovery/events (fire-and-forget with console.warn)

Rollback note:
  - Delete hook and component files

## STOP CONDITIONS
Done when: test files exist and fail with expected RED
Escalate when: test framework missing or config issue

---

### Task 6: Implement useOutletDiscovery hook logic [depends: T5]

## OBJECTIVE
Implement full hook logic: fetch discovery, handle online/offline/reconnect, send tracking events, add-to-cart with current catalog data, dismiss session-only, cart deduplication.

Steps:
1. Write failing test for: useOutletDiscovery_adds_item_with_current_catalog_price
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery item P3 with catalog price 12000 stock 5, When addToCart(P3), Then onAdd called with product_id and current catalog price
   Exercise through: hook addToCart callback
   Test doubles: mock onAdd, mock products catalog
   Expected RED: addToCart not implemented or uses stale data

2. Run test — verify FAIL: `cd apps/web && npm test -t useOutletDiscovery_adds_item_with_current_catalog_price`

3. Write failing test for: useOutletDiscovery_hides_item_when_stock_zero_in_catalog
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery item P3 but catalog shows stock 0, When hook returns items, Then P3 marked unavailable and add button disabled
   Exercise through: hook return value
   Test doubles: mock products catalog with stock 0
   Expected RED: stock check not implemented

4. Run test — verify FAIL

5. Write failing test for: useOutletDiscovery_dismiss_session_only
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery items, When dismiss(P3) then reload hook, Then P3 reappears (session-only)
   Exercise through: hook dismiss + re-render
   Test doubles: mock fetch response
   Expected RED: dismiss not implemented or persists

6. Run test — verify FAIL

7. Write failing test for: useOutletDiscovery_sends_tracking_events_fire_and_forget
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery items, When mount + click add, Then fetch called to /api/ai/discovery/events for displayed (once per item) + clicked+cart (on add), and fetch errors caught silently with console.warn
   Exercise through: hook lifecycle + addToCart
   Test doubles: mock fetch for both discovery + events
   Expected RED: tracking not implemented

8. Run test — verify FAIL

9. Write failing test for: useOutletDiscovery_cart_deduplication
   Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
   Level: unit
   Test intent: Given discovery items including P3, When cart already contains P3, Then P3 excluded from returned discovery items
   Exercise through: hook return value with cart param
   Test doubles: mock products catalog
   Expected RED: dedup not implemented

10. Run test — verify FAIL

11. Write failing test for: useOutletDiscovery_refetches_on_reconnect
    Test file: `apps/web/src/hooks/useOutletDiscovery.test.ts`
    Level: unit
    Test intent: Given hook offline with cached items, When online status changes to true, Then fetch re-triggered and items updated
    Exercise through: hook with mocked useOnlineStatus transition
    Test doubles: mock useOnlineStatus, mock fetch
    Expected RED: reconnect listener not implemented

12. Run test — verify FAIL

13. Implement `useOutletDiscovery`:
    - useEffect: fetch `/api/ai/discovery` when online && token && !loading
    - Listen to `useOnlineStatus()` changes; refetch when transitioning offline → online
    - Map response to DiscoveryItem[] with reason from API
    - Filter against cart + dismissed Set (memory)
    - `addToCart(productId)`: find product in `products` catalog (latest price/stock), if available call `setCart` + `onAdd` + send clicked+cart events
    - `dismiss(productId)`: add to dismissed Set
    - Tracking: on mount send 'displayed' for each item; on add send 'clicked' + 'cart'; all fetch with try/catch + console.warn; event payload includes crypto.randomUUID(), product_id, occurred_at (ISO now)
    - Offline: return empty + loading false (component handles hide)

14. Run all 6 tests — verify PASS

15. Refactor while green: extract tracking helper if reused

16. Commit: `git commit -m "feat(web): implement useOutletDiscovery with catalog sync, dismiss, cart dedup, reconnect, and fire-and-forget tracking"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: tambah ke keranjang + validasi, dismiss session-only, tracking funnel, cart dedup, offline/reconnect

## WHY THIS APPROACH
Complexity: standard
Justification: Hook with multiple async behaviors (fetch, tracking, catalog sync, reconnect); parallelizable with component task.

## SANDWICH CONTEXT
[CRITICAL: Add-to-cart uses current catalog (not stale display data); dismiss session-only; tracking fire-and-forget; cart dedup; reconnect refetch]
You are implementing Task 6 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/src/hooks/useOutletDiscovery.ts, apps/web/src/hooks/useOutletDiscovery.test.ts
Available after: T5 (scaffold)
Architecture rule: Reuse useOnlineStatus; feature flag; cache invalidation after order
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
Given item P3 price 12000 stock 5, When addToCart, Then onAdd called with current price
Given catalog stock 0 for P3, When hook runs, Then P3 unavailable (button disabled)
Given dismiss P3 then reload, Then P3 reappears
Given mount + add, Then tracking events sent (displayed per item, clicked+cart on add) with console.warn on error
Given cart contains P3, When hook runs, Then P3 excluded from discovery items
Given offline→online transition, When status changes, Then fetch re-triggered

## QUALITY BAR
Must-have:
  - Catalog sync on add (price/stock from products prop)
  - Dismiss in memory Set (cleared on reload)
  - Tracking events: displayed on mount, clicked+cart on add, all try/catch + console.warn
  - Cart deduplication
  - Reconnect refetch
  - Offline: empty items, component handles hide

Must-not-have:
  - Persistent dismiss
  - Auto-add
  - Blocking tracking

Open question risks:
  - Double-click add debounce: handled in component (button disabled during pending)

Rollback note:
  - Revert hook to scaffold

## STOP CONDITIONS
Done when: all 6 tests pass
Escalate when: catalog sync race condition with products prop updates

---

### Task 7: Implement DiscoverySection component UI [depends: T5]

## OBJECTIVE
Implement component rendering: items with reason, price, stock, add button (disabled when stock 0), dismiss button, offline state, fetch error state with retry, empty state.

Steps:
1. Write failing test for: DiscoverySection_renders_items_with_reason_add_dismiss
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given items with reason, When rendered, Then each item shows name, price, stock, reason, 'Tambah' button, dismiss button
   Exercise through: render(<DiscoverySection items={items} onAdd={fn} onDismiss={fn} />)
   Test doubles: mock callbacks
   Expected RED: component renders empty or missing elements

2. Run test — verify FAIL: `cd apps/web && npm test -t DiscoverySection_renders_items_with_reason_add_dismiss`

3. Write failing test for: DiscoverySection_disables_add_when_stock_zero
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given item with stock 0, When rendered, Then 'Tambah' button disabled with 'Stok habis' tooltip/text
   Exercise through: render with stock 0 item
   Test doubles: none
   Expected RED: stock check UI not implemented

4. Run test — verify FAIL

5. Write failing test for: DiscoverySection_offline_shows_message
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given offline=true, When rendered, Then section shows 'tersedia saat online' without items
   Exercise through: render(<DiscoverySection items={[]} offline={true} />)
   Test doubles: none
   Expected RED: offline UI not implemented

6. Run test — verify FAIL

7. Write failing test for: DiscoverySection_fetch_error_shows_retry
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given error prop set, When rendered, Then shows error notice + 'Coba lagi' button that calls onRetry
   Exercise through: render(<DiscoverySection error="Failed" onRetry={fn} />)
   Test doubles: mock onRetry
   Expected RED: error UI not implemented

8. Run test — verify FAIL

9. Write failing test for: DiscoverySection_empty_state_shows_message
   Test file: `apps/web/src/components/DiscoverySection.test.tsx`
   Level: unit
   Test intent: Given empty items array (no error, not offline), When rendered, Then shows 'Belum ada rekomendasi baru saat ini' without add buttons
   Exercise through: render(<DiscoverySection items={[]} offline={false} error={null} />)
   Test doubles: none
   Expected RED: empty state UI not implemented

10. Run test — verify FAIL

11. Implement `DiscoverySection.tsx`:
    - Section wrapper with title "Coba produk lain"
    - Grid of cards (match ReorderSection visual style)
    - Each card: name, price (Rp formatted), stock badge, reason text (small, muted), "Tambah" button (disabled if unavailable), dismiss button (icon only)
    - Offline: render message "tersedia saat online" in place of items
    - Error: render alert with message + "Coba lagi" button (calls onRetry)
    - Empty: "Belum ada rekomendasi baru saat ini"
    - Accessibility: proper aria-labels, semantic HTML

12. Run all 5 tests — verify PASS

13. Refactor while green: extract item card subcomponent if complex

14. Commit: `git commit -m "feat(web): implement DiscoverySection with reason, add, dismiss, offline, error, empty states"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — rules: tambah ke keranjang, dismiss session-only, offline, fetch error, empty state

## WHY THIS APPROACH
Complexity: standard
Justification: Component with 5 UI states (items, offline, error, empty, stock 0); parallelizable with hook task.

## SANDWICH CONTEXT
[CRITICAL: Visual parity with ReorderSection; offline hides section; error non-blocking with retry; empty state message]
You are implementing Task 7 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/src/components/DiscoverySection.tsx, apps/web/src/components/DiscoverySection.test.tsx
Available after: T5 (scaffold)
Architecture rule: Feature flag; reuse StatusBadge, Card, Button components
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
Given items with reason, When rendered, Then cards show name, price, stock, reason, add, dismiss
Given item stock 0, When rendered, Then add button disabled with 'Stok habis'
Given offline=true, When rendered, Then 'tersedia saat online' message, no items
Given error prop, When rendered, Then error notice + 'Coba lagi' button
Given empty items + not offline + no error, When rendered, Then 'Belum ada rekomendasi baru saat ini'

## QUALITY BAR
Must-have:
  - Visual consistency with ReorderSection (Card, StatusBadge, Button)
  - Reason text visible per item
  - Dismiss button per item
  - Offline + error + empty states

Must-not-have:
  - Persistent dismiss
  - Auto-add
  - Blocking error

Rollback note:
  - Revert component to scaffold

## STOP CONDITIONS
Done when: all 5 tests pass
Escalate when: visual regression with ReorderSection

---

### Task 8: Integrate DiscoverySection into OrderForm [depends: T6, T7]

## OBJECTIVE
Render DiscoverySection below ReorderSection in OrderForm, pass props from useOutletDiscovery, handle feature flag, dummy-mode hide, refresh after order.

Steps:
1. Write failing test for: OrderForm_renders_discovery_below_reorder
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given token + products + online + feature flag enabled, When OrderForm mounts, Then ReorderSection renders, then DiscoverySection renders below it
   Exercise through: render <OrderForm token="..." products={[...]} />
   Test doubles: mock useOutletReorder, useOutletDiscovery, useOnlineStatus
   Expected RED: DiscoverySection not rendered

2. Run test — verify FAIL: `cd apps/web && npm test -t OrderForm_renders_discovery_below_reorder`

3. Write failing test for: OrderForm_discovery_hidden_when_feature_flag_disabled
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given NEXT_PUBLIC_ENABLE_DISCOVERY=false, When OrderForm mounts, Then DiscoverySection not rendered
   Exercise through: render with env mock
   Test doubles: mock env
   Expected RED: feature flag not checked

4. Run test — verify FAIL

5. Write failing test for: OrderForm_discovery_hidden_in_dummy_mode
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given useDummyStore returns isDummy=true, When OrderForm mounts, Then DiscoverySection not rendered
   Exercise through: render OrderForm with dummy mock
   Test doubles: mock useDummyStore
   Expected RED: dummy guard not implemented

6. Run test — verify FAIL

7. Write failing test for: OrderForm_refreshes_discovery_after_order_success
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: integration
   Test intent: Given discovery items showing, When order submitted successfully, Then discovery refreshes (items purchased removed)
   Exercise through: render OrderForm, submit order mock success
   Test doubles: mock submitOutletOrder, mock useOutletDiscovery refresh
   Expected RED: refresh not triggered

8. Run test — verify FAIL

9. Modify `OrderForm.tsx`:
   - Import `useOutletDiscovery` and `DiscoverySection`
   - Add `NEXT_PUBLIC_ENABLE_DISCOVERY !== 'false'` guard (like `reorderEnabled`)
   - Initialize `const dismissedRef = useRef(new Set<number>());` once in component body; pass `dismissedRef.current` and `addDismissed: (id) => dismissedRef.current.add(id)` to hook
   - Render `<DiscoverySection />` below `<ReorderSection />` with props from hook
   - Pass `offline={!isOnline}` and `error` + `onRetry` from hook
   - Add `isDummy` guard (mirror `showReorderSection`: hide discovery when dummy mode)
   - After successful submit, increment `discoveryRefreshKey` (like `reorderRefreshKey`) to trigger refetch

10. Run all 4 tests — verify PASS

11. Refactor while green: extract hook options setup to avoid inline ref

12. Commit: `git commit -m "feat(web): integrate DiscoverySection into OrderForm with feature flag, dummy guard, and post-order refresh"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — all web integration rules

## WHY THIS APPROACH
Complexity: standard
Justification: Integration task wiring hook + component into existing OrderForm; follows existing ReorderSection integration pattern exactly.

## SANDWICH CONTEXT
[CRITICAL: DiscoverySection below ReorderSection; feature flag guard; dummy-mode hide; refresh after order like reorder]
You are implementing Task 8 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/src/components/OrderForm.tsx, apps/web/src/components/OrderForm.test.tsx
Available after: T6 (hook works), T7 (component works)
Architecture rule: Feature flag NEXT_PUBLIC_ENABLE_DISCOVERY; dummy-mode guard; cache invalidation after order; placement below "Pesan Lagi"
[RESTATE: Must NOT touch OrderCreationService, auth, DB schema]

## DELIVERABLE
Given token + products + online + flag enabled + not dummy, When OrderForm mounts, Then DiscoverySection renders below ReorderSection
Given flag disabled, When OrderForm mounts, Then DiscoverySection not rendered
Given dummy mode, When OrderForm mounts, Then DiscoverySection not rendered
Given order submitted successfully, When order completes, Then discovery refetches and purchased items excluded

## QUALITY BAR
Must-have:
  - Placement exactly below ReorderSection (same section wrapper)
  - Feature flag guard matching reorderEnabled pattern
  - Dummy-mode guard matching showReorderSection pattern
  - Refresh after order (increment key)
  - Props: offline, error, onRetry from hook

Must-not-have:
  - Changes to ReorderSection behavior
  - Changes to OrderCreationService
  - Auto-add

Rollback note:
  - Remove DiscoverySection render and hook call from OrderForm

## STOP CONDITIONS
Done when: all 4 tests pass, visual integration correct
Escalate when: OrderForm test setup missing or complex

---

### Task 9: Verify discovery order flow end-to-end (Playwright E2E) [depends: T8] [test-risk]

## OBJECTIVE
Use the existing Playwright setup to verify discovery API → OrderForm → add-to-cart → order submit and event recording, plus offline behavior, tracking persistence, and 422 stock validation.

Steps:
1. Write failing test for: e2e_outlet_sees_discovery_adds_to_cart_submits_order
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given authenticated seeded outlet, eligible purchase history and available discovery product, When outlet opens /orders, adds the discovery item and submits, Then order succeeds and next discovery fetch excludes the purchased item
   Exercise through: Playwright browser against local web + API stack, using existing E2E seed/auth fixtures
   Test doubles: intercept only non-deterministic analytics if needed; use real discovery/order API and test database
   Expected RED: discovery UI/API flow is absent
2. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
3. Write failing test for: e2e_offline_discovery_hidden_reorder_works
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet with cached reorder data, When browser goes offline and opens /orders, Then reorder remains visible and discovery is hidden with 'tersedia saat online'
   Exercise through: Playwright browser context offline mode on /orders
   Test doubles: Playwright network offline mode only
   Expected RED: offline discovery state is absent
4. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
5. Write failing test for: e2e_tracking_events_recorded_displayed_clicked_cart
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet views 3 discovery items and adds 1, When interactions complete, Then persisted RecommendationEvent rows include displayed for the visible products and clicked + cart for the added product
   Exercise through: Playwright UI + authenticated API/database assertion using existing E2E harness
   Test doubles: none for API/database; real E2E stack
   Expected RED: event tracking not wired through the UI
6. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
7. Write failing test for: e2e_stock_exhausted_between_add_and_submit_returns_422
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet adds discovery item P3 to cart, then P3 stock becomes 0 before submit, When outlet submits order, Then response 422 with item error for P3 and order not created
   Exercise through: Playwright UI + direct DB manipulation to exhaust stock, then submit
   Test doubles: none; real DB stock update
   Expected RED: discovery flow not verified against existing stock validation
8. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
9. Write failing test for: e2e_order_succeeds_when_tracking_fails
   Test file: `apps/web/e2e/outlet-discovery.spec.ts`
   Level: E2E (Playwright)
   Test intent: Given outlet adds discovery item and submits order while tracking endpoint returns 500, Then order succeeds and no error surfaced to outlet
   Exercise through: Playwright UI with route interception to fail /api/ai/discovery/events, then submit
   Test doubles: Playwright route interception for tracking endpoint
   Expected RED: tracking failure blocks order
10. Run test — verify FAIL: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
11. Implement any integration fixes exposed by these tests; do not change OrderCreationService contract.
12. Run all 5 tests — verify PASS: `cd apps/web && npx playwright test e2e/outlet-discovery.spec.ts`
13. Refactor while green: none expected
14. Commit: `git commit -m "test(e2e): verify outlet discovery flow, tracking, offline, stock 422, and tracking failure resilience"`

## REFERENCES LOADED
docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md — cross-unit scenarios

## WHY THIS APPROACH
Complexity: standard
Justification: Cross-unit verification (Rule 6) for scenarios spanning API + web; [test-risk] because E2E test level spans units. Uses existing Playwright setup.

## SANDWICH CONTEXT
[CRITICAL: Must verify cross-unit GWT: discovery fetch → web add → order submit → tracking recorded; offline; stock 422; tracking failure non-blocking; no new deps]
You are implementing Task 9 for Outlet Recommendation Discovery.
Spec: docs/pocket/spec/2026-10-06-outlet-recommendation-discovery/outlet-discovery.md
Design decision: Option A
Files in scope: apps/web/e2e/outlet-discovery.spec.ts
Available after: T8 (full integration in OrderForm)
Architecture rule: OrderCreationService contract unchanged; tracking fire-and-forget
[RESTATE: Must NOT touch auth/RBAC, DB schema, OrderCreationService]

## DELIVERABLE
Given outlet with history + catalog, When load /orders → see discovery → add item → submit, Then order created with item, next discovery excludes purchased
Given offline, When load /orders, Then reorder from cache, discovery hidden 'tersedia saat online'
Given view 3 items + add 1, When interactions, Then 3 displayed + 1 clicked + 1 cart in RecommendationEvent
Given stock exhausted between add and submit, When submit, Then 422 atomik + item error, order not created
Given tracking endpoint 500, When add + submit, Then order succeeds without outlet-facing error

## QUALITY BAR
Must-have:
  - Cross-unit verification of discovery → add → order → tracking
  - Offline behavior verified
  - Stock 422 atomik behavior verified (no regression)
  - Tracking failure non-blocking verified

Must-not-have:
  - New test framework setup (use existing Playwright)
  - Changes to OrderCreationService contract

Rollback note:
  - N/A (test only)

## STOP CONDITIONS
Done when: all 5 E2E tests pass
Escalate when: flaky tests or missing E2E infrastructure

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Scaffold API route + controller stubs + service stub | prereq | lightweight | Test fails with stubs returning empty/501 |
| T2 | Implement RecommendationService::discover() core logic | T1 | standard | 7 GWT: exclusion, guards, fallback, tie-breaker, cancelled-only, limit clamp, category reason |
| T3 | AIController::discovery() + strict outlet-only scoping + error handling | T2 | standard [test-risk] | 4 GWT: scoping, non-outlet 403, 401, 500 error shape |
| T4 | AIController::storeDiscoveryEvent() + idempotency | T3 | standard | 4 GWT: record, replay, conflict, non-outlet 403 |
| T5 | Scaffold web hook + component stubs + test files | T2 | lightweight | Test files exist, fail with RED (stubs) |
| T6 | Implement useOutletDiscovery hook logic | T5 | standard | 6 GWT: catalog sync, stock check, dismiss, tracking, cart dedup, reconnect |
| T7 | Implement DiscoverySection component UI | T5 | standard | 5 GWT: render items, stock 0 disabled, offline, error retry, empty state |
| T8 | Integrate DiscoverySection into OrderForm | T6, T7 | standard | 4 GWT: render below reorder, flag guard, dummy guard, post-order refresh |
| T9 | Verify discovery order flow end-to-end (Playwright E2E) | T8 | standard [test-risk] | 5 GWT: full flow, offline, tracking recorded, 422 stock, tracking failure non-blocking |

---

## Preflight Summary (from Phase 0)

**Codebase scanned:**
- API: `AIController`, `RecommendationService`, `routes/api.php`, `AITest.php`, `RecommendationEvent`, `MeasurementController`, `OrderCreationService`
- Web: `OrderForm.tsx`, `useOutletReorder.ts`, `ReorderSection.tsx`, `useOnlineStatus.ts`, hooks tests, offline queue
- Patterns: `resolveOutletId()` token scoping, `EXCLUDED_ORDER_STATUSES`, bounded ranking envelope, feature flag `NEXT_PUBLIC_ENABLE_REORDER`, cache invalidation after order

**Test framework:** PHPUnit (API), **Jest + React Testing Library** (web), Playwright (E2E at `apps/web/e2e/`)
- API test conventions: `authenticatedUser()`, `order()` factory, `assertJsonPath`, factory-based data setup
- Web test conventions: `renderHook` for hooks, `render` for components, mocked callbacks, `jest.fn()`
- E2E: Playwright (`npx playwright test e2e/outlet-discovery.spec.ts`)

**File conventions:**
- API: Controllers in `app/Http/Controllers/`, Services in `app/Services/`, routes in `routes/api.php`, tests in `tests/Feature/`
- Web: Hooks in `src/hooks/`, Components in `src/components/`, tests co-located `.test.ts/.test.tsx`
- E2E: `apps/web/e2e/*.spec.ts`

**Existing helpers:**
- `useOnlineStatus()` hook for connectivity
- `loadOutletCachedSections` / `fetchOutletDashboard` for outlet data
- `MeasurementController` idempotent replay pattern for events

**Library docs fetched:**
- Laravel 10+ (routing, controllers, validation, Eloquent)
- React 18 + Jest + React Testing Library (hooks testing, component testing)
- Playwright 1.48+ (E2E)
- No unfamiliar dependencies — all in existing stack

**Key findings:**
- `RecommendationService::recommend()` already has exact guard patterns needed
- `AIController::resolveOutletId()` returns requestedOutletId for admin; **outlet-only requires explicit isOutlet() check** (as done in `segmentation()`)
- `OrderCreationService` already validates stock atomically (422 `items.{index}.quantity`)
- `RecommendationEvent` model exists with idempotent replay logic in `MeasurementController`
- Web `OrderForm` integration point confirmed: ReorderSection rendered in `<section>` before product grid
- Feature flag pattern established via `process.env.NEXT_PUBLIC_ENABLE_REORDER !== 'false'`

**Unknown areas:**
- None remaining — adapter resolver not used for discovery; Playwright confirmed.