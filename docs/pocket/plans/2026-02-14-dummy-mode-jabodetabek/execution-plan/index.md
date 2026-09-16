# Dummy Mode JABODETABEK — Execution Index

**Date:** 2026-02-14
**Spec:** docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
**Source Plan:** ../execution-plan.md
**Total Tasks:** 12
**Total Phases:** 3

---

## Execution Flow

```
T1→T2,T5,T7(PARALLEL)→T3,T4,T6(PARALLEL)→T8,T9,T10,T11(PARALLEL)→T12
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Foundation + Data (T1, T2, T3, T4, T5, T6, T7)
- **Phase 2:** [phase-2.md](phase-2.md) — Interception (T8, T9, T10, T11)
- **Phase 3:** [phase-3.md](phase-3.md) — Verification (T12)

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Dummy module scaffold + seeded RNG + date utils + JABODETABEK seed constants | Phase 1 | [T1-dummy-module-scaffold-seeded-rng-date-utils-jabode.md](tasks/T1-dummy-module-scaffold-seeded-rng-date-utils-jabode.md) | [prereq] |
| T2 | Zustand dummy store (isDummy, entities, role, toggle, localStorage persist) | Phase 1 | [T2-zustand-dummy-store-isdummy-entities-role-toggle-l.md](tasks/T2-zustand-dummy-store-isdummy-entities-role-toggle-l.md) | [depends: T1] |
| T3 | Role persistence at login (ddp_role) + Sidebar offline role read | Phase 1 | [T3-role-persistence-at-login-ddp-role-sidebar-offline.md](tasks/T3-role-persistence-at-login-ddp-role-sidebar-offline.md) | [depends: T2] |
| T4 | Topbar toggle UI | Phase 1 | [T4-topbar-toggle-ui.md](tasks/T4-topbar-toggle-ui.md) | [depends: T2] |
| T5 | DummyFactory — master data (territories/outlets/products/suppliers) | Phase 1 | [T5-dummyfactory-master-data-territories-outlets-produ.md](tasks/T5-dummyfactory-master-data-territories-outlets-produ.md) | [depends: T1] [parallel: T2] |
| T6 | DummyFactory — transactions + analytics/DI/AI aggregates | Phase 1 | [T6-dummyfactory-transactions-analytics-di-ai-aggregat.md](tasks/T6-dummyfactory-transactions-analytics-di-ai-aggregat.md) | [depends: T5] |
| T7 | Read-guard helper + commit-guard helper | Phase 1 | [T7-read-guard-helper-commit-guard-helper.md](tasks/T7-read-guard-helper-commit-guard-helper.md) | [depends: T1] [parallel: T2] |
| T8 | Guard data-intelligence-api + operations-api | Phase 2 | [T8-guard-data-intelligence-api-operations-api.md](tasks/T8-guard-data-intelligence-api-operations-api.md) | [depends: T6, T7] |
| T9 | Guard admin/* + sales/* API modules | Phase 2 | [T9-guard-admin-sales-api-modules.md](tasks/T9-guard-admin-sales-api-modules.md) | [depends: T6, T7] |
| T10 | Consolidate and guard inline-fetch pages | Phase 2 | [T10-consolidate-and-guard-inline-fetch-pages.md](tasks/T10-consolidate-and-guard-inline-fetch-pages.md) | [depends: T6, T7] |
| T11 | Fake mutation mutators + write-path guards | Phase 2 | [T11-fake-mutation-mutators-write-path-guards.md](tasks/T11-fake-mutation-mutators-write-path-guards.md) | [depends: T6, T7] |
| T12 | Cross-unit Dummy Mode integration verification | Phase 3 | [T12-cross-unit-dummy-mode-integration-verification.md](tasks/T12-cross-unit-dummy-mode-integration-verification.md) | [depends: T6, T7, T8, T9, T10, T11] |
