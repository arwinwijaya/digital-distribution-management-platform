# Kejelasan Menu Barang

**Date:** 2026-09-26  
**Status:** draft  
**Author:** brainstorm session  
**Spec path:** `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`

---

## Summary

Menu Barang admin saat ini menampilkan sebagian kecil data produk walaupun API sudah menyediakan kategori, deskripsi, supplier, dan status. Akibatnya admin sulit membedakan barang, memahami konteks harga/stok, dan mengenali produk yang tidak dapat dibeli. Fitur ini memperjelas tampilan melalui kolom status/kategori, label harga dan stok yang eksplisit, filter server-side, serta expandable row tanpa mengubah schema atau makna data lintas sistem.

---

## Context

### Current State

- Frontend menu berada di `apps/web/src/app/admin/products/page.tsx` dengan adapter di `api.ts`.
- Backend daftar produk memakai `GET /products` di `ProductController`, dengan middleware `rbac:products:read`.
- `Product` memiliki `name`, `description`, `price`, `sku`, `stock_quantity`, `category`, `is_active`, `supplier_id`, `created_at`, dan `updated_at`.
- Tabel admin saat ini menampilkan Nama Produk, SKU, Harga, Stok, Dibuat, Diperbarui, dan Aksi.
- Price history tersedia melalui `GET /admin/products/{id}/prices` dengan offset cursor.
- Katalog publik sudah menampilkan beberapa informasi yang belum ada di menu admin.
- `price` dan `stock_quantity` dikonsumsi oleh order, analitik, rekomendasi, WhatsApp, marketplace, dan dummy mode.

### Problem / Motivation

Admin membutuhkan informasi lebih detail dan tidak ambigu, tetapi penambahan semua informasi sebagai kolom akan membuat tabel sulit dipindai. Data satuan/UoM, harga bertingkat, dan riwayat stok belum ada sehingga tidak boleh dipalsukan sebagai kemampuan baru pada fase ini.

### Related Areas

- `apps/web/src/app/admin/products/page.tsx`
- `apps/web/src/app/admin/products/api.ts`
- `apps/web/src/app/admin/products/page.test.tsx`
- `apps/web/src/components/ui/Table.tsx`
- `apps/web/src/lib/admin-table.ts`
- `apps/api/app/Http/Controllers/ProductController.php`
- `apps/api/app/Models/Product.php`
- `apps/api/app/Models/Supplier.php`
- `apps/api/app/Http/Controllers/AdminProductController.php`
- `apps/api/database/factories` and backend feature tests
- `apps/web/src/dummy/*`

---

## Scope

### In-Scope

