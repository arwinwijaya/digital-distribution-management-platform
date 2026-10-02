# Closeout — 2026-10-02-invoice-detail-pdf-customizer

- **Plan:** docs/pocket/plans/2026-10-02-invoice-detail-pdf-customizer
- **Type:** phased
- **Started:** 2026-10-01  ·  **Closed:** 2026-10-02
- **Baseline SHA:** a23fed24741522d4be85737b4e3542b120ca9923  ·  **Final SHA:** 2e4fd5d62f
- **Result:** CLOSED — all phases DONE, all reviewable tasks REVIEW_PASS

## Phases

### Phase 1 — execution-plan/phase-1.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T1 | Create invoice_templates table, model, seeder, and RBAC entries | 4ff1680ca9 | REVIEW_PASS |
| T2 | Add product_name_snapshot column to order_items | 1f638fdaa9 | REVIEW_PASS |
| T3 | Admin template CRUD API and structured form | 567849d377 | REVIEW_PASS |
| T4 | Invoice detail endpoint with authorization and content | 958e50c60e | REVIEW_PASS |

_SHA range: a23fed2474..64bde691f9_

### Phase 2 — execution-plan/phase-2.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T5 | PDF export endpoint and Blade view | 953efa8618 | REVIEW_PASS |
| T6 | Dummy-mode parity for detail and PDF | d4b43c78f6 | REVIEW_PASS |
| T8 | Frontend admin template form | 4dce467c29 | REVIEW_PASS |
| T7 | Frontend detail page and Export PDF button | 8d4419445d | REVIEW_PASS |

_SHA range: 64bde691f9..2e4fd5d62f_

## Carried Forward

Non-blocking observations from review — accepted at close, recorded for follow-up.

- **T1** (Minor): refactor-while-green fillable constant extraction not applied (inline array remains) — apps/api/app/Models/InvoiceTemplate.php
- **T2** (Minor): N+1 re-fetch in persistOrder loop; OrderItemFactory not updated for snapshot default — apps/api/app/Services/OrderCreationService.php, apps/api/database/factories/OrderItemFactory.php
- **T4** (Minor): duplicate alias keys in response (is_overdue/overdue, line_items/items, payments/payment_history); isOverdue redundant check; getDetail ~67 lines — apps/api/app/Services/InvoiceService.php
- **T5** (Minor): duplicated isOverdue logic in PdfGeneratorService.php:118; pdf.blade.php 364 lines > ~300 heuristic — apps/api/app/Services/PdfGeneratorService.php, apps/api/resources/views/invoices/pdf.blade.php
- **T6** (Minor): none (clean)
- **T7** (Minor): DUMMY_PDF_BYTES is a static literal rather than a seeded factory blob — acceptable per spec static blob, mirrors backend reuse; show_npwp toggle not rendered in detail view (only show_outlet_phone + primary_color visible) — apps/web/src/app/invoices/api.ts:354, apps/web/src/app/invoices/components/InvoiceDetail.tsx:65-164
- **T8** (Minor): hardcoded #0F172A duplicated at page.tsx:12; object URL preview lacks unmount cleanup at page.tsx:68 — apps/web/src/app/admin/invoice-template/page.tsx
- **T5** (Minor, phase-pass): Spec feature flag invoice_pdf for progressive rollout not implemented by any Phase 2 task; migration/route guard remains unconditional — docs/pocket/spec/.../invoice-detail-pdf-customizer.md: Implementation Notes
- **T7/T4/T6** (Minor, phase-pass): Frontend dummy detail builder diverges on payments.outlet_id (maps invoice.order_id instead of real outlet_id) — apps/web/src/app/invoices/api.ts:280

## Skipped Tasks

_None_

---

**All 8 tasks reviewed at their final SHAs. All verdicts REVIEW_PASS. Plan closed.**