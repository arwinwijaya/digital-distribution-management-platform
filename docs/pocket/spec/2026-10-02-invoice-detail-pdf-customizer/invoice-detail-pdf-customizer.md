# Invoice Detail, PDF Export & Template Customization

**Date:** 2026-10-02
**Status:** draft
**Author:** pocket-grinding session
**Spec path:** docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md

---

## Summary
Authorized users (outlet, finance, admin) can click an invoice row to view full details and export the invoice as a PDF. Admins can customize a single corporate-style invoice template via a structured form (logo, company info, colors, footer, toggles). The PDF generation, detail view, and export must respect RBAC, display the same fields according to the active template, and honour a dynamic overdue badge while preserving stored totals.

---

## Context
- **Current state:** `GET /invoices` lists invoices with RBAC `invoices:read`. The `Invoice` model stores header fields only; related `Order`, `OrderItem`, `Payment`, and `Outlet` models provide the data needed for a detailed view. No detail endpoint, PDF generation, or template management exists. No PDF library is present in `composer.json`.
- **Problem / Motivation:** Users cannot see line‑item breakdown, payment history, or export a printable invoice. Admins have no way to brand the invoice for corporate consistency.
- **Related areas:** `InvoiceController`, `InvoiceService`, `Order`/`OrderItem`/`Payment` models, frontend `apps/web/src/app/invoices/*`, dummy mode parity.

---

## Scope
### In‑Scope
- Click an invoice row → detail page (`/invoices/[id]`).
- Export the same detail as a PDF (`/invoices/{id}/pdf`).
- Admin structured‑form UI to edit a single corporate template (logo, company name/address/NPWP, primary color, footer text, notes, signer, toggles for NPWP & outlet phone).
- Seed a default corporate template (PT Digital Distribusi Nusantara, Jakarta address, Navy/Slate palette).
- RBAC enforcement identical to the list endpoint.
- Dummy‑mode parity for detail and PDF (zero‑network when dummy is on).

### Out‑of‑Scope
- Automatic email/WhatsApp sharing of PDFs.
- Payment processing or invoice issuance/cancellation changes.
- Multiple template catalogs, drag‑and‑drop or raw HTML editors.
- Company‑profile management beyond the fields needed for the invoice.

---

## Architecture Constraints
- Backend: Laravel 11, PHP 8.3, PostgreSQL.
- Frontend: Next 16, React 18, TypeScript, Tailwind, Zustand.
- Must not modify existing invoice issuance or cancellation logic.
- Must preserve existing `invoices:read` RBAC guard.
- Must add a **read‑only** PDF endpoint (`GET /invoices/{id}/pdf`).
- Must introduce a new `invoice_templates` table (single active row) and `company_settings` JSON column (seeded). Migration must be backward compatible.
- Must add `barryvdh/laravel-dompdf` (or equivalent) as a server‑side PDF renderer; no client‑side PDF libs.
- Dummy mode must return the same JSON shape for detail and a static PDF blob (generated once during dummy seed).

---

## Dependencies
### Existing (to leverage)
- `laravel/framework` – request handling, RBAC middleware.
- `spatie/laravel-permission` – role/permission checks.
- `barryvdh/laravel-dompdf` – will be added for PDF rendering.

### New (proposed)
- `barryvdh/laravel-dompdf` ^2.0 – server‑side HTML→PDF.
- Migration for `invoice_templates` (id, name, logo_path, company_name, address, npwp, primary_color, footer_text, notes, signer_name, signer_title, show_npwp, show_outlet_phone, created_at, updated_at).
- Seed data for the default corporate template.

---

## Stories + Scenarios
### Story 1 – View Detail
> As an authorized user (outlet, finance, admin) I want to click an invoice row to see full details so that I know what was billed and what remains.

**Rule 1 – Authorization**
- Example A: Outlet user with `outlet_id=5` opens its own invoice `INV‑20260101‑123`. → `200 OK` with full detail.
- Example B: Same outlet tries `INV‑20260101‑999` belonging to outlet 9. → `403 Forbidden`.
- Example C: Unauthenticated request. → `401 Unauthorized`.
- Example D: Invoice ID does not exist. → `404 Not Found`.

```gherkin
Scenario: Outlet views its own invoice detail
  Given an authenticated outlet user (outlet_id=5) with `invoices:read`
  When GET `/invoices/123`
  Then response status is 200
  And response body contains header, frozen line items, payment history, outlet info

Scenario: Outlet tries another outlet's invoice
  Given same user
  When GET `/invoices/999`
  Then response status is 403

Scenario: Unauthenticated request
  Given no token
  When GET `/invoices/123`
  Then response status is 401

Scenario: Invoice not found
  Given valid token
  When GET `/invoices/555555`
  Then response status is 404
```

**Rule 2 – Content**
- Frozen product name is taken from the `order_items.product_name_snapshot` column (snapshot at invoice creation).
- Payment history includes **all** payment rows (completed, pending, failed) ordered by `created_at DESC`.
- Totals (`total_amount`, `paid_amount`, `balance_amount`) are taken from the stored invoice snapshot; they are **not** recomputed on every request.
- Cancelled invoices show stored totals and status; overdue invoices show a dynamic “OVERDUE” badge when `due_date < today` **and** `balance_amount > 0`.