- Menambahkan kolom utama Kategori dan Status.
- Menampilkan label `Harga Jual` dengan tooltip bahwa nilainya adalah harga jual yang digunakan dalam order.
- Menampilkan label `Stok (unit)`.
- Status derived dengan prioritas badge: `Aktif` → `Tidak bisa dibeli` → `Nonaktif` untuk sorting; pada konflik render satu badge dengan prioritas tampilan `Nonaktif` terlebih dahulu, lalu `Tidak bisa dibeli`, lalu `Aktif`. Detail tetap boleh menjelaskan fakta dasar produk dan supplier.
- Status supplier nonaktif berarti `subscription_status != 'active'`. Supplier null/orphan ditampilkan `—` dan tidak masuk filter `Tidak bisa dibeli`.
- Expandable row lokal pada halaman Products yang menampilkan identitas lengkap, supplier, status, nilai stok, catatan satuan, dan price history.
- Price history: lima item terbaru, load more berbasis offset cursor, empty state, retry saat error, disable tombol saat loading, stale-response guard, abort saat state tabel berubah.
- Indikator stok: Habis `<= 0`, Rendah `1..10`, Aman `>= 11`; nilai fractional dibulatkan ke bawah; `NULL` di-coalesce menjadi `0`.
- Filter server-side kategori, status, dan kesehatan stok dengan AND semantics.
- Filter status: Semua (default), Aktif, Nonaktif, Tidak bisa dibeli. Filter dapat overlap.
- Filter kesehatan API: `out`, `low`, `ok`; nilai invalid termasuk array/malformed diabaikan.
- Opsi kategori dari `meta.categories` distinct backend; exact match setelah trim; opsi eksplisit `Tanpa kategori`.
- Sorting kategori alfabetis dengan null/empty di akhir dan tie-break `id DESC`.
- Sorting status berdasarkan prioritas `Aktif` → `Tidak bisa dibeli` → `Nonaktif` dan tie-break `id DESC`.
- Sorting/filter changes mempertahankan sort, reset product cursor ke `0`, menutup expanded rows, dan menghitung summary dari filtered set.
- Summary mempertahankan shape `{ total, out_of_stock }`; `out_of_stock` menghitung NULL sebagai 0.
- Supplier di-eager-load pada `GET /products` dan dikembalikan sebagai nested object `{ id, name, subscription_status }`; semua role dengan `products:read` boleh menerimanya.
- Admin context memakai query param `include_unpurchasable=1` yang dikirim **selalu** oleh halaman admin (initial load, filter, sort, pagination). Param ini hanya melewati klausa eligibility supplier pada `ProductController` sehingga admin dapat melihat produk dengan supplier nonaktif atau orphan (`Tidak bisa dibeli` / `—`).
- Tanpa `include_unpurchasable=1`, `GET /products` mempertahankan perilaku legacy (eligibility katalog publik) dan test existing tetap berlaku.
- Row trigger mendukung keyboard Enter/Space dan ARIA; kontrol `Ubah harga` tetap terpisah.
- GET list error/timeout menampilkan error state dan retry.
- Dummy mode wajib memiliki paritas penuh dengan real mode.
- Backend tests untuk eager-load, filter, sorting, null handling, summary, and invalid query.

### Out-of-Scope

- Schema migration untuk UoM, conversion, harga beli/jual/grosir/bertier, atau threshold per produk.
- Perubahan makna `price` atau `stock_quantity`.
- Perubahan order creation, inventory deduction, analytics, recommendations, WhatsApp, marketplace, atau public catalog eligibility.
- Stock movement history.
- Multi-warehouse.
- Role-based view presets.
- Export CSV, sparkline, virtualization.
- Memperluas shared `Table.tsx` dengan behavior expandable untuk halaman lain.
- Edit/rollback price history.

---

## Architecture Constraints

- **Layers yang boleh disentuh:** halaman/adapter frontend Products, komponen UI lokal Products, endpoint query ProductController, response serializer bila diperlukan, dummy adapters, serta frontend/backend tests.
- **Layers yang tidak boleh disentuh:** schema database, order semantics, stock deduction semantics, cross-module price semantics, dan shared table behavior untuk halaman lain.
- **Patterns:** gunakan komponen `Table`, `Card`, `Badge`, `StatusBadge`, `TableSummary`, `TablePagination`; pertahankan response envelope, pagination, RBAC, dan dummy guard; filter server-side; keep backend/frontend sort allowlists synchronized.
- **Legacy behavior:** jika parameter baru tidak digunakan, pertahankan default existing (`id ASC` dan limit default 100 pada legacy path) sesuai kontrak ProductController. Khususnya, tanpa `include_unpurchasable=1`, filter eligibility supplier (produk dengan supplier nonaktif/orphan dikecualikan) harus tetap berlaku agar katalog publik dan OrderForm tidak berubah.
- **Architecture validation:** PASS.

---

## Dependencies

### Existing (to leverage)

- Next.js/React/TypeScript/Tailwind — UI existing.
- Zustand dummy store — dummy parity.
- Existing UI components — table, cards, badges, pagination, summary, buttons.
- Laravel Eloquent — eager-load supplier, query filters, derived sorting.
- Existing price history endpoint — cursor pagination.
- Existing Jest + Testing Library — frontend tests.
- Existing Laravel/PHPUnit test stack — backend tests.

