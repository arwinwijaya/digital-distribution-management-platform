# Backend API (`apps/api`)

Laravel 11 JSON API. Top-level map; deeper notes in the referenced memories.

## Entry points
- HTTP: `public/index.php` -> `bootstrap/app.php` (binds Http/Console Kernel + ExceptionHandler singletons) -> `App\Http\Kernel`.
- Routes: `RouteServiceProvider::boot()` maps `routes/api.php` under prefix `api` with the `api` middleware group. `routes/console.php` holds an `inspire` command.
- Console: `App\Console\Kernel` loads `app/Console/Commands/*` and defines the schedule.

## Layout
- `app/Http/Controllers/` (33) - thin controllers.
- `app/Http/Requests/` (25) - FormRequest validation.
- `app/Http/Middleware/` (11) - see `mem:api/auth`.
- `app/Services/` (43) - all business logic; see `mem:api/domain`.
- `app/Models/` (26) - Eloquent entities; see `mem:api/domain`.
- `app/Support/` - `ListQuery` (sort allowlist + pagination), `ConcurrencyTestBarrier` (test-only).
- `app/Contracts/WhatsAppClient.php` bound to `app/Services/WhatsAppHttpClient.php` in `AppServiceProvider`.
- `app/Policies/UserPolicy.php`, `app/Providers/*`, `app/Exceptions/Handler.php`.
- `config/` - app, auth, database, cache, session, jwt, orders, pre_pilot, whatsapp, telescope.
- `database/migrations/` (47), `database/seeders/` (`DatabaseSeeder`, `ScaleFixtureSeeder`), `database/factories/` (7).
- `tests/Feature/` (incl. `Concurrency/` and `Support/`), `tests/Performance/LoadTest.php`.

## Key config facts
- `config/auth.php`: `api` guard driver = `jwt`, provider = eloquent `App\Models\User`.
- `config/jwt.php`: `secret` falls back to `APP_KEY`; default TTL 1440 min (compose overrides to 60).
- `config/orders.php`: `commission_percentage` (snapshotted per order); test concurrency-barrier env keys.
- `config/whatsapp.php`: `enabled`, `webhook_secret`, `verify_token`, catalog limit, reminder schedule/backoff.
- `config/pre_pilot.php`: `enabled`, `kill_switch`.
- `AppServiceProvider::register` binds `AuthService` singleton, `WhatsAppClient -> WhatsAppHttpClient`, `UserPolicy`. `AuthServiceProvider` registers a `bearer-token` guard via `Auth::viaRequest`.

## Route surface (`routes/api.php`, ~99 declarations)
- Public: `POST /auth/register`, `POST /auth/login`, `POST /whatsapp/webhook` (HMAC-verified in controller), `GET /health`.
- All else under `auth:api` + `reject.stale_jwt`; many under `deny.finance`; `admin/operations/*` under `pre_pilot`.
- Duplicate/alias routes exist: `/orders` vs `/admin/orders`, `/finance/reminders` vs `/reminders`, payment-terms POST+PUT+GET.

See `mem:api/domain` for controllers/services/models, `mem:api/auth` for auth+middleware, `mem:api/request_flow` for the end-to-end path.
