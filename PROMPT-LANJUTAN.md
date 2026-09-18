# Prompt Lanjutan — Refactor & Regression Test `apps/api`

> Tempelkan prompt ini di sesi baru. Kerjakan **dari `apps/api`** untuk semua perintah `php artisan test`.

---

## Konteks Proyek

- Repo: `/d/Development/amal/digital-distribution-management-platform` (monorepo).
- Backend: `apps/api` — Laravel 11, PHP 8.3.26, `pdo_pgsql` terpasang.
- `.env` punya `DB_HOST=db` (Docker-only, tidak resolve dari host) → test PostgreSQL race harus **skip** via `PostgresRaceProbe::isAvailable()`.
- PHPUnit default mem-pin `DB_CONNECTION=sqlite` (`:memory:`).
- `phpunit.xml.dist` punya testsuite: `Unit`, `Feature`, `Performance`.
- `Tests\TestCase` boot via `bootstrap/app.php`.

## Tujuan Asal (dari user)

1. Perbaiki source sesuai `note.md` (sudah selesai di commit `6b2914f` + `63e5d75`).
2. Buat regression test untuk perbaikan di `note.md` **dan** pecah file test besar menjadi beberapa file per kategori testing Laravel: **Unit / Feature / Concurrency-Performance**.

`note.md` mendokumentasikan 256 kegagalan pre-existing + 5 fix (A–E):
- **A** `tests/Support/PostgresRaceProbe.php` (baru, `isAvailable()` guard).
- **B** guard `markTestSkipped` di InvoiceTest, PaymentConcurrencyTest, PaymentTest.
- **C** `close()` exception-safe di `InvoiceConcurrencyHarness` + `PaymentConcurrencyHarness`.
- **D** assertion basi OrderTest dikoreksi (re-approve order `Confirmed` → idempoten `200`).
- **E** update baseline docs.

## Status yang Sudah Selesai ✅

### Task #1 — Unit regression tests (PASS)
- `tests/Unit/PostgresRaceProbeTest.php` (4 test).
- `tests/Unit/ConcurrencyHarnessTeardownTest.php` (6 test, DataProvider + reflection).
- Jalankan: `php artisan test tests/Unit/PostgresRaceProbeTest.php tests/Unit/ConcurrencyHarnessTeardownTest.php`

### Task #2 — Ekstraksi concurrency tests (hampir selesai)
- **Baru:** `tests/Feature/Concurrency/Support/SqliteHttpRaceCase.php` — trait pengganti infra SQLite HTTP race yang tadinya terduplikasi 3x. Method: `prepareRaceDatabase`, `createRaceUser`, `createRaceOutlet`, `createRaceProduct`, `startRaceServers`, `waitForRaceServer`, `raceLogin`, `runConcurrentHttpRequests`, `publicHttpRequest`, `raceUrl`, `findFreePort`, `stopRaceInfrastructure`. Sudah diparameterisasi (prefix/password/`method`).
  - Catatan API: `runConcurrentHttpRequests(array $requests, ?string $defaultPath = null)`; tiap request menerima `['token'=>..,'body'=>..,'method'=>..,'path'=>..]`.
- `git mv` (sudah dilakukan) ke `tests/Feature/Concurrency/`, namespace `Tests\Feature\Concurrency`:
  - `PaymentConcurrencyTest`, `DeliveryConcurrencyTest`, `PrePilotConcurrencyCompatibilityTest`, `WhatsAppPostgresConcurrencyTest`, `InvoiceReminderPostgresConcurrencyTest`.
- Rewrite pakai trait dan **PASS**:
  - `DeliveryConcurrencyTest` (1 test)
  - `PrePilotConcurrencyCompatibilityTest` (3 test)
- `tests/Feature/InvoiceReminderTest.php` require diubah → `./Concurrency/InvoiceReminderPostgresConcurrencyTest.php`.
- `PostgresConcurrencyFeatureCase` + `InvoiceReminderPostgresScenarios` tetap di `tests/Feature/Support` (masih dipakai).
- **Baru:** `tests/Feature/Concurrency/OrderConcurrencyTest.php` — 2 test dari OrderTest (`test_concurrent_same_identity_submissions_return_one_order_result`, `test_concurrent_admin_approvals_append_one_confirmed_history`) + hapus infra race dari `OrderTest.php` (OrderTest jadi 22 test, PASS).
- **Baru:** `tests/Feature/Concurrency/InvoiceConcurrencyTest.php` — `test_concurrent_approvals_create_one_invoice` dari InvoiceTest (InvoiceTest jadi 9 test, PASS).
- **Dibersihkan:** `PaymentTest.php` — hapus alias legacy `test_concurrent_same_identity_payment_posts_replay_one_payment` (duplikat persis `PaymentConcurrencyTest`) + buang import/properti/tearDown harness (PaymentTest jadi 6 test, PASS).

## Yang MASIH Tersisa ⚠️

