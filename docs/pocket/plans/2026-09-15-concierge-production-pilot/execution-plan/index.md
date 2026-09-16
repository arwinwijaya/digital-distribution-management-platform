# Concierge Production Pilot — Execution Index

**Date:** 2026-09-15
**Spec:** docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md
**Source Plan:** ../execution-plan.md
**source-sha256:** 67f5fc7d53273045a65045d21edbb51840fceb961ac08b05155bae2b3d2ae621
**Total Tasks:** 3
**Total Phases:** 1

---

## Execution Flow

```
T1→T2→T3
```

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Pilot Infrastructure — Partner Qualification & KPI Helpers | Phase 1 | [T1-pilot-infrastructure-partner-qualification-kpi-helpers.md](tasks/T1-pilot-infrastructure-partner-qualification-kpi-helpers.md) | [prereq] |
| T2 | Pilot Workflow Execution — Order-to-Payment Lifecycle with KPI Collection | Phase 1 | [T2-pilot-workflow-execution-order-to-payment-lifecycle-with-kpi-collection.md](tasks/T2-pilot-workflow-execution-order-to-payment-lifecycle-with-kpi-collection.md) | [depends: T1] |
| T3 | Pilot Evaluation — Decision Matrix & Phase 7 Evidence Documentation | Phase 1 | [T3-pilot-evaluation-decision-matrix-phase-7-evidence-documentation.md](tasks/T3-pilot-evaluation-decision-matrix-phase-7-evidence-documentation.md) | [depends: T2] |
