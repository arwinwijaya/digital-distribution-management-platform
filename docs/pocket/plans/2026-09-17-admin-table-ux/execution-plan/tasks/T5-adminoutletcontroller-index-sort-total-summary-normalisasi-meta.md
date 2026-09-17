# Task T5 — `AdminOutletController@index` — sort + total + summary + normalisasi meta

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: `AdminOutletController@index` — sort + total + summary + normalisasi meta [depends: T1]

## OBJECTIVE
Upgrade `index` outlet. **Offset cursor + clamp `max(cursor,0)` + `limit+1` SUDAH ada — jangan diubah.** Tambahkan: sort allowlist `[created_at,updated_at,name,id,category,score]`, `meta.total` + `meta.summary {active,inactive}` dengan filter yang sama, dan **normalisasi `meta` ke top-level** (`has_more`/`limit`/`cursor` saat ini nested di dalam object `data` → pindah ke `meta`; `data` tetap array agar `fetchAdminOutlets` yang sudah menangani dua bentuk response tetap jalan).

Steps:
1. Write failing test for: default sort terbaru + total + summary
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent: Given beberapa outlet (created_at bervariasi) / When `GET /admin/outlets?limit=15` / Then `data[0]` = created_at terbaru (id DESC tiebreak), response `meta.total` = jumlah, `meta.summary.active/inactive` konsisten.
   Exercise through: HTTP `getJson('/admin/outlets')` (auth admin)
   Test doubles: `Carbon::setTestNow()` (freeze clock — dipakai juga di `AdminOutletTest`/`PromotionTest`)
   Expected RED: `meta.total`/`meta.summary` belum ada + urutan masih id ASC.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AdminOutletTest`
3. Implement sort allowlist + orderByRaw + total + summary → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + sort by name + nulls-last
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent: Given `GET /admin/outlets?sort=__proto__&order=desc` / Then HTTP 200 + urutan default terbaru (bukan 422/500); `?sort=name&order=asc` / Then urut nama asc; baris null created_at selalu terakhir pada desc & asc.
   Exercise through: `getJson('/admin/outlets?...')`
   Test doubles: none beyond auth
   Expected RED: sort param diabaikan/tidak ada → urutan tidak berubah.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AdminOutletTest`
6. Implement (fallback sudah via T1 resolveSort) + offset cursor + meta normalization → PASS → refactor → commit.

7. Write failing test for: normalisasi `meta` top-level + cursor di luar total
   Test file: `apps/api/tests/Feature/AdminOutletTest.php`
   Level: integration
   Test intent: Given total 48 (limit 15) / When `GET /admin/outlets?limit=15` / Then `meta.has_more`, `meta.limit`, `meta.cursor` ada di **top-level `meta`** (`meta.cursor = 0`); `GET /admin/outlets?cursor=60` / Then HTTP 200, `data: []`, `meta.has_more: false`, `meta.total` tetap 48.
   Exercise through: `getJson('/admin/outlets?limit=15')` + `getJson('/admin/outlets?cursor=60')`
   Test doubles: none beyond auth
   Expected RED: `has_more`/`limit`/`cursor` saat ini hanya nested di dalam object `data` (`data.has_more`), bukan di top-level `meta` → assertion `meta.has_more` gagal.
8. Run test — verify FAIL: `cd apps/api && php artisan test --filter=AdminOutletTest`
9. Implement normalisasi `meta` top-level (`has_more`/`limit`/`cursor`/`total`/`summary`) sambil mempertahankan bentuk `data` array → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Kontrak cursor", "Kontrak total", "Sort allowlist per controller", "Summary strip source", GWT Story1/2/4.

## WHY THIS APPROACH
Complexity: standard
Justification: Outlet adalah kanonik; implementasi pertama menetapkan pola yang direplikasi controller lain.

## SANDWICH CONTEXT
[CRITICAL: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah guard/auth; tidak ada migrasi]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Design decision: offset cursor, top-level meta.total + meta.summary.
Files in scope: `apps/api/app/Http/Controllers/AdminOutletController.php`, `apps/api/tests/Feature/AdminOutletTest.php`
Available after: T1 (ListQuery)
Architecture rule: `total` = COUNT dgn filter sama; `summary` = query agregat terpisah.
[RESTATE: sort via ListQuery allowlist; invalid → 200 fallback; jangan ubah guard/auth; tidak ada migrasi]

## DELIVERABLE
Given GET /admin/outlets tanpa sort, When merespons, Then urutan created_at DESC nulls-last lalu id DESC + meta.total + summary.
Given GET /admin/outlets?sort=__proto__, When merespons, Then HTTP 200 fallback default (bukan 422/500).
Given GET /admin/outlets?category=warung, When merespons, Then meta.total hanya outlet kategori tsb.
Given GET /admin/outlets?cursor=60 (di luar total), When merespons, Then data:[] + meta.has_more:false + meta.total tetap (no crash).
Given GET /admin/outlets (halaman 1), When merespons, Then meta.has_more/meta.limit/meta.cursor ada di top-level `meta` (bukan nested di dalam `data`).

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `has_more` tetap (limit+1); offset cursor + clamp `max(cursor,0)` yang sudah ada TIDAK diubah.
  - `meta.total` top-level (normalisasi dari nested `data`).
  - Bentuk `data` tetap array (kompatibel `fetchAdminOutlets`).
Must-not-have:
  - Menyentuh Outlet model fillable/schema.
  - Mengubah perilaku endpoint non-list (show/update).
Open question risks:
  - Outlet `score`/`category` kolom real → assumed (allowlist).
Rollback note:
  - Revert controller; frontend fallback estimasi bila total hilang.
Red flags:
  - orderBy kolom di luar allowlist → STOP.

## STOP CONDITIONS
Done when: 3 integration test groups PASS (default sort+total+summary, sort fallback+name+nulls, normalisasi meta+beyond-total); total konsisten dgn filter.
Uncertain when: `category` bukan kolom melainkan computed → NEEDS_CONTEXT.
Escalate when: perlu migrasi untuk index sort.
