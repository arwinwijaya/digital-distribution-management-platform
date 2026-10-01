# Task T8 — Frontend admin template form

**Phase:** 2
**Depends:** T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
