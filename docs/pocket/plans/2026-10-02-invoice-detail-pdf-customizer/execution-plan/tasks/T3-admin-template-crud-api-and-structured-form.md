# Task T3 — Admin template CRUD API and structured form

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
