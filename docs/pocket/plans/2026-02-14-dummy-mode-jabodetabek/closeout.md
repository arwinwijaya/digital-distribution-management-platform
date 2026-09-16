# Closeout — 2026-02-14-dummy-mode-jabodetabek

- **Plan:** docs/pocket/plans/2026-02-14-dummy-mode-jabodetabek
- **Type:** phased
- **Started:** 2026-09-16  ·  **Closed:** 2026-09-16
- **Baseline SHA:** 9eadea865939c64e1ac61c314678fedd7a3ba1a1  ·  **Final SHA:** bc951fee8835f26c9aabac0989e5e6d27deca1b6
- **Result:** CLOSED — all phases DONE, all reviewable tasks REVIEW_PASS

## Phases

### Phase 1 — execution-plan/phase-1.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T1 | Dummy module scaffold + seeded RNG + date utils + JABODETABEK seed constants | 37b3d73c257d0018cf5f297c7229179e3a503069 | REVIEW_PASS |
| T2 | Zustand dummy store (isDummy, entities, role, toggle, localStorage persist) | 2796b2418211ba8978d196de6e35b8028346af34 | REVIEW_PASS |
| T3 | Role persistence at login (ddp_role) + Sidebar offline role read | cefa8c1ebb4ea622c99e34c8ef162a67fe51d11b | REVIEW_PASS |
| T4 | Topbar toggle UI | f20a0406024c95709d21fed0e2d56895e57ee7fe | REVIEW_PASS |
| T5 | DummyFactory — master data (territories/outlets/products/suppliers) | 29245524f613289e30d013462a3b109127081b3d | REVIEW_PASS |
| T6 | DummyFactory — transactions + analytics/DI/AI aggregates | 93aee807cadd90ea6028aaa136ed37123341ed3f | REVIEW_PASS |
| T7 | Read-guard helper + commit-guard helper | 3ead2cb259c9b6b429d69ad036d43e6cdf7e7824 | REVIEW_PASS |

_SHA range: 9eadea865939c64e1ac61c314678fedd7a3ba1a1..3ead2cb259c9b6b429d69ad036d43e6cdf7e7824_

