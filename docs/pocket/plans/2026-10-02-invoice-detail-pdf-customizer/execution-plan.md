# EXECUTION PLAN — Invoice Detail, PDF Export & Template Customization

**Date:** 2026-10-02
**Spec:** docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
**Status:** draft
**Total tasks:** 8

---

## Execution Overview

### Recommended Order
```
T1, T2 (parallel, prereq) → T3, T4 (parallel) → T5 → T6, T7 (parallel) → T8
```

> Dependency order above is **recommended** — pocket skill enforces actual parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1, T2 | none (prereq) |
| Group B | T3, T4 | T1, T2 complete |
| Group C | T6, T7 | T5 completes |
| Group D | T8 | T3 completes |

### Constraints Reminder
**Architecture:** Laravel 11 + PHP 8.3 + PostgreSQL; Next 16 + React 18 + Tailwind + Zustand; preserve `invoices:read` RBAC; add read-only `GET /invoices/{id}` and `GET /invoices/{id}/pdf`; new `invoice_templates` single active row; new `order_items.product_name_snapshot` column; server-side `barryvdh/laravel-dompdf ^2.0` only; dummy-mode parity zero-network
**Out-of-scope:** Email/WhatsApp sharing, payment processing / invoice issuance-cancellation changes, multiple template catalogs, drag-and-drop or raw HTML editors, company-profile beyond invoice fields
**Assumptions at risk:** `product_name_snapshot` is a new column populated at invoice creation (verified absent today); `invoice_template:edit` for admin only and `invoice_template:read` for admin+finance are new RBAC entries; PDF parity mirrors detail JSON plus template toggles
**Sequencing:** Dependency order shown is recommended only — pocket enforces actual blocking rules. Do not treat `[depends: TN]` as a hard lock unless the task cannot logically proceed without the prerequisite's output.

### File Structure Map

```
Rule: Authorization (detail + PDF)
  Create: (none)
  Modify: apps/api/routes/api.php (created by: T4)
  Modify: apps/api/app/Http/Controllers/InvoiceController.php (created by: T4)
  Modify: apps/api/app/Services/InvoiceService.php (created by: T4)
  Test:   apps/api/tests/Feature/InvoiceDetailAuthorizationTest.php (created by: T4)
  Test:   apps/api/tests/Feature/InvoicePdfAuthorizationTest.php (created by: T5)

Rule: Content (frozen name, payments, totals, overdue/cancelled)
  Create: (none)
  Modify: apps/api/app/Services/InvoiceService.php (created by: T4)
  Modify: apps/api/app/Models/OrderItem.php (created by: T2)
  Test:   apps/api/tests/Feature/InvoiceDetailContentTest.php (created by: T4)

Rule: PDF parity (template toggles, watermark, filename)
  Create: apps/api/resources/views/invoices/pdf.blade.php (created by: T5)
  Modify: apps/api/app/Http/Controllers/InvoiceController.php (created by: T5)
  Modify: apps/api/composer.json (created by: T5)
  Test:   apps/api/tests/Feature/InvoicePdfParityTest.php (created by: T5)

Rule: PDF performance (≤5s)
  Create: (none)
  Modify: apps/api/app/Services/PdfGeneratorService.php (created by: T5)
  Test:   apps/api/tests/Feature/InvoicePdfPerformanceTest.php (created by: T5)

Rule: Template admin (single corporate template)
  Create: apps/api/database/migrations/xxxx_xx_xx_create_invoice_templates_table.php (created by: T1)
  Create: apps/api/app/Models/InvoiceTemplate.php (created by: T1)
  Create: apps/api/database/seeders/InvoiceTemplateSeeder.php (created by: T1)
  Create: apps/api/app/Http/Controllers/Admin/InvoiceTemplateController.php (created by: T3)
  Create: apps/api/app/Http/Requests/UpdateInvoiceTemplateRequest.php (created by: T3)
  Create: apps/web/src/app/admin/invoice-template/page.tsx (created by: T8)
  Test:   apps/api/tests/Feature/InvoiceTemplateAdminTest.php (created by: T3)

Rule: Template RBAC finance read / admin edit
  Create: (none)
  Modify: apps/api/database/seeders/RoleMenuAccessSeeder.php (created by: T1)
  Test:   apps/api/tests/Feature/InvoiceTemplateRbacTest.php (created by: T3)

Rule: Dummy-mode parity (detail JSON + static PDF blob)
  Create: (none)
  Modify: apps/api/database/factories/InvoiceFactory.php (existing)
  Modify: apps/api/database/factories/OrderItemFactory.php (existing)
  Modify: apps/api/app/Http/Controllers/InvoiceController.php (existing)
  Test:   apps/api/tests/Feature/DummyInvoiceParityTest.php (created by: T6)

Rule: Frontend detail page + Export PDF
  Create: apps/web/src/app/invoices/[id]/page.tsx (created by: T7)
  Create: apps/web/src/app/invoices/components/InvoiceDetail.tsx (created by: T7)
  Modify: apps/web/src/app/invoices/api.ts (existing)
  Modify: apps/web/src/app/invoices/page.tsx (existing)
  Test:   apps/web/src/app/invoices/__tests__/detail.test.tsx (created by: T7)
  Test:   apps/web/src/app/invoices/__tests__/export-pdf.test.tsx (created by: T7)
```