### New (proposed)

None. No new dependency is needed.

---

## Stories + Scenarios

### Story 1: Identitas barang yang jelas

> As an admin, I want the main table and expanded detail to show complete product identity, so that I can distinguish products without ambiguity.

**Rule 1: Main table identity**

- Example: product `Kopi Kapal`, SKU `SKU-001`, category `Minuman`, active supplier, `is_active=true` displays category and `Aktif`.
- Example: empty category displays `—`.
- Example: inactive supplier displays `Tidak bisa dibeli` unless product itself is inactive, in which case `Nonaktif` has display priority.

```gherkin
Scenario: Main table shows identity columns
  Given product Kopi Kapal has SKU SKU-001, category Minuman, active supplier, and is_active=true
  When an authenticated admin opens Kelola Produk
  Then the table shows Nama, SKU, Kategori, Status, Harga Jual, Stok (unit), Dibuat, Diperbarui, and Aksi
  And the category cell shows Minuman
  And the status badge shows Aktif

Scenario: Empty category uses fallback
  Given product Gula Pasir 1kg has a null or empty category
  When the admin views the row
  Then the category cell shows —

Scenario: Inactive supplier is visible
  Given product Beras Premium has is_active=true and supplier.subscription_status=expired
  When the admin views the row
  Then the status badge shows Tidak bisa dibeli
  And the detail explains Supplier tidak aktif

Scenario: Product inactive takes status display priority
  Given a product has is_active=false and supplier.subscription_status=expired
  When the admin views the row
  Then the single main status badge shows Nonaktif
  And the expanded detail states that the supplier is also not active

Scenario: Null or orphan supplier is safe
  Given a product has supplier_id=null or an orphan supplier reference
  When the admin views or expands the row
  Then supplier renders —
  And the product is not included by the Tidak bisa dibeli status filter
```

**Rule 2: Expanded identity detail**

```gherkin
Scenario: Expand shows complete identity
  Given the admin sees product Kopi Kapal
  When the admin activates its row trigger
  Then an expanded detail panel appears
  And it shows description, category, supplier name and subscription status, product status, created_at, and updated_at
  And aria-expanded is true and aria-controls points to the detail panel

Scenario: Keyboard opens and closes the row
  Given the row trigger is focused
  When the admin presses Enter or Space
  Then the detail panel toggles
  And the row trigger's aria-expanded value reflects the open state
  And the Ubah harga control remains an independent control
```

### Story 2: Harga dan stok tidak ambigu

> As an admin, I want explicit labels and stock-health indicators, so that I know what the displayed price and quantity represent.

**Rule 1: Price and stock labels**

```gherkin
Scenario: Price header is explicit
  Given the admin views the product table
  Then the price header is Harga Jual
  And its tooltip says Harga jual yang digunakan dalam order

Scenario: Stock header is explicit
  Given the admin views the product table
  Then the stock header is Stok (unit)

Scenario: Null stock is rendered consistently
  Given a product has stock_quantity=null
  When the API and UI process the product
  Then the backend treats it as 0
  And the table renders 0 with Stok habis
  And the detail renders Nilai stok Rp 0
```

**Rule 2: Stock health boundaries**

```gherkin
Scenario: Out of stock includes zero and negative values
  Given a product has stock_quantity=0 or stock_quantity=-2
  When the admin views the table
  Then the row is classified as Habis
  And summary.out_of_stock includes it

Scenario: Low stock is one through ten
  Given a product has stock_quantity=1, 8, or 10
  When the admin views the table
  Then the row is classified as Rendah

Scenario: Safe stock starts at eleven
  Given a product has stock_quantity=11
  When the admin views the table
  Then the row is classified as Aman

Scenario: Fractional stock is floored
  Given a product has stock_quantity=10.9
  When the API and UI classify the row
  Then it is treated as 10 and classified as Rendah

Scenario: Expanded detail shows stock value without inventing UoM
  Given a product has Harga Jual Rp 15.000 and normalized stock 40
  When the admin expands the row
  Then the detail shows Nilai stok Rp 600.000
  And it shows Satuan belum terdefinisi di sistem
```

