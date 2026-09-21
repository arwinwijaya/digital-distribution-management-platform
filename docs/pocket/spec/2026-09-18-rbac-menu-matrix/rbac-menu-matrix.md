# RBAC Menu Access Matrix

**Date:** 2026-09-18
**Status:** draft
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
**Pitch:** docs/pocket/spec/2026-09-18-rbac-menu-matrix/pitch-exploration.md

---

## Summary

Replace hardcoded boolean guards (`adminOnly` / `finance` / `deny.finance`) in `Sidebar.tsx` and `api.php` with a DB-backed RBAC matrix `role × menu → none | read | edit`. Matrix is seeded with sensible defaults for 7 roles, viewable and editable via a new admin page `/admin/rbac` (accessible to `platform_owner` + `admin`), enforced by a per-route Laravel middleware and a Zustand store on the frontend. Dummy mode mirrors the same matrix in-memory.

---

## Context

### Current State
- 18 `NAV_ITEMS` in `apps/web/src/components/Sidebar.tsx` with flags `adminOnly`, `finance`, `adminHref`. Role filtering is hardcoded: `finance` sees only `finance:true` items; `admin`/`platform_owner` see all; other roles see non-`adminOnly` items. No concept of `read` vs `edit` per menu.
- API routes in `apps/api/routes/api.php` use `deny.finance` middleware and controller-level `abort_unless` for authorization. No per-menu RBAC table.
- Auth: JWT via `tymon/jwt-auth`, `User.role` is a single enum column (7 values), `jwt_version` for stale-token rejection.
- Dummy: Zustand singleton `dummy:isDummy` in `apps/web/src/dummy/store.ts`, `withDummyRead` guards, in-memory entities via `buildFullDummy`.

### Problem / Motivation
- Adding a new role or changing who sees which menu requires a code change and deploy.
- No distinction between "can view" and "can mutate" — if a menu is visible, all actions are allowed.
- `finance` access is binary (3 menus) with no gradation.
- `platform_owner` is intended as superset of `admin` but this is implicit in `FinanceAuthorizationService`/`UserPolicy`, not explicit in a matrix.

### Related Areas
- `apps/web/src/components/Sidebar.tsx`
- `apps/web/src/components/Sidebar.test.tsx`
- `apps/web/src/dummy/{store,guards,index}.ts`
- `apps/api/app/Models/User.php`
- `apps/api/app/Http/Middleware/{DenyFinanceAdministration,RejectStaleJwt}.php`
- `apps/api/app/Http/Kernel.php`, `apps/api/routes/api.php`
- `apps/api/database/seeders/DatabaseSeeder.php`
- `apps/api/app/Services/FinanceAuthorizationService.php`, `apps/api/app/Policies/UserPolicy.php`
- `apps/web/src/app/admin/*` (orders, outlets, products, promotions, sales-performance, users)

---

## Scope

