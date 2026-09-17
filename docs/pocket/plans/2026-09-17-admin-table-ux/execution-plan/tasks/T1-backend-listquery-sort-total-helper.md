# Task T1 — Backend `ListQuery` sort/total helper

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Backend `ListQuery` sort/total helper [prereq]

## OBJECTIVE
Buat helper domain murni `apps/api/app/Support/ListQuery.php` yang memusatkan logika sort-allowlist, order normalisasi, ekspresi nulls-last portabel, dan offset/`meta` — dipakai 6 controller.

Steps:
1. Write failing test for: resolveSort allowlist accept + invalid fallback
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given allowlist `['created_at','name','id']` / When `resolveSort` dipanggil dengan `('name','asc')` / Then return `['name','asc']`; dengan `('__proto__','desc')` / Then return `['created_at','desc']` (default); dengan `('name','DROP')` / Then order default `desc`.
   Exercise through: `ListQuery::resolveSort()` (public static)
   Test doubles: none (pure function)
   Expected RED: `ListQuery` class belum ada → `Error: Class "App\Support\ListQuery" not found`.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
3. Implement `ListQuery::resolveSort` + `normalizeOrder` → verify PASS → refactor while green → commit.

4. Write failing test for: rawOrder nulls-last portabel
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given column `created_at`, order `desc` / When `rawOrder` dipanggil / Then return string `(created_at IS NULL) ASC, created_at DESC, id DESC`; order `asc` / Then `(created_at IS NULL) ASC, created_at ASC, id DESC`.
   Exercise through: `ListQuery::rawOrder()`
   Test doubles: none
   Expected RED: method belum ada → `Error: Call to undefined method`.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
6. Implement `rawOrder` → PASS → refactor → commit.

7. Write failing test for: offset clamp + meta shape
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given `offset(null,15)` / Then `0`; `offset(45,15)` / Then `45`; `offset(-5,15)` / Then `0`. Given `meta(48, ['active'=>32,'inactive'=>16])` / Then `['total'=>48,'summary'=>['active'=>32,'inactive'=>16]]`; `meta(12, [])` / Then `summary` key absent.
   Exercise through: `ListQuery::offset()`, `ListQuery::meta()`
   Test doubles: none
   Expected RED: methods belum ada → `Error: Call to undefined method`.
8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
9. Implement `offset` + `meta` → PASS → refactor → commit.

10. Write failing test for: default caller-supplied (untuk endpoint publik)
   Test file: `apps/api/tests/Unit/ListQueryTest.php`
   Level: unit
   Test intent: Given allowlist `['created_at','name','id']` + default caller `('id','asc')` / When `resolveSort($allowlist, '__proto__', 'desc', 'id', 'asc')` / Then `['id','asc']` (fallback ke default caller, BUKAN `created_at desc`); tanpa argumen default / Then `['created_at','desc']` (default global).
   Exercise through: `ListQuery::resolveSort()`
   Test doubles: none
   Expected RED: param default caller belum didukung → selalu `['created_at','desc']`.
11. Run test — verify FAIL: `cd apps/api && php artisan test --filter=ListQueryTest`
12. Implement default opsional → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Sort allowlist per controller", "Nulls-last dua arah", "Kontrak cursor", "Kontrak total", "Architecture Constraints".

## WHY THIS APPROACH
Complexity: lightweight
Justification: Shared Helper Pattern — 6 controller butuh resolusi sort + total + nulls-last yang identik. Tanpa helper, tiap controller menulis salinan logika sendiri dan tidak pernah digabung.

## SANDWICH CONTEXT
[CRITICAL: sort hanya via allowlist; invalid → fallback `created_at DESC`; jangan sentuh guard/auth/migrasi]
You are implementing backend sort/total helper untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: Option B (Full Paging), offset cursor seragam, top-level `meta.total`.
Files in scope: `apps/api/app/Support/ListQuery.php`, `apps/api/tests/Unit/ListQueryTest.php`
Available after: none (prereq)
Architecture rule: nulls-last portabel `(col IS NULL) ASC, col DIR, id DESC` agar identik di SQLite (test) & PostgreSQL (runtime).
[RESTATE: sort hanya via allowlist; invalid → fallback `created_at DESC`; jangan sentuh guard/auth/migrasi]

## DELIVERABLE
Given allowlist valid + sort valid, When resolveSort dipanggil, Then column+order valid dikembalikan.
Given default caller disuplai, When sort invalid, Then fallback ke default caller (bukan global).
Given sort/order invalid/berbahaya, When resolveSort dipanggil, Then fallback diam `created_at DESC` (no throw).
Given column + order, When rawOrder dipanggil, Then ekspresi nulls-last portabel (id DESC tiebreak).
Given cursor negatif/null, When offset dipanggil, Then clamp ke 0.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Allowlist divalidasi ketat; kolom selain allowlist ditolak → default.
  - `order` hanya `asc`|`desc` (case-insensitive), selain itu `desc`.
  - `rawOrder` memakai kolom yang sudah lolos allowlist (no injection).
Must-not-have:
  - Menyentuh controller/route/schema/migrasi apapun.
Open question risks:
  - SQLite mendukung `(col IS NULL) ASC` → assumed ya (ekspresi boolean). Jika gagal di CI → report NEEDS_CONTEXT.
Rollback note:
  - Hapus `app/Support/ListQuery.php` + test; controller belum memakainya.
Red flags:
  - Mengubah perilaku endpoint yang ada → DONE_WITH_CONCERNS.

## STOP CONDITIONS
Done when: 4 unit test groups PASS (allowlist, rawOrder, offset/meta, default caller), hanya 1 file source + 1 file test baru.
Uncertain when: SQLite menolak ekspresi boolean di ORDER BY.
Escalate when: dibutuhkan perubahan schema/guard.