Note: `(created by: T<N>)` annotations mark files that do not exist until T<N> runs. The implementer writing a RED test must not import from a file a later task creates — the test would fail on an import error instead of the behavior it is meant to prove.

---

## Pocket Packets

---

### Task 1: Create invoice_templates table, model, seeder, and RBAC entries [prereq]

## OBJECTIVE
Create the single-active-row corporate template storage and its RBAC permissions. Seeded row is PT Digital Distribusi Nusantara with Navy/Slate palette.

Steps:
1. Write failing test for: invoice_templates table and seeded default row exist
   Test file: `apps/api/tests/Feature/InvoiceTemplateMigrationTest.php`
   Level: integration
   Test intent: Given a fresh migration run / When inspecting schema and seeded data / Then table `invoice_templates` exists with columns id, logo_path, company_name, address, npwp, primary_color, footer_text, notes, signer_name, signer_title, show_npwp, show_outlet_phone, timestamps and one seeded row with company_name PT Digital Distribusi Nusantara
   Exercise through: database schema + seeder run
   Test doubles: none (uses RefreshDatabase on sqlite/postgres test DB); do NOT mock the migration
   Expected RED: table does not exist and seeder class missing
2. Run test — verify FAIL: `php artisan test --filter=InvoiceTemplateMigrationTest`
3. Implement migration `xxxx_xx_xx_create_invoice_templates_table.php`, model `app/Models/InvoiceTemplate.php` with fillable matching columns, seeder `database/seeders/InvoiceTemplateSeeder.php` inserting the default corporate row, and RBAC seed entries for `invoice_template:read` (admin, finance) and `invoice_template:edit` (admin only) in `RoleMenuAccessSeeder`; verify PASS with same command; refactor while green to extract fillable constant; commit

4. Write failing test for: invoice_templates RBAC entries seeded
   Test file: `apps/api/tests/Feature/InvoiceTemplateRbacSeedTest.php`
   Level: integration
   Test intent: Given seeded RoleMenuAccess / When querying menu_key invoice_template / Then admin has edit level and finance has read level, other roles have none
   Exercise through: RoleMenuAccess query
   Test doubles: none (uses seeded DB)
   Expected RED: menu_key invoice_template has no rows
5. Run test — verify FAIL: `php artisan test --filter=InvoiceTemplateRbacSeedTest`
6. Implement RBAC seeder entries for invoice_template (read for admin+finance, edit for admin); verify PASS; refactor while green; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Template admin + Template RBAC, Architecture Constraints (invoice_templates columns, seeder, RBAC levels), Design Decision (Option A)

## WHY THIS APPROACH
Complexity: lightweight
Justification: Isolated schema work with no dependents yet; must land before any detail/PDF/template code reads it.

