# Task T16 — Fixture dummy + `NavItem` + wiring RBAC

**Phase:** 5
**Depends:** T13, T14, T15
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 16: Parity dummy + NavItem + RBAC wiring

## OBJECTIVE
Tambahkan fixture dummy untuk semua API/halaman baru, pasang `withDummyRead`,
tambahkan `NavItem`, dan pastikan menu tampil/hilang sesuai RBAC.

Steps:
1. Write failing dummy tests.
   Test file: `apps/web/src/dummy/__tests__/phase9.test.ts`
   Level: unit/integration (jest)
   Test intent: Given dummy mode ON / When client API baru dipanggil / Then zero network
   dan fixture AI actions/replenishment/experiments returned; keys cocok dengan field nyata.
   Test doubles: dummy store/seed.
   Expected RED: fixtures/keys belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/dummy/__tests__/phase9.test.ts`
3. Implement fixtures + withDummyRead + seed → PASS → refactor → commit.
4. Write failing test menu: `ai_actions`/`supply_chain` NavItem tampil untuk admin,
   hidden untuk outlet/sales; role guard aktif.
5. Run test — verify FAIL → implement Sidebar/Nav + seeder check → PASS → commit.

## REFERENCES LOADED
Spec AC-9; Phase 8 T17 pola; `apps/web/src/dummy/*`; `Sidebar.tsx`; `RbacMatrixSeeder`.

## WHY THIS APPROACH
Complexity: medium
Justification: parity dummy + RBAC menjaga demo/offline dan keamanan navigasi.

## SANDWICH CONTEXT
[CRITICAL: zero network saat dummy ON; platform_owner setara admin]
Files in scope: `apps/web/src/dummy/*`, API clients, `Sidebar.tsx`/`Topbar.tsx`,
menu keys, test.
Available after: T13 + T14 + T15.
Architecture rule: `withDummyRead` untuk semua modul baru; seeder `updateOrCreate`.

## DELIVERABLE
- Fixtures + wiring + menu visibility + seeder defaults verified.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: no empty states dummy ON; no menu leak across roles.
Must-not-have: real network in dummy; hardcoded roles in page bypassing middleware.
Rollback note: remove NavItem + fixtures.

## STOP CONDITIONS
Done when: dummy + menu tests PASS for all roles.
Escalate when: menu registry FE tidak sinkron dengan CATALOG.
