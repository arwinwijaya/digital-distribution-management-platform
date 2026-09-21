# Pitch Exploration — RBAC Menu Access Matrix

**Date:** 2026-09-18
**Status:** Ready for grinding

---

## Problem

Sidebar and API use hardcoded boolean guards (`adminOnly` / `finance` / `deny.finance`)
instead of a dynamic matrix. There is no concept of `read` vs `edit` per menu.

**Root tension:** Simplicity (one enum column) vs flexibility (N×M matrix with action levels).
Must stay simple for 7 roles now, but extensible for new roles later.

**Constraints:**
- 7 roles today: `platform_owner`, `admin`, `supplier`, `outlet`, `sales`, `driver`, `finance`
- 17 nav items → 17 `menu_key` values
- 2 levels: `read` (view list/detail) / `edit` (create/update/delete/approve)
- `none` = invisible (filtered from sidebar, blocked in middleware)
- Matrix editor accessible to `platform_owner` + `admin`
- Enforcement: Laravel middleware per-route + sidebar filter
- Dummy mode: matrix is stored in Zustand, same defaults
- Fresh DB: default matrix seeded in `DatabaseSeeder`

**Success signal:** Admin opens "Kelola Akses" → edits a cell (e.g., `sales → Produk → read→edit`)
→ saves → affected role's sidebar and API immediately reflect the change.

---

## Brainstorm Summary

**Methods used:** Question Storming, First Principles, Solution Matrix, Six Thinking Hats
**Advisor curation:** Applied

### Key insights
1. 2-table minimal model is sufficient (no third permission table)
2. Seed safety is #1 risk — default matrix in DatabaseSeeder
3. Replace old guards, don't layer them
4. Fetch once, cache client-side (Zustand)
5. Sidebar filters on `level !== 'none'`; `edit` vs `read` only on per-page action buttons

### Discarded
- Audit log (out of scope MVP)
- Real-time hot-reload (save → re-fetch is enough)
- Action-level dropdowns in sidebar (sidebar = menus, actions = per-page)

---

## Default Matrix (Seed)

| Menu            | platform_owner | admin | outlet | supplier | sales | driver | finance |
|-----------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| dashboard       | edit | edit | edit | edit | edit | edit | read |
| invoices        | edit | edit | read | — | — | — | edit |
| orders          | edit | edit | edit | read | edit | read | read |
| products        | edit | edit | read | read | read | read | read |
| outlets         | edit | edit | read | — | read | — | read |
| marketplace     | edit | edit | edit | edit | edit | — | read |
| payments        | edit | edit | edit | — | read | — | edit |
| delivery        | edit | edit | read | — | read | edit | read |
| sales           | edit | edit | read | — | edit | — | read |
| analytics       | edit | read | — | — | — | — | — |
| data_intelligence | edit | read | — | — | — | — | — |
| operations      | edit | read | — | — | — | — | — |
| admin_orders    | edit | edit | — | — | — | — | — |
| admin_products  | edit | edit | — | — | — | — | — |
| admin_users     | edit | read | — | — | — | — | — |
| admin_promotions| edit | edit | — | — | — | — | — |
| admin_sales_performance | edit | edit | — | — | — | — | — |
| rbac_matrix     | edit | read | — | — | — | — | — |

Total: 18 menus (17 existing + 1 new RBAC page), 126 cells, ~50 non-none.

---

## Architecture Sketch (for grinding)

### DB
- `menu_definitions` — `key` (PK string), `label`, `group` (operasional/analitik/admin), `sort_order`
- `role_menu_access` — `role` (string, indexed), `menu_key` (FK), `level` (`none|read|edit`), composite PK `(role, menu_key)`

### Backend
- Middleware `RbacMiddleware` — reads `(role, menu_key)` → `level` from DB, compares to `required_level`
- Alias `rbac` in `Kernel.php`
- Routes: `->middleware('rbac:admin.users:read')` per endpoint (GET=read, POST/PATCH/DELETE=edit)
- Endpoints:
  - `GET  /admin/rbac/matrix` — return full matrix (owner+admin)
  - `PUT  /admin/rbac/matrix` — batch update (owner+admin, `level === 'edit'` cells for write)
  - `GET  /auth/me` — include `rbac: Record<string, 'read'|'edit'>` alongside `role`
- Dummy: `dummy/rbac.ts` — default matrix literal, `getDummyMatrix()` filtered by role

### Frontend
- Zustand store `useRbacStore` — `{ matrix: Record<string, Record<string, string>>, fetch(matrix), setCell, save() }`
- `Sidebar.tsx` refactor: replace `adminOnly`/`finance` guards with `matrix[role][menuKey] !== 'none'`
- Page `apps/web/src/app/admin/rbac/page.tsx` — grid 18×7 (rows=menus, cols=roles), cell = switch (—/read/edit), Save button
- Page-level guards: `useRequireRoleAccess(menuKey, 'edit')` hook for action buttons
- `Sidebar.test.tsx` — update to assert matrix-driven visibility

### Migration Plan
- Fresh seed: `DatabaseSeeder` seeds both `menu_definitions` + `role_menu_access`
- Existing DB: seeder is idempotent (`firstOrCreate`), safe to re-run `php artisan db:seed`

---

## Approach Directions

### Direction A: DB-Backed RBAC with Explicit Middleware (Recommended)
2 tables, per-route `->middleware('rbac:menu_key:level')`, Zustand store, sidebar filter.
+ Most explicit, debuggable, 1 query/request (cacheable).
− Each new route needs annotation.

### Direction B: Policy-Based (Laravel Gates)
Same tables, but `Gate::define()` / `can:view-menu` in controllers.
+ More idiomatic Laravel.
− Verbose policies, auth logic split across layers.

### Direction C: JSON Config File (No DB)
Matrix in `config/rbac.php`, versionable via git, no DB table.
+ Simplest, zero DB overhead.
− Admin can't change without deploy — violates requirement.

**Recommended: Direction A.**

---

## Open Questions (for grinding to resolve)

1. Fresh-DB fallback: if matrix row missing for a role→menuKey, default to `none` or to seeded default?
2. Should `platform_owner` be hardcoded superset (always edit everywhere) or just seeded as edit?
3. Page `/admin/rbac` URL slug — `/admin/rbac` or `/admin/access` or `/admin/permissions`?
4. Do we keep `deny.finance` as fallback or remove entirely?

---

## Next Step

→ Hand off to `pocket-grinding` with this pitch as INPUT context.
