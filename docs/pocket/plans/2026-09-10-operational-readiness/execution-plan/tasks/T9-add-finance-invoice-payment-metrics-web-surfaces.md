# Task T9 — Add finance invoice/payment/metrics web surfaces

**Phase:** 3
**Depends:** T2, T3, T4, T7, T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Add finance invoice/payment/metrics web surfaces [depends: T2, T3, T4, T7, T8]

## OBJECTIVE
Expose the approved finance workflow in the existing Next.js shell: finance can log in to payment/invoice/metrics pages, view bounded data, record payments, and see the six finance metric groups without changing API contracts. Preserve the existing delivery proof UI contract.

Files:
- Create `apps/web/src/app/invoices/page.tsx`.
- Modify `apps/web/src/app/payments/page.tsx`, `apps/web/src/app/dashboard/page.tsx`, `apps/web/src/components/LoginForm.tsx`, and `apps/web/src/components/Sidebar.tsx`.

Steps:
[no-tdd — structural UI adapter; API/E2E behavior is verified in Tasks 3, 4, 7, 8, and 10]
1. Implement the finance role label/navigation, invoice page, bounded invoice/payment requests and pagination metadata, payment idempotency payload, and six finance metric cards using existing Next.js UI/API conventions. Preserve the current delivery page payload; do not modify it here.
2. Verify the structural adapter: `npm --workspace apps/web run lint`.
   Expected: TypeScript catches any role/type/API-shape mismatch; no new dependency or test-environment setup is required.
3. Build the production bundle: `npm --workspace apps/web run build`.
   Expected: Next.js compiles all finance routes and existing pages successfully.
4. Refactor within listed UI files only: keep existing components/helpers, avoid generic utilities and new state libraries; re-run `npm --workspace apps/web run lint && npm --workspace apps/web run build`.
5. Commit: `git add apps/web/src/app/invoices/page.tsx apps/web/src/app/payments/page.tsx apps/web/src/app/dashboard/page.tsx apps/web/src/components/LoginForm.tsx apps/web/src/components/Sidebar.tsx && git commit -m "feat(web): add finance operational surfaces"`.

## REFERENCES LOADED
- Spec visibility, metrics, delivery proof, and finance access assumptions.
- `apps/web/src/app/payments/page.tsx`, `dashboard/page.tsx`, `delivery/page.tsx` — existing fetch/UI contracts.
- `apps/web/src/components/LoginForm.tsx`, `Sidebar.tsx`, `ui` components, and `apps/web/package.json` — role/login and test/build conventions.

## WHY THIS APPROACH
Complexity: standard. This is a thin adapter over approved API contracts, but it must avoid widening finance access in the UI and must preserve current delivery proof behavior.

## SANDWICH CONTEXT
[CRITICAL: Frontend changes must consume the approved API contracts and must not weaken server-side authorization or delivery proof validation.]
You are implementing finance web surfaces for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend existing Next.js pages/components.
Files in scope: the files listed for Task 9.
Available after: T2, T3, T4, T7, and T8 API contracts.
Architecture rule: use existing fetch/auth/UI conventions; no new dependency, API redesign, or client-side-only security.
[RESTATE: Frontend changes must consume the approved API contracts and must not weaken server-side authorization or delivery proof validation.]

## DELIVERABLE
- Given finance credentials, when invoice/payment pages load, then only authorized scoped data is shown with bounded pagination.
- Given finance records a valid payment, then the page sends the existing idempotency field and refreshes the invoice/payment state.
- Given finance metrics contain zero values, then the dashboard renders valid zero cards without division/UI errors.
- Given delivery completion, then the existing page still sends recipient name and proof URL while API remains authoritative.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: finance role acceptance, bounded requests, stable error states, API-contract fidelity, accessibility-preserving proof fields, lint/build/test green.
Must-not-have: no client-only authorization, no broad finance navigation to unrelated admin actions, no gateway/PWA/new dependency.
Open question risks: dashboard placement and invoice identifier display are intentionally implementation details; report only if API contracts must change.
Rollback note: revert page/component changes independently of backend records and APIs.

## STOP CONDITIONS
Escalate if the UI requires a new API contract, a new dependency, or bypasses server authorization.
