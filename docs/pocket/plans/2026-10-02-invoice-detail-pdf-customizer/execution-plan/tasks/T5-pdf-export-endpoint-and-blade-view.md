# Task T5 — PDF export endpoint and Blade view

**Phase:** 2
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