### Story 3: Filter products accurately

> As an admin, I want server-side filters for category, status, and stock health, so that pagination and summary describe the results I am viewing.

**Rule 1: Filter contract**

```gherkin
Scenario: Combined filters use AND semantics
  Given the admin selects category Minuman, status Aktif, and stock health Rendah
  When the product list is loaded
  Then only products satisfying all three filters are returned
  And the product cursor resets to 0
  And all expanded rows are closed

Scenario: Stock health uses API values
  Given the admin selects Habis, Rendah, or Aman
  When the request is sent
  Then the API receives out, low, or ok respectively

Scenario: Invalid filters are ignored
  Given the request contains unknown category, invalid stock_health, array, or malformed filter values
  When the API handles the request
  Then invalid values are ignored
  And the API returns 200 using the remaining valid filters
  And no 500 or validation error is returned

Scenario: Category options are complete
  Given products across all pages have categories Minuman, Sembako, and empty category
  When the admin opens the category filter
  Then options are supplied from backend meta.categories as distinct values
  And the options include Tanpa kategori

Scenario: Category matching trims but remains exact
  Given category values are normalized by trimming
  When the admin selects Minuman
  Then Minuman matches
  And minuman does not match
  And contains-only values such as Minuman Ringan do not match Minuman
```

**Rule 2: Status filters overlap**

```gherkin
Scenario: Status filter Aktif
  Given the admin selects Aktif
  When results load
  Then only products with is_active=true are returned

Scenario: Status filter Nonaktif
  Given the admin selects Nonaktif
  When results load
  Then only products with is_active=false are returned

Scenario: Status filter Tidak bisa dibeli
  Given the admin selects Tidak bisa dibeli
  When results load
  Then products with a non-null/non-orphan supplier and subscription_status != active are returned

Scenario: Status filters may overlap
  Given a product is_nonaktif and its supplier is non-active
  When the admin selects Nonaktif or Tidak bisa dibeli
  Then the product appears in either matching filter
```

**Rule 3: Filtered summary and paging**

```gherkin
Scenario: Summary describes filtered results
  Given filters leave three products and one normalized out-of-stock product
  When the list is returned
  Then meta.summary remains { total: 3, out_of_stock: 1 }
  And total and has_more are calculated from the filtered server-side query

Scenario: Sort remains active after filtering
  Given the admin has selected a sort column and then changes a filter
  When the results reload
  Then the selected sort remains active
  And cursor is reset to 0
```

### Story 4: Supplier response and failure behavior

> As an authenticated product reader, I want supplier context without list failure, so that incomplete supplier data is visible but non-fatal.

```gherkin
Scenario: Admin context exposes unpurchasable products
  Given the admin page sends include_unpurchasable=1
  When GET /products is requested
  Then products with a non-active supplier and orphan supplier references are included
  And the eligibility clause for supplier is skipped only for this request

Scenario: Public catalog eligibility is preserved
  Given a caller does not send include_unpurchasable
  When GET /products is requested
  Then products with non-active or orphan suppliers remain excluded
  And existing catalog eligibility tests remain valid

Scenario: Product response includes eager-loaded supplier
  Given an authenticated user has products:read
  When GET /products returns a product with a supplier
  Then the item includes supplier { id, name, subscription_status }

Scenario: Null supplier does not fail the list
  Given a product has no supplier
  When GET /products is requested
  Then the list succeeds
  And supplier is null or renders — in the UI

Scenario: Product list failure can be retried
  Given GET /products times out or the database fails
  When the admin loads the page
  Then an error state is displayed
  And a Coba lagi action retries the list request
```

### Story 5: Price history in expanded detail

> As an admin, I want recent price history in the expanded row, so that I can inspect changes without leaving the product list.

