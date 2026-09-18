# Tech Stack & Version Pins

## Backend (`apps/api`)
- PHP `^8.2` (Docker image uses `php:8.3-fpm`), Laravel `^11.0`.
- Auth: `tymon/jwt-auth ^2.0` (JWT), `laravel/sanctum ^4.0` installed but intentionally unused (see `mem:core`).
- Installed but **unreferenced in `app/`**: `spatie/laravel-permission ^6.4`, `spatie/laravel-query-builder ^6.0`, `spatie/laravel-data ^4.0`.
- Dev: `phpunit ^11`, `laravel/pint`, `mockery`, `fakerphp/faker`, `nunomaduro/collision`, `laravel/telescope ^5`, `laravel/sail`.
- Required PHP extensions (Dockerfile): pdo_pgsql, mbstring, exif, pcntl, bcmath, gd, intl, zip, **redis (phpredis, via pecl)**. phpredis is mandatory because `config/database.php`/redis config defaults `REDIS_CLIENT=phpredis`.

## Frontend (`apps/web`)
- Next.js `^16.3` (App Router) + React `18.3` + TypeScript `^5.4`.
- State: `zustand ^4.5`. Charts/maps: `leaflet ^1.9` + `react-leaflet ^4.2`. Styling: `tailwindcss ^3.4`, postcss, autoprefixer.
- `axios ^1.7` is a dependency but the codebase uses `fetch` only.
- Dev/test: `jest ^29` + `jest-environment-jsdom` + `@testing-library/*`, Babel presets, `eslint ^8` + `eslint-config-next`, `sharp`.
- `next.config.js`: `output: 'standalone'`, `reactStrictMode: true`.

## Monorepo / tooling
- Package manager: **npm workspaces** (root `package.json` declares `apps/*` + `packages/*`).
- Root devDependency: `typescript ^5.4`.
- `packages/shared` (`@ddp/shared`) — `main`/`types` point at `src/index.ts`; has a stale committed `src/index.js` build artifact.

## Data stores / external
- PostgreSQL 16 (`postgres:16-alpine`); tests use sqlite `:memory:`.
- Redis 7 (`redis:7-alpine`) — cache/session/queue driver in compose (overrides .env `file`).
- Mailpit (`axllent/mailpit:latest`) — dev SMTP catcher.
- WhatsApp Cloud API `https://graph.facebook.com/v20.0` (external).

## Runtime bases
- `php:8.3-fpm`, `node:20-alpine`. Compose runs dev servers (`php artisan serve`, `next dev`) rather than the built images' production commands.
