# Task T1 — Scaffold Playwright config + install

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
