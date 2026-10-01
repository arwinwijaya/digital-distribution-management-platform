# Task T3 — Dummy parity — dashboardOutlet fixture

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Dummy parity — dashboardOutlet fixture [depends: T2]

## OBJECTIVE
Tambahkan fixture `dashboardOutlet` di `apps/web/src/dummy/aggregates.ts` (dan tipe di `apps/web/src/dummy/index.ts` jika diperlukan) yang memenuhi parity untuk story 1–5: counts per status (untuk ringkasan), orders terbaru, credit-limit snapshot, dan data favorit. Guard `withDummyRead` harus mengembalikan outlet dashboard tanpa network saat dummy mode ON, mengikuti pola `dashboardAdmin`/`dashboardFinance` di `apps/web/src/app/dashboard/api.ts`.

Steps:
1. Write failing test for: dashboardOutlet aggregates parity
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit
   Test intent: Given seed with 5 orders (New×3 Delivered×2), 2 products, credit_limit set / When `buildAggregates(seed)` / Then `aggregates.dashboardOutlet` exists, metrics counts match orders per status, recent list length matches, credit fields present and formatted, favorites top-k matches helper `topFavorites`
   Exercise through: `buildAggregates` / `buildDashboardOutlet` exported builder
   Test doubles: Use existing seed factory (no network)
   Expected RED: `dashboardOutlet` is undefined
2. Run test — verify FAIL: `npm --prefix apps/web test -- apps/web/src/dummy/aggregates.test.ts -t "dashboardOutlet"`
3. Implement: Define type `OutletDashboardData` in dummy, add `dashboardOutlet` to `Aggregates` interface, implement `buildDashboardOutlet(master, tx, window)` reusing helpers from T2 (import allowed after T2). Use `money()` for rupiah strings, reuse `safeNumber` pattern. Seed deterministic via existing RNG.
4. Run test — verify PASS, extend test to cover empty-state (zero orders → empty arrays, not zeros-as-error) and credit_limit null case (field null triggers hide branch).
5. Refactor while green (extract shared builder if needed), commit.

## REFERENCES LOADED
docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md — Story 1–5 parity, architecture constraint withDummyRead, file `apps/web/src/dummy/aggregates.ts` L225-230 + L1306, `apps/web/src/dummy/guards.ts`, `apps/web/src/app/dashboard/api.ts` dummy branch.

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single module change with clear precedent (dashboardAdmin/dashboardFinance); judgment limited to fixture shape that satisfies UI contracts.

## SANDWICH CONTEXT
[CRITICAL: Dummy outlet must satisfy withDummyRead — zero network when isDummy=true.]
You are implementing dummy fixture parity for outlet dashboard.
Spec: docs/pocket/spec/2026-10-01-outlet-dashboard-insight/outlet-dashboard.md
Design decision: Option A — v1 composes existing endpoints; dummy follows same shape so outlet UI is testable without backend.
Files in scope: apps/web/src/dummy/aggregates.ts, apps/web/src/dummy/index.ts, apps/web/src/dummy/aggregates.test.ts
Available after: T2 (helpers) — import helpers for aggregation; T1 not needed for dummy
Architecture rule: Follow existing aggregates pattern (money(), StatCard props), do not change analytics/finance fixtures.
[RESTATE: Dummy parity must enable same empty/error branches testable without backend.]

## DELIVERABLE
Given dummy mode ON with outlet seed, When loadDashboard for outlet is called, Then withDummyRead returns dashboardOutlet without fetch [Story 1–5 parity]
Given zero orders seed, When aggregates built, Then outlet sections expose empty arrays for UI empty branch [Story 1 empty]
Given credit_limit null seed, When aggregates built, Then credit_limit null is present [Story 4 hide]

## QUALITY BAR
Must-have:
  - withDummyRead path for outlet added in aggregates and wired in api.ts test (task 4 handles wiring; this task just builds fixture)
  - Parity counts: sum of per-status counts = total, recent 10 newest-first, credit fields Rupiah strings, favorites via helper
  - No network, no new menu key

Must-not-have:
  - Changing dashboardAdmin/dashboardFinance metrics
  - Cross-outlet data
  - New backend call in dummy

Open question risks:
  - Fixture shape assumed `{summary, recent, shopping, credit, favorites}` — wire-up in T4 will finalize; if shape wrong: T4 test will fail → NEEDS_CONTEXT

Rollback note:
  - Remove dashboardOutlet field; outlet dummy path falls back to network

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - Must-not behavior implemented → STOP

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, existing aggregates.test still green, no out-of-scope files modified
Uncertain when: helper import creates cycle (dummy imports dashboard helper) — keep helper in `outlet-helpers.ts` which imports nothing
Escalate when: dummy store toggle breaks existing guards.test
