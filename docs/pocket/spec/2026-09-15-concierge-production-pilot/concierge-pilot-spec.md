# Concierge Production Pilot — Phase 6 Business Validation

**Date:** 2026-09-15
**Status:** draft
**Author:** grinding session
**Spec path:** docs/pocket/spec/2026-09-15-concierge-production-pilot/concierge-pilot-spec.md

---

## Summary

Pilot produksi pertama untuk memvalidasi workflow order-to-payment pada satu partner dan satu territory menggunakan platform DDP yang sudah ada. Tim internal menjalankan concierge pilot selama 1 minggu (extendable +3 hari), dengan target minimum 20 order valid dan KPI utama berupa pengurangan waktu pemrosesan sebesar 30% dari baseline manual. Hasil pilot menjadi bukti untuk Phase 7 MVP completion.

---

## Context

### Current State
- DDP memiliki fondasi lengkap: order, approval, delivery, invoice, payment, WhatsApp notifications
- Pre-pilot compatibility & readiness sudah selesai (REVIEW_PASS)
- Fitur yang tersedia: JWT auth, order creation, idempotency, stock reservation, credit limit, status history, approval, invoice, delivery, payment, partial payment, WhatsApp notifications, operational readiness API, admin web surface
- Belum ada partner aktif, tidak ada data produksi, tidak ada KPI baseline

### Problem / Motivation
Platform secara teknis sudah luas, tetapi belum terbukti mempercepat distribusi nyata pada partner dan transaksi produksi. Pilot pertama harus membuktikan bahwa workflow order-to-payment di platform lebih cepat dari baseline manual, dengan data yang kredibel.

### Related Areas
- `apps/api/app/Http/Controllers/OrderController.php`
- `apps/api/app/Services/OrderCreationService.php`
- `apps/api/app/Http/Controllers/DeliveryController.php`
- `apps/api/app/Services/InvoiceService.php`
- `apps/api/app/Services/PaymentService.php`
- `apps/api/app/Http/Controllers/AuthController.php`
- `apps/api/routes/api.php` (pre-pilot admin operations)
- `apps/web/src/app/orders/` (order UI)
- `apps/web/src/app/delivery/` (delivery UI)
- `apps/web/src/app/invoices/` (invoice UI)

---

## Scope

### In-Scope
- Kriteria pemilihan partner pilot (10 outlet aktif, PIC ditunjuk, dokumentasi baseline)
- Concierge pilot execution: tim internal login sebagai outlet, input order via web app
- Baseline waktu manual: POST orders → status Delivered
- Target: 1 minggu (extendable +3 hari), minimum 20 order valid
- KPI utama: delta -30% waktu vs baseline
- KPI guardrail: error rate <5%, delivery success >95%, payment completion >90%
- Monitoring harian dan evaluasi akhir
- Decision matrix: scale-up, iterate, atau stop
- Evidence documentation untuk Phase 7

### Out-of-Scope
- Rollout multi-partner
- WhatsApp-first ordering (WhatsApp tetap aktif untuk notifications, tapi bukan channel order pilot)
- PWA/mobile/offline
- Fitur baru significant sebelum pilot
- Klaim dari fixture/demo
- Phase 7-10 eksekusi langsung

---

## Architecture Constraints

- **Layers this work may touch:** existing Laravel controllers/services, existing Next.js pages, operational events (additive), correlation ID
- **Layers this work must NOT touch:** order/invoice/payment/delivery source state (no mutations), financial ledger, schema migrations, new roles
- **Patterns that must be followed:** existing auth flow (concierge login sebagai outlet), existing order creation flow, existing delivery confirmation flow
- **Architecture validation result:** PASS

---

## Dependencies

### Existing (to leverage)
- Laravel 11 + JWT auth — concierge login sebagai outlet
- OrderController + OrderCreationService — order creation dengan idempotency
- DeliveryController — delivery confirmation
- InvoiceService + PaymentService — invoice dan payment processing
- OperationalReadinessController — readiness check sebelum pilot
- OperationalEventService — event logging untuk audit trail
- Correlation ID middleware — request tracing
- Feature flag + kill switch — safe controls

### New (proposed)
none

---

## Stories + Scenarios

### Story 1: Partner Selection

