# Task T17 — Dummy fixtures + NavItem + RBAC page wiring

**Phase:** 5
**Depends:** T13, T14, T15, T16
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 17: Dummy fixtures + NavItem + RBAC page wiring

## OBJECTIVE
Lengkapi parity dummy mode untuk seluruh permukaan Phase 8 (roster, check-in, PoD,
tracking) dan daftarkan menu baru (`driver_roster`, `field_ops`) di `NavItem` frontend
sehingga Sidebar/RBAC page menampilkannya sesuai role.

Steps:
1. Write failing test for: dummy roster + tracking fixture.
   Test file: `apps/web/src/dummy/field-ops.test.ts`
   Level: unit
   Test intent: Given dummy mode ON / When `listDrivers()` / Then mengembalikan fixture
   deterministik tanpa fetch; When `getTrack(id)` / Then fixture posisi ada.
   Exercise through: dummy api modules + store.
   Test doubles: dummy store.
   Expected RED: fixture belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/field-ops.test.ts`
3. Implement fixture `fieldOps` di `dummy/aggregates.ts` + guard di modul api → PASS → commit.

4. Write failing test for: NavItem + RBAC visibility.
   Test intent: Given matrix admin / When render Sidebar / Then "Operasi Lapangan" &
   "Roster Driver" tampil; Given role driver dengan field_ops read / Then "Operasi
   Lapangan" tampil, "Roster Driver" tidak.
   Test file: `apps/web/src/components/Sidebar.test.tsx` (EXISTING — perluas; jangan hapus test matrix yang sudah ada).
5. Run test — verify FAIL → tambah NavItem + key → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-7, AC-8; `apps/web/src/dummy/*`, `Sidebar.tsx`, `NavItem`, `MenuDefinition::CATALOG` (T4).

## WHY THIS APPROACH
Complexity: medium
Justification: parity dummy adalah kontrak repo; menu baru butuh NavItem agar Sidebar
matrix-driven menampilkannya.

## SANDWICH CONTEXT
[CRITICAL: dummy ON = zero network; key NavItem harus sama dengan CATALOG (`driver_roster`, `field_ops`)]
Files in scope: `apps/web/src/dummy/aggregates.ts`, `apps/web/src/dummy/index.ts`, `apps/web/src/components/Sidebar.tsx` (+ NavItem list), `apps/web/src/components/Sidebar.test.tsx` (perluas), test.
Available after: T13–T16.
Architecture rule: fixture deterministik (seeded RNG); `platform_owner` setara admin untuk `adminOnly`.

## DELIVERABLE
- Fixture: driver roster (≥8), tracking pings, visit dengan check-in, PoD sample.
- NavItem `field_ops` + `driver_roster` (grup & adminOnly sesuai CATALOG).
- Semua modul api Phase 8 memakai `withDummyRead`.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Zero network saat dummy ON (diverifikasi test).
  - Tidak ada state kosong saat ON.
  - Key menu sinkron backend↔frontend.
Must-not-have:
  - Duplikasi logika RBAC di frontend (pakai map dari store).
Open question risks:
  - Label menu final (id-ID) — samakan dengan CATALOG T4.
Rollback note:
  - Hapus fixture + NavItem entries.

## STOP CONDITIONS
Done when: dummy test + Sidebar visibility test PASS.
Escalate when: key CATALOG T4 berbeda dari NavItem.
