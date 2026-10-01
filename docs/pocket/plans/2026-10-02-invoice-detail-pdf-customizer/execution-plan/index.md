# Invoice Detail, PDF Export & Template Customization — Execution Index

**Date:** 2026-10-02
**Spec:** docs/pocket/spec/2026-10-02-invoice-detail-pdf-customizer/invoice-detail-pdf-customizer.md
**Source Plan:** ../execution-plan.md
**source-sha256:** c70b35f845a6647115f04819989effae7e0e2aa685496f6a43c9f6a9cbea5742
**Total Tasks:** 8
**Total Phases:** 2

---

## Execution Flow

```
T1,T2(PARALLEL)→T3,T4(PARALLEL)→T5,T8(PARALLEL)→T6,T7(PARALLEL)
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Create invoice_templates table, model, seeder, and RBAC entries (T1, T2, T3, T4)
- **Phase 2:** [phase-2.md](phase-2.md) — PDF export endpoint and Blade view (T5, T8, T6, T7)

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Create invoice_templates table, model, seeder, and RBAC entries | Phase 1 | [T1-create-invoice-templates-table-model-seeder-and-rbac-entries.md](tasks/T1-create-invoice-templates-table-model-seeder-and-rbac-entries.md) | [prereq] |
| T2 | Add product_name_snapshot column to order_items | Phase 1 | [T2-add-product-name-snapshot-column-to-order-items.md](tasks/T2-add-product-name-snapshot-column-to-order-items.md) | [prereq] |
| T3 | Admin template CRUD API and structured form | Phase 1 | [T3-admin-template-crud-api-and-structured-form.md](tasks/T3-admin-template-crud-api-and-structured-form.md) | [depends: T1] |
| T4 | Invoice detail endpoint with authorization and content | Phase 1 | [T4-invoice-detail-endpoint-with-authorization-and-content.md](tasks/T4-invoice-detail-endpoint-with-authorization-and-content.md) | [depends: T1, T2] |
| T5 | PDF export endpoint and Blade view | Phase 2 | [T5-pdf-export-endpoint-and-blade-view.md](tasks/T5-pdf-export-endpoint-and-blade-view.md) | [depends: T4] |
| T8 | Frontend admin template form | Phase 2 | [T8-frontend-admin-template-form.md](tasks/T8-frontend-admin-template-form.md) | [depends: T3] |
| T6 | Dummy-mode parity for detail and PDF | Phase 2 | [T6-dummy-mode-parity-for-detail-and-pdf.md](tasks/T6-dummy-mode-parity-for-detail-and-pdf.md) | [depends: T4, T5] |
| T7 | Frontend detail page and Export PDF button | Phase 2 | [T7-frontend-detail-page-and-export-pdf-button.md](tasks/T7-frontend-detail-page-and-export-pdf-button.md) | [depends: T4, T5] |
