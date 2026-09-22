# Task T8 — Proof-of-delivery upload endpoint (foto + tanda tangan)

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 8: Proof-of-delivery upload endpoint

## OBJECTIVE
Tambah `POST /deliveries/{id}/proof` yang menerima foto + tanda tangan (base64/multipart),
menyimpannya via disk abstraction, dan menulis URL ke `proof_of_delivery` json plus
metadata `pod_captured_at`/`pod_latitude`/`pod_longitude`. Driver hanya untuk delivery miliknya.

Steps:
1. Write failing test for: upload sukses.
   Test file: `apps/api/tests/Feature/FieldOps/DeliveryProofUploadTest.php`
   Level: feature
   Test intent: Given driver + delivery `in_progress` / When `POST /deliveries/{id}/proof`
   dengan foto valid + signature valid / Then 201 + `proof_of_delivery.photo_url` &
   `signature_url` terisi + `pod_captured_at` terisi; `Storage::fake()`.
   Exercise through: HTTP endpoint + `Storage::fake`.
   Test doubles: `Storage::fake('local')`, factory.
   Expected RED: route belum ada → 404.
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=DeliveryProofUploadTest`
3. Implement upload + metadata → PASS → refactor → commit.

4. Write failing test for: validasi & otorisasi.
   Test intent: Given file > batas ukuran / When upload / Then 422; Given tipe tak didukung
   / Then 422; Given driver lain / Then 403.
5. Run test — verify FAIL → implement validasi → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-5, DD-3 (disk-agnostic), A-2 (lokal).

## WHY THIS APPROACH
Complexity: medium
Justification: `UpdateDeliveryStatusRequest` sudah punya field URL; T8 menambah endpoint
upload nyata tanpa mengunci ke S3.

## SANDWICH CONTEXT
[CRITICAL: pakai disk abstraction; driver-only; jangan ubah flow status delivery existing]
Files in scope: `apps/api/app/Http/Controllers/DeliveryController.php`, `apps/api/app/Http/Requests/UploadProofRequest.php`, `apps/api/config/filesystems.php` (BARU — belum ada di repo), `apps/api/routes/api.php`, test.
Available after: T1 (kolom pod meta).
Architecture rule: `Storage::disk(config('filesystems.default'))`; simpan path relatif; kembalikan URL via `Storage::url()`. CATATAN: `config/filesystems.php` belum ada → buat dengan disk `local` (`storage/app/private`) + `public` link, default `local`.

## DELIVERABLE
- `POST /deliveries/{id}/proof` (rbac:field_ops:edit) menerima `photo` (image, max 5MB) + `signature` (image/png, max 2MB) + optional `latitude`/`longitude`.
- Menulis `proof_of_delivery = {photo_url, signature_url, captured_at}` + kolom `pod_*`.
- 403 bukan pemilik; 422 tipe/ukuran salah.
- `config/filesystems.php` dibuat dengan disk `local` (default) + `public`; helper `Storage::fake()` di test.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `Storage::fake` di test (tanpa I/O nyata).
  - Validasi mime + size.
Must-not-have:
  - Menyimpan base64 mentah ke DB.
  - Hardcode path S3.
Open question risks:
  - A-2: disk `local` default; jika butuh S3 → NEEDS_CONTEXT.
Rollback note:
  - Hapus route + method + request; file ter-upload tidak dibersihkan otomatis (catat).

## STOP CONDITIONS
Done when: upload + validasi + otorisasi PASS dengan `Storage::fake`; `config/filesystems.php` ada & `Storage::disk('local')` berfungsi.
Escalate when: butuh S3/object storage produksi (A-2 → NEEDS_CONTEXT).
