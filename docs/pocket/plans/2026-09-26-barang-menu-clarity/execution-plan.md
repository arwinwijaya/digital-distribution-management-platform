# EXECUTION PLAN — Barang Menu Clarity

**Date:** 2026-09-26
**Spec:** docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md
**Status:** draft
**Total tasks:** 6

---

## Preflight Summary

- **Codebase scanned:** `apps/web/src/app/admin/products/page.tsx`, `api.ts`, `page.test.tsx`, `components/ui/Table.tsx`, `lib/admin-table.ts`, `components/ui/{Badge,StatusBadge,Select,TableSummary}.tsx`, `dummy/{guards,store}.ts`; `apps/api/app/Http/Controllers/{ProductController,AdminProductController}.php`, `Models/{Product,Supplier}.php`, `Support/ListQuery.php`, product/supplier factories, routes, RBAC seeder, `tests/Feature/{ProductTest,AdminProductPriceTest}.php`.
- **Recent history reviewed:** existing product table work is split across commits `c47cda0`, `32cd1b5`, and `15f2f18`; backend list compatibility work is in `6d01470`, `46ad726`, `7c73ea7`, and `d6dc615`.
- **Test framework:** frontend Jest + Testing Library (`cd apps/web && npx jest ...`); backend Laravel PHPUnit (`cd apps/api && php artisan test ...`). Existing tests are colocated with frontend modules and feature tests are in `apps/api/tests/Feature`.
- **File conventions:** API responses use `{status,data,meta}`; dummy reads use `withDummyRead`; product list uses offset cursor and `compareRows`/`paginate`; backend query scalar safety uses `ListQuery::scalarString`; UI composes existing `Table`, `Card`, `Badge`, `StatusBadge`, `TableSummary`, and `TablePagination`.
- **Existing helpers:** `ListQuery` for scalar query normalization and safe raw ordering; `compareRows`/`paginate`/`toggleSort`/`formatDateTime` in `admin-table.ts`; `withDummyRead` and `useDummyRefresh` for dummy parity. No existing product-clarity status/stock helper or expandable-row primitive.
- **Library docs fetched:** none required; all dependencies are existing Next.js/React, Laravel/Eloquent, Jest/Testing Library, and PHPUnit APIs already used in the repository. No new dependency is proposed.
- **Key findings:** `/products` is also consumed by legacy catalog/order flows, so new filters and supplier eager loading must be additive; `Table.tsx` has no row expansion behavior and must remain unchanged; current dummy products omit timestamps/supplier details and need explicit parity data; current page uses a side history panel and must move history into the local expanded detail without changing the shared table.
- **Unknown areas:** exact production DB driver behavior for derived SQL sort expressions must be verified by backend tests on the repository's supported SQLite/PostgreSQL paths.

## SPEC PARSED

- **Feature:** Kejelasan Menu Barang / Barang Menu Clarity
- **Design:** Option A — local expandable row in Products; shared `Table.tsx` remains unchanged.
- **Constraints:** no schema migration or cross-module price/stock semantic changes; server-side filters; legacy defaults when new params are absent; synchronized backend/frontend sort allowlists; full dummy parity; AbortController plus stale-response guard; `COALESCE(stock_quantity, 0)` for filter/summary/sort; no dependencies.
- **Rules:** 7 acceptance rule groups, with all 41 GWT scenarios in Stories plus the condensed acceptance criteria.
- **GWT coverage:** all story scenarios have GWT; derived assertions are added only for query normalization and helper boundaries where the condensed criteria omit implementation-level detail.
- **Negative rules:** invalid query values return 200/are ignored; orphan suppliers are not `Tidak bisa dibeli`; shared `Table.tsx` and cross-module semantics must not change.
- **Open assumptions:** threshold 10; fractional stock is floored; category matching is trim + case-sensitive exact; all `products:read` roles may receive supplier context; display precedence is `Nonaktif > Tidak bisa dibeli > Aktif`; status sort is `Aktif → Tidak bisa dibeli → Nonaktif`, with `id DESC` ties; category sort is alphabetic, nulls-last, `id DESC` ties.
- **Resolved contradiction:** the admin page always sends `include_unpurchasable=1`; only that additive request path skips the supplier-eligibility clause, while requests without the param retain legacy public-catalog eligibility (including the existing inactive-supplier regression test). `is_active` filtering and `Product::isPurchasable()` remain untouched.
- **Dependency correction:** T3 consumes the exported product types/classification helpers created by T2, so T3 depends on T2; the earlier T2/T3 parallel annotation was invalid and has been removed.
- **Rollback:** revert the Products page/adapter and ProductController changes; no database rollback is needed.

## Execution Overview

### Recommended Order
```
T1 → T2 → T3 → T4 → T5 → T6
```

> Dependency order above is recommended — pocket skill enforces actual blocking and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1 | immediately |
| Group B | T2 | T1 completes |
| Group C | T3 | T2 completes |
| Group D | T4 | T2 and T3 complete |
| Group E | T5 | T4 completes |
| Group F | T6 | T1–T5 complete |