### Phase 2 — execution-plan/phase-2.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T8 | Guard data-intelligence-api + operations-api | fa3ddfe926983c7123bcbd6e649f1b48e29a7ed7 | REVIEW_PASS |
| T9 | Guard admin/* + sales/* API modules | 7cde36776f35f37dd7bfeacfc1b9b3bb79091f0c | REVIEW_PASS |
| T10 | Consolidate and guard inline-fetch pages | 375d652ef807e95fc8824e7b9aca1d6453ef5b7f | REVIEW_PASS |
| T11 | Fake mutation mutators + write-path guards | 41dc5333227f44069592f6b58dbc1cf84c42cde3 | REVIEW_PASS |

_SHA range: 3ead2cb259c9b6b429d69ad036d43e6cdf7e7824..41dc5333227f44069592f6b58dbc1cf84c42cde3_

### Phase 3 — execution-plan/phase-3.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T12 | Cross-unit Dummy Mode integration verification | bc951fee8835f26c9aabac0989e5e6d27deca1b6 | REVIEW_PASS |

_SHA range: 41dc5333227f44069592f6b58dbc1cf84c42cde3..bc951fee8835f26c9aabac0989e5e6d27deca1b6_

## Carried Forward

Non-blocking observations from review — accepted at close, recorded for follow-up.

- **T2** (Minor): DummyEntities is declared as `Record<string, unknown>` (a maximally loose placeholder). Downstream Phase B/C guards reading dummyEntities.ou — apps/web/src/dummy/store.ts:15
- **T2** (Minor): localStorage.clear() is invoked twice in beforeEach with reset() in between. reset() already clears the flag and state, so the second clear — apps/web/src/dummy/store.test.ts:26-28
- **T2** (Minor): The 'entities never persisted' assertion enumerates three hardcoded key guesses (dummy:entities, dummy:dummyEntities, dummy_entities). Asser — apps/web/src/dummy/store.test.ts:57-59
- **T2** (Minor): reset() restores isDummy/dummyEntities/role and clears the persisted flag but does not clear the module-level dummyGenerator slot, so a prev — apps/web/src/dummy/store.ts:81-84
- **T3** (Minor): Duplicate localStorage.clear() in test beforeEach (called on line 1 and line 3 of the block) — harmless but redundant — apps/web/src/components/auth-role-offline.test.tsx:42-46
- **T3** (Minor): useSidebarAuth reads isDummy only on mount, so a live ON->OFF toggle mid-session without remount keeps the offline role until remount. Bound — apps/web/src/components/Sidebar.tsx:82-93
- **T5** (Minor): Supplier range is declared rng.int(7, 9) but SUPPLIER_NAMES (from T1 seed) has only 8 entries, so .shuffle(...).slice(0, count) silently tru — apps/web/src/dummy/factory.ts:181-184
- **T5** (Minor): DummyOutlet carries id/name/territoryId/city/lat/lon but no address/district. AdminOutlet (apps/web/src/app/admin/outlets/api.ts:13) has opt — apps/web/src/dummy/factory.ts:20-27
- **T5** (Minor): buildOutlets declares takeName and pushOutlet as closures that close over usedNames/counter/outlets. This is idiomatic but means the functio — apps/web/src/dummy/factory.ts:117-139
- **T6** (Minor): Commit message uses feat(web) scope; DELIVERABLE requires feat(dummy) scope. Content is the single conventional-commits task commit, so this — 93aee80 (commit message)
- **T6** (Minor): DummyBootstrap.tsx (a 'use client' wrapper calling installDummy()) is the 10th file, outside the task's 9-file scope. Justified: layout.tsx — apps/web/src/components/DummyBootstrap.tsx:1-15
- **T6** (Minor): Aggregates.suppliers (SupplierPerformanceData) shadows MasterData.suppliers (DummySupplier[]) in buildFullDummy's spread. Verified intention — apps/web/src/dummy/index.ts:buildFullDummy
- **T6** (Minor): Forecast accuracy metrics (wape '0.12', accuracy 0.88) are fixed presentation constants gated on actualDays >= 14, while all counts, sums, f — apps/web/src/dummy/aggregates.ts:613-614
- **T7** (Minor): useDummyRefresh's useEffect dependency array includes the onChange callback. If Phase B callers pass inline arrow functions (e.g., useDummyR — apps/web/src/dummy/guards.ts:50
- **T7** (Minor): Redundant double localStorage.clear() in beforeEach — lines 12 and 14 both call localStorage.clear(), with useDummyStore.getState().reset() — apps/web/src/dummy/guards.test.ts:12-14
- **T7** (Minor): The useDummyRefresh integration test (cycle 3) fires two store toggles and asserts exactly 2 onChange calls. It does not explicitly verify t — apps/web/src/dummy/guards.test.ts:82-98
- **T8** (Minor): Each guard calls useDummyStore.getState() twice (once for isDummy, once for dummyEntities); hoisting to a local variable would reduce duplic — apps/web/src/lib/data-intelligence-api.ts:68
- **T9** (Minor): Commit message 'feat(dummy-mode): T9 — guard admin/* + sales/* read APIs with withDummyRead' differs literally from DELIVERABLE 'feat(dummy) — commit f5fad13
- **T10** (Minor): Identical 3-line money() formatter duplicated in payments/api.ts, ProductCatalog.tsx, MarketplaceCatalog.tsx, OrderForm.tsx; trivial colocat — apps/web/src/app/payments/api.ts:34
- **T10** (Minor): Dummy stock formula 40 + ((idx * 13) % 260) repeated in ProductCatalog, MarketplaceCatalog, OrderForm builders; deterministic demo derivatio — apps/web/src/components/ProductCatalog.tsx:26
- **T10** (Minor): trackOrder guards via early-return instead of withDummyRead like siblings; behavior identical (zero network) but inconsistent idiom — apps/web/src/components/OrderForm.tsx:52
- **T11** (Minor): mutations.ts is 656 lines — a large single module (mandated as one file by the packet; cohesive but unsplit). — apps/web/src/dummy/mutations.ts
- **T11** (Minor): updateDummyOutlet persists only `name` into the store graph; category/city/district/address/is_active are echoed in the return value but not — apps/web/src/dummy/mutations.ts:575
- **T11** (Minor): Promotion mutators write to graph.promotions, but the promotions read path (listDummyPromotions) derives the list independently from product — apps/web/src/app/admin/promotions/api.ts:19
- **T12** (Minor): Test file is 429 lines (was 414), exceeding the ~300-line refactor heuristic. Justified as a comprehensive cross-unit integration test with — apps/web/__tests__/dummy-mode.e2e.test.tsx

## Strengths (recorded for follow-up)

- **T1** (strength): clean separation between RNG, dates, and seed constants — easy to reason about
- **T2** (strength): Persist-to-localStorage hygiene and reset semantics handled correctly
- **T4** (strength): Topbar toggle respects spec's goal-level affordance (between Online and Keluar, Mode Dummy switch)
- **T8** (strength): Data-intelligence + operations guards compose cleanly via per-API-function guard

## Skipped Tasks

_None_ — every task was reviewable (DONE + done_sha + matching reviewed_sha).
