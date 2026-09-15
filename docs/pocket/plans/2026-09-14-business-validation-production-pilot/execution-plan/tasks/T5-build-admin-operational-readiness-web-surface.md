# Task T5 — Build admin operational readiness web surface

**Phase:** 1
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: Build admin operational readiness web surface [depends: T4] [test-risk]

## OBJECTIVE
Add an admin-only `/operations` page that displays readiness checks, issue filters, safe issue details, and correlation references using the existing API/auth/sidebar conventions. Do not add a pilot transaction workflow or client-side source-of-truth computation.

Files:
- Create: `apps/web/src/app/operations/page.tsx`
- Create: `apps/web/src/app/operations/page.test.tsx`
- Create: `apps/web/src/lib/operations-api.ts`
- Create: `apps/web/src/lib/operations-types.ts`
- Modify: `apps/web/src/components/Sidebar.tsx`
- Test: `apps/web/src/components/Sidebar.test.tsx`

Steps:

1. Write failing test for: readiness and issue page states.
   Test file: `apps/web/src/app/operations/page.test.tsx`
   Level: component/integration
   Test intent:
   Given the readiness API returns `blocked` with remediation and the issue API returns failed WhatsApp/pipeline issues, When admin opens `/operations`, Then the page renders status, check evidence, remediation, issue source/status/severity/attempts/timestamps, and correlation ID; loading and HTTP error states are explicit.
   Given the API returns HTTP 503 with `code: "pre_pilot_disabled"`, When admin opens `/operations`, Then the page renders a disabled/activation-required state and does not expose controls that imply readiness or issue data is available.
   Exercise through: rendered page and `operations-api` against mocked fetch responses.
   Test doubles: mock only fetch responses; do not mock API client or page internals.
   Expected RED: page/types/API client do not exist.
2. Run test — verify FAIL:
   ```bash
   cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx
   ```
   Expected RED: operations page/types/API client are absent and disabled-gate state is unimplemented.
3. Implement typed `operations-types.ts`, `operations-api.ts` using `apiUrl`, `authHeaders`, and stored-token conventions, then render the page with explicit loading/error/blocked/ready states.
4. Run test — verify PASS:
   ```bash
   cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx
   ```
5. Write failing test for: filters and safe issue detail.
   Test file: `apps/web/src/app/operations/page.test.tsx`
   Level: component/integration
   Test intent:
   Given issues are displayed, When admin changes `source`, `status`, `severity`, `from`, `to`, `correlation_id`, `page`, or `limit` filters or opens a row, Then the API receives only allowlisted values, the list refreshes, safe details appear, and no raw payload/secret is rendered. Failed refresh preserves the last successful list with an alert.
   Test doubles: mock fetch responses only; do not mock `operations-api`, page state, or redaction presentation.
   Expected RED: filter serialization, detail loading, disabled response handling, and resilient refresh behavior are unimplemented.
6. Run test — verify FAIL using the same focused command.
   Expected failure: filter/detail assertions fail because the behavior does not yet exist.
7. Implement filters/detail presentation only; server remains responsible for authorization, filtering, redaction, and truth. Do not calculate readiness or severity in the browser.
8. Write failing sidebar test and run:
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: component
   Test intent:
   Given an admin, Operations is visible; given finance/outlet/sales/driver, no new admin operations link is shown according to existing navigation behavior.
   Test doubles: mock only session/role data; do not mock Sidebar internals.
   Expected RED: no Operations navigation entry or role assertion exists.
   ```bash
   cd apps/web && npm test -- --runInBand src/components/Sidebar.test.tsx
   ```
   Expected failure: Operations navigation assertions fail before implementation.
9. Add `/operations` to `Sidebar.tsx` using current role synchronization. Do not alter existing navigation labels/routes or AppShell layout.
10. Run all task tests and typecheck:
    ```bash
    cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx src/components/Sidebar.test.tsx && npm run lint
    ```
11. Refactor while green:
    - Reuse `api.ts` and existing UI primitives.
    - Keep response mapping in `operations-api.ts`; keep state in the page.
    - Never persist or recompute financial/order truth in the client.
12. Commit:
    ```bash
    git add apps/web/src/app/operations/page.tsx apps/web/src/app/operations/page.test.tsx apps/web/src/lib/operations-api.ts apps/web/src/lib/operations-types.ts apps/web/src/components/Sidebar.tsx apps/web/src/components/Sidebar.test.tsx
    git commit -m "feat(web): add pre-pilot operations surface"
    ```

## REFERENCES LOADED

- T4 operational API contract.
- `apps/web/src/lib/api.ts` — API URL, auth headers, and token conventions.
- `apps/web/src/components/Sidebar.tsx` and `AppShell.tsx` — role-aware navigation/layout.
- `apps/web/src/app/dashboard/page.tsx` — loading/error/session patterns.
- `apps/web/package.json` — Jest, Testing Library, and typecheck commands.

## WHY THIS APPROACH

Complexity: standard. The page combines readiness, filters, detail, auth-aware navigation, and resilient error states while deliberately remaining a diagnostic consumer.

## SANDWICH CONTEXT

[CRITICAL: The web surface consumes server diagnostics; it must not redefine authorization, readiness, severity, or transaction truth.]
You are implementing the admin pre-pilot operations surface.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: listed operations page/API/types/sidebar/test files only.
Available after: T4.
Architecture rule: reuse existing auth/API/UI conventions; no pilot transaction page and no client-side mutation.
[RESTATE: The web surface consumes server diagnostics; it must not redefine authorization, readiness, severity, or transaction truth.]

## DELIVERABLE

Given blocked/ready checks and operational issues, When admin opens the page, Then the server-provided status, evidence, filters, safe detail, and correlation reference are visible.
Given a non-admin role, When navigation renders, Then no new admin operations route is exposed through the sidebar.
[must-not] Given diagnostic data renders, When the browser processes it, Then it must not mutate or recompute order, invoice, payment, or readiness truth.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Existing auth/API/UI helper reuse.
- Explicit loading/error/blocked states.
- Server-owned filters/redaction/authorization.
- Web tests and typecheck green.

Must-not-have:
- No new auth abstraction.
- No pilot order/KPI workflow.
- No raw sensitive payload rendering.

## STOP CONDITIONS

Done when: page tests, sidebar tests, and typecheck pass.
Escalate when: the API contract requires client-side truth or a new role.