## SANDWICH CONTEXT
[CRITICAL: Must not modify existing invoice issuance or cancellation logic and must preserve invoices:read]
You are implementing invoice_templates storage for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/database/migrations/*create_invoice_templates*, apps/api/app/Models/InvoiceTemplate.php, apps/api/database/seeders/InvoiceTemplateSeeder.php, apps/api/database/seeders/RoleMenuAccessSeeder.php
Available after: none (prereq)
Architecture rule: Migration must be backward compatible (up creates new table only, down drops it); no change to existing invoice tables
[RESTATE: Must not modify existing invoice issuance or cancellation logic and must preserve invoices:read]

## DELIVERABLE
Given a fresh database, When migrations and seeders run, Then invoice_templates table exists with all declared columns and one default corporate row
Given seeded RBAC, When checking RoleMenuAccess for invoice_template, Then admin has edit and finance has read, others have none
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Migration up creates table with exact columns: logo_path nullable, company_name, address, npwp, primary_color, footer_text nullable, notes nullable, signer_name nullable, signer_title nullable, show_npwp boolean default true, show_outlet_phone boolean default true
  - Down drops table
  - Seeder inserts exactly one default row (PT Digital Distribusi Nusantara)
  - RBAC seed adds invoice_template:read for admin+finance and invoice_template:edit for admin

Must-not-have:
  - Any change to orders, order_items, invoices, payments tables
  - Client-side PDF library

Open question risks:
  - none (resolved)

Rollback note:
  - php artisan migrate:rollback drops invoice_templates; seeder is re-runnable

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: RBAC matrix shape differs from RoleMenuAccess conventions
Escalate when: Migration touches existing invoice tables

---

### Task 2: Add product_name_snapshot column to order_items [prereq]

**OBJECTIVE**
Add the frozen product-name snapshot column that detail and PDF will read, verifying the real schema today has no such column.

Steps:
1. Write failing test for: order_items has product_name_snapshot column
   Test file: `apps/api/tests/Feature/OrderItemSnapshotColumnTest.php`
   Level: integration
   Test intent: Given a fresh migrated database / When inspecting order_items schema / Then column `product_name_snapshot` exists as nullable string and `OrderItem` model `fillable` includes it
   Exercise through: Schema inspection via Laravel's Schema facade and model `$fillable`
   Test doubles: none (uses in‑memory SQLite test DB)
   Expected RED: column does not exist and model lacks fillable entry
2. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotColumnTest`
3. Implement migration `xxxx_xx_xx_add_product_name_snapshot_to_order_items_table.php` adding nullable string `product_name_snapshot` after `product_id`, update `app/Models/OrderItem.php` `$fillable` and `casts` to include the new column, backfill existing rows from the related `products.name` where null; verify PASS; refactor while green to extract column constant; commit

4. Write failing test for: snapshot is populated at order creation and survives product rename
   Test file: `apps/api/tests/Feature/OrderItemSnapshotPopulateTest.php`
   Level: integration
   Test intent: Given a product named `Sabun A` and an order created for it / When the order is persisted via `OrderCreationService::persistOrder` / Then the resulting `order_items.product_name_snapshot` equals `Sabun A` and does not change after the product is renamed to `Sabun B`
   Exercise through: Create product, create order via `OrderCreationService->create(...)`, then rename product and fetch order items
   Test doubles: none; use real services (no mocking of OrderCreationService)
   Expected RED: snapshot column remains null or reflects renamed product name
5. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotPopulateTest`
6. Implement snapshot population directly in `app/Services/OrderCreationService::persistOrder` after each `OrderItem::create` call, setting `product_name_snapshot` to `$product->name` (retrieved via `$order->items->first()->product->name` or via the `$items` array prepared earlier). Ensure existing backfill logic runs for legacy rows.
   Verify PASS; refactor while green to extract a helper `populateProductSnapshot(OrderItem $item, Product $product)`; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Content (frozen product name), Architecture Constraints (new column via migration), verified schema: order_items today has no snapshot column

## WHY THIS APPROACH
Complexity: lightweight
Justification: Schema addition isolated to order_items; must exist before detail/PDF read it.

## SANDWICH CONTEXT
[CRITICAL: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]
You are implementing product_name_snapshot for Invoice Detail, PDF Export \& Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/database/migrations/*add_product_name_snapshot*, apps/api/app/Models/OrderItem.php, apps/api/app/Services/OrderCreationService.php (persistOrder method), apps/api/database/factories/OrderItemFactory.php
Available after: none (prereq)
Architecture rule: Additive migration only; nullable column so existing rows remain valid; down drops column
[RESTATE: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]

## DELIVERABLE
Given migrated DB, When inspecting order_items, Then `product_name_snapshot` column exists and is fillable
Given a product `Sabun A`, When an order is created via `OrderCreationService::persistOrder` / `OrderCreationService->create`, Then `order_items.product_name_snapshot` equals `Sabun A` and does not change after product rename
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Nullable string column `product_name_snapshot`
  - Backfill for existing rows; creation path populates snapshot from product name

Must-not-have:
  - Recomputing snapshot on every read; changes to invoice totals or payment logic

Open question risks:
  - none

Rollback note:
  - migrate:rollback drops column; snapshot code can be removed without data loss

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Order creation path is not the one that ultimately creates `order_items`; if so, create an observer instead.
Escalate when: Constraint violated.


## OBJECTIVE
Add the frozen product-name snapshot column that detail and PDF will read, verifying the real schema today has no such column.

Steps:
1. Write failing test for: order_items has product_name_snapshot column
   Test file: `apps/api/tests/Feature/OrderItemSnapshotColumnTest.php`
   Level: integration
   Test intent: Given migrated database / When inspecting order_items schema / Then column product_name_snapshot exists as nullable string and OrderItem fillable includes it
   Exercise through: Schema::hasColumn and model fillable
   Test doubles: none (uses test DB)
   Expected RED: column does not exist
2. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotColumnTest`
3. Implement migration `xxxx_xx_xx_add_product_name_snapshot_to_order_items_table.php` adding nullable string product_name_snapshot after product_id, update `app/Models/OrderItem.php` fillable and casts, backfill existing rows from products.name where null; verify PASS; refactor while green; commit

4. Write failing test for: new order_items snapshot is populated from product name at creation
   Test file: `apps/api/tests/Feature/OrderItemSnapshotPopulateTest.php`
   Level: integration
   Test intent: Given a product named Sabun A / When an order_item is created for that product / Then product_name_snapshot equals Sabun A and survives a later product rename to Sabun B
   Exercise through: OrderItem factory / creation path + Product update
   Test doubles: none (uses factories); do NOT mock OrderItem
   Expected RED: snapshot column stays null or follows rename
5. Run test — verify FAIL: `php artisan test --filter=OrderItemSnapshotPopulateTest`
6. Implement snapshot population in order creation service/observer (set product_name_snapshot from product.name on create if null); verify PASS; refactor while green to extract helper; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Content (frozen product name), Architecture Constraints (new column via migration), verified schema: order_items today has no snapshot column

## WHY THIS APPROACH
Complexity: lightweight
Justification: Schema addition isolated to order_items; must exist before detail/PDF read it.

## SANDWICH CONTEXT
[CRITICAL: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]
You are implementing product_name_snapshot for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/database/migrations/*add_product_name_snapshot*, apps/api/app/Models/OrderItem.php, apps/api/app/Services/OrderService.php or OrderItem observer, apps/api/database/factories/OrderItemFactory.php
Available after: none (prereq)
Architecture rule: Additive migration only; nullable column so existing rows remain valid; down drops column
[RESTATE: Must not modify existing invoice issuance or cancellation logic beyond adding the snapshot column]

## DELIVERABLE
Given migrated DB, When inspecting order_items, Then product_name_snapshot column exists and is fillable
Given a product Sabun A, When an order_item is created, Then snapshot equals Sabun A and does not change when product is renamed to Sabun B
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Nullable string column product_name_snapshot
  - Backfill for existing rows
  - Creation path populates snapshot from product.name

Must-not-have:
  - Recomputing snapshot on every read
  - Changes to invoice totals or payment logic

Open question risks:
  - none

Rollback note:
  - migrate:rollback drops column

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Order creation path is not the one that ultimately creates order_items
Escalate when: Constraint violated

---

### Task 3: Admin template CRUD API and structured form [depends: T1]

## OBJECTIVE
Expose the single corporate template for admin edit (finance read) with logo validation and structured fields.

Steps:
1. Write failing test for: admin can read and update template, finance can read but not edit
   Test file: `apps/api/tests/Feature/InvoiceTemplateAdminTest.php`
   Level: integration
   Test intent: Given admin with invoice_template:edit / When GET /admin/invoice-template Then 200 with template JSON; When POST with valid fields and logo ≤2MB JPG/PNG/SVG Then 200 and persisted; Given finance with invoice_template:read / When GET Then 200 but POST Then 403; Given outlet role / When GET Then 403
   Exercise through: HTTP routes GET /admin/invoice-template and POST /admin/invoice-template via Rbac middleware
   Test doubles: Storage fake for logo upload; do NOT mock controller
   Expected RED: routes return 404 and no validation
2. Run test — verify FAIL: `php artisan test --filter=InvoiceTemplateAdminTest`
3. Implement routes in `apps/api/routes/api.php` under auth:api + rbac:invoice_template:read for GET and rbac:invoice_template:edit for POST, controller `app/Http/Controllers/Admin/InvoiceTemplateController.php` with show/update, FormRequest `app/Http/Requests/UpdateInvoiceTemplateRequest.php` validating company_name, address, npwp, primary_color hex, footer_text, notes, signer fields, show_npwp/show_outlet_phone booleans, logo file max 2048 mime jpg/png/svg, storing to disk and updating InvoiceTemplate single row; verify PASS; refactor while green to extract validation; commit

4. Write failing test for: logo validation rejects oversized and wrong mime and preserves prior file
   Test file: `apps/api/tests/Feature/InvoiceTemplateLogoValidationTest.php`
   Level: integration
   Test intent: Given existing template with logo / When POST with logo >2MB or mime pdf Then 422 and stored logo_path unchanged
   Exercise through: POST /admin/invoice-template with fake files
   Test doubles: Storage fake
   Expected RED: request succeeds or prior logo is lost
5. Run test — verify FAIL: `php artisan test --filter=InvoiceTemplateLogoValidationTest`
6. Implement strict file validation in FormRequest and controller logic that only replaces logo_path on success; verify PASS; refactor while green; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Template admin + Template RBAC, Architecture Constraints (single active row, fields, logo handling), Design Decision (Option A)

## WHY THIS APPROACH
Complexity: standard
Justification: Requires routing, RBAC, validation, file storage, and persistence; moderate branching.

## SANDWICH CONTEXT
[CRITICAL: Only admin with invoice_template:edit may edit/publish; admin and finance with invoice_template:read may view]
You are implementing admin template CRUD for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/routes/api.php, apps/api/app/Http/Controllers/Admin/InvoiceTemplateController.php, apps/api/app/Http/Requests/UpdateInvoiceTemplateRequest.php, apps/api/app/Models/InvoiceTemplate.php
Available after: T1 (table and RBAC seed)
Architecture rule: Structured form only — no raw HTML or drag-and-drop; respect existing Rbac middleware pattern rbac:menu:level
[RESTATE: Only admin with invoice_template:edit may edit/publish; admin and finance with invoice_template:read may view]

## DELIVERABLE
Given admin with edit permission, When GET /admin/invoice-template, Then 200 with template JSON
Given admin, When POST valid payload with logo, Then 200 and row updated
Given finance with read, When GET, Then 200 but POST Then 403
Given invalid logo (>2MB or wrong mime), When POST, Then 422 and prior logo preserved
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - GET guarded by rbac:invoice_template:read, POST by rbac:invoice_template:edit
  - Validation for all template fields and logo size/mime
  - Storage fake compatible in tests

Must-not-have:
  - Multiple template rows
  - Raw HTML editor
  - Exposure to outlet role

Open question risks:
  - none

Rollback note:
  - Routes are additive; removing them restores prior behavior

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Rbac level mapping for new menu_key behaves differently
Escalate when: Constraint violated

---

### Task 4: Invoice detail endpoint with authorization and content [depends: T1, T2]

## OBJECTIVE
Provide click-to-detail JSON that respects outlet ownership and returns frozen names, full payment history, stored totals, and overdue badge.

Steps:
1. Write failing test for: detail authorization matrix
   Test file: `apps/api/tests/Feature/InvoiceDetailAuthorizationTest.php`
   Level: integration
   Test intent: Given outlet user outlet_id=5 with invoices:read / When GET /invoices/123 owned by 5 Then 200; When GET /invoices/999 owned by 9 Then 403; Given no token When GET /invoices/123 Then 401; Given valid token When GET /invoices/555555 Then 404
   Exercise through: HTTP GET /invoices/{id} via Rbac + controller
   Test doubles: none (uses factories for invoices/orders/outlets); do NOT mock InvoiceService
   Expected RED: route returns 404 for all ids
2. Run test — verify FAIL: `php artisan test --filter=InvoiceDetailAuthorizationTest`
3. Implement route GET /invoices/{id} with middleware auth:api + rbac:invoices:read, controller method InvoiceController@show delegating to InvoiceService::getDetail, enforcing outlet ownership (outlet users only see own outlet_id, admin/finance see all), returning 403 for cross-outlet, 404 for missing; verify PASS; refactor while green; commit

4. Write failing test for: detail content uses snapshot, full payments, stored totals, badges
   Test file: `apps/api/tests/Feature/InvoiceDetailContentTest.php`
   Level: integration
   Test intent: Given invoice created when product was Sabun A then product renamed to Sabun B / When GET detail Then line item shows Sabun A (snapshot); Given invoice with 3 payments completed/pending/failed / When GET detail Then all 3 returned ordered created_at DESC; Given stored totals / When GET detail Then total_amount/paid_amount/balance_amount equal stored snapshot not recomputed; Given due_date < today and balance >0 / When GET detail Then overdue badge true; Given status cancelled / When GET detail Then status cancelled and totals still stored
   Exercise through: HTTP GET /invoices/{id} JSON shape (header, line items, payments, outlet info)
   Test doubles: none; use factories with product_name_snapshot
   Expected RED: returns live product name, filters payments, or recomputes totals
5. Run test — verify FAIL: `php artisan test --filter=InvoiceDetailContentTest`
6. Implement InvoiceService::getDetail to eager load order.orderItems.product, payments, outlet, map line items from product_name_snapshot fallback, order payments by created_at DESC without status filter, return stored totals, compute overdue badge as due_date < today && balance_amount > 0, surface status; verify PASS; refactor while green extracting badge helper; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Authorization + Content, Architecture Constraints (read-only detail, preserve invoices:read), verified schema for order_items snapshot

## WHY THIS APPROACH
Complexity: standard
Justification: Authz plus content mapping across multiple relations; needs careful outlet scoping and snapshot handling.

## SANDWICH CONTEXT
[CRITICAL: Cross-outlet detail must return 403 and must not leak data; read-only, no change to issuance/cancellation]
You are implementing invoice detail endpoint for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/routes/api.php, apps/api/app/Http/Controllers/InvoiceController.php, apps/api/app/Services/InvoiceService.php, apps/api/app/Models/Invoice.php, apps/api/app/Models/OrderItem.php
Available after: T1 (template table), T2 (snapshot column)
Architecture rule: Route is read-only GET with rbac:invoices:read; outlet scoping must match existing list endpoint; overdue badge is computed dynamically but totals are stored snapshots
[RESTATE: Cross-outlet detail must return 403 and must not leak data; read-only, no change to issuance/cancellation]

## DELIVERABLE
Given outlet user accessing own invoice, When GET /invoices/{id}, Then 200 with header, frozen line items, payment history, outlet info
Given outlet accessing another outlet's invoice, When GET, Then 403
Given unauthenticated, When GET, Then 401
Given missing id, When GET, Then 404
Given product rename after invoice, When GET detail, Then line item shows frozen snapshot name
Given multiple payments, When GET detail, Then all statuses returned newest first
Given stored totals, When GET detail, Then totals equal snapshot
Given overdue condition, When GET detail, Then badge true; Given cancelled, Then status cancelled with stored totals
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - RBAC via rbac:invoices:read
  - Outlet ownership check
  - Snapshot, full payment history, stored totals, overdue badge logic

Must-not-have:
  - Recomputed totals
  - Filtered payments
  - Modification of invoice/order data

Open question risks:
  - none

Rollback note:
  - Removing route restores prior 404 behavior

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Outlet scoping diverges from list endpoint
Escalate when: Constraint violated

---

### Task 5: PDF export endpoint and Blade view [depends: T4]

## OBJECTIVE
Generate a server-side PDF that mirrors the detail JSON and respects the active template, with correct filename, watermark, badge, and performance.

Steps:
1. Write failing test for: PDF authorization mirrors detail
   Test file: `apps/api/tests/Feature/InvoicePdfAuthorizationTest.php`
   Level: integration
   Test intent: Given outlet user outlet_id=5 / When GET /invoices/123/pdf owned by 5 Then 200 application/pdf; When GET /invoices/999/pdf owned by 9 Then 403; Given no token When GET pdf Then 401; Given missing id When GET pdf Then 404
   Exercise through: HTTP GET /invoices/{id}/pdf
   Test doubles: none
   Expected RED: route missing returns 404
2. Run test — verify FAIL: `php artisan test --filter=InvoicePdfAuthorizationTest`
3. Implement route GET /invoices/{id}/pdf with same RBAC, controller method InvoiceController@pdf reusing InvoiceService::getDetail and outlet check; verify PASS; refactor while green; commit

4. Write failing test for: PDF parity with template toggles, watermark, badge, filename
   Test file: `apps/api/tests/Feature/InvoicePdfParityTest.php`
   Level: integration
   Test intent: Given active template with show_npwp false / When GET pdf Then rendered PDF bytes do not contain NPWP line but do contain company_name/address and primary_color; Given cancelled invoice / When GET pdf Then PDF contains BATAL watermark; Given overdue invoice / When GET pdf Then PDF contains OVERDUE badge; And Content-Disposition filename is INV-YYYYMMDD-{id}.pdf with Content-Type application/pdf
   Exercise through: HTTP GET /invoices/{id}/pdf response headers and PDF text extraction (via Dompdf text or blade render assertion)
   Test doubles: none; uses real Dompdf rendering; do NOT mock PdfGeneratorService
   Expected RED: PDF not generated or ignores template toggles
5. Run test — verify FAIL: `php artisan test --filter=InvoicePdfParityTest`
6. Implement composer require barryvdh/laravel-dompdf ^2.0, Blade view resources/views/invoices/pdf.blade.php rendering header, line items from snapshot, payments, outlet info, template fields (logo, company_name, address, npwp conditional on show_npwp, outlet phone conditional on show_outlet_phone, primary_color, footer_text, notes, signer), conditional BATAL watermark and OVERDUE badge, service PdfGeneratorService::renderInvoicePdf wrapping Dompdf loadView and streaming with correct headers and filename INV-YYYYMMDD-{id}.pdf; verify PASS; refactor while green; commit

7. Write failing test for: PDF performance bound
   Test file: `apps/api/tests/Feature/InvoicePdfPerformanceTest.php`
   Level: integration
   Test intent: Given normal invoice (<100 line items, <10 payments) / When GET /invoices/{id}/pdf Then response time <5 seconds
   Exercise through: HTTP GET /invoices/{id}/pdf with timing
   Test doubles: none
   Expected RED: generation exceeds bound or not measured
8. Run test — verify FAIL: `php artisan test --filter=InvoicePdfPerformanceTest`
9. Implement performance guard (eager loading, limited image size) and assert timing in test; verify PASS; refactor while green; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: PDF parity + Performance + Authorization, Design Decision Option A (Dompdf), Architecture Constraints (read-only PDF, template toggles)

## WHY THIS APPROACH
Complexity: deep
Justification: PDF rendering, template binding, authz reuse, filename and performance constraints; multiple GWT scenarios.

## SANDWICH CONTEXT
[CRITICAL: PDF must be server-side Dompdf only, read-only, and must respect active template toggles exactly as detail]
You are implementing PDF export for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/routes/api.php, apps/api/app/Http/Controllers/InvoiceController.php, apps/api/app/Services/PdfGeneratorService.php, apps/api/resources/views/invoices/pdf.blade.php, apps/api/composer.json, apps/api/app/Services/InvoiceService.php
Available after: T4 (detail service and authz)
Architecture rule: No client-side PDF libs; mirror detail JSON fields; add BATAL watermark for cancelled and OVERDUE badge when due_date < today && balance > 0
[RESTATE: PDF must be server-side Dompdf only, read-only, and must respect active template toggles exactly as detail]

## DELIVERABLE
Given authorized user, When GET /invoices/{id}/pdf, Then 200 application/pdf with filename INV-YYYYMMDD-{id}.pdf
Given cross-outlet user, When GET pdf, Then 403; unauthenticated 401; missing 404
Given active template toggles, When GET pdf, Then NPWP/outlet phone visibility follows toggles and colors/logo/footer applied
Given cancelled invoice, When GET pdf, Then BATAL watermark present
Given overdue invoice, When GET pdf, Then OVERDUE badge present
Given normal invoice, When GET pdf, Then generation <5s
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - barryvdh/laravel-dompdf ^2.0 via composer
  - Blade view consuming active InvoiceTemplate
  - Correct headers and filename pattern
  - Watermark and badge conditions
  - Performance <5s for normal invoice

Must-not-have:
  - Client-side PDF generation
  - Multiple templates
  - Raw HTML editor

Open question risks:
  - none

Rollback note:
  - Removing route and composer dep restores prior state; migration for templates remains

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Dompdf text extraction in tests is flaky
Escalate when: Constraint violated

---

### Task 6: Dummy-mode parity for detail and PDF [depends: T4, T5]

## OBJECTIVE
Keep zero-network dummy mode returning the same JSON shape for detail and a static PDF blob.

Steps:
1. Write failing test for: dummy detail returns same shape as real detail
   Test file: `apps/api/tests/Feature/DummyInvoiceParityTest.php`
   Level: integration
   Test intent: Given dummy mode on (config/app.dummy_mode true) / When GET /invoices/{id} Then response JSON shape equals real mode shape (header, line items with product_name_snapshot, payments ordered newest, totals, badge) using seeded dummy invoice
   Exercise through: HTTP GET /invoices/{id} with dummy flag toggled
   Test doubles: Config fake for dummy_mode; do NOT mock InvoiceService beyond flag branching
   Expected RED: dummy returns list shape or missing snapshot/payments
2. Run test — verify FAIL: `php artisan test --filter=DummyInvoiceParityTest::test_dummy_detail_shape`
3. Implement dummy branching in InvoiceController@show (or DummyModeHandler trait) returning pre-seeded dummy invoice JSON when flag on, and extend dummy factories/seeder to include product_name_snapshot and payment rows with varied statuses; verify PASS; refactor while green; commit

4. Write failing test for: dummy PDF returns static blob with correct headers
   Test file: `apps/api/tests/Feature/DummyInvoiceParityTest.php` (second case)
   Level: integration
   Test intent: Given dummy mode on / When GET /invoices/{id}/pdf Then response is application/pdf with filename INV-YYYYMMDD-{id}.pdf and bytes equal pre-generated static PDF (generated once during dummy seed)
   Exercise through: HTTP GET /invoices/{id}/pdf with dummy flag
   Test doubles: Config fake
   Expected RED: dummy PDF hits real Dompdf path or returns JSON
5. Run test — verify FAIL: `php artisan test --filter=DummyInvoiceParityTest::test_dummy_pdf_blob`
6. Implement dummy PDF path returning static blob stored during dummy seeding (or cached file) with same headers as real mode; verify PASS; refactor while green; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Dummy-mode parity, Architecture Constraints (zero-network dummy must return same JSON shape and static PDF blob)

## WHY THIS APPROACH
Complexity: standard
Justification: Requires config-driven branching and factory/seeder extensions without affecting real mode.

## SANDWICH CONTEXT
[CRITICAL: Dummy mode must be zero-network and must not affect real-mode responses when flag is off]
You are implementing dummy-mode parity for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/api/app/Http/Controllers/InvoiceController.php, apps/api/database/factories/InvoiceFactory.php, apps/api/database/factories/OrderItemFactory.php, apps/api/database/seeders/DummySeeder.php or equivalent
Available after: T4 (detail), T5 (PDF)
Architecture rule: Toggle via config/app.dummy_mode; deterministic dummy payload and static PDF bytes
[RESTATE: Dummy mode must be zero-network and must not affect real-mode responses when flag is off]

## DELIVERABLE
Given dummy mode on, When GET /invoices/{id}, Then JSON shape equals real mode including snapshot and payments
Given dummy mode on, When GET /invoices/{id}/pdf, Then static PDF blob with correct headers and filename
Given dummy mode off, When GET detail or pdf, Then real-mode behavior unchanged
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Config-driven branching
  - Dummy seeder includes product_name_snapshot and all payment statuses
  - Static PDF blob reused

Must-not-have:
  - Real Dompdf invocation when dummy on
  - Shape divergence between dummy and real

Open question risks:
  - none

Rollback note:
  - Removing branching restores real-only behavior

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Dummy flag location differs from config/app.dummy_mode
Escalate when: Constraint violated

---

### Task 8: Frontend admin template form [depends: T3]

## OBJECTIVE
Provide a structured admin form in Next.js (`/admin/invoice-template`) to view and update the corporate template (logo upload, company name, address, NPWP, primary color, footer, notes, signer, toggles).

Steps:
1. Write failing test for: admin template settings page renders current template and submits updates
   Test file: `apps/web/src/app/admin/invoice-template/__tests__/template-form.test.tsx`
   Level: integration
   Test intent: Given admin user with `invoice_template:read` and `invoice_template:edit` / When navigating to `/admin/invoice-template` / Then form fields load with current template values; When user edits fields and clicks Save / Then POST `/admin/invoice-template` is called with updated payload and success message appears
   Exercise through: Next.js page component rendering and form submission
   Test doubles: Mock fetch for GET and POST `/admin/invoice-template`
   Expected RED: page does not exist or form elements missing
2. Run test — verify FAIL: `npm test -- template-form.test.tsx --watchAll=false`
3. Implement page `apps/web/src/app/admin/invoice-template/page.tsx` rendering structured inputs for company details, color picker, text areas for footer/notes, signer info, and checkboxes for `show_npwp` and `show_outlet_phone`, plus logo file input with 2MB preview/validation; wire to API client methods `getAdminInvoiceTemplate()` and `updateAdminInvoiceTemplate(data)`; verify PASS; refactor while green; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Template admin UI requirement, Architecture Constraints (Next 16 + Tailwind + Zustand)

## WHY THIS APPROACH
Complexity: standard
Justification: Frontend form integration with file upload and validation for admin branding.

## SANDWICH CONTEXT
[CRITICAL: Only accessible to admin with invoice_template:edit; structured form only, no raw HTML editor]
You are implementing admin template UI for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/web/src/app/admin/invoice-template/page.tsx, apps/web/src/app/invoices/api.ts (admin methods)
Available after: T3 (backend admin template API)
Architecture rule: Follow existing Next.js admin page patterns and Tailwind styling; zero-network dummy parity if applicable
[RESTATE: Only accessible to admin with invoice_template:edit; structured form only, no raw HTML editor]

## DELIVERABLE
Given admin user on `/admin/invoice-template`, When page loads, Then current template fields render in structured form
Given valid edits and click Save, When submitted, Then POST updates template and success feedback displays
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Form fields for all template properties
  - Logo upload input with 2MB max check
  - Success / error handling on submit

Must-not-have:
  - Raw HTML or drag-and-drop editor

Open question risks:
  - none

Rollback note:
  - Removing page restores 404 for admin template route

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Admin routing convention differs from `/admin/`
Escalate when: Constraint violated

---

### Task 7: Frontend detail page and Export PDF button [depends: T4, T5]

## OBJECTIVE
Let authorized users click a row to see full detail and export the same detail as PDF, respecting RBAC.

Steps:
1. Write failing test for: detail page fetches and renders header, line items, payments, badge
   Test file: `apps/web/src/app/invoices/__tests__/detail.test.tsx`
   Level: integration
   Test intent: Given route /invoices/123 and API returns detail JSON with header, frozen line items, payments ordered newest, outlet info, overdue badge / When page mounts / Then header, line items table, payment table, and OVERDUE badge render; respects template colors/logo
   Exercise through: Next page component apps/web/src/app/invoices/[id]/page.tsx rendering
   Test doubles: Mock fetch for GET /invoices/{id} (msw/fetch mock); do NOT mock InvoiceDetail component under test
   Expected RED: page does not exist or renders empty
2. Run test — verify FAIL: `npm test -- detail.test.tsx --watchAll=false`
3. Implement page `apps/web/src/app/invoices/[id]/page.tsx` fetching via new api `getInvoiceDetail(id)`, component `apps/web/src/app/invoices/components/InvoiceDetail.tsx` rendering tables and badge, wiring to `apps/web/src/app/invoices/api.ts` adding getInvoiceDetail; verify PASS; refactor while green extracting reusable component; commit

4. Write failing test for: Export PDF button visible only to authorized and downloads with correct filename
   Test file: `apps/web/src/app/invoices/__tests__/export-pdf.test.tsx`
   Level: integration
   Test intent: Given authorized user (invoices:read) on detail page / When Export PDF button clicked / Then fetch GET /invoices/{id}/pdf as blob is invoked and download triggered with filename INV-YYYYMMDD-{id}.pdf; Given unauthorized dummy user / Then button hidden
   Exercise through: InvoiceDetail Export button click handler
   Test doubles: Mock fetch for pdf blob + URL.createObjectURL + anchor download; do NOT mock button itself
   Expected RED: button missing or downloads wrong file
5. Run test — verify FAIL: `npm test -- export-pdf.test.tsx --watchAll=false`
6. Implement Export PDF button in InvoiceDetail, api method downloadInvoicePdf(id) fetching blob and triggering download with correct filename pattern, hiding button when RBAC check fails; also make list page rows link to /invoices/[id]; verify PASS; refactor while green; commit

> Test **intent** only — never test source code. The implementer writes the test during the RED step, against the API that exists by then.

## REFERENCES LOADED
docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md — rule: Authorization + Content + PDF parity for frontend, Architecture Constraints (Next 16 + Tailwind + Zustand, dummy parity)

## WHY THIS APPROACH
Complexity: standard
Justification: Next routing, API client, component composition, RBAC-gated UI, and blob download.

## SANDWICH CONTEXT
[CRITICAL: Frontend must mirror backend RBAC and template exactly; no client-side PDF libs]
You are implementing frontend detail and export for Invoice Detail, PDF Export & Template Customization.
Spec: docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
Design decision: Option A — Server-side PDF (Laravel Dompdf) + Structured Form Template
Files in scope: apps/web/src/app/invoices/[id]/page.tsx, apps/web/src/app/invoices/components/InvoiceDetail.tsx, apps/web/src/app/invoices/api.ts, apps/web/src/app/invoices/page.tsx (link from row)
Available after: T4 (detail API), T5 (PDF API)
Architecture rule: Use Tailwind + existing Zustand patterns; zero-network dummy parity via api layer; hide Export button when not authorized
[RESTATE: Frontend must mirror backend RBAC and template exactly; no client-side PDF libs]

## DELIVERABLE
Given authorized user on /invoices/123, When page loads, Then header, frozen line items, payments, outlet info, and badge render
Given Export PDF clicked, When authorized, Then blob downloaded as INV-YYYYMMDD-{id}.pdf
Given unauthorized, When on detail, Then Export button hidden and detail fetch respects 403
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Click from list row navigates to /invoices/[id]
  - Detail fetch via getInvoiceDetail
  - Blob download via downloadInvoicePdf with correct filename
  - RBAC-gated button visibility

Must-not-have:
  - Client-side PDF generation
  - Raw HTML editor

Open question risks:
  - none

Rollback note:
  - Removing page and api methods restores prior list-only behavior

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified
Uncertain when: Frontend RBAC check source differs from backend
Escalate when: Constraint violated

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | invoice_templates table + model + seeder + RBAC | prereq | lightweight | table + default row + RBAC seed |
| T2 | product_name_snapshot column | prereq | lightweight | snapshot column exists and populates at order creation |
| T3 | Admin template CRUD API + validation | T1 | standard | admin read/update, finance read, logo validation |
| T4 | Detail endpoint authz + content | T1, T2 | standard | 200/403/401/404 and snapshot/payments/totals/badge |
| T5 | PDF endpoint + Blade + performance | T4 | deep | template parity, watermark, filename, <5s |
| T6 | Dummy-mode parity | T4, T5 | standard | dummy JSON shape + static PDF blob |
| T7 | Frontend detail + Export PDF | T4, T5 | standard | renders detail and downloads INV-*.pdf |
| T8 | Frontend admin template form | T3 | standard | structured form for admin template editing |

