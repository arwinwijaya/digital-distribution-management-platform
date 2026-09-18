# Suggested Commands

## Run stack (Docker, recommended)
- `docker compose up -d` — start all 5 services.
- `docker compose exec api php artisan migrate --seed` — required on first run; compose has NO auto-migrate step.
- Access: web http://localhost:3000, api http://localhost:8000 (`/api/health`), mailpit http://localhost:8025, db `localhost:5432` (ddp_user/ddp_password/ddp_database), redis `localhost:6379`.
- `docker compose logs -f api` / `docker compose logs -f web`.
- `docker compose up -d --build` — rebuild after Dockerfile changes.
- `docker compose down` (add `-v` to wipe postgres_data/redis_data).

## Root npm scripts (fan out to workspaces)
- `npm run dev` / `npm run build` / `npm run test` / `npm run lint`.
- `npm run docker:up` | `docker:down` | `docker:logs` | `docker:rebuild`.
- `npm run api:artisan -- <cmd>` (runs `php artisan` in `apps/api`).

## Backend (`apps/api`) — run from that dir
- `php artisan serve` (dev), `php artisan migrate`, `php artisan migrate --seed`.
- `php artisan test` or `vendor/bin/phpunit` — tests use sqlite :memory: (see phpunit.xml.dist).
- `vendor/bin/pint` — code style (Laravel Pint).
- Concurrency suites need PostgreSQL: `tests/Feature/Concurrency/*Postgres*`.

## Frontend (`apps/web`) — run from that dir
- `npm run dev` (next dev), `npm run build`, `npm start`.
- `npm test` (jest), `npm run test:watch`, `npm run test:e2e` (= `jest order-flow.test.js --runInBand`, boots a real PHP HTTP server vs sqlite; needs PHP on PATH).
- `npm run lint` = `tsc --noEmit` (type check, not eslint).

## Windows notes
- Development host is **Windows**; compose bind-mounts source for live reload. Laravel/Next boot is slow over Windows bind mounts, hence generous healthcheck `start_period` (60s).
- Use `docker compose exec api php artisan ...` rather than host PHP to avoid extension/version drift.
- Shell is Git Bash/PowerShell; prefer forward-slash paths and avoid unix-only flags that differ (e.g. `grep -P` may be unavailable).
