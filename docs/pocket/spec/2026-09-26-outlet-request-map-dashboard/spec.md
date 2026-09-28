# Outlet Request Map Dashboard

**Date:** 2026-09-26
**Status:** draft
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md

---

## Summary

Admin membutuhkan peta operasional pada section Wilayah di Data Intelligence untuk menemukan outlet yang memiliki request/order barang. V1 memakai active snapshot geographic sebagai satu-satunya sumber data, dengan default status `New + Confirmed` dan periode 30 hari, lalu melakukan filter status/periode secara deterministik dari payload snapshot yang diperkaya.

Peta menampilkan satu marker per outlet, menyembunyikan koordinat invalid tanpa membuang informasi jumlah outlet yang tidak dapat dipetakan, dan membuka drawer detail snapshot saat marker dipilih. Fitur ini read-only, mempertahankan RBAC yang ada, tidak menambah dependency peta atau menu baru, dan harus memiliki parity dengan dummy mode.

---

## Context

### Current State

- Backend adalah Laravel API di `apps/api`; frontend adalah Next.js App Router di `apps/web`.
- `GeographicAnalyticsService::produce()` menghasilkan section `geographic` untuk window rolling 30 hari. Saat ini payload outlet hanya memiliki `outlet_id`, `outlet_name`, `territory`, `latitude`, `longitude`, `orders`, dan `sales`.
- `GeographicAnalyticsController::index()` membaca active snapshot melalui `ActiveDataSnapshotReader`, mengembalikan `{table, map_points, snapshot_version, window}`, dan saat ini membuang outlet dengan koordinat invalid.
- Endpoint `GET /admin/analytics/geographic` menggunakan `rbac:data_intelligence:read` dan controller juga membatasi akses kepada admin.
- `GeoMap.tsx` memakai Leaflet, memiliki branded marker dan guard koordinat, tetapi membangun ulang instance map ketika `points` berubah dan belum memiliki filter, status marker, marker-layer diff, drawer, atau fit-to-data bounds.
- `fetchGeographicData()` memakai `withDummyRead`; dummy geographic aggregate saat ini menghasilkan map points dan table tanpa status/day/detail fields.
- `OrderController::index()` admin-only dan hanya memiliki pagination/sort; belum memiliki filter additive `outlet_id`, `status`, atau periode.
- `DataPipelineService::computeWindow()` memakai timezone `Asia/Jakarta`, window prior-day, dan 30 hari inclusive: `end = hari kalender sebelum hari run`, `start = end - 29 hari`.

### Problem / Motivation

Admin tidak dapat melihat lokasi outlet yang memiliki request/order barang, sehingga prioritas pemenuhan dan kunjungan lapangan sulit ditentukan berdasarkan wilayah. Snapshot geographic yang ada tidak menyimpan dimensi status, bucket harian, atau detail produk, sehingga filter aktif/status/periode dan drawer detail tidak dapat dibuat akurat tanpa memperluas payload stage.

### Related Areas

- `apps/api/app/Services/GeographicAnalyticsService.php`
- `apps/api/app/Http/Controllers/GeographicAnalyticsController.php`
- `apps/api/app/Http/Controllers/OrderController.php`
- `apps/api/app/Services/DataPipelineService.php`
- `apps/api/app/Services/ActiveDataSnapshotReader.php`
- `apps/api/routes/api.php`
- `apps/web/src/components/data-intelligence/GeoMap.tsx`
- `apps/web/src/app/data-intelligence/page.tsx`
- `apps/web/src/lib/data-intelligence-api.ts`
- `apps/web/src/dummy/aggregates.ts`
- Existing geographic and data-intelligence tests

---

## Scope

### In-Scope

- Map di section `Wilayah` pada halaman Data Intelligence yang hanya dapat digunakan admin.
- Default multi-status selection `{New, Confirmed}` dan default period `30d`.
- Status chips: `New`, `Confirmed`, `Delivered`, `Partially Paid`, `Semua`.
- Period chips: `Hari ini`, `7 hari`, `30 hari`; `Hari ini` berarti tanggal `window.end` dari snapshot, bukan tanggal kalender aktual.
- Snapshot geographic v2 dengan `orders_by_status`, `sales_by_status`, `daily_by_status`, product summary bounded, dan latest request.
- Filter status + periode client-side dari snapshot, termasuk kombinasi status dan periode.
- Satu marker per outlet, marker hanya jika jumlah order terfilter > 0, dan status/count visual yang deterministik.
- Invalid/null coordinate handling dengan `plottable: false` dan invalid-coordinate count.
- Empat state data yang dibedakan: snapshot unavailable, filter menghasilkan request tetapi semua koordinat invalid, tidak ada request pada filter, dan map dengan titik valid.
- Freshness/window label berdasarkan snapshot version dan `window.end`/timezone.
- Klik marker membuka drawer snapshot-only; drawer freeze pada filter saat dibuka dan memberi banner ketika filter berubah.
- Link drawer menuju order list dengan query filter additive.
- Error differentiation: 401, 403, 5xx/network; retry hanya untuk 5xx/network.
- Dummy parity dan fixture edge cases.
- A11y keyboard semantics untuk filter, marker alternative, dan drawer.
- Test API, service, API client, page, GeoMap, dummy aggregate, dan regression tests.

### Out-of-Scope

