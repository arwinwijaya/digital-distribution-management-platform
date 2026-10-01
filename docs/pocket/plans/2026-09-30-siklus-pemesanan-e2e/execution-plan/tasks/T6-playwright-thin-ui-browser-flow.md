# Task T6 — Playwright thin-UI browser flow

**Phase:** 2
**Depends:** T3, T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Playwright thin-UI browser flow [depends: T3, T4]

## OBJECTIVE
Implement Chromium-only Playwright spec that runs the 4-role serial flow on real data (isDummy=false) with per-step JSONL logging and verifies Paid + invariants.

Steps:
1. Write failing test for: Outlet creates order via browser
   Test file: `apps/web/e2e/siklus-pemesanan.spec.ts`
   Level: E2E (real browser + real API)
   Test intent: Given DB seeded via scripts/e2e-seed.sh and web+api running / When outlet login siti.nurhaliza@ddp.test/password123 → /orders, select active product qty 2+1, click order-submit / Then URL is /orders, order reference ORD-... visible, status New, JSONL event order_created logged with role=outlet and durationMs
   Exercise through: Playwright `page.goto('/login')`, `getByTestId('login-email')`, `getByTestId('login-submit')`, `getByTestId('order-submit')`, API polling GET /orders/{id} as oracle, and logger `logEvent`
   Test doubles: None — real browser + real backend; do NOT mock fetch/auth; stub WhatsApp only if triggered
   Expected RED: Spec file missing / selector not found / assertion fails
2. Run test — verify FAIL: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts -g "Outlet creates order" --project=chromium`
3. Write failing test for: Admin approves and invoice available
   Test file: `apps/web/e2e/siklus-pemesanan.spec.ts`
   Level: E2E
   Test intent: Given order New from previous step / When admin login ratna.sari@ddp.test → /admin/orders, click admin-order-approve-{id} / Then order status Confirmed, invoice unpaid with total==order total and paid 0.00, JSONL event admin_approve
   Exercise through: New browserContext clear storageState + Playwright pages
   Test doubles: Real services
   Expected RED: Missing test / approve button disabled
4. Run test — verify FAIL: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts -g "Admin approves" --project=chromium`
5. Write failing test for: Admin assigns delivery
   Test file: `apps/web/e2e/siklus-pemesanan.spec.ts`
   Level: E2E
   Test intent: Given order Confirmed / When admin creates delivery for driver Joko Widodo with notes "Pengiriman manual test 1 siklus pemesanan" / Then delivery assigned with correct driver_id, order still Confirmed, JSONL event delivery_assigned
   Exercise through: Admin delivery assignment UI or API POST /deliveries (UI preferred if admin page exposes it, else direct API via page.request with admin storageState)
   Test doubles: Real backend
   Expected RED: Assignment UI not found
6. Run test — verify FAIL: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts -g "Admin assigns delivery" --project=chromium`
7. Write failing test for: Driver completes delivery
   Test file: `apps/web/e2e/siklus-pemesanan.spec.ts`
   Level: E2E
   Test intent: Given delivery assigned / When driver login joko.widodo@ddp.test → /delivery, click delivery-start-{id}, fill delivery-recipient-{id}=Siti Nurhaliza and delivery-proof-url-{id}=https://example.com/proof/manual-check-order-001.jpg, click delivery-complete-{id} / Then delivery delivered with delivered_at, order Delivered, proof stored, JSONL events in_progress + delivered
   Exercise through: Driver browserContext
   Test doubles: Real backend
   Expected RED: Start/complete buttons missing
8. Run test — verify FAIL: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts -g "Driver completes delivery" --project=chromium`
9. Write failing test for: Finance pays in full → Paid
   Test file: `apps/web/e2e/siklus-pemesanan.spec.ts`
   Level: E2E
   Test intent: Given order Delivered with outstanding TOTAL / When finance login dewi.lestari@ddp.test → /payments, fill payment-order-id=TOTAL's order id, payment-amount=TOTAL, click payment-submit / Then payment completed, order Paid, invoice paid balance 0.00, receipt_reference present, badge Paid visible, toast present (presence), JSONL event payment_completed
   Exercise through: Finance browserContext + payments page
   Test doubles: Real backend
   Expected RED: Payment form not found / badge not Paid
