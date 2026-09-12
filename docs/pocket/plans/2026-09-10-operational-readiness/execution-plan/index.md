# Operational Readiness Slice 1 — Execution Index

**Date:** 2026-09-10
**Spec:** `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
**Source Plan:** ../execution-plan.md
**source-sha256:** 5109c96d498cc194fa41c6be2ea352dedb70025d35a8401f11ea63134adf03fc
**Total Tasks:** 10
**Total Phases:** 3

---

## Execution Flow

```
T1,T8(PARALLEL)→T2→T3→T4,T5(PARALLEL)→T6→T7→T9→T10
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Create operational data foundation and shared domain records (T1, T8, T2)
- **Phase 2:** [phase-2.md](phase-2.md) — Implement invoice lifecycle and cancellation (T3, T4, T5)
- **Phase 3:** [phase-3.md](phase-3.md) — Implement idempotent WhatsApp invoice reminders and scheduler (T6, T7, T9, T10)

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Create operational data foundation and shared domain records | Phase 1 | [T1-create-operational-data-foundation-and-shared-domain-records.md](tasks/T1-create-operational-data-foundation-and-shared-domain-records.md) | [prereq] |
| T8 | Enforce delivery proof at the API boundary | Phase 1 | [T8-enforce-delivery-proof-at-the-api-boundary.md](tasks/T8-enforce-delivery-proof-at-the-api-boundary.md) | [prereq] |
| T2 | Add finance role, request-time authorization, and payment terms | Phase 1 | [T2-add-finance-role-request-time-authorization-and-payment-terms.md](tasks/T2-add-finance-role-request-time-authorization-and-payment-terms.md) | [depends: T1] |
| T3 | Implement invoice lifecycle and cancellation | Phase 2 | [T3-implement-invoice-lifecycle-and-cancellation.md](tasks/T3-implement-invoice-lifecycle-and-cancellation.md) | [depends: T1, T2] |
| T4 | Complete payment lifecycle and bounded payment history | Phase 2 | [T4-complete-payment-lifecycle-and-bounded-payment-history.md](tasks/T4-complete-payment-lifecycle-and-bounded-payment-history.md) | [depends: T3] |
| T5 | Add idempotent legacy invoice backfill | Phase 2 | [T5-add-idempotent-legacy-invoice-backfill.md](tasks/T5-add-idempotent-legacy-invoice-backfill.md) | [depends: T3] |
| T6 | Implement idempotent WhatsApp invoice reminders and scheduler | Phase 3 | [T6-implement-idempotent-whatsapp-invoice-reminders-and-scheduler.md](tasks/T6-implement-idempotent-whatsapp-invoice-reminders-and-scheduler.md) | [depends: T4] [test-risk] |
| T7 | Add finance invoice/payment metrics API | Phase 3 | [T7-add-finance-invoice-payment-metrics-api.md](tasks/T7-add-finance-invoice-payment-metrics-api.md) | [depends: T4, T6] |
| T9 | Add finance invoice/payment/metrics web surfaces | Phase 3 | [T9-add-finance-invoice-payment-metrics-web-surfaces.md](tasks/T9-add-finance-invoice-payment-metrics-web-surfaces.md) | [depends: T2, T3, T4, T7, T8] |
| T10 | Verify the complete operational readiness slice end to end | Phase 3 | [T10-verify-the-complete-operational-readiness-slice-end-to-end.md](tasks/T10-verify-the-complete-operational-readiness-slice-end-to-end.md) | [depends: T5, T6, T7, T8, T9] [test-risk] |
