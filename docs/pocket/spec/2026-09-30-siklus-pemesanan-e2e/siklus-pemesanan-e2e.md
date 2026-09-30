# Siklus Pemesanan End-to-End (Hybrid API + Thin UI)

**Date:** 2026-09-30
**Status:** approved
**Author:** pocket-grinding session (from pitch `2026-09-30-siklus-pemesanan-e2e/pitch-exploration.md`)
**Spec path:** docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md

---

## Summary

Otomatisasi satu siklus pemesanan outlet → pembayaran (`Outlet login → pilih produk → buat pesanan → admin approve → assign delivery → driver antar → delivered → pembayaran → Paid`) memakai pendekatan hybrid: business rules dan negative checkpoints (§7 + §8 dari `docs/manual-check-siklus-pemesanan.md`) diuji via API test (perluasan `apps/web/order-flow.test.js`), sedangkan critical path UI (§6) diuji via browser test Playwright Chromium real-data. Setiap aksi sukses maupun gagal tercatat sebagai satu event JSONL per step plus screenshot/trace/video on-failure. Database diisolasi via `docker compose exec api php artisan migrate:fresh --seed` sebelum tiap run.

## Context

### Current State
- Cek siklus hanya berupa checklist manual (`docs/manual-check-siklus-pemesanan.md` §6 UI, §7 API, §8 negative) yang dijalankan serial sebagai 4 role berbeda tanpa observability terstruktur.
- `apps/web/order-flow.test.js` hanya API-level (`php -S` + sqlite via Jest, bahkan di-ignore oleh `jest.config.js` default) — tidak meng-cover UI browser.
- Browser E2E greenfield: `@playwright/test` belum terinstal; tidak ada direktori `apps/web/e2e`.
- Login terpadu `/login` (`apps/web/src/app/login/page.tsx` + `components/LoginForm.tsx`): POST `apiUrl('/auth/login')`, token di `localStorage.ddp_token` + `ddp_role`, redirect via `roleDestination()` (`outlet→/orders`, `driver→/delivery`, `sales→/sales/orders`, `supplier→/marketplace`, admin/finance/platform_owner→`/dashboard`).
- Order: `components/OrderForm.tsx` (`submitOutletOrder` POST `/orders` + `Idempotency-Key`, `loadOrderFormProducts` GET `/products`).
- Admin approve: `app/admin/orders/page.tsx` (`PUT /orders/{id}/approve`, tombol `"Setujui"`, detail + `status_history`).
- Delivery: `app/delivery/page.tsx` + `app/delivery/api.ts` (tombol `"Mulai antar"` PATCH `/deliveries/{id}/status` → `in_progress`, form `"Nama penerima"` + `"URL bukti pengiriman"` + `"Tandai terkirim"` → `delivered`; PoD upload via `uploadProof` POST `/deliveries/{id}/proof`).
- Payment: `app/payments/page.tsx` (`recordPayment` POST `/payments`, form `"ID pesanan"` + `"Jumlah pembayaran"` + `"Simpan pembayaran"`, pesan sukses `"Pembayaran berhasil dicatat. Referensi: ..."`).
- API routes terkonfirmasi (`apps/api/routes/api.php`): `POST /auth/login`, `GET /products`, `POST /orders`, `GET /orders/{id}`, `PUT /orders/{id}/approve`, `GET /invoices`, `POST /deliveries`, `GET /deliveries`, `PATCH /deliveries/{id}/status`, `POST /payments`, `GET /payments`. Tidak ada `/test/seed` — isolasi hanya via artisan.
- Seeder (`DatabaseSeeder.php:13`) konfirmasi `DEFAULT_PASSWORD = password123` + 4 akun siklus.
- CI: direktori `.github/workflows/` kosong (belum ada pipeline); services compose: `db`, `redis`, `api`, `queue-worker`, `scheduler`, `web`, `nginx`.

### Problem / Motivation
Tanpa automasi browser + log terstruktur, regresi UI (mis. fix redirect outlet-first `608ff6a`, auth state loss, toast, validasi client-side) tidak terdeteksi API test; failure harus di-debug via re-run manual karena tidak ada audit trail per step.

