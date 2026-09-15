# Pre-Pilot Feature Compatibility & Readiness

**Date:** 2026-09-14  
**Status:** draft  
**Author:** revision session  
**Original target:** `Concierge Order-to-Payment Production Pilot`

---

## Summary

Dokumen ini **belum mengaktifkan atau menjalankan pilot produksi**. Fase ini menyiapkan platform agar aman dipakai pada fase pilot berikutnya dengan dua fokus:

1. membuktikan kompatibilitas fitur existing order-to-payment; dan
2. menambahkan fitur operasional yang bounded untuk observability, readiness, dan penanganan kegagalan.

Pilot produksi `Pilot Distributor A`/`Territory A`, periode dua minggu, dan minimum 100 order valid tetap menjadi target fase berikutnya—bukan deliverable fase ini.

---

## Context

### Current State

Repository adalah monorepo dengan Laravel API, Next.js web app, PostgreSQL, Redis, dan Docker. Fitur existing yang harus dipertahankan meliputi:

- JWT auth dan role `admin`, `outlet`, `sales`, `driver`, `finance`;
- order creation, idempotency key, stock reservation, credit limit, dan status history;
- approval dan invoice creation yang idempotent;
- delivery assignment, actor ownership, proof-of-delivery, dan state transition;
- payment, partial payment, overpayment guard, cents-safe calculation, row locks, dan payment idempotency;
- WhatsApp catalog/notification/retry dengan provider idempotency dan delivery lease;
- analytics, finance metrics, data pipeline, invoice reminder, territory, dan credit-limit surface;
- Next.js auth helper, sidebar, serta halaman orders, invoices, payments, delivery, analytics, dan data intelligence.

### Problem / Motivation

Rencana sebelumnya langsung membangun persistence, telemetry, KPI, dan UI pilot. Itu terlalu dini: belum ada compatibility baseline yang membuktikan bahwa perubahan tambahan tidak merusak workflow existing. Tim juga belum memiliki readiness gate, correlation ID yang konsisten, atau operational issue view untuk diagnosis sebelum partner nyata diaktifkan.

### Related Areas

- `apps/api/routes/api.php`
- `apps/api/app/Http/Kernel.php`
- `apps/api/app/Http/Controllers/OrderController.php`
- `apps/api/app/Services/OrderCreationService.php`
- `apps/api/app/Http/Controllers/DeliveryController.php`
- `apps/api/app/Services/InvoiceService.php`
- `apps/api/app/Services/PaymentService.php`
- `apps/api/app/Services/WhatsAppOutboundService.php`
- `apps/api/app/Services/DataPipelineService.php`
- `apps/api/app/Models/DataPipelineRun.php`
- `apps/api/app/Models/InvoiceReminder.php`
- `apps/api/app/Models/WhatsAppMessage.php`
- `apps/api/tests/Feature/OrderTest.php`
- `apps/api/tests/Feature/InvoiceTest.php`
- `apps/api/tests/Feature/PaymentConcurrencyTest.php`
- `apps/api/tests/Feature/DeliveryConcurrencyTest.php`
- `apps/api/tests/Feature/WhatsAppTest.php`
- `apps/api/tests/Feature/OperationalReadinessTest.php`
- `apps/web/src/lib/api.ts`
- `apps/web/src/components/Sidebar.tsx`
- `apps/web/src/components/AppShell.tsx`

---

## Scope

### In-Scope

- Inventory dan compatibility contract untuk API, authorization, persistence, concurrency, notification, analytics, dan web existing.
- Regression tests pada public HTTP boundaries untuk order, approval/invoice, delivery, payment, WhatsApp, role access, dan existing analytics.
- Feature flag dan kill switch untuk fitur pre-pilot baru; transaksi existing tidak boleh bergantung pada flag tersebut.
- Correlation ID yang menerima ID valid dari request atau membuat ID baru, mengembalikannya lewat response header, dan tersedia pada operational diagnostics.
- Redacted operational event journal additive untuk request outcome, route/action, actor, status, error class, dan correlation ID; journal ini bukan source transaksi.
- Read-only readiness endpoint untuk memeriksa konektivitas database, konfigurasi dependency, scheduler convention, dan status pipeline existing.
- Read-only operational issue view dari failure state existing (misalnya failed WhatsApp message, failed invoice reminder, dan failed data pipeline run), dengan filter dan redaction.
- Admin web surface untuk readiness dan operational issues yang memakai auth/API/UI conventions existing.
- Runbook pre-pilot, compatibility checklist, rollback/kill-switch procedure, dan definition of done untuk fase pilot berikutnya.

