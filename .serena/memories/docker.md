# Docker Architecture

Single root `docker-compose.yml` with 5 services. Development-oriented (dev servers + bind mounts).

## Services
| svc | image/build | ports | volumes | depends_on (healthy) | healthcheck |
|-----|-------------|-------|---------|----------------------|-------------|
| db | postgres:16-alpine | 5432:5432 | postgres_data | - | pg_isready |
| redis | redis:7-alpine | 6379:6379 | redis_data | - | redis-cli ping |
| mailpit | axllent/mailpit:latest | 1025, 8025 | - | - | /mailpit readyz |
| api | build apps/api/Dockerfile | 8000:8000 | ./apps/api:/var/www/html | db,redis,mailpit | curl /api/health |
| web | build apps/web/Dockerfile | 3000:3000 | ./apps/web, ./packages/shared, anon node_modules/.next | api | wget / |

- Startup order: db/redis/mailpit (parallel) -> api (after all healthy) -> web (after api healthy).
- api command overrides image CMD: `php artisan serve --host=0.0.0.0 --port=8000` (Dockerfile CMD is `php-fpm`/EXPOSE 9000). web runs `npm run dev`.
- Networking: default compose bridge; containers address each other by service name (`db`, `redis`, `mailpit`).
- `NEXT_PUBLIC_API_URL=http://localhost:8000/api` is baked into web env AND next.config.js; browser reaches api via host-published 8000.
- Healthchecks use long `start_period` (60s) because Laravel/Next boot is slow over Windows bind mounts.

## Dockerfiles
- `apps/api/Dockerfile`: `php:8.3-fpm` + gd/intl/zip/pdo_pgsql + **phpredis via pecl** (mandatory; config defaults `REDIS_CLIENT=phpredis`). Composer install then `COPY apps/api .`; `composer dump-autoload`; chown www-data.
- `apps/web/Dockerfile`: `node:20-alpine`; copies root manifest + lockfile + workspace manifests -> `npm ci` -> copies `apps/web` + `packages/shared`.
- Root `.dockerignore` excludes `**/node_modules`, `**/vendor`, `.env*` (keeps host deps out of the build context and image deps intact).

## Operational gaps
- No migration/seed step on api start (run `docker compose exec api php artisan migrate --seed`).
- No queue-worker or scheduler container despite `QUEUE_CONNECTION=database` and a defined schedule.
- Secrets hardcoded in compose (APP_KEY, JWT_SECRET="jwt-secret-for-local", DB password). APP_DEBUG=true, Telescope enabled.
- Named volumes: `postgres_data`, `redis_data` (`docker compose down -v` wipes them).