### Related Areas
- `apps/web/src/app/login/page.tsx`, `apps/web/src/components/LoginForm.tsx`
- `apps/web/src/components/OrderForm.tsx`, `apps/web/src/app/orders/page.tsx`
- `apps/web/src/app/admin/orders/page.tsx`, `apps/web/src/app/delivery/page.tsx` + `api.ts`, `apps/web/src/app/payments/page.tsx` + `api.ts`
- `apps/web/order-flow.test.js`, `apps/web/jest.config.js`, `apps/web/package.json` (`test:e2e`)
- `apps/api/routes/api.php`, `DatabaseSeeder.php`, `PaymentService.php` (`replayExistingPayment`, `createPaymentForOrder`), `DeliveryController::store`
- `docs/manual-check-siklus-pemesanan.md` (§6.1–6.9, §7.1–7.16, §8.1–8.4, §10 acceptance)

---

## Scope

### In-Scope
- Browser test Playwright Chromium (headless CI + headed debug lokal) untuk 1 siklus serial (§6): outlet order → admin approve → assign delivery → driver deliver → finance full payment → verifikasi Paid.
- Perluasan API test untuk §7 + §8: assign-guard, pay-guard, overpayment, idempotency order + payment.
- Structured logging first-class: 1 event JSONL per aksi terinstrumentasi (sukses + gagal) + screenshot/trace/video on-failure + JUnit/HTML report.
- Kontrak `data-testid` minimal (full coverage halaman kritis) — tambah atribut saja, tanpa ubah logic.
- Seed isolation deterministik: `docker compose exec api php artisan migrate:fresh --seed` sebelum tiap run (ASSUMED: cukup; tanpa endpoint test-only).

### Out-of-Scope
- Firefox/WebKit matrix — Chromium saja (alasan: resource CI + permintaan awal).
- Log pipeline remote (Loki/Elastic) — cukup file JSONL + CI artifacts.
- Perubahan business logic backend, skema/migrasi DB, RBAC matrix, state machine order/delivery/invoice/payment.
- Dummy-mode E2E tersendiri — uji mode real (`isDummy=false`); guard `withDummyRead`/`useDummyStore` di-bypass.
- WhatsApp/email/push real — stub/mock bila tersentuh.
- Performance/load testing; concurrency barrier di luar idempotency deterministik.
- Jest unit test frontend — hanya API test + browser test.

---

## Architecture Constraints

- Layers this work may touch: `apps/web/src` (tambah `data-testid` saja), `apps/web/e2e/**` (spec + fixtures baru), modul logger JSONL baru, perluasan `order-flow.test.js`, script seed (`scripts/e2e-seed.sh` atau npm script), workflow CI baru di `.github/workflows/`.
- Layers this work must NOT touch: kontrak API Laravel, migration/skema DB, RBAC matrix, payment/invoice/order state machine, business rule backend.
- Patterns that must be followed: Page Object hanya untuk halaman kompleks (order/delivery/payment); 1 `browserContext` per role + clear `storageState` antar role; serial execution (no parallel) untuk siklus; selector utama `data-testid` (fallback `getByLabel`/`getByRole` hanya sementara); video/trace/screenshot on-failure only.
- Architecture validation result: PASS (8/8 checklist Phase 6; dependensi baru `@playwright/test` adalah dev-only test runner, bukan runtime app).

---

## Dependencies

### Existing (to leverage)
- `jest@29` + `jest-environment-jsdom` — API test yang sudah ada (`order-flow.test.js`).
- `next@16.3`, `react@18`, `zustand@4` — app under test (bukan test dep).
- Docker Compose services `db` (postgres:16-alpine), `api` (php-fpm + artisan), `web` (Next.js) — environment test.

### New (proposed)
- `@playwright/test@~1.48` (devDependency di `apps/web`) — runner browser: auto-wait, trace, screenshot, video, JUnit+JSON+HTML reporter, multi-`browserContext`. Alternatives rejected: Cypress (runner terpisah, multi-context/trace lebih lemah), Puppeteer (Chromium-only + tanpa test-runner/reporting bawaan), WebDriverIO (lebih berat, tanpa keunggulan untuk kasus ini).
- CI image `mcr.microsoft.com/playwright` — browser resmi; alternatif host-runner ditolak (flaky browser version drift di Windows host).
- *(No other new deps. Hand-rolled logger JSONL adalah file util ~50 baris, bukan commodity lib — tidak perlu lib logging eksternal.)*

---

## Stories + Scenarios

### Story 1: Siklus UI utama (thin browser path, §6)
> As an outlet/admin/driver/finance user, I want to complete one order from login to payment in the real browser, so that the business status flow and transaction evidence can be verified end-to-end.

