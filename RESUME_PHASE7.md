# Resume Phase 7 — MVP Completion & Core Operations

> Generated: 2026-09-16. Copy-paste commands untuk melanjutkan setelah stop.

## Status Saat Stop

| Task | Status | Catatan |
|------|--------|---------|
| T1 Database Migrations | **DONE** | commit `1858ff4`, logged |
| T2 Role Management (F1) | **17/17 PASS** tapi **belum commit+log** | worktree `.worktree/T2` branch `task/T2` — ada 8 modified + 19 untracked |
| T3 Outlet Profile (F2) | **22/22 PASS** + committed `e4e4aa6` di `task/T3` | belum merge ke `main`, belum log DONE |
| T4 Product Prices (F3) | **7/7 FAIL** (404 — route belum ter-register) + worktree terkontaminasi file T3 | branch `task/T4` dirty, perlu reset & fix |
| T5-T9 | WAITING | Phase 2, depends on T2-T4 |

---

## 1. Cek Status (wajib pertama kali buka sesi baru)

```bash
# dari root repo: D:/Development/amal/digital-distribution-management-platform
npx -y pocketto-pi log status "docs/pocket/plans/2026-09-16-phase7-mvp-completion" --json --contract 2
git worktree list
git log --oneline -5
git -C .worktree/T2 status --short
git -C .worktree/T3 status --short
git -C .worktree/T4 status --short
```

`log init` idempotent — kalau ragu jalankan lagi:
```bash
npx -y pocketto-pi log init "docs/pocket/plans/2026-09-16-phase7-mvp-completion" --json --contract 2
```

---

## 2. Selesaikan T2 (commit + audit + log)

```bash
# commit T2
cd .worktree/T2
git add apps/api/app/Policies/ apps/api/app/Services/UserRoleService.php apps/api/app/Http/Controllers/UserRoleController.php apps/api/app/Http/Requests/AssignRoleRequest.php apps/api/app/Http/Requests/UpdateProfileRequest.php apps/api/app/Models/User.php apps/api/app/Services/FinanceAuthorizationService.php apps/api/app/Http/Controllers/OutletController.php apps/api/app/Http/Controllers/AuthController.php apps/api/app/Http/Kernel.php apps/api/app/Providers/AppServiceProvider.php apps/api/app/Http/Middleware/RejectStaleJwt.php apps/api/database/migrations/2026_09_16_000003_add_jwt_version_to_users_table.php apps/api/routes/api.php apps/api/tests/Feature/RoleManagementTest.php

# hati-hati: file T3/T4 ikut ke-detect sebagai untracked di T2 — JANGAN add All (-A), add selektif seperti di atas
git commit -m "feat(auth): add platform_owner role, UserPolicy, UserRoleService, user listing, profile update, and fix updatePaymentTerms admin assertion"
T2_SHA=$(git rev-parse HEAD)
echo $T2_SHA

# verifikasi lagi
cd apps/api && php artisan test --filter=RoleManagementTest 2>&1 | tail -5

# kembali ke main dan merge
cd ../..
git checkout main
git merge --no-ff task/T2 -m "merge(task/T2): role management F1"

# audit (spawn subagent read-only) — opsional tapi disarankan
# lalu log DONE
npx -y pocketto-pi log update "docs/pocket/plans/2026-09-16-phase7-mvp-completion" "execution-plan/phase-1.md" DONE --task T2 --sha $T2_SHA --json --contract 2
```

---

## 3. Selesaikan T3 (merge + log — sudah committed)

```bash
git log task/T3 --oneline -3
# seharusnya ada e4e4aa6 feat(outlets): ...

# cek tidak ada file T3 yang konflik dengan T2
git merge --no-ff task/T3 -m "merge(task/T3): outlet profile & lifecycle F2"

# jika konflik di routes/api.php — resolve manual, keep both blocks
# verifikasi
cd apps/api && php artisan test --filter=AdminOutletTest 2>&1 | tail -5
cd apps/api && php artisan test --filter=OutletScoringTest 2>&1 | tail -5

T3_SHA=$(git rev-parse HEAD)
npx -y pocketto-pi log update "docs/pocket/plans/2026-09-16-phase7-mvp-completion" "execution-plan/phase-1.md" DONE --task T3 --sha $T3_SHA --json --contract 2
```

---

## 4. Fix & Selesaikan T4 (worktree terkontaminasi — reset dulu)

Root cause T4: `routes/api.php` tidak ada route admin/products + worktree T4 ikut mengandung file T3 (`AdminOutletController`, dll).

