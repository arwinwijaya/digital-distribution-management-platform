# Task T7 — `PromotionController@index` — id-cursor → offset + sort + total + summary

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: `PromotionController@index` — id-cursor → offset + sort + total + summary [depends: T1]

## OBJECTIVE
Konversi `index` promosi dari id-based cursor (`where id > cursor`, `next_cursor`) ke **offset cursor**; sort allowlist `[created_at,updated_at,start_date,end_date,id]`; `meta.total` top-level + `meta.summary {total,active,scheduled,ended}`; pertahankan `assertAdminOrOwner`.

Steps:
1. Write failing test for: offset cursor + default sort + total + summary
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent: Given promosi (start/end bervariasi) / When `GET /admin/promotions?limit=15&cursor=0` / Then data urut created_at DESC (id DESC tiebreak), `meta.total` = jumlah, `meta.summary` berisi active/scheduled/ended konsisten; `cursor=15` / Then halaman kedua (bukan where id>15).
   Exercise through: `getJson('/admin/promotions?...')`
   Test doubles: `Carbon::setTestNow()` untuk freeze `now` agar klasifikasi active/scheduled/ended deterministik (restore di teardown); none beyond auth
   Expected RED: cursor masih id-based (`next_cursor` id) + `meta.total`/`summary` absent.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=PromotionTest`
3. Implement offset + sort + total + summary → PASS → refactor → commit.

4. Write failing test for: sort invalid fallback + sort start_date
   Test file: `apps/api/tests/Feature/PromotionTest.php`
   Level: integration
   Test intent: Given `?sort=start_date&order=asc` / Then urut start_date asc nulls-last; `?sort=__proto__` / Then 200 fallback default.
   Exercise through: `getJson('/admin/promotions?...')`
   Test doubles: none
   Expected RED: sort diabaikan.
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=PromotionTest`
6. Implement → PASS → refactor → commit.

## REFERENCES LOADED
docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md — rule: "Kontrak cursor" (konversi id-based), "Sort allowlist per controller", "Summary strip source".

## WHY THIS APPROACH
Complexity: standard
Justification: Perubahan kontrak cursor (id→offset) + summary berbasis waktu; satu-satunya controller dengan 4-state summary.

## SANDWICH CONTEXT
[CRITICAL: konversi cursor id→offset; sort via allowlist; jangan ubah assertAdminOrOwner; summary {total,active,scheduled,ended}]
You are implementing backend list upgrade untuk fitur admin table readability.
Spec: docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md
Files in scope: `apps/api/app/Http/Controllers/PromotionController.php`, `apps/api/tests/Feature/PromotionTest.php`
Available after: T1
Architecture rule: active = now dalam [start,end]; scheduled = start>now; ended = end<now.
[RESTATE: konversi cursor id→offset; sort via allowlist; jangan ubah assertAdminOrOwner; summary {total,active,scheduled,ended}]

## DELIVERABLE
Given GET /admin/promotions?cursor=15, When merespons, Then offset halaman 2 (bukan id-based).
Given GET /admin/promotions tanpa sort, When merespons, Then created_at DESC + meta.total + summary 4-state.
Given GET /admin/promotions?sort=__proto__, When merespons, Then 200 fallback default.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `next_cursor` lama dihapus (diganti offset `cursor`); response frontend toleran.
  - Summary konsisten dgn filter owner (assertAdminOrOwner scope).
Must-not-have:
  - Menyentuh create/update/delete/broadcast promo.
Rollback note:
  - Revert controller; frontend lama yang pakai `next_cursor` tidak dipakai (dummy+real sudah offset).
Red flags:
  - `next_cursor` masih ada → DONE_WITH_CONCERNS (contract tidak bersih).

## STOP CONDITIONS
Done when: 2 integration test groups PASS; cursor offset terverifikasi.
Uncertain when: scope owner memengaruhi summary (owner filter) → pastikan summary pakai query ber-scope sama.
Escalate when: perlu mengubah schema promo.