```gherkin
Scenario: Five latest history entries are shown
  Given a product has 12 price history entries
  When the admin expands the row
  Then five latest entries are shown
  And Muat lebih banyak is available

Scenario: Exactly five entries hide load more
  Given a product has exactly five price history entries
  When the admin expands the row
  Then five entries are shown
  And Muat lebih banyak is not shown

Scenario: Empty history has an empty state
  Given a product has no price history
  When the admin expands the row
  Then an empty history message is shown
  And Muat lebih banyak is not shown

Scenario: History failure has retry
  Given the price history request fails
  When the admin expands the row
  Then the panel shows an error message and Coba lagi

Scenario: Load more is protected from duplicate requests
  Given price history has more entries and a load-more request is in progress
  When the admin clicks Muat lebih banyak again
  Then the button is disabled and only one request is processed

Scenario: Table state change aborts stale history
  Given row A history is loading
  When the admin changes a filter, sort, or page
  Then all expanded rows close
  And the in-flight history request is aborted
  And a stale response cannot render in another row

Scenario: Deleted product history is handled safely
  Given the product is deleted before its history request completes
  When the API returns 404
  Then a clear message is shown
  And the expanded panel closes safely
```

### Story 6: Sorting contracts

```gherkin
Scenario: Category sorting is deterministic
  Given categories Alpha, beta, and null/empty exist
  When the admin sorts by Kategori
  Then values sort alphabetically according to the defined exact normalization
  And null/empty values are last
  And ties use id DESC

Scenario: Status sorting uses derived priority
  Given products are Aktif, Tidak bisa dibeli, and Nonaktif
  When the admin sorts by Status
  Then rows order Aktif, Tidak bisa dibeli, Nonaktif
  And ties use id DESC

Scenario: Frontend and backend sort allowlists agree
  Given Kategori or Status is marked sortable in the UI
  When a sort request is sent
  Then the backend recognizes the same allowlisted column and does not silently fall back
```

---

## Acceptance Criteria

```
Rule: Main table clarity
  ✓ Given a product with category and active supplier, When the admin opens the page, Then columns include Kategori and Status and labels are Harga Jual and Stok (unit).
  ✓ Given category is null/empty, When rendered, Then category shows —.
  ✓ Given product is inactive and supplier is inactive, When rendered, Then one main badge shows Nonaktif and detail exposes supplier inactivity.
  ✓ Given supplier is null/orphan, When rendered, Then supplier shows — and is excluded from Tidak bisa dibeli.

Rule: Stock normalization and health
  ✓ Given stock is NULL, When processed, Then backend COALESCEs it to 0, UI renders 0 + Habis, and summary counts it.
  ✓ Given stock is <=0, When classified, Then it is Habis.
  ✓ Given stock is 1..10, When classified, Then it is Rendah.
  ✓ Given stock is >=11, When classified, Then it is Aman.
  ✓ Given stock is fractional, When classified, Then it is floored before classification.

Rule: Filter contract
  ✓ Given category/status/stock filters, When applied, Then server-side query applies AND semantics and resets cursor to 0.
  ✓ Given invalid scalar, array, or malformed values, When received, Then filters are ignored and response remains 200.
  ✓ Given category metadata is loaded, When filter opens, Then distinct categories plus Tanpa kategori are available.
  ✓ Given filter changes, When results reload, Then sort remains active and expanded rows close.

Rule: Status and category sorting
  ✓ Given Status sort, When requested, Then order is Aktif → Tidak bisa dibeli → Nonaktif with id DESC tie-break.
  ✓ Given Kategori sort, When requested, Then alphabetic order has nulls-last and id DESC tie-break.
  ✓ Given a sortable column is shown, When requested, Then frontend/backend allowlists match.

Rule: Filtered summary and API contract
  ✓ Given active filters, When list returns, Then meta.summary shape remains { total, out_of_stock } over filtered rows.
  ✓ Given supplier exists, When GET /products returns, Then nested supplier contains id, name, subscription_status.
  ✓ Given supplier/list query failure, When GET /products fails, Then UI displays an error and retry.

Rule: Expandable detail and accessibility
  ✓ Given a row trigger, When Enter/Space is pressed, Then detail toggles with aria-expanded and aria-controls.
  ✓ Given action button Ubah harga is focused, When activated, Then it performs its own action and does not toggle row accidentally.
  ✓ Given expanded detail, When loaded, Then it shows identity, supplier, price context, stock value, static unit note, and timestamps.

Rule: Price history
  ✓ Given 12 entries, When expanded, Then five latest are shown and load more is available.
  ✓ Given exactly five or zero entries, When expanded, Then load more is hidden.
  ✓ Given history request fails, When displayed, Then retry is available.
  ✓ Given load more is pending, When clicked repeatedly, Then only one request is made.
  ✓ Given filter/sort/page changes during history loading, When state changes, Then request is aborted and stale data cannot paint another row.
```

