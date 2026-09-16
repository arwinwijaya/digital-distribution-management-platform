# Task T8 — Frontend — Admin Pages

**Phase:** 2
**Depends:** T2, T3, T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Frontend — Admin Pages [depends: T2, T3, T4, T5]

## OBJECTIVE
Create admin frontend pages for user management, outlet management, product management, and promotion management. Each page includes list view with filters, create/edit forms, and relevant detail views.

Files:
- Create: `apps/web/src/app/admin/users/page.tsx`
- Create: `apps/web/src/app/admin/users/api.ts`
- Create: `apps/web/src/app/admin/outlets/page.tsx`
- Create: `apps/web/src/app/admin/outlets/api.ts`
- Create: `apps/web/src/app/admin/products/page.tsx`
- Create: `apps/web/src/app/admin/products/api.ts`
- Create: `apps/web/src/app/admin/promotions/page.tsx`
- Create: `apps/web/src/app/admin/promotions/api.ts`

Steps:
1. Create admin users page: list with role filter, role assignment UI, pagination.
   - API client: GET /admin/users, PATCH /admin/users/{id}/role
   - Follow existing page patterns (apps/web/src/app/admin/)
   - Verify: `cd apps/web && npm run build` succeeds

2. Create admin outlets page: list with name/category/territory/is_active filters, edit form, scoring display, purchase history view.
   - API client: GET /admin/outlets, PATCH /admin/outlets/{id}, GET /admin/outlets/{id}/orders, GET /admin/outlets/{id}/summary
   - Verify: `cd apps/web && npm run build` succeeds

3. Create admin products page: list, price edit form, price history view.
   - API client: GET /products, PATCH /admin/products/{id}, GET /admin/products/{id}/prices
   - Verify: `cd apps/web && npm run build` succeeds

4. Create admin promotions page: CRUD list, create/edit form, broadcast trigger button.
   - API client: GET /admin/promotions, POST /admin/promotions, PATCH /admin/promotions/{id}, DELETE /admin/promotions/{id}, POST /admin/promotions/{id}/broadcast
   - Verify: `cd apps/web && npm run build` succeeds

5. Run full frontend test suite:
   `cd apps/web && npm test`
   Expected: all tests pass

6. Commit:
   `git add apps/web/src/app/admin/`
   `git commit -m "feat(web): add admin pages for users, outlets, products, and promotions"`

[no-tdd — frontend pages are structural/UI tasks with build verification]

## REFERENCES LOADED
- spec — Frontend requirements for all admin pages
- apps/web/src/app/admin/ — existing admin page patterns
- apps/web/src/app/ — existing page layout patterns

## WHY THIS APPROACH
One page per admin domain following existing Next.js patterns. API clients follow existing axios/zustand patterns.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Frontend pages must use existing Tailwind + Zustand + axios patterns — no new UI framework]
You are implementing Admin Frontend Pages for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: One page per admin domain using existing patterns
Files in scope: apps/web/src/app/admin/users/, apps/web/src/app/admin/outlets/, apps/web/src/app/admin/products/, apps/web/src/app/admin/promotions/
Available after: T2 (users), T3 (outlets), T4 (products), T5 (promotions)
Architecture rule: Use existing Next.js 16 + Tailwind + Zustand + axios patterns
[RESTATE: Frontend pages must use existing patterns — no new UI framework]

## DELIVERABLE
Given admin, When visiting /admin/users, Then user list with role filter displayed
Given admin, When visiting /admin/outlets, Then outlet list with filters and edit form
Given admin, When visiting /admin/products, Then product list with price edit and history
Given admin, When visiting /admin/promotions, Then promotion CRUD with broadcast trigger

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Each page uses existing page layout pattern
- API clients use existing axios patterns
- Build succeeds without errors
- No new npm dependencies
- Jest render test per page where applicable (list renders, filter interaction, form submit handler mocked)

Must-not-have:
- New UI framework or component library
- Hardcoded API URLs

## STOP CONDITIONS
Done when: all admin pages created, `npm run build` succeeds, tests pass, commit created
Escalate when: build fails or API endpoints not available
