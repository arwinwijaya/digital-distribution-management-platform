# Task T12 — Integrasi antrean offline ke form order + status UI

**Phase:** 3
**Depends:** T11
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 12: Integrasi antrean offline ke form order + status UI

## OBJECTIVE
Sambungkan `OrderForm` ke antrean offline: bila offline (atau request gagal karena
network), order masuk antrean dan ditampilkan sebagai "menunggu sinkron"; saat online,
`flushQueue` dijalankan otomatis dan order muncul di daftar.

Steps:
1. Write failing test for: submit saat offline → enqueue.
   Test file: `apps/web/src/components/OrderForm.offline.test.tsx`
   Level: component
   Test intent: Given `navigator.onLine=false` + isi order valid / When submit / Then
   pesan "Menunggu sinkronisasi" tampil dan `orderQueue.list()` berisi 1 item (zero fetch).
   Exercise through: render `OrderForm` + `fireEvent.submit`.
   Test doubles: mock `navigator.onLine`, mock queue, mock fetch.
   Expected RED: submit mencoba fetch dan error.
2. Run test — verify FAIL: `cd apps/web && npx jest OrderForm.offline.test.tsx`
3. Implement guard offline → enqueue → PASS → refactor → commit.

4. Write failing test for: kembali online → flush.
   Test intent: Given 1 item antrean / When `online` event fired / Then `flushQueue`
   dipanggil dan antrean kosong + order muncul di daftar.
5. Run test — verify FAIL → implement auto-flush hook → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-6, AC-7.

## WHY THIS APPROACH
Complexity: medium
Justification: memakai `useOnlineStatus` (T10) + `orderQueue` (T11); tidak mengubah
`OrderCreationService`.

## SANDWICH CONTEXT
[CRITICAL: jangan ubah perilaku submit online normal; offline hanya jalur tambahan]
Files in scope: `apps/web/src/components/OrderForm.tsx`, test.
Available after: T10 (online status), T11 (queue).
Architecture rule: bila dummy mode ON, offline queue dinonaktifkan (dummy sudah zero-network).

## DELIVERABLE
- Submit offline → enqueue + toast "Menunggu sinkronisasi".
- `online` event → auto-flush + refresh daftar.
- Indikator jumlah item tertunda.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Jalur online tidak berubah (test lama tetap hijau).
  - Tidak enqueue saat dummy ON.
Must-not-have:
  - Duplikasi order (idempotency key dari T11).
Open question risks:
  - Sinkron dengan refresh dummy (`useDummyRefresh`).
Rollback note:
  - Hapus guard offline; kembali ke perilaku lama.

## STOP CONDITIONS
Done when: submit offline + auto-flush PASS; test OrderForm lama tetap hijau.
Escalate when: kontrak `OrderForm` tidak memungkinkan injeksi queue.
