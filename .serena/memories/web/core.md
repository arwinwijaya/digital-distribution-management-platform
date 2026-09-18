# Frontend (`apps/web`)

Next.js 16 App Router SPA, React 18, TypeScript, Tailwind, Zustand. Talks to the API only via browser `fetch` with a Bearer token.

## Entry points
- `src/app/layout.tsx` - root layout; mounts `<DummyBootstrap/>` (registers dummy generator at module eval) + `<AppShell/>`.
- `src/app/page.tsx` - public landing; `AppShell` renders children bare (no chrome) when `pathname === '/'`.
- `src/app/manifest.ts` - PWA manifest.

## Routes (21 `page.tsx`)
Public: `/` (landing), `/outlets` (registration). App: `/dashboard`, `/orders`, `/products`, `/marketplace`, `/payments`, `/invoices`, `/delivery`, `/sales`, `/sales/orders`, `/sales/performance`, `/analytics`, `/data-intelligence`, `/operations`. Admin: `/admin/orders`, `/admin/outlets`, `/admin/products`, `/admin/users`, `/admin/promotions`, `/admin/sales-performance`.

## Layout / navigation
- `src/components/AppShell.tsx` - shell; hides sidebar/topbar on `/`.
- `src/components/Sidebar.tsx` - role-filtered nav. finance -> only `finance:true` items; admin/platform_owner -> all; others -> non-adminOnly. Admin sees `adminHref` override for outlets. Role resolved from `/auth/me` (or localStorage when dummy ON).
- `src/components/Topbar.tsx` - logout (clears token + reloads to `/`) and the dummy-mode toggle.

## Data layer
- `src/lib/api.ts`: `API_URL` (`NEXT_PUBLIC_API_URL` default `http://localhost:8000/api`), `apiUrl`, `authHeaders`, `getStoredToken/storeToken/clearStoredToken` (`localStorage.ddp_token`).
- Per-route loaders: `src/app/<route>/api.ts`, `src/lib/operations-api.ts`, `src/lib/data-intelligence-api.ts`.
- All loaders return typed shapes and throw on non-OK; pages hold data in local `useState`.

## Auth in the client
- Token + `ddp_role` in `localStorage`. Login (`LoginForm`) stores token, dispatches `ddp-auth-change` CustomEvent `{token, role}`.
- Components also listen to the `storage` event so multi-tab logout/login stays in sync.
- `LoginForm` accepts `expectedRole` and rejects wrong-role logins client-side.

## UI primitives
- `src/components/ui/*` (Button, Card, Input, Select, Textarea, Modal, Table, TablePagination/Summary/DensityToggle, Badge, StatusBadge, StatCard, EmptyState, Skeleton, PageHeader, ViewModeToggle) re-exported from `src/components/ui/index.ts`.
- Charts: `src/components/Charts.tsx`; maps: `src/components/data-intelligence/GeoMap.tsx` (leaflet).

See `mem:web/dummy_mode` for the offline dataset system.
