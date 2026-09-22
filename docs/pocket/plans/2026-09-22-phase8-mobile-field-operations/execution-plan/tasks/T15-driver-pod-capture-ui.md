# Task T15 — UI capture PoD driver (kamera + canvas tanda tangan)

**Phase:** 4
**Depends:** T8
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 15: UI capture PoD driver

## OBJECTIVE
Buat komponen capture Proof of Delivery untuk driver: ambil foto (kamera/file input),
gambar tanda tangan pada `<canvas>`, lalu unggah ke endpoint T8.

Steps:
1. Write failing test for: submit foto + tanda tangan.
   Test file: `apps/web/src/components/delivery/PodCapture.test.tsx`
   Level: component
   Test intent: Given komponen render / When set foto (file) + gambar tanda tangan +
   klik submit / Then `uploadProof(deliveryId, {photo, signature})` dipanggil.
   Exercise through: render + fireEvent + mock canvas `toBlob`.
   Test doubles: mock `HTMLCanvasElement.prototype.toBlob`, mock api.
   Expected RED: komponen belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/components/delivery/PodCapture.test.tsx`
3. Implement kamera input + canvas tanda tangan + submit → PASS → refactor → commit.

4. Write failing test for: disable submit bila belum lengkap.
   Test intent: Given tanpa foto / Then tombol submit disabled; Given tanpa tanda tangan /
   Then disabled; Given keduanya ada / Then enabled.
5. Run test — verify FAIL → implement guard → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-5, AC-7, DD-3.

## WHY THIS APPROACH
Complexity: medium
Justification: canvas tanda tangan + `<input type=file accept=image/* capture=environment>`
untuk kamera; tanpa dependency baru.

## SANDWICH CONTEXT
[CRITICAL: jangan kirim bila salah satu bukti kosong; canvas harus mengembalikan blob PNG]
Files in scope: `apps/web/src/components/delivery/PodCapture.tsx`, `apps/web/src/app/delivery/api.ts`, test.
Available after: T8 (endpoint upload).
Architecture rule: kirim `multipart/form-data`; guard dummy (fake sukses); reset setelah sukses.

## DELIVERABLE
- Komponen `PodCapture`: input foto (preview), canvas tanda tangan (clear), tombol submit.
- `uploadProof(id, {photo, signature, latitude?, longitude?})` di `delivery/api.ts`.
- Disabled state + pesan sukses/gagal.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Submit disabled sampai foto + tanda tangan ada.
  - Canvas mengembalikan PNG blob non-kosong.
Must-not-have:
  - Menyimpan foto di state global/dummy store.
Open question risks:
  - Ukuran canvas/high-DPI dapat memperbesar blob → batasi dimensi.
Rollback note:
  - Hapus komponen + api method.

## STOP CONDITIONS
Done when: submit + disabled guard PASS; `tsc --noEmit` bersih.
Escalate when: canvas tidak tersedia (SSR) → guard `typeof document`.
