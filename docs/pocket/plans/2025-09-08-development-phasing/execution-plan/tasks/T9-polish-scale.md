# Task T9 — Polish & Scale

**Phase:** 2
**Depends:** T6, T7, T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 9: Polish & Scale [depends: T6, T7, T8]

## OBJECTIVE
Optimize performance, add advanced features, and prepare for production scale.

Files:
- Modify: `apps/web/` (performance optimization)
- Modify: `apps/api/` (performance optimization)
- Create: `apps/web/app/marketplace/`
- Test: `apps/api/tests/Performance/`

Steps:
1. Write failing test for: Performance benchmark
   Test file: `apps/api/tests/Performance/LoadTest.php`
   Level: performance
   Test intent: Given100 concurrent users, When accessing API, Then response time <2s
   Exercise through: Load testing tools
   Test doubles: mock external services
   Expected RED: Performance benchmarks not defined

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter Performance`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Http/Controllers/` (optimize queries)
   Implement: Database query optimization, caching

4. Run test — verify PASS: `cd apps/api && php artisan test --filter Performance`

5. Refactor while green (bounded):
   - Optimize database indexes
   - Re-run test: `cd apps/api && php artisan test --filter Performance`

6. Commit:
   `git add . && git commit -m "perf(scale): optimize for production scale"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase8 Polish & Scale
Phase8 deliverables: Performance optimization, advanced BI, marketplace
Success criteria:500+ outlets,100+ active, sustainable revenue

## WHY THIS APPROACH
Complexity: standard
Justification: Production readiness

## SANDWICH CONTEXT
[CRITICAL: Must handle500+ outlets]
You are implementing Polish & Scale for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: All project files (optimization)
Available after: T6, T7, T8 (Sales, WhatsApp, AI)
Architecture rule: Performance optimization, caching, database indexing
[RESTATE: Must handle500+ outlets]

## DELIVERABLE
Given100 concurrent users, When accessing API, Then response time <2s
Given500+ outlets, When using platform, Then performance remains acceptable
Given marketplace exists, When suppliers join, Then multi-supplier support works

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Performance benchmarks met
  - Scalability verified
  - Marketplace functionality

Must-not-have:
  - Over-optimization (premature optimization)

Open question risks:
  - None (spec is clear)

Rollback note:
  - Can rollback optimizations if issues arise

## STOP CONDITIONS
Done when: Performance benchmarks met, marketplace functional
Uncertain when: N/A
Escalate when: Cannot meet performance requirements