---

## Design Decision

**Chosen option:** Option A — local expandable row in Products.

**Summary:** Keep the shared `Table` component unchanged and compose a local row-trigger/detail-row implementation in the Products page/module. Add server-side filters, supplier eager-loading, derived status/category sorting, and full dummy parity while preserving the existing response envelope and cross-module data semantics.

**Rejected options:**

- Option B — dedicated full Products table: rejected because it duplicates existing shared sorting, density, keyboard, and empty-state behavior.
- Option C — side panel only: rejected because it does not satisfy the confirmed expandable-row behavior and weakens row-local context/accessibility.

**Key tradeoffs accepted:**

- A local expandable-row implementation adds Products-specific UI code but prevents shared-table regressions.
- Offset cursor remains for price history, accepting small duplicate/skip risk if a new history entry is inserted during pagination.
- `subscription_status != active` is intentionally used as the non-active rule; orphan supplier records remain `—` and are not treated as non-purchasable.
- A fixed low-stock threshold of 10 is used temporarily; per-product thresholds remain a future schema concern.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|---|---|---|
| Should low-stock threshold eventually be per product? | Assumed fixed threshold 10 for this phase. | Future migration may be needed. |
| Is fractional stock valid in persisted data? | UI/API floor fractional values for classification. | Data-quality policy may need tightening later. |
| Should status detail show both inactive product and inactive supplier? | Main badge uses Nonaktif priority; detail explains both facts. | Copy may need refinement without contract change. |
| Should category case normalization be changed later? | Exact match after trim; case-sensitive. | Legacy category variants may remain separate. |
| Can all `products:read` roles see supplier name/status? | Confirmed yes for this feature. | RBAC policy may later require a restricted admin endpoint. |

---

## Implementation Notes

- Update frontend `AdminProduct` and dummy types to include nested supplier and any metadata needed for categories.
- Preserve `/products` response envelope and existing default behavior when new params are absent.
- Add backend query normalization for invalid scalar/array values without returning validation errors.
- Use SQL-safe expressions/allowlists for derived Status and Category sorting; do not interpolate raw query values.
- Keep `apps/web/src/lib/admin-table.ts` product sort allowlist synchronized with `ProductController`.
- Keep expandable behavior local to Products rather than modifying shared `Table.tsx`.
- Use an `AbortController` and request identity/token guard for price history.
- Update existing tests whose header assertions expect `Harga`, and add frontend/backend tests for the acceptance criteria.
- Use `COALESCE(stock_quantity, 0)` consistently in filtered query and summary.
- Return `meta.categories` as distinct backend metadata and preserve existing meta fields.

---

## Rollback Plan

- Revert the frontend Products page/adapter and backend ProductController query/serializer changes.
- No schema migration is introduced, so database rollback is unnecessary.
- If UI rollout is feature-flagged, disable the Products clarity flag to restore the previous table while retaining the backward-compatible API changes.
- Existing price/order/stock services require no rollback because their semantics are unchanged.