- Entity atau tabel `request` baru; request tetap direpresentasikan oleh `Order`.
- Mutasi order, approval/reject/execute, atau perubahan workflow.
- WebSocket, polling real-time, atau live query sebagai sumber peta/drawer.
- Dependency Leaflet clustering atau dependency frontend baru.
- Menu key/RBAC key baru; fitur memakai `data_intelligence:read` yang sudah ada.
- Centroid fallback untuk outlet yang tidak memiliki koordinat.
- Perubahan status vocabulary `Cancelled`/`Canceled` di domain order.
- Perubahan schema `orders` atau `outlets`.
- Modifikasi `OrderCreationService`, `PromotionService`, `StockPlanningService`, atau `ForecastService`.
- Rebuilding historical snapshots; snapshot lama ditangani dengan fallback eksplisit.

---

## Architecture Constraints

- **Layers yang boleh disentuh:** geographic stage/service dan controller, additive order-list query filters, API type/client, Data Intelligence page, GeoMap, dummy aggregates/fixtures, dan tests.
- **Layers yang tidak boleh disentuh:** core protected services, order/outlet schema, atomic snapshot publication contract, dan MenuDefinition/sidebar catalog.
- **Patterns wajib:** envelope `{status,data}`, existing RBAC middleware, scalar-safe query parsing, existing snapshot reader, `withDummyRead` parity, deterministic output, explicit insufficient-data fallback, and no hidden stale-data display.
- **Money:** backend tidak memakai float untuk agregasi uang; output monetary values adalah decimal string 2 digit. Frontend menjumlahkan dengan integer cents helper sebelum formatting.
- **Dates:** semua boundary calendar-day dihitung di `Asia/Jakarta`; date bucket memakai `YYYY-MM-DD`.
- **Access:** endpoint tetap admin-only; frontend tidak boleh menampilkan dummy data sebelum role admin diketahui.
- **Architecture validation:** CONDITIONAL PASS — valid setelah payload contract, controller passthrough, status propagation, marker-layer diff, dan performance/a11y criteria di bawah dipenuhi.

---

## Dependencies

### Existing (to leverage)

- Leaflet yang sudah dipakai `GeoMap.tsx` — map tiles, marker, popup, bounds, and layer management.
- Laravel Eloquent/Carbon — snapshot stage, date normalization, validation, and query composition.
- `ActiveDataSnapshotReader` — immutable active snapshot read path.
- `withDummyRead` and Zustand dummy store — dummy parity.
- Existing RBAC middleware and `/auth/me` role/RBAC pattern — admin authorization.
- Existing `toCents`/`fromCents` convention in dummy aggregates — decimal-safe fixture calculations.

### New (proposed)

none. Marker-layer diff, date filtering, status validation, and error classification are feature-specific uses of existing APIs, not new third-party dependencies.

---

## Payload Contract

### Geographic response envelope

`GET /admin/analytics/geographic` continues to return:

```json
{
  "status": "success",
  "data": {
    "snapshot_available": true,
    "geographic_section_available": true,
    "snapshot_version": 42,
    "window": {
      "start": "2026-08-27",
      "end": "2026-09-25",
      "timezone": "Asia/Jakarta"
    },
    "table": [],
    "map_points": []
  }
}
```

When there is no active published snapshot, `snapshot_available=false`, `geographic_section_available=false`, `snapshot_version=null`, `window=null`, and arrays are empty. When an active snapshot exists but geographic rows are absent, `snapshot_available=true` and `geographic_section_available=false`. This distinction drives empty-state precedence.

### `map_points` v2 row

All active outlet rows are passed through, including rows with invalid coordinates. Controller output has this shape:

```json
{
  "outlet_id": 12,
  "outlet_name": "Outlet H",
  "territory": "Jakarta Selatan",
  "latitude": -6.2,
  "longitude": 106.8,
  "plottable": true,
  "orders": 9,
  "sales": "125000.00",
  "orders_by_status": {
    "New": 5,
    "Confirmed": 2,
    "Delivered": 1,
    "Partially Paid": 1
  },
  "sales_by_status": {
    "New": "60000.00",
    "Confirmed": "30000.00",
    "Delivered": "20000.00",
    "Partially Paid": "15000.00"
  },
  "daily_by_status": [
    {
      "date": "2026-09-25",
      "counts": {"New": 2, "Confirmed": 1, "Delivered": 0, "Partially Paid": 0},
      "sales": {"New": "20000.00", "Confirmed": "10000.00", "Delivered": "0.00", "Partially Paid": "0.00"}
    }
  ],
  "product_summary": [
    {"product_id": 8, "product_name": "Produk A", "quantity": 12, "subtotal": "90000.00"}
  ],
  "product_summary_truncated": false,
  "latest_request": {
    "order_id": 301,
    "status": "New",
    "created_at": "2026-09-25T10:20:00+07:00"
  }
}
```

Contract rules:

