# Outlet-First Login Appeal — Execution Index

**Date:** 2026-09-30
**Spec:** docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md
**Source Plan:** ../execution-plan.md
**source-sha256:** 65bb4910c270f479d6a06fe63bd9dff21426d10ff93b8cf4e2c5b37ededeabd3
**Total Tasks:** 2
**Total Phases:** 1

---

## Execution Flow

```
T1→T2
```

---

## Task Index

| Task ID | Name | Phase | Task File | Annotation |
|---|---|---|---|---|
| T1 | Extend LoginForm with backward-compatible ctaLabel prop | Phase 1 | [T1-extend-loginform-with-backward-compatible-ctalabel-prop.md](tasks/T1-extend-loginform-with-backward-compatible-ctalabel-prop.md) | [prereq] [test-risk] |
| T2 | Outlet-first hero + form-first layout on /login | Phase 1 | [T2-outlet-first-hero-form-first-layout-on-login.md](tasks/T2-outlet-first-hero-form-first-layout-on-login.md) | [depends: T1] [test-risk] |
