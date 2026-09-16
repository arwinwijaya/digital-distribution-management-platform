# Task T2 — Central Authorization Policy & Role Management (F1)

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Central Authorization Policy & Role Management (F1) [depends: T1]

## OBJECTIVE
Implement platform_owner role support, central UserPolicy for authorization, general role assignment with audit and JWT invalidation, user listing with pagination, and user profile update. Fix the missing admin assertion on OutletController::updatePaymentTerms.

Files:
- Create: `apps/api/app/Policies/UserPolicy.php`
- Create: `apps/api/app/Services/UserRoleService.php`
- Create: `apps/api/app/Http/Controllers/UserRoleController.php`
- Create: `apps/api/app/Http/Requests/UpdateProfileRequest.php`
- Create: `apps/api/app/Http/Requests/AssignRoleRequest.php`
- Modify: `apps/api/app/Models/User.php` — add `isPlatformOwner()`, add `territory_id` to fillable
- Modify: `apps/api/app/Services/FinanceAuthorizationService.php` — add `isPlatformOwner()`, `assertAdminOrOwner()`
- Modify: `apps/api/app/Http/Controllers/OutletController.php` — add admin assertion to `updatePaymentTerms`
- Modify: `apps/api/routes/api.php` — add new routes
- Create: `apps/api/tests/Feature/RoleManagementTest.php`

Steps:
1. Write failing tests for: Platform Owner superset access
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent:
   Given a user with role=platform_owner, When calling GET /admin/users, Then access is granted (200)
   Given a user with role=admin, When calling PATCH /admin/users/{id}/role (role management endpoint), Then 403 forbidden
   Given a user with role=outlet, When calling GET /admin/users, Then 403 forbidden
   Exercise through: HTTP endpoints via authenticated requests
   Test doubles: none — real JWT auth with factory users
   Expected RED: UserPolicy does not exist; isPlatformOwner() does not exist; admin assertion missing from updatePaymentTerms

2. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected failure: Method not found or 403/500 errors

3. Implement UserPolicy with `viewAny`, `update`, `assignRole` methods checking platform_owner OR admin.
   Implement `isPlatformOwner()` on User model.
   Implement `assertAdminOrOwner()` on FinanceAuthorizationService.
   File: `apps/api/app/Policies/UserPolicy.php`, `apps/api/app/Models/User.php`, `apps/api/app/Services/FinanceAuthorizationService.php`

4. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected: PASS

5. Write failing tests for: Role assignment with audit + JWT invalidation
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent:
   Given admin assigns role sales→driver to user X, When checking audit log, Then from_role=sales, to_role=driver, actor=admin logged
   Given admin changes user X's role, When user X tries to use old refresh token, Then 401 forced re-login
   Given admin tries to assign invalid role "superuser", Then validation error returned
   Exercise through: HTTP POST /admin/users/{id}/role endpoint
   Test doubles: none — real JWT with RoleAssignmentAudit verification
   Expected RED: UserRoleService does not exist; no role assignment endpoint; no JWT invalidation on role change

6. Run test — verify FAIL:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected failure: Route not defined or service class not found

7. Implement UserRoleService (assign role with RoleAssignmentAudit + JWT invalidation via token blacklist/refresh revocation).
   Implement UserRoleController with `assignRole` action.
   Implement AssignRoleRequest validation (valid role enum, not 'superuser').
   File: `apps/api/app/Services/UserRoleService.php`, `apps/api/app/Http/Controllers/UserRoleController.php`, `apps/api/app/Http/Requests/AssignRoleRequest.php`

8. Run test — verify PASS:
   `cd apps/api && php artisan test --filter=RoleManagementTest`
   Expected: PASS

9. Write failing tests for: User profile update
   Test file: `apps/api/tests/Feature/RoleManagementTest.php`
   Level: integration
   Test intent:
   Given authenticated user, When calling PATCH /auth/me with name/email, Then profile updated and returned
   Given authenticated user, When calling PATCH /auth/me with role=outlet, Then 200 with role unchanged (ignored)
   Exercise through: HTTP PATCH /auth/me endpoint
   Test doubles: none
   Expected RED: AuthController::me is GET-only; no PATCH handler; role field not filtered

10. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected failure: Method not allowed (405) for PATCH /auth/me

11. Add `update` method to AuthController handling PATCH /auth/me with name/email only (role field ignored).
    Create UpdateProfileRequest validation.
    File: `apps/api/app/Http/Controllers/AuthController.php`, `apps/api/app/Http/Requests/UpdateProfileRequest.php`

12. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected: PASS

13. Write failing tests for: User listing with pagination
    Test file: `apps/api/tests/Feature/RoleManagementTest.php`
    Level: integration
    Test intent:
    Given admin, When calling GET /admin/users with role filter=sales, Then only sales users returned (paginated)
    Given outlet user, When calling GET /admin/users, Then 403 forbidden
    Exercise through: HTTP GET /admin/users endpoint
    Test doubles: none
    Expected RED: GET /admin/users route does not exist

14. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected failure: Route not defined

15. Implement UserRoleController::index with role filter, limit+1 pagination.
    File: `apps/api/app/Http/Controllers/UserRoleController.php`

16. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected: PASS

17. Write failing test for: Fix updatePaymentTerms missing admin assertion
    Test file: `apps/api/tests/Feature/RoleManagementTest.php`
    Level: integration
    Test intent:
    Given outlet user, When calling PUT /admin/outlets/{id}/payment-terms, Then 403 forbidden
    Exercise through: HTTP PUT endpoint
    Test doubles: none
    Expected RED: outlet user can currently update payment terms (missing assertion)

18. Run test — verify FAIL:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected failure: 200 instead of 403

19. Add `$this->authorization->assertAdmin($request->user())` to `updatePaymentTerms`.
    File: `apps/api/app/Http/Controllers/OutletController.php`

20. Run test — verify PASS:
    `cd apps/api && php artisan test --filter=RoleManagementTest`
    Expected: PASS

21. Refactor while green: Extract valid role constants to User model. Ensure UserPolicy is registered in AuthServiceProvider.
22. Commit:
    `git add apps/api/app/Policies/ apps/api/app/Services/UserRoleService.php apps/api/app/Http/Controllers/UserRoleController.php apps/api/app/Http/Requests/ apps/api/app/Models/User.php apps/api/app/Services/FinanceAuthorizationService.php apps/api/app/Http/Controllers/OutletController.php apps/api/routes/api.php apps/api/tests/Feature/RoleManagementTest.php`
    `git commit -m "feat(auth): add platform_owner role, UserPolicy, UserRoleService, user listing, profile update, and fix updatePaymentTerms admin assertion"`

## REFERENCES LOADED
- spec — F1 Role Management rules, all GWT scenarios
- apps/api/app/Models/User.php — existing role helpers, JWT claims
- apps/api/app/Services/FinanceAuthorizationService.php — existing isAdmin/assertAdmin pattern
- apps/api/app/Models/RoleAssignmentAudit.php — existing audit trail
- apps/api/app/Services/FinanceRoleService.php — existing assign/remove + audit pattern
- apps/api/app/Http/Controllers/AuthController.php — existing me endpoint (GET only)

## WHY THIS APPROACH
Reuses the FinanceRoleService audit pattern for general role assignment. Centralizes authorization in UserPolicy. Minimal new abstraction — extends existing patterns.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: platform_owner is superset of admin — every admin endpoint must grant access to platform_owner; every platform_owner-only endpoint must deny admin]
You are implementing Role Management (F1) for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Incremental service-layer extension; UserPolicy centralizes authorization
Files in scope: apps/api/app/Policies/, apps/api/app/Services/UserRoleService.php, apps/api/app/Http/Controllers/UserRoleController.php, apps/api/app/Http/Requests/, apps/api/app/Models/User.php, apps/api/app/Services/FinanceAuthorizationService.php, apps/api/app/Http/Controllers/OutletController.php, apps/api/routes/api.php
Test framework: Laravel PHPUnit with RefreshDatabase, JWT via login helper
Available after: T1 (migrations)
Architecture rule: string-role enum (no spatie); RoleAssignmentAudit for all role changes; JWT invalidation on role change forces re-login
[RESTATE: platform_owner superset of admin — owner has all admin access; admin does NOT have owner-only access]

## DELIVERABLE
Given user with role=platform_owner, When calling any admin endpoint, Then access is granted
Given user with role=admin, When calling platform_owner-only endpoint, Then 403
Given user with role=outlet, When calling any admin endpoint, Then 403
Given admin assigns role sales→driver, When checking audit log, Then from_role=sales, to_role=driver, actor=admin
Given admin changes role, When user uses old refresh token, Then 401
Given invalid role "superuser", When assigning, Then validation error
Given authenticated user, When PATCH /auth/me with name/email, Then updated
Given authenticated user, When PATCH /auth/me with role, Then role unchanged
Given admin, When GET /admin/users?role=sales, Then paginated sales users
Given outlet, When GET /admin/users, Then 403
Given outlet, When PUT /admin/outlets/{id}/payment-terms, Then 403

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- UserPolicy registered and used for admin endpoints
- isPlatformOwner() on User model
- UserRoleService uses DB::transaction + lockForUpdate (matching FinanceRoleService pattern)
- RoleAssignmentAudit created for every role change
- JWT invalidation on role change
- limit+1 pagination for user listing
- Admin assertion on updatePaymentTerms

Must-not-have:
- Modifications to payment/invoice/delivery core logic
- spatie/laravel-permission usage
- Role field accepted in profile update (silently ignored)

Open question risks:
- Platform Owner vs Admin endpoint matrix assumed owner=superset → if wrong: per-endpoint authz review needed

## STOP CONDITIONS
Done when: all F1 GWT scenarios pass, tests green, commit created
Escalate when: authorization policy grants access to wrong roles
