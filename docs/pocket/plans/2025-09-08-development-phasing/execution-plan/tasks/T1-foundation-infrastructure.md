# Task T1 — Foundation & Infrastructure

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Foundation & Infrastructure [prereq]

## OBJECTIVE
Set up the project foundation including monorepo structure, authentication, database schema, API structure, and deployment pipeline. This is the prerequisite for all subsequent phases.

Files:
- Create: `package.json` (root)
- Create: `composer.json` (apps/api)
- Create: `docker-compose.yml`
- Create: `.github/workflows/ci.yml`
- Create: `apps/web/` (Next.js app structure)
- Create: `apps/api/` (Laravel app structure)
- Create: `packages/shared/` (shared types)
- Test: `apps/api/tests/Feature/AuthTest.php`

Steps:
1. Write failing test for: Authentication system
   Test file: `apps/api/tests/Feature/AuthTest.php`
   Level: integration
   Test intent: Given valid credentials, When user logs in, Then JWT token is returned
   Exercise through: POST /api/auth/login
   Test doubles: mock external services
   Expected RED: Authentication endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter AuthTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Http/Controllers/AuthController.php`
   Implement: Login endpoint with JWT token generation

4. Run test — verify PASS: `cd apps/api && php artisan test --filter AuthTest`

5. Refactor while green (bounded):
   - Extract auth logic to service class
   - Re-run test: `cd apps/api && php artisan test --filter AuthTest`

6. Commit:
   `git add . && git commit -m "chore(auth): setup authentication system"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase0 Foundation
Phase0 deliverables: Project setup, authentication, database schema, API structure, deployment pipeline

## WHY THIS APPROACH
Complexity: standard
Justification: Foundation must be solid for all subsequent phases. Authentication is critical path.

## SANDWICH CONTEXT
[CRITICAL: Solo developer must be able to deploy and test the system]
You are implementing Foundation & Infrastructure for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: Root project structure, apps/api, apps/web, packages/shared
Available after: none (prereq)
Architecture rule: Monorepo with Next.js frontend + Laravel backend + PostgreSQL
[RESTATE: Solo developer must be able to deploy and test the system]

## DELIVERABLE
Given developer has access to repository, When running setup commands, Then project starts successfully
Given developer has credentials, When logging in, Then JWT token is returned
Given API is running, When making requests, Then responses are returned
Given CI/CD pipeline is configured, When pushing code, Then tests run automatically

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Authentication system works
  - Database migrations run successfully
  - API endpoints accessible
  - Deployment pipeline functional

Must-not-have:
  - Over-engineering (keep it simple for solo developer)
  - Unnecessary complexity

Open question risks:
  - None (greenfield project)

Rollback note:
  - If foundation fails, can restart from scratch

## STOP CONDITIONS
Done when: Authentication works, API accessible, deployment automated
Uncertain when: N/A (greenfield)
Escalate when: Cannot setup project structure
