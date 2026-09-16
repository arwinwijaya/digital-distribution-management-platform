# Dummy Mode — JABODETABEK (60 hari, semua menu)

**Date:** 2026-02-14
**Status:** approved
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md

---

## Summary

Menambahkan mode **Dummy** ke frontend Next.js: satu toggle di Topbar mengganti semua menu menjadi dataset deterministik & relasional (60 hari terakhir, 5 kota JABODETABEK) yang dihasilkan **client-side saja** tanpa satu pun request data ke backend. Mode ini berfungsi sebagai alat verifikasi fungsi (analitik, chart, GeoMap, KPI, tabel, paginasi, form mutasi) dan alat demo/presentasi. Valid untuk **semua environment dan semua role**; saat ON tidak mengubah data asli.

## Context

### Current State

- Frontend Next.js 16 (`'use client'` di semua halaman) memanggil 48 call-site `apiUrl()` yang tersebar di 23 file; tidak ada interception layer maupun store (Zustand ^4.5.0 sudah terinstall tapi belum dipakai).
- Dua pola wrapper dominan: `adminFetch<T>` (meng-throw error) dan `FetchResult<T>` (mengembalikan `{ ok, data, error }`); sebagian halaman memanggil `fetch` langsung (`analytics`, `dashboard`, `payments`, `delivery`, `orders`).
- Sidebar (13 item) memfilter menu berdasarkan role (logika finance/else/adminOnly); Topbar menampilkan badge Online + tombol Keluar yang conditional pada `hasToken`.
- Role di-resolve melalui `GET /auth/me` pada setiap mount; `apiUrl()` dan `authHeaders(token)` menggunakan `localStorage('ddp_token')`.
- Analitik (`/ai/recommendations|forecast|segmentation`) dan Data Intelligence (`/admin/analytics/geographic|suppliers|stock-planning|measurement/*` + GeoMap OSM + operasi) memiliki shape TypeScript yang terdefinisi dengan baik.

### Problem / Motivation

Tanpa data produksi, fungsi-fungsi visualisasi tidak bisa diverifikasi: halaman Analitik dan Data Intelligence menampilkan state kosong (`Belum ada rekomendasi`, `Data belum cukup`, GeoMap kosong), chart dasbor kosong, tabel pesanan/pembayaran/invoice/delivery tidak punya baris untuk menguji paginasi atau pergerakan status, dan mutasi form tidak bisa diuji end-to-end. Mode Dummy memperbaiki hal ini dengan mengganti **apa yang dikembalikan modul API**, bukan dengan mengganti tampilan downstream — sehingga agregasi, kalkulasi, dan chart yang sebenarnya tetap dijalankan.

### Related Areas

- `apps/web/src/components/Topbar.tsx`, `apps/web/src/components/Sidebar.tsx`
- `apps/web/src/lib/api.ts`, `apps/web/src/lib/data-intelligence-api.ts`, `apps/web/src/lib/operations-api.ts`, `apps/web/src/lib/data-intelligence-types.ts`, `apps/web/src/lib/operations-types.ts`
- `apps/web/src/app/admin/outlets/api.ts`, `products/api.ts`, `promotions/api.ts`, `sales-performance/api.ts`, `users/api.ts`, `sales/orders/api.ts`, `sales/performance/api.ts`, halaman `analytics`, `dashboard`, `payments`, `delivery`, `operations`, `sales`, `admin/orders` + komponen

---

## Scope

### In-Scope

- Toggle **Mode Dummy** di Topbar antara Online & Keluar — terlihat bila login, untuk semua role, di semua environment; status `isDummy` di-persist ke `localStorage`.
- Zustand slice baru (`isDummy`, `dummyEntities`, `role`, `toggle`, `refreshDummy`): **generate-once** (deterministik, seed tetap) dataset relasional (~48 outlet / ~30 produk / ~8 supplier / ~900 order/60d; 1:1 payment/invoice/delivery) dengan koordinat JABODETABEK; rolling window timestamps (`now − 60d` → `now`).
- Guard di level modul API + helper halaman inline-fetch: `isDummy ? dummyX() : fetchX()` — **zero backend data network** saat ON (role & dummy dibaca client-side).
- **Persist role** saat login ke `localStorage('ddp_role')`; Sidebar membaca role dari localStorage ketika Dummy ON (tanpa `/auth/me`).
- Cakupan menu **penuh** (while ON, zero data network): Dasbor (metrics + tren + kinerja outlet + `FinanceMetrics` finansial), Invoice, Pesanan, Produk, Outlet, Marketplace, Pembayaran, Pengiriman, Sales, Analitik (`/ai/recommendations|forecast|segmentation`), Data Intelligence (`geographic` tabel+map_points 40–60, `suppliers`, `stock-planning`, `measurement/recommendations`, `measurement/forecasts`), Operasi (`/readiness` + `/issues` + `/issues/:id`).
- Fungsi di atas harus benar-benar terverifikasi: state finding yang kosong **tidak boleh** muncul saat ON.
- Event pelacakan `POST /admin/measurement/events` (`sendFunnelEvent`) di-guard — fake sukses, zero network (durasi interaksi).
- Fake mutasi lokal yang writable saat ON: semua POST/PATCH (termasuk pelacakan) di-routing ke store dummy; mutasi ephemeral (hilang saat refresh/toggle OFF); ID baru di-prefix `dummy-` (atau integer negatif untuk tipe numerik); side-effect relasional (order → payment/invoice/delivery; status delivery).
- Auto re-fetch data real saat toggle OFF (tanpa navigasi); in-flight fetches: **best-effort discard** (guard di titik commit state; tidak ada threading `AbortController`).

