# Task T3 — `RoutingService` nearest-neighbor bounded deterministik

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: `RoutingService` nearest-neighbor bounded deterministik

## OBJECTIVE
Ganti `RoutingService::plan()` yang selalu mengembalikan `[]` menjadi nearest-neighbor
bounded deterministik: ambil stop berkoordinat dari order/delivery, urutkan dari titik
awal (gudang/outlet pertama), hitung jarak haversine, cap `MAX_STOPS`, tie-break `id`.

Steps:
1. Write failing test for: urutan deterministik + total jarak.
   Test file: `apps/api/tests/Unit/RoutingServiceTest.php`
   Level: unit
   Test intent: Given 3 stop koordinat tetap / When `plan()` / Then urutan stop sesuai
   nearest-neighbor dari titik awal; When dipanggil dua kali / Then hasil identik; When
   stop > `MAX_STOPS` / Then hasil ≤ `MAX_STOPS`.
   Exercise through: `RoutingService::plan()`.
   Test doubles: model Delivery/Order in-memory atau factory.
   Expected RED: `plan()` mengembalikan `[]` → assertion gagal.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=RoutingServiceTest`
3. Implement haversine + nearest-neighbor + cap → PASS → refactor → commit.

4. Write failing test for: stop tanpa koordinat dilewati.
   Test intent: Given 1 stop tanpa lat/long / When `plan()` / Then stop itu tidak muncul,
   tidak ada exception.
5. Run test — verify FAIL → implement → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-3, DD-2.

## WHY THIS APPROACH
Complexity: medium
Justification: seam sudah ada (`RoutingService`), hanya mengisi implementasi bounded
deterministik agar testable tanpa ML/solver eksternal.

## SANDWICH CONTEXT
[CRITICAL: deterministik; tanpa dependency eksternal; jangan ubah signature `plan(Delivery $delivery): array`]
Files in scope: `apps/api/app/Services/RoutingService.php`, `apps/api/tests/Unit/RoutingServiceTest.php`.
Available after: T1 (kolom outlet lat/long + delivery).
Architecture rule: haversine murni PHP; `MAX_STOPS` konstanta kelas (mis. 20).

## DELIVERABLE
- `plan()` mengembalikan `['stops' => [['id'=>..,'latitude'=>..,'longitude'=>..,'distance_km'=>..], ...], 'total_distance_km' => float]` atau `[]` bila tak ada koordinat.
- Deterministik (tie-break `id` asc).
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Deterministik: input sama → output sama.
  - Bounded `MAX_STOPS`.
  - Stop tanpa koordinat dilewati tanpa error.
Must-not-have:
  - Dependency paket rute eksternal.
  - Perubahan signature publik.
Open question risks:
  - Definisi titik awal rute (gudang vs outlet pertama) — asumsikan titik awal = stop pertama by id.
Rollback note:
  - Kembalikan `plan()` ke `return []`.

## STOP CONDITIONS
Done when: unit test urutan + determinisme + cap + skip PASS.
Uncertain when: titik awal tidak terdefinisi.
Escalate when: butuh data gudang yang belum ada.
