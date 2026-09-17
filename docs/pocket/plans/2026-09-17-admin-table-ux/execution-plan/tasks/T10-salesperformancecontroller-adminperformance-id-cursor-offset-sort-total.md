# Task T10 — `SalesPerformanceController@adminPerformance` — id-cursor → offset + sort + total

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 10: `SalesPerformanceController@adminPerformance` — id-cursor → offset + sort + total [depends: T1]

## OBJECTIVE
Konversi `adminPerformance` dari id-based cursor ke **offset**; sort allowlist server-side hanya `[name,id]`; `meta.total` top-level (jumlah sales user). `achievement` tetap dihitung via `performanceService` (sort achievement = client-side). Pertahankan guard `isAdminOrOwner` 403.

Steps:
1. Write failing test for: offset cursor + total + default order
   Test file: `apps/api/tests/Feature/SalesPerformanceTest.php`
   Level: integration
   Test intent: Given sales user / When `GET /admin/sales/performance?limit=100&cursor=0` / Then `meta.total` = jumlah sales, cursor offset bekerja (bukan where id>); non-admin / Then 403.
   Exercise through: `getJson('/admin/sales/performance')`
   Test doubles: none beyond auth; `performanceService` dipanggil per-user (existing)
   Expected RED: `meta.total` absent + cursor id-based (`next_cursor` id).
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=SalesPerformanceTest`
3. Implement offset + total (keep name ASC/id default order) → PASS → refactor → commit.

4. Write failing test for: sort name + invalid fallback
   Test file: `apps/api/tests/Feature/SalesPerformanceTest.php`
   Level: integration
   Test intent: Given `?sort=name&order=asc` / Then urut nama asc; `?sort=achievement` / Then 200 fallback default (achievement BUKAN allowlist server); `?sort=__proto__` / Then 200 fallback.
   Exercise through: `getJson('/admin/sales/performance?...')`
   Test doubles: none
   Expected RED: sort diabaikan.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=SalesPerformanceTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller" (sales-performance), Implementation Notes `sales-performance`.

## WHY THIS APPROACH
Complexity: standard
Justification: `achievement` derived → sort server hanya kolom DB; konversi cursor membutuhkan perubahan query utama tanpa mengganggu perhitungan performance.

## SANDWICH CONTEXT
[CRITICAL: sort server hanya name/id (achievement BUKAN kolom DB); jangan ubah isAdminOrOwner guard/formatAmounts]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/SalesPerformanceController.php`, `apps/api/tests/Feature/SalesPerformanceTest.php`
Available after: T1
Architecture rule: offset cursor; `calculatePerformance` dipanggil per baris hasil query.
[RESTATE: sort server hanya name/id (achievement BUKAN kolom DB); jangan ubah isAdminOrOwner guard/formatAmounts]

## DELIVERABLE
Given GET /admin/sales/performance?cursor=0, When merespons, Then offset + meta.total = jumlah sales.
Given GET /admin/sales/performance?sort=name, When merespons, Then urut name (asc/desc).
Given GET /admin/sales/performance?sort=achievement, When merespons, Then 200 fallback default (tidak 422/500).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `formatAmounts` + `calculatePerformance` tidak diubah.
  - `next_cursor` id dihapus → offset.
Must-not-have:
  - Sort achievement di backend (harus client-side).
Rollback note:
  - Revert controller; frontend fallback estimasi.
Red flags:
  - Menambah `achievement` ke allowlist server → STOP (bukan kolom DB).

## STOP CONDITIONS
Done when: 2 integration test groups PASS; guard 403 tetap.
Uncertain when: period resolve memengaruhi jumlah total.
Escalate when: butuh agregasi performance server-side.
