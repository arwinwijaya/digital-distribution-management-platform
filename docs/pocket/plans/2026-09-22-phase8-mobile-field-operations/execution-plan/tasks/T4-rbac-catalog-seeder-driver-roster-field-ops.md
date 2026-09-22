# Task T4 — RBAC catalog + seeder default (`driver_roster`, `field_ops`)

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: RBAC catalog + seeder default (`driver_roster`, `field_ops`)

## OBJECTIVE
Tambahkan 2 menu baru ke `MenuDefinition::CATALOG` (`driver_roster`, `field_ops`) dan
matriks akses default pada seeder: admin `edit`, platform_owner `edit`, sales `read`
(field_ops), driver `read` (field_ops), finance/outlet `none`.

Steps:
1. Write failing test for: catalog + default matrix.
   Test file: `apps/api/tests/Feature/RbacFieldOpsCatalogTest.php`
   Level: feature
   Test intent: Given seeded RBAC / When `MenuDefinition::keys()` / Then memuat
   `driver_roster` + `field_ops`; When `GET /admin/rbac/matrix` sebagai admin / Then
   kedua key ada; When map role `driver` / Then `field_ops = read`, `driver_roster = none`.
   Exercise through: seeder + `RbacMatrixService`.
   Test doubles: none.
   Expected RED: key belum ada → assertion gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RbacFieldOpsCatalogTest`
3. Tambah CATALOG + seeder default → PASS → refactor → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-8, Architecture Constraints (RBAC), `MenuDefinition::CATALOG` (19 menu saat ini).

## WHY THIS APPROACH
Complexity: lightweight
Justification: menu baru butuh entri single-source-of-truth CATALOG + seeder agar
endpoint RBAC merender map penuh di fresh DB.

## SANDWICH CONTEXT
[CRITICAL: CATALOG adalah single source of truth; jangan ubah 19 menu lama; seeder idempotent]
Files in scope: `apps/api/app/Models/MenuDefinition.php`, `apps/api/database/seeders/*Rbac*`, `apps/api/tests/Feature/RbacFieldOpsCatalogTest.php`.
Available after: T1.
Architecture rule: `sort` melanjutkan urutan (20, 21); grup `operasional` (field_ops) & `admin` (driver_roster).

## DELIVERABLE
- CATALOG +2 entri: `field_ops` (label "Operasi Lapangan", grup operasional, sort 20), `driver_roster` (label "Roster Driver", grup admin, sort 21).
- Seeder default: admin/platform_owner `edit`; sales/driver `read` field_ops; driver_roster admin-only.
- Idempotent re-seed.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 19 → 21 menu; key lama tidak berubah.
  - Seeder idempotent (aman dijalankan ulang).
Must-not-have:
  - Mengubah level menu lama.
Open question risks:
  - Apakah sales butuh `driver_roster:read`? Asumsi: tidak.
Rollback note:
  - Hapus 2 entri CATALOG + baris seeder; hapus baris `role_menu_access` terkait.

## STOP CONDITIONS
Done when: test catalog + matrix PASS; re-seed idempotent.
Escalate when: butuh menu baru lain (mis. `tracking`).
