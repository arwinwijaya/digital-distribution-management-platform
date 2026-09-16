# Resume Phase 7 — MVP Completion & Core Operations

> Generated: 2026-09-16 (updated). Copy-paste this prompt ke sesi baru pi untuk melanjutkan.

## Status Saat Stop

| Task | Status | Catatan |
|------|--------|---------|
| T1 Database Migrations | **DONE** | logged |
| T2 Role Management (F1) | **DONE** | merged + logged |
| T3 Outlet Profile (F2) | **DONE** | merged + logged |
| T4 Product Prices (F3) | **DONE** | merged + logged |
| T5 Promotion Mgmt (F4) | **DONE** | merged `00659bb` + logged |
| T6 Sales Orders (F5) | **DONE tests** | 12/12 SalesOrder, 20/22 SalesPerformance (2 FAIL — string formatting) |
| T7 Promo Broadcast (F6) | **DONE tests** | 8/8 PASS in worktree (retry bug fixed) |
| T8 Frontend Admin | **DONE** | committed `336682c` in worktree |
| T9 Frontend Sales | **BLOCKED** | depends T6, T7 |

---

## Current Git State

```
main:    00659bb (merge T5)
T6:      .worktree/T6  branch task/T6  at 00659bb, UNCOMMITTED (all work done)
T7:      .worktree/T7  branch task/T7  at 00659bb, UNCOMMITTED (all work done)
T8:      .worktree/T8  branch task/T8  at 336682c, COMMITTED
T9:      not created yet
```

---

## 1. Verifikasi Status

```bash
cd D:/Development/amal/digital-distribution-management-platform
git log --oneline -3 main
git worktree list
# cok uncommitted files
git -C .worktree/T6 status --short
git -C .worktree/T7 status --short
git -C .worktree/T8 status --short
```

---

## 2. Finish T6 — Fix 2 Failing Tests

Di `.worktree/T6/apps/api`, tests/Feature/SalesPerformanceTest.php punya 2 FAIL:

```
Failed asserting that 20000000 is identical to '20000000.00'.
  at tests/Feature/SalesPerformanceTest.php:436
  and line 437
```

**Root cause:** `SalesPerformanceService::calculatePerformance()` returns `target` and `achievement` as numeric float, tapi test expect string `'20000000.00'` (karena `decimal:2` column).

**Fix:**
```bash
cd .worktree/T6/apps/api

# buka SalesPerformanceService.php, di method calculatePerformance()
# ubah return value:
#   'target'       => (string) number_format((float)$target->target_amount, 2, '.', ''),
#   'achievement'  => (string) number_format((float)$achievement, 2, '.', ''),
#   'percentage'   => (float) number_format($percentage, 2, '.', ''),
```

**Cara lain (lebih simple): ubah test expectations menjadi numerik:**
```php
// SalesPerformanceTest.php - ganti baris 436-437:
->assertJsonPath('data.target', 20000000)    // bukan '20000000.00'
->assertJsonPath('data.achievement', 8000000) // bukan '8000000.00'
```

**Tapi:** karena endpoint lain (SalesTarget CRUD) return string `'20000000.00'` dari DB, lebih konsisten untuk CAST di service. Pilih approach yang paling konsisten dengan test expectations lain.

```bash
# verifikasi
cd .worktree/T6/apps/api
php artisan test --filter=SalesOrderTest       # harus 12/12
php artisan test --filter=SalesPerformanceTest  # harus 22/22
# regression
php artisan test --filter="RoleManagementTest|AdminOutletTest|OutletScoringTest|AdminProductPriceTest|PromotionTest|SalesOrderTest|SalesPerformanceTest" # harus 65+
```

---

## 3. Commit T6

```bash
cd .worktree/T6
git add apps/api/app/Http/Controllers/SalesOrderController.php apps/api/app/Http/Requests/StoreSalesOrderRequest.php apps/api/app/Http/Controllers/SalesTargetController.php apps/api/app/Http/Controllers/SalesPerformanceController.php apps/api/app/Http/Requests/StoreSalesTargetRequest.php apps/api/app/Http/Requests/UpdateSalesTargetRequest.php apps/api/app/Models/SalesTarget.php apps/api/app/Services/SalesPerformanceService.php apps/api/database/migrations/2026_09_16_000008_create_sales_targets_table.php apps/api/routes/api.php apps/api/tests/Feature/SalesOrderTest.php apps/api/tests/Feature/SalesPerformanceTest.php apps/api/app/Models/Order.php apps/api/app/Services/OrderCreationService.php
git commit -m "feat(sales): add sales order collection with territory binding, sales targets, and performance views"
T6_SHA=$(git rev-parse HEAD)
echo "T6 SHA: $T6_SHA"
```

---

## 4. Commit T7