- `plottable` is defined by one canonical rule, implemented twice and tested with the same case table. The backend authority is `GeographicAnalyticsService::isValidCoordinate()`, intentionally tightened from the current `(float)` cast: it returns false when either value is `null`, missing, not `is_int`/`is_float` (numeric strings such as `"-6.2"` are rejected), non-finite (`NAN`/`INF`), latitude outside [-90,90], longitude outside [-180,180], or exactly `(0,0)`. Everything else is `true`. The frontend adapter `isValidPoint()` in `GeoMap.tsx` implements the identical rule (`typeof value === 'number'`, `Number.isFinite`, same ranges, same `(0,0)` rejection) and is covered by the same case table. Client-side filtering uses the backend-serialized `plottable` field; `isValidPoint` is the equivalent adapter for locally constructed points.
- Four eligible statuses are canonical and zero-filled in every v2 status map: `New`, `Confirmed`, `Delivered`, `Partially Paid`. Missing source keys become `0`/`"0.00"`; unknown source statuses are excluded from these maps.
- `orders` and `sales` remain the legacy full-window totals for compatibility. Filtered totals are derived from `orders_by_status`/`daily_by_status` and integer-cents sales maps.
- `daily_by_status` contains dates within the snapshot window, sorted strictly ascending by `YYYY-MM-DD` string order (oldest first), with all four status keys present. Zero-count days may be omitted to meet the payload budget; omitted days mean zero and are counted separately as `omitted_zero_days` metadata, never as truncation.
- `daily_by_status` boundaries are inclusive and use `YYYY-MM-DD` in `Asia/Jakarta`.
- `product_summary` is bounded to the top five products for the snapshot's full 30-day window, sorted by quantity descending, then product id ascending for deterministic ties. It is optional for old/partial rows. For a selected period narrower than 30 days, the drawer labels this section as snapshot-window detail and does not imply that it is period-exact; if a period-exact product summary is unavailable, it shows an explicit unavailable message.
- `latest_request` is the latest eligible order in the snapshot window. It may be null. It is not fabricated when no matching order exists.
- New fields are passed through by `GeographicAnalyticsController::index()` by widening the fixed-field projection to include every v2 field (`plottable`, `orders_by_status`, `sales_by_status`, `daily_by_status`, `product_summary`, `product_summary_truncated`, `latest_request`); no v2 field is silently dropped.

### Truncation metadata

The geographic envelope carries an explicit `meta` object so the frontend can distinguish intentional zero-omission from budget truncation:

```json
"meta": {
  "truncated": false,
  "omitted_zero_days": 18,
  "product_summary_capped": false
}
```

- `omitted_zero_days`: envelope aggregate. For every v2 row that has at least one order in the window, count `windowDays - len(daily_by_status)`; the sum is `omitted_zero_days`. Rows with zero orders or without a `daily_by_status` array contribute nothing. This is intentional compression, not data loss; the frontend treats omitted days as zero and shows no warning for them.
- `product_summary_capped`: envelope aggregate, true when ANY outlet row was capped. Each individual map point carries its own `product_summary_truncated: boolean` (true when that outlet had more than five products and the list was cut to five). The drawer labels the list as "Top 5 produk" only for rows with `product_summary_truncated=true`; the envelope flag drives the general truncation warning.
- `truncated`: true only when the serialized response exceeded the 500KB budget and rows or fields were cut to fit. When true, the frontend shows "Data peta dipangkas untuk performa. Beberapa detail mungkin tidak lengkap." and marks affected sections as possibly incomplete.
- GWT: given a reference response under 500KB, `truncated=false`; given a simulated oversized response, the API returns `truncated=true` with the cap applied and the frontend warning renders.
- Invalid-coordinate rows are returned with `plottable=false`, not removed, so the frontend can count invalid outlets with filtered orders.
- v1 rows are identified per row by field presence, not by `snapshot_version` integer. `Array.isArray(daily_by_status)` plus presence of status maps is the frontend capability guard.

### Money and date rules

- Backend aggregation uses database decimal/string values or integer cents; it must not cast monetary aggregate values to PHP float before summing.
- Frontend aggregation uses integer cents (`toCents`/`fromCents`-style helper), never `Number(decimalStringA) + Number(decimalStringB)` for business totals.
- A window `{start,end,timezone}` represents the inclusive calendar interval `[start 00:00:00, end 23:59:59.999999]` in `Asia/Jakarta`, normalized consistently against stored timestamps.
- `7d` selects dates `[window.end - 6 days, window.end]`; `30d` selects `[window.end - 29 days, window.end]`; `Hari ini` selects `[window.end, window.end]`.

### Additive order-list filter contract

`OrderController@index` accepts only these additional query keys: `outlet_id`, `status`, `start`, and `end`. Every other new query key is rejected with HTTP 422. Validation runs before query construction. A validation failure returns exactly this envelope shape (messages may be localized only if the field keys and meaning remain stable):

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "outlet_id": "Must be a positive integer"
  }
}
```

`outlet_id` must be a positive integer. `status` is a comma-separated list and each value must be exactly one of `New`, `Confirmed`, `Delivered`, or `Partially Paid`; duplicate values are normalized once. `start` and `end` must be valid `YYYY-MM-DD` dates, `start <= end`, and the inclusive range must not exceed 90 days. If any field is invalid, no broadened/unfiltered query is executed. With no new parameters, existing result, pagination, and sort behavior is unchanged. The frontend URL-encodes every value and joins selected statuses with commas.

### Response errors and stale-data rule

`adminFetch` throws a typed `ApiError` with `status: number | null` and `retryable: boolean`; HTTP 5xx and network errors are retryable, while 401 and 403 are not. The page clears visible geographic data when a fetch fails and never presents a previous successful snapshot as current after an error. `401` clears the stored token and returns to session-expired/login state; `403` renders access denied without retry; `5xx` and network failure render the retry action.

---

## Stories + Scenarios

### Story: Admin sees active request hotspots

> As an admin, I want a map of outlets with active requests, so that I can prioritize fulfillment by territory.

**Rule 1: Default active filter**

- Default selected statuses are `{New, Confirmed}`.
- Default period is `30d`.
- One marker represents one outlet and is shown only when its filtered count is greater than zero.

```gherkin
Scenario: Default map shows New and Confirmed requests only
  Given an active v2 snapshot has Outlet A with New=4, Confirmed=3, Delivered=5
  And the default selection is statuses {New, Confirmed} and period 30d
  When an admin opens Data Intelligence
  Then the map shows exactly one marker for Outlet A
  And the marker count is 7
  And Delivered=5 is not included in that count

Scenario: Outlet with only non-default status is hidden
  Given Outlet B has Delivered=6 and no New or Confirmed requests in the snapshot
  When the admin opens the default map
  Then Outlet B has no marker

