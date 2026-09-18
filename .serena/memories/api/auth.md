# API Auth & Middleware

## Authentication model
- Guard: `api` (driver `jwt`, provider eloquent `User`). Library: `tymon/jwt-auth`.
- `App\Models\User implements JWTSubject`; `getJWTCustomClaims()` adds `role` and `jwt_version`.
- `App\Services\AuthService`: `createToken`, `invalidateToken` (blacklist), `refreshToken`, `validateToken`.
- `AuthServiceProvider` also registers a `bearer-token` guard (`Auth::viaRequest`) for legacy/manual use.
- Sanctum is installed but the `api` middleware group intentionally EXCLUDES `EnsureFrontendRequestsAreStateful`; compose sets `SANCTUM_STATEFUL_DOMAINS=""`. Re-adding it injects CSRF into `/api/*` -> 419 on login.

## Middleware (aliases in `App\Http\Kernel`)
- Global: `TrustProxies`, `HandleCors`, `ValidatePostSize`, `TrimStrings`, `ConvertEmptyStringsToNull`.
- Group `api`: `ThrottleRequests:api` (60/min per user-or-IP, `RouteServiceProvider`), `SubstituteBindings`, `AttachCorrelationId`.
- Aliases: `auth` -> `Authenticate`; `deny.finance` -> `DenyFinanceAdministration`; `reject.stale_jwt` -> `RejectStaleJwt`; `pre_pilot` -> `PrePilotGate`.
- `AttachCorrelationId`: resolves/creates `X-Correlation-ID`, adds it to log context + response, and records a fail-open operational event (only when the pre-pilot gate is enabled).
- `RejectStaleJwt`: compares the token's `jwt_version` claim to `users.jwt_version`; mismatch -> 401 "Token invalid due to role change". Legacy tokens without the claim are allowed.
- `DenyFinanceAdministration`: 403 when `FinanceAuthorizationService::isFinance`; applied to order/catalog/sales routes.
- `PrePilotGate`: 503 `{status:'error', code:'pre_pilot_disabled'}` when `PrePilotFeatureGate::isEnabled()` is false.

## Authorization
- `FinanceAuthorizationService` is the single role gate: `isAdmin`, `isPlatformOwner`, `isFinance`, `isAdminOrOwner`, `assertAdmin`, `assertAdminOrOwner`, `assertPlatformOwner`, `assertFinance`. It re-reads the user from DB (so it reflects current `is_active`/role, not the token).
- `platform_owner` is a superset of `admin`. Admin endpoints must use `assertAdminOrOwner`.
- `UserPolicy` (constructed with `FinanceAuthorizationService`) registered in `AppServiceProvider`.
