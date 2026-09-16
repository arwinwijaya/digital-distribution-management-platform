# Task T9 — Frontend — Sales Pages & Integration

**Phase:** 2
**Depends:** T6, T7
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Frontend — Sales Pages & Integration [depends: T6, T7]

## OBJECTIVE
Create sales frontend pages for order collection and performance dashboard, plus admin sales performance view. Includes cross-cutting integration verification.

Files:
- Create: `apps/web/src/app/sales/orders/page.tsx`
- Create: `apps/web/src/app/sales/orders/api.ts`
- Create: `apps/web/src/app/sales/performance/page.tsx`
- Create: `apps/web/src/app/sales/performance/api.ts`
- Create: `apps/web/src/app/admin/sales-performance/page.tsx`
- Create: `apps/web/src/app/admin/sales-performance/api.ts`
- Modify: `apps/web/src/app/sales/page.tsx` — add navigation links if needed

Steps:
1. Create sales orders page: order creation form for sales (outlet selector scoped to territory, product list, quantity inputs, submit).
   - API client: POST /sales/orders
   - Show territory-scoped outlets in selector
   - Verify: `cd apps/web && npm run build` succeeds

2. Create sales performance page (own): dashboard with target, achievement, percentage, order_count for current month.
   - API client: GET /sales/my-performance
   - Verify: `cd apps/web && npm run build` succeeds

3. Create admin sales performance page: view of all sales performance with period filter.
   - API client: GET /admin/sales/performance?period=YYYY-MM
   - Verify: `cd apps/web && npm run build` succeeds

4. Run full test suite (backend + frontend):
   `cd apps/api && php artisan test && cd ../web && npm test`
   Expected: all tests pass

5. Commit:
   `git add apps/web/src/app/sales/orders/ apps/web/src/app/sales/performance/ apps/web/src/app/admin/sales-performance/`
   `git commit -m "feat(web): add sales order form, sales performance dashboard, and admin sales view"`

[no-tdd — frontend pages are structural/UI tasks with build verification]

## REFERENCES LOADED
- spec — Frontend requirements for sales pages
- apps/web/src/app/sales/page.tsx — existing sales page
- apps/web/src/app/admin/sales/ — existing admin sales patterns

## WHY THIS APPROACH
Sales order form reuses existing outlet/product APIs. Performance dashboards fetch from new F5 endpoints.

Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Sales pages must scope outlet selector to sales user's territory — no unrestricted outlet access]
You are implementing Sales Frontend Pages for Phase 7.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Sales order form with territory-scoped outlets; performance dashboards
Files in scope: apps/web/src/app/sales/orders/, apps/web/src/app/sales/performance/, apps/web/src/app/admin/sales-performance/, apps/web/src/app/sales/page.tsx
Available after: T6 (sales orders + performance endpoints), T7 (promotion broadcast)
Architecture rule: Territory-scoped outlet selector; no new npm dependencies
[RESTATE: Sales outlet selector must be territory-scoped — no unrestricted outlet access]

## DELIVERABLE
Given sales user, When visiting /sales/orders, Then order form with territory-scoped outlets
Given sales user, When visiting /sales/performance, Then own performance dashboard
Given admin, When visiting /admin/sales/performance, Then all sales performance view

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Outlet selector scoped to sales user's territory
- Build succeeds without errors
- Performance data correctly displayed
- Jest render test per page where applicable

Must-not-have:
- Unrestricted outlet access for sales users
- Hardcoded performance data

## STOP CONDITIONS
Done when: all sales pages created, build succeeds, tests pass, commit created
Escalate when: build fails or territory scoping missing