**Rule 1: Serial 4-role flow on real data**
- Example A: outlet `siti.nurhaliza@ddp.test` / `password123` login → `/orders`, pilih produk aktif stok cukup (qty 2 + 1), submit → order `New` + `ORD-...` + total tercatat.
- Example B: admin `ratna.sari@ddp.test` approve → `Confirmed` + invoice `unpaid` (total sama, paid `0.00`); assign driver Joko Widodo + notes `Pengiriman manual test 1 siklus pemesanan` → delivery `assigned`, order tetap `Confirmed`.
- Example C: driver `joko.widodo@ddp.test` → `in_progress` (order tetap `Confirmed`); isi recipient `Siti Nurhaliza` + proof `https://example.com/proof/manual-check-order-001.jpg` → `delivered`, order `Delivered`.
- Example D: finance `dewi.lestari@ddp.test` bayar `TOTAL_AMOUNT` (finance boleh membayar order outlet mana pun) → payment `completed`, order `Paid`, invoice `paid`, balance `0.00`, receipt/reference terisi, badge `Paid` + toast/notifikasi present (presence, bukan exact-text).

```gherkin
Scenario: Outlet membuat order baru melalui browser
  Given database telah di-reset via migrate:fresh --seed dan produk aktif memiliki stok cukup
  And browser berjalan mode real data (isDummy=false)
  When outlet login dengan siti.nurhaliza@ddp.test / password123
  And memilih produk aktif lalu mengirim order (qty contoh 2 + 1)
  Then login berhasil mengarah ke /orders
  And order dibuat dengan status New
  And order reference, numeric id, total, dan items tercatat di log

Scenario: Admin menyetujui order dan invoice tersedia
  Given order outlet berstatus New
  When admin login dengan ratna.sari@ddp.test
  And menyetujui order tersebut via tombol Setujui
  Then order berstatus Confirmed
  And invoice tersedia dengan status unpaid
  And total invoice sama dengan total order dan paid_amount 0.00

Scenario: Admin membuat assignment delivery
  Given order berstatus Confirmed
  When admin membuat delivery untuk driver Joko Widodo dengan notes contoh
  Then delivery berstatus assigned
  And driver_id sesuai Joko Widodo
  And order tetap berstatus Confirmed

Scenario: Driver menyelesaikan delivery
  Given delivery Joko berstatus assigned
  When driver login dengan joko.widodo@ddp.test
  And mengubah delivery ke in_progress
  And mengisi recipient Siti Nurhaliza serta proof URL valid
  And menandai delivery sebagai delivered
  Then delivery berstatus delivered dan delivered_at terisi
  And order berstatus Delivered
  And bukti penerimaan tersimpan

Scenario: Finance mencatat pembayaran penuh
  Given order berstatus Delivered dan invoice outstanding sebesar TOTAL_AMOUNT
  When finance login dengan dewi.lestari@ddp.test
  And mencatat payment sebesar TOTAL_AMOUNT (metode cash/bank_transfer)
  Then payment berstatus completed
  And order berstatus Paid dengan paid amount = TOTAL_AMOUNT
  And invoice berstatus paid dengan balance 0.00
  And receipt/reference pembayaran tersedia
  And UI menampilkan badge Paid
  And toast/notifikasi sukses hadir (presence assertion)
```

**Rule 2: Status invariants (§5 + §10 manual doc)**
- Example: order `New → Confirmed → Delivered → Paid`; delivery `assigned → in_progress → delivered`; invoice `unpaid → paid`; `status_history` minimal memuat `New, Confirmed, Delivered, Paid`.

```gherkin
Scenario: Status history lengkap
  Given siklus selesai hingga Paid
  When detail order diambil via UI/API
  Then status_history memuat minimal New, Confirmed, Delivered, Paid berurutan
```

### Story 2: Business-rule negative checks (API test, §8)
> As a system owner, I want invalid state transitions rejected automatically, so that orders cannot skip the valid sequence.

**Rule 1: Assign-guard** — delivery hanya untuk order `Confirmed` (pesan: `Only confirmed orders can be assigned for delivery.`).
**Rule 2: Pay-guard** — payment hanya untuk order `Delivered` (pesan: `Payments can only be recorded for delivered orders.`).
**Rule 3: Overpayment-guard** — payment tidak boleh melebihi outstanding (pesan: `Payment cannot exceed the outstanding balance.`); order tetap `Delivered`, outstanding tidak berubah.
**Rule 4: Idempotency** — key sama + payload identik → HTTP 200 `created:false`, tanpa record baru (ASSUMED dari `PaymentService::replayExistingPayment`; pocket-planning wajib verifikasi dari code); key sama + payload berbeda → 422, data pertama tidak berubah.

