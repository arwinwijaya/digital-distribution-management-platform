# Closeout — 2026-09-22-phase8-mobile-field-operations

- **Plan:** docs/pocket/plans/2026-09-22-phase8-mobile-field-operations
- **Type:** phased (6 phases, 18 tasks)
- **Started:** 2026-09-22  ·  **Closed:** 2026-09-22
- **Baseline SHA:** 18f041312aab73beaac5d8fd22d098c12de5e8d3  ·  **Final SHA:** 2665894 (last task commit; docs closeout commit follows)
- **Result:** CLOSED — all 18 tasks DONE

## Tasks

| Task | Name | done_sha | Status |
|------|------|----------|--------|
| T1 | Field-ops schema (driver_profiles, visit GPS, PoD meta, location pings) | 6696c42 | DONE |
| T2 | DriverProfile model + relations + factory + seeder | 24762db | DONE |
| T3 | Bounded deterministic RoutingService (nearest-neighbor) | 0d111d8 | DONE |
| T4 | RBAC catalog + seeder (`driver_roster`, `field_ops`) | e5a8ebe | DONE |
| T5 | DriverRosterController CRUD + requests + routes | 121e34a | DONE |
| T6 | Visit check-in/check-out endpoints + radius validation | 610af38 | DONE |
| T7 | Delivery location ping endpoints (driver POST + admin track GET) | 54829b7 | DONE |
| T8 | Proof-of-delivery upload endpoint + filesystems config | 212fc95 | DONE |
| T9 | Wire RoutingService into DeliveryController@store + route admin | 2f37653 | DONE |
| T10 | Service worker + registration + offline page + Topbar indicator | e6c3bd6 | DONE |
| T11 | Offline order queue (IndexedDB wrapper + flush) | 868e7f5 | DONE |
| T12 | Offline queue integration into order form + status UI | da5db44 | DONE |
| T13 | Admin driver roster page (`/admin/drivers`) + api + table contract | a01f4a8 | DONE |
| T14 | Sales visit check-in/out UI + geolocation | 2a6c70d | DONE |
| T15 | Driver PoD capture UI (camera + canvas signature) | 7fd3cd6 | DONE |
| T16 | Admin live tracking page (GeoMap + polling) | 3db66ea | DONE |
| T17 | Dummy fixtures + NavItem + RBAC page wiring | e1bb13b | DONE |
| T18 | Cross-unit integration + full suite verification | 0973d53 / 2665894 | DONE |

_SHA range: 18f041312aab73beaac5d8fd22d098c12de5e8d3..2665894_

## Verification

- **API suite:** `php artisan test` → **595 passed, 7 skipped** (pgsql-only concurrency/load), **0 failed** (3791 assertions).
- **Web suite:** `npx jest` → **555 passed, 0 failed** (66 suites).
- **TypeScript:** `npx tsc --noEmit` → clean.
- **T18 integration tests:** `FieldOpsIntegrationTest` (API) 3 passed / 90 assertions; `field-ops-integration.test.tsx` (web) 1 passed.
- **Migrations:** 4 additive + reversible migrations; migrate/rollback clean on SQLite (portable, no Postgres-only types).

## Acceptance Criteria — verified

- **AC-1 Driver roster CRUD** — `POST/GET/PATCH/DELETE /admin/drivers` (T5, T13). Non-driver / duplicate-profile → 422; sales/outlet → 403. Verified by `DriverRosterTest` + `admin/drivers/page.test.tsx`.
- **AC-2 GPS check-in/out** — `POST /sales/visits/{id}/check-in|check-out` with outlet-radius validation; check-out completes the visit (T6, T14). Verified by `SalesVisitCheckinTest` + `SalesVisitCheckinTest`/`checkin.test.tsx`.
- **AC-3 Route optimization** — `RoutingService::plan()` nearest-neighbor, haversine, `MAX_STOPS` cap, deterministic tie-break; persisted to `route_data.stops` (T3, T9). Verified by `RoutingServiceTest` + `DeliveryRouteTest` + T18 route assertion.
- **AC-4 Live tracking** — `POST /deliveries/{id}/location` + `GET /admin/deliveries/{id}/track` (`last_position` + newest-first bounded pings) (T7, T16). Verified by `DeliveryTrackingTest` + T18 (last_position = newest ping, count 2, 50-cap).
- **AC-5 PoD capture** — `POST /deliveries/{id}/proof` stores photo + signature URLs + `pod_captured_at`/geo (T8, T15). Verified by `DeliveryProofUploadTest` + `PodCapture.test.tsx` + T18.
- **AC-6 PWA offline shell** — service worker + `/offline` + online indicator; offline order queue flushed to idempotent `POST /orders` on reconnect (T10–T12). Verified by `pwa/register.test.ts`, `order-queue.test.ts`, `OrderForm.offline.test.tsx`, and T18 web (offline → online flush → sent, queue empties).
- **AC-7 Frontend field surfaces** — admin roster, sales check-in/out, driver PoD capture, admin live-tracking map (T13–T16). Verified by the respective page/component suites.
- **AC-8 Parity & authorization** — dummy mode ON = zero network on all new pages; RBAC menus `driver_roster` + `field_ops` gate routes and nav; `platform_owner` == admin (T4, T17). Verified by `field-ops.test.ts`, `Sidebar.test.tsx`, `RbacFieldOpsCatalogTest`.