Scenario: Zero filtered-order outlets are hidden
  Given Outlet C has zero orders for the selected statuses and period
  When the map is filtered
  Then Outlet C has no marker
  And Outlet C is not counted as an invalid-coordinate outlet

Scenario: Multiple orders at one outlet produce one marker
  Given Outlet D has 15 selected orders
  When the map renders
  Then exactly one marker exists for Outlet D
  And its count is 15
```

**Rule 2: Coordinate safety and bounds**

```gherkin
Scenario: Invalid coordinates are hidden but counted
  Given Outlet E has 5 filtered orders and latitude is null
  And Outlet F has 2 filtered orders and latitude=0 and longitude=0
  When the map renders
  Then neither outlet is rendered as a marker
  And the UI says 2 outlets cannot be mapped because coordinates are unavailable

Scenario: Valid data fits map bounds on initial load or explicit reset only
  Given at least one valid filtered marker exists
  And this is the first successful load or the admin activated an explicit reset control
  When the map renders
  Then the map fits bounds derived from the valid markers
  Given the map is already shown and the admin changes a filter
  When markers update
  Then the current map center and zoom are preserved
  And fitBounds is not re-applied on filter changes

Scenario: No valid points uses a safe fallback view
  Given there are no valid filtered points
  When the map view is initialized
  Then it uses the existing Jakarta default center and zoom
  And it does not render a Null Island marker
```

**Rule 3: Freshness**

```gherkin
Scenario: Snapshot freshness is visible
  Given snapshot_version=42 and window.end=2026-09-25 with timezone Asia/Jakarta
  When the map is displayed
  Then the UI shows the snapshot version and "Data per 25 Sep 2026 (Asia/Jakarta)"
  And it does not claim that the data is live
```

### Story: Admin filters statuses and periods

> As an admin, I want status and period filters, so that I can move from active fulfillment triage to historical analysis.

**Rule 1: Multi-toggle status selection**

The status controls are multi-toggle buttons. Initial selection is `{New, Confirmed}`. Selecting a named status adds/removes it. `Semua` selects all four canonical statuses. Missing status keys are treated as zero. A selection of no statuses is allowed and produces the no-request state rather than silently reverting.

```gherkin
Scenario: Status and period filters compose
  Given Outlet G has New=5 outside the last 7 days and New=2 inside the last 7 days
  And Outlet G has Delivered=10 inside the last 7 days
  When the admin selects status {New} and period 7d
  Then Outlet G's marker count is 2
  And the Delivered orders are excluded

Scenario: Semua includes all eligible statuses
  Given Outlet H has New=2, Confirmed=3, Delivered=4, Partially Paid=1 in 30d
  When the admin selects Semua and period 30d
  Then the marker count is 10
  And Semua means exactly those four eligible statuses

Scenario: Partially Paid has an explicit chip
  Given Outlet I has only Partially Paid=4
  When the admin selects Partially Paid
  Then Outlet I appears with count 4

Scenario: Missing status keys are zero-filled
  Given a partial row has orders_by_status {"New": 2} and omits the other statuses
  When the admin selects Semua
  Then the row contributes count 2
  And it does not produce NaN or an exception
```

**Rule 2: Period semantics**

```gherkin
Scenario: Hari ini means the snapshot end date
  Given the snapshot window.end is 2026-09-25
  When the admin selects Hari ini
  Then only the 2026-09-25 daily bucket is included
  And the label says "Hari snapshot · 25 Sep 2026"
  And the feature does not claim to show the calendar date 2026-09-26

Scenario: Seven-day period is inclusive
  Given window.end is 2026-09-25
  When the admin selects 7 hari
  Then dates 2026-09-19 through 2026-09-25 are included
  And 2026-09-18 is excluded

Scenario: Invalid filter state is safe
  Given a deep-link or query state contains an unknown status or period
  When the page initializes
  Then it falls back to statuses {New, Confirmed} and period 30d
  And it does not throw a 500 or render an unbounded data set
```

**Rule 3: Filter update performance**

```gherkin
Scenario: Filter changes do not rebuild the Leaflet map
  Given the map is open at a custom center and zoom
  When the admin changes a status or period chip
  Then the existing Leaflet map instance remains mounted
  And only the marker layer and derived counts are updated
  And the center and zoom remain unchanged
  And local filter recomputation completes within 100ms on the reference test fixture
```

### Story: Admin inspects an outlet

> As an admin, I want to click an outlet marker and inspect its request summary, so that I know what is being requested before taking action.

**Rule 1: Drawer is snapshot-only and filter-aware**

```gherkin
Scenario: Marker opens a detail drawer
  Given Outlet J has 5 New and 2 Confirmed requests in the active filter
  When the admin clicks Outlet J's marker
  Then a drawer opens with Outlet J, territory, and filtered count 7
  And it shows status counts for the selected scope
  And it shows latest request data when available
  And it provides a "Lihat semua order" action

Scenario: Drawer freezes its opening filter
  Given the drawer is open for Outlet J with statuses {New,Confirmed} and period 30d
  When the admin changes the map filter to Delivered and 7d
  Then the drawer keeps the data captured at opening
  And it shows "Filter berubah — tutup dan buka ulang untuk memuat data terbaru"
  And it does not silently rewrite the drawer contents

Scenario: Drawer closes accessibly
  Given the drawer is open
  When the admin presses Escape or activates the close control/backdrop
  Then the drawer closes
  And keyboard focus returns to the marker trigger or its accessible list alternative