### Constraints Reminder
**Architecture:** ProductController may extend the additive `/products` query/serializer only; Products frontend may add local row/detail modules and adapter logic; existing response envelope, RBAC middleware, pagination, dummy guard, and UI primitives remain in use. `Table.tsx` is unchanged. Backend and frontend sortable columns must stay synchronized. `COALESCE(stock_quantity, 0)` is required in every backend stock filter, summary, and stock-derived sort/classification path.

**Out-of-scope:** schema migration; UoM/conversion/threshold schema; changes to order creation, deduction, analytics, recommendations, WhatsApp, marketplace, public eligibility, stock movements, warehouses, role presets, CSV export, sparkline, virtualization, shared Table expansion, price-history edit/rollback.

**Assumptions at risk:** fixed low-stock threshold 10; floor fractional stock; trim-only case-sensitive category matching; all `products:read` roles may receive supplier name/status; badge and sort precedence specified above. Any contradiction requires `NEEDS_CONTEXT`, not a silent behavior change.

**Sequencing:** dependency order shown is recommended only — pocket-development enforces actual blocking and sequencing.

### File Structure Map

```
Rule: Backend list contract, filters, summary, eager supplier, and derived sorting
  Modify: apps/api/app/Http/Controllers/ProductController.php       (T1)
  Test:   apps/api/tests/Feature/ProductTest.php                    (T1, T6)

Rule: Frontend product contract, status/stock classification, sort mirror, and dummy parity
  Create: apps/web/src/app/admin/products/product-clarity.ts       (T2)
  Create: apps/web/src/app/admin/products/product-clarity.test.ts   (T2)
  Modify: apps/web/src/app/admin/products/api.ts                    (T2)
  Modify: apps/web/src/lib/admin-table.ts                           (T2)
  Modify: apps/web/src/app/admin/products/page.test.tsx             (T2, T3, T4, T5, T6)

Rule: Local expandable identity/detail row and accessibility
  Create: apps/web/src/app/admin/products/ProductRowDetail.tsx      (T3)
  Create: apps/web/src/app/admin/products/ProductRowDetail.test.tsx  (T3)
  Modify: apps/web/src/app/admin/products/page.tsx                   (T3, T4, T5)

Rule: Server-side filter controls, summary/paging/error state
  Modify: apps/web/src/app/admin/products/page.tsx                   (T4)
  Modify: apps/web/src/app/admin/products/api.ts                    (T4)

Rule: Price history pagination, retry, abort, and stale-response safety
  Create: apps/web/src/app/admin/products/PriceHistoryPanel.tsx      (T5)
  Create: apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx  (T5)
  Modify: apps/web/src/app/admin/products/api.ts                     (T5)
  Modify: apps/web/src/app/admin/products/page.tsx                    (T5)

Rule: Cross-unit contract verification and regression gates
  Modify: apps/api/tests/Feature/ProductTest.php                    (T6)
  Modify: apps/web/src/app/admin/products/page.test.tsx              (T6)
```

## Pocket Packets

---

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

---

### Task 2: Frontend product contract, clarity helpers, sort mirror, and dummy parity [depends: T1] [test-risk]

## OBJECTIVE
Extend the Products adapter and dummy branch for the backend contract, and create a pure local `product-clarity.ts` display-only module for normalized stock/status/category presentation and sorting/filter values. This module is the shared frontend helper consumed by T3–T5; those tasks import it rather than reimplement display status or stock logic. Model nested suppliers, `meta.categories`, `meta.summary`, `status`, and `stock_health`; preserve the legacy adapter API used by order/catalog tests; keep dummy mode equivalent in filters, sorting, summaries, metadata, supplier states, and price-history pagination with zero network calls. Add `category` and derived `status` to `SORT_ALLOWLISTS.products` in lockstep with the backend, and send `include_unpurchasable=1` on every admin list request.

Steps:
1. Write failing test for: stock normalization and health boundaries
   Test file: `apps/web/src/app/admin/products/product-clarity.test.ts`
   Level: unit
   Test intent: Given NULL/undefined/negative/zero/fractional/1/10/10.9/11 stock values / When the exported stock normalizer/classifier runs / Then values are floored, NULL becomes 0, and health is exactly Habis for `<=0`, Rendah for `1..10`, Aman for `>=11`.
   Exercise through: exported pure product-clarity functions
   Test doubles: none
   Expected RED: module and classification functions do not exist.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/product-clarity.test.ts --runInBand`
3. Implement pure stock normalization/classification and explicit labels (`Stok (unit)`, `Nilai stok`, static unit note) → verify PASS → refactor while green → commit `feat(web): add product stock clarity helpers`
4. Write failing test for: derived status display precedence and supplier safety
   Test file: `apps/web/src/app/admin/products/product-clarity.test.ts`
   Level: unit
   Test intent: Given explicit admin-backend fixtures—(a) active product/active supplier, (b) active product/non-active supplier, (c) active product/orphan supplier, (d) inactive product/active supplier, and (e) inactive product/non-active supplier / When status is derived / Then statuses are (a) Aktif, (b) Tidak bisa dibeli, (c) Aktif, (d) Nonaktif, (e) Nonaktif; orphan/null supplier is never `Tidak bisa dibeli`, is_active wins over supplier status, and detail facts retain both product and supplier state.
   Exercise through: exported status derivation helper
   Test doubles: none
   Expected RED: no local status precedence logic exists and current AdminProduct has no nested supplier model.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/product-clarity.test.ts --runInBand`
