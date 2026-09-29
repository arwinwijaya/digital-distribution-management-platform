# Task T6 — Cross-unit contract and regression verification

**Phase:** 1
**Depends:** T1, T2, T3, T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
