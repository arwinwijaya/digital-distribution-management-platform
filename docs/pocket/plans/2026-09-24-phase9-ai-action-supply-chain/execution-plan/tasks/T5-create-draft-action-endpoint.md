# Task T5 — Endpoint create draft action

**Phase:** 2
**Depends:** T2, T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: Endpoint create draft action (`POST /admin/recommendation-actions`)

## OBJECTIVE
Expose daftar + create draft action melalui controller/request/route dengan envelope API,
admin RBAC, actor dari session, dan idempotency.

Steps:
1. Write failing feature test: request valid mengembalikan 201 draft.
   Test file: `apps/api/tests/Feature/RecommendationActionControllerTest.php`
   Level: feature/HTTP
   Test intent: Given admin token + `rbac:ai_actions:edit` / When POST payload `draft_order`
   dengan `idempotency_key` / Then 201 `{status:'success',data.status:'draft'}`; Order count
   tidak berubah. GET endpoint mengembalikan daftar dengan pagination/meta.
   Test doubles: factories + actingAs token.
   Expected RED: route/controller belum ada.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RecommendationActionControllerTest`
3. Implement FormRequest + controller + route (auth/stale JWT/RBAC) → PASS → refactor → commit.
4. Write failing tests: non-admin 403; malformed payload 422; replay/conflict maps to
   `idempotent_replay`/409.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec AC-1, AC-9; `AIController.php`, `MeasurementController::storeEvent`, `routes/api.php`.

## WHY THIS APPROACH
Complexity: medium
Justification: HTTP boundary tipis, service T2 menjadi sumber kebenaran, dan middleware
mengikuti route Phase 8.

## SANDWICH CONTEXT
[CRITICAL: endpoint hanya membuat draft; tidak boleh execute]
Files in scope: `apps/api/app/Http/Controllers/RecommendationActionController.php`,
`apps/api/app/Http/Requests/StoreRecommendationActionRequest.php`, `apps/api/routes/api.php`, test.
Available after: T2 + T4.
Architecture rule: envelope `{status,data}`; `auth:api` + `reject.stale_jwt` + `rbac:ai_actions:edit`.

## DELIVERABLE
- `GET/POST /admin/recommendation-actions` dengan validasi, 201, 403, 409/422, pagination.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: actor session; no business mutation; idempotent replay.
Must-not-have: controller menerima `created_by` / bypass RBAC.
Rollback note: route/controller dapat dinonaktifkan lewat `ai_actions.enabled`.

## STOP CONDITIONS
Done when: HTTP happy/error/replay tests PASS.
Uncertain when: pagination contract existing admin-table — ikuti format controller admin terbaru.
Escalate when: middleware key belum didukung registry.