6. Implement the explicit status enum/labels, supplier null guard, and category trim-only display fallback → verify PASS → refactor while green → commit `feat(web): add product status clarity helpers`
7. Write failing test for: adapter query/response contract and dummy parity
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration (adapter + dummy store; network boundary mocked only for real mode)
   Test intent: Given filters `{category:'Minuman',status:'active',stockHealth:'low'}` and sort `{column:'status',order:'asc'}` / When `fetchAdminProducts` runs in real mode / Then URL contains encoded filters, sort/order, cursor 0, `include_unpurchasable=1`, and parsed result contains supplier, `meta.categories`, filtered summary, and pagination. When dummy mode is on with equivalent data / Then the same filtering, sorting, summary, category options, and no-network guard are observable, and the dummy contract preserves `include_unpurchasable` semantics.
   Exercise through: exported `fetchAdminProducts` and dummy mode public adapter boundary
   Test doubles: mock global `fetch` only in real mode; do not mock `fetchAdminProducts`, `listDummyAdminProducts`, or the dummy store under test
   Expected RED: adapter has no new filter fields/meta parsing, dummy list ignores filters and categories, and current sort allowlist lacks category/status.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
9. Implement AdminProduct/filters/result types, real URL serialization (always sending `include_unpurchasable=1`), response parsing, deterministic dummy supplier/category/filter/sort/summary parity, and `SORT_ALLOWLISTS.products` entries matching backend → verify PASS → refactor while green → commit `feat(web): add product clarity adapter and dummy parity`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rules: Stock normalization and health; Filter contract; Status filters overlap; Supplier response; Sorting contracts; Dummy parity. Code: `products/api.ts`, `admin-table.ts`, dummy guards/store, existing product page tests.
[CRITICAL: the frontend/backend sort allowlists and response contract must remain synchronized]

## WHY THIS APPROACH
Complexity: standard
Justification: pure classification is independently unit-testable, while the adapter/dummy seam needs integration-level URL and zero-network verification without coupling UI rendering to query math. This module is the shared frontend display helper consumed by T3–T5, so later tasks import it instead of duplicating status/stock logic.

## SANDWICH CONTEXT
[CRITICAL: dummy mode must have full parity and the frontend must never mark a sort column that ProductController does not recognize]
You are implementing the frontend contract foundation for Barang Menu Clarity.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `apps/web/src/app/admin/products/product-clarity.ts`, `product-clarity.test.ts`, `api.ts`, `apps/web/src/lib/admin-table.ts`, and mapped page tests.
Available after: T1 backend contract.
Architecture rule: retain `withDummyRead`, response envelope, legacy adapter callers, and no new dependencies; classification must floor fractional stock and coalesce null to zero.
[RESTATE: Dummy mode and real mode expose the same product clarity contract, and sort allowlists must match.]

## DELIVERABLE
Given stock values across all boundaries / When normalized/classified / Then Habis/Rendah/Aman follows the fixed threshold 10 and flooring rule.
Given product/supplier state combinations / When status is derived / Then display precedence is Nonaktif > Tidak bisa dibeli > Aktif, with orphan/null supplier safe.
Given real-mode filters/sort / When `fetchAdminProducts` runs / Then query values (including `include_unpurchasable=1`), and nested response/meta fields are preserved.
Given equivalent dummy data / When dummy mode is enabled / Then filters, sort, summary, categories, suppliers, and pagination match real-mode semantics and network calls stay at zero.
Given `category` and `status` sort columns / When the UI allowlist is inspected / Then it is synchronized with ProductController's allowlist.

## QUALITY BAR
Must-have:
  - Pure helper tests cover null, negative, fractional, 0/1/10/11 boundaries and status precedence.
  - Real and dummy adapters preserve `{status,data,meta}` semantics and all legacy callers.
  - Admin adapter always sends `include_unpurchasable=1`; dummy parity includes the same contract without a network call.
  - Dummy mode performs zero network calls and exposes categories/suppliers/history-compatible data.
  - No duplicated status/stock logic is introduced in page render code; import the domain helper.
Must-not-have:
  - No changes to shared `Table.tsx`, public catalog semantics, or new dependency.
Open question risks:
  - Existing dummy fixtures may not have supplier status fields → enrich only the adapter fixture projection; do not alter unrelated dummy domains.
Rollback note:
  - Revert helper, adapter, and allowlist changes; backend remains backward-compatible.
Red flags:
  - Frontend allows `status`/`category` while backend rejects/falls back → STOP and synchronize before continuing.

## STOP CONDITIONS
Done when: helper and adapter tests pass in real and dummy modes, and backend contract from T1 is consumed without fallback ambiguity.
Uncertain when: a legacy caller depends on an undocumented result shape; preserve it and report `NEEDS_CONTEXT`.
Escalate when: parity requires changing shared dummy infrastructure or public product semantics.

---

### Task 3: Local expandable Products identity/detail row and accessibility [depends: T2]

