# Task T3 — Add data-testid contract to critical pages

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Add data-testid contract to critical pages [prereq]

## OBJECTIVE
Add minimal `data-testid` attributes to the 5 critical UI surfaces so Playwright can select deterministically; no logic/handler changes.

Steps:
1. Write failing test for: Login form testids exist
   Test file: `apps/web/src/components/LoginForm.testid.spec.tsx`
   Level: unit (component render)
   Test intent: Given LoginForm renders / When querying by testId / Then `login-email`, `login-password`, `login-submit`, `login-error` (if error prop) are present
   Exercise through: Render LoginForm with @testing-library/react
   Test doubles: None (render real component; mock fetch if needed but not the component)
   Expected RED: `getByTestId('login-email')` throws — attribute not yet present
2. Run test — verify FAIL: `npx --prefix apps/web jest src/components/LoginForm.testid.spec.tsx -t "login testid" --runInBand`
3. Implement: Add `data-testid="login-email"` to email Input, `login-password` to password Input, `login-submit` to Button, `login-error` to error `<p role="alert">`. Commit after green (see sub-steps below, but as one task commit, keep atomic — reviewer expects one commit per task; intermediate greens not committed separately).
4. Write failing test for: OrderForm submit testids
   Test file: `apps/web/src/components/OrderForm.testid.spec.tsx`
   Level: unit
   Test intent: Given OrderForm renders with products / When querying / Then `order-submit`, `order-success`, `order-track-input`, `order-track-submit` present
   Exercise through: Render OrderForm with mocked token/products
   Test doubles: Mock `loadOrderFormProducts` / fetch; do NOT mock OrderForm itself
   Expected RED: `getByTestId('order-submit')` throws
5. Run test — verify FAIL: `npx --prefix apps/web jest src/components/OrderForm.testid.spec.tsx -t "order testid" --runInBand`
6. Write failing test for: Admin approve testids
   Test file: `apps/web/src/app/admin/orders/page.testid.spec.tsx`
   Level: unit
   Test intent: Given AdminOrdersPage renders with mocked orders / When querying / Then `admin-orders-table`, `admin-order-approve-*`, `admin-order-detail` present
   Exercise through: Render page with mocked fetch for /admin/orders
   Test doubles: Mock fetch; do NOT mock page component
   Expected RED: `getByTestId('admin-orders-table')` throws
7. Run test — verify FAIL: `npx --prefix apps/web jest src/app/admin/orders/page.testid.spec.tsx -t "admin orders testid" --runInBand`
8. Write failing test for: Delivery action testids
   Test file: `apps/web/src/app/delivery/page.testid.spec.tsx`
   Level: unit
   Test intent: Given delivery page renders with deliveries / When querying / Then `delivery-start-*`, `delivery-recipient-*`, `delivery-proof-url-*`, `delivery-complete-*` present
   Exercise through: Render delivery page
   Test doubles: Mock `loadDeliveries` / fetch
   Expected RED: `getByTestId('delivery-start-1')` throws
9. Run test — verify FAIL: `npx --prefix apps/web jest src/app/delivery/page.testid.spec.tsx -t "delivery testid" --runInBand`
10. Write failing test for: Payments form testids
   Test file: `apps/web/src/app/payments/page.testid.spec.tsx`
   Level: unit
   Test intent: Given payments page renders / When querying / Then `payment-order-id`, `payment-amount`, `payment-submit`, `payment-success` present
   Exercise through: Render payments page
   Test doubles: Mock `loadPaymentsList`
   Expected RED: `getByTestId('payment-order-id')` throws
11. Run test — verify FAIL: `npx --prefix apps/web jest src/app/payments/page.testid.spec.tsx -t "payment testid" --runInBand`
12. Implement all remaining testids in one pass: OrderForm (`order-submit` on Button "Kirim pesanan", `order-success` on success div, `order-track-input`/`order-track-submit` on track form), admin/orders (`admin-orders-table`, `admin-order-approve-{id}`, `admin-order-detail`, `admin-orders-error`), delivery (`delivery-start-{id}`, `delivery-recipient-{id}`, `delivery-proof-url-{id}`, `delivery-complete-{id}`), payments (`payment-order-id`, `payment-amount`, `payment-submit`, `payment-success`), orders page tracking (`order-status-badge`, `order-toast` where applicable). Verify all PASS: `npx --prefix apps/web jest src/components/*.testid.spec.tsx src/app/admin/orders/page.testid.spec.tsx src/app/delivery/page.testid.spec.tsx src/app/payments/page.testid.spec.tsx --runInBand` → refactor while green (keep attribute naming consistent kebab-case) → Commit: `git commit -m "feat(web): add data-testid contract for e2e critical path"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — In-Scope data-testid, Spec Context (LoginForm, OrderForm, admin/orders, delivery, payments), Implementation Notes (tambah data-testid saja)

## WHY THIS APPROACH
Complexity: standard
Justification: Touches 6 files across 5 routes; requires judgment on stable selector names without altering handlers; 5 RED cycles keep each surface independently provable.

## SANDWICH CONTEXT
[CRITICAL: Must NOT alter logic/handlers — only add data-testid attributes]
You are implementing data-testid contract for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Option B Hybrid — Playwright thin UI locators deterministic
Files in scope: apps/web/src/components/LoginForm.tsx, apps/web/src/components/OrderForm.tsx, apps/web/src/app/admin/orders/page.tsx, apps/web/src/app/delivery/page.tsx, apps/web/src/app/payments/page.tsx, apps/web/src/app/orders/page.tsx
Available after: none (prereq)
Architecture rule: Add attributes only; do not refactor handlers, state, or styling; keep isDummy guards intact
[RESTATE: Must NOT alter logic/handlers — only add data-testid]

## DELIVERABLE
Given LoginForm renders, When queried by data-testid, Then login-email/password/submit exist
Given OrderForm renders, When queried, Then order-submit/success/track exist
Given admin orders page renders, When queried, Then admin-orders-table/approve/detail exist
Given delivery page renders, When queried, Then delivery-start/recipient/proof-url/complete exist
Given payments page renders, When queried, Then payment-order-id/amount/submit/success exist

## QUALITY BAR
Must-have:
  - All ~15-20 data-testid present as listed in File Structure Map
  - No handler/logic change (diff is only JSX attribute additions)
  - Existing tests still pass

Must-not-have:
  - Changes to apps/api/**
  - Logic refactor or style overhaul
  - Removing isDummy guards

Open question risks:
  - Final list ~15-20 assumed → if wrong: report NEEDS_CONTEXT with actual list, do not block

Rollback note:
  - Revert attribute additions (inert, safe to keep)

Red flags:
  - Handler code changed → DONE_WITH_CONCERNS
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: All 5 test suites PASS and attributes present in source
Uncertain when: Component test setup requires heavy mocking → NEEDS_CONTEXT
Escalate when: Asked to change business logic → STOP
