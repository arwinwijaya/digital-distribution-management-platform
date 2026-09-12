# Task T2 — Add finance role, request-time authorization, and payment terms

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Add finance role, request-time authorization, and payment terms [depends: T1]

## OBJECTIVE
Implement admin-controlled single-role finance assignment/removal, audit it, enforce current-role authorization, and expose validated 1–90 day outlet payment-term configuration with a seven-day fallback contract.

Files:
- Create `apps/api/app/Services/FinanceAuthorizationService.php`, `apps/api/app/Services/FinanceRoleService.php`, `apps/api/app/Http/Controllers/FinanceRoleController.php`, `apps/api/app/Http/Requests/SetPaymentTermRequest.php`, and `apps/api/app/Http/Requests/SetFinanceRoleRequest.php`.
- Modify `apps/api/app/Models/User.php`, `apps/api/app/Models/Outlet.php`, `apps/api/app/Http/Controllers/OutletController.php`, and `apps/api/routes/api.php`.
- Test `apps/api/tests/Feature/FinanceAccessTest.php` and `apps/api/tests/Feature/PaymentTermTest.php`.

Steps:
1. RED/GREEN cycle — Admin assigns finance role:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given an active admin and active target, when the admin assigns finance, then the target has exactly finance and an audit exists. Exercise through the authenticated admin role-assignment HTTP route. Test doubles: none for auth/database; use real JWT and DB. Expected RED: route/service/audit do not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=admin_assigns_finance --testdox`.
   Implement only the assignment path, then run the same command and verify PASS.
2. RED/GREEN cycle — Repeated assignment:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given an active user already has finance, when admin assigns finance again, then the request succeeds without a duplicate role/audit or unrelated mutation. Exercise through the same HTTP route. Test doubles: none. Expected RED: no idempotent repeat behavior exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=finance_assignment_is_idempotent --testdox`.
   Implement repeat behavior, then run the same command and verify PASS.
3. RED/GREEN cycle — Inactive target rejection:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given an inactive target, when admin assigns finance, then the request is rejected and the target cannot access finance. Exercise through the same HTTP route. Test doubles: none. Expected RED: no active-user guard exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=inactive_user_cannot_receive_finance --testdox`.
   Implement the active-user guard, then run the same command and verify PASS.
4. RED/GREEN cycle — Single-role replacement:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given a user with another role, when finance is assigned, then exactly finance replaces the prior role and the change is audited. Exercise through the assignment route. Test doubles: none. Expected RED: role replacement is not implemented.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=finance_replaces_prior_role --testdox`.
   Implement replacement/audit behavior, then run the same command and verify PASS.
5. RED/GREEN cycle — Role removal invalidates old token:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given a finance token issued before removal, when admin removes finance and the user makes the next finance request, then the request is forbidden. Exercise through removal followed by a real protected HTTP request. Test doubles: none. Expected RED: stale role claims are not currently tested/guarded.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=removed_finance_role_denies_old_token --testdox`.
   Implement current-database-role resolution/removal, then run the same command and verify PASS.
6. RED/GREEN cycle — Finance cannot access unrelated administration:
   Test file: `apps/api/tests/Feature/FinanceAccessTest.php`. Level: integration. Test intent: Given a finance user, when unrelated user/product/marketplace/credit-limit/AI/sales/delivery-assignment/order-approval routes are requested, then each returns 403. Exercise through real HTTP routes; operational allow-list tests belong to their owning tasks. Test doubles: none. Expected RED: finance role and current-role guard are absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php --filter=finance_cannot_access_unrelated_administration --testdox`.
   Implement shared authorization and route checks, then run the same command and verify PASS.
7. RED/GREEN cycle — Valid payment terms:
   Test file: `apps/api/tests/Feature/PaymentTermTest.php`. Level: integration. Test intent: Given an authenticated admin and outlet, when an integer term from 1 through 90 is configured, then GET/PUT returns and persists it. Exercise through `/api/admin/outlets/{id}/payment-terms`. Test doubles: none. Expected RED: endpoint and field do not exist.
   Run RED: `cd apps/api && php artisan test tests/Feature/PaymentTermTest.php --filter=admin_can_set_valid_payment_term --testdox`.
   Implement the endpoint and persistence, then run the same command and verify PASS.
8. RED/GREEN cycle — Invalid payment terms:
   Test file: `apps/api/tests/Feature/PaymentTermTest.php`. Level: integration. Test intent: Given zero, negative, fractional, or >90 input, when configuration is attempted, then validation returns 422 and no term changes. Exercise through the same endpoint. Test doubles: none. Expected RED: no validation contract exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/PaymentTermTest.php --filter=invalid_payment_terms_are_rejected --testdox`.
   Implement integer/range validation, then run the same command and verify PASS.
9. Refactor while green: make every finance check call `FinanceAuthorizationService`; keep role assignment and term validation domain-scoped; run `cd apps/api && php artisan test tests/Feature/FinanceAccessTest.php tests/Feature/PaymentTermTest.php --testdox`.
10. Commit: `git add apps/api/app/Services/FinanceAuthorizationService.php apps/api/app/Services/FinanceRoleService.php apps/api/app/Http/Controllers/FinanceRoleController.php apps/api/app/Http/Requests/SetPaymentTermRequest.php apps/api/app/Http/Requests/SetFinanceRoleRequest.php apps/api/app/Models/User.php apps/api/app/Models/Outlet.php apps/api/app/Http/Controllers/OutletController.php apps/api/routes/api.php apps/api/tests/Feature/FinanceAccessTest.php apps/api/tests/Feature/PaymentTermTest.php && git commit -m "feat(auth): add finance role and payment terms"`.

## REFERENCES LOADED
- Spec finance access and terms GWT scenarios.
- `apps/api/app/Models/User.php`, `AuthController.php`, `routes/api.php` — single role and JWT request-time resolution.
- `apps/api/app/Http/Controllers/CreditLimitController.php`, `DeliveryController.php`, and existing authorization tests — must-not scope boundaries.

## WHY THIS APPROACH
Complexity: deep. It changes authorization without changing JWT response shape and must prove stale-token invalidation and endpoint isolation.

## SANDWICH CONTEXT
[CRITICAL: Finance authorization must read the current user role from the database on every protected request; stale JWT claims must never preserve access.]
You are implementing finance access and outlet payment terms for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — preserve the existing single-role model.
Files in scope: the files listed for Task 2.
Available after: T1 schema/models.
Architecture rule: use existing JWT/auth middleware and controller/request conventions; do not introduce Spatie permission migration or multi-role users.
[RESTATE: Finance authorization must read the current user role from the database on every protected request; stale JWT claims must never preserve access.]

## DELIVERABLE
- Given an active admin and active target, when finance is assigned twice, then exactly one finance role and one audit per actual change exist and the repeated assignment is idempotent.
- Given an inactive target, when assignment is requested, then it is rejected.
- Given a removed finance role and an old token, when the next finance request is made, then it returns 403.
- Given an outlet term, when 1–90 integer days are configured, then it is stored; invalid values return 422 and never reach invoice creation.
- Given a finance user, when unrelated admin endpoints are requested, then each is forbidden and no unrelated data is exposed.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: request-time role checks, audit persistence, active-user guard, outlet isolation, precise validation, and no auth response redesign.
Must-not-have: no broad permission graph, no multi-role array, no admin privilege leakage to finance.
Open question risks: none beyond the dashboard/invoice display assumptions.
Rollback note: revert role assignments through the admin route; leave existing role behavior intact.

## STOP CONDITIONS
Stop if current-user resolution cannot distinguish current role from stale claims, or if an unrelated controller needs a global architecture rewrite.