## OBJECTIVE
Replace the Products page's name-click/side-history interaction with a Products-local expandable row/detail composition while continuing to render the existing shared `Table` for the tabular header/cell layout. Create `ProductRowDetail.tsx` for identity, supplier facts, product status facts, price context, normalized stock value, static unit note, timestamps, and the local keyboard/ARIA trigger. Consume the `product-clarity.ts` types/helpers from T2 (do not duplicate status/stock logic). Keep `Ubah harga` an independent button and leave `apps/web/src/components/ui/Table.tsx` unchanged.

Steps:
1. Write failing test for: main identity columns and fallback/precedence labels
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration (rendered Products page)
   Test intent: Given products with category, null/empty category, active supplier, inactive supplier, inactive product, and null supplier / When the page loads / Then headers include Nama, SKU, Kategori, Status, Harga Jual, Stok (unit), Dibuat, Diperbarui, Aksi; category fallback is `—`; price tooltip explains order price; stock null renders 0/Habis; main status is Aktif for active product with active/orphan/null supplier, Tidak bisa dibeli for active product with non-active supplier, and Nonaktif for inactive product.
   Exercise through: rendered `AdminProductsPage` and public DOM/accessibility output
   Test doubles: mock `fetch` response only; do not mock ProductRowDetail or classification helpers
   Expected RED: current page has old Harga/Stok headers, no category/status columns, no tooltip, and renders null stock as `—`.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
3. Create `ProductRowDetail.tsx` and update page columns/rendering to use local clarity helpers and existing Badge/StatusBadge/Card primitives → verify PASS → refactor while green → commit `feat(web): clarify product table identity columns`
4. Write failing test for: expanded detail identity and stock value
   Test file: `apps/web/src/app/admin/products/ProductRowDetail.test.tsx`
   Level: unit
   Test intent: Given a product with description/category/supplier status/price/normalized stock/timestamps / When the local detail is rendered open / Then it shows all identity facts, supplier name/status, product status facts, `Nilai stok Rp ...`, and `Satuan belum terdefinisi di sistem`, without inventing a UoM.
   Exercise through: `ProductRowDetail` public rendered output
   Test doubles: none; pass a plain AdminProduct fixture
   Expected RED: detail component does not exist.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/ProductRowDetail.test.tsx --runInBand`
6. Implement the local detail panel fields and static unit note using T2's exported status/stock/category helpers and existing UI primitives → verify PASS → refactor while green → commit `feat(web): add expanded product identity detail`
7. Write failing test for: keyboard trigger, ARIA relation, and independent price action
   Test file: `apps/web/src/app/admin/products/ProductRowDetail.test.tsx`
   Level: unit
   Test intent: Given a closed row trigger / When Enter or Space is pressed / Then detail toggles, `aria-expanded` changes, and `aria-controls` points at the detail panel; given `Ubah harga` is focused and activated / Then its callback runs and row expansion does not toggle.
   Exercise through: local trigger/button DOM events and callbacks
   Test doubles: spy callback for price action only; do not mock React event handling or the detail component
   Expected RED: current product name button opens a side panel, has no aria-expanded/controls, and action independence is untested.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/ProductRowDetail.test.tsx --runInBand`
9. Implement semantic row trigger/keyboard handling and stop propagation/independent controls locally in Products; keep `Table.tsx` untouched → verify PASS → refactor while green → commit `feat(web): make product expansion keyboard accessible`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rules: Main table identity; Expanded identity detail; Price and stock labels; Expanded detail; Accessibility. Code: page, Table, Badge, StatusBadge, Card, Button, format helpers.
[CRITICAL: expandable behavior is local to Products; shared Table.tsx must not change]

## WHY THIS APPROACH
Complexity: standard
Justification: the page must coordinate a local detail row with shared table markup, while the detail component is independently testable for accessibility and calculated display facts.

## SANDWICH CONTEXT
[CRITICAL: Do not add expandable behavior to `apps/web/src/components/ui/Table.tsx` or alter its behavior for other admin pages]
You are implementing the Products-local expandable row for Barang Menu Clarity.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `apps/web/src/app/admin/products/page.tsx`, `ProductRowDetail.tsx`, their tests, and existing UI primitive imports only.
Available after: T2 (product clarity helpers, adapter contract, and dummy parity).
Architecture rule: compose existing `Table`, `Card`, `Badge`, `StatusBadge`, `TableSummary`, and `TablePagination`; use a local row/detail implementation and preserve independent `Ubah harga` behavior.
[RESTATE: Shared Table.tsx remains unchanged; all expansion and ARIA behavior belongs to Products.]

## DELIVERABLE
Given a loaded product list / When the page renders / Then identity columns and explicit Harga Jual/Stok (unit) labels appear with category/status/fallbacks.
Given a product row trigger / When Enter or Space is pressed / Then the detail panel opens/closes with correct `aria-expanded` and `aria-controls`.
Given expanded detail / When displayed / Then description, category, supplier context, both product/supplier facts, timestamps, price context, normalized stock value, and static unit note appear.
Given the price action / When clicked or keyboard-activated / Then only price editing runs and row expansion does not toggle.
Given an inactive product with inactive supplier / When rendered / Then one main badge is Nonaktif while detail explains supplier inactivity.

## QUALITY BAR
Must-have:
  - Local Products implementation owns expansion; shared Table.tsx has no diff.
  - Null/empty category displays em dash; null stock displays normalized 0/Habis.
  - Supplier null/orphan safely displays em dash and is not classified as non-purchasable.
  - Keyboard and ARIA behavior is directly tested.
