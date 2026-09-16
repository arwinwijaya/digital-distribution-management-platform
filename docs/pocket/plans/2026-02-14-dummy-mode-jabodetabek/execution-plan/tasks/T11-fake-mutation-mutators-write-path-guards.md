# Task T11 — Fake mutation mutators + write-path guards

**Phase:** 2
**Depends:** T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


## OBJECTIVE
Add write-path fake mutations to the dummy store — order creation with relational side-effects, delivery status with proof, product price, promotions, user role, outlet, PLUS previously-missed writes: `payments/page.tsx` POST, `OrderForm.tsx` POST, `sales/page.tsx` POST visits, and `sendFunnelEvent` — plus a `mutations.ts` module of pure mutator functions and the write guards at every POST/PATCH call-site — zero network while ON, `dummy-`/negative IDs, ephemeral.

Files:
- Create: `apps/web/src/dummy/mutations.ts`
- Test: `apps/web/src/dummy/mutations.test.ts`
- Modify: `apps/web/src/app/sales/orders/api.ts` (`createSalesOrder` write path)
- Modify: `apps/web/src/app/admin/products/api.ts` (`updateProductPrice`)
- Modify: `apps/web/src/app/admin/promotions/api.ts` (`createPromotion`, `updatePromotion`, `deletePromotion`, `broadcastPromotion`)
- Modify: `apps/web/src/app/admin/users/api.ts` (`assignUserRole`)
- Modify: `apps/web/src/app/admin/outlets/api.ts` (`updateOutlet`)
- Modify: `apps/web/src/app/delivery/page.tsx` (PATCH status call-site)
- Modify: `apps/web/src/app/payments/page.tsx` (POST payment call-site :173)
- Modify: `apps/web/src/components/OrderForm.tsx` (POST order call-site :28)
- Modify: `apps/web/src/app/sales/page.tsx` (POST visit call-site :51)
- Modify: `apps/web/src/lib/data-intelligence-api.ts` (`sendFunnelEvent` POST only — reads are T8's)

Steps:
1. Write failing test for: order creation applies relational side-effects with prefixed IDs
   Test file: `apps/web/src/dummy/mutations.ts` → `apps/web/src/dummy/mutations.test.ts`
   Level: unit

   Test intent:
   Given store ON with T6 entities loaded
   When `createDummyOrder({ outlet_id, items })` runs
   Then:
   - a new order exists with a `dummy-` prefixed string id (and/or negative numeric id) that collides with no existing id
   - exactly one new payment, one new invoice, and one new delivery exist, each referencing the new order id
   - the new order appears in the orders list returned by subsequent reads
   - orders count increased by exactly 1

   Exercise through:
   - `createDummyOrder` from `apps/web/src/dummy/mutations.ts` and the store read selectors

   Test doubles:
   - mock/fake: global fetch (assert never called)
   - do NOT mock: mutations module, store

   Expected RED:
   - file does not exist → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/mutations.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/mutations'`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/mutations.ts` — pure-ish mutators that take the current entities and return the next entities (plus store-bound wrappers): `createDummyOrder`, `updateDummyDelivery(deliveryId, status, proof?)`, `updateDummyProductPrice`, `createDummyPromotion` / `updateDummyPromotion` / `deleteDummyPromotion` / `broadcastDummyPromotion`, `assignDummyUserRole`, `updateDummyOutlet`, `sendDummyFunnelEvent` (T8's read counterpart left unguarded here — THIS task owns the funnel POST), `createDummyPayment` (for `payments/page.tsx:173`), `createDummyVisit` (for `sales/page.tsx:51`), `createDummyOutletOrder` (for `OrderForm.tsx:28`; wraps the same 1:1 order → payment/invoice/delivery linkage). IDs: string prefixed `dummy-`; numeric → negative counter.

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/mutations.test.ts --runInBand`
   Expected: PASS

5. Write failing test for: write guards stop network and mutations stay ephemeral
   Test file: `apps/web/src/dummy/mutations.test.ts` (append)
   Level: integration

   Test intent:
   Given store ON with a jest.fn() global fetch and `ddp_token` seeded
   When the following are ALL invoked while ON (single test, sequential awaits):
   `createSalesOrder(token, payload)` (sales/orders/api.ts), `updateProductPrice` (admin/products/api.ts),
   `createPromotion/updatePromotion/deletePromotion/broadcastPromotion` (admin/promotions/api.ts; 4 calls),
   `assignUserRole` (admin/users/api.ts), `updateOutlet` (admin/outlets/api.ts),
   the delivery PATCH path (delivery/page.tsx), `sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 })` (data-intelligence-api.ts — real signature `FunnelEventType = 'displayed'|'clicked'|'cart'`, NOT `(token,payload)`),
   the payments POST path (payments/page.tsx:173), the sales POST visits path (sales/page.tsx:51), and the OrderForm POST (OrderForm.tsx:28)
   Then:
   - each resolves to a fake success matching its real response shape (including `FunnelEventPayload { event_uuid, event_type: 'clicked', outlet_id: 1, product_id: 2 }` for the funnel call)
   - global fetch was NEVER called ONCE across ALL of them (`expect(fetch).not.toHaveBeenCalled()`) — a single cross-writes zero-fetch assertion
   Given store OFF, When `createSalesOrder(token, payload)` is invoked, Then global fetch IS called with `POST` (no regression)
   Given those mutations were made, When `toggle()` OFF then ON (or `resetEntities()`), Then the real/dummy baseline contains none of the created `dummy-` records

   Exercise through:
   - every write guard listed above (read exports + inline POST/PATCH call-sites via their modules/pages) + store `toggle()`

   Test doubles:
   - mock/fake: global fetch; localStorage token; dummy generator stub
   - do NOT mock: mutations module, store, api modules

   Expected RED:
   - `createSalesOrder` POSTs to the backend while ON → fetch recorded

6. Run test — verify FAIL, then implement (wire the guard into all write call-sites; delivery page PATCH becomes `updateDummyDelivery` when ON), then verify PASS:
   `cd apps/web && npx jest src/dummy/mutations.test.ts --runInBand`

7. Run the full web suite to prove no write-path regressions:
   `cd apps/web && npx jest --runInBand`
   Expected: all pre-existing tests still PASS

8. Refactor while green (bounded) + re-run (must stay PASS).

9. Commit (mutations module first, then the call-site wiring, so the shared helper lands before its consumers):
   `git add apps/web/src/dummy/mutations.ts apps/web/src/dummy/mutations.test.ts`
   `git commit -m "feat(dummy): add fake mutation mutators with relational side-effects"`
   `git add apps/web/src/app/sales/orders/api.ts apps/web/src/app/admin/products/api.ts apps/web/src/app/admin/promotions/api.ts apps/web/src/app/admin/users/api.ts apps/web/src/app/admin/outlets/api.ts apps/web/src/app/delivery/page.tsx apps/web/src/app/payments/page.tsx apps/web/src/components/OrderForm.tsx apps/web/src/app/sales/page.tsx apps/web/src/lib/data-intelligence-api.ts`
   `git commit -m "feat(dummy): guard write call-sites to fake mutations"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 3 R1 (POST/PATCH to dummy store only, zero network, identical success UI), R2 (`dummy-` prefix / negative ints, no collisions), R3 (relational side-effects: order → payment/invoice/delivery; delivered status), R4 (ephemeral), R5 (tracking faked), R6 (dummy op-issue ids non-navigable).
