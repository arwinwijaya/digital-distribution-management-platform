# EXECUTION PLAN — Siklus Pemesanan End-to-End (Hybrid API + Thin UI)

**Date:** 2026-09-30
**Spec:** docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
**Status:** draft
**Total tasks:** 8

---

## Execution Overview

### Recommended Order
```
T1, T2, T3, T5 prereq → T4, T7 after T1 → T6 after T3, T4 → T8 after T5, T6, T7
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T2, T3, T5 | — (prereq) |
| Group B | T4, T7 | T1 complete |
| Group C | T6 | T3, T4 complete |
| Group D | T8 | T5, T6, T7 complete |

### Constraints Reminder
**Architecture:** May touch `apps/web/src` (data-testid only), `apps/web/e2e/**`, logger `apps/web/e2e/support/**`, extend `apps/web/order-flow.test.js`, seed script `scripts/e2e-seed.sh` + `apps/web/package.json` scripts, CI `.github/workflows/e2e.yml`. Must NOT touch API contracts, DB migrations/schema, RBAC matrix, order/delivery/invoice/payment state machines, backend business rules.
**Out-of-scope:** Firefox/WebKit, Loki/Elastic, dummy-mode E2E, WhatsApp/email/push real, performance/load, frontend Jest unit tests beyond API.
**Assumptions at risk:** JSONL sink `apps/web/test-results/e2e-logs/<runId>.jsonl`; retention `actions/upload-artifact retention-days:30` + 7-day local purge; ~15-20 data-testid; replay identical = 200 created:false (PaymentService::replayExistingPayment); migrate:fresh --seed determinism; JWT expiry sufficient; runId+orderRef correlation sufficient.
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules.

### File Structure Map
```
Rule: Seed isolation
  Create: scripts/e2e-seed.sh                              (created by: T2)
  Modify: apps/web/package.json                            (modified by: T2)
  Modify: package.json                                      (modified by: T2)

Rule: Serial 4-role browser flow + status invariants (§6, Story 1 Rule 1-2)
  Create: apps/web/playwright.config.ts                    (created by: T1)
  Create: apps/web/e2e/siklus-pemesanan.spec.ts            (created by: T6)
  Create: apps/web/e2e/pages/login.page.ts                 (created by: T6)
  Create: apps/web/e2e/pages/orders.page.ts                (created by: T6)
  Create: apps/web/e2e/pages/admin-orders.page.ts          (created by: T6)
  Create: apps/web/e2e/pages/delivery.page.ts              (created by: T6)
  Create: apps/web/e2e/pages/payments.page.ts              (created by: T6)
  Create: apps/web/e2e/support/fixtures.ts                 (created by: T6)
  Create: apps/web/e2e/support/logger.ts                   (created by: T4)
  Modify: apps/web/src/components/LoginForm.tsx             (modified by: T3)
  Modify: apps/web/src/components/OrderForm.tsx             (modified by: T3)
  Modify: apps/web/src/app/admin/orders/page.tsx           (modified by: T3)
  Modify: apps/web/src/app/delivery/page.tsx              (modified by: T3)
  Modify: apps/web/src/app/payments/page.tsx              (modified by: T3)
  Modify: apps/web/src/app/orders/page.tsx                (modified by: T3)
  Modify: apps/web/jest.config.js                          (modified by: T1)
  Modify: apps/web/tsconfig.json                           (modified by: T1)

Rule: Business-rule negative checks (§8, Story 2)
  Modify: apps/web/order-flow.test.js                      (modified by: T5)
  Test:   apps/web/order-flow.test.js                      (test by: T5)

Rule: data-testid contract
  Create: apps/web/src/components/LoginForm.testid.spec.tsx  (created by: T3)
  Create: apps/web/src/components/OrderForm.testid.spec.tsx  (created by: T3)
  Create: apps/web/src/app/admin/orders/page.testid.spec.tsx (created by: T3)
  Create: apps/web/src/app/delivery/page.testid.spec.tsx     (created by: T3)
  Create: apps/web/src/app/payments/page.testid.spec.tsx     (created by: T3)
  Test:   each file above                                  (tested by: T3)

Rule: Observability — JSONL per step (§9 Rule 1, Story 3)
  Create: apps/web/e2e/support/logger.ts                   (created by: T4)
  Test:   apps/web/e2e/support/logger.test.ts              (test by: T4)
  Create: apps/web/e2e/siklus-pemesanan.spec.ts            (created by: T6 — emits JSONL)

Rule: Observability — artifacts on-failure (§9 Rule 2)
  Modify: apps/web/playwright.config.ts                    (modified by: T1 — screenshot/trace/video)
  Create: scripts/purge-e2e-artifacts.sh                   (created by: T7)
  Modify: apps/web/package.json                            (modified by: T7)
  Create: .github/workflows/e2e.yml                        (created by: T8 — retention-days 30)
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

### Task 1: Scaffold Playwright config + install [prereq] [no-tdd — structural task]

## OBJECTIVE
Install `@playwright/test@~1.48` as devDependency in `apps/web`, create `apps/web/playwright.config.ts` locked to Chromium with on-failure trace/screenshot/video and JUnit+HTML+JSON reporters, and exclude e2e from Jest/tsc.

Steps:
1. Create the structure / install dep + config: `npm i -D @playwright/test@~1.48` in `apps/web`, write `playwright.config.ts` with `defineConfig({ testDir: 'e2e', fullyParallel: false, workers: 1, timeout: 60000, reporter: [['html',{open:'never'}],['junit',{outputFile:'test-results/e2e-junit.xml'}],['json',{outputFile:'test-results/e2e-results.json'}]], use: { baseURL: 'http://localhost:3000', trace: 'retain-on-failure', screenshot: 'only-on-failure', video: 'retain-on-failure' }, projects: [{ name:'chromium', use:{ browserName:'chromium' }}] })`, add `'<rootDir>/e2e/'` and `'<rootDir>/playwright\\.(config|.*)\\.(ts|js)$'` to `testPathIgnorePatterns` in `jest.config.js`, and `exclude: ["e2e", "playwright.config.ts"]` in `tsconfig.json` if needed.
2. Verify: `npx --prefix apps/web playwright --version && npx --prefix apps/web tsc --noEmit && npx --prefix apps/web jest --listTests --runInBand > /tmp/ddp-jest-tests.txt && ! grep -E '(^|/)e2e/|playwright\\.config' /tmp/ddp-jest-tests.txt`
3. Commit: `git commit -m "chore(e2e): scaffold Playwright Chromium config with on-failure artifacts"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 1 Rule 1, Story 3 Rule 2, Dependencies (New: @playwright/test, mcr.microsoft.com/playwright), Architecture Constraints

## WHY THIS APPROACH
Complexity: lightweight
Justification: Config-only scaffolding, no business logic; choices already validated via context7 (projects, trace/video/screenshot retain-on-failure, storageState, JUnit/JSON reporters). Deterministic Chromium-only avoids matrix flake.

## SANDWICH CONTEXT
[CRITICAL: Must NOT touch API contracts, DB migrations, RBAC, or state machines — test infra only]
You are implementing Playwright scaffold for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Option B Hybrid API+thin UI — Playwright only for §6 critical path
Files in scope: apps/web/package.json, apps/web/playwright.config.ts, apps/web/jest.config.js, apps/web/tsconfig.json
Available after: none (prereq)
Architecture rule: Lock to Chromium; forbid firefox/webkit via config; video/trace/screenshot on-failure only; must not break npm test / lint
[RESTATE: Must NOT touch API contracts, DB migrations, RBAC, or state machines]

## DELIVERABLE
[no-tdd — structural task] Playwright config exists, Chromium-only, reporters and artifact modes as specified, and `npm --prefix apps/web run lint` + `npm --prefix apps/web test -- --listTests` still pass with e2e excluded.

## QUALITY BAR
Must-have:
  - @playwright/test pinned ~1.48 in apps/web devDependencies
  - playwright.config.ts defines single project chromium, baseURL http://localhost:3000, trace/video/screenshot retain/on-failure
  - reporters html + junit (test-results/e2e-junit.xml) + json (test-results/e2e-results.json)
  - e2e/ excluded from jest.config.js and tsc
  - workers:1, fullyParallel:false for serial 4-role flow

Must-not-have:
  - Any change to apps/api/**, DB schema, RBAC, or order state machine
  - Firefox/WebKit projects
  - Shared utils/helpers reimplementation

Open question risks:
  - mcr.microsoft.com/playwright vs host runner → if wrong: CI image mismatch, fix workflow image in T8
  - Logger path <runId>.jsonl assumption → ensure test-results dir exists

Rollback note:
  - Remove devDependency + delete apps/web/playwright.config.ts + revert jest/tsconfig excludes

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: Playwright version prints, tsc --noEmit passes, jest --listTests excludes e2e, config file matches spec
Uncertain when: Browser install fails offline → NEEDS_CONTEXT (use npx playwright install --with-deps chromium in CI only)
Escalate when: Config requires touching backend → STOP

---

### Task 2: Seed isolation script [prereq] [no-tdd — structural task]

## OBJECTIVE
Create deterministic DB reset entrypoint used before every E2E run (local and CI) via `docker compose exec api php artisan migrate:fresh --seed`.

Steps:
1. Create `scripts/e2e-seed.sh` (bash, `set -euo pipefail`, runs `docker compose exec api php artisan migrate:fresh --seed --force`), make executable, and add `apps/web/package.json` scripts: `"test:e2e:seed": "bash ../../scripts/e2e-seed.sh"` plus root `package.json` script `"e2e:seed": "bash scripts/e2e-seed.sh"` if missing.
2. Verify: `ls -la scripts/e2e-seed.sh && bash -n scripts/e2e-seed.sh && cat apps/web/package.json | grep -A2 test:e2e:seed`
3. Commit: `git commit -m "chore(e2e): add deterministic DB seed isolation script"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — In-Scope seed isolation, Spec Context (DatabaseSeeder password123), Implementation Notes

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single shell entrypoint, no logic branching; matches existing `docker:up`/`api:artisan` patterns and Route verification (no /test/seed endpoint).

## SANDWICH CONTEXT
[CRITICAL: Must NOT add /test/seed endpoint or change DB schema/migrations]
You are implementing seed isolation for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Option B Hybrid — migrate:fresh --seed per run (assume sufficient, no snapshot)
Files in scope: scripts/e2e-seed.sh, apps/web/package.json, package.json
Available after: none (prereq)
Architecture rule: Must NOT add test-only API endpoint; must use docker compose exec api php artisan
[RESTATE: Must NOT add /test/seed endpoint or change DB schema]

## DELIVERABLE
[no-tdd — structural task] Seed script exists, is executable, and npm scripts reference it; dry-run syntax check passes.

## QUALITY BAR
Must-have:
  - scripts/e2e-seed.sh uses `docker compose exec api php artisan migrate:fresh --seed`
  - npm scripts wired for local invocation

Must-not-have:
  - New Laravel routes or controllers
  - DB schema changes
  - Hardcoded secrets

Open question risks:
  - migrate:fresh too slow on CI → if wrong: report NEEDS_CONTEXT, propose snapshot restore in follow-up

Rollback note:
  - Delete scripts/e2e-seed.sh + remove npm script entries

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: Script file exists with correct content and package.json scripts present
Uncertain when: Docker not available locally → NEEDS_CONTEXT but still DONE_WITH_CONCERNS (CI will validate)
Escalate when: Asked to create API seed endpoint → STOP

---

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

---

### Task 4: JSONL logger shared helper [depends: T1] [test-risk]

## OBJECTIVE
Create `apps/web/e2e/support/logger.ts` usable from both Playwright (browser test) and Node (API test if wired) that writes one JSONL line per instrumented action with required fields.

Steps:
1. Write failing test for: Logger writes success event with required fields
   Test file: `apps/web/e2e/support/logger.test.ts`
   Level: unit
   Test intent: Given logger initialized with runId / When logEvent called with success payload (role, step, action, url, selector, apiStatus, durationMs) / Then file `test-results/e2e-logs/<runId>.jsonl` contains one JSON line with all required fields `runId,timestamp,role,step,action,url,selector,apiStatus,durationMs,status,error,attachments` and status=success
   Exercise through: Import `createLogger(runId)` and call `logEvent(...)`
   Test doubles: Use a unique `fs.mkdtempSync(path.join(os.tmpdir(), 'ddp-jsonl-'))` directory passed through `createLogger(runId, { dir: tempDir })`; do not mock `fs` or logger, and remove the temp directory in `afterEach`
   Expected RED: Module not found or logEvent not a function
2. Run test — verify FAIL: `npx --prefix apps/web jest e2e/support/logger.test.ts -t "writes success event" --runInBand`
3. Write failing test for: Logger writes failure event with error + attachments
   Test file: `apps/web/e2e/support/logger.test.ts`
   Level: unit
   Test intent: Given logger / When logEvent called with status=failed, error message, attachments (screenshot/trace paths) / Then JSON line has status=failed, error non-empty, attachments array with paths
   Exercise through: Same logger API
   Test doubles: Same unique `mkdtempSync` directory; do not mock logger or append calls
   Expected RED: Missing error/attachments serialization
4. Run test — verify FAIL: `npx --prefix apps/web jest e2e/support/logger.test.ts -t "writes failure event" --runInBand`
5. Write failing test for: Logger runId correlation
   Test file: `apps/web/e2e/support/logger.test.ts`
   Level: unit
   Test intent: Given two logEvents with same runId / When both written / Then both lines share runId and timestamps are ISO-8601 parseable
   Exercise through: Sequential logEvent calls
   Test doubles: Same unique `mkdtempSync` directory; do not mock logger or append calls
   Expected RED: runId not persisted or timestamp not ISO
6. Run test — verify FAIL: `npx --prefix apps/web jest e2e/support/logger.test.ts -t "runId correlation" --runInBand`
7. Implement: Create `apps/web/e2e/support/logger.ts` with `export type LogEvent = { runId:string; timestamp:string; role:string; step:string; action:string; url:string; selector:string; apiStatus:number|null; durationMs:number; status:'success'|'failed'; error:string|null; attachments:string[] }`, `createLogger(runId:string, opts?:{dir:string})` that ensures `test-results/e2e-logs/`, and `logEvent(event:Partial<LogEvent>)` that fills timestamp `new Date().toISOString()` if missing, and appends `JSON.stringify(event)+"\n"` via `fs.appendFileSync`. Ensure dir creation idempotent and safe for parallel workers (mkdirSync recursive). Verify all PASS: `npx --prefix apps/web jest e2e/support/logger.test.ts --runInBand` → refactor while green → Commit: `git commit -m "feat(e2e): add JSONL per-step logger with runId correlation"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 3 Rule 1, Acceptance Criteria Observability, Open Questions (sink path), Implementation Notes (runId)

## WHY THIS APPROACH
Complexity: standard
Justification: Shared helper needed by both Playwright (T6) and potentially Jest (T5); cross-runtime consumption makes mock boundary and file concurrency the test-risk; requires careful API design without external deps.

## SANDWICH CONTEXT
[CRITICAL: Logger must be usable from both Playwright and Node without adding runtime deps; must not touch backend]
You are implementing JSONL logger for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Hand-rolled ~50-line util, no commodity lib; sink assumed test-results/e2e-logs/<runId>.jsonl
Files in scope: apps/web/e2e/support/logger.ts, apps/web/e2e/support/logger.test.ts
Available after: T1 (playwright config defines test-results dir)
Architecture rule: Deterministic path, recursive mkdir, no backend changes, no Loki/Elastic
[RESTATE: Must not add runtime deps or touch backend]

## DELIVERABLE
Given logger initialized, When success event logged, Then JSONL line contains all required fields with status success
Given failure event logged, When error + attachments provided, Then line has status failed, error, attachments
Given two events same runId, When written, Then both share runId and ISO timestamps

## QUALITY BAR
Must-have:
  - Required fields exactly: runId,timestamp,role,step,action,url,selector,apiStatus,durationMs,status,error,attachments
  - One JSON per line, UTF-8, append mode, recursive mkdir
  - ISO-8601 timestamp if not supplied
  - Tests before implementation

Must-not-have:
  - External logging lib (pino/winston)
  - Backend changes
  - Log per internal call (invoice/WhatsApp) — top-level action only

Open question risks:
  - Sink path assumed → if CI expects different, report NEEDS_CONTEXT
  - Granularitas top-level assumed → if wrong: bising, report

Rollback note:
  - Delete e2e/support/logger.* (consumers will fallback to no-op)

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Logger reimplemented locally in T5/T6 instead of imported → STOP

## STOP CONDITIONS
Done when: All 3 logger tests PASS and file exists
Uncertain when: Parallel worker file contention observed → NEEDS_CONTEXT (consider per-worker runId)
Escalate when: Asked to add remote log pipeline → STOP

---

### Task 5: API negative checks — extend order-flow.test.js [prereq]

## OBJECTIVE
Extend `apps/web/order-flow.test.js` to cover §8 guards + idempotency deterministically, reusing existing real HTTP+sqlite harness.

Steps:
1. Write failing test for: Assign rejected before Confirmed
   Test file: `apps/web/order-flow.test.js`
   Level: integration (real HTTP against php -S + sqlite, via existing harness)
   Test intent: Given outlet order newly created with status New (no admin approve) / When API POST /deliveries for that order (admin token, driver Joko) / Then HTTP 422 and body contains "Only confirmed orders can be assigned" and no delivery created (GET /deliveries still empty for that order)
   Exercise through: request('/api/deliveries', {method:'POST', headers: authHeaders(adminToken), body: {order_id}})
   Test doubles: None — use real server/DB from harness; do NOT mock Order/Delivery models
   Expected RED: Test not present or assertion fails (today only happy path is covered)
2. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Assign rejected before Confirmed" --runInBand`
3. Write failing test for: Payment rejected before Delivered
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given order Confirmed but delivery not delivered (assigned/in_progress) / When POST /payments amount=TOTAL / Then 422 "Payments can only be recorded for delivered orders" and order remains not Paid, invoice still unpaid
   Exercise through: request('/api/payments', {method:'POST', headers: authHeaders(financeToken)})
   Test doubles: Real DB
   Expected RED: Missing test
4. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Payment rejected before Delivered" --runInBand`
5. Write failing test for: Overpayment rejected
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given order Delivered with outstanding O / When POST /payments amount = O + 1 / Then 422 "Payment cannot exceed the outstanding balance" and no payment created, outstanding still O
   Exercise through: POST /payments over outstanding
   Test doubles: Real DB
   Expected RED: Missing test
6. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Overpayment rejected" --runInBand`
7. Write failing test for: Idempotency replay identical returns 200 created:false
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given first POST /orders or /payments with Idempotency-Key K succeeded (201 created:true) / When identical payload re-sent with same K / Then HTTP 200, body created=false, same id/reference, no duplicate row (count unchanged)
   Exercise through: Two POSTs with same header + body
   Test doubles: Real DB
   Expected RED: Currently harness asserts 200 but spec expects created:false distinction — test missing
8. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Idempotency replay identical" --runInBand`
9. Write failing test for: Idempotency same key different payload → 422
   Test file: `apps/web/order-flow.test.js`
   Level: integration
   Test intent: Given first request with K succeeded / When different payload (e.g., different quantity or amount) is sent with the same K / Then HTTP 422, and a subsequent GET or list query proves the first record has the exact original id/reference, payload amount/quantity, status, and count unchanged
   Exercise through: POST with same K different body, then GET the original order/payment and compare a snapshot captured before the second request
   Test doubles: Real DB
   Expected RED: Missing test
10. Run test — verify FAIL: `npx --prefix apps/web jest order-flow.test.js -t "Idempotency different payload" --runInBand`
11. Implement: Extend order-flow.test.js with 5 new `it(...)` blocks using existing `request()`/`authHeaders()` helpers, reusing `makeRuntime`/`migrate:fresh --seed` harness but asserting status codes/messages above. For the different-payload case, capture the complete first record snapshot and row count before replay, assert HTTP 422, then fetch again and assert id/reference, payload amount/quantity, status, and count equal the snapshot. Import PaymentService replay logic expectation: `expect(body.created).toBe(false)` and `expect(response.status).toBe(200)` for identical replay; `expect(response.status).toBe(422)` for different payload. Verify all PASS: `npx --prefix apps/web jest order-flow.test.js --runInBand` → refactor while green (extract helper `expectNoDelivery(orderId)` if duplicated, commit separately as refactor if over 3 duplications) → Commit: `git commit -m "test(api): cover order negative guards and idempotency (§8)"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 2 Rules 1-4, Acceptance Criteria Negative checks, Spec Context (routes, PaymentService::replayExistingPayment)

## WHY THIS APPROACH
Complexity: standard
Justification: Reuses proven harness (php -S + sqlite, 120s timeout); 5 independent GWT scenarios each need own RED cycle; must verify 200 vs 201 distinction without mocking domain.

## SANDWICH CONTEXT
[CRITICAL: Must NOT change backend business rules, routes, or DB schema]
You are implementing API negative checks for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Hybrid — guards stay in API test (fast, deterministic)
Files in scope: apps/web/order-flow.test.js
Available after: T2 (seed script); logger is intentionally not imported because this task verifies API business rules only
Architecture rule: Real HTTP server only; no mocks of Order/Payment models; assertions on HTTP status + DB state
[RESTATE: Must NOT change backend business rules or schema]

## DELIVERABLE
Given order New, When POST /deliveries, Then 422 + no delivery
Given order not Delivered, When POST /payments, Then 422 + no mutation
Given outstanding O, When pay > O, Then 422 + no payment + outstanding remains O
Given key K succeeded, When replay identical K, Then 200 created:false + no duplicate
Given key K succeeded, When payload differ + same K, Then 422 + first record intact

## QUALITY BAR
Must-have:
  - All 5 negative scenarios with own test + own command
  - Assertions on both HTTP status and persistence (no duplicate / no mutation)
  - Reuse existing harness helpers (findFreePort, waitForServer, authHeaders)

Must-not-have:
  - Mocking Order/Payment/Delivery domain
  - Changing apps/api/**
  - Adding new test framework

Open question risks:
  - Replay identical assumed 200 created:false → if actual is 201, report NEEDS_CONTEXT and adjust assertion

Rollback note:
  - Remove the 5 new `it` blocks (restore file to prior)

Red flags:
  - Work outside listed file → DONE_WITH_CONCERNS
  - Backend contract changed → STOP

## STOP CONDITIONS
Done when: All 5 new tests plus existing happy-path test PASS via jest order-flow.test.js --runInBand
Uncertain when: 422 message wording differs → NEEDS_CONTEXT (assert status only, message contains)
Escalate when: Harness cannot reproduce PaymentService replay → STOP

---

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

---

### Task 7: Artifact retention + local purge [depends: T1] [no-tdd — structural task]

## OBJECTIVE
Wire artifact retention: CI 30 days via `actions/upload-artifact retention-days`, local purge script for 7-day retention.

Steps:
1. Create `scripts/purge-e2e-artifacts.sh` that deletes `apps/web/test-results/` and `apps/web/test-results/e2e-logs/` entries older than 7 days (`find ... -mtime +7 -delete`), and add `apps/web/package.json` script `"test:e2e:purge": "bash ../../scripts/purge-e2e-artifacts.sh"`.
2. Verify: `bash -n scripts/purge-e2e-artifacts.sh && cat apps/web/package.json | grep test:e2e:purge && ls -la scripts/purge-e2e-artifacts.sh`
3. Commit: `git commit -m "chore(e2e): add artifact purge script for 7-day local retention"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 3 Rule 2, Open Questions (retensi), Implementation Notes

## WHY THIS APPROACH
Complexity: lightweight
Justification: Two small shell+config changes; no test logic; retention enforced via GitHub Actions native param + local cron/manual script.

## SANDWICH CONTEXT
[CRITICAL: Must NOT add remote log pipeline; retention only via upload-artifact and local script]
You are implementing artifact retention for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: 30 days CI, 7 days local
Files in scope: scripts/purge-e2e-artifacts.sh, apps/web/package.json, .github/workflows/e2e.yml (retention param added in T8 but script is T7)
Available after: T1 (test-results path), T4 (logger path)
Architecture rule: No Loki/Elastic, no new infra
[RESTATE: Must NOT add remote log pipeline]

## DELIVERABLE
[no-tdd — structural task] Purge script exists and package script wired; CI workflow (T8) will set retention-days:30

## QUALITY BAR
Must-have:
  - Script deletes test-results older than 7 days safely (dry-run check)
  - Script is executable

Must-not-have:
  - Backend changes
  - Deleting source files

Open question risks:
  - Retention 30/7 assumed → if wrong: adjust param, report

Rollback note:
  - Delete script + remove npm script

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: Script syntax passes and npm script present
Uncertain when: CI path differs → NEEDS_CONTEXT
Escalate when: Asked to add external storage → STOP

---

### Task 8: CI workflow wiring for E2E [depends: T5, T6, T7]

## OBJECTIVE
Create `.github/workflows/e2e.yml` that runs seed → API tests → Playwright Chromium (mcr.microsoft.com/playwright) → upload artifacts with retention 30 days.

Steps:
1. Create the workflow / config
2. Verify: `cat .github/workflows/e2e.yml && npx --prefix apps/web tsc --noEmit`
3. Commit: `git commit -m "ci(e2e): wire hybrid E2E workflow with Playwright container and 30-day artifacts"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Implementation Notes (CI, mcr.microsoft.com/playwright), Rollback Plan, Existing .github/workflows/ci.yml

## WHY THIS APPROACH
Complexity: lightweight
Justification: YAML-only, reuses docker compose services (db, api, web) + Playwright image; no code branching.

## SANDWICH CONTEXT
[CRITICAL: Must use mcr.microsoft.com/playwright image, Chromium only, retention-days 30, must not break existing ci.yml]
You are implementing CI wiring for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Official Playwright container; hybrid runners
Files in scope: .github/workflows/e2e.yml, .github/workflows/ci.yml (read-only reference, do not break)
Available after: T5 (API test), T6 (browser flow), T7 (purge/retention)
Architecture rule: Job runs npm run test:e2e:seed → npm --prefix apps/web run test:e2e (API) → npx --prefix apps/web playwright test --project=chromium; upload test-results/ + e2e-logs/ with retention-days:30
[RESTATE: Must use official Playwright container and retention-days 30]

## DELIVERABLE
[no-tdd — structural task] Workflow file exists, triggers on push/PR, runs seed, runs API then Playwright, uploads artifacts with retention 30, and does not break existing ci.yml

## QUALITY BAR
Must-have:
  - Container `mcr.microsoft.com/playwright:v1.48.0-jammy` or `actions/setup-node` + `npx playwright install --with-deps chromium` equivalent
  - Steps: checkout, setup-node, install, docker compose up, seed, api test, playwright test, upload-artifact with retention-days: 30
  - Artifacts include `apps/web/test-results/**` and `apps/web/test-results/e2e-logs/**` and `playwright-report/`

Must-not-have:
  - Firefox/WebKit jobs
  - Changes to app runtime code

Open question risks:
  - Windows+Docker determinism → if flaky: report NEEDS_CONTEXT

Rollback note:
  - Delete .github/workflows/e2e.yml (API tests still run via existing ci.yml)

Red flags:
  - Existing ci.yml broken → DONE_WITH_CONCERNS
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: Workflow YAML is valid (yamllint/actions schema) and tsc still passes
Uncertain when: Docker compose not reachable on GH runner → NEEDS_CONTEXT (use service containers)
Escalate when: Requires infra change beyond workflow → STOP

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Scaffold Playwright config + install | prereq | lightweight | playwright --version + tsc --noEmit + jest list excludes e2e |
| T2 | Seed isolation script | prereq | lightweight | bash -n + package scripts |
| T3 | Add data-testid contract | prereq | standard | 5 suites PASS with getByTestId |
| T4 | JSONL logger shared helper | T1 [test-risk] | standard | logger.test.ts 3 cases PASS |
| T5 | API negative checks | prereq | standard | order-flow.test.js existing +5 new tests PASS |
| T6 | Playwright thin-UI browser flow | T3, T4 | standard | playwright test 6 scenarios PASS + JSONL ≥6 events |
| T7 | Artifact retention + purge | T1 | lightweight | purge script syntax + npm script |
| T8 | CI workflow wiring | T5, T6, T7 | lightweight | e2e.yml valid + tsc passes |