Must-not-have:
  - No side-panel-only solution, no UoM invention, no edits to shared table behavior, and no reimplemented status/stock logic; import T2 helpers.
Open question risks:
  - Existing table row markup may require a local wrapper to place a detail row; preserve table semantics or report `NEEDS_CONTEXT` rather than invalid HTML.
Rollback note:
  - Revert page and new local detail files; shared Table.tsx remains unchanged.
Red flags:
  - Any edit to `apps/web/src/components/ui/Table.tsx` → STOP.

## STOP CONDITIONS
Done when: page/detail tests pass, ARIA and independent action behavior are observable, and Table.tsx is byte-for-byte unchanged.
Uncertain when: valid HTML requires a local table renderer that would duplicate shared behavior; escalate before changing shared Table.
Escalate when: expansion cannot be implemented locally without changing shared component behavior.

---

### Task 4: Server-side filter controls, state reset, summary, sorting, paging, and list retry [depends: T2, T3] [test-risk]

## OBJECTIVE
Add Products-local category/status/stock-health controls and wire them to `fetchAdminProducts`. Use AND semantics through the adapter, preserve the selected sort after filter changes, reset cursor to 0, close all expanded rows, and recompute summary from the filtered response. Render complete category metadata including `Tanpa kategori`; retain search/paging behavior; display a retryable error state for list timeout/database failures. Ensure filter controls do not change the legacy request when new values are absent.

Steps:
1. Write failing test for: combined filter URL, cursor reset, sort preservation, and expansion close
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration (rendered page + mocked HTTP boundary)
   Test intent: Given a selected status/category/stock filter, an active non-default sort, page cursor 15, and an expanded row / When a filter changes / Then the next request contains all filters with cursor 0 and existing sort/order, the expanded detail closes, the rendered summary comes from filtered `meta.summary`, category options are `Semua kategori` + all backend categories + `Tanpa kategori`, and selecting `Tanpa kategori` sends the explicit no-category request and returns only null/empty-category rows.
   Exercise through: Products page controls and fetch boundary
   Test doubles: mock global `fetch` responses; do not mock page state, adapter, or Table
   Expected RED: current page has no filter controls and filter changes cannot reset cursor/close expansion while preserving sort.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
3. Implement Select controls, filter state, reset/close orchestration, and summary/paging wiring → verify PASS → refactor while green → commit `feat(web): add server-side product filters`
4. Write failing test for: complete category/status/stock options and API values
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given backend `meta.categories` containing only distinct non-empty categories / When the category filter opens and each health/status option is selected / Then options are `Semua kategori` + all backend categories + frontend-added `Tanpa kategori`; selecting `Tanpa kategori` sends the explicit no-category request; status labels are `Semua/Aktif/Nonaktif/Tidak bisa dibeli`, health labels map to `out/low/ok`, and the request sends the backend values exactly.
   Exercise through: rendered Select controls and request URL
   Test doubles: mock fetch only
   Expected RED: no category/status/health controls or API value mapping exists.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
6. Implement metadata-driven category options, explicit filter labels, and API value mapping; keep invalid values out of client state → verify PASS → refactor while green → commit `feat(web): add product filter options`
7. Write failing test for: list failure and retry
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given `GET /products` rejects/times out / When the page loads / Then an alert/error state and `Coba lagi` action appear; when retry is activated / Then a new request is made and a successful response renders.
   Exercise through: page initial load and retry button
   Test doubles: mock/reject global fetch; do not mock error state or retry callback
   Expected RED: current error is plain text without retry action and page has no explicit retry path.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
9. Implement retryable list error state, loading-safe controls, legacy search behavior, and filter-change lifecycle cleanup → verify PASS → refactor while green → commit `feat(web): add product list retry state`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rules: Filter contract; Status filters overlap; Filtered summary and paging; Product list failure; Implementation Notes. Code: Products page/API, Select, TableSummary, TablePagination, existing page tests.
[CRITICAL: filters are server-side; filter/sort changes reset cursor and close expanded rows while sort remains selected]

## WHY THIS APPROACH
Complexity: standard
Justification: this task coordinates several page state machines (filters, cursor, sort, expansion, summary, and retry) across the adapter seam and must preserve existing pagination/search behavior.

## SANDWICH CONTEXT
[CRITICAL: never implement category/status/health filtering by slicing the current page; every filter request must reach the server adapter]
You are implementing the Products filter and list-state layer.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `apps/web/src/app/admin/products/page.tsx`, `api.ts`, and `page.test.tsx`; use T2 helper/types and T3 local expansion.
Available after: T2 and T3.
Architecture rule: AND semantics are expressed in request params; filter/sort changes reset cursor to 0, preserve sort, close expanded rows, and summary uses filtered server metadata.
[RESTATE: Filtering is server-side and state changes must reset cursor/close rows without losing sort.]

