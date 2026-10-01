# Invoice Detail, PDF Export & Template Customization — Create invoice_templates table, model, seeder, and RBAC entries (Phase 1 of 2)

**Date:** 2026-10-02
**Original plan:** ../execution-plan.md
**Prerequisite:** None (first phase)
**Contains tasks:** {T1, T2, T3, T4}
**Unlocks next:** Phase 2

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T1:** Create invoice_templates table, model, seeder, and RBAC entries [prereq] → [tasks/T1-create-invoice-templates-table-model-seeder-and-rbac-entries.md](tasks/T1-create-invoice-templates-table-model-seeder-and-rbac-entries.md)
- **T2:** Add product_name_snapshot column to order_items [prereq] → [tasks/T2-add-product-name-snapshot-column-to-order-items.md](tasks/T2-add-product-name-snapshot-column-to-order-items.md)
- **T3:** Admin template CRUD API and structured form [depends: T1] → [tasks/T3-admin-template-crud-api-and-structured-form.md](tasks/T3-admin-template-crud-api-and-structured-form.md)
- **T4:** Invoice detail endpoint with authorization and content [depends: T1, T2] → [tasks/T4-invoice-detail-endpoint-with-authorization-and-content.md](tasks/T4-invoice-detail-endpoint-with-authorization-and-content.md)

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- All tests pass
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT

Hand off to Phase 2 ONLY after this gate passes.