```bash
# bersihkan worktree T4 dan buat ulang dari main terbaru (yang sudah merge T2+T3)
git worktree remove .worktree/T4 --force
git branch -D task/T4
PARENT=$(git rev-parse HEAD)
git worktree add .worktree/T4 -b task/T4 $PARENT
git worktree list

# dispatch ulang T4 — baca packet dulu
cat docs/pocket/plans/2026-09-16-phase7-mvp-completion/execution-plan/tasks/T4-product-price-management-f3.md

# lalu re-run subagent T4 (atau manual implement):
# File yang harus ada di T4:
# - apps/api/app/Http/Controllers/AdminProductController.php
# - apps/api/app/Http/Requests/UpdateProductPriceRequest.php
# - apps/api/app/Services/ProductPriceService.php
# - apps/api/app/Models/ProductPriceHistory.php
# - apps/api/routes/api.php (tambahkan PATCH /admin/products/{id} + GET /admin/products/{id}/prices)
# - apps/api/tests/Feature/AdminProductPriceTest.php
# - optional fix: apps/api/app/Services/InvoiceService.php (cancel-New without invoice)

# setelah implement, verifikasi
cd .worktree/T4/apps/api && php artisan test --filter=AdminProductPriceTest 2>&1 | tail -10

# commit
cd ../..
git add apps/api/app/Http/Controllers/AdminProductController.php apps/api/app/Http/Requests/UpdateProductPriceRequest.php apps/api/app/Services/ProductPriceService.php apps/api/app/Models/ProductPriceHistory.php apps/api/routes/api.php apps/api/tests/Feature/AdminProductPriceTest.php
git commit -m "feat(products): add admin price management with history logging and cancel-New order fix"
T4_SHA=$(git rev-parse HEAD)

cd ../..
git checkout main
git merge --no-ff task/T4 -m "merge(task/T4): product price management F3"
npx -y pocketto-pi log update "docs/pocket/plans/2026-09-16-phase7-mvp-completion" "execution-plan/phase-1.md" DONE --task T4 --sha $T4_SHA --json --contract 2

# Phase 1 selesai — cek
npx -y pocketto-pi log status "docs/pocket/plans/2026-09-16-phase7-mvp-completion" --json --contract 2
```

---

## 5. Lanjut Phase 2 (T5 → T6,T7,T8 → T9)

Urutan sesuai `execution-plan/index.md`: `T1→T2,T3,T4(PARALLEL)→T5→T6,T7,T8(PARALLEL)→T9`

```bash
# T5 Promotion Management (F4) — depends T3,T4 — buat worktree dan dispatch
git worktree add .worktree/T5 -b task/T5 $(git rev-parse HEAD)
# baca packet:
cat docs/pocket/plans/2026-09-16-phase7-mvp-completion/execution-plan/tasks/T5-promotion-management-f4.md
# dispatch subagent dengan prompt dari file tersebut (worktree .worktree/T5)

# setelah T5 DONE + merge + log:
# parallel T6 (Sales Orders), T7 (WhatsApp Broadcast), T8 (Frontend Admin)
git worktree add .worktree/T6 -b task/T6 $(git rev-parse HEAD)
git worktree add .worktree/T7 -b task/T7 $(git rev-parse HEAD)
git worktree add .worktree/T8 -b task/T8 $(git rev-parse HEAD)

# terakhir T9 (Frontend Sales) — depends T6,T7
git worktree add .worktree/T9 -b task/T9 $(git rev-parse HEAD)
```

Atau cukup beri instruksi ke agent:
> "lanjut phase 7 dari T2" / "resume pocket-development Phase 1 T2-T4"

Agent akan baca `RESUME_PHASE7.md` ini + `log.json` dan melanjutkan dispatch sesuai packet.

---

## 6. Cleanup Setelah Semua DONE

```bash
git worktree list
git worktree remove .worktree/T2 --force  # setelah merge
git worktree remove .worktree/T3 --force
git worktree remove .worktree/T4 --force
git branch -d task/T2 task/T3 task/T4  # optional
npx -y pocketto-pi log status "docs/pocket/plans/2026-09-16-phase7-mvp-completion" --json --contract 2
```

---

## Quick Resume (satu baris untuk agent baru)

Paste ini di sesi baru:

```
Lanjut Phase 7 MVP Completion. Baca RESUME_PHASE7.md dan docs/pocket/plans/2026-09-16-phase7-mvp-completion/log.json.
Status: T1 DONE (1858ff4), T2 17/17 PASS belum commit, T3 22/22 PASS committed e4e4aa6 belum merge, T4 FAIL perlu reset.
Kerjakan sesuai urutan di RESUME_PHASE7.md section 2-4, lalu lanjut Phase 2 T5-T9.
```

## Referensi

- Spec: `docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md`
- Plan: `docs/pocket/plans/2026-09-16-phase7-mvp-completion/execution-plan.md` (sha256 `c97358359a83f4d4f400a0ffa34f5f1a7d1cef523576ba8e17917bfcdb2df6e3`)
- Packets: `execution-plan/tasks/T*.md`
- Log: `docs/pocket/plans/2026-09-16-phase7-mvp-completion/log.json`