## DELIVERABLE
Given category/status/health selections / When the list reloads / Then all selected values are sent together, only matching server results render, cursor is 0, expanded rows are closed, and sort is preserved.
Given backend category metadata / When the category filter opens / Then distinct categories and Tanpa kategori are available.
Given stock options / When selected / Then Habis/Rendah/Aman map to out/low/ok.
Given filtered response summary / When rendered / Then `{total,out_of_stock}` is shown for the filtered set.
Given request timeout/failure / When retry is clicked / Then the list request runs again and success replaces the error state.
Given no new params / When legacy search/list loads / Then existing default request behavior remains.

## QUALITY BAR
Must-have:
  - No client-side filtering of paginated product rows.
  - Filter changes reset cursor to 0, close all expanded rows, and preserve selected sort/order.
  - Summary and `has_more` are taken from the filtered server response.
  - Retry action is keyboard accessible and does not duplicate requests while loading.
Must-not-have:
  - No changes to public catalog behavior, shared Table, or backend semantics in this frontend task.
Open question risks:
  - Existing response metadata may omit categories on legacy callers → treat missing metadata as empty options while preserving list rendering; report only if backend T1 contract cannot supply it.
Rollback note:
  - Revert page filter/state changes; adapter remains backward-compatible.
Red flags:
  - Filtering current rows instead of sending server params → STOP.

## STOP CONDITIONS
Done when: all page tests pass for filters/options/reset/summary/retry and existing sort/search/paging tests remain green.
Uncertain when: a filter interaction causes duplicate request races; add request identity/cleanup before proceeding.
Escalate when: satisfying filters requires changing shared Table or public catalog behavior.

---

### Task 5: Price history panel pagination, retry, abort, and stale-response guard [depends: T4] [test-risk]

## OBJECTIVE
Create a Products-local `PriceHistoryPanel` used inside expanded detail that imports T2's exported response/API helpers and display helpers instead of reimplementing detail logic. Load five latest entries on expansion, hide load-more for exactly five/zero entries, support offset-cursor load more with duplicate-request protection, show empty/error/retry states, and safely handle deleted-product 404. Thread an `AbortController` and request identity/token guard from page table-state changes (filter, sort, page) so in-flight history cannot paint a different row; close expanded rows and abort history when table state changes. Keep dummy history behavior at full parity.

Steps:
1. Write failing test for: five latest entries and load-more visibility
   Test file: `apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx`
   Level: integration (panel + mocked history adapter boundary)
   Test intent: Given 12 entries / When the panel opens / Then five latest entries render and `Muat lebih banyak` is available; given exactly five entries / Then five render and load-more is hidden; given zero / Then empty history appears and load-more is hidden.
   Exercise through: `PriceHistoryPanel` rendered component and supplied fetch callback
   Test doubles: mock callback response; do not mock panel state or pagination logic
   Expected RED: no local panel exists; current page renders all fetched entries in a side card and has no load-more control.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/PriceHistoryPanel.test.tsx --runInBand`
3. Implement five-entry initial page, empty state, and conditional offset-cursor load-more UI → verify PASS → refactor while green → commit `feat(web): add expandable price history panel`
4. Write failing test for: history failure, retry, and deleted product 404
   Test file: `apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx`
   Level: integration
   Test intent: Given history callback rejects with a normal error / When panel opens / Then clear error and `Coba lagi` appear; retry invokes one new request; given a 404/deleted-product error / Then a clear deleted-product message appears and the close callback is called safely.
   Exercise through: panel fetch/retry/close callbacks
   Test doubles: rejected promises and callback spies; do not mock error rendering or close state
   Expected RED: no panel retry/404 classification or safe close behavior exists.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/PriceHistoryPanel.test.tsx --runInBand`