### 1. Investigasi flake (PRIORITAS)
`tests/Feature/Concurrency/OrderConcurrencyTest::test_concurrent_admin_approvals_append_one_confirmed_history`
**kadang** gagal (`[200, 500]` bukan `[200, 200]`) hanya saat full-dir run, ~1 dari 3–5 run. Tidak pernah gagal saat dijalankan sendiri.

- Sudah dikonfirmasi: **flaky juga pada kode HEAD** (versi lama `OrderTest` yang sama) — jadi **bukan regresi dari refactor**.
- Root cause belum ketemu. Dugaan: race/retry `runApprovalTransaction` (3x retry `QueryException`) + barrier file + SQLite file lock; atau server worker lambat start.
- **TODO:** tentukan apakah flake ini acceptable (pre-existing) atau perlu di-hardening. Kalau pre-existing, cukup dokumentasikan di `note.md`. Kalau mau di-fix, kandidat: naikkan jumlah retry, atau perbaiki barrier/wait di `SqliteHttpRaceCase`.
- Cara reproduce: `for i in 1 2 3 4 5; do php artisan test tests/Feature/Concurrency >/tmp/run.out 2>&1; grep -o '[0-9]* failed' /tmp/run.out | head -1; done`

### 2. Task #3 — Registrasi testsuite & split
- Tambah testsuite `Concurrency` di `phpunit.xml.dist`; tambahkan `<exclude>tests/Feature/Concurrency</exclude>` di testsuite `Feature` agar tidak double-run.
- Split `tests/Feature/OrderTest.php` → `OrderTest` (creation/approval) + `OrderQueryTest` (test `test_admin_orders_list_*` + helper `loginAsAdmin`/`createOrderAt`).
- Cek juga file Feature besar lain yang perlu dipecah.

### 3. Task #4 — Verifikasi penuh & update docs
- Jalankan full suite: `php artisan test` (target: 0 failed; bandingkan dengan baseline `note.md`: 433 passed / 8 skipped).
- Jalankan juga `DB_HOST=127.0.0.1 php artisan test` (baseline 436 passed / 5 skipped) untuk cek jalur pgsql.
- Update hitungan di `note.md` bila berubah.
- Update docs yang menyebut path lama: `docs/dokumentasi-teknis.md`, `docs/panduan-setup.md`, `docs/pilot/pre-pilot-compatibility-baseline.md`, `docs/pilot/pre-pilot-compatibility-matrix.md` (path `PaymentConcurrencyTest`, `DeliveryConcurrencyTest`, dll).

### 4. Bersih-bersih
- Pastikan tidak ada file debug tertinggal (sempat ada patch sementara ke `SqliteHttpRaceCase.php` & `OrderConcurrencyTest.php` untuk log ke `/tmp`; sudah di-revert — **verifikasi ulang** dengan `git diff`).
- Cek import tak terpakai di file yang diedit (`php -l` tiap file).

## File yang Dimodifikasi/Ditambah

**Baru:**
- `tests/Feature/Concurrency/Support/SqliteHttpRaceCase.php`
- `tests/Feature/Concurrency/OrderConcurrencyTest.php`
- `tests/Feature/Concurrency/InvoiceConcurrencyTest.php`
- `tests/Unit/PostgresRaceProbeTest.php`
- `tests/Unit/ConcurrencyHarnessTeardownTest.php`

**Modifikasi:**
- `tests/Feature/OrderTest.php`, `tests/Feature/InvoiceTest.php`, `tests/Feature/PaymentTest.php`, `tests/Feature/InvoiceReminderTest.php`
- `tests/Feature/Concurrency/{DeliveryConcurrencyTest,PrePilotConcurrencyCompatibilityTest,PaymentConcurrencyTest,WhatsAppPostgresConcurrencyTest,InvoiceReminderPostgresConcurrencyTest}.php`
- (moved dari `tests/Feature/` ke `tests/Feature/Concurrency/`)

## Perintah Verifikasi Cepat

```bash
cd apps/api
php artisan test tests/Unit/PostgresRaceProbeTest.php tests/Unit/ConcurrencyHarnessTeardownTest.php
php artisan test tests/Feature/OrderTest.php tests/Feature/InvoiceTest.php tests/Feature/PaymentTest.php
php artisan test tests/Feature/Concurrency
php artisan test                      # full suite
```

## Instruksi untuk Agent

1. Mulai dengan **verifikasi state** (`git status`, `git diff --stat`, `php -l` pada file yang berubah) — pastikan tidak ada sisa debug.
2. Selesaikan **investigasi flake** (bagian 1) dan putuskan: dokumentasikan atau hardening.
3. Kerjakan **Task #3** (testsuite + split OrderTest).
4. Jalankan **Task #4** (full suite + update `note.md` + docs).
5. Laporkan ringkas: file yang diubah, hasil test sebelum/sesudah, dan keputusan soal flake.
