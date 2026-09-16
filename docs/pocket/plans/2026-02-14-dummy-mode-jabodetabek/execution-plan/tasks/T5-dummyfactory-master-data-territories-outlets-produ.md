# Task T5 — DummyFactory — master data (territories/outlets/products/suppliers)

**Phase:** 1
**Depends:** T1] [parallel: T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


## OBJECTIVE
Build the deterministic master-data factory: 5 JABODETABEK territories (from T1 seed), ~48 outlets (~9–10 per territory with names/addresses/coords inside territory bbox), ~30 products (SKU, name, category, price), ~8 suppliers. Pure functions taking `(rng, window)`; no store import. This task produces the leaf module; T6's `buildFullDummy(today?)` (in `apps/web/src/dummy/index.ts`) will compose it, so keep the types file-agnostic.

Files:
- Create: `apps/web/src/dummy/factory.ts`
- Test: `apps/web/src/dummy/factory.test.ts`

Steps:
1. Write failing test for: counts, bbox containment, determinism
   Test file: `apps/web/src/dummy/factory.test.ts`
   Level: unit

   Test intent:
   Given `DUMMY_SEED` + a fixed `today` (e.g. 2026-02-14) via T1 utils
   When `buildMasterData()` runs twice
   Then:
   - outlet count is 44..52 (≈9–10 per territory × 5)
   - every outlet has territory in the 5 + lat/lon inside the JABODETABEK bbox + `city` one of the 5 names
   - product count is 28..32, each with unique SKU
   - supplier count is 7..9
   - both runs are deep-equal (determinism)
   - outlet id `dummy-001` is "Toko Bogor Indah" (canonical example from spec Story 2 R2)

   Exercise through:
   - `buildMasterData(rng, window)` from `apps/web/src/dummy/factory.ts`

   Test doubles:
   - mock/fake: none (inject fixed `today`; RNG from T1)
   - do NOT mock: factory, rng, seed

   Expected RED:
   - `apps/web/src/dummy/factory.ts` does not exist → import error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/factory.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/factory'`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/factory.ts` — `buildMasterData()` composing T1 seed lists; per-outlet jittered coords around territory anchors (still inside bbox); stable string ids `dummy-001...`; types exported (`DummyOutlet`, `DummyProduct`, `DummySupplier`, `DummyTerritory`, `MasterData`).

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/factory.test.ts --runInBand`
   Expected: PASS

5. Refactor while green (bounded) + re-run (must stay PASS).

6. Commit:
   `git add apps/web/src/dummy/factory.ts apps/web/src/dummy/factory.test.ts`
   `git commit -m "feat(dummy): add deterministic master-data factory (outlets, products, suppliers)"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Story 2 R2 (deterministic relational; canonical `dummy-001` "Toko Bogor Indah"); geo bbox; ~48 outlets / ~30 products / ~8 suppliers (Phase 3 locked volumes).
Preflight: T1 (`rng.ts`, `dates.ts`, `seed.ts`) is the only available-after input.

## WHY THIS APPROACH
Justification: Master data is the referential anchor for every transaction and aggregate — transactions (T6) cannot exist without stable outlet/product/supplier ids. Pure functions keep it unit-testable without the store.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Factory must be pure — no store imports, no fetch, no Date.now() inside (today is injected)]
You are implementing the master-data factory for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/src/dummy/factory.ts`, `apps/web/src/dummy/factory.test.ts` — no other files
Available after: T1
Architecture rule: Import ONLY from `apps/web/src/dummy/*` (T1 utils). No backend, no new deps.
[RESTATE: Factory must be pure — no store imports, no fetch, no Date.now() inside (today is injected)]

## DELIVERABLE
Given fixed seed + fixed today, When buildMasterData() runs twice, Then deep-equal results with 44..52 outlets AND 28..32 unique-SKU products AND 7..9 suppliers
Given outlets built, When inspected, Then every outlet inside JABODETABEK bbox AND dummy-001 is Toko Bogor Indah

All tests PASS. Commit exists with message matching `feat(dummy): add deterministic master-data factory (outlets, products, suppliers)`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Canonical `dummy-001` = "Toko Bogor Indah" (spec example other tasks rely on)
  - All coords inside spec bbox; all `city` values one of the 5 names
  - Unique SKUs; deterministic across runs
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Store/fetch/network imports
  - Date.now() inside the factory (inject `today`)
  - Backend changes; new dependencies

Open question risks:
  - none

Rollback note:
  - Delete `factory.ts` + test; T6 depends on it (do not merge T6 without T5).

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task imports outside `apps/web/src/dummy/*` or touches out-of-scope files