```

**Rule 2: Detail fallback is explicit**

```gherkin
Scenario: Old snapshot keeps the legacy marker and explains missing detail
  Given a v1 row has orders and valid coordinates but no orders_by_status or daily_by_status
  When the admin opens the marker drawer
  Then the marker still renders from legacy orders and sales
  And the drawer says "Detail produk belum tersedia — jalankan pipeline data"
  And the UI does not invent status or daily values

Scenario: Partial row has per-outlet fallback
  Given one row has daily_by_status and another row does not
  When the admin selects 7 hari
  Then the row with daily data is filtered normally
  And the row without daily data is excluded from period-specific counts
  And the UI reports the number of outlets without daily detail
  And 30d legacy/status-compatible display remains available for that row

Scenario: Product detail is bounded and deterministic
  Given an outlet has more than five products in the snapshot window
  When the drawer opens
  Then it shows at most five products sorted by quantity descending and product id ascending for ties
  And monetary subtotals are displayed as fixed two-decimal values
  And it states when the product summary is snapshot-window detail rather than period-exact detail
```

**Rule 3: Order navigation preserves context**

```gherkin
Scenario: Drawer link opens filtered order list
  Given the drawer is for outlet 12 with statuses {New,Confirmed} and period 7d
  When the admin activates "Lihat semua order"
  Then navigation includes outlet_id=12, status=New,Confirmed, start, and end
  And query values are URL-encoded
  And the order list preserves existing pagination and sort behavior

Scenario: Invalid order-list filter is rejected safely
  Given an order-list request contains a non-numeric outlet_id or an unknown status
  When the API validates the request
  Then it returns a structured 422 validation error
  And it does not broaden the query to all orders
```

### Story: Admin receives accurate empty and error states

> As an admin, I want the map to explain why no markers are shown, so that empty data is not confused with a broken map.

**Rule 1: Empty-state precedence**

```gherkin
Scenario: No active snapshot has highest precedence
  Given there is no active published snapshot
  When the page loads
  Then it shows "Data peta belum tersedia"
  And it does not show "Tidak ada request pada periode ini"
  And it does not show an invalid-coordinate count

Scenario: Active snapshot has no geographic section
  Given an active snapshot exists but geographic_section_available=false
  When the page loads
  Then it shows "Data peta belum tersedia"

Scenario: Filter has requests but all coordinates are invalid
  Given the geographic section exists
  And filtered orders are greater than zero
  And all outlets with filtered orders have plottable=false
  When the map renders
  Then it shows "Outlet memiliki request tetapi koordinat belum tersedia"
  And it shows the invalid outlet count

Scenario: Filter has no requests
  Given the geographic section exists
  And the selected status/period totals are zero
  When the map renders
  Then it shows "Tidak ada request pada periode ini"
  And it does not show a coordinate warning for zero-order outlets
```

**Rule 2: Error classification and retry**

```gherkin
Scenario: Fetch failures carry a typed status, not a message string
  Given geographic fetch rejects with HTTP 401, 403, 500, or a network error
  When `fetchGeographicData` propagates the failure
  Then the rejection is an `ApiError` carrying `{status: number|null, retryable: boolean}`
  And `status=null` with `retryable=true` for a network failure
  And the page classifies 401/403/5xx from `status`, never from a localized message string

Scenario: Temporary server error offers retry
  Given geographic fetch returns HTTP 500 or a network failure
  When the page handles the failure
  Then it shows role=alert with "Tidak dapat memuat data peta"
  And it shows a "Coba lagi" button
  And it does not display a previous successful snapshot as current data

Scenario: Forbidden response does not offer a retry loop
  Given geographic fetch returns HTTP 403
  When the page handles the failure
  Then it shows "Akses ditolak"
  And it does not show a retry button

Scenario: Expired session clears access
  Given geographic fetch returns HTTP 401
  When the page handles the failure
  Then the stored token is cleared
  And the page shows "Sesi berakhir. Silakan masuk kembali"
  And no map data is rendered

Scenario: Retry replaces the failed state
  Given a retryable error is visible
  When the admin activates Coba lagi
  Then loading starts and the fetch is issued again
  And on success the error is removed and the current snapshot is rendered
```

### Story: Access control, dummy parity, and accessibility

> As the platform owner, I want the feature to follow existing access and dummy conventions, so that production and development do not diverge.

**Rule 1: Admin-only visibility**

```gherkin
Scenario: Non-admin cannot see geographic data
  Given a sales or outlet user calls the geographic endpoint
  When authorization runs
  Then the API returns 403
  And no geographic rows are disclosed

Scenario: Non-admin dummy mode is also denied
  Given a non-admin user has dummy mode enabled
  When Data Intelligence initializes
  Then role authorization is evaluated before withDummyRead
  And the page shows access denied without rendering dummy markers
```

**Rule 2: Dummy fixture covers production-shape cases**

```gherkin
Scenario: Dummy mode supports combined filters
  Given dummy mode is enabled
  And the fixture contains New, Confirmed, Delivered, Partially Paid, daily buckets, and product detail
  When the admin selects Semua and 7 hari
  Then the same filter math and marker counts are rendered as the real v2 contract

Scenario: Dummy mode covers invalid and zero-order states
  Given dummy mode is enabled
  And the fixture contains an invalid-coordinate outlet with orders and a zero-order outlet
  When the admin selects the relevant statuses and period
  Then invalid-coordinate count and zero-order hiding match production behavior

