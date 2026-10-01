# Task T4 — JSONL logger shared helper

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