### Out-of-Scope

- Perubahan apa pun pada backend Laravel / database / migration / `docker-compose` — termasuk endpoint dummy di API (alasan: arsitektur yang disepakati adalah client-side murni).
- Persistensi mutasi dummy lintas toggle OFF/refresh (alasan: mode ini sementara; persist lintas sesi menyulitkan reasoning tentang data).
- Perbaikan scope menu per-role yang lebih tajam untuk `driver`/`sales` di Sidebar (alasan: perilaku saat ini `else → all non-adminOnly` dipertahankan; fix role adalah isu terpisah).
- Penambahan dependensi produksi baru tanpa justifikasi (alasan: Zustand, Jest, dll. sudah terinstall).

---

## Architecture Constraints

- Layers that may be touched: `apps/web/src` (lib, app/*, components/*, new store under `apps/web/src/store/*` atau colocated `apps/web/src/lib/dummy/*`)
- Layers that MUST NOT be touched: `apps/api` (Laravel), `database/*`, `docker-compose.yml`, top-level infra
- Patterns that must be followed: konvensi `apiUrl(...)` / `authHeaders(token)` / `getStoredToken()`; `'use client'`; hirarki Sidebar/Topbar yang ada; Zustand ≥ 4.5.0 yang sudah terinstall (tanpa dep baru)
- Architecture validation result: PASS (Phase 6 — 8 checklist passed; no new dependency, no migration, no security regression, performance: dataset kecil)

---

## Dependencies

### Existing (to leverage)

- `zustand ^4.5.0` — dummy slice + persist kecil (tanpa dep baru)
- `next ^16.3.4` + `react ^18.3.0` — Topbar/Sidebar/`'use client'` conventions (sudah ada)
- `leaflet ^1.9.4` + `react-leaflet ^4.2.1` — GeoMap dummy points (sudah ada)
- `jest ^29.7.0` + `@testing-library/react ^15` — unit tests untuk RNG/util/interval (sudah ada)

### New (proposed)

- none. Jika `zod` diusulkan untuk validasi shape nanti, harus dijustifikasi terpisah (mis. “menangkap ketidakcocokan dummy di build”). Hand-rolling untuk RNG ter-seed dan aritmetika tanggal diterima di sini (commodity scope kecil, tidak ada crypto/auth).

---

## Stories + Scenarios

### Story 1: Toggle Mode Dummy di Topbar

> As a logged-in user (any role), I want a Mode Dummy toggle between Online and Keluar, so that I can demo and verify every menu on deterministic dummy data.

**Rule: visibility, persistence, role-offline, auto-re-fetch**

- A: admin with Dummy OFF toggles ON → all menus show only dummy data (zero data network), GeoMap 40–60 points.
- B: user refreshes with Dummy ON → same deterministic data, same toggle state.
- C: logged-out (no token) → no toggle shown.
- D: Dummy ON on `/analytics`, toggle OFF → real data re-fetches automatically; dummy disappears.

```gherkin
Scenario: Toggle ON shows only dummy, offline
  Given logged in as admin with Dummy OFF
  When  the Mode Dummy toggle is clicked ON
  Then  all menus render only dummy data (zero backend data requests)
  And   the Data Intelligence GeoMap renders between 40 and 60 points

Scenario: Refresh preserves dummy determinism
  Given Dummy ON
  When  the page is refreshed (F5)
  Then  Dummy stays ON and the identical deterministic dataset renders (same counts and values)

Scenario: No toggle when logged out
  Given not logged in (no token)
  When  any page loads
  Then  no Mode Dummy toggle is rendered

Scenario: Toggle OFF auto re-fetches real data
  Given Dummy ON on /analytics as admin
  When  the Mode Dummy toggle is clicked OFF
  Then  real backend data re-fetches automatically without navigation
  And   dummy data is no longer displayed (role is resolved via backend /auth/me)
```

### Story 2: Dummy read path per menu (incl. Analitik + Data Intelligence)

> As a user with Dummy ON, I want every menu to render from the dummy store through the same functions so I can verify they work.

**Rule: API-module guards, relational determinism, Analitik populated, Data-Intelligence populated, Finance dashboard populated, funnel guarded**

- A: any dummy call `fetchX()` → `dummyX()`; inline-fetch helpers guarded identically; zero backend network.
- B: `dummy-001` outlet appears consistently in orders, payments, invoices, deliveries, map.
- C: `/analytics` shows recommendations (non-empty), 4-period forecast, non-empty segmentation — "Belum ada rekomendasi" / "Data belum cukup" must NOT appear.
- D: `/data-intelligence` geographic table non-empty, map 40–60 points inside JABODETABEK bbox; suppliers, stock items, measurement funnel/rates present.
- E: finance user on `/dashboard` sees FinanceMetrics cards (issued invoices, overdue rate, collection, payment status breakdown).
- F: `sendFunnelEvent` returns fake success with zero network while ON.

```gherkin
Scenario: Analytics populated in dummy
  Given Dummy ON as admin on /analytics
  When  recommendations and forecast are rendered
  Then  recommendations list is non-empty
  And   forecast has 4 periods
  And   segmentation list is non-empty
  And   neither "Belum ada rekomendasi" nor "Data belum cukup" is shown

Scenario: Data-Intelligence geographic + map in dummy
  Given Dummy ON as admin on /data-intelligence
  When  geographic is rendered
  Then  the geographic table is non-empty
  And   map_points count is between 40 and 60
  And   every map point lies within the JABODETABEK bounding box (lat ~ -6.9..-5.9, lon ~ 105.9..107.3)

Scenario: Finance dashboard populated in dummy
  Given Dummy ON as finance on /dashboard
  When  metrics are rendered
  Then  FinanceMetrics cards are visible (issued_invoices, outstanding_balance, overdue_rate, collection_time, payment_status_breakdown)

Scenario: Funnel tracking faked offline
  Given Dummy ON as admin with recommendations visible on /analytics or /data-intelligence
  When  a funnel/recommendation is clicked (sendFunnelEvent)
  Then  the call returns fake success
  And   zero network request is sent to /admin/measurement/events

Scenario: Relational consistency across menus
  Given Dummy ON as admin
  When  outlet "Toko Bogor Indah" (dummy-001) exists
  Then  the same outlet id appears identically in the orders list, payments invoice, delivery route, and GeoMap map_points
```

### Story 3: Fake write mutations in Dummy mode

> As a user with Dummy ON, I want writes to fake-succeed locally so write functions are verifiable without touching real data.

**Rule: zero-network writes, prefixed IDs, relational side-effects, ephemeral, non-navigable dummy issue detail**

- A: new order at `/orders` → order appears with `dummy-` ID; linked payment/invoice/delivery exist; zero /api requests.
- B: mark delivery delivered with proof fields → status updates locally; no PATCH sent.
- C: toggle OFF (or refresh) after mutations → real data shows none of the dummy mutations.
- D: operations issue detail for a dummy issue (`dummy-*`) → non-navigable (in-memory only).
- E: `POST /admin/measurement/events` while ON → faked (Story 2 F applies).

```gherkin
Scenario: Create order fake-mutation
  Given Dummy ON at /orders
  When  submitting a new order form
  Then  the new order appears in the dummy list with a dummy- prefixed id
  And   linked dummy payment/invoice/delivery exist for that order
  And   zero network requests are sent to /api

Scenario: Delivery status fake-mutation
  Given Dummy ON at /delivery with a delivery item
  When  marking it as delivered with recipient and proof URL
  Then  the delivery status updates to delivered locally
  And   no PATCH request is sent to /api

Scenario: Mutations do not leak to real data
  Given Dummy ON with one or more mutations made
  When  the toggle is clicked OFF (or the page is refreshed, or real data re-fetched)
  Then  the real dataset contains none of the dummy-prefixed mutations

Scenario: Dummy operations issue detail non-navigable
  Given Dummy ON with a dummy issue id (dummy-*)
  When  clicking a link to /operations detail for that id
  Then  the link is non-functional in dummy mode (detail render is in-memory only; no fetch by id to /admin/operations/issues/:id)

Scenario: Tracking POST is faked
  Given Dummy ON with recommendations visible
  When  sendFunnelEvent is invoked
  Then  it returns fake success with zero network (same as Story 2 funnel scenario)
```

---

## Acceptance Criteria

```
Story 1 — Toggle Topbar
  ✓ Given logged in as admin with Dummy OFF, When toggle clicked ON, Then all menus render only dummy and GeoMap 40..60 points
  ✓ Given Dummy ON, When page refreshed, Then Dummy stays ON with same deterministic data
  ✓ Given logged out, When page loads, Then no toggle
  ✗ Given logged out, When trying to access protected page with Dummy ON, Then login is required (same as before)
  ✓ Given Dummy ON on /analytics, When toggle clicked OFF, Then real data re-fetches without navigation

Story 2 — Read paths (Analitik + Data Intelligence + Dashboard finance)
  ✓ Given Dummy ON as admin, When /analytics loaded, Then recommendations non-empty AND forecast 4 periods AND segmentation non-empty
  ✓ Given Dummy ON as admin, When /data-intelligence loaded, Then geographic table non-empty AND map_points 40..60 inside JABODETABEK bbox
  ✓ Given Dummy ON as finance, When /dashboard loaded, Then FinanceMetrics cards visible
  ✗ Given Dummy ON as admin, When /analytics loaded with empty dummy generator, Then spec violated (generator must never be empty by design)
  ✓ Given Dummy ON with recommendations visible, When sendFunnelEvent clicked, Then fake success, zero network

Story 3 — Fake mutations
  ✓ Given Dummy ON at /orders, When submitting order form, Then order with dummy- id appears + linked payment/invoice/delivery, zero /api requests
  ✓ Given Dummy ON at /delivery, When mark delivered, Then status updates locally, no PATCH
  ✓ Given Dummy ON with mutations, When toggle OFF, Then real data has none of the dummy mutations
  ✗ Given Dummy ON with dummy- id, When requesting detail at /admin/operations/issues/dummy-*, Then no fetch to backend
```

---

## Design Decision

**Chosen option:** **Option A — Zustand singleton store + per-API-function guard**

Summary: Single Zustand `dummySlice` (isDummy, dummyEntities, role, toggle/refresh reducers) plus a pure, seeded DummyFactory that builds the relational graph. Each API-module function and each consolidated inline-fetch helper gets a one-line guard (`isDummy ? dummyX() : fetchX()`). Writes mutate `dummyEntities` locally (ephemeral; prefixed IDs; relational side-effects). In-flight toggle OFF uses best-effort discard at commit layer; recommended to centralize this via a lightweight “effect guard” helper (not per-file AbortControllers).

- **Rejected Option B (global fetch override):** fails relational invariant; each route would need a shared registry anyway.
- **Rejected Option C (page-level swapped components):** violates "fungsi yang sama" requirement — alternate components would bypass the aggregation/chart functions we must verify.

**Key tradeoffs accepted:**

- Generator volume (~15–20 small typed functions) — accepted; each is a pure function returning a typed shape, fast to write and test.
- Best-effort discard on toggle OFF may leave 1–2 in-flight requests whose responses are discarded — accepted; no per-file AbortControllers.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Should operations issue detail for dummy IDs be navigable? | assumed: non-navigable (in-memory only, no fetch by id) | User clicks a stale link from dummy list; harmless broken route — can add client-side check later |
| Does best-effort discard need special handling for non-GET? | assumed: no special handling — POST/PATCH already guarded at call-site, so discard only applies to reads | Write coalescing during toggle OFF -> handled by ordering OFF-before-write guard; revisit if a write slips through at exact toggle instant |
| Do we need Zod validation for dummy shapes now? | assumed: no — TypeScript interfaces already enforce shape; generators typed to same return types | Shape drift caught only at compile time; can add Zod later as follow-up |

---

## Implementation Notes

*(For pocket-planning / implementers)*

- Prefer a centralized “read guard” helper (e.g. `withDummyRead(realFetch, dummyData)`) plus a “commit guard” helper (e.g. `commitIfDummy(moment, data, setter)`) so the `isDummy` check is not copy-pasted.
- Persist `ddp_role` at **login time** (`LoginForm`, auth helper) — e.g. store `LoginForm → window.dispatchEvent(ddp-auth-change) → localStorage('ddp_role')`. Sidebar reads `localStorage('ddp_role')` when `isDummy === true` and skips `/auth/me`.
- Generate dummy at **toggle-ON** (not at import) so `now − 60d` window is fresh; regenerate only when `toggle()` switches false→true.
- Prefix convention: string IDs → `"dummy-" + ...`; numeric auto-increment IDs → negative integers (e.g., `-1, -2, ...`).
- Keep dummy file layout shallow: `apps/web/src/dummy/{index.ts, store.ts, factory.ts, generators/*.ts}` (or `apps/web/src/lib/dummy/*` — either is fine; pick one and stick to it).

---

## Rollback Plan

- Toggle OFF atau hapus `localStorage('dummy_is_dummy' | 'ddp_role' | ...)` → semua halaman kembali ke data real (tanpa deploy).
- Jika store menyebabkan masalah, hilangkan impor dependency-nya: hapus `dummyStore` export, hapus guard per file, dan hapus file `src/dummy/*` — tidak ada migrasi untuk membatalkan.

