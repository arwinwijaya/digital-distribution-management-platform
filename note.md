# Catatan Test Case FAILED — Backend Laravel (`apps/api`)

Dokumen ini mencatat test case yang **FAILED** pada test suite backend Laravel,
beserta akar masalah dan status perbaikannya.

> Konteks: seluruh kegagalan di bawah adalah **pre-existing tech debt** — tidak
> disebabkan oleh fitur delivery maupun perubahan lain di working tree.

---

## Ringkasan

| Skenario | Failed | Passed | Skipped | Durasi |
|---|---|---|---|---|
| Sebelum perbaikan (`php artisan test`) | **256** | 179 | 5 | 216 s |
| Setelah perbaikan (`php artisan test`) | **0** | 433 | 8 | 473 s |
| Setelah perbaikan (`DB_HOST=127.0.0.1 php artisan test`) | **0** | 436 | 5 | 482 s |

Ada **2 akar masalah** independen yang menghasilkan 256 kegagalan:

1. **Kaskade 255 test** — `InvoiceConcurrencyHarness` / `PaymentConcurrencyHarness`
   membuka koneksi `new PDO('pgsql:host=db;…')` tanpa guard. Dari host,
   hostname `db` (Docker-only) tidak resolve → `PDOException` dilempar di tengah
   test, lalu `tearDown()` → `close()` melempar error yang sama → `parent::tearDown()`
   tidak pernah jalan → `RefreshDatabase` tidak rollback transaksi sqlite `:memory:`
   → semua test berikutnya dalam proses PHP yang sama gagal dengan
   `"There is already an active transaction"`.
2. **2 assertion basi** — `OrderTest` masih mengharapkan `422` saat re-approve order
   `Confirmed`, padahal kontrak idempoten yang terdokumentasi mengembalikan `200`.

---

## Akar Masalah 1 — Kaskade 255 kegagalan

**Gejala:** 255 test gagal dengan pesan identik:

```
PDOException
There is already an active transaction
at vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php:151
```

satu test gagal dengan error asli:

```
SQLSTATE[08006] [7] could not translate host name "db" to address: Unknown host
at tests/Support/InvoiceConcurrencyHarness.php:31
```

**Bukti determinisme:** jumlah `255` konsisten di setiap run (dengan maupun tanpa
perubahan lain). `DB_HOST=127.0.0.1 php artisan test` menurunkan kegagalan
**255 → 2** (menyisakan 2 assertion basi di Akar Masalah 2).

**Perbaikan yang diterapkan:**

| # | File | Perubahan |
|---|---|---|
| A | `tests/Support/PostgresRaceProbe.php` *(baru)* | Static `isAvailable()` — cek `extension_loaded('pdo_pgsql')`, probe koneksi pgsql dengan `connect_timeout=2`, coba DB terkonfigurasi lalu fallback `postgres`. **Sengaja tidak** mem-gate `config('database.default') === 'pgsql'` karena phpunit mem-pin default ke sqlite → akan selalu skip dan menghapus coverage diam-diam. |
| B | `tests/Feature/InvoiceTest.php`<br>`tests/Feature/PaymentConcurrencyTest.php`<br>`tests/Feature/PaymentTest.php` | `markTestSkipped(...)` bila `! PostgresRaceProbe::isAvailable()` **sebelum** harness dibuat. |
| C | `tests/Support/InvoiceConcurrencyHarness.php`<br>`tests/Support/PaymentConcurrencyHarness.php` | `close()` dibuat exception-safe: `try/catch (\Throwable)` + `finally { $this->database = null; }` supaya `tearDown()` tidak pernah melempar. |

**Daftar 256 test yang FAILED (sebelum perbaikan), per kelas:**


### Tests\Feature\InvoiceTest

- `admin can cancel unpaid invoice`
- `concurrent approvals create one invoice`
- `invalid invoice pagination is rejected`
- `invoice history is bounded and scoped`
- `non admin cannot cancel order`
- `payment row prevents cancellation`

### Tests\Feature\MarketplaceTest

- `marketplace empty state is a stable empty page`
- `marketplace lists active suppliers and products from multiple suppliers`
- `marketplace query indexes are present for listing and status filters`
- `marketplace requires authentication and validates bounds`

### Tests\Feature\MeasurementTest

- `all zero or insufficient actuals remain pending`
- `duplicate event retry is idempotent`
- `empty history returns safe output`
- `forecast wape and strict target boundary are calculated`
- `legacy ai access remains compatible`
- `measurement event ingestion is admin only`
- `recent average fallback is returned for sparse history`
- `recommendation funnel is measured`

### Tests\Feature\OperationalDiagnosticsTest

- `disabled gate preserves existing transaction endpoints`
- `issue detail requires admin and redacts sensitive fields`
- `issues list requires admin and returns filters`
- `readiness disabled gate returns 503`
- `readiness kill switch returns 503`
- `readiness requires admin and returns valid schema`

