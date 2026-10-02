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

# ── 0. Storage dirs (api_storage volume is empty on first boot) ─────────────
# Must run BEFORE migrations/cache warm-up: config:cache + view:cache write into
# storage/framework, and a fresh named volume would otherwise be missing them.
mkdir -p \
  storage/fonts \
  storage/logs \
  storage/app/public/logos \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views
chown -R www-data:www-data storage 2>/dev/null || true
chmod -R 775 storage 2>/dev/null || true

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

# ── 2b. Optional seed (fresh volumes after `down -v`: schema exists, data gone)
# Only the primary `api` service sets RUN_SEED=1. Idempotent: all seeders use
# firstOrCreate, so re-running on a populated DB adds nothing.
if [ "${RUN_SEED:-0}" = "1" ]; then
  echo "[entrypoint] seeding database"
  php artisan db:seed --force --no-interaction
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

# ── 4. public/storage symlink so `public` disk files (template logos) resolve ─
if [ ! -e public/storage ]; then
  php artisan storage:link || echo "[entrypoint] storage:link skipped"
fi

# ── 5. Hand off ─────────────────────────────────────────────────────────────
exec "$@"
