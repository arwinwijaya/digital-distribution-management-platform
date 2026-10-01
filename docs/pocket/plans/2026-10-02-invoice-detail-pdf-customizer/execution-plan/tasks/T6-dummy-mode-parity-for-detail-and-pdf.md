# Task T6 — Dummy-mode parity for detail and PDF

**Phase:** 2
**Depends:** T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