Scenario: Dummy fixture matrix is deterministic and asserts parity case by case
  Given the documented deterministic fixture table (101=Outlet H with New4/Conf3/Del2/PP1, 102=Outlet E with null latitude and 5 orders, 103=Outlet F with (0,0) and 2 orders, 104=Outlet C with zero orders, 105=Outlet V1 legacy-only)
  When the admin selects Semua and period 7 hari
  Then Outlet H's marker count is exactly 8 (the literal daily sum across the 7d buckets, not the 30-day total of 10)
  And the invalid-coordinate count is exactly 2 (E and F)
  And Outlet C renders no marker and is excluded from the invalid count
  And Outlet V1 renders no marker under 7 hari because it lacks daily detail, is reported in the outlets-without-daily-detail count, and renders its legacy marker under 30 hari
  And Outlet V1's drawer shows "Detail produk belum tersedia — jalankan pipeline data"
  And every value above is asserted in the fixture test as fixed literals, never recomputed by the filter code under test

Scenario: Dummy mode contains an old-shape row
  Given dummy mode is enabled
  And one fixture row omits v2 fields
  When its marker drawer is opened
  Then the old-snapshot fallback message is rendered
```

**Rule 3: Keyboard and semantic accessibility**

```gherkin
Scenario: Filter controls expose selection state
  Given a keyboard-only admin uses the filter controls
  When focus moves across status and period buttons
  Then each control is a real button with an accessible label and aria-pressed state
  And the selected status set is announced deterministically

Scenario: Marker data has a keyboard alternative
  Given markers are rendered on the Leaflet canvas
  When a keyboard-only admin navigates the map section
  Then an accessible outlet list or equivalent focusable marker controls expose every visible outlet
  And selecting one opens the same drawer

Scenario: Drawer is a labelled modal dialog
  Given the detail drawer opens
  When it mounts
  Then it has role="dialog", aria-modal="true", and aria-label or aria-labelledby set to the outlet name
  And focus moves into the drawer on open
  And focus is contained while open
  And Escape, the close control, or the backdrop closes it
  And focus is restored to the exact marker control that opened it (or its list alternative when opened from the list)

Scenario: Map tiles fail but the outlet list still works
  Given the Leaflet tile layer fails to load (tile 5xx/offline)
  When the map section renders
  Then it shows fallback text that map imagery is unavailable
  And the keyboard-accessible outlet list remains present and usable for opening the drawer
```

---

## Acceptance Criteria

```
Rule: Snapshot contract and backward compatibility
  ✓ Given a v2 geographic row, When GET /admin/analytics/geographic returns, Then it passes through plottable, orders_by_status, sales_by_status, daily_by_status, product_summary, and latest_request.
  ✓ Given a v1 or partial row, When the frontend parses it, Then field-presence guards select the documented per-outlet fallback without throwing.
  ✓ Given invalid coordinates, When the API responds, Then the row remains present with plottable=false and no marker is rendered.
  ✓ Given lat/lng as null, NaN, Infinity, "-6.2" (string), 91, 181, or exactly (0,0), When `isValidCoordinate()` runs, Then it returns false.
  ✓ Given lat=-6.2 and lng=106.8 as finite numbers (int/float), When `isValidCoordinate()` runs, Then it returns true.
  ✓ The backend stage casts DB decimal strings to float before calling `isValidCoordinate()`; the helper itself does not cast.
  ✓ Given `daily_by_status` buckets [2026-09-25, 2026-09-24, 2026-09-20], When serialized, Then the order is ascending: 2026-09-20, 2026-09-24, 2026-09-25.
  ✓ Given `snapshot_version=42` with the v2 payload shape and no new `meta`, When the frontend parses it, Then the capability guard falls back per row rather than rejecting the payload.

Rule: Truncation metadata
  ✓ Given a reference response under 500KB, When serialized, Then `meta.truncated=false`, `meta.omitted_zero_days` equals the envelope aggregate of omitted date cells, and `meta.product_summary_capped=false` when no outlet was capped.
  ✓ Given a simulated oversized response, When the API caps it, Then `meta.truncated=true` and the frontend renders "Data peta dipangkas untuk performa. Beberapa detail mungkin tidak lengkap."
  ✓ Given `omitted_zero_days > 0` with `truncated=false`, When filters compute counts, Then omitted days count as zero and no truncation warning is shown.
  ✓ Given an outlet row with `product_summary_truncated=true`, When the drawer renders that outlet, Then it labels the list "Top 5 produk"; the envelope `meta.product_summary_capped` mirrors the OR of all per-row flags.

Rule: Default map
  ✓ Given v2 data with New=4 and Confirmed=3, When an admin opens the page, Then one marker shows count 7 under default 30d.
  ✓ Given an outlet with zero filtered orders, When the map renders, Then no marker or invalid-coordinate count is produced for that outlet.
  ✓ Given valid points, When the map renders on first load or explicit reset, Then fitBounds uses data-derived points.
  ✓ Given the map already shown, When a filter changes, Then center and zoom are preserved and fitBounds is not re-applied.

Rule: Status and period filtering
  ✓ Given the default state, When the page initializes, Then statuses are {New,Confirmed} and period is 30d.
  ✓ Given status=New and period=7d, When daily_by_status has old New=5 and recent New=2, Then count is 2.
  ✓ Given Semua, When counts exist for all four eligible statuses, Then all four are included.
  ✓ Given unknown status/period deep-link values, When the page initializes, Then it falls back to the safe default without a 500.
  ✓ Given window.end=2026-09-25, When Hari ini is selected, Then only 2026-09-25 is included and the label identifies it as the snapshot day.

