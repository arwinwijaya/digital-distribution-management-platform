# Task T6 — Publish pre-pilot runbook and enforce final compatibility gate

**Phase:** 1
**Depends:** T2, T3, T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Publish pre-pilot runbook and enforce final compatibility gate [depends: T2, T3, T4, T5] [test-risk]

## OBJECTIVE
Document and verify the final gate that decides whether the platform is ready for a later pilot plan. The gate must prove existing compatibility, safe rollback, kill-switch behavior, diagnostics readiness, and absence of pilot activation.

Files:
- Create: `docs/pilot/pre-pilot-runbook.md`
- Create: `docs/pilot/pre-pilot-gate-checklist.md`

Steps:

1. Write failing gate checklist in `pre-pilot-gate-checklist.md` containing required evidence:
   - T2 API/authorization/idempotency/concurrency regression commands green;
   - T3 flag, kill switch, correlation, redaction, and migration rollback green;
   - T4 readiness/issues API green and read-only source snapshot unchanged;
   - T5 web tests/typecheck green;
   - no partner/territory activation, no pilot tagging, no 100-order KPI claim, no financial-ledger mutation;
   - explicit owner and sign-off fields for platform admin and product owner.
2. Write `pre-pilot-runbook.md` with:
   - how to configure safe defaults and enable/disable the pre-pilot operational surface;
   - readiness review and remediation ownership;
   - issue inbox handling and existing retry action links, without retrying from GET;
   - correlation ID investigation and redaction rules;
   - compatibility regression commands;
   - rollback/kill-switch procedure preserving transactions;
   - handoff requirements for a separate future production-pilot spec.
3. Verify documentation:
   ```bash
   test -s docs/pilot/pre-pilot-runbook.md && \
   test -s docs/pilot/pre-pilot-gate-checklist.md && \
   rg -n "READY_FOR_PILOT|kill switch|correlation|redact|idempot|concurr|rollback|source|ledger|100|partner|territory" docs/pilot/pre-pilot-*.md
   ```
   Expected: documents exist and explicitly distinguish pre-pilot readiness from pilot execution.
4. Run the final gate commands from the saved checklist:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotCompatibilityTest.php tests/Feature/PrePilotConcurrencyCompatibilityTest.php tests/Feature/PrePilotControlsTest.php tests/Feature/OperationalDiagnosticsTest.php
   cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx src/components/Sidebar.test.tsx && npm run lint
   ```
   Expected: PASS. Run the relevant existing suites from T2 as well; any failure blocks `READY_FOR_PILOT`.
5. Review while green:
   - Confirm the plan does not instruct implementation of a pilot data model or production pilot UI.
   - Confirm all new API/UI functionality is kill-switchable and source-read-only.
   - Confirm the runbook’s future pilot handoff has unresolved partner/date/KPI decisions explicitly listed.
6. Commit:
   ```bash
   git add docs/pilot/pre-pilot-runbook.md docs/pilot/pre-pilot-gate-checklist.md
   git commit -m "docs(pre-pilot): define readiness gate and rollback"
   ```

## REFERENCES LOADED

- T1–T5 packets and the pre-pilot spec.
- `docs/panduan-pengguna.md` and `docs/dokumentasi-teknis.md` — existing operator terminology.
- `apps/api/tests/Feature/OperationalReadinessTest.php` — operational evidence convention.

## WHY THIS APPROACH

Complexity: standard. The final gate combines API, middleware, persistence, diagnostics, and web evidence; documentation alone is insufficient without repeatable commands.

## SANDWICH CONTEXT

[CRITICAL: This gate can authorize only a later pilot handoff; it must never represent that a production pilot has run.]
You are closing the pre-pilot compatibility phase.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: the two runbook/checklist files only; test execution may read the repository but must not modify production code.
Available after: T2, T3, T4, and T5.
Architecture rule: all compatibility and source-immutability gates must be green; failure means `BLOCKED`, not a relaxed checklist.
[RESTATE: This gate can authorize only a later pilot handoff; it must never represent that a production pilot has run.]

## DELIVERABLE

Given all required regression, control, diagnostic, and web checks pass, When the checklist is reviewed, Then the result is `READY_FOR_PILOT` with owner/sign-off and rollback evidence.
Given any regression or source-mutation check fails, When the gate runs, Then result is `BLOCKED` and no pilot activation is permitted.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Commands are executable and tied to evidence.
- Rollback and kill switch preserve source transactions.
- Future pilot work is explicitly separated.

Must-not-have:
- No adoption claim.
- No fixture/demo treated as production evidence.
- No instruction to bypass failed tests.

## STOP CONDITIONS

Done when: runbook/checklist are validated and all T2–T5 gates pass.
Escalate when: any existing regression, redaction, kill-switch, or source-immutability check fails.

---

## Acceptance-Criteria Traceability

| Spec rule | Task and verification |
|---|---|
| Existing API/role/state/idempotency compatibility | T2 `PrePilotCompatibilityTest.php` public-boundary regression cycles |
| Same-key/different-payload returns 422; first order unchanged | T2 RED/GREEN cycle, payload fingerprint migration/service/controller assertions |
| Existing concurrency/locking/payment policy remains safe | T2 `PrePilotConcurrencyCompatibilityTest.php` with real DB/unique constraints |
| Flag default-off and kill switch isolation | T3 flag cycle; T4 disabled-route cycle; T5 disabled-page cycle |
| Correlation ID, journal, redaction, and fail-open behavior | T3 correlation/journal cycle |
| Readiness status, evidence, remediation, no mutation | T4 readiness cycle |
| Issue list/detail, filters, severity mapping, pagination, correlation lookup | T4 issue cycle and T5 filter/detail cycle |
| Admin-only operations web surface | T4 authorization cycle and T5 sidebar/page cycle |
| Rollback, ownership, `READY_FOR_PILOT`, and no pilot activation | T6 checklist/runbook validation and final gate commands |
