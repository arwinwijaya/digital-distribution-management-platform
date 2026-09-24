# Task T12 — Assignment A/B deterministik + `RevenueLiftService`

**Phase:** 3
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 12: A/B assignment + revenue lift

## OBJECTIVE
Implementasi assignment bucket deterministik (`hash(experiment_key|subject_key)`) dan
perhitungan revenue lift treatment vs control dari data order/measurement, dipersist ke
`revenue_lift_snapshots` dengan status `insufficient-data` saat sampel kurang.

Steps:
1. Write failing unit tests.
   Test file: `apps/api/tests/Unit/AbAssignmentRevenueLiftTest.php`
   Level: unit
   Test intent: Given experiment_key + subject_key / When assign / Then stable control/
   treatment across calls; revenue lift computed from provided samples with method_version;
   sample < minimum (30) → insufficient-data, not zero/perfect.
   Test doubles: order/measurement fixture rows.
   Expected RED: services belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AbAssignmentRevenueLiftTest`
3. Implement assignment + revenue lift + persist snapshot → PASS → commit.
4. Write failing test: same inputs → identical lift; empty data safe.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec AC-6, A-1, A-6; `MeasurementService` (funnel/safeRate/decimalToCents).

## WHY THIS APPROACH
Complexity: medium
Justification: pengukuran lift harus deterministik dan jujur (insufficient-data bukan 0).

## SANDWICH CONTEXT
[CRITICAL: deterministic hash; no randomness; sample threshold configurable]
Files in scope: `apps/api/app/Services/AbExperimentService.php`, `RevenueLiftService.php`, test.
Available after: T1.
Architecture rule: unique (experiment_id, subject_key); decimal-safe; method_version.

## DELIVERABLE
- assign() + computeLift() + persist; insufficient-data safe.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: deterministic; threshold; decimal-safe; no false perfect score.
Must-not-have: random bucketing; auto-applying treatment to users.
Open question risks: A-6 (no auto routing in MVP).
Rollback note: disable experiment endpoints; snapshots retained as history.

## STOP CONDITIONS
Done when: assign/compute/insufficient/deterministic tests PASS.
Escalate when: butuh definisi metrik lift bisnis spesifik (mis. GMV vs unit).
