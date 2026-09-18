# Code Conventions

## Backend (Laravel)
- Layering is strict: Route -> Controller (thin) -> Service (all logic) -> Eloquent Model. Controllers must not contain business rules; put them in `app/Services/*`.
- Request validation lives in `app/Http/Requests/*` FormRequests (25 of them), not inline `$request->validate()` (legacy exceptions exist, e.g. `AuthController::login`).
- Authorization via `FinanceAuthorizationService` (`assertAdmin`, `assertAdminOrOwner`, `assertPlatformOwner`, `assertFinance`). Never check `role === 'admin'` directly for admin endpoints - platform_owner must also pass.
- Every mutation touching orders/credit/invoices/payments must run in `DB::transaction` with `lockForUpdate()` on the aggregate root (outlet/order). Follow `OrderCreationService::create`.
- Money: compute in integer cents internally (`moneyToCents`), format to 2dp decimal strings at the boundary. Never float-compare currency.
- Idempotency: retryable writes carry a namespaced identity (see `OrderController::requestIdentity` + `Order::idempotency_key`/`idempotency_payload_hash`).
- JSON contract: `{status:'success', data:...}` / `{status:'error', message|code}`; use `abort_unless(...,403)` / `ValidationException::withMessages([...])` / `ConflictHttpException` for 403/422/409.
- Config via `config/*.php` + `env()` only; no `env()` calls outside config files.
- Named middleware aliases (Kernel): `auth`, `deny.finance`, `reject.stale_jwt`, `pre_pilot`, `throttle`, `can`, `signed`.
- `app/Support/*` holds pure helpers (`ListQuery`, `ConcurrencyTestBarrier`).

## Frontend (Next.js)
- App Router: one folder per route with `page.tsx`; data access lives in a sibling `api.ts` loader, NOT inline in the page.
- Interactive pages are `'use client'`; token read from `localStorage` via `@/lib/api` (`getStoredToken`, `authHeaders`, `apiUrl`). No server-side data fetching.
- Dummy-mode reads MUST be wrapped in `withDummyRead(isDummy, dummyValue, realFetch)` so no network occurs while dummy is ON. Writes are not guarded.
- Auth changes broadcast via `window.dispatchEvent(new CustomEvent('ddp-auth-change', {detail:{token,role}}))` and observed via the `storage` event; components must re-read token on both.
- Path alias `@/*` -> `src/*` (tsconfig + jest moduleNameMapper).
- UI primitives come from `@/components/ui` (Button, Card, Input, Table, Modal, ...); reuse instead of raw elements.
- Page tests live beside sources as `*.test.tsx`/`*.test.ts`.

## Style
- Backend formatting enforced by Laravel Pint. Frontend type-checked with `tsc --noEmit`; eslint config exists but the `lint` script runs tsc.
- Indonesian for user-facing copy (APP_LOCALE=id); code identifiers/comments in English.
