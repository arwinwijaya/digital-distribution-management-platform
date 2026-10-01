# Task T8 — CI workflow wiring for E2E

**Phase:** 2
**Depends:** T5, T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