6. Implement typed error classification, retry, and safe 404 close handling → verify PASS → refactor while green → commit `feat(web): add price history retry handling`
7. Write failing test for: duplicate load-more protection and stale response suppression
   Test file: `apps/web/src/app/admin/products/PriceHistoryPanel.test.tsx`
   Level: integration (async/concurrency seam)
   Test intent: Given more entries and a pending load-more request / When the button is clicked repeatedly / Then it is disabled and only one callback is processed; given a slower first-page request for product A that resolves after the panel has already switched to product B / Then A's rows never render in B; given request A resolves after the panel closed or unmounted / Then A's rows are ignored and no stale state is set.
   Exercise through: panel async public callbacks and request identity behavior
   Test doubles: deferred promises and AbortSignal-aware callback spies; do not mock the panel reducer/state under test
   Expected RED: current page has no in-flight guard, abort signal, or stale-response token.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/PriceHistoryPanel.test.tsx --runInBand`
9. Implement in-flight guard, request identity/token check, AbortController threading, and cleanup on unmount/product change → verify PASS → refactor while green → commit `fix(web): guard stale product price history responses`
10. Write failing test for: table state change aborts history and closes rows
    Test file: `apps/web/src/app/admin/products/page.test.tsx`
    Level: integration (Products page + adapter/network seam)
    Test intent: Given row A history is loading / When filter, sort, or page changes / Then expanded rows close, the request receives an aborted signal, and a late A response cannot render in another row; dummy mode follows the same visible lifecycle without network.
    Exercise through: rendered Products page state changes and mocked fetch signal
    Test doubles: deferred global fetch with an inspectable AbortSignal; do not mock page lifecycle or expansion state
    Expected RED: current page has no expanded-row lifecycle or abort cleanup when table state changes.
11. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
12. Integrate panel into ProductRowDetail/page and abort/reset history on filter/sort/page changes; preserve five-item offset contract and dummy parity → verify PASS → refactor while green → commit `feat(web): integrate safe expandable price history`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rule: Price history; Rules: Expanded detail, filter/sort changes, dummy parity. Code: existing `fetchPriceHistory`, `AdminProductController::prices`, page tests, dummy guards.
[CRITICAL: abort in-flight history and reject stale responses before any stale row state can render]

## WHY THIS APPROACH
Complexity: deep
Justification: this is a networked asynchronous UI seam with pagination, retry, 404 lifecycle, duplicate-click protection, AbortController cleanup, and cross-row stale-response hazards; a dedicated local panel keeps the concurrency logic testable without touching shared UI.

## SANDWICH CONTEXT
[CRITICAL: an aborted or stale history response must never paint another expanded row]
You are implementing Products-local price history behavior.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `PriceHistoryPanel.tsx`, its test, `api.ts`, `page.tsx`, `ProductRowDetail.tsx`, and mapped page tests.
Available after: T4 filter/state lifecycle.
Architecture rule: reuse existing offset cursor endpoint, no edit/rollback history, AbortController plus request identity guard, duplicate-load-more disabled, and dummy parity.
[RESTATE: Abort and stale-response guards are mandatory at the Products history boundary.]

## DELIVERABLE
Given 12 history rows / When expanding / Then five latest rows and load-more appear.
Given exactly five or zero rows / When expanding / Then load-more is hidden and the correct empty state appears.
Given a history failure / When displayed / Then retry is available; a 404 gives a clear deleted-product message and safe close.
Given load-more pending / When clicked repeatedly / Then exactly one request runs and button is disabled.
Given row A history pending / When filter, sort, page, product, or unmount state changes / Then the request is aborted/invalidated, rows close, and stale A data cannot paint another row.
Given dummy mode / When history is requested / Then no network is used and pagination/empty/error presentation remains equivalent.

## QUALITY BAR
Must-have:
  - Initial history page is five entries, using existing offset cursor response fields.
  - AbortController and a monotonically changing request identity/token both guard state updates.
  - Abort errors are silent; non-abort failures are retryable; 404 is explicit and closes safely.
  - Load-more cannot issue duplicate requests.
Must-not-have:
  - No changes to history edit/rollback semantics, endpoint shape, schema, or shared Table.
Open question risks:
  - Existing dummy history always has five rows → extend only the local dummy projection/test seam to exercise zero/more states, without changing global seed semantics.
Rollback note:
  - Revert local panel/page integration; existing side-history behavior can be restored without database changes.
Red flags:
  - A stale response updates shared `priceHistory` after product/state identity changes → STOP.

## STOP CONDITIONS
Done when: panel/page concurrency tests pass, signal abort is observed, no stale data paints, and dummy mode remains zero-network.
Uncertain when: browser abort behavior differs from Jest deferred signal behavior; preserve identity guard and report environment discrepancy.
Escalate when: satisfying the requirement requires changing the backend history endpoint or adding a dependency.

---

### Task 6: Cross-unit contract and regression verification [depends: T1, T2, T3, T4, T5] [test-risk]

## OBJECTIVE
Run final cross-unit verification at the real API and rendered frontend seams. Add focused contract assertions ensuring the backend JSON consumed by the adapter contains supplier/category/summary/sort/filter fields and that Products renders those fields, resets state, and preserves legacy defaults. Verify no shared Table or schema/cross-module files changed.

Steps:
1. Write failing test for: backend-to-frontend list contract compatibility
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given the same fixtures used by the frontend contract (category, status, supplier, null stock, history product) / When the real authenticated `GET /api/products?include_unpurchasable=1` endpoint is called with filters and `sort=status|category` / Then the response envelope and fields exactly satisfy the adapter's `AdminProduct`/meta expectations, including nested supplier, categories, filtered summary, cursor, and has_more; when the same request is repeated WITHOUT `include_unpurchasable` / Then non-active and orphan-supplier rows are excluded as legacy requires.
   Exercise through: real Laravel HTTP endpoint and JSON contract assertions
   Test doubles: none; real DB and auth fixtures
   Expected RED: T1–T5 may expose a mismatch between response serializer/query output and adapter assumptions.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
3. Add only the missing contract/regression assertions and fix the smallest in-scope mismatch in T1/T2 implementation; verify PASS → refactor while green → commit `test(api): verify product clarity response contract`
4. Write failing test for: frontend renders real contract fields and preserves legacy defaults
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration
   Test intent: Given a response shaped exactly like the backend contract / When the page loads with no new filter params / Then legacy default request fields remain present, new identity/detail fields render, and existing price edit, summary, sort, paging, and dummy tests remain green; when new sort/filter values are used / Then no silent fallback occurs.
   Exercise through: rendered Products page with only global fetch as the network double
   Test doubles: mock fetch response; do not mock adapter/page/helpers
   Expected RED: a field-name, allowlist, or legacy-default mismatch can make the page silently omit or fall back from the backend contract.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
6. Add only missing frontend contract assertions/fixes within mapped Products files; verify PASS → refactor while green → commit `test(web): verify product clarity page contract`
7. Write failing test for: out-of-scope regression guard
   Test file: `apps/api/tests/Feature/ProductTest.php`
   Level: integration
   Test intent: Given a legacy request with no new params and existing product/order-eligible fixtures / When the list is requested / Then default id ASC/limit 100 and existing eligibility behavior remain (non-active and orphan-supplier rows stay excluded without `include_unpurchasable`); no schema or cross-module semantic changes are required by the feature.
   Exercise through: legacy `GET /api/products` request and existing feature assertions
   Test doubles: none
   Expected RED: an accidental replacement of legacy defaults or eligibility behavior would be caught before handoff.
8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ProductTest`
9. Add regression assertions only where absent; verify PASS → refactor while green → commit `test(api): lock legacy product list behavior`
10. Run full verification without changing implementation:
    - `cd apps/api && php artisan test --filter=ProductTest`
    - `cd apps/web && npx jest src/app/admin/products --runInBand`
    - `cd apps/web && npx tsc --noEmit`
    - inspect `git diff -- apps/web/src/components/ui/Table.tsx apps/api/database` and confirm no changes
