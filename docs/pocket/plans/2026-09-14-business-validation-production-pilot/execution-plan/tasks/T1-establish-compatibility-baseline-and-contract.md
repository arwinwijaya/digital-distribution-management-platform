# Task T1 — Establish compatibility baseline and contract

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Establish compatibility baseline and contract [prereq]

## OBJECTIVE
Review and refine the existing compatibility contract and evidence baseline for every existing order-to-payment feature that must remain unchanged. This is documentation-only; do not add production code or pilot schema.

Files:
- Modify: `docs/pilot/pre-pilot-compatibility-matrix.md`
- Modify: `docs/pilot/pre-pilot-compatibility-baseline.md`

Steps:

1. Read the current implementation and relevant tests listed in the spec. Record, for each feature, public route, actor roles, success/error status codes, important response fields, idempotency behavior, concurrency guarantee, retry/lease behavior, and source tables.
2. Review/update `pre-pilot-compatibility-matrix.md` with rows for auth/RBAC, order, approval/invoice, delivery, payment, WhatsApp, analytics/finance/pipeline, web, database/config. Mark each row `preserve`, `additive`, or `blocked`; identify the exact regression command that proves it.
3. Review/update `pre-pilot-compatibility-baseline.md` with:
   - current role matrix (`admin`, `outlet`, `sales`, `driver`, `finance`);
   - current endpoint and response contract references;
   - known concurrency/idempotency cases;
   - source-of-truth boundaries;
   - baseline test commands;
   - explicit gaps and assumptions.
4. Verify documentation:
   ```bash
   test -s docs/pilot/pre-pilot-compatibility-matrix.md && \
   test -s docs/pilot/pre-pilot-compatibility-baseline.md && \
   rg -n "admin|outlet|sales|driver|finance|idempot|concurr|payment policy|WhatsApp|response|source of truth" docs/pilot/pre-pilot-compatibility-*.md
   ```
   Expected: both documents exist and every compatibility area has an evidence command or a clearly marked gap.
5. Review while green:
   - Remove any claim that a production pilot, partner activation, two-week window, or 100 valid orders has occurred.
   - Ensure “admin ops” is mapped to existing `admin`, not a new role.
6. Commit:
   ```bash
   git add docs/pilot/pre-pilot-compatibility-matrix.md docs/pilot/pre-pilot-compatibility-baseline.md
   git commit -m "docs(pre-pilot): refine feature compatibility baseline"
   ```

## REFERENCES LOADED

- `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md` — pre-pilot scope and compatibility contract.
- `apps/api/routes/api.php` — current route and middleware surface.
- `apps/api/app/Http/Controllers/OrderController.php` — order/approval response and authorization behavior.
- `apps/api/app/Services/OrderCreationService.php` — transaction, stock, credit, and order idempotency boundary.
- `apps/api/app/Services/PaymentService.php` — payment lock, cents-safe balance, policy, and replay boundary.
- `apps/api/app/Http/Controllers/DeliveryController.php` — delivery authorization and state transition boundary.
- `apps/api/app/Services/WhatsAppOutboundService.php` — provider idempotency, lease, and failure behavior.
- `apps/api/tests/Feature/OperationalReadinessTest.php` — established end-to-end evidence conventions.

## WHY THIS APPROACH

Complexity: standard. A written contract is needed before test expansion so regression tests protect real existing behavior rather than an invented pilot contract.

## SANDWICH CONTEXT

[CRITICAL: The pre-pilot phase must preserve existing feature behavior and must not activate or claim a production pilot.]
You are defining the compatibility baseline for the Digital Distribution Management Platform.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: the two documentation files listed above only.
Available after: none.
Architecture rule: document actual behavior from the repository; do not invent routes, roles, schema, or production evidence.
[RESTATE: The pre-pilot phase must preserve existing feature behavior and must not activate or claim a production pilot.]

## DELIVERABLE

Given the current repository, When an engineer reads the matrix, Then every existing feature has a compatibility promise, source-of-truth boundary, and verification command.
Given an assumption is not verifiable from code/tests, When the baseline is written, Then it is marked as a gap/assumption rather than presented as fact.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Exact existing roles/routes/test commands are cited.
- Compatibility and non-goals are explicit.
- No production pilot claim.

Must-not-have:
- No new API/schema proposal in this task.
- No invented role or tenant model.

## STOP CONDITIONS

Done when: both documents are reviewable and all areas in the spec matrix are covered.
Escalate when: current implementation and spec conflict on a compatibility promise.
