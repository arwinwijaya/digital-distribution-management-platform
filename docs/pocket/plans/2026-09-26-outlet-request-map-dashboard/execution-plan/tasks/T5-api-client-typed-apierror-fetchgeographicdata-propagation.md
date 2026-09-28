# Task T5 — API client — typed ApiError + fetchGeographicData propagation

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: API client — typed ApiError + fetchGeographicData propagation [prereq]

## OBJECTIVE
Introduce a typed `ApiError` (`{status: number|null, retryable: boolean, message: string}`), make `adminFetch` throw it with the real HTTP status and retryable classification (5xx/network retryable; 401/403 not), and propagate it through `fetchGeographicData`. Error classification must never rely on message strings.

Steps:
1. Write failing test for: HTTP status propagation
   Test file: `apps/web/src/lib/api-error.test.ts`
   Level: unit
   Test intent: Given `fetch` resolves with HTTP 500 / When `adminFetch` runs / Then it throws `ApiError` with `status:500` and `retryable:true`; Given HTTP 401 or 403 / Then `retryable:false`; Given a network rejection / Then `status:null` and `retryable:true`.
   Exercise through: `adminFetch` (mocked `global.fetch`)
   Test doubles: mock `fetch`; do NOT mock the unit under test
   Expected RED: module/class does not exist; current code throws a plain `Error`
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/api-error.test.ts`
3. Implement `ApiError` + update `adminFetch` to attach `res.status` and classify → verify PASS → refactor → commit `feat(api): typed ApiError with status and retryable`
4. Write failing test for: geographic fetch propagation
   Test file: `apps/web/src/lib/data-intelligence-api.test.ts`
   Level: unit
   Test intent: Given `adminFetch` rejects with a 403 `ApiError` / When `fetchGeographicData` runs / Then the same `ApiError` (with `status` and `retryable`) propagates unchanged and no dummy fallback masks it.
   Exercise through: `fetchGeographicData`
   Test doubles: mock `adminFetch`; do NOT mock the unit under test
   Expected RED: error is swallowed or rethrown as a plain `Error`
5. Run test — verify FAIL: `cd apps/web && npx jest src/lib/data-intelligence-api.test.ts`
6. Implement propagation → verify PASS → refactor → commit `feat(api): propagate typed error through geographic fetch`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rule: Response errors and stale-data rule; Story: Error classification and retry
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: lightweight
Justification: an error class plus a fetch wrapper — pure logic, no new dependencies.

## SANDWICH CONTEXT
[CRITICAL: Error classification branches on `ApiError.status`, never on localized message text]
You are implementing typed API error propagation.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/lib/api-error.ts`, `apps/web/src/lib/api-error.test.ts`, `apps/web/src/lib/api.ts`, `apps/web/src/lib/data-intelligence-api.ts`, `apps/web/src/lib/data-intelligence-api.test.ts`
Available after: none (prereq)
Architecture rule: 5xx/network retryable; 401/403 not; existing `withDummyRead` short-circuit preserved; no stale snapshot after failure
[RESTATE: Error classification branches on `ApiError.status`]

## DELIVERABLE
Given HTTP 500, When `adminFetch`, Then throws `ApiError{status:500,retryable:true}`
Given a network rejection, When `adminFetch`, Then throws `ApiError{status:null,retryable:true}`
Given HTTP 401/403, When `adminFetch`, Then `retryable:false`
Given `fetchGeographicData` fails, When called, Then the typed `ApiError` propagates unchanged
[must-not] Given any failure, When handled, Then classification must NOT read the message string

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `ApiError` exposes `status`, `retryable`, `message`
  - `adminFetch` attaches the real HTTP status
  - Network failure → `status:null`, `retryable:true`
  - Existing `withDummyRead` behavior preserved
Must-not-have:
  - String-based error classification
  - New dependencies
Open question risks:
  - `fetch` mock in the test env does not expose `status` → report NEEDS_CONTEXT
Rollback note:
  - Revert to the plain `Error` throw
Red flags:
  - Message-string branching → STOP
  - Dummy path bypassed for admin reads → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all error scenarios yield the correct `ApiError` and the page can branch on `status`
Uncertain when: the test environment cannot surface HTTP status
Escalate when: `withDummyRead` semantics would change
