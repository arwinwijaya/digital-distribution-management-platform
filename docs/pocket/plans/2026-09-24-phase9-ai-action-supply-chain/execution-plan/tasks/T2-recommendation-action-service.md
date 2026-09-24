# Task T2 — `RecommendationActionService` draft-first + idempotency

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: `RecommendationActionService` draft-first + idempotency

## OBJECTIVE
Implementasi service yang membuat **draft** aksi dari rekomendasi tanpa memutasi state
bisnis, dengan idempotency (`idempotency_key` + payload hash) dan audit transisi awal.

Steps:
1. Write failing test: draft dibuat & idempotent replay.
   Test file: `apps/api/tests/Unit/RecommendationActionServiceTest.php`
   Level: unit
   Test intent: Given payload valid (`type=draft_order`, items) / When `createDraft()` /
   Then baris `recommendation_actions` status `draft` + satu audit `created`, **tanpa** Order
   baru; When dipanggil ulang dengan `idempotency_key` + payload identik / Then
   `idempotent_replay=true` tanpa baris baru; When payload berbeda / Then exception 422/409.
   Exercise through: `RecommendationActionService::createDraft()`.
   Test doubles: factories Outlet/Product/User.
   Expected RED: class belum ada → error.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RecommendationActionServiceTest`
3. Implement service (hash payload via pola `OrderCreationService::payloadFingerprint`) →
   PASS → refactor → commit.
4. Write failing test: reject payload invalid (type tak dikenal, items kosong) → 422.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec Phase 9 — DD-1, DD-3, DD-5; AC-1.
`apps/api/app/Services/OrderCreationService.php` (payloadFingerprint/idempotency),
`apps/api/app/Models/RecommendationAction.php` (T1).

## WHY THIS APPROACH
Complexity: medium
Justification: memusatkan logika draft + idempotency di service agar controller tipis dan
dapat diuji tanpa HTTP; pola disalin dari `OrderCreationService`.

## SANDWICH CONTEXT
[CRITICAL: tidak boleh memanggil OrderCreationService/PromotionService di sini — draft saja]
Files in scope: `apps/api/app/Services/RecommendationActionService.php`,
`apps/api/tests/Unit/RecommendationActionServiceTest.php`.
Available after: T1 (tabel + model).
Architecture rule: idempotency key unik; payload hash kanonik (json sortir kunci); audit append-only.

## DELIVERABLE
- `createDraft(array $payload, User $actor): array{action: RecommendationAction, created: bool}`
  dengan validasi + idempotency + audit `created`.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Draft tidak membuat order/promo.
  - Idempotent replay (payload identik) & konflik payload berbeda.
  - Audit `created` tercatat.
Must-not-have:
  - Mutasi state bisnis apapun.
  - Mengambil actor dari payload (harus dari argumen `$actor`).
Open question risks:
  - Definisi hash kanonik untuk `draft_campaign` (field promo) — samakan dengan items order.
Rollback note:
  - Service murni; hapus pemanggilan = kembali ke read-only.

## STOP CONDITIONS
Done when: test draft + idempotency + validasi PASS.
Uncertain when: bentuk payload `draft_campaign` belum jelas → pakai skema promo minimum.
Escalate when: butuh entitas baru untuk draft selain action.
