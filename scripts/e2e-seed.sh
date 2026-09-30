#!/usr/bin/env bash
set -euo pipefail

# Deterministic DB reset entrypoint used before every E2E run.
# Resets the database inside the api container via migrate:fresh --seed.
# No backend routes or schema changes — lightweight shell entrypoint only.

docker compose exec api php artisan migrate:fresh --seed --force