10. Run test — verify FAIL: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts -g "Finance pays in full" --project=chromium`
11. Write failing test for: Status history invariant
   Test file: `apps/web/e2e/siklus-pemesanan.spec.ts`
   Level: E2E
   Test intent: Given cycle Paid / When detail order fetched via UI track or GET /orders/{id} / Then status_history contains New,Confirmed,Delivered,Paid in order
   Exercise through: Track order UI or API
   Test doubles: Real backend
   Expected RED: History missing entries
12. Run test — verify FAIL: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts -g "Status history" --project=chromium`
13. Implement: Create `e2e/pages/*.page.ts` POMs wrapping getByTestId locators, `e2e/support/fixtures.ts` with `test.extend<{ runId:string }>` that generates runId, `beforeAll` seed via `execSync('bash scripts/e2e-seed.sh')` if DB not already fresh, and `e2e/siklus-pemesanan.spec.ts` that serially runs 4 contexts (outlet→admin→driver→finance) clearing storageState between roles, calling `logEvent` per step with duration, and asserting as above with `expect` on UI + API oracle. Verify all PASS: `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts --project=chromium` (headless) and `npx --prefix apps/web playwright test e2e/siklus-pemesanan.spec.ts --project=chromium --headed` dry-check → refactor while green (extract `loginAs(role)` helper) → Commit: `git commit -m "feat(e2e): add thin-UI 4-role browser flow with JSONL logging"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 1 Rule 1-2 (all 6 scenarios), Story 3, Related Areas (login, OrderForm, delivery/api.ts, payments, admin/orders)

## WHY THIS APPROACH
Complexity: standard
Justification: Single E2E spec with serial contexts, POM for complex pages, real-data deterministic via seed; 6 RED cycles keeps each role-step independently falsifiable; presence-only toast assertion reduces flake.

## SANDWICH CONTEXT
[CRITICAL: Must use real data (isDummy=false), 4 serial browserContexts, data-testid selectors, no dummy guards]
You are implementing thin-UI browser flow for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Hybrid — Playwright only for §6 critical path; API oracles allowed
Files in scope: apps/web/e2e/siklus-pemesanan.spec.ts, apps/web/e2e/pages/**, apps/web/e2e/support/fixtures.ts, apps/web/e2e/support/logger.ts (import)
Available after: T3 (testids), T4 (logger)
Architecture rule: Chromium only, serial execution, clear storageState per role, trace/video/screenshot on-failure only, finance may pay any outlet's order
[RESTATE: Must use real data and data-testid, serial contexts only]

## DELIVERABLE
Given DB fresh, When outlet creates order, Then redirect /orders + New + reference logged
Given New, When admin approves, Then Confirmed + invoice unpaid
Given Confirmed, When admin assigns Joko, Then delivery assigned + order still Confirmed
Given assigned, When driver start+complete with recipient+proof URL, Then delivered + Delivered + proof stored
Given Delivered + outstanding TOTAL, When finance pays TOTAL, Then Paid + invoice paid + balance 0.00 + receipt + badge Paid + toast present
Given Paid, When detail fetched, Then status_history New→Confirmed→Delivered→Paid

## QUALITY BAR
Must-have:
  - 4 roles serial, each with new browserContext + clear storageState
  - Selectors via data-testid (no text selector as primary)
  - One JSONL event per instrumented action (success and failure) via logger
  - Finance pays any outlet's order (do not assert outlet ownership)
  - Toast check is presence only, not exact-text

Must-not-have:
  - Firefox/WebKit, parallel workers, dummy-mode paths
  - Text-based flaky selectors as primary
  - Shared helper reimplemented instead of importing logger

Open question risks:
  - JWT expiry mid-flow → if 401, report NEEDS_CONTEXT (extend token TTL in test env)
  - Idempotency header handling in UI → not needed for this flow

Rollback note:
  - Delete e2e/siklus-pemesanan.spec.ts + pages/fixtures (logger stays)

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Backend touched → STOP

## STOP CONDITIONS
Done when: All 6 Playwright tests PASS and JSONL file has ≥6 success events + artifacts on failure if forced
Uncertain when: Selector missing due to T3 not merged → NEEDS_CONTEXT (ensure T3 landed)
Escalate when: Requires new API endpoint → STOP
