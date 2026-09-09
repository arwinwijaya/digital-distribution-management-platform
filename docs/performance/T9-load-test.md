# T9 load benchmark

The benchmark is `apps/api/tests/Performance/LoadTest.php` and exercises the Laravel HTTP kernel through `curl_multi` with 100 authenticated concurrent requests across catalog, supplier listing, admin order listing, and dashboard reads. It reports wall-clock time, p50, p95, max, status failures, and per-path metrics. The SLO is p95 and max `< 2.0s`.

## Exact command

```sh
cd apps/api && php artisan test --filter Performance
```

The command also matches the existing bounded analytics tests. A real load run requires an already running API and tokens:

```sh
PERFORMANCE_BASE_URL=http://127.0.0.1:8000 \
PERFORMANCE_ADMIN_TOKEN=<admin-token> \
PERFORMANCE_OUTLET_TOKENS=<token-1>,<token-2>,...<token-100> \
php artisan test --filter LoadTest
```

Tokens are supplied explicitly so the benchmark does not log in 100 users during the measured interval. No provider credentials are used. `PERFORMANCE_LOCAL=true` enables the temporary SQLite fixture and local PHP workers, but the PHP built-in server/SQLite combination is not a production-concurrency substitute on Windows; if it cannot provide 100 concurrent sockets the test fails with status/timing evidence rather than claiming a pass.

## Representative fixture

`php artisan db:seed --class=Database\\Seeders\\ScaleFixtureSeeder` generates 500 outlets (100 active), 5 suppliers (4 active), 220 products, and 500 orders. It is a generator, not a committed fixture dump, and is idempotent by its fixture supplier marker.

## Correctness and cache boundary

Marketplace listing queries are bounded to 50 rows per page, join supplier state in SQL, and exclude inactive suppliers before pagination. Listing responses use `Cache-Control: no-store`; this avoids shared-cache leakage and makes supplier deactivation immediately visible. Feature coverage checks inactive-supplier filtering, immediate deactivation visibility, empty/error contracts, and listing indexes.