### Out-of-Scope untuk Fase Ini

- Aktivasi partner atau territory nyata.
- Concierge onboarding produksi.
- Pilot window dua minggu dan target minimum 100 order valid.
- Pilot-specific order tagging, immutable pilot event store, KPI denominator pilot, baseline reduction target, dan production pilot metrics.
- Reconciliation atau auto-correction terhadap order, invoice, payment, atau financial ledger.
- Perubahan state machine, role baru, tenant isolation, atau partner-territory authorization baru.
- PWA/mobile/offline, browser/device compatibility matrix, WhatsApp-first ordering, dynamic pricing, external payment/financing, ML/LLM, dan promotion broadcast.
- Penggantian endpoint, response shape, auth abstraction, idempotency policy, retry framework, atau notification provider.

---

## Compatibility Contract

| Fitur existing | Kontrak yang wajib dipertahankan | Bukti kompatibilitas |
|---|---|---|
| Auth dan role | Role tetap `admin`/`outlet`/`sales`/`driver`/`finance`; middleware `auth:api` dan `deny.finance` tetap authoritative; tidak ada role `admin ops` baru | Existing auth/finance-access tests dan forbidden assertions |
| Order API | `POST/GET /api/orders` mempertahankan status code, body, outlet scope, stock/credit behavior, dan idempotency replay/conflict. Reuse key dengan canonical payload berbeda wajib `422` tanpa mengubah order pertama | `OrderTest` + pre-pilot HTTP regression + idempotency hardening |

> Idempotency conflict adalah **compatibility hardening exception**: ini memperketat safety contract yang dituju tanpa mengubah response sukses, state machine, atau perilaku request identik existing.
| Approval/invoice | `PUT /api/orders/{id}/approve` tetap memakai lock, state transition, invoice idempotency, dan notification isolation | `InvoiceTest`, `OperationalReadinessTest`, approval retry |
| Delivery | Assignment, role ownership, proof-of-delivery validation, transition response, dan order status tetap sama | `DeliveryTest`, authorization, concurrency, readiness suite |
| Payment | `POST/GET /api/payments` tetap menjaga partial payment, overpayment, cents-safe balance, lock order, dan idempotency | `PaymentTest`, `PaymentConcurrencyTest`, readiness suite |
| WhatsApp | Provider idempotency, claim lease, retry, failed/sent state, dan webhook semantics tidak diubah | `WhatsAppTest` dan PostgreSQL concurrency suite |
| Analytics/finance/pipeline | Aggregate existing, pagination/filter, scheduler, dan pipeline output tetap tersedia; diagnostics hanya membaca | Existing analytics, finance, pipeline, reminder tests |
| Web | Existing pages/sidebar/auth helper tetap usable; halaman baru hanya menambah navigation admin | Typecheck, existing web tests, new operations page tests |
| Database/config | Tidak ada pilot migration atau backfill pada fase ini; config baru additive dan default-safe | Migration/config review dan rollback check |

> Compatibility di sini berarti kompatibilitas feature dan kontrak platform existing, bukan matriks browser/device.

---

## Additional Features

### P0 — Required Before Pilot

1. **Feature flag + kill switch**
   - Fitur pre-pilot operational surface default off untuk environment yang belum diaktifkan.
   - Kill switch mematikan readiness/issues surface dan diagnostic capture tambahan tanpa menghentikan order, invoice, delivery, payment, atau WhatsApp existing.
   - Perubahan flag dapat diaudit melalui application log tanpa menyimpan secret.

2. **Compatibility regression gate**
   - Existing regression suite harus green sebelum deploy.
   - Pilot/pre-pilot code tidak boleh mengubah output existing hanya agar test baru lulus.
   - Compatibility gate dijalankan setelah perubahan pada controller/service/config yang disentuh fase ini.

