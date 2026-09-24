# Task T4 — RBAC catalog + seeder (`ai_actions`, `supply_chain`)

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: RBAC catalog + seeder (`ai_actions`, `supply_chain`)

## OBJECTIVE
Tambahkan menu `ai_actions` dan `supply_chain` ke `MenuDefinition::CATALOG` dan
`RbacMatrixSeeder` (default matrix) tanpa mengubah menu lama.

Steps:
1. Write failing test: catalog + seeder memuat menu baru.
   Test file: `apps/api/tests/Feature/RbacAiActionsCatalogTest.php`
   Level: feature
   Test intent: Given `MenuDefinition::CATALOG` / When di-inspect / Then berisi
   `ai_actions` dan `supply_chain`; When `db:seed` `RbacMatrixSeeder` / Then
   `role_menu_access` memuat `platform_owner=edit`, `admin=edit` untuk keduanya, dan role
   lain `none` (tidak ada baris); idempotent saat dijalankan ulang.
   Exercise through: CATALOG + seeder + `RbacMatrixService`.
   Test doubles: —
   Expected RED: menu belum ada → assert gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacAiActionsCatalogTest`
3. Implement entri CATALOG + MATRIX → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 9 — AC-9; Phase 8 T4 pola.
`apps/api/app/Models/MenuDefinition.php`, `apps/api/database/seeders/RbacMatrixSeeder.php`.

## WHY THIS APPROACH
Complexity: low
Justification: menu adalah satu sumber kebenaran bersama FE; menambah entri menjaga
konsistensi RBAC tanpa menyentuh menu lama.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah urutan/label menu lama; tambah entri baru saja]
Files in scope: `apps/api/app/Models/MenuDefinition.php`, `apps/api/database/seeders/RbacMatrixSeeder.php`,
`apps/api/tests/Feature/RbacAiActionsCatalogTest.php`.
Available after: T1.
Architecture rule: `CATALOG` = sumber kebenaran bersama `role_menu_access.menu_key` + `NavItem.key`.

## DELIVERABLE
- 2 menu baru terdaftar (label Indonesia + group) + default matrix `platform_owner/admin=edit`.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Idempotent seeder (`updateOrCreate`).
  - `platform_owner` setara admin.
Must-not-have:
  - Mengubah jumlah/urutan menu lama yang memecah `NavItem`.
Open question risks:
  - Penamaan label menu (usulan: "Aksi AI", "Rantai Pasok").
Rollback note:
  - Hapus entri menu + baris `role_menu_access` terkait.

## STOP CONDITIONS
Done when: test catalog + seeder PASS.
Uncertain when: nama menu bentrok dengan yang ada.
Escalate when: perlu menu per-subfitur (pecah lebih lanjut).