11. Commit any test-only finalization as `test(products): complete Barang menu clarity verification` (if no file changed, record the existing task commits and do not create an empty commit).

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — all acceptance criteria, admin/public eligibility scenarios, and rollback/out-of-scope sections. Code: all T1–T5 mapped files, existing product/API/UI tests, routes, factories, and shared Table.
[CRITICAL: final verification must prove the real backend/frontend contract while protecting legacy defaults and shared-table boundaries]

## WHY THIS APPROACH
Complexity: standard review
Justification: the feature crosses Laravel JSON, a TypeScript adapter, dummy mode, page state, and local detail components; focused contract checks catch drift that isolated unit tests cannot.

## SANDWICH CONTEXT
[CRITICAL: this task may add assertions or smallest in-scope fixes only; it may not expand the feature or touch shared Table/schema/cross-module semantics]
You are performing final cross-unit verification for Barang Menu Clarity.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: mapped ProductTest/page test and, only if proven necessary, the T1–T5 mapped implementation files.
Available after: T1 through T5.
Architecture rule: real API and rendered page boundaries are tested; no implementation shortcut may bypass adapter, RBAC, dummy, or legacy compatibility.
[RESTATE: Verify the contract without broadening scope or modifying shared Table/schema/cross-module semantics.]

## DELIVERABLE
Given backend fixtures and clarity query params / When real `GET /api/products` runs / Then the frontend-consumed envelope, supplier, metadata, summary, filters, and sorts match.
Given the backend-shaped response / When Products renders / Then all identity/detail/filter/error behaviors and legacy defaults remain observable.
Given legacy request and existing eligibility fixtures / When `/products` is called without new params / Then id ASC/default limit 100 and legacy behavior remain.
Given the complete diff / When scope is inspected / Then shared Table, database schema, and cross-module price/stock/order files are untouched.

## QUALITY BAR
Must-have:
  - Backend ProductTest, frontend Products tests, and TypeScript checks pass.
  - Contract checks cross the real HTTP/adapter/render seams rather than mocking the unit under test.
  - Any implementation fix remains within the existing T1–T5 file map.
Must-not-have:
  - No new feature work, schema migration, shared Table change, or cross-module semantic change.
Open question risks:
  - If the repository's full suite exposes unrelated baseline failures, isolate them by command and report exact failures rather than weakening assertions.
Rollback note:
  - Revert test-only assertions or the smallest in-scope correction; no database rollback.
Red flags:
  - Any out-of-scope diff or silently falling-back sort allowlist → STOP and report.

## STOP CONDITIONS
Done when: contract/regression tests and listed verification commands pass, and scope inspection confirms all constraints.
Uncertain when: unrelated baseline tests fail; report exact command/output and keep task open.
Escalate when: backend/frontend contract cannot be reconciled without schema or out-of-scope semantic changes.

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Backend product list contract, filters, summary, supplier, and sorting | prereq | deep | Real ProductTest covers eager supplier, AND filters, invalid queries, COALESCE summary, derived category/status sort, and legacy defaults |
| T2 | Frontend product contract, clarity helpers, sort mirror, and dummy parity | T1 | standard | Pure stock/status helper boundaries plus real/dummy adapter URL/meta/zero-network parity |
| T3 | Local expandable Products identity/detail row and accessibility | T2 | standard | Rendered identity/detail fields, keyboard Enter/Space, ARIA, and independent Ubah harga action |
| T4 | Server-side filter controls, state reset, summary, sorting, paging, and list retry | T2, T3 | standard | Filter URL AND semantics, metadata options, cursor reset, expansion close, summary, retry |
| T5 | Price history panel pagination, retry, abort, and stale-response guard | T4 | deep | Five-item offset history, empty/error/404/retry, duplicate-load guard, AbortController and stale token |
| T6 | Cross-unit contract and regression verification | T1–T5 | standard review | Real API/frontend contract, legacy defaults, full targeted tests, TypeScript, and scope guard |

**Test strategy audit:** trigger fired for T1/T2/T4/T5/T6 due to persistence, networking, concurrency, and cross-unit API/UI behavior; review required before approval.
