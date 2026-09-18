# Project: Digital Distribution Management Platform (DDP)

FMCG distribution ecosystem monorepo: suppliers -> distributors -> sales -> retail outlets.
Two apps + one (currently unused) shared package, orchestrated by a single docker-compose stack.

## Source map
- `apps/api/` — Laravel 11 JSON API (PHP 8.3). See `mem:api/core`.
- `apps/web/` — Next.js 16 App Router SPA (React 18/TS). See `mem:web/core`.
- `packages/shared/` — `@ddp/shared` TS types. NOT imported by apps/web; types are stale (missing finance/platform_owner roles). Treat as dead weight unless resurrected.
- `docker-compose.yml` (root) — 5 services: db, redis, mailpit, api, web. See `mem:docker`.
- `docs/` — technical/user/setup docs plus pre-pilot runbooks; `docs/pocket/{plans,spec}` holds planning notes.
- Root loose planning files: `idea.md`, `note.md`, `checklist.md`, `development-roadmap.md`, `.todo`.

## Project-wide invariants
- Auth is **JWT (`tymon/jwt-auth`)** via the `api` guard, NOT Sanctum cookie sessions. `EnsureFrontendRequestsAreStateful` is deliberately excluded from the `api` middleware group; `SANCTUM_STATEFUL_DOMAINS=""`. Re-adding it injects CSRF into `/api/*` and breaks login with 419.
- Roles are a plain `users.role` enum: `admin|supplier|outlet|sales|driver|finance|platform_owner`. `platform_owner` is a **superset of admin** — every admin endpoint must also grant platform_owner. Central gate: `FinanceAuthorizationService`.
- All business logic lives in `app/Services/*` (43 services); controllers are thin and validate via FormRequests. Correctness under concurrency relies on `DB::transaction` + `lockForUpdate` (orders, credit, invoices, payments).
- API responses are `{status:'success', data:...}` or `{status:'error', message|code}`. Every response carries `X-Correlation-ID` (AttachCorrelationId).
- Frontend stores auth in `localStorage` (`ddp_token`, `ddp_role`) and talks to the API only via browser `fetch` with `Authorization: Bearer`.
- Frontend ships an offline **dummy mode** (Zustand + seeded generator) that short-circuits reads with zero network. See `mem:web/core`.

## Cross-cutting behaviours
- Idempotent order creation: unique `orders.idempotency_key` + `idempotency_payload_hash`; retried/read-back on unique-key race.
- `reject.stale_jwt` middleware rejects tokens whose `jwt_version` claim differs from the DB (forces re-login after role change).
- `deny.finance` middleware blocks finance role from non-finance endpoints; `pre_pilot` middleware 503s when the pre-pilot gate is disabled.
- Scheduled jobs (`invoices:reminders` every minute, `data:pipeline` daily 02:00 Asia/Jakarta) are defined but NOT executed by the compose stack (no scheduler/queue container).

## Known gaps (do not "fix" silently; confirm with user)
- No queue-worker or scheduler container despite `QUEUE_CONNECTION=database`.
- No migration/seed step on api startup.
- Hardcoded secrets in docker-compose.yml (APP_KEY, JWT_SECRET, DB password).
- Unused deps: spatie/laravel-permission, spatie/laravel-query-builder, spatie/laravel-data, laravel/sanctum, axios, packages/shared.
