# Task T3 — Seam adapter `RecommendationModelAdapter` + deterministik + guardrail validator

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Seam adapter `RecommendationModelAdapter` + deterministik + guardrail validator

## OBJECTIVE
Definisikan interface `RecommendationModelAdapter` + implementasi default deterministik
(membungkus `RecommendationService`), sebuah validator skema output, dan resolver config
dengan fallback aman. Adapter **tidak boleh** memutasi state bisnis.

Steps:
1. Write failing test: default deterministik + fallback saat adapter gagal.
   Test file: `apps/api/tests/Unit/RecommendationModelAdapterTest.php`
   Level: unit
   Test intent: Given config default / When `resolve()` / Then mengembalikan
   `DeterministicRecommendationAdapter`; When adapter eksternal (fake) melempar exception /
   Then hasil fallback deterministik dengan `fallback=true`, tanpa exception keluar;
   When output adapter gagal validasi skema / Then fallback + penanda `invalid_output=true`.
   Exercise through: `RecommendationModelAdapterResolver` + `ModelOutputValidator`.
   Test doubles: `FakeRecommendationModelAdapter` (throwing / invalid output).
   Expected RED: class belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RecommendationModelAdapterTest`
3. Implement interface + deterministik adapter + resolver + validator → PASS → refactor → commit.
4. Write failing test: adapter tidak boleh memanggil service mutasi (assert tidak ada Order
   baru setelah `predict()`).
5. Run test — verify FAIL → implement (guard) → PASS → commit.

## REFERENCES LOADED
Spec Phase 9 — DD-4, AC-7, A-3.
`apps/api/app/Services/RecommendationService.php`; `apps/api/config/*` (pola config driver).

## WHY THIS APPROACH
Complexity: medium
Justification: roadmap menuntut kemampuan AI, tetapi keandalan produk tidak boleh bergantung
pada layanan eksternal non-deterministik; seam + fallback membuatnya opsional dan teruji.

## SANDWICH CONTEXT
[CRITICAL: adapter hanya mengembalikan saran; dilarang memutasi state bisnis]
Files in scope: `apps/api/app/Services/Recommendation/RecommendationModelAdapter.php` (interface),
`DeterministicRecommendationAdapter.php`, `RecommendationModelAdapterResolver.php`,
`ModelOutputValidator.php`, `apps/api/config/ai_actions.php`, test terkait.
Available after: T1; `RecommendationService` sudah ada.
Architecture rule: timeout + fallback; output divalidasi skema ketat; kill-switch config.

## DELIVERABLE
- Interface + adapter deterministik + resolver + validator, dengan `method`, `method_version`,
  `fallback` pada hasil.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Config default → deterministik; kegagalan eksternal → fallback tanpa 500.
  - Output tak sesuai skema → ditolak + fallback.
  - Tidak ada mutasi state bisnis.
Must-not-have:
  - Dependency HTTP client wajib / network call di default.
  - Menyimpan prompt/response mentah berisi PII.
Open question risks:
  - A-3: tidak ada layanan ML/LLM nyata → uji via fake/stub.
Rollback note:
  - Set `ai_actions.ml_adapter.driver=deterministic` memaksa fallback.

## STOP CONDITIONS
Done when: test deterministik + fallback + validator + guard PASS.
Uncertain when: skema output adapter eksternal belum ditentukan → definisikan minimum.
Escalate when: dibutuhkan kredensial layanan eksternal untuk menguji.
