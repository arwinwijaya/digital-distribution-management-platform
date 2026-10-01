# Task T1 — Create invoice_templates table, model, seeder, and RBAC entries

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
