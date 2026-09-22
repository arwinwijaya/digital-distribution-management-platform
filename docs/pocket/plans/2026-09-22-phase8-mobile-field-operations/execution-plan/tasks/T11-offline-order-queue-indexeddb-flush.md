# Task T11 — Offline order queue (IndexedDB wrapper + flush)

**Phase:** 3
**Depends:** T10
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 11: Offline order queue (IndexedDB wrapper + flush)

## OBJECTIVE
Buat antrean order offline client-side (IndexedDB via wrapper, fallback localStorage)
yang menyimpan payload order saat offline dan mem-flush ke `POST /orders` (idempotent)
saat kembali online.

Steps:
1. Write failing test for: enqueue + list.
   Test file: `apps/web/src/lib/offline/order-queue.test.ts`
   Level: unit
   Test intent: Given queue kosong / When `enqueue({payload})` / Then `list()` memuat 1
   item dengan id unik + `created_at`; When `remove(id)` / Then `list()` kosong.
   Exercise through: `orderQueue.enqueue/list/remove`.
   Test doubles: adapter storage in-memory (inject `StorageAdapter` stub; TIDAK memakai `fake-indexeddb` — belum ada di deps).
   Expected RED: modul belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/offline/order-queue.test.ts`
3. Implement queue storage → PASS → refactor → commit.

4. Write failing test for: flush sukses + idempotency key.
   Test intent: Given 2 item antrean + `POST /orders` sukses / When `flushQueue(send)` /
   Then 2 item terkirim dengan header idempotency (payload hash) + antrean kosong; Given
   satu item gagal (network) / Then item itu tetap di antrean, yang sukses terhapus.
   Test doubles: mock `send` (jest.fn) sukses/gagal.
5. Run test — verify FAIL → implement flush → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-6, DD-4 (idempotent replay via payload hash).

## WHY THIS APPROACH
Complexity: medium
Justification: `POST /orders` sudah idempotent (`idempotency_payload_hash`), jadi antrean
cukup menyimpan payload dan replay; tanpa endpoint sync baru.

## SANDWICH CONTEXT
[CRITICAL: flush tidak boleh menghapus item yang gagal; tidak ada endpoint baru]
Files in scope: `apps/web/src/lib/offline/order-queue.ts`, `apps/web/src/lib/offline/indexeddb.ts` (wrapper), test.
Available after: T10 (offline shell).
Architecture rule: `StorageAdapter` interface (`get/set/delete/keys`) dapat di-inject agar test tidak butuh IndexedDB nyata; implementasi `IndexedDbAdapter` (native, tanpa dependency baru) + fallback `LocalStorageAdapter` bila `indexedDB` absen (A-3). JANGAN menambah paket `idb-keyval`/`fake-indexeddb` (tidak ada di `apps/web/package.json`).

## DELIVERABLE
- `orderQueue`: `enqueue(payload)`, `list()`, `remove(id)`, `clear()`.
- `flushQueue(send: (payload)=>Promise<Response>)` mengembalikan `{sent, failed}`.
- Idempotency key deterministik dari payload (hash).
- `StorageAdapter` interface + `IndexedDbAdapter` (native) + `LocalStorageAdapter` fallback.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Item gagal tidak hilang.
  - Idempotency key stabil untuk payload sama.
Must-not-have:
  - Menyimpan token/secret di IndexedDB.
  - Menambah dependency npm baru (`idb-keyval`, `fake-indexeddb`) — gunakan adapter injectable.
Open question risks:
  - A-3: IndexedDB absen → fallback localStorage.
Rollback note:
  - Hapus modul queue; tidak ada dampak server.

## STOP CONDITIONS
Done when: enqueue/list/remove + flush sukses/gagal PASS.
Escalate when: hash idempotency tidak cocok dengan kontrak `POST /orders`.