Rule: Drawer and navigation
  ✓ Given a marker click, When the drawer opens, Then it shows filtered outlet/status information and available snapshot detail.
  ✓ Given the filter changes while open, When the drawer is still visible, Then its opening data is frozen and the filter-changed banner appears.
  ✓ Given a drawer link, When activated, Then outlet/status/date query parameters are encoded and preserved in the order-list URL.
  ✓ Given invalid order-list filter parameters, When the API validates them, Then it returns a 422 envelope `{status,message,errors}` with field-level errors and never broadens the query.
  ✓ Given `outlet_id=abc`, When validated, Then 422 with `errors.outlet_id="Must be a positive integer"`.
  ✓ Given `status=Unknown`, When validated, Then 422 with `errors.status="Status must be one of: New,Confirmed,Delivered,Partially Paid"`.
  ✓ Given `start > end` or a range over 90 days or a malformed `YYYY-MM-DD`, When validated, Then 422 with a field error.
  ✓ Given no new parameters, When the endpoint is called, Then results, pagination, and sort match the pre-change behavior exactly.

Rule: Empty states
  ✓ Given no active snapshot or no geographic section, When the page loads, Then it shows data unavailable.
  ✓ Given filtered orders > 0 and all matching coordinates invalid, When the page renders, Then it shows the coordinate-unavailable state and count.
  ✓ Given filtered orders = 0, When the page renders, Then it shows no requests and no invalid-coordinate warning.

Rule: Error and authorization handling
  ✓ Given a rejected geographic fetch, When it propagates, Then it is an `ApiError` with `status: number|null` and `retryable: boolean` (5xx/network retryable; 401/403 not).
  ✓ Given 5xx/network failure, When geographic fetch fails, Then alert + retry is shown, visible geographic data is cleared, and a previous snapshot is not presented as current.
  ✓ Given 403, When geographic fetch fails, Then access denied is shown without retry.
  ✓ Given 401, When geographic fetch fails, Then the token is cleared and login/session-expired state is shown.
  ✓ Given a non-admin in dummy mode, When the page initializes, Then role denial happens before dummy data is read.

Rule: Fallback disclosure
  ✓ Given a v1 row (no status maps/daily), When period=7 hari, Then it is excluded from period counts, has no marker, and contributes to the outlets-without-daily-detail count.
  ✓ Given a v1 row with legacy `orders` and period=30 hari, When the map renders, Then its legacy marker renders from `orders`.
  ✓ Given a v1 row's drawer, When opened, Then it shows "Detail produk belum tersedia — jalankan pipeline data" and invents no status/daily values.
  ✓ Given a partial row (status maps, no daily), When period=7 hari, Then it is excluded from period counts and counted in outlets-without-daily-detail; when period=30 hari, Then status-map totals are used.
  ✓ Given `product_summary` absent, When the drawer opens, Then it shows the unavailable message; given `product_summary` present, Then it shows the labeled snapshot-window scope.

Rule: Dummy, money, performance, and accessibility
  ✓ Given dummy mode, When each status/period/invalid/zero/old-shape fixture case runs, Then behavior matches the real contract.
  ✓ Given decimal sales strings, When filtered totals are calculated, Then integer-cents aggregation preserves exact two-decimal output.
  ✓ Given the reference scale fixture (`ScaleFixtureSeeder`: 500 total outlets, 100 active outlets with valid coordinates, 220 products, 500 orders), When a status/period chip changes, Then local recomputation is under 100ms, the Leaflet instance is the same object, and center/zoom are unchanged.
  ✓ Given the reference fixture, When the geographic response is serialized, Then it stays under 500KB; if it cannot be, the test fails and `meta.truncated=true` with cap metadata must be produced.
  ✓ Given keyboard-only interaction, When filters/markers/drawer are used, Then aria-pressed, focus containment, Escape, and focus restoration to the opening trigger all work.
  ✓ Given the drawer open, Then it has `role="dialog"`, `aria-modal="true"`, and an accessible name equal to the outlet name.
  ✓ Given tile layer failure (5xx/offline), When the map section renders, Then fallback text is shown and the outlet list remains usable for opening the drawer.
  ✓ Given dummy mode with the deterministic fixture table below, When each status/period combination is selected, Then every asserted value is a fixed literal from the table.

  **Deterministic dummy fixture table** (all IDs stable across runs):

  | outlet_id | name     | latitude | longitude | New30 | Conf30 | Del30 | PP30 | daily_7d_keys                                 | legacy_only | legacy_orders |
  |-----------|----------|----------|-----------|-------|--------|-------|------|-----------------------------------------------|-------------|---------------|
  | 101       | Outlet H | -6.2     | 106.8     | 4     | 3      | 2     | 1    | 09-20(New1,Del1), 09-22(Conf1,PP1), 09-25(New2,Conf1,Del1) | no          | —             |
  | 102       | Outlet E | null     | 106.8     | 3     | 2      | 0     | 0    | 09-21(New2), 09-24(Conf1)                     | no          | —             |
  | 103       | Outlet F | 0        | 0         | 1     | 1      | 0     | 0    | 09-23(New1,Conf1)                             | no          | —             |
  | 104       | Outlet C | -6.3     | 106.9     | 0     | 0      | 0     | 0    | (omitted)                                     | no          | —             |
  | 105       | Outlet V1| -6.4     | 107.0     | —     | —      | —     | —    | (absent)                                      | yes         | 10            |

  **Window**: end=2026-09-25, start=2026-08-27 (30d). 7d = 2026-09-19..2026-09-25. Hari ini = 2026-09-25.

  **Expected literals** (test asserts these exact values):

  - Default ({New,Conf}, 30d): markers → H count=7, V1 count=10 (legacy badge); invalid=2 (E,F); zero-hide=C.
  - Semua, 30d: H=10, V1=10 (legacy); invalid=2; C hidden.
  - Semua, 7d: H=8; E invalid (count=3); F invalid (count=2); C hidden; V1 excluded → outlets_without_daily_detail=1.
  - {New,Conf}, 7d: H=5; E invalid (3); F invalid (2); V1 excluded; invalid=2; outlets_without_daily_detail=1.
  - Partially Paid, Hari ini (09-25): H=0; E=0; F=0; V1 excluded; filteredOrders=0 → empty state "Tidak ada request pada periode ini".
  - {New,Conf}, Hari ini: H=3; E=0; F=0; V1 excluded; invalid=0; marker=H count=3.
  - V1 drawer under 30d: legacy badge "Rincian status tidak tersedia"; under 7d/Hari ini: excluded and counted in outlets_without_daily_detail.
  - V1 drawer at any period: "Detail produk belum tersedia — jalankan pipeline data".