3. **Correlation ID dan operational event journal**
   - Request dengan `X-Correlation-ID` valid mempertahankan ID tersebut; request tanpa ID mendapat ID baru.
   - Response mengembalikan `X-Correlation-ID`.
   - Saat feature aktif, outcome request dicatat pada journal additive bersama route/action, actor, status, error class, dan timestamp.
   - Journal tidak menyimpan body/token/password/payment credential; secret wajib diredaksi.

4. **Readiness checklist**
   - Admin dapat melihat status database, application config, scheduler convention, WhatsApp configuration, dan latest data-pipeline state.
   - Readiness bersifat read-only dan tidak memicu order/payment/pipeline mutation.
   - Status `ready`, `warning`, atau `blocked` disertai evidence dan remediation.

### P1 — Recommended Operational Additions

5. **Operational issue inbox**
   - Membaca failure state existing dari WhatsApp messages, invoice reminders, dan data pipeline runs.
   - Filter berdasarkan source, status, severity, waktu, dan correlation ID dari operational journal bila tersedia.
   - Tidak menambahkan state machine paralel; retry tetap memakai action existing.

6. **Correlation/issue detail view**
   - Admin dapat mencari correlation ID pada operational journal dan membuka detail issue untuk melihat source, actor/reference yang aman, timestamps, attempts, error class, dan next action.
   - Payload sensitif tidak ditampilkan.

7. **Pre-pilot runbook dan evidence checklist**
   - Menetapkan owner, approval, rollback, test command, readiness evidence, dan exit criteria sebelum pilot berikutnya.

---

## User Stories and Scenarios

### Story: Existing workflow tetap kompatibel

> As a product owner, I want fitur tambahan tidak mengubah perilaku existing, so that rollout pre-pilot tidak merusak operasi berjalan.

```gherkin
Scenario: Non-pilot order tetap memakai kontrak existing
  Given feature pre-pilot off dan outlet membuat order valid
  When client memanggil POST /api/orders dengan idempotency key
  Then status code, response shape, stock reservation, credit validation, status history, dan idempotency behavior tetap sama
  And tidak ada pilot/pre-pilot record yang mengubah order

Scenario: Approval retry tetap idempotent
  Given order berstatus New
  When admin memanggil approval dua kali
  Then hanya satu Confirmed status history dan satu invoice tercipta
  And notification failure tidak membatalkan committed order

Scenario: Payment concurrency tetap aman
  Given invoice memiliki outstanding balance terbatas
  When dua payment diproses bersamaan
  Then row lock dan payment policy existing menentukan hasil
  And saldo tidak negatif atau ter-posting ganda

Scenario: Permission existing tidak berubah
  Given actor tidak memiliki permission untuk action
  When actor mencoba approval, delivery mutation, atau payment action
  Then response forbidden/validation mengikuti behavior existing
  And tidak ada source transaction mutation
```

### Story: Controls dapat dimatikan dengan aman

```gherkin
Scenario: Feature flag default off
  Given environment belum mengaktifkan pre-pilot operational features
  When client memanggil workflow order-to-payment existing
  Then workflow tetap berjalan
  And readiness/issues surface tidak dapat digunakan

Scenario: Kill switch tidak menghentikan transaksi existing
  Given operational feature sebelumnya aktif
  When admin/platform mengaktifkan kill switch
  Then readiness/issues/diagnostic capture tambahan berhenti atau menjadi disabled
  And order, approval, invoice, delivery, payment, reminder, dan WhatsApp existing tetap dapat diproses
```

### Story: Readiness dapat diverifikasi sebelum pilot

```gherkin
Scenario: Readiness blocked karena dependency/configuration
  Given database, WhatsApp configuration, atau pipeline state tidak memenuhi checklist
  When admin membuka readiness endpoint
  Then response berstatus blocked atau warning dengan evidence dan remediation
  And endpoint tidak melakukan mutation

Scenario: Readiness siap
  Given semua required checks lulus dan feature flag aktif
  When admin membuka readiness endpoint
  Then response berstatus ready dengan timestamp, check names, dan correlation ID
```

### Story: Failure dapat dioperasikan

