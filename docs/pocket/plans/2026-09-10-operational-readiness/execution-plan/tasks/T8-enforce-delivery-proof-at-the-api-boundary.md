# Task T8 — Enforce delivery proof at the API boundary

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Enforce delivery proof at the API boundary [prereq]

## OBJECTIVE
Make recipient name and proof URL mandatory, nonblank, and valid only when transitioning a delivery to `delivered`, while retaining current authorization, state-transition, and proof response behavior.

Files:
- Modify `apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php` and `apps/api/app/Http/Controllers/DeliveryController.php` only as needed.
- Modify `apps/web/src/app/delivery/page.tsx` only if the existing required fields need a compatibility-preserving accessibility/error adjustment.
- Test `apps/api/tests/Feature/DeliveryTest.php`.

Steps:
1. RED/GREEN cycle — Required recipient:
   Test file: `apps/api/tests/Feature/DeliveryTest.php`. Level: integration. Test intent: Given an in-progress delivery, when delivered is submitted without a recipient or with whitespace, then validation rejects and delivery remains in progress. Exercise through `PATCH /api/deliveries/{id}/status`. Test doubles: no request/model/transaction mocks. Expected RED: current nullable rule allows omission.
   Run RED: `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --filter=delivered_requires_nonblank_recipient --testdox`.
   Implement conditional recipient validation, then run the same command and verify PASS.
2. RED/GREEN cycle — Required valid proof URL:
   Test file: `apps/api/tests/Feature/DeliveryTest.php`. Level: integration. Test intent: Given an in-progress delivery, when delivered is submitted without or with invalid proof URL, then validation rejects and state remains in progress. Exercise through the same PATCH boundary. Test doubles: none. Expected RED: current nullable URL rule allows omission.
   Run RED: `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --filter=delivered_requires_valid_proof_url --testdox`.
   Implement conditional URL validation, then run the same command and verify PASS.
3. Regression verification — Valid completion and non-delivery transitions:
   Test file: `apps/api/tests/Feature/DeliveryTest.php`. Level: integration. Test intent: Add named characterization tests `test_delivery_completion_persists_proof` and `test_non_delivery_transitions_do_not_require_proof`; given valid proof, completion persists/returns proof, and given assigned/in-progress transition, proof remains optional. Exercise through the existing PATCH boundary and assert persisted state. Test doubles: none. These are characterization checks because the existing implementation already satisfies them; they are not RED cycles.
   Run verification: `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --filter='test_delivery_completion_persists_proof|test_non_delivery_transitions_do_not_require_proof' --testdox`.
4. Refactor while green: keep transition-specific validation in the FormRequest and state mutation in the controller; run `cd apps/api && php artisan test tests/Feature/DeliveryTest.php --testdox`.
5. Commit: `git add apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php apps/api/app/Http/Controllers/DeliveryController.php apps/web/src/app/delivery/page.tsx apps/api/tests/Feature/DeliveryTest.php && git commit -m "fix(delivery): require proof before completion"`.

## REFERENCES LOADED
- Spec delivery proof GWT scenarios.
- `apps/api/app/Http/Requests/UpdateDeliveryStatusRequest.php`, `DeliveryController.php`, `DeliveryTest.php` — current validation, locks, authorization, and UI payload.
- `apps/web/src/app/delivery/page.tsx` — existing recipient/proof input contract.

## WHY THIS APPROACH
Complexity: lightweight. The UI already collects both fields; the missing guarantee is the API boundary.

## SANDWICH CONTEXT
[CRITICAL: Delivery completion must remain an authorized state transition and must not accept missing/blank proof.]
You are enforcing delivery proof for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — preserve the existing delivery flow.
Files in scope: the files listed for Task 8.
Available after: none (prerequisite task can run independently).
Architecture rule: preserve delivery authorization, row locks, status history, and recipient/proof response fields; no uploads/storage.
[RESTATE: Delivery completion must remain an authorized state transition and must not accept missing/blank proof.]

## DELIVERABLE
- Given an in-progress delivery, when recipient or valid proof URL is missing/blank/invalid, then API returns validation error and delivery remains in progress.
- Given valid recipient and URL, when delivered is submitted, then delivery becomes delivered and both fields are persisted and returned.
- Given assigned/in-progress transition not completing delivery, then proof remains optional.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: server-side conditional validation, persisted proof, current authorization and state-history behavior, direct API tests.
Must-not-have: no photo/signature upload, object storage, or UI-only enforcement.
Open question risks: none.
Rollback note: revert request/controller changes while retaining existing delivery records.

## STOP CONDITIONS
Escalate if enforcing proof requires changing delivery status names or driver/sales authorization.
