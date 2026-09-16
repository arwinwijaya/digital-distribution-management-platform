# Task T10 — Consolidate and guard inline-fetch pages

**Phase:** 2
**Depends:** T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


## OBJECTIVE
Extract the inline `fetch(apiUrl(...))` calls in pages/components into per-page `api.ts` modules with typed helpers, then guard those helpers with `withDummyRead` and wire `useDummyRefresh` so pages re-fetch real data automatically when the toggle goes OFF. Covers dashboard, analytics, payments (list), delivery (list), INVOICES (page.tsx:47 inline read), operations (read path), sales (read path), ProductCatalog, MarketplaceCatalog, and OrderForm (products catalog read at :16 — POST order write stays in T11).

Files:
- Create: `apps/web/src/app/dashboard/api.ts`
- Create: `apps/web/src/app/analytics/api.ts`
- Create: `apps/web/src/app/payments/api.ts`
- Create: `apps/web/src/app/delivery/api.ts`
- Create: `apps/web/src/app/invoices/api.ts`
- Modify: `apps/web/src/app/dashboard/page.tsx`
- Modify: `apps/web/src/app/analytics/page.tsx`
- Modify: `apps/web/src/app/payments/page.tsx`
- Modify: `apps/web/src/app/delivery/page.tsx`
- Modify: `apps/web/src/app/invoices/page.tsx`
- Modify: `apps/web/src/app/operations/page.tsx`
- Modify: `apps/web/src/app/sales/page.tsx`
- Modify: `apps/web/src/components/ProductCatalog.tsx`
- Modify: `apps/web/src/components/MarketplaceCatalog.tsx`
- Modify: `apps/web/src/components/OrderForm.tsx`
- Test: `apps/web/src/app/dashboard/dummy-guard.test.tsx`

Steps:
1. Write failing test for: dashboard analytics populated while ON; real re-fetch on toggle OFF
   Test file: `apps/web/src/app/dashboard/dummy-guard.test.tsx`
   Level: integration

   Test intent:
   Given the store ON with T6 aggregates and jest.fn() global fetch
   When the dashboard loader runs
   Then:
   - metrics/trends/outlet-performance are populated with dummy values
   - global fetch was NEVER called
   Given the store ON while rendered, When `toggle()` is called OFF
   Then:
   - `useDummyRefresh` fires and the loader re-runs against real fetch (fetch IS called now)

   Exercise through:
   - the page-level loader helper (public entry point of `apps/web/src/app/dashboard/api.ts`) and the `useDummyRefresh` subscription — not the component internals

   Test doubles:
   - mock/fake: global fetch (assert not-called when ON, called after OFF); dummy generator stub
   - do NOT mock: the api helper, guards, store

   Expected RED:
   - page still fetches inline on mount → fetch recorded while ON

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`
   Expected failure: `Expected fetch not to have been called`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/app/dashboard/api.ts` (new) — extract `loadDashboard(token, role, startDate?, endDate?)` and the finance variant out of `page.tsx`, preserving the existing role-branching and `jakartaDateString` offsets; guard each branch with `withDummyRead` returning T6 `DashboardData` / `FinanceMetrics`.
   File: `apps/web/src/app/dashboard/page.tsx` — import the loader; add `useDummyRefresh(() => token && load(...))` so toggle OFF re-fetches; keep all rendering untouched.
   File: `apps/web/src/app/analytics/api.ts` (new) — extract the 3-way `Promise.all` (recommendations/forecast/segmentation) into `loadAnalytics(token)`, guarded to return T6 `AIData`.
   File: `apps/web/src/app/payments/api.ts` (new) — extract list loader; guard list read ONLY (POST payment create is T11).
   File: `apps/web/src/app/delivery/api.ts` (new) — extract list loader; guard list read ONLY (PATCH status is T11).
   File: `apps/web/src/app/invoices/api.ts` (new) — extract `page.tsx:47` invoices list loader; guard with `withDummyRead` returning T6-linked invoice rows (derived from tx, never hardcoded).
   File: `apps/web/src/app/analytics/page.tsx`, `payments/page.tsx`, `delivery/page.tsx`, `invoices/page.tsx`, `operations/page.tsx`, `sales/page.tsx`, `components/ProductCatalog.tsx`, `components/MarketplaceCatalog.tsx`, `components/OrderForm.tsx` (reads at :16 + :36) — same extract-then-guard pattern; add `useDummyRefresh` to each page that owns a loader.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`
   Expected: PASS

5. Write failing test for: analytics non-empty + no empty-state text while ON
   Test file: `apps/web/src/app/dashboard/dummy-guard.test.tsx` (append — analytics case)
   Level: integration

   Test intent:
   Given the store ON as admin
   When `loadAnalytics('token')` runs
   Then:
   - recommendations length > 0 AND forecast.predictions length === 4 AND segmentation.segments length > 0
   - global fetch was NEVER called
   And when the analytics page renders with that data, `Belum ada rekomendasi` and `Data belum cukup` are NOT present in the DOM

   Exercise through:
   - `loadAnalytics` + rendering `apps/web/src/app/analytics/page.tsx` with the dummy payload

   Test doubles:
   - mock/fake: global fetch; localStorage token
   - do NOT mock: analytics page, loader, guards, store

   Expected RED:
   - loader unimplemented / empty arrays → length assertions or DOM text assertion fails

6. Run test — verify FAIL, then implement, then verify PASS:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`