Preflight: write functions found — `createSalesOrder` (`sales/orders/api.ts`), `updateProductPrice` (`admin/products/api.ts`), `createPromotion`/`updatePromotion`/`deletePromotion`/`broadcastPromotion` (`admin/promotions/api.ts`), `assignUserRole` (`admin/users/api.ts`), `updateOutlet` (`admin/outlets/api.ts`), `sendFunnelEvent` (`lib/data-intelligence-api.ts:196`), delivery PATCH inline in `app/delivery/page.tsx`, `payments/page.tsx:173` POST, `components/OrderForm.tsx:28` POST, `sales/page.tsx:51` POST `/sales/visits`.

## WHY THIS APPROACH
Justification: Mutators (shared helper, own tests) + call-site wiring are one deliverable: the wiring cannot pass without the mutators, and the mutators are useless unwired. Two commits keep the helper reviewable before its consumers. Rule of three applies — eight call-sites share one guard pattern.
Complexity: deep

## SANDWICH CONTEXT
[CRITICAL: While Dummy is ON, a write must NEVER reach the backend — no POST/PATCH under any circumstance]
You are implementing fake write mutations for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/mutations.ts`, `apps/web/src/dummy/mutations.test.ts`, and the ten listed call-site files (`sales/orders/api.ts`, `admin/products/api.ts`, `admin/promotions/api.ts`, `admin/users/api.ts`, `admin/outlets/api.ts`, `delivery/page.tsx`, `payments/page.tsx`, `components/OrderForm.tsx`, `sales/page.tsx`, `lib/data-intelligence-api.ts`) — no other files
Available after: Phase A T6 + T7 complete
Architecture rule: New IDs must never collide with real auto-increment ids (`dummy-` prefix for strings, negative integers for numbers). Mutations are in-memory only — never persisted. Success UI must be identical to the real path (same resolved values, same absence of errors).
[RESTATE: While Dummy is ON, a write must NEVER reach the backend — no POST/PATCH under any circumstance]

## DELIVERABLE
Given store ON at /orders, When submitting an order, Then it appears with a negative integer `id` AND a `dummy-`-prefixed `order_id` string (spec: numeric ids → negative, string ids → `dummy-` prefix; e.g. order `id: -1`, `order_id: 'dummy-ORD-001'`) plus one linked payment/invoice/delivery each, AND zero fetch
Given store ON at /orders (OrderForm path), When the order form submits, Then the same 1:1 linkage holds AND zero fetch to `/orders` or `/products` (catalog read was guarded in T10)
Given store ON at /payments, When creating a payment, Then it records locally AND zero fetch
Given store ON at /sales, When creating a visit, Then it records locally AND zero fetch
Given store ON at /delivery, When marking delivered with proof fields, Then status updates locally AND no PATCH fetch
Given store ON with mutations made, When toggle OFF then ON, Then no `dummy-` record is present
Given store ON, When sendFunnelEvent runs, Then fake success AND zero fetch
Given store OFF, When a write function is invoked, Then the real POST/PATCH is sent (no regression)
Scope note — admin/orders — NOT in-scope: spec Scope/In-Scope enumerates Dasbor, Invoice, Pesanan (sales orders), Produk, Outlet, Marketplace, Pembayaran, Pengiriman, Sales, Analitik, Data Intelligence, Operasi — it does NOT list `admin/orders` (`/admin/orders` + `PUT /orders/:id/approve`). Leave those hits on the backend (no guard). Confirm with the user in Phase C before guarding.

All tests PASS. Commit exists with messages matching `feat(dummy): add fake mutation mutators with relational side-effects` and `feat(dummy): guard write call-sites to fake mutations`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Zero network on every write path while ON (verified by fetch-never-called assertions) INCLUDING payments POST, OrderForm POST, sales-visit POST, and sendFunnelEvent
  - 1:1 side-effects on order creation from BOTH order entry points (`createSalesOrder` and `OrderForm`)
  - ID collision safety (`dummy-` / negative)
  - Ephemerality verified by toggle cycle
  - Whole `npx jest` suite green (write-path regression guard)
  - Tests written BEFORE implementation (TDD — not after)
  - Commit messages follow conventional commits format

Must-not-have:
  - Persisting mutations to localStorage
  - Guarding read functions or page loaders (T9/T10 own those)
  - Backend changes; new dependencies

Open question risks:
  - Dummy operations issue detail is non-navigable by design → if the operations page links by id, route to a client-side no-op; do not add backend calls

Rollback note:
  - Delete `mutations.ts` + test; revert the ten call-site edits. Pages fall back to real writes.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, both commits created
Uncertain when: a write call-site's returned shape cannot be faked identically to the real response (flag NEEDS_CONTEXT)
Escalate when: out-of-scope files are touched or a read path is guarded here

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T8 | Guard DI + operations APIs | Phase A T6/T7 | standard | Dummy aggregates returned, zero fetch; `FetchResult` envelope preserved |
| T9 | Guard admin/* + sales/* reads | Phase A T6/T7 | standard | Shape parity + filters applied; zero fetch |
| T10 | Consolidate + guard inline pages | Phase A T6/T7 | deep | Analytics non-empty, no empty-state text, auto re-fetch on OFF, invoices + OrderForm catalog guarded |
| T11 | Fake mutations + write guards | Phase A T6/T7 | deep | `dummy-` ids, 1:1 side-effects, ephemeral, zero network across 10 write call-sites |