### Tests\Feature\OperationalReadinessTest

- `e2e approval retry creates one invoice`
- `e2e delivery and partial payment`
- `e2e delivery rejects missing proof`
- `e2e finance assignment is audited`
- `e2e full payment and cancellation guard`
- `e2e http driven dataset composes metrics and histories`
- `e2e metrics and histories are bounded scoped`
- `e2e reminder retry is bounded and immutable`
- `e2e removed finance role denies old token`
- `e2e retries do not duplicate operational records`

### Tests\Feature\OperationalSchemaTest

- `operational indexes and foreign keys exist`
- `operational migrations are reversible`
- `operational models have domain types and factories`
- `operational tables and columns exist`

### Tests\Feature\OrderTest

- `admin can approve new order`
- `admin orders list handles non scalar params without error`
- `admin orders list offset cursor returns second page`
- `admin orders list returns newest first with total and offset cursor`
- `admin orders list silently falls back for invalid sort`
- `admin orders list sorts by status ascending`
- `admin without outlet can list and view orders`
- `cannot approve already confirmed order`
- `concurrent admin approvals append one confirmed history`
- `concurrent same identity submissions return one order result`
- `duplicate order submission is idempotent`
- `duplicate product ids are rejected`
- `inactive supplier product cannot be ordered but supplierless product can`
- `missing request key uses stable identity for retries`
- `non admin cannot list orders`
- `order rejects invalid product`
- `order rejects invalid quantity`
- `order requires items`
- `order status history is tracked`
- `order submission snapshots commission and decrements stock`
- `outlet can create order with products`
- `outlet cannot approve order`
- `unauthenticated user cannot create order`
- `unavailable product does not create partial order`

### Tests\Feature\OutletScoringTest

- `outlet scoring formula with delivered orders`
- `outlet with no orders has score zero`
- `outlet with only pending orders has score zero`
- `recalculate ignores orders older than 90 days`
- `scoring counts sales collected orders same as self serve`

### Tests\Feature\OutletTest

- `legacy outlet route requires authentication`
- `outlet can register with valid data`
- `outlet cannot register with duplicate phone`
- `outlet cannot register without required fields`

### Tests\Feature\PaymentConcurrencyTest

- `concurrent same identity payment posts replay one payment`

### Tests\Feature\PaymentTermTest

- `admin can set valid payment term`
- `invalid payment terms are rejected`

### Tests\Feature\PaymentTest

- `admin can record payment for delivered order`
- `concurrent same identity payment posts replay one payment`
- `order is blocked when credit limit would be exceeded`
- `outstanding balance includes pending and unpaid orders but excludes paid orders`
- `partial payments have non negative balance and replay is idempotent`
- `payment rejects unauthorized order and invalid status or amount`
- `zero credit limit rejects order before order or stock mutation`

### Tests\Feature\Phase1IntegrationTest

- `outlet onboarding to admin approval has no manual association`
- `outlet registration rejects arbitrary user ownership`
- `supplier subscription fields have explicit defaults and allowed values`

### Tests\Feature\PilotQualificationTest

- `fails documentation check`
- `fails internet check`
- `fails pic check`
- `fails with insufficient outlets`
- `qualifies with all checks pass`
- `qualifies with sufficient outlets`

### Tests\Feature\PilotWorkflowTest

- `cancellation excludes from metrics denominator`
- `cancelled order excluded from valid count`
- `concierge creates order as outlet`
- `correlation id propagates across lifecycle`
- `db timing feeds into speed delta`
- `full lifecycle completes through payment`
- `get pilot events`
- `guardrail violation detected`
- `operational event pilot query`
- `order lifecycle to delivered`
- `reliability guardrails`
- `speed delta calculation`
- `volume below target triggers extend`
- `volume meets target`

### Tests\Feature\PrePilotCompatibilityTest

- `analytics finance and pipeline routes keep success shape`
- `approval retry creates one invoice and whatsapp failure does not rollback`
- `delivery missing proof is rejected without mutation`
- `identical retry returns same resource without duplicate`
- `partial payment and overpayment follow payment policy`
- `same key different payload returns 422 and leaves first order unchanged`
- `unauthorized approval and delivery and payment attempt rejected`
- `valid outlet order returns 201 with expected contract`

### Tests\Feature\PrePilotConcurrencyCompatibilityTest

- `concurrent approvals preserve one wins behavior`
- `concurrent payments do not produce negative balance`
- `concurrent same identity orders return one result`

### Tests\Feature\PrePilotControlsTest

- `correlation generates bounded id for missing or unsafe id`
- `correlation journal failure does not change response`
- `correlation journal records actor and failure outcome`
- `correlation journal records one redacted event on success`
- `correlation returns same valid id and preserves body`
- `flag kill switch disables journal`
- `flag off disables journal but preserves behavior`

