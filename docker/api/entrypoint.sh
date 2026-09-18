#!/bin/sh
#
# Production entrypoint for the Laravel API image.
#
# Responsibilities:
#   1. Wait for the database to accept connections.
#   2. Optionally run migrations (only the primary `api` service sets
#      RUN_MIGRATIONS=1, so concurrent boots cannot race).
#   3. Warm Laravel's caches so requests never pay the compile cost.
#   4. exec the container command (php-fpm, queue:work, schedule:work, ...).
#
set -eu

echo "[entrypoint] starting: $*"

# ── 1. Wait for the database ────────────────────────────────────────────────
if [ "${WAIT_FOR_DB:-1}" = "1" ]; then
  attempt=0
  until php -r '
      $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;connect_timeout=2",
          getenv("DB_HOST") ?: "db",
          getenv("DB_PORT") ?: "5432",
          getenv("DB_DATABASE") ?: "ddp_database");
      try {
          new PDO($dsn, getenv("DB_USERNAME") ?: "ddp_user", getenv("DB_PASSWORD") ?: "");
          exit(0);
      } catch (Throwable $e) {
          exit(1);
      }
  ' 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
      echo "[entrypoint] database not reachable after 60 attempts, giving up" >&2
      exit 1
    fi
    echo "[entrypoint] waiting for database ($attempt)..."
    sleep 2
  done
  echo "[entrypoint] database is reachable"
fi

# ── 2. Migrations ───────────────────────────────────────────────────────────
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[entrypoint] running migrations"
  php artisan migrate --force --no-interaction
fi

# ── 3. Cache warm-up ────────────────────────────────────────────────────────
# config/event/route caches are mandatory and must succeed. view:cache is
# best-effort: a headless API may legitimately have no resources/views directory.
if [ "${WARM_CACHES:-1}" = "1" ]; then
  php artisan config:cache
  php artisan event:cache
  php artisan route:cache
  php artisan view:cache || echo "[entrypoint] view:cache skipped (no views)"
fi

# ── 4. Hand off ─────────────────────────────────────────────────────────────
exec "$@"
