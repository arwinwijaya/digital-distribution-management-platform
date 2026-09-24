# Task T17 — Fallback & guardrail adapter lintas unit [test-risk]

**Phase:** 6
**Depends:** T3, T5–T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 17: Fallback & guardrail lintas unit

## OBJECTIVE
Verifikasi lintas unit bahwa adapter deterministik default dipakai, adapter eksternal
gagal → fallback aman, output invalid → ditolak, dan adapter tidak pernah memutasi
state bisnis. Marker `[test-risk]` karena strategi pengujian lintas batas adapter/service.

Langkah verifikasi (bukan implementasi baru kecuali gap):
1. Write cross-unit test A: Given `FakeFailingAdapter` / When full draft flow dijalankan
   end-to-end / Then no 500, response carries fallback=true, no Order created.
   Test file: `apps/api/tests/Feature/Phase9AdapterFallbackTest.php`
   Level: **cross-unit** (route → resolver → validator → service)
   Test intent: timeout/exception adapter → fallback deterministik.
   Test doubles: fake adapters (failing / invalid / slow).
   Expected RED (bila gap): flow error 500 atau mutasi state.
2. Write cross-unit test B: invalid output → rejected + fallback; verify zero Order/Promotion.
3. Run: `cd apps/api && php artisan test --filter=Phase9AdapterFallbackTest`
4. Fix gap bila ada (service/resolver/validator) → PASS → refactor → commit.

## REFERENCES LOADED
Spec DD-4, AC-7; T3 seam; T5–T8 flow.

## WHY THIS APPROACH
Complexity: high
Justification: risiko utama Phase 9 adalah adapter non-deterministik bocor ke mutasi;
verifikasi lintas unit menangkap interaksi yang tidak terlihat unit test.

## SANDWICH CONTEXT
[CRITICAL: test lewat HTTP boundary bila memungkinkan; jangan mock seluruh flow]
Files in scope: test + fix minimal di resolver/validator/service. No new schema.
Available after: T3 + T5–T8.
Architecture rule: timeout, output validation, kill-switch, actor-safe audit.

## DELIVERABLE
- Cross-unit proof: fallback + invalid-output + no-mutation.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: no 500; fallback flagged; no business mutation; audit intact.
Must-not-have: skipping slow/timeout coverage.
Rollback note: force deterministic driver.

## STOP CONDITIONS
Done when: cross-unit tests PASS on real HTTP stack.
Uncertain when: adapter eksternal nyata belum tersedia → fake/stub acceptable (document).
Escalate when: fallback mengubah kontrak response yang dipakai FE.