> As a product owner, I want partner pilot terpilih berdasarkan kriteria yang jelas, so that partner memiliki kondisi operasional yang memungkinkan validasi workflow.

**Rule 1: Partner harus memiliki minimal 10 outlet aktif**
- Example A: 15 outlet dengan order dalam 30 hari → Qualify
- Example B: 3 outlet dengan order dalam 30 hari → Tidak qualifikasi

**Rule 2: Partner harus memiliki dokumentasi proses order manual**
- Example A: Proses order manual terdokumentasi (waktu rata-rata, langkah-langkah) → Baseline tercatat
- Example B: Tidak ada dokumentasi → Tidak qualifikasi

**Rule 3: Partner harus menunjuk PIC untuk koordinasi harian**
- Example A: PIC ditunjuk, tersedia 08:00-17:00 WIB → Qualify
- Example B: Tidak ada PIC → Tidak qualifikasi

**Rule 4: Partner harus memiliki akses internet yang stabil**
- Example A: Internet stabil, tidak ada outage signifikan → Qualify
- Example B: Internet sering putus → Tidak qualifikasi

```gherkin
Scenario: Partner memenuhi kriteria pilot
  Given partner memiliki 15 outlet dengan order dalam 30 hari terakhir
  And partner memiliki dokumentasi proses order manual
  And partner menunjuk PIC yang tersedia 08:00-17:00 WIB
  And partner memiliki akses internet stabil
  When tim melakukan evaluasi kriteria partner
  Then partner dinyatakan memenuhi kriteria untuk pilot

Scenario: Partner tidak memenuhi kriteria - outlet kurang
  Given partner memiliki 5 outlet dengan order dalam 30 hari terakhir
  When tim melakukan evaluasi kriteria partner
  Then partner dinyatakan tidak memenuhi kriteria
  And tim memberikan rekomendasi untuk mencari partner lain
```

---

### Story 2: Concierge Pilot Execution

> As a tim internal, I want menjalankan concierge pilot selama 1 minggu dengan minimum 20 order valid, so that kita membuktikan waktu pemrosesan lebih cepat dari baseline.

**Rule 1: Tim concierge login sebagai outlet dan input order via web app**
- Example A: Concierge login sebagai outlet, buat order → Order tercatat dengan actor = outlet
- Example B: Concierge logout, login sebagai outlet lain → Audit trail benar

**Rule 2: Baseline diukur dari POST orders → status Delivered**
- Example A: Baseline manual: 2 hari (48 jam) dari order sampai delivered → Baseline tercatat
- Example B: Platform: 4 jam dari order sampai delivered → Waktu tercatat

**Rule 3: Minimum 20 order valid harus diselesaikan dalam 1 minggu**
- Example A: 25 order valid selesai → Target terpenuhi
- Example B: 15 order valid selesai → Extend 3 hari

**Rule 4: Semua order harus melalui workflow end-to-end**
- Example A: Order → Approved → Delivered → Invoiced → Paid → Complete
- Example B: Order → Cancelled → Tidak dihitung dalam valid orders

```gherkin
Scenario: Concierge membuat order sebagai outlet
  Given concierge login sebagai outlet "Warung Sejahtera"
  When concierge membuat order dengan produk A, qty 10
  Then order tercatat dengan status "New"
  And actor_id = outlet "Warung Sejahtera"
  And correlation ID tercatat di operational event

Scenario: Order berhasil sampai delivered
  Given order dengan status "New"
  When admin approve order
  Then status berubah menjadi "Confirmed"
  And invoice terbuat
  When delivery selesai
  Then status berubah menjadi "Delivered"
  And waktu dari order.created_at ke delivered_at tercatat

Scenario: Order dibatalkan sebelum approval
  Given order dengan status "New"
  When admin cancel order
  Then status berubah menjadi "Cancelled"
  And order tidak dihitung dalam valid orders
  Dan delivery success denominator tidak terpengaruh
```

---

### Story 3: KPI Monitoring

> As a product owner, I want memantau KPI kecepatan dan keandalan selama pilot, so that kita tahu apakah pilot berhasil atau perlu penyesuaian.

**Rule 1: KPI utama = delta waktu rata-rata vs baseline**
- Example A: Baseline 48 jam, platform 4 jam → Delta: -91.7% (target -30% terpenuhi)
- Example B: Baseline 48 jam, platform 36 jam → Delta: -25% (target -30% tidak terpenuhi)