```gherkin
Scenario: Issue inbox menggabungkan failure existing
  Given terdapat failed WhatsApp message dan failed invoice reminder
  When admin membuka issue inbox dengan filter status failed
  Then kedua issue terlihat dengan source, severity, attempts, timestamp, dan next action
  And data source tetap tidak berubah

Scenario: Detail issue diredaksi
  Given failure menyimpan phone, token, atau provider error payload
  When admin membuka detail issue
  Then secret/token/password/payment credential tidak tampil
  And correlation ID serta error class yang aman tetap tersedia
```

---

## Acceptance Criteria

```text
Rule: Existing feature compatibility
  ✓ Given pre-pilot features off, When existing order/approval/invoice/delivery/payment/WhatsApp flow is exercised, Then existing response, permission, state, idempotency, locking, retry, and source data behavior remain compatible.
  ✓ Given an idempotency key has been committed for one canonical order payload, When the same key is submitted with a different payload, Then API returns `422` conflict and the committed order, stock, credit, and history remain unchanged.
  ✓ Given an idempotency key is replayed with the same canonical payload, When the request is repeated, Then the original resource is returned without duplicate source records.
  ✓ Given existing regression tests are run, When any regression fails, Then the pre-pilot change is not considered ready and no pilot activation is allowed.
  ✓ Given a non-authorized actor calls an existing mutation, When the request is processed, Then it remains forbidden/unchanged according to existing authorization.

Rule: Safe controls
  ✓ Given the new feature flag defaults off, When normal transactions run, Then transactions remain available and new operational surfaces are disabled.
  ✓ Given kill switch is enabled, When normal transactions run, Then only additional diagnostics/readiness/issues behavior is disabled; source workflow remains available.

Rule: Correlation ID
  ✓ Given a request has a valid X-Correlation-ID, When it completes or fails, Then the same ID is returned and is available in safe diagnostic context.
  ✓ Given a request has no valid ID, When it completes, Then the API generates and returns one without exposing secrets.

Rule: Readiness
  ✓ Given required readiness checks fail, When admin requests readiness, Then status is blocked/warning with evidence/remediation and no mutation occurs.
  ✓ Given all required checks pass, When admin requests readiness, Then status is ready and response includes check results and correlation ID.

Rule: Operational issue view
  ✓ Given existing notification/reminder/pipeline failures exist, When admin filters issue inbox, Then matching issues are listed with source, status, severity, attempts, timestamp, and safe next action.
  ✓ Given a correlation ID exists in the operational journal, When admin searches it, Then request outcome and safe issue references are shown in time order.
  ✓ Given an issue contains sensitive payload, When its detail is viewed, Then sensitive values are redacted and source rows are not mutated.

Rule: Pre-pilot boundary
  ✓ Given this phase is executed, When implementation is reviewed, Then no partner is activated, no 100-order KPI is claimed, no pilot-specific transaction event store is required, and no financial source ledger is changed.
```

---

## Architecture Constraints

- **Allowed layers:** existing Laravel controllers/services/middleware/config, read-only operational query services, existing Next.js operational surfaces, tests, logging, and documentation.
- **Forbidden layers:** new mobile architecture, external payment/financing, ML/LLM, tenant isolation, parallel transaction workflow, destructive migration, and financial-ledger mutation.
- **Must preserve:** service orchestration, FormRequest validation, existing auth/authorization, transactions, row locks, status history, idempotency, retry/lease, notification provider contract, and payment policy.
- **No new dependency:** do not add an auth, retry, validation, observability, or export library for this phase.
- **Read-only diagnostics:** readiness and issue queries may read existing rows but must not update order, invoice, payment, delivery, reminder, WhatsApp, or pipeline source state. An additive redacted operational journal is allowed as evidence, but is never transaction truth.
- **Flag isolation:** normal source workflows must not require the new feature flag to be enabled.
- **Response compatibility:** new response headers are allowed; existing JSON response bodies, status codes, and routes must not be silently changed, except the explicit idempotency conflict hardening rule documented in the spec.
- **Idempotency fingerprint:** the committed canonical payload fingerprint must be compared before replay; a different payload with the same client identity is rejected without source mutation.

---

## Design Decision