```gherkin
Scenario: Assign ditolak sebelum order Confirmed
  Given order baru berstatus New
  When API mencoba POST /deliveries untuk order tersebut
  Then API mengembalikan HTTP 422
  And tidak ada delivery baru

Scenario: Payment ditolak sebelum order Delivered
  Given order berstatus Confirmed dan delivery belum delivered
  When API mencoba POST /payments
  Then API mengembalikan HTTP 422
  And saldo/order/invoice tidak berubah

Scenario: Overpayment ditolak
  Given order berstatus Delivered dengan outstanding sebesar O
  When API mencatat payment lebih dari O
  Then API mengembalikan HTTP 422
  And payment tidak tercatat dan outstanding tetap O

Scenario: Idempotency replay identik
  Given request payment/order pertama dengan idempotency key K berhasil
  When request identik dikirim ulang dengan K
  Then response HTTP 200 dengan created=false menunjuk record yang sama
  And tidak ada record duplikat

Scenario: Idempotency key dipakai ulang dengan payload berbeda
  Given request pertama dengan key K berhasil
  When request berbeda dikirim dengan K yang sama
  Then API mengembalikan HTTP 422
  And data pertama tidak berubah
```

### Story 3: Observability first-class (§9 manual doc → otomatis)
> As a tester/developer, I want every step logged (success and failure), so that failures are diagnosable without manual re-run.

**Rule 1: JSONL per step** — tepat satu event per aksi terinstrumentasi; field wajib: `runId, timestamp, role, step, action, url, selector, apiStatus, durationMs, status, error, attachments`. (ASSUMED sink: `apps/web/test-results/e2e-logs/<runId>.jsonl`.)
**Rule 2: Artifacts on-failure** — screenshot + trace + video Playwright hanya saat gagal; retensi 7 hari lokal (purge script) / 30 hari CI (`actions/upload-artifact retention-days: 30`). (ASSUMED.)

```gherkin
Scenario: Semua step menghasilkan log
  Given test menjalankan satu atau lebih step
  When step sukses atau gagal
  Then tepat satu event JSONL dibuat untuk setiap aksi terinstrumentasi
  And event sukses memuat status sukses serta durationMs
  And event gagal memuat error serta attachment yang tersedia

Scenario: Artifact hanya saat gagal
  Given sebuah test gagal (mis. assertion Paid tidak terpenuhi)
  When Playwright on-failure hook berjalan
  Then screenshot, trace, dan video tersimpan di folder artifacts run tersebut
  And path artifact tercantum di event JSONL gagal
```

---

## Acceptance Criteria

```
ACCEPTANCE CRITERIA — siklus-pemesanan-e2e
Date: 2026-09-30 | Scope confirmed: yes (user: setuju)

Rule: Serial 4-role browser flow (§6)
  ✓ Given DB fresh + produk stok cukup, When outlet login + submit order, Then redirect /orders + order New + id/reference/total logged
  ✓ Given order New, When admin approve, Then order Confirmed + invoice unpaid (total sama, paid 0.00)
  ✓ Given order Confirmed, When admin assign Joko, Then delivery assigned + order tetap Confirmed
  ✓ Given delivery assigned, When driver start + complete dengan recipient + proof URL, Then delivery delivered + order Delivered + proof tersimpan
  ✓ Given order Delivered + outstanding TOTAL, When finance bayar TOTAL (outlet mana pun), Then payment completed + order Paid + invoice paid + balance 0.00 + receipt + badge Paid + toast present
  ✓ Given siklus Paid, When detail diambil, Then status_history minimal New, Confirmed, Delivered, Paid

Rule: Negative checks (§8, API test)
  ✓ Given order New, When POST /deliveries, Then 422 + no delivery
  ✓ Given order belum Delivered, When POST /payments, Then 422 + no mutation
  ✓ Given outstanding O, When pay > O, Then 422 + no payment + outstanding tetap O
  ✓ Given key K sukses, When replay identik K, Then 200 created:false + no duplicate
  ✗ Given key K sukses, When payload beda + K sama, Then 422 + data pertama utuh

Rule: Observability (§9)
  ✓ Given tiap aksi terinstrumentasi, When sukses/gagal, Then tepat 1 event JSONL dengan field wajib
  ✓ Given test gagal, When hook berjalan, Then screenshot+trace+video tersimpan + path di event gagal
```