**Rule 2: KPI guardrail: error rate <5%, delivery success >95%, payment completion >90%**
- Example A: Error 1/25=4%, delivery 24/25=96%, payment 23/24=95.8% → Guardrail terpenuhi
- Example B: Error 2/25=8%, delivery 22/25=88%, payment 20/22=90.9% → Guardrail dilanggar

**Rule 3: Data dikumpulkan harian dan dilaporkan**
- Example A: Hari 1: 5 order, 0 error, 4 delivered → Dilaporkan
- Example B: Hari 3: 15 order, 2 error, 12 delivered → Dilaporkan

**Rule 4: Jika guardrail dilanggar, pilot dihentikan untuk evaluasi**
- Example A: Error rate 8% → Pilot dihentikan, evaluasi akar masalah
- Example B: Delivery success 88% → Pilot dihentikan, evaluasi akar masalah

```gherkin
Scenario: KPI kecepatan terpenuhi
  Given baseline waktu manual: 48 jam
  And waktu rata-rata platform: 4 jam
  When evaluasi kecepatan dilakukan
  Then delta waktu: -91.7%
  And target -30% terpenuhi

Scenario: KPI keandalan terpenuhi
  Given 25 order valid diselesaikan
  When menghitung guardrail
  Then error rate: 4% (<5%)
  And delivery success: 96% (>95%)
  And payment completion: 95.8% (>90%)

Scenario: Guardrail dilanggar - error rate tinggi
  Given pilot sedang berjalan
  When error rate mencapai 8%
  Then pilot dihentikan untuk evaluasi
  And akar masalah diidentifikasi
  And rekomendasi perbaikan dibuat

Scenario: Volume tidak tercapai - extend
  Given hari ke-7 hanya 14 order valid
  When evaluasi volume dilakukan
  Then pilot di-extend 3 hari
  And target tetap 20 order valid
```

---

### Story 4: Evaluasi & Keputusan

> As a product owner, I want mengevaluasi hasil pilot dengan data yang valid, so that kita bisa memutuskan apakah scale-up, iterasi, atau hentikan.

**Rule 1: Evaluasi dilakukan setelah pilot selesai**
- Example A: Pilot 1 minggu selesai, 25 order → Evaluasi
- Example B: Pilot extend 3 hari selesai, 22 order → Evaluasi

**Rule 2: Keputusan berdasarkan KPI terukur**
- Example A: Semua KPI terpenuhi → Scale-up
- Example B: Sebagian KPI terpenuhi → Iterate
- Example C: KPI tidak terpenuhi → Stop

**Rule 3: Hasil didokumentasikan untuk Phase 7**
- Example A: Scale-up → Dokumentasi case study untuk partner lain
- Example B: Iterate → Dokumentasi lessons learned

```gherkin
Scenario: Evaluasi akhir pilot - scale up
  Given pilot selesai selama 1 minggu
  And 25 order valid diselesaikan
  And delta kecepatan -91.7% (target -30% terpenuhi)
  And error rate 4% (<5%)
  And delivery success 96% (>95%)
  And payment completion 95.8% (>90%)
  When evaluasi akhir dilakukan
  Then keputusan scale-up diambil
  And hasil didokumentasikan sebagai case study

Scenario: Evaluasi akhir pilot - iterasi
  Given pilot selesai selama 1 minggu
  And 20 order valid diselesaikan
  And delta kecepatan -25% (target -30% tidak terpenuhi)
  And error rate 3% (<5%)
  And delivery success 97% (>95%)
  And payment completion 92% (>90%)
  When evaluasi akhir dilakukan
  Then keputusan iterate diambil
  And perbaikan teridentifikasi untuk pilot berikutnya

Scenario: Evaluasi akhir pilot - stop
  Given pilot selesai selama 1 minggu
  And 18 order valid diselesaikan
  And error rate 8% (>5%)
  And delivery success 89% (<95%)
  When evaluasi akhir dilakukan
  Then keputusan stop diambil
  And akar masalah diidentifikasi
  And rekomendasi untuk iterasi signifikan dibuat
```

---

## Acceptance Criteria

