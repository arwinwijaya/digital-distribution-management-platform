# Closeout — 2026-09-18-rbac-menu-matrix

- **Plan:** docs/pocket/plans/2026-09-18-rbac-menu-matrix
- **Type:** flat
- **Started:** 2026-09-19  ·  **Closed:** 2026-09-19
- **Baseline SHA:** 9cb3c61a8ee7f8bd41ddb35099e3fe040e76c041  ·  **Final SHA:** 422a2612c20d5a3165fd9b730fe2282a32f90628
- **Result:** CLOSED — all 12 tasks DONE

## Tasks

| Task | Name | done_sha | Status |
|------|------|----------|--------|
| T1 | RBAC schema + models + seeder | 6662ee2 | DONE |
| T2 | Seed default matrix in base TestCase | 37e0417 | DONE |
| T3 | Rbac middleware + Kernel alias | 579c448 | DONE |
| T4 | Matrix GET/PUT endpoint | c7233c9 | DONE |
| T5 | /auth/me rbac map | 4c50211 | DONE |
| T6 | Full route enforcement + remove deny.finance | 5adc9db | DONE |
| T7 | Align existing tests with new matrix | 5adc9db | DONE |
| T8 | Dummy RBAC fixture | 8ece397 | DONE |
| T9 | useRbacStore (Zustand) | 3eaa03d | DONE |
| T10 | Sidebar matrix-driven | b54433f | DONE |
| T11 | Admin /admin/rbac page | 3174be5 | DONE |
| T12 | Integration + dummy parity | 422a261 | DONE |

_SHA range: 9cb3c61a8ee7f8bd41ddb35099e3fe040e76c041..422a2612c20d5a3165fd9b730fe2282a32f90628_

## Verification

- **API suite:** `php artisan test` → 525 passed, 7 skipped (pgsql-only concurrency/load), 0 failed (3484 assertions).
- **Web suite:** `npx jest` → 487 passed, 0 failed (56 suites).
- **TypeScript:** `npx tsc --noEmit` → clean.
- **Migrations:** migrate + seed + idempotent re-seed + rollback verified on SQLite (portable; no Postgres-only features).

## Delivered

- **Schema & seed:** `menu_definitions` + `role_menu_access` tables (additive, reversible); 19 menus × 77 non-`none` default cells seeded idempotently via `MenuDefinition::CATALOG` (single source of truth).
- **Middleware:** `App\Http\Middleware\Rbac` (alias `rbac`) enforcing `rbac:<menu_key>:<required_level>` with ordinal `none < read < edit`; missing row → `none` → 403; fails closed on unknown level. Runs after `auth:api` (unauthenticated → 401 before RBAC).
- **Endpoints:** `GET`/`PUT /admin/rbac/matrix` (all-or-nothing, controller-level `assertAdminOrOwner`, decision K-A); `GET /auth/me` now returns `data.rbac` (19-key map).
- **Route enforcement:** every protected route annotated; legacy `deny.finance` middleware removed.
- **Frontend:** dummy RBAC fixture (`apps/web/src/dummy/rbac.ts`), `useRbacStore` (Zustand), matrix-driven `Sidebar`, and the `/admin/rbac` editor page — all with zero-network dummy parity.

## Decisions

- **K-A — Matrix endpoints bypass `rbac:` middleware.** The spec default gives `admin → rbac_matrix = read`, but Story 2 requires admin to PUT. Authorization is therefore at the controller level (`assertAdminOrOwner`); the matrix level governs menu *visibility* only.
- **K-B — Admin mutations under read-level menus.** Routes gated at `rbac:admin_users:read` as a menu gate; the controller's `isPlatformOwner`/`assertAdmin` is the real mutation authorization.
- **K-C (user-approved Option A) — Generalized K-B; spec default matrix kept 100% intact.** Only the route mapping in `api.php` was adjusted:
  1. `/ai/*` drops the `rbac:` gate — dual-audience endpoint authorized in `AIController` (`isAdmin() || isOutlet()`).
  2. `POST /admin/pipeline/manual-trigger`, `POST /admin/measurement/events`, `POST|PATCH /admin/territories*` gate at `read` — controllers enforce the admin write boundary (`isAdmin`).
  3. `POST /outlets` drops the `rbac:` gate — outlet self-service; finance denied in `StoreOutletRequest::authorize()` (runs before validation, so finance still gets 403 rather than 422).
  4. Race-DB setup (`SqliteHttpRaceCase::prepareRaceDatabase`) seeds `RbacMatrixSeeder` so HTTP race workers authenticate correctly.

  Rationale: the spec's `POST /outlets → outlets:edit` mapping contradicted its own default matrix (outlet holds only `read`), and 16 further failures exposed the same read-vs-edit gap for admin mutations and dual-audience routes. Keeping the spec matrix untouched and fixing the mapping was the minimal, spec-faithful correction.

## Carried Forward

Non-blocking observations — recorded for follow-up.

- **T6/T7 (Minor):** `deny.finance` semantics for `POST /outlets` were replaced by an outlet-self-service guard (`abort_unless(isOutlet())` / `StoreOutletRequest::authorize()`). Behaviour for finance is preserved (403), but the endpoint is now strictly outlet-only — no current test or consumer covers admin/sales/driver/supplier POST `/outlets`. Revisit if a staff-assisted outlet registration flow is ever needed.
- **T10 (Minor):** `Sidebar` visibility is driven purely by the cached matrix; a server-side matrix change is only reflected after re-login or a `/admin/rbac` save. This is the spec's accepted "stale cache" behaviour (server enforcement still returns 403). An explicit refresh affordance could be added later.
- **T10 (Minor):** `Sidebar.tsx` still contains the `adminHref` special case (admin → `/admin/outlets`) as a role check rather than a matrix rule; it is orthogonal to visibility and unchanged from before RBAC.
- **T12 (Minor):** `useRbacStore.save` was fixed to refresh the caller's own `map` when their role row is edited (otherwise an admin editing their own row would not see the Sidebar change until re-login). Verified by 2 store unit tests + the integration suite.
- **Docs (Minor):** `checklist.md` (audit 2026-09-16) still lists Phase 7 as future; it predates this work and should be refreshed to reflect RBAC as DONE.
- **Infra (Minor):** the 7 skipped API tests require `DB_CONNECTION=pgsql` + `pdo_pgsql` (Postgres concurrency/load); they are environment-gated, not failures.
- **Spec drift (Minor):** the spec text repeatedly says "18 menus / 18 keys"; the implemented catalog is **19** (the `worktree` menu was added per Decision 1). `GET /auth/me` returns a 19-key map and the matrix is 19×7. Spec prose is stale, not the implementation.

## Skipped Tasks

_None_ — all 12 tasks delivered.
