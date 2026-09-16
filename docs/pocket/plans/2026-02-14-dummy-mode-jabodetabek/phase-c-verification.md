# EXECUTION PLAN — Dummy Mode JABODETABEK: Phase C (Verification)

**Date:** 2026-02-14
**Spec:** docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
**Status:** draft
**Total tasks:** 1

---

## Execution Overview

### Recommended Order
```
All Phase A + Phase B tasks complete → T1 (single integration verifier)
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
| Group | Tasks | Unblocked After |
|-------|-------|-----------------|
| Group A | T1 | Phase A T6 (tx+aggregates) + T7 (guards) AND Phase B B-T1..B-T4 complete |

### Constraints Reminder
**Architecture:** Touch ONLY `apps/web/src` + `apps/web/__tests__/` or new `e2e`-style folder inside `apps/web`. This task is read-only on app logic (no module/package edits except `Topbar.tsx`'s optional badge variant covered below — or flag it NEEDS_CONTEXT instead). DO NOT touch `apps/api`, `database/*`, `docker-compose.yml`.
**Out-of-scope:** Backend/API changes; new prod dependencies; sharpening driver/sales role scopes.
**Assumptions at risk:** Cross-unit verification is the last defense for spec drift (no Zod) and for confirming zero-network + ephemeral guarantees end-to-end.
**Sequencing:** This plan has one task — there is nothing to parallelize inside it, and the task itself is not parallel.
**Test hygiene (all phases):** `beforeEach` → `localStorage.clear()`, re-seed `ddp_token`/`ddp_role`, reset the store singleton, register the fixed-today generator; `afterEach` → `jest.restoreAllMocks()`. NOTE: DI read functions resolve the token via `getStoredToken()` internally — seed `ddp_token` before any OFF-path fetch assertion.

### File Structure Map
```
All stories — final integration proof
  Create: apps/web/__tests__/dummy-mode.e2e.test.tsx       (created by: T1)
  Test:   apps/web/__tests__/dummy-mode.e2e.test.tsx       (created by: T1)
  Modify: apps/web/src/components/Topbar.tsx (only if badge text deviates — else no edit)
```

Note: No other app file is modified by this phase. Spec does NOT prescribe a badge/toggle label string — the toggle only has to sit "antara Online & Keluar". If the verifier finds the affordance missing entirely, fix by adding a labelled toggle per Phase A T4's design (do NOT invent an exact badge string); if multiple UI inconsistencies surface, escalate NEEDS_CONTEXT rather than expanding scope to page-level fixes.

---

## Pocket Packets

---

### Task 1: Cross-unit Dummy Mode integration verification [depends: Phase A T6 + T7 and Phase B B-T1..B-T4]

## OBJECTIVE
Prove the three story acceptance criteria end-to-end (GWT level) by toggling real components, loading every guarded helper against the real Zustand store, and asserting zero network + no empty-state text + ephemeral mutations — all without reaching the backend. Also verify funnel tracking is faked (Story 2-F / Story 3-E) and finance dashboard is populated (Story 2-E).

Files:
- Create: `apps/web/__tests__/dummy-mode.e2e.test.tsx`
- Modify (only if needed): `apps/web/src/components/Topbar.tsx` — accessible label fix

Steps:
1. Write failing test for: analytics populated + geo points + order→payment→invoice side-effects
   Test file: `apps/web/__tests__/dummy-mode.e2e.test.tsx`
   Level: integration

   Test intent:
   Given `localStorage ddp_token` + `ddp_role='admin'`, dummy store OFF, jest.fn() global fetch returning a stub, and the real `Topbar` rendered
   When the Mode Dummy switch is toggled ON
   Then:
   - Analytics loaders return non-empty recommendations, 4-period forecast, and non-empty segmentation; rendering the analytics page shows NEITHER `Belum ada rekomendasi` NOR `Data belum cukup`
   - Data-Intelligence GeoMap `map_points` length is 40..60 with every point inside the JABODETABEK bbox and `table.length === 5`
   - Finance dashboard (`loadDashboard(token, 'finance')`) returns ALL FinanceMetrics keys (issued_invoices, outstanding_balance, overdue_rate, collection_time, payment_status_breakdown, reminders) — not just the admin shape
   - Submitting an order via `createSalesOrder(token, payload)` creates exactly one order with a `dummy-` id, one payment, one invoice, one delivery, and global fetch was NEVER called
   - `sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 })` returns fake success with shape `{ event_uuid: string, event_type: 'clicked', outlet_id: 1, product_id: 2 }` AND global fetch was NEVER called (Acceptance Criteria: Story 2, sendFunnelEvent fake success). NOTE: real signature is `(event: FunnelEventType, context?: FunnelEventContext, eventUuid?: string)` where `FunnelEventType = 'displayed' | 'clicked' | 'cart'` and `FunnelEventContext = { outlet_id?: number|null, product_id?: number|null, occurred_at?: string }`. Not `(token, payload)`.

   Suite hygiene: `beforeEach` clears localStorage, re-seeds `ddp_token`/`ddp_role`, resets the dummy store + registers a fixed-today generator (`setDummyGenerator((today?) => buildFullDummy(fixedToday ?? new Date('2026-02-14')))`) so the determinism assertions below have a known pin.

   Exercise through:
   - Real `Topbar` (goal-level UI entry point per spec — Topbar toggle is the user's affordance)
   - Real loader functions `loadAnalytics` (from B-T3) + `fetchGeographicData` (from B-T1) + `createSalesOrder` (from B-T4)
   - Real `useDummyStore` (toggled by the switch click, not by `getState().toggle()` directly)

   Test doubles:
   - mock/fake: global fetch (assert NEVER called while ON); localStorage `ddp_token`/`ddp_role`; jsdom
   - do NOT mock: Topbar, loaders, api modules, guards, store, factory

   Expected RED:
   - Any success signal unmet (e.g. `map_points` empty, analytics empty, missing side-effects) → assertion fails

2. Run test — verify FAIL:
   `cd apps/web && npx jest __tests__/dummy-mode.e2e.test.tsx --runInBand`
   Expected failure: any signal assertion fails (or the modules are not yet wired in a local checkout — still FAIL before GREEN)

3. Implement app fixes ONLY to make the signals pass, scoped to the described gap:
   - If the Topbar has no Dummy affordance at all (no switch containing 'dummy' text/label while logged in), fix by adding the label per Phase A T4's design — this must NOT happen if T4 passed. Otherwise leave Topbar untouched (do NOT invent an exact badge string — spec only says the toggle sits 'antara Online & Keluar').
   - Any other signal failure that surfaces as "real data leaked while ON" or "empty analytics array" or "side-effect missing" must already be covered by Phase A/Phase B helpers — do NOT re-edit helpers here beyond re-running those tasks; if the helper fix scope grows beyond one file, escalate NEEDS_CONTEXT and leave the test RED with an inline comment naming the responsible helper and its spec line.

4. Run test — verify PASS:
   `cd apps/web && npx jest __tests__/dummy-mode.e2e.test.tsx --runInBand`
   Expected: PASS

5. Write failing test for: refresh persistence, auto re-fetch on OFF, zero network invariant
   Test file: `apps/web/__tests__/dummy-mode.e2e.test.tsx` (append)
   Level: integration

   Test intent:
   Given the store toggled ON and a mutated dummy order exists
   When "refresh" is simulated (remount `Topbar` with `localStorage['dummy:isDummy']` still set, clear the store singleton, and re-init) and then toggle OFF
   Then:
   - after remount, the store restores `isDummy === true` from localStorage AND `dummyEntities` is non-null (regenerated by the init path, per T2 store spec) — this is Story 1 B: refresh renders the same deterministic data, not just an empty state
   - calling `loadAnalytics(token)` after the remount returns non-empty recommendations (verifying data renders, not just the flag)
   - after toggle OFF, `dummyEntities === null` and the subsequent loader re-fetch DOES call global fetch (real data return — spec: toggle OFF auto re-fetches without navigation, entities never persisted)
   Given store ON with jest.fn() global fetch, When ALL guarded reads are issued in a single cross-unit sweep:
   `loadAnalytics`, `loadDashboard(t, 'admin')`, `loadDashboard(t, 'finance')`, `fetchGeographicData`, `fetchSupplierPerformanceData`, `fetchStockPlanningData`, `fetchRecommendationMeasurementData`, `fetchForecastMeasurementData`,
   `fetchReadiness`, `fetchIssues`, `fetchIssueDetail`,
   `fetchAdminOutlets`, `fetchAdminUsers`, `fetchProducts`, `fetchPromotions`, `fetchAdminSalesPerformance`,
   `fetchSalesOutlets`, `fetchCatalogProducts`, `fetchMyPerformance`,
   plus page loaders: `loadInvoices`, `loadDeliveries`, `loadPaymentsList`, `loadOperations`, `loadSalesList`, `loadProductCatalog`, `loadMarketplaceCatalog`, `loadOrderFormProducts`, `trackOrder`, `fetchOutletOrders(t, 1)`, `fetchOutletSummary(t, 1)`, `fetchPriceHistory(t, 1)`,
   Then:
   - global fetch call count is exactly 0 (cross-unit zero-network invariant covering every guarded call-site in Phases A+B)

   Exercise through:
   - Same real components/loaders + a fresh jsdom mount (simulated refresh) + the store's `persist`-to-localStorage path

   Test doubles:
   - mock/fake: global fetch; localStorage; jsdom remount
   - do NOT mock: Topbar, loaders, helpers, store

   Expected RED:
   - Refresh loses the flag (reads `ddp_role` but not `isDummy`), or mutations survive toggle OFF

6. Run test — verify FAIL / then fix the described gap (scoped like step 3) / verify PASS.

7. Write failing test for: determinism after toggle cycle (refresh-persistent identity)
   Test file: `apps/web/__tests__/dummy-mode.e2e.test.tsx` (append)
   Level: integration

   Test intent:
   Given a fixed `today` (2026-02-14) pinned by registering `setDummyGenerator((today?) => buildFullDummy(today ?? new Date('2026-02-14')))` BEFORE the first toggle — this is the ONLY seam by which `today` reaches the factory through the real `toggle()` path (the store's `toggle()` calls `generate()` with no args; the generator itself defaults to the pinned date)
   When ON → OFF → ON is toggled
   Then:
   - the second ON produces byte-identical aggregates to the first ON (deterministic per the fixed seed + rolling window contract)
   - outlet `dummy-001` still reads "Toko Bogor Indah" on the second cycle

   Exercise through:
   - `useDummyStore.getState().toggle()` cycles; compare the two `dummyEntities` snapshots

   Test doubles:
   - mock/fake: localStorage; the real factory wrapped by the fixed-today generator (NOT a fake of the factory's output)
   - do NOT mock: store, factory

   Expected RED:
   - Second cycle differs (Date.now()-seeded RNG or non-deterministic window) → equality fails

8. Run full web suite to confirm zero regressions:
   `cd apps/web && npx jest --runInBand`
   Expected: all pre-existing + new tests PASS

9. Refactor `dummy-mode.e2e.test.tsx` only (bounded — no app refactors in this phase) + re-run (must stay PASS).

10. Commit:
   `git add apps/web/__tests__/dummy-mode.e2e.test.tsx`
   `git commit -m "test(dummy): add cross-unit dummy-mode integration coverage"`
   If Topbar label was fixed:
   `git add apps/web/src/components/Topbar.tsx`
   `git commit -m "fix(dummy): add accessible label to Mode Dummy toggle"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Acceptance Criteria (Story 1 GWT, Story 2 GWT, Story 3 GWT) and Stories + Scenarios; Story 3 R1/R3/R4 (zero network, 1:1 side-effects, ephemeral); Story 1 (toggle OFF auto re-fetch, refresh persistence, localStorage survival; Story 1 LOGOUT clears ddp_role); Story 2 R2/R3 (determinism, analytics non-empty) — see also Phase A `T1`/`T2`/`T6` semantics registered as blocker-driven bridges.
Preflight: goal-level UI entry point is `Topbar.tsx` (per spec); Phase B `B-T3` owns `loadAnalytics`; Phase A `T1` owns the fixed `DUMMY_SEED` (determinism); Phase A `T2` owns persist-`isDummy`-only. Jest reads `apps/web/jest.config.js`.

## WHY THIS APPROACH
Justification: System C-2 cross-unit integration (real Topbar + real loaders/store/helpers) is the only level where the three story acceptance criteria compose — unit tests on helpers pass while end-to-end toggles stay broken. The "fix scope bounded to one label" constraint prevents this verifier from turning into a second Phase B; deeper failures escalate as NEEDS_CONTEXT so the full suite confirms unit-test quality.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Do NOT edit helpers/pages here — if the test fails because a helper is wrong, flag NEEDS_CONTEXT naming the helper + spec line instead of patching Phase B inline]
You are writing the cross-unit verifier for Dummy Mode JABODETABEK — the final gate before HANDOFF TO DELIVERY.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard
Files in scope: `apps/web/__tests__/dummy-mode.e2e.test.tsx` and — ONLY if the badge text deviates — `apps/web/src/components/Topbar.tsx` (one-line label fix) — no other file
Available after: Phase A T6 + T7 and every Phase B task (T1–T4) complete and green
Architecture rule: Keep helpers/pages read-only in this phase. Escalate with NEEDS_CONTEXT rather than bulk-fixing Phase A/B inline.
[RESTATE: Do NOT edit helpers/pages here — if the test fails because a helper is wrong, flag NEEDS_CONTEXT naming the helper + spec line instead of patching Phase B inline]

## DELIVERABLE
Given store toggled ON via the real Topbar switch, When analytics + GeoMap + order submit + funnel loaders run, Then ALL three story acceptance criteria pass AND global fetch count is 0
Given store ON with a mutation, When refresh simulated then toggle OFF, Then flag restored AND dummyEntities deep-equals the first ON entities (not just non-null) after remount, with analytics populated AND entities null after OFF AND subsequent loader real-fetches
Given all guarded reads (analytics + DI reads + ops + admin/sales + all page loaders ≈ 27 call-sites), When inspected, Then global fetch call count is 0 (cross-unit zero-network invariant)
Given fixed today (pinned via generator registration), When ON → OFF → ON toggled, Then second ON produces deep-equal entities to first ON AND dummy-001 is Toko Bogor Indah (determinism)

All tests PASS. Full `npx jest` suite green. Commit(s) exist with messages matching `test(dummy): add cross-unit dummy-mode integration coverage` (+ optional `fix(dummy): add accessible label to Mode Dummy toggle`).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Goal-level entry point: real `Topbar` rendering + switch click
  - Three story acceptance criteria verified against real loaders/modules (not stubs)
  - Zero-network asserted with a `jest.fn()` global fetch at the cross-unit level
  - Ephemerality proven by the toggle cycle (no `dummy-` records survive)
  - Refresh persistence proven by remount reading `localStorage['dummy:isDummy']` AND producing deep-equal entities (not just non-null)
  - Zero-network sweep covers all guarded read call-sites (not just 4 loaders) — aim for ≥25 in a single `expect(fetch).toHaveBeenCalledTimes(0)`
  - Determinism asserted across the toggle cycle (same seed + same today → equal)
  - Full suite green (regression guard for earlier phases)
  - Tests written BEFORE app fixes (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Bulk edits to helpers/pages (escalate instead)
  - Backend/API/DB changes; new dependencies
  - `Date.now()` inside test determinism assertions (inject today's value)

Open question risks:
  - Topbar badge text is NOT spec-prescribed (spec only positions the toggle "antara Online & Keluar") — so the "only if needed" branch rarely fires; if the affordance exists, make no Topbar edit at all
  - Dummy operations issue detail remains non-navigable by design (do NOT test navigating to it as part of story acceptance criteria; leave it as a D/CARVE-OUT validated at the B-T1 unit level)

Rollback note:
  - Delete `apps/web/__tests__/dummy-mode.e2e.test.tsx`; revert the optional Topbar line. Phase A/B behavior is unaffected.

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, full suite green, commit created
Uncertain when: the story acceptance criteria compose differently than phrased because a loader name differs (adapt the loader target, note it in the test header comment, and proceed)
Escalate when: a failure points to a Phase A/B helper bug that would require editing `apps/web/src/dummy/*` or `apps/web/src/lib/*` or `apps/web/src/app/*/*` beyond the allowed one-line Topbar label fix — return NEEDS_CONTEXT with helper path + spec line + observed vs expected

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Cross-unit integration verifier | Phase A T6+T7, Phase B B-T1..B-T4 | standard | 3 story acceptance criteria + funnel + finance dashboard, zero network, ephemerality, refresh persistence, determinism |