```gherkin
Scenario: Detail shows frozen product name even after product rename
  Given an invoice created when product name was "Sabun A"
  And the product later renamed to "Sabun B"
  When the outlet views the invoice detail
  Then the line item still shows "Sabun A"

Scenario: Payment history shows all statuses
  Given an invoice with three payments: completed, pending, failed
  When viewing detail
  Then all three rows appear ordered newest first
```

### Story 2 – Export PDF
> As an authorized user I want to export the invoice as a PDF so that I can share or archive it.

**Rule 1 – Parity & Authorization**
- PDF content mirrors the detail view **and** respects the active template toggles (e.g., NPWP or outlet phone can be hidden).
- Same RBAC responses (`200`, `403`, `401`, `404`) as the JSON detail endpoint.
- Cancelled invoices receive a watermark “BATAL”. Overdue invoices receive a badge “OVERDUE”.

```gherkin
Scenario: Finance exports a normal invoice PDF
  Given a finance user with `invoices:read`
  When GET `/invoices/123/pdf`
  Then response status is 200
  And `Content-Type` is `application/pdf`
  And filename is `INV-20231002-123.pdf`
  And the PDF layout matches the active template

Scenario: Outlet tries another outlet's PDF
  Given outlet user (outlet_id=5)
  When GET `/invoices/999/pdf`
  Then response status is 403
```

**Rule 2 – Performance**
- PDF generation must complete within **5 seconds** for a normal invoice (< 100 line items, < 10 payments).

---

## Acceptance Criteria
```
Rule: Authorization
  ✓ Given outlet user, When accessing own invoice, Then 200
  ✓ Given outlet user, When accessing another outlet, Then 403
  ✓ Given unauthenticated, When accessing any invoice, Then 401
  ✓ Given non‑existent id, When accessing, Then 404

Rule: Content
  ✓ Frozen product name comes from snapshot column
  ✓ Payment history shows all statuses ordered newest first
  ✓ Totals use stored snapshot values
  ✓ Cancelled shows BATAL watermark; overdue shows badge if due_date < today && balance > 0

Rule: PDF parity
  ✓ PDF mirrors JSON detail and respects template toggles
  ✓ PDF filename follows pattern `INV-YYYYMMDD-{id}.pdf`
  ✓ PDF generation ≤ 5 s for normal invoice
```
```

## Design Decision
**Chosen option:** **Option A – Server‑side PDF (Laravel Dompdf) + Structured Form Template**
- Rationale: Guarantees legal‑grade PDF fidelity, centralises branding control, avoids client‑side inconsistencies, and matches the “corporate default” requirement.
- Rejected Options B (client‑side jsPDF) and C (headless Chrome) due to quality, performance, and infrastructure overhead.

### Trade‑offs
+ Single source of truth on the server; PDF generation is deterministic.
- Adds a new dependency (`barryvdh/laravel-dompdf`) and migration for template tables.

## Open Questions / Assumptions (resolved)
| Question | Resolution |
|----------|------------|
| Frozen product name source | Snapshot column in `order_items` (recommended). |
| Payment history display | Show all payment rows, ordered newest, using stored invoice totals. |
| Cancelled / overdue handling | Show stored totals; add dynamic overdue badge; BATAL watermark for cancelled. |
| PDF vs detail parity | PDF must honour the active template toggles exactly as the detail view. |
| ID disclosure | Return `403` for cross‑outlet access (explicit disclosure as per existing RBAC). |
| Template access contract | Only users with role `admin` (and permission `invoice_template:edit`) can read, preview, edit, and publish the template. |
| Logo handling | Logo uploaded as a file (max 2 MB, JPG/PNG/SVG). On validation failure the prior template remains unchanged. |
| PDF filename identifier | Use the database `invoice.id` (primary key) in the filename. |

## Implementation Notes
- Add routes `GET /invoices/{id}` and `GET /invoices/{id}/pdf` with `rbac:invoices:read` middleware.
- Create `InvoiceTemplate` model + migration, seed default corporate row.
- Build a Blade view `resources/views/invoices/pdf.blade.php` that consumes the active template and invoice data.
- In dummy mode, extend `dummy.invoices` factory to include frozen product name snapshot and payment rows; `loadInvoiceDetail` and `downloadPdf` should return pre‑generated static PDF bytes.
- Update frontend: new page `apps/web/src/app/invoices/[id]/page.tsx` with detail UI and “Export PDF” button.
- Validate logo upload size & mime type server‑side.
- Add feature flag `invoice_pdf` to allow progressive rollout.

## Rollback Plan
1. Deploy migration with `up` only; keep `down` script ready.
2. Feature flag `invoice_pdf` defaults `off` – toggling `on` rolls out the endpoints.
3. If a critical bug appears, turn the flag `off` and rollback the migration (drop `invoice_templates`).
4. Data migrations are additive; no destructive changes to existing invoices.