## Delivered

- **Schema (T1):** `driver_profiles`, `delivery_location_pings`; +7 GPS columns on `sales_visits`; +3 PoD metadata columns on `deliveries` — all additive + reversible.
- **Domain (T2):** `DriverProfile` model + `User::driverProfile()` relation + factory + seeder.
- **Routing (T3, T9):** bounded deterministic nearest-neighbor `RoutingService::plan()` → `route_data = {stops, total_distance_km}` persisted on delivery assignment.
- **RBAC (T4):** two new menus (`driver_roster`, `field_ops`) in `MenuDefinition::CATALOG` + default seeder (21 keys total).
- **API (T5–T9):** driver roster CRUD, visit check-in/out with radius guard, delivery location ping + admin track, PoD upload (disk-agnostic), route read.
- **PWA (T10–T12):** manual service worker (no `next-pwa`), offline fallback page, Topbar online/offline indicator, IndexedDB order queue with localStorage fallback and idempotent flush wired into `OrderForm`.
- **Frontend (T13–T16):** `/admin/drivers`, sales `VisitCheckin`, driver `PodCapture` (camera + canvas signature), `/admin/tracking` with `GeoMap` + polling.
- **Parity (T17):** deterministic dummy fixtures (`driverProfiles` 12, visits, PoD URLs), `NavItem` wiring, dummy track resolving driver names — zero network.
- **Verification (T18):** cross-unit API + web integration tests; full-suite regression run.

## Decisions

- **DD-1 — Additive, seam-preserving field operations.** Phase 8 extends existing `sales_visits`/`deliveries` rather than introducing parallel visit/delivery entities; only `driver_profiles` and `delivery_location_pings` are genuinely new. Keeps a single source of truth for order→delivery.
- **DD-2 — Routing as a bounded deterministic service.** Nearest-neighbor + haversine + `MAX_STOPS`, deterministic tie-break by id, so it is testable without ML and swappable by an external adapter.
- **DD-3 — PoD via a disk-agnostic upload endpoint.** `POST /deliveries/{id}/proof` stores through the filesystem abstraction (default `local`) and returns relative URLs — not locked to S3.
- **DD-4 — Client-side offline queue + idempotent replay.** IndexedDB queue flushed to the already-idempotent `POST /orders`; no new `/sync` endpoint, so no double-order risk.
- **DD-5 — Live tracking via bounded polling.** `GET /admin/deliveries/{id}/track` returns latest position + max N pings; fixed-interval frontend polling. No WebSocket/push in Phase 8.
- **T18 test-wiring corrections (test-only, no feature change):**
  - `check_in_latitude` is cast `decimal:7` → asserted as string (`'-6.2000500'`), not float.
  - `route_data` contract is `{stops, total_distance_km}` (per T3/T9 and `DeliveryRouteTest`); the T18 test's initial `waypoints` key was a slip and was corrected to `stops`.
  - The fixture delivery for the route assertion must be assigned through `POST /deliveries` (`assignThroughApi = true`) so `RoutingService::plan()` runs and `route_data` is populated; a direct `Delivery::create()` bypasses it.

## Carried Forward

Non-blocking observations — recorded for follow-up.

- **PoD storage (A-2, Minor):** PoD files use the `local` disk, not S3/object storage. Production object storage is deferred to Phase 6/10 as documented; the upload path is disk-agnostic so the swap is config-only.
- **Check-in radius (A-1, Minor):** radius defaults to 200 m via `orders.visit_radius_m`; acceptable for Phase 8 and configurable if field feedback requires tuning.
- **Tracking transport (A-4, Minor):** live tracking is polling-based (no WebSocket/push). Fine for Phase 8 scale; revisit if dispatcher latency becomes a concern.
- **Driver profiles optional (A-5, Minor):** `driver_profiles` may be empty for legacy drivers; assignment does not require a profile (backward compatible).
- **Route optimization scope (Minor):** nearest-neighbor only — no VRP/time-window solver. The `RoutingService` seam allows a future external adapter.
- **Spec prose drift (Minor):** the T18 task text mentions `route_data.waypoints`; the implemented and documented contract (T3/T9) is `route_data.stops`. Implementation and plan docs agree — only the task prose was stale.
- **Infra (Minor):** the 7 skipped API tests require `DB_CONNECTION=pgsql` + `pdo_pgsql` (Postgres concurrency/load); they are environment-gated, not failures.

## Skipped Tasks

_None_ — all 18 tasks delivered.