### Tests\Feature\ProductTest

- `array sort param falls back without server error`
- `empty catalog returns empty array`
- `inactive supplier products are not exposed in legacy catalog`
- `invalid sort falls back to products default id asc`
- `meta summary excludes products of inactive suppliers`
- `meta summary reports total and out of stock for the filtered set`
- `meta total reflects the search filter`
- `product availability is displayed`
- `products are displayed with prices`
- `products can be searched by name`
- `products can be sorted by created at desc with nulls last`
- `products default ordering is id asc and meta total is additive`
- `products require authentication`

### Tests\Feature\PromotionBroadcastTest

- `broadcast creates one message per eligible outlet`
- `message body contains promotion details and outlet name`
- `non admin cannot broadcast`
- `outlet whose recent order referenced inactive supplier product is excluded`
- `outlet with last order older than 30 days is excluded`
- `retry resends only failed messages`
- `second broadcast reports already sent without duplicates`
- `zero eligible outlets returns zero count`

### Tests\Feature\PromotionTest

- `admin can create promotion`
- `admin can delete non broadcast promotion`
- `admin can list promotions with limit plus one`
- `admin can update promotion`
- `admin can view promotion`
- `admin list promotions cursor is offset based`
- `admin list promotions defaults to newest with total and summary`
- `admin list promotions handles non scalar params without error`
- `admin list promotions places null created at rows last in both directions`
- `admin list promotions silently falls back for invalid sort`
- `admin list promotions sorts by start date ascending`
- `broadcast promo update is blocked`
- `non broadcast promo can still be updated`
- `non overlapping promos on same product both valid`
- `outlet cannot access promotions admin routes`
- `overlapping promos on same product rejected`
- `promo applied when min order met`
- `promo created after order does not auto apply`
- `promo not applied when min order not met`
- `promo snapshot frozen at order creation`
- `promo with past end date is not considered active`

### Tests\Feature\RoleManagementTest

- `admin can list users with role filter`
- `admin cannot access platform owner only endpoints`
- `driver cannot access admin endpoints`
- `finance cannot access admin endpoints`
- `invalid role rejected`
- `outlet cannot access admin endpoints`
- `platform owner can access admin endpoints`
- `platform owner can assign role with audit`
- `profile update ignores role field`
- `role assignment invalidates jwt`
- `sales cannot access admin endpoints`
- `supplier cannot access admin endpoints`
- `update payment terms platform owner allowed`
- `update payment terms requires admin`
- `user can update profile name and email`
- `user listing defaults to newest first with total and cursor`
- `user listing handles non scalar sort param without error`
- `user listing has pagination meta`
- `user listing limit plus one technique`
- `user listing role filtered total counts only matching role`
- `user listing silently falls back for invalid sort`
- `user listing sorts by name ascending`

### Tests\Feature\SalesOrderTest

- `admin cannot create sales order through sales endpoint`
- `outlet user cannot create sales order`
- `sales order applies promotion through shared pipeline`
- `sales order is idempotent for same identity`
- `sales order rejects unknown outlet`
- `sales order requires items`
- `sales order requires outlet id`
- `sales user can create order for outlet in own territory`
- `sales user cannot create order for untagged outlet`
- `sales user cannot create order outside territory`
- `sales user without territory cannot create order`
- `unauthenticated user cannot create sales order`

### Tests\Feature\SalesOutletsTest

- `admin user gets 403`
- `outlet user gets 403`
- `outlets ordered by name`
- `response includes expected fields only`
- `sales user with territory gets filtered outlets`
- `sales user without territory gets 403`
- `unauthenticated user gets 401`

### Tests\Feature\SalesPerformanceTest

- `achievement percentage of target`
- `admin can create sales target`
- `admin can delete sales target`
- `admin can list sales targets with limit plus one`
- `admin can update sales target`
- `admin can view sales target`
- `admin performance cursor is offset not id`
- `admin performance invalid sort falls back to default`
- `admin performance meta total and cursor offset`
- `admin performance returns all sales users`
- `admin performance sort achievement falls back to default`
- `admin performance sort by id descending`
- `admin performance sort by name descending`
- `admin performance with limit plus one pagination`
- `cancelled orders excluded from achievement`
- `duplicate target for same user and period rejected`
- `invalid period format rejected`
- `my performance defaults to current period`
- `new orders excluded from achievement`
- `orders outside period are excluded`
- `outlet user cannot create sales target`
- `performance service counts all revenue statuses`
- `sales user cannot access admin performance`
- `sales user cannot access my performance without sales role`
- `sales user cannot create sales target`
- `sales user cannot list sales targets`
- `sales user my performance returns own data`
- `zero target yields zero percentage no division by zero`

### Tests\Feature\SalesTest