6b. Write failing test for: EVERY extracted page loader returns dummy with zero network (zero-fetch sweep)
   Test file: `apps/web/src/app/dashboard/dummy-guard.test.tsx` (append — loader sweep)
   Level: integration

   Test intent:
   Given the store ON with T6 aggregates, `ddp_token` seeded, and global fetch mocked
   When ALL of the following are awaited in one test (LOCKED exported names — T10 must export exactly these):
   `loadInvoices` (invoices/api.ts), `loadDeliveries` (delivery/api.ts), `loadPaymentsList` (payments/api.ts),
   `loadOperations` (operations/api.ts), `loadSalesList` (sales/api.ts), `loadProductCatalog` (ProductCatalog),
   `loadMarketplaceCatalog` (MarketplaceCatalog), `loadOrderFormProducts` + `trackOrder` (OrderForm)
   Then:
   - each resolves to a non-empty, correctly-shaped payload (invoices array; deliveries array; payments list; operations issues; sales visits; products catalog; marketplace suppliers+products; OrderForm catalog; tracked order or a documented null stub for `trackOrder`)
   - global fetch was NEVER called ONCE across all calls (`expect(fetch).not.toHaveBeenCalled()`) — a single cross-loader zero-fetch assertion

   This sweep closes the loader coverage gap — T10 extracts 10 loaders but steps 1–5 only assert dashboard + analytics, and `ProductCatalog`/`MarketplaceCatalog` were previously unnamed. NOTE (audit item): parts of this sweep may already pass via the guards imported from T7; if so, mark the file's header as a regression/characterization guard and move on (do not stall waiting for RED). `loadAnalytics` is covered by step 5, not repeated here.

6c. Run test — verify FAIL (or PASS-if-guarded-already, annotated as regression), then implement, then verify PASS:
   `cd apps/web && npx jest src/app/dashboard/dummy-guard.test.tsx --runInBand`

7. Run the full web suite to prove no page regressions:
   `cd apps/web && npx jest --runInBand`
   Expected: all pre-existing tests still PASS

8. Refactor while green (bounded) + re-run (must stay PASS).

