# Task T1 — Backend product list contract, filters, summary, supplier, and sorting

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Backend product list contract, filters, summary, supplier, and sorting [prereq] [test-risk]

## OBJECTIVE
Extend `ProductController::index()` additively for the Products clarity contract. Eager-load `supplier` and serialize only `{id,name,subscription_status}` in the nested response; retain legacy `/products` behavior (`id ASC`, default limit 100) when new params are absent; normalize invalid scalar, array, and malformed query values by ignoring them; apply category/status/stock-health filters with AND semantics; return distinct `meta.categories` (non-empty, trimmed, distinct) plus the admin page adds `Tanpa kategori`; compute filtered `{total,out_of_stock}` using `COALESCE(stock_quantity,0)`; implement safe category/status derived ordering and synchronized allowlist entries without interpolating raw query input. Add an additive `include_unpurchasable=1` query param that, when present, skips ONLY the supplier-eligibility clause in `applyCatalogFilters` (allowing products with non-active supplier and orphan supplier references); without it, legacy public-catalog eligibility (including the existing `test_inactive_supplier_products_are_not_exposed_in_legacy_catalog`) remains untouched. Do not change public purchasability semantics, `is_active` filtering, or the Product model schema.

Steps:
1. Write failing test for: admin context `include_unpurchasable=1` exposes unpurchasable products; without it legacy eligibility remains
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given products with (a) active supplier, (b) expired supplier, (c) orphan supplier_id, (d) null supplier_id, (e) inactive product / When `GET /api/products?include_unpurchasable=1` / Then all (a)-(d) appear (e excluded by `is_active`), and (b)/(c) show in results; when `GET /api/products` without param / Then (b)/(c) are excluded and the existing `test_inactive_supplier_products_are_not_exposed_in_legacy_catalog` stays green.
   Exercise through: HTTP `GET /api/products` with and without `include_unpurchasable=1`
   Test doubles: none; use `RefreshDatabase`, real factories, authenticated users
   Expected RED: current `applyCatalogFilters` always enforces supplier eligibility; new param branch does not exist.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
3. Implement the `include_unpurchasable` branch in `applyCatalogFilters` that conditionally skips the supplier-eligibility clause → verify PASS → refactor while green → commit `feat(api): add include_unpurchasable for admin context`
4. Write failing test for: supplier eager-load response and null/orphan safety
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given an authenticated `products:read` user and one product with a supplier plus one with `supplier_id=null`/orphan reference / When `GET /api/products` / Then the response is 200, the supplier item is `{id,name,subscription_status}`, the missing supplier is null, and no list failure occurs.
   Exercise through: HTTP `GET /api/products` route and JSON envelope
   Test doubles: none; use `RefreshDatabase`, real Product/Supplier factories, and authenticated test users
   Expected RED: current response does not eager-load/limit supplier to the required nested object and does not expose `meta.categories`.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
6. Implement eager loading and additive nested supplier/category metadata serialization → verify PASS → refactor while green → commit `feat(api): add supplier context to product list`
7. Write failing test for: combined category/status/stock filters use AND semantics
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given products spanning `Minuman`, `Sembako`, active/inactive products, active/non-active suppliers, and stock values 0/5/11 / When requesting `category=Minuman&status=active&stock_health=low` / Then only rows satisfying all three filters are returned, `meta.total` and `meta.summary` describe that filtered set, and `meta.summary` shape remains `{total,out_of_stock}`.
   Exercise through: HTTP `GET /api/products` query contract
   Test doubles: none; real database fixtures
   Expected RED: controller currently has only legacy search/supplier eligibility filtering and does not recognize the new parameters or filtered summary.
8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
9. Implement normalized category/status/stock-health filters with SQL-safe AND composition and filtered aggregates using `COALESCE(stock_quantity, 0)` → verify PASS → refactor while green → commit `feat(api): add product clarity filters and summary`
10. Write failing test for: invalid scalar, array, and malformed filters are ignored with 200
    Test file: `apps/api/tests/Feature/ProductTest.php`
    Level: integration
    Test intent: Given valid products / When requests contain unknown category, invalid `stock_health`, `status[]=active`, malformed category arrays, or invalid sort/order values / Then each response is 200, invalid values are ignored, remaining valid filters still apply, and no 500/validation error is returned.
    Exercise through: HTTP query parsing at `ProductController::index`
    Test doubles: none; real HTTP requests with encoded array query parameters
    Expected RED: new branches would otherwise pass arrays into comparisons/order expressions or reject malformed values instead of silently ignoring them.
11. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
12. Implement scalar guards and silent fallback/ignore behavior for every new query parameter and limit/cursor path → verify PASS → refactor while green → commit `fix(api): ignore invalid product list query values`
13. Write failing test for: category/status sort contracts and category metadata
    Test file: `apps/api/tests/Feature/ProductTest.php`
    Level: integration
    Test intent: Given categories `Alpha`, `beta`, null, and empty plus active/non-purchasable/inactive rows with known ids / When sorting by `category` and `status` in both directions / Then category values are alphabetic with null/empty last and `id DESC` ties, status priority is `Aktif → Tidak bisa dibeli → Nonaktif` with `id DESC` ties, and `meta.categories` is distinct (non-empty, trimmed) — `Tanpa kategori` is added by the frontend.
    Exercise through: HTTP `GET /api/products?sort=category|status`
    Test doubles: none; real database fixtures; assert generated results, not SQL implementation
    Expected RED: current allowlist excludes `category` and `status`, and `ListQuery::rawOrder` cannot express the derived status priority or normalized category ordering.
14. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
15. Implement allowlist entries and SQL-safe derived order expressions with explicit null/empty handling and deterministic `id DESC` tiebreaks; preserve legacy default ordering when new params are absent → verify PASS → refactor while green → commit `feat(api): add product clarity sorting contract`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rules: Filter contract; Status filters overlap; Filtered summary and paging; Supplier response; Sorting contracts; Admin context exposes unpurchasable products; Public catalog eligibility is preserved; Implementation Notes. Code: `ProductController.php`, `Product.php`, `Supplier.php`, `ListQuery.php`, `ProductTest.php`, routes and RBAC seeder.
[CRITICAL: preserve legacy `/products` defaults and cross-module product semantics while adding clarity query behavior]

## WHY THIS APPROACH
Complexity: deep
Justification: one HTTP endpoint feeds legacy catalog/order consumers while this task adds derived SQL filters, aggregates, metadata, eager loading, and allowlisted ordering that must work across supported databases.

## SANDWICH CONTEXT
[CRITICAL: Do not alter schema, order/stock/price semantics, or public supplier eligibility; only add the Products list response/query contract]
You are implementing the backend half of Barang Menu Clarity.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products; shared `Table.tsx` remains unchanged.
Files in scope: `apps/api/app/Http/Controllers/ProductController.php`, `apps/api/tests/Feature/ProductTest.php`
Available after: none (prerequisite)
Architecture rule: keep response envelope, RBAC, pagination, and legacy defaults; use allowlists and SQL-safe expressions; `COALESCE(stock_quantity, 0)` for all stock filter/summary/sort paths.
[RESTATE: Backend work is additive and must preserve legacy `/products` behavior and cross-module semantics.]

## DELIVERABLE
Given the admin page sends `include_unpurchasable=1` / When `GET /api/products` runs / Then products with a non-active supplier or orphan supplier reference are included, while `is_active` filtering still applies.
Given a caller omits `include_unpurchasable` / When `GET /api/products` runs / Then the legacy supplier-eligibility clause applies and existing catalog eligibility tests stay green.
Given a products:read request with a supplier / When `GET /api/products` returns / Then each supplier is nested as `{id,name,subscription_status}` and missing suppliers are null without list failure.
Given category/status/stock-health query values / When the list loads / Then filters combine with AND semantics, cursor/total/has_more/summary describe the filtered query, and `meta.categories` is distinct (non-empty, trimmed).
Given NULL, zero, negative, and fractional stock / When filtering and summarizing / Then classification uses floor and NULL coalesces to zero; out-of-stock includes values `<= 0` after normalization.
Given unknown, malformed, or array query values / When the endpoint receives them / Then invalid values are ignored and response is 200.
Given category/status sort / When requested / Then the defined normalized order and `id DESC` tie-break are deterministic.
Given no new params / When legacy `/products` is requested / Then existing `id ASC` and default limit 100 behavior remains.

## QUALITY BAR
Must-have:
  - `include_unpurchasable=1` is additive and skips ONLY the supplier-eligibility clause; `is_active` filtering and `Product::isPurchasable()` are untouched.
  - Supplier is eager-loaded and serialized only with the approved nested fields.
  - Filter, summary, metadata, and sorting are server-side and use the same filtered builder.
  - `COALESCE(stock_quantity, 0)` is used for filter/summary/sort logic; no schema migration is added.
  - Invalid scalar/array/malformed query values silently fall back or are ignored with HTTP 200.
  - Backend allowlist includes exactly the columns the frontend will mark sortable in T2 (`category`, `status` plus the existing columns).
Must-not-have:
  - No changes to Product model casts, order creation, inventory deduction, default public catalog eligibility, or schema.
  - No raw query input interpolated into SQL.
Open question risks:
  - A DB-specific derived-order incompatibility → report `NEEDS_CONTEXT` with the failing driver/query; do not weaken the sorting contract.
Rollback note:
  - Revert `ProductController.php`; no database rollback is required.
Red flags:
  - Any change to Product schema, `is_active` semantics, or default public catalog eligibility → STOP.

## STOP CONDITIONS
Done when: all ProductTest cycles pass, legacy defaults are covered, and no out-of-scope file changes exist.
Uncertain when: supported DBs disagree on derived ordering or fractional numeric coercion.
Escalate when: implementing the contract requires a migration, Product model semantic change, or shared table change.
