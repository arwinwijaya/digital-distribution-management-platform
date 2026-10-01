# Siklus Pemesanan End-to-End (Hybrid API + Thin UI) — Execution Index

**Date:** 2026-09-30
**Spec:** docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
**Source Plan:** ../execution-plan.md
**source-sha256:** de359ba8ebd51aba771d4b6c9ddda77e6d3526894d758f80ccc378728ee07d6b
**Total Tasks:** 8
**Total Phases:** 2

---

## Execution Flow

```
T1,T2,T3,T5(PARALLEL)→T4,T7(PARALLEL)→T6→T8
```

---

## Phase Summary

- **Phase 1:** [phase-1.md](phase-1.md) — Scaffold Playwright config + install (T1, T2, T3, T5)
- **Phase 2:** [phase-2.md](phase-2.md) — JSONL logger shared helper (T4, T7, T6, T8)

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Scaffold Playwright config + install | Phase 1 | [T1-scaffold-playwright-config-install.md](tasks/T1-scaffold-playwright-config-install.md) | [prereq] [no-tdd — structural task] |
| T2 | Seed isolation script | Phase 1 | [T2-seed-isolation-script.md](tasks/T2-seed-isolation-script.md) | [prereq] [no-tdd — structural task] |
| T3 | Add data-testid contract to critical pages | Phase 1 | [T3-add-data-testid-contract-to-critical-pages.md](tasks/T3-add-data-testid-contract-to-critical-pages.md) | [prereq] |
| T5 | API negative checks — extend order-flow.test.js | Phase 1 | [T5-api-negative-checks-extend-order-flow-test-js.md](tasks/T5-api-negative-checks-extend-order-flow-test-js.md) | [prereq] |
| T4 | JSONL logger shared helper | Phase 2 | [T4-jsonl-logger-shared-helper.md](tasks/T4-jsonl-logger-shared-helper.md) | [depends: T1] [test-risk] |
| T7 | Artifact retention + local purge | Phase 2 | [T7-artifact-retention-local-purge.md](tasks/T7-artifact-retention-local-purge.md) | [depends: T1] [no-tdd — structural task] |
| T6 | Playwright thin-UI browser flow | Phase 2 | [T6-playwright-thin-ui-browser-flow.md](tasks/T6-playwright-thin-ui-browser-flow.md) | [depends: T3, T4] |
| T8 | CI workflow wiring for E2E | Phase 2 | [T8-ci-workflow-wiring-for-e2e.md](tasks/T8-ci-workflow-wiring-for-e2e.md) | [depends: T5, T6, T7] |