9. Commit:
   `git add apps/web/src/app/dashboard/api.ts apps/web/src/app/analytics/api.ts apps/web/src/app/payments/api.ts apps/web/src/app/delivery/api.ts apps/web/src/app/invoices/api.ts apps/web/src/app/dashboard/page.tsx apps/web/src/app/analytics/page.tsx apps/web/src/app/payments/page.tsx apps/web/src/app/delivery/page.tsx apps/web/src/app/invoices/page.tsx apps/web/src/app/operations/page.tsx apps/web/src/app/sales/page.tsx apps/web/src/components/ProductCatalog.tsx apps/web/src/components/MarketplaceCatalog.tsx apps/web/src/components/OrderForm.tsx apps/web/src/app/dashboard/dummy-guard.test.tsx`
   `git commit -m "feat(dummy): consolidate page data loaders and add dummy guards"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R1 (inline-fetch pages consolidated to helpers with the same guard), R3 (analytics populated; empty states must NOT show), R5 (BOTH dashboard shapes); Story 1 (toggle OFF auto re-fetch, no navigation).
Preflight: `dashboard/page.tsx` has a ~40-line `loadForRole` with role branching + `jakartaDateString` offsets; `analytics/page.tsx` does a 3-way `Promise.all` inline; `payments/page.tsx` has `usePaymentData` (list at :46, POST at :173 → T11); `delivery/page.tsx` fetches inline twice (list + PATCH status → PATCH is T11); `invoices/page.tsx:47` has an inline list read (was MISSED in first draft — added after spec review); `operations/page.tsx` + `sales/page.tsx` (list :28 reads, POST :51 → T11) + `ProductCatalog.tsx` + `MarketplaceCatalog.tsx` each have 1 inline read; `components/OrderForm.tsx` has THREE hits: products catalog read :16, POST :28 (T11), tracking `GET /orders/:id` :36 (T10 — was MISSED in the first review pass).

## WHY THIS APPROACH
Justification: Rule 4 split — this is a distinct layer from T8/T9 (page loaders, not module wrappers) with its own verification (DOM empty-state assertions + auto re-fetch). Integration level is mandatory because the GWT spans loader→store→render.
Complexity: deep

## SANDWICH CONTEXT
[CRITICAL: Rendering components must NOT change — only the data source moves behind a guarded loader]
You are consolidating inline-fetch pages for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: the four new `api.ts` files, the eight listed page/component files, and `apps/web/src/app/dashboard/dummy-guard.test.tsx` — no other files
Available after: Phase A T6 + T7 complete
Architecture rule: Preserve every existing render path, role branch, and date-offset helper exactly; the only behavioral change is where data comes from. `useDummyRefresh` must trigger the real re-fetch on toggle OFF without requiring navigation.
[RESTATE: Rendering components must NOT change — only the data source moves behind a guarded loader]

## DELIVERABLE
Given store ON, When dashboard/analytics loaders run, Then populated dummy data AND zero fetch
Given store ON as admin, When analytics renders, Then recommendations non-empty AND forecast 4 periods AND segmentation non-empty AND neither empty-state string appears
Given store ON as finance, When the dashboard loader runs, Then FinanceMetrics-shaped data is returned
Given store ON, When EVERY extracted loader (invoices, operations, sales, payments, delivery, ProductCatalog, MarketplaceCatalog, OrderForm catalog + tracking) runs, Then non-empty correctly-shaped dummy AND zero fetch across all of them (single sweep assertion)
Given store ON while rendered, When toggle goes OFF, Then the loader re-runs against real fetch with no navigation
Given store OFF, When the full web suite runs, Then all pre-existing tests still PASS

All tests PASS. Commit exists with message matching `feat(dummy): consolidate page data loaders and add dummy guards`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Loaders extracted into `api.ts` before guarding (not guarded inline in JSX)
  - `useDummyRefresh` wired on every page that owns a loader (auto re-fetch on OFF)
  - Empty-state strings absent while ON
  - Zero-fetch sweep across ALL 10 extracted loaders (incl. ProductCatalog + MarketplaceCatalog + OrderForm tracking) — not just dashboard/analytics
  - Whole `npx jest` suite green (regression guard)
  - `invoices/page.tsx` inline read is extracted and guarded (spec in-scope: Invoice menu)
  - `OrderForm.tsx` product-catalog read (:16) AND tracking read (:36) are guarded (POST :28 stays T11)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Changing rendered markup/components (only the data source)
  - Guarding write calls (T11)
  - Backend changes; new dependencies

Open question risks:
  - `delivery/page.tsx` PATCH is a write → leave it for T11 and guard only the list read here
  - Some pages may use local non-exported types → mirror them minimally in the new `api.ts` and note it in the commit body

Rollback note:
  - Revert the page edits and delete the new `api.ts` files; delete the test.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, commit created
Uncertain when: a page's role branching cannot be preserved without touching markup (flag NEEDS_CONTEXT)
Escalate when: out-of-scope files are touched or a write path is guarded here