**Chosen option:** Compatibility-first pre-pilot hardening.

**Decision:** Pause production-pilot implementation. First establish an executable compatibility contract and regression suite, then add only operational controls that are additive, read-only where possible, reversible, and independently kill-switchable. The production pilot becomes a later plan that can start only after this plan reaches `READY_FOR_PILOT`.

**Rejected options:**

- **Build pilot telemetry/KPI persistence immediately:** rejected because it expands schema and source boundaries before existing compatibility is proven.
- **Create a separate pilot operations module:** rejected because it risks a second source of truth and is unnecessary for pre-pilot readiness.
- **Use manual testing only:** rejected because idempotency, concurrency, authorization, and response compatibility need repeatable regression evidence.

**Accepted tradeoff:** A small amount of additive middleware/config/API/UI work is accepted now to make failure diagnosis and activation safety explicit. A targeted idempotency conflict hardening change is also accepted because silently replaying a different payload is unsafe; no partner-facing or financial behavior changes are otherwise accepted.

---

## Dependencies

### Existing to Leverage

- Laravel 11 middleware, config, controllers, services, scheduler, logging, and PHPUnit test stack.
- Existing JWT/auth and finance authorization services.
- Existing `DataPipelineRun`, `InvoiceReminder`, `WhatsAppMessage`, and their failure/retry fields.
- Existing order, invoice, delivery, payment, WhatsApp, analytics, and operational readiness tests.
- Next.js, React, Jest, Testing Library, `apiUrl`, `authHeaders`, and `Sidebar` conventions.

### New

None.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if wrong |
|---|---|---|
| Apa arti compatibility? | Confirmed: kompatibilitas fitur/kontrak platform existing, bukan browser/device matrix. | Scope berubah bila dibutuhkan compatibility lintas device/browser. |
| Kapan pilot dilakukan? | Deferred: setelah fase ini berstatus `READY_FOR_PILOT`; tanggal, partner, territory, dan 100-order window belum ditetapkan. | KPI/pilot plan berikutnya belum dapat dijalankan. |
| Siapa approver kill switch? | Assumed: platform admin; semua perubahan flag dicatat. | Fitur tidak dapat dimatikan cepat saat incident. |
| Apa severity issue? | Assumed: `critical` untuk blocked source workflow, `warning` untuk retryable/degraded, `info` untuk resolved/history. | Prioritas operasional perlu dikalibrasi. |
| Bagaimana readiness memeriksa scheduler? | Assumed: memvalidasi konfigurasi dan convention `Asia/Jakarta`/`withoutOverlapping`, bukan menjalankan scheduler dari HTTP request. | Status scheduler mungkin belum membuktikan worker hidup. |
| Retention/redaction? | Assumed: ikuti logging/retention policy existing; secret/token/password/payment credential selalu redacted. | Compliance atau diagnosis mungkin belum cukup. |

---

## Rollback / Stop Conditions

- Matikan feature flag atau kill switch; jangan menghapus atau mengubah source transaction.
- Revert route/UI operational surface bila menyebabkan regresi; endpoint existing harus tetap tersedia.
- Jika compatibility test gagal, hentikan aktivasi dan buka issue sampai root cause diselesaikan.
- Jika diagnostics ternyata perlu menulis source state, stop dan desain ulang sebagai derived/read model.
- Fase ini selesai hanya jika seluruh acceptance criteria terpenuhi, existing relevant suite green, dan runbook menyatakan bukti `READY_FOR_PILOT`.

---

## Definition of Done untuk Fase Pre-Pilot

- Compatibility matrix dan baseline evidence tersimpan.
- Existing API/UI/authorization/concurrency tests tetap green.
- Same-key/different-payload idempotency conflict is enforced without changing the committed order.
- Feature flag dan kill switch teruji tidak memutus transaksi normal.
- Correlation ID tersedia tanpa sensitive-data leakage.
- Readiness endpoint dan operational issue view tersedia untuk admin existing.
- Web surface memakai helper/auth conventions existing.
- Runbook, rollback, owner, dan gate `READY_FOR_PILOT` terdokumentasi.
- Tidak ada klaim bahwa pilot produksi atau adoption 100 order telah terjadi.
