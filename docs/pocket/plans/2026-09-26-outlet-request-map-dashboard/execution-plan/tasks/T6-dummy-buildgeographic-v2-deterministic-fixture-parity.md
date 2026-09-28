# Task T6 — Dummy — buildGeographic v2 + deterministic fixture parity

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Dummy — buildGeographic v2 + deterministic fixture parity [depends: T1]

## OBJECTIVE
Extend `buildGeographic()` in `apps/web/src/dummy/aggregates.ts` to the v2 shape with a deterministic fixture matching the spec table (outlet IDs 101–105), and update `aggregates.test.ts` so the JABODETABEK bbox assertion checks plottable points only plus literal per-status/period expectations.

Steps:
1. Write failing test for: deterministic v2 fixture values
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit
   Test intent: Given the deterministic master/tx fixture (101=H valid, 102=E `null` lat, 103=F `(0,0)`, 104=C zero orders, 105=V1 legacy-only) / When `buildGeographic(master, tx, window)` / Then `map_points` carry the exact literal `orders_by_status`, `sales_by_status`, `daily_by_status` (H buckets 09-20/09-22/09-25), `product_summary`, `product_summary_truncated`, `latest_request`, and `plottable` per the spec table; V1 has no v2 fields.
   Exercise through: `buildGeographic`
   Test doubles: fixed master+tx fixture (NO RNG / no randomness in the geographic fixture)
   Expected RED: v1 shape only; fixture IDs absent
2. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/aggregates.test.ts`
3. Implement `buildGeographic` v2 + deterministic fixture for 101–105 → verify PASS → refactor → commit `feat(dummy): geographic v2 fixture with deterministic parity`
4. Write failing test for: literal filter outcomes + bbox update
   Test file: `apps/web/src/dummy/aggregates.test.ts`
   Level: unit
   Test intent: Given the fixture, When `computeFilteredCounts` (from T1) runs for the documented combos / Then the literals hold: Semua+30d H=10; Semua+7d H=8; {New,Conf}+7d H=5; {New,Conf}+Hari ini H=3; invalid count=2 (E,F); C hidden; V1 excluded from 7d with `outlets_without_daily_detail=1`; and the bbox assertion now covers plottable points only (E/F excluded).
   Exercise through: `buildGeographic` + `computeFilteredCounts`
   Test doubles: fixed fixture
   Expected RED: v2 fields absent so counts cannot be literal-asserted
5. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/aggregates.test.ts`
6. Verify literal assertions pass and update the bbox test → refactor → commit `test(dummy): literal parity assertions for geographic fixture`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rule: Dummy fixture matrix (deterministic table, NO RNG), Story: Dummy parity; spec section: Deterministic dummy fixture table
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: deterministic fixture construction plus v2 field parity and literal assertions.

## SANDWICH CONTEXT
[CRITICAL: The dummy fixture must assert the exact literals from the spec table — never recompute expected values with the filter code under test]
You are implementing the dummy geographic aggregate v2 with deterministic fixture parity.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/web/src/dummy/aggregates.ts`, `apps/web/src/dummy/aggregates.test.ts`
Available after: T1 (filter helpers for literal assertions)
Architecture rule: `withDummyRead` parity, deterministic output (no randomness in the geographic fixture), integer-cents money, explicit fallback for v1 rows
[RESTATE: Assert exact literals from the spec table]

## DELIVERABLE
Given the deterministic fixture (101–105), When `buildGeographic`, Then `map_points` carry v2 fields matching the spec's Expected literals table
Given Semua+7d, Then H=8, invalid=2 (E,F), C hidden, V1 excluded with `outlets_without_daily_detail=1`
Given V1 (legacy-only), When 30d, Then the legacy marker renders; when 7d/Hari ini, Then it is excluded and counted
Given the updated bbox test, Then it asserts plottable points only
[must-not] Given the fixture, When built, Then the test must NOT derive expected values from the filter implementation

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Fixture IDs 101–105 stable across runs
  - Every asserted value is a fixed literal from the spec table
  - `daily_by_status` buckets match the spec (H: 09-20, 09-22, 09-25)
  - `product_summary` deterministic top-5
  - `latest_request` present for v2 rows, absent for V1
Must-not-have:
  - Random generation in the geographic fixture
  - Recomputing expected values with the filter code under test
Open question risks:
  - Existing dummy master schema cannot host IDs 101–105 → report NEEDS_CONTEXT
Rollback note:
  - Revert to the v1 dummy geographic and restore the previous bbox test
Red flags:
  - Non-deterministic fixture → STOP
  - Existing unrelated aggregate tests broken → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: all literal assertions pass, bbox test updated, parity with the production filter math
Uncertain when: fixture data conflicts with the existing dummy master schema
Escalate when: the fixture cannot be made deterministic