```

---

## Design Decision

**Chosen option:** Option A — Snapshot-first enriched geographic projection.

**Summary:** Extend the existing geographic pipeline and active snapshot row contract, then perform deterministic status/period filtering in the client. Keep the existing Data Intelligence surface, RBAC, Leaflet dependency, snapshot metadata, and dummy-read path. Additive order-list query filters support drawer navigation without changing existing unfiltered behavior.

**Rejected options:**

- **Option B — Snapshot map + live drawer:** rejected because it introduces two sources of truth and violates the approved snapshot-only drawer behavior.
- **Option C — Live query-first map:** rejected because it bypasses the established atomic snapshot model, requires a new endpoint/cache/performance path, and risks divergence from other Data Intelligence sections.
- **New clustering dependency:** rejected because a new map dependency is out of scope; v1 uses one marker per outlet, in-place marker updates, bounded payload/detail, and explicit performance measurement.

**Key tradeoffs accepted:**

- The v2 payload is larger because it must compose status and period filters from one snapshot. Zero-day omission and bounded product detail are required to meet the response target.
- Old snapshots continue to render legacy map totals but cannot claim v2 status/day/product precision; the UI must say what is unavailable.
- Product summary is bounded and may be snapshot-window rather than selected-period exact for narrow periods; the drawer must label this and show an explicit fallback instead of misleading precision.
- Snapshot freshness is transparent but not real-time; no stale snapshot is silently presented as current after a failed fetch.
- No advanced clustering is included in v1; the scale fixture and response budget are acceptance gates for deciding whether a follow-up is needed.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Can all four statuses be represented in the existing order status vocabulary? | assumed: `New`, `Confirmed`, `Delivered`, and `Partially Paid` are the canonical eligible set already used by `GeographicAnalyticsService`. | A later status rename would require a contract/version update. |
| What exact reference device defines the 100ms client budget? | locked: the existing web test/CI environment plus the `ScaleFixtureSeeder` fixture (500 total / 100 active outlets with valid coordinates); the measured baseline is recorded in the performance test. | A low-powered production device may require a follow-up optimization. |
| How are old rows mixed with v2 rows in one active snapshot? | resolved by per-row field-presence guards and documented fallback; no global version gate. | Partial detail can be visible for some outlets and unavailable for others, which the UI must disclose. |
| Are product details period-exact for every preset? | resolved: only when the payload provides matching detail; otherwise label snapshot-window scope or show unavailable fallback. | Admin may expect period-exact product quantities; copy must remain explicit. |

---

## Implementation Notes

- Write failing tests before implementation for the service payload, controller invalid-row passthrough, order-list filters, API error status propagation, filter math, empty-state precedence, and GeoMap marker-layer lifecycle.
- The geographic stage must retain territory aggregates while adding outlet detail; invalid coordinates remain excluded from map rendering but not from territory totals.
- `GeoMap.tsx` must own a stable Leaflet map instance and a marker layer/reference map keyed by `outlet_id`; filter changes update/remove markers without removing the tile layer.
- Use data-derived `fitBounds` only on initial valid data or explicit reset; filter changes preserve viewport.
- The page must obtain/verify the authenticated role before invoking a dummy read for this admin-only surface. Reuse the existing `/auth/me` role/RBAC pattern rather than decoding a token ad hoc.
- API client errors need a typed error carrying HTTP status. Do not infer 401/403 from localized message strings.
- `OrderController@index` filters are additive, scalar-safe, allowlisted, and validated before query construction. Existing calls with no new parameters must retain current results and pagination behavior.
- Dummy fixture must remain deterministic; do not use random order generation for the geographic aggregate tests.
- The dummy geographic fixture must include the documented fixture matrix (valid H, null-latitude E, (0,0) F, zero-order C, legacy-only V1). Because invalid-coordinate rows are now returned instead of dropped, the existing `aggregates.test.ts` bbox assertion (40–60 points inside JABODETABEK) must be updated to assert plottable points only; invalid rows are present in `map_points` but excluded by the plottable check.
- If the 500KB target cannot be met with the full bounded contract, apply the documented cap and set `meta.truncated=true`; the performance test must assert both the size target and the metadata contract. Do not silently truncate.
- Implement `GeographicAnalyticsService::isValidCoordinate()` as the single coordinate-rule authority and reuse it in both stage and controller projection.
- Widen the controller's fixed-field projection (`GeographicAnalyticsController::index()`) explicitly to every v2 field, and cover it with a passthrough test so a future field addition fails loudly rather than being silently dropped.
- Error classification must branch on `ApiError.status`, never on message text; add unit tests for 401/403/5xx/network mapping to the three page states.

---

## Rollback Plan

- Revert the frontend map/filter/drawer changes; the existing geographic section and legacy marker contract remain available.
- Revert the geographic stage/controller additions and re-run the existing pipeline to publish the previous payload shape if the v2 snapshot contract causes production issues.
- Remove the additive order-list filter handling; existing unfiltered order listing remains unchanged.
- No schema rollback or data deletion is required because this feature adds no migration and does not mutate orders/outlets.
