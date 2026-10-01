# Task T7 — Frontend detail page and Export PDF button

**Phase:** 2
**Depends:** T4, T5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

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