```
Rule: Partner selection
  ✓ Given partner memiliki 10+ outlet aktif (order 30 hari), When evaluasi, Then qualifikasi
  ✓ Given partner memiliki dokumentasi baseline, When evaluasi, Then baseline tercatat
  ✓ Given partner menunjuk PIC 08:00-17:00, When evaluasi, Then qualifikasi
  ✗ Given partner memiliki <10 outlet aktif, When evaluasi, Then tidak qualifikasi

Rule: Concierge execution
  ✓ Given concierge login sebagai outlet, When buat order, Then order tercatat dengan actor = outlet
  ✓ Given baseline manual 48 jam, When platform 4 jam, Then delta tercatat
  ✓ Given 20+ order valid selesai, When pilot selesai, Then target volume terpenuhi
  ✗ Given order cancelled, When evaluasi, Then tidak dihitung dalam valid orders

Rule: KPI monitoring
  ✓ Given delta kecepatan -91.7%, When evaluasi, Then target -30% terpenuhi
  ✓ Given error rate <5%, delivery >95%, payment >90%, When evaluasi, Then guardrail terpenuhi
  ✓ Given guardrail dilanggar, When evaluasi, Then pilot dihentikan
  ✗ Given error rate >5%, When evaluasi, Then pilot dihentikan untuk evaluasi

Rule: Evaluation
  ✓ Given semua KPI terpenuhi, When evaluasi, Then keputusan scale-up
  ✓ Given sebagian KPI terpenuhi, When evaluasi, Then keputusan iterate
  ✓ Given KPI tidak terpenuhi, When evaluasi, Then keputusan stop
  ✗ Given hasil tidak terdokumentasi, When evaluasi, Then tidak ada bukti untuk Phase 7
```

---

## Design Decision

**Chosen option:** Option A — Pilot Minimum Viable

**Summary:** Pilot 1 minggu (extendable +3 hari), 1 partner, 1 territory, concierge login sebagai outlet, target 20 order valid, delta -30% vs baseline, web app only.

**Rejected options:**
- Option B (WhatsApp Bridge): rejected because parsing order dari WhatsApp rentan error dan bisa mengaburkan KPI error rate
- Option C (Extended 4 minggu): rejected because terlalu lama untuk bukti bisnis awal, partner mungkin bosan

**Key tradeoffs accepted:**
- Concierge login sebagai outlet berarti adoption metric sedikit bias, tetapi ini tradeoff wajar untuk pilot pertama yang fokus pada kecepatan proses
- Web app only berarti outlet harus login, tetapi lebih mudah diukur dan dikontrol
- Durasi 1 minggu mungkin tidak cukup untuk data statistik yang kuat, tetapi cukup untuk validasi awal

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Bagaimana jika internet down saat pilot? | Assumed: pilot di-pause, waktu tidak dihitung | Data waktu bisa tidak akurat |
| Apakah concierge boleh retry order yang gagal? | Assumed: retry adalah percobaan baru, bukan order baru | Error rate bisa inflated |
| Bagaimana jika PIC tidak tersedia? | Assumed: ada backup PIC, atau pilot di-pause | Approval bottleneck |
| Apakah pilot boleh diluar jam kerja? | Assumed: hanya jam kerja 08:00-17:00 WIB | Waktu delivery bisa lebih lama |
| Bagaimana jika ada order in-transit di akhir pilot? | Assumed: tidak dihitung dalam KPI, dilanjutkan post-pilot | Delivery success denominator berkurang |

---

## Implementation Notes

- Concierge harus login sebagai outlet yang sudah terdaftar di partner pilot
- Setiap order harus memiliki correlation ID untuk audit trail
- Operational event harus mencatat: order created, approved, delivered, invoiced, paid
- Readiness check harus dilakukan sebelum pilot dimulai
- Feature flag pre-pilot operational features harus aktif
- Kill switch harus tersedia jika pilot perlu dihentikan mendadak

---

## Rollback Plan

- Hentikan pilot, tidak ada perubahan schema
- Operational events bersifat additive, tidak mengubah source data
- Correlation ID dan event logging bisa dimatikan via feature flag
- Jika pilot gagal, cukup dokumentasi lessons learned untuk iterasi berikutnya
- Tidak ada dampak ke operasi normal platform