### In-Scope
- DB migration: `menu_definitions` (key PK, label, group, sort_order) + `role_menu_access` (role, menu_key FK, level `none|read|edit`, composite PK)
- Seed default matrix (18 menus × 7 roles) in `DatabaseSeeder.php` (idempotent via firstOrCreate)
- Laravel middleware `Rbac` with alias `rbac` in `Kernel.php`, per-route usage `->middleware('rbac:menu_key:required_level')`
- API endpoints: `GET /admin/rbac/matrix` and `PUT /admin/rbac/matrix` (both owner+admin)
- API response: `GET /auth/me` includes `rbac: Record<menu_key, level>` (full 18-key map for the caller's role)
- Zustand store `useRbacStore` (fetch, cache, save) following `dummy/store.ts` pattern
- `Sidebar.tsx` refactor: replace boolean guards with matrix-driven `level !== 'none'`
- New admin page `apps/web/src/app/admin/rbac/page.tsx` (grid 18 rows × 7 cols, cell = none/read/edit, Save)
- Dummy mode: `getDummyMatrix()` hardcoded in `apps/web/src/dummy/` mirroring DB seed
- Update `Sidebar.test.tsx` to assert matrix-driven visibility

### Out-of-Scope
- Audit log for matrix changes — deferred, can be added later as `rbac_change_logs`
- Real-time hot-reload without re-fetch — save → re-fetch is sufficient
- Role templates / "copy from role" — YAGNI for 7 hardcoded roles
- Page-level action button guards (per-page `edit` vs `read` checks) — matrix controls menu visibility + API; button-level checks are a follow-up
- Granular action levels (create/update/delete/approve as separate permissions) — 2 levels (`read`/`edit`) are sufficient per user decision

---

## Architecture Constraints

- Layers this work may touch: `apps/api` (migration, model, middleware, controller, routes, seeder), `apps/web` (Sidebar, Zustand store, admin page, dummy)
- Layers this work must NOT touch: `docker-compose.yml`, nginx, Telescope, data-intelligence pipelines
- Patterns that must be followed: `Kernel.php` middleware alias pattern; `api.php` route structure; Zustand store pattern (`dummy/store.ts`); `firstOrCreate` idempotent seeding
- DB: `menu_definitions.key` is string PK; `role_menu_access` composite PK `(role, menu_key)` with FK to `menu_definitions.key`
- Enforcement: middleware per-route (no global `Route::uri → menu_key` lookup map)
- Frontend: matrix fetched at login, cached client-side (like `GET /auth/me`)

---

## Dependencies

### Existing (to leverage)
- `zustand@4.5` — RBAC store (same pattern as dummy store)
- `tymon/jwt-auth@2.0` — auth context for middleware (`$request->user()->role`)
- `laravel/framework@11` — middleware, validation, Eloquent
- `next@16.3` / `react@18` — admin page, Sidebar
- `jest@29` — Sidebar.test update

### New (proposed)
- none — no new npm/composer dependencies required

---

## Default Matrix (Seed)

| key | label | group | platform_owner | admin | outlet | supplier | sales | driver | finance |
|-----|-------|-------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| dashboard | Dasbor | operasional | edit | edit | edit | edit | edit | edit | read |
| orders | Pesanan | operasional | edit | edit | edit | read | edit | read | read |
| products | Produk | operasional | edit | edit | read | read | read | read | read |
| outlets | Outlet | operasional | edit | edit | read | — | read | — | read |
| marketplace | Marketplace | operasional | edit | edit | edit | edit | edit | — | read |
| payments | Pembayaran | operasional | edit | edit | edit | — | read | — | edit |
| delivery | Pengiriman | operasional | edit | edit | read | — | read | edit | read |
| sales | Sales | operasional | edit | edit | read | — | edit | — | read |
| invoices | Invoice | operasional | edit | edit | read | — | — | — | edit |
| analytics | Analitik | analitik | edit | read | — | — | — | — | — |
| data_intelligence | Data Intelligence | analitik | edit | read | — | — | — | — | — |
| operations | Operasi | analitik | edit | read | — | — | — | — | — |
| admin_orders | Approval Pesanan | admin | edit | edit | — | — | — | — | — |
| admin_products | Harga Produk | admin | edit | edit | — | — | — | — | — |
| admin_users | Kelola Pengguna | admin | edit | read | — | — | — | — | — |
| admin_promotions | Kelola Promosi | admin | edit | edit | — | — | — | — | — |
| admin_sales_performance | Performa Sales | admin | edit | edit | — | — | — | — | — |
| rbac_matrix | Kelola Akses | admin | edit | read | — | — | — | — | — |

`—` = `none` (not stored or stored as `none`). ~50 non-none cells.

---

## Stories + Scenarios

### Story 1 — View RBAC matrix
> As a platform_owner or admin, I want to view the full role×menu access matrix, so that I know who can access what.

**Rule 1: Only owner+admin can read matrix**
- Example A: `admin` GET /admin/rbac/matrix → 200 with nested object.
- Example B: `sales` GET /admin/rbac/matrix → 403.
- Example C: unauthenticated GET /admin/rbac/matrix → 401.

```gherkin
Scenario: Admin views matrix
  Given user with role=admin is authenticated
  When GET /admin/rbac/matrix
  Then response 200 with body { status:"success", data:{ admin:{dashboard:"edit", ... 18 keys}, platform_owner:{...}, ... 7 roles } }

Scenario: Non-admin blocked from viewing matrix
  Given user with role=sales is authenticated
  When GET /admin/rbac/matrix
  Then response 403

Scenario: Unauthenticated blocked
  Given no token
  When GET /admin/rbac/matrix
  Then response 401

Scenario: Fresh DB with zero role_menu_access rows
  Given migration ran but role_menu_access is empty (seed not run)
  When admin GET /admin/rbac/matrix
  Then response 200 with all 18 keys = "none" for every role (no 500)
```

---

### Story 2 — Edit RBAC matrix
> As a platform_owner or admin, I want to update access levels for role×menu cells, so that permissions reflect operational needs.

**Rule 1: PUT accepts array of {role, menu_key, level} where level ∈ none|read|edit. All-or-nothing.**
- Example A: valid PUT → 200 with full updated matrix.
- Example B: empty array `[]` → 422.

**Rule 2: Admin cannot modify platform_owner row**
- Example: admin PUT [{role:platform_owner, menu_key:dashboard, level:read}] → entire request 403, no cell applied.

**Rule 3: Self-lockout protection**
- Example A: admin PUT [{role:admin, menu_key:rbac_matrix, level:none}] → 422 "cannot remove own edit access to rbac_matrix".
- Example B: platform_owner PUT [{role:platform_owner, menu_key:rbac_matrix, level:none}] → 422 as well.
- Example C: platform_owner PUT [{role:admin, menu_key:rbac_matrix, level:none}] → 200 (owner can modify admin).

**Rule 4: Invalid menu_key or level → 422, all-or-nothing**
- Example: PUT [{role:sales, menu_key:nonexistent, level:read}] → 422, no cell applied.

**Rule 5: Concurrency is last-write-wins (no locking)**

```gherkin
Scenario: Admin successfully updates sales access
  Given admin is authenticated and matrix has sales→products=read
  When admin PUT /admin/rbac/matrix with [{role:sales, menu_key:products, level:edit}]
  Then response 200 with full updated matrix where sales→products=edit

Scenario: Admin blocked from modifying platform_owner row
  Given admin is authenticated
  When admin PUT /admin/rbac/matrix with [{role:platform_owner, menu_key:dashboard, level:read}]
  Then response 403 and no cell in the request is applied

Scenario: Mixed valid+forbidden is all-or-nothing
  Given admin is authenticated
  When admin PUT with 5 valid cells + 1 platform_owner cell
  Then response 403 and none of the 5 valid cells are applied

Scenario: Self-lockout blocked
  Given admin is authenticated with admin→rbac_matrix=edit
  When admin PUT [{role:admin, menu_key:rbac_matrix, level:none}]
  Then response 422 error "cannot remove own edit access to rbac_matrix"

Scenario: Self-lockout blocked for platform_owner too
  Given platform_owner is authenticated with platform_owner→rbac_matrix=edit
  When platform_owner PUT [{role:platform_owner, menu_key:rbac_matrix, level:none}]
  Then response 422

Scenario: Platform_owner can downgrade admin
  Given platform_owner is authenticated
  When platform_owner PUT [{role:admin, menu_key:rbac_matrix, level:none}]
  Then response 200

Scenario: Invalid menu_key rejected all-or-nothing
  Given admin is authenticated
  When admin PUT [{role:sales, menu_key:nonexistent_menu, level:read}]
  Then response 422 and no cell applied

Scenario: Empty array rejected
  Given admin is authenticated
  When admin PUT /admin/rbac/matrix with []
  Then response 422 "at least one cell required"

Scenario: Invalid level rejected
  Given admin is authenticated
  When admin PUT [{role:sales, menu_key:products, level:write}]
  Then response 422
```

---

### Story 3 — Sidebar visibility
> As any authenticated user, I want sidebar to show only menus where my role has level != none.

**Rule 1: Sidebar reads matrix from Zustand store (populated at login from GET /auth/me).**
**Rule 2: Menu shown iff matrix[role][menu_key] !== "none" (missing key → "none").**
**Rule 3: Dummy mode uses in-memory dummy matrix with same filtering logic.**

```gherkin
Scenario: Finance sees limited sidebar
  Given user role=finance, matrix has finance→analytics=none, finance→payments=edit, finance→dashboard=read
  When user logs in and sidebar renders
  Then sidebar shows dashboard, payments, invoices, etc where level != none, and hides analytics, data_intelligence, operations, admin_*

Scenario: Admin sees admin menus
  Given user role=admin, matrix has admin→admin_users=read, admin→admin_orders=edit
  When sidebar renders
  Then sidebar shows Kelola Pengguna, Approval Pesanan, etc (all where admin level != none)

Scenario: Missing matrix row defaults to none
  Given role=sales, matrix has no row for sales→analytics
  When sidebar renders
  Then analytics menu is hidden

Scenario: Dummy mode mirrors DB matrix
  Given dummy ON, dummy matrix has outlet→products=read, outlet→analytics=none
  When outlet user renders sidebar in dummy mode
  Then products is visible, analytics is hidden

Scenario: Stale client cache shows menu that server would block
  Given admin changed sales→products to none while sales user has cached matrix with products=read
  When sales navigates to /products without re-login
  Then sidebar still shows products (stale) but GET /products returns 403 from middleware
```

---

### Story 4 — API enforcement via middleware
> As a system, I want every protected route to check role→menu_key against matrix, so that hidden menus are also blocked server-side.

**Rule 1: Routes annotated with `->middleware('rbac:menu_key:required_level')` check `role_menu_access`. Missing row → "none" → 403.**
**Rule 2: GET requires read, POST/PATCH/PUT/DELETE requires edit (enforced by the required_level param per route).**
**Rule 3: Middleware runs after `auth:api` + `reject.stale_jwt`; unauthenticated → 401 before RBAC.**

```gherkin
Scenario: Read allowed
  Given sales has products=read, route GET /products guarded by rbac:products:read
  When sales GET /products
  Then 200

Scenario: Edit blocked for read-only role
  Given sales has products=read, route POST /products guarded by rbac:products:edit
  When sales POST /products {name:"foo"}
  Then 403 {status:error, message:"Forbidden: requires edit on products"}

Scenario: Missing row blocks
  Given finance has no row for analytics (→ none), route GET /analytics guarded by rbac:analytics:read
  When finance GET /analytics
  Then 403

Scenario: Unauthenticated blocked before RBAC
  Given no token, route GET /products guarded by auth:api + rbac:products:read
  When GET /products
  Then 401 (not 403)
```

---

### Story 5 — Auth/me includes RBAC
> As a logged-in user, I want GET /auth/me to return my role and my RBAC map, so that sidebar can render without an extra fetch.

**Rule 1: Response includes `rbac: Record<menu_key, level>` with all 18 keys for the user's role (including "none" entries).**

```gherkin
Scenario: Auth me returns full RBAC map
  Given user role=sales is authenticated
  When GET /auth/me
  Then response includes {role:"sales", rbac:{dashboard:"edit", products:"read", ..., rbac_matrix:"none" (18 keys)} }

Scenario: Role with no rows returns all none
  Given user has a role with zero rows in role_menu_access
  When GET /auth/me
  Then rbac is {dashboard:"none", ... 18 keys all "none"} (not null, not missing)

Scenario: Dummy mode auth/me returns dummy RBAC
  Given dummy ON, outlet user
  When GET /auth/me (or dummy equivalent)
  Then rbac reflects dummy matrix for outlet
```

---

### Story 6 — Seed and migration
> As a deployer, I want `php artisan migrate --seed` to create menu definitions and the default matrix idempotently.

**Rule 1: Migration creates `menu_definitions` and `role_menu_access` with correct keys/constraints.**
**Rule 2: Seeder populates 18 `menu_definitions` and ~50 non-none `role_menu_access` rows via `firstOrCreate` (idempotent).**

```gherkin
Scenario: Fresh DB seed
  Given empty DB
  When php artisan migrate --seed
  Then menu_definitions has 18 rows and role_menu_access has ~50 rows

Scenario: Re-seed is idempotent
  Given DB already seeded
  When php artisan db:seed
  Then no duplicate rows, no error, counts unchanged

Scenario: Existing DB with data, new migration adds tables without affecting existing data
  Given DB has users/orders/etc but no RBAC tables
  When php artisan migrate
  Then RBAC tables created, existing data untouched, new seed can be run
```

---

## Acceptance Criteria

```
Rule: View matrix access
  ✓ Given admin is authenticated, When GET /admin/rbac/matrix, Then 200 with nested object {role:{menu_key:level}} 18×7
  ✓ Given finance is authenticated, When GET /admin/rbac/matrix, Then 403
  ✓ Given no token, When GET /admin/rbac/matrix, Then 401
  ✓ Given empty role_menu_access, When admin GET /admin/rbac/matrix, Then 200 with all "none"

Rule: Edit matrix
  ✓ Given admin & valid cells, When PUT /admin/rbac/matrix, Then 200 with full updated matrix
  ✓ Given admin tries to modify platform_owner row, When PUT contains role=platform_owner, Then 403 all-or-nothing (no cell applied)
  ✓ Given mixed valid+forbidden cells, When PUT, Then 403 all-or-nothing
  ✓ Given admin self-lockout (admin→rbac_matrix→none), When PUT, Then 422
  ✓ Given platform_owner self-lockout, When PUT, Then 422
  ✓ Given platform_owner modifies admin, When PUT admin row, Then 200
  ✓ Given invalid menu_key, When PUT, Then 422 all-or-nothing
  ✓ Given invalid level value, When PUT, Then 422
  ✓ Given empty array [], When PUT, Then 422
  ✓ Concurrent PUTs, When two admins save simultaneously, Then last-write-wins (no lock)

Rule: Sidebar visibility
  ✓ Given finance matrix, When sidebar renders, Then shows only menus where level != none
  ✓ Given missing row for role→menu, When sidebar renders, Then menu hidden (none fallback)
  ✓ Given dummy ON, When sidebar renders, Then uses dummy matrix same logic
  ✓ Given stale cache (matrix changed server-side), When user navigates, Then sidebar stale but API returns 403

Rule: API enforcement
  ✓ Given sales products=read, When GET /products (rbac:products:read), Then 200
  ✓ Given sales products=read, When POST /products (rbac:products:edit), Then 403
  ✓ Given missing row → none, When GET guarded route, Then 403
  ✓ Given no token, When GET guarded route, Then 401 (before RBAC)

Rule: Auth/me RBAC
  ✓ Given authenticated user, When GET /auth/me, Then includes rbac with 18 keys for caller's role
  ✓ Given role with zero rows, When GET /auth/me, Then rbac is 18 keys all "none"
  ✓ Given full map, Then missing key is never absent — always explicit "none"

Rule: Seed and migration
  ✓ Given empty DB, When migrate --seed, Then 18 menu_definitions + ~50 role_menu_access
  ✓ Given already seeded, When db:seed again, Then idempotent (no duplicates, no error)
```

---

## Design Decision

**Chosen: Direction A — DB-Backed RBAC with Explicit Middleware**

Per-route `->middleware('rbac:menu_key:required_level')` on every protected route in `api.php`, with a single `Rbac` middleware class registered as alias `rbac` in `Kernel.php`. Matrix fetched once at login via `GET /auth/me` (full 18-key map for caller's role) and cached in Zustand `useRbacStore`; sidebar filters on `level !== 'none'`.

**Rejected:**
- Direction B (Policy/Gate): more verbose, splits auth logic between routes and controllers, no benefit for menu-level checks.
- Direction C (JSON config file): violates requirement that admin can change matrix without deploy.

**Key tradeoffs accepted:**
- Each new route must add `->middleware('rbac:...')` annotation (explicit cost, but makes permission visible at route definition).
- Full 18-key map in `GET /auth/me` and `GET /admin/rbac/matrix` is slightly larger payload (~126 entries for full matrix) but eliminates frontend missing-key ambiguity.
- Last-write-wins for concurrent PUT (no DB locking) — acceptable for low-frequency admin-only endpoint.
- All-or-nothing PUT semantics — simpler client handling than per-cell partial success.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Fresh-DB fallback when row missing | assumed: `none` (menu invisible, API 403) | If wrong, new roles get unintended access. Mitigated by seed. |
| `platform_owner` superset | assumed: seeded as `edit` everywhere, but not hardcoded bypass — row in DB is source of truth | If platform_owner row deleted, owner loses access. Mitigated by self-lockout guard. |
| `/admin/rbac` URL slug | assumed: `/admin/rbac` for page and `/admin/rbac/matrix` for API | Easy to rename if product prefers `/admin/access` |
| `deny.finance` interaction | assumed: keep `deny.finance` removed/replaced where `rbac` middleware covers same routes; no double-guard stacking | If both remain, finance blocked twice (harmless but confusing). Planning must decide removal. |
| `adminHref` routing (e.g., `/admin/outlets` vs `/outlets`) | assumed: matrix controls visibility only; href logic stays in Sidebar.tsx | If matrix were to control href, additional column needed |

---

## Implementation Notes

- Middleware signature: `handle(Request $request, Closure $next, string $menuKey, string $requiredLevel)` — Laravel passes route middleware params as extra args.
- Level comparison: `none < read < edit` ordinal; `required=read` passes if stored is `read` or `edit`; `required=edit` passes only if stored is `edit`.
- `menu_definitions` is reference data; `role_menu_access` is the mutable matrix. `role` column is string enum (no FK to users table, since roles are hardcoded).
- Frontend `useRbacStore` should expose `levelFor(menuKey): 'none'|'read'|'edit'` and `canRead`/`canEdit` helpers.
- Sidebar refactor: replace `if (role === 'finance') ...` and `adminOnly` checks with `useRbacStore.getState().levelFor(item.key) !== 'none'`. Keep `NAV_ITEMS` but add `key` field matching `menu_definitions.key`.
- Dummy matrix: literal object in `apps/web/src/dummy/rbac.ts` (or `store.ts`) mirroring seed defaults; `getDummyMatrix(role)` returns `Record<string, level>`.

---

## Rollback Plan

- **DB:** `php artisan migrate:rollback` drops `role_menu_access` and `menu_definitions`. No data loss (RBAC tables are standalone).
- **Code:** revert `Kernel.php` alias, remove `Rbac` middleware, remove `/admin/rbac` page and `useRbacStore`, restore `Sidebar.tsx` boolean guards from git.
- **Seed:** no rollback needed; `DatabaseSeeder` RBAC seeding is additive and idempotent.
- **No feature flag required** — RBAC is additive; removing middleware restores previous hardcoded guards immediately.

---

## Edge Case Hunter Backstop

- [x] Edge case hunter dispatched after Phase 4 GWT scenarios
- [x] Blocking findings reviewed; 5 questions asked and resolved (all-or-nothing, full map, 422 empty, nested object + PUT full, last-write-wins)
- [x] Scenarios and acceptance criteria updated to reflect resolutions
- [x] Recommended scenarios incorporated (stale cache, empty matrix, platform_owner→admin downgrade, zero-row role, unauthenticated before RBAC)