---

## Design Decision

**Chosen option:** Option B — Hybrid API + thin UI.

**Summary:** Business rules (§7 + §8) tetap di API test (cepat, deterministik, reuse `order-flow.test.js`); browser Playwright hanya untuk critical path UI (§6) dengan logging terstruktur yang sama. Satu-satunya opsi yang memenuhi "otomatis di browser + catat semua log" sekaligus meredam vektor flake dari spike (minim `data-testid`, seeded DB shared).

**Rejected options:**
- Option A (Full-UI Playwright): rejected karena seluruh guard bisnis lewat UI = paling lambat & flaky sampai ~20 testid + seed strategy matang; maintenance tinggi.
- Option C (API-only): rejected karena tidak menjawab permintaan "test otomatis di browser" — miss regresi UI (redirect, auth state, toast).

**Key tradeoffs accepted:**
- Dua runner (Jest + Playwright) — setup awal lebih kompleks, diimbangi kecepatan + stabilitas.
- Presence-assertion untuk toast (bukan exact-text) — lebih tahan perubahan copywriting, kehilangan deteksi typo pesan.
- `migrate:fresh --seed` per run (bukan snapshot/endpoint) — paling simpel & deterministik, lebih lambat dari snapshot restore.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Sink file JSONL final | assumed: `apps/web/test-results/e2e-logs/<runId>.jsonl` | CI tidak bisa aggregate log bila path beda |
| Retensi artifact | assumed: `actions/upload-artifact retention-days: 30` (CI) + purge script 7 hari (lokal) | Artifact hilang sebelum dianalisis / storage membengkak |
| Daftar `data-testid` final (~15–20) | assumed: login-email/password/submit/error; order-submit/success/track; admin-orders-table/approve/detail; delivery-start/recipient/proof-url/complete; payment-order-id/amount/submit/success; status-badge/toast | Selector rapuh → flake bila kurang |
| Replay identik = 200 `created:false` | assumed dari `PaymentService::replayExistingPayment`; planning wajib verifikasi dari code | AC salah bila aktualnya 201/kontrak lain |
| `migrate:fresh --seed` cukup deterministik di CI Windows+Docker | assumed: ya | Perlu snapshot/endpoint bila terlalu lambat |
| JWT cukup untuk seluruh alur serial tanpa refresh | assumed: ya (planning verifikasi expiry) | Test tengah jalan 401 bila expiry pendek |
| Korelasi antar-step cukup via `runId` + `order reference` (tanpa correlation-ID header baru) | assumed: ya | Butuh propagasi header bila log terfragmentasi |
| Granularitas event = 1 per aksi UI/API top-level (bukan per internal call invoice/WhatsApp) | assumed: top-level saja | Log bising bila internal ikut di-emit |

---

## Implementation Notes

- Tambah `data-testid` saja — dilarang mengubah logic/handler di `LoginForm.tsx`, `OrderForm.tsx`, `delivery/page.tsx`, `payments/page.tsx`, `admin/orders/page.tsx`.
- Browser test wajib `isDummy=false`; jangan menyentuh flag dummy.
- Lock Playwright ke Chromium (`browserName: 'chromium'` / `projects: [{ name: 'chromium' }]`); larang `firefox`/`webkit` via config (bukan lint).
- External service (WhatsApp dsb.) di-stub; tidak ada network call real keluar.
- Perintah target: `npm run test:e2e` (headless CI) + `test:e2e:headed` (debug lokal); API test tetap via Jest sebelum/sesudah sesuai wiring CI.
- Bukti manual-doc §4 (`PRODUCT_ID`, `ORDER_REF`, `TOTAL_AMOUNT`, dsb.) menjadi nilai runtime yang dicatat di JSONL, bukan fixture hardcode.

---

## Rollback Plan

- Nonaktifkan job E2E di workflow CI (hapus/skip job `playwright-test`) — API test tetap jalan; tidak perlu deploy backend.
- Hapus devDependency `@playwright/test` + direktori `apps/web/e2e` bila browser test harus ditarik; `data-testid` yang sudah ditambah bersifat inert (tidak memengaruhi runtime) sehingga boleh tetap.
- Tidak ada migrasi/schema/RBAC yang berubah — tidak ada rollback data.
