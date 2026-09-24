# Task T18 — Cross-unit integration verification end-to-end [test-risk]

**Phase:** 6
**Depends:** semua task Phase 1–5
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 18: Cross-unit integration verification

## OBJECTIVE
Verifikasi end-to-end seluruh rantai Phase 9: draft → approve → execute (order &
campaign), replenishment draft → approve → execute, kalibrasi, assignment/lift,
idempotency, audit, auth/RBAC, dan dummy parity. Marker `[test-risk]` karena skenario
lintas unit.

Langkah verifikasi:
1. Write integration test API: full lifecycle draft_order → approve → execute creates
   exactly one Order; draft_campaign → approve → execute creates exactly one Promotion;
   execute-before-approve 422; idempotent replay; invalid transitions 422; non-admin 403.
   Test file: `apps/api/tests/Feature/Phase9EndToEndTest.php`
   Level: **integration/cross-unit** (auth → controller → service → existing services → DB).
   Expected RED bila gap: duplicate/missing state.
2. Write integration test replenishment + calibration + lift + audit completeness
   (setiap transisi ada satu event; `method`/`method_version`/`fallback` terlihat di response).
3. FE check: `npx jest` untuk halaman T13–T16 + dummy ON zero network smoke; `tsc --noEmit`.
4. Run suites → fix gap minimal → PASS → refactor → commit.
5. Verifikasi `migrate` + rollback per-step Phase 9 bersih.

## REFERENCES LOADED
Spec AC-1–AC-10; semua task T1–T17; pola verifikasi Phase 8 T18.

## WHY THIS APPROACH
Complexity: high
Justification: satu test yang menghubungkan unit-unit membuktikan kontrak kolaborasi,
bukan hanya unit isolation.

## SANDWICH CONTEXT
[CRITICAL: gunakan stack HTTP nyata; factory data nyata; jangan mock service inti]
Files in scope: test + fix minimal. No new schema/business mutation path.
Available after: semua task.
Architecture rule: existing suites hijau; migration reversible; envelope + RBAC.

## DELIVERABLE
- Bukti end-to-end: 2 execute paths + replenishment + audit + idempotency + fallback.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: exactly-once business effect; append-only audit; admin gates; dummy parity.
Must-not-have: new business mutation paths; skipped rollback check.
Rollback note: kill-switch config; rollback migrasi per-step.

## STOP CONDITIONS
Done when: API + web suites hijau, lifecycle + audit + idempotency terbukti, rollback bersih.
Siap untuk `closeout.md`.
Uncertain when: flakiness concurrency di SQLite → stabilkan dengan retry/lock pola existing.
Escalate when: gap arsitektur ditemukan (mis. transaksi lintas service deadlock).