```bash
cd .worktree/T7
# cek dulu test pass
cd apps/api && php artisan test --filter=PromotionBroadcastTest && cd ../..
git add apps/api/app/Http/Controllers/PromotionBroadcastController.php apps/api/app/Http/Requests/StorePromotionRequest.php apps/api/app/Models/Promotion.php apps/api/app/Models/WhatsAppMessage.php apps/api/app/Services/WhatsAppOutboundService.php apps/api/routes/api.php apps/api/tests/Feature/PromotionBroadcastTest.php
git commit -m "feat(broadcast): add WhatsApp promotion broadcast with per-outlet targeting, idempotency, and retry"
T7_SHA=$(git rev-parse HEAD)
echo "T7 SHA: $T7_SHA"
```

---

## 5. Merge T6 + T7 + T8 ke Main

```bash
cd D:/Development/amal/digital-distribution-management-platform

# T6
git checkout main
git merge --no-ff task/T6 -m "merge(task/T6): sales order collection & performance F5"

# T7
git merge --no-ff task/T7 -m "merge(task/T7): WhatsApp promotion broadcast F6"
# jika conflict di routes/api.php atau Promotion.php — resolve manual

# T8
git merge --no-ff task/T8 -m "merge(task/T8): frontend admin pages"
# kemungkinan conflict di routes/api.php atau Models/Promotion.php

# verifikasi regression
cd apps/api && php artisan test --filter="RoleManagementTest|AdminOutletTest|OutletScoringTest|AdminProductPriceTest|PromotionTest|SalesOrderTest|SalesPerformanceTest|PromotionBroadcastTest"
```

---

## 6. Log DONE T6, T7, T8

```bash
cd D:/Development/amal/digital-distribution-management-platform

npx -y pocketto-pi log update "docs/pocket/plans/2026-09-16-phase7-mvp-completion" "execution-plan/phase-2.md" DONE --task T6 --sha $T6_SHA --json --contract 2
npx -y pocketto-pi log update "docs/pocket/plans/2026-09-16-phase7-mvp-completion" "execution-plan/phase-2.md" DONE --task T7 --sha $T7_SHA --json --contract 2
npx -y pocketto-pi log update "docs/pocket/plans/2026-09-16-phase7-mvp-completion" "execution-plan/phase-2.md" DONE --task T8 --sha $T8_SHA --json --contract 2
```

---

## 7. T9 — Frontend Sales Pages

```bash
cd D:/Development/amal/digital-distribution-management-platform

# buat worktree
MAIN_SHA=$(git rev-parse main)
git worktree add .worktree/T9 -b task/T9 $MAIN_SHA
cp .worktree/T8/apps/api/.env .worktree/T9/apps/api/.env 2>/dev/null || true
ln -sfn "$PWD/.worktree/T8/apps/api/vendor" "$PWD/.worktree/T9/apps/api/vendor" 2>/dev/null || true
ln -sfn "$PWD/.worktree/T8/apps/api/node_modules" "$PWD/.worktree/T9/apps/api/node_modules" 2>/dev/null || true

# baca packet
cat docs/pocket/plans/2026-09-16-phase7-mvp-completion/execution-plan/tasks/T9-frontend-sales-pages.md

# dispatch subagent untuk T9
```

---

## 7b. Log Close

```bash
npx -y pocketto-pi log close "docs/pocket/plans/2026-09-16-phase7-mvp-completion" --json --contract 2
```

---

## Key Architecture Reminders

- **Decimal columns:** `decimal:2` cast returns STRING e.g. `'15000.00'` — assert with `assertJsonPath('data.field', '15000.00')` bukan float
- **Order create:** returns HTTP 201, not 200
- **Auth:** `FinanceAuthorizationService::assertAdminOrOwner()` for admin checks
- **JWT:** `app(AuthService::class)->createToken($user)` returns `['token' => '...', 'expires_in' => ...]`
- **Pagination:** limit+1 technique: `$rows = $query->limit($limit + 1)->get(); $hasMore = $rows->count() > $limit`
- **Routes:** register inside `Route::middleware('reject.stale_jwt')->group(...)` block in `routes/api.php`
- **Idempotency keys:** MUST be outlet-scoped: `hash('sha256', json_encode(['outlet_id' => ..., 'request_identity' => ...]))`
- **T7 critical lesson:** Laravel route objects cache controller instances. Mid-test `bindClient()` swapping won't work on subsequent HTTP calls. Use single mutable double instead.
- **Pre-existing test failure:** 237 failures in full suite due to "already active transaction" PDOException — NOT caused by Phase 7 changes

---

## Copy-Paste This Entire File as Your Next Prompt

```
Baca file RESUME_PHASE7.md di root repo dan ikuti instruksi di dalamnya secara sequential.
```
