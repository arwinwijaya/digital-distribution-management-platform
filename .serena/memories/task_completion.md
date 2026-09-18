# Task Completion Checklist

Run the checks for whichever side(s) you changed. CI (`.github/workflows/ci.yml`) runs api-tests against a Postgres service and web checks on push/PR.

## Backend (`apps/api`) - run from that dir
- `vendor/bin/pint` - format/style (Laravel Pint).
- `php artisan test` (or `vendor/bin/phpunit`) - full suite; runs on sqlite `:memory:` per `phpunit.xml.dist` (`failOnRisky`/`failOnWarning` = true, so warnings fail the build).
- For changes touching concurrency/locking: also run the PostgreSQL-backed suites in `tests/Feature/Concurrency/*Postgres*` (need a real Postgres and opt-in test barriers via env, e.g. `ORDER_CONCURRENCY_BARRIER_*`, `WHATSAPP_CONCURRENCY_BARRIER_ENABLED`).
- Inside Docker: `docker compose exec api php artisan test`.

## Frontend (`apps/web`) - run from that dir
- `npm run lint` = `tsc --noEmit` - type check must pass.
- `npm test` - Jest unit/component suite.
- `npm run test:e2e` - real HTTP order-flow E2E (needs `PHP_BINARY`/php on PATH; boots a sqlite server). Run when touching the order flow end to end.

## Cross-cutting
- If you change API response shapes, update the matching frontend `api.ts` loader + its `*.test.ts` in the same change.
- If you add/rename a route, update `apps/web` loaders and any role-visibility logic in `src/components/Sidebar.tsx`.
- Do not commit build artifacts (`.phpunit.result.cache`, `tsconfig.tsbuildinfo`, `packages/shared/src/index.js`).