- `sales user can plan a visit`
- `sales visits are scoped and outlet users are rejected`
- `sales visits pagination is bounded and scoped`

### Tests\Feature\StockPlanningTest

- `invalid stock planning input is safe`
- `sku receives a reorder recommendation`
- `stock planning endpoint is admin only`
- `sufficient stock or zero demand produces no reorder`

### Tests\Feature\SupplierPerformanceTest

- `missing delivery data does not bias on time score`
- `supplier bi is admin only`
- `supplier score exposes component and weighted values`
- `supplier with no observations is insufficient`

### Tests\Feature\WhatsAppTest

- `ambiguous input is rejected and feature can be disabled`
- `ambiguous sender is rejected and phone variants are canonicalized`
- `catalog is bounded and sent through client`
- `confirmation notification and catalog use client`
- `duplicate provider delivery does not create another order`
- `failed inbound event can retry without duplicate order`
- `fresh sending lease is not duplicated but stale unknown send is reclaimed`
- `inactive supplier product cannot be ordered but supplierless product can`
- `provider failure does not change confirmed order and can retry`
- `signature and sender are required even when legacy bypass is disabled`
- `structured items are non empty integer and distinct`
- `valid message creates one order`

---

## Akar Masalah 2 — 2 assertion basi di `OrderTest`

Kedua test ini menguji ulang perilaku yang **sudah berubah** dan tidak lagi sesuai
kontrak. Keduanya **lulus** setelah dijalankan dengan PostgreSQL terjangkau
(`DB_HOST=127.0.0.1`), sehingga bukan bagian dari kaskade.

| Test | Assertion lama | Aktual | Pesan kegagalan |
|---|---|---|---|
| `OrderTest > cannot approve already confirmed order` | `assertStatus(422)` | **200** | `Expected response status code [422] but received 200.` |
| `OrderTest > concurrent admin approvals append one confirmed history` | `assertSame([200, 422], $statuses)` | **[200, 200]** | `Failed asserting that two arrays are identical` (index 1: 422 vs 200) |

**Kenapa basi:** `OrderController::approveInTransaction()` sengaja memperlakukan
re-approve order `Confirmed` sebagai **idempoten** (pakai ulang invoice, `200`,
tanpa baris history duplikat). `approvalResponse()` hanya mengembalikan `422`
saat `$approved === null` (state selain `New`/`Confirmed`).

Kontrak ini terdokumentasi di `docs/pilot/pre-pilot-compatibility-baseline.md:37`,
dan test saudara `InvoiceTest::test_approval_retry_reuses_invoice` sudah
meng-assert `200` + 1 invoice + 1 history row. Kedua test `OrderTest` dibuat
3 hari **sebelum** commit idempotensi (`9c0c8564` < `7407152`).

**Perbaikan yang diterapkan (test-only, tanpa ubah kode produksi):**

| # | File | Perubahan |
|---|---|---|
| D | `tests/Feature/OrderTest.php` | `test_cannot_approve_already_confirmed_order` → di-rename menjadi `test_reapproving_already_confirmed_order_is_idempotent`, assert `200` + `status=success` + tepat **1 invoice** + tepat **1** baris history `Confirmed`. `test_concurrent_admin_approvals_append_one_confirmed_history` → assert `[200, 200]` + docblock disesuaikan. |
| E | `docs/pilot/pre-pilot-compatibility-baseline.md` | Entri gap L127 diubah dari "Gap — test-to-implementation mismatch" menjadi "Resolved", mencatat assertion basi telah dikoreksi. |

---

## Verifikasi

```
# 1. Default (pgsql tidak terjangkau) → race tests di-skip, tidak ada kaskade
php artisan test
#   Tests:  8 skipped, 433 passed (3219 assertions)   → 0 failed

# 2. PostgreSQL terjangkau → race tests benar-benar berjalan (bukan skip)
DB_HOST=127.0.0.1 php artisan test
#   Tests:  5 skipped, 436 passed (3246 assertions)   → 0 failed

# 3. Targeted
DB_HOST=127.0.0.1 php artisan test --filter="InvoiceTest|PaymentConcurrencyTest|PaymentTest|OrderTest"
#   Tests:  64 passed (419 assertions)                → 0 failed
```

Jumlah skip naik tepat **+3** (5 → 8) pada run default, sesuai 3 race test yang
kini di-skip dengan pesan:
`"PostgreSQL race database is unreachable; skipping ... concurrency coverage."`

---

## Catatan tambahan

- `DeliveryConcurrencyTest.php:81` masih memakai `assertContains(..., [200, 422], ...)`
  untuk transisi delivery (domain berbeda) dan **sengaja tidak diubah** karena
  sudah toleran terhadap kedua nilai.
- Pre-existing stderr noise di web jest dari `src/app/operations/page.tsx`
  (React `act` warning) bukan kegagalan.
