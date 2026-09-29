# Task T2 — Frontend product contract, clarity helpers, sort mirror, and dummy parity

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
