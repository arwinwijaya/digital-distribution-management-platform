# Dokumentasi Teknis — Digital Distribution Management Platform (DDP)

> Audiens: developer, DevOps, QA, arsitek solusi.
> Dokumen pendamping non-teknis: `docs/panduan-pengguna.md`.
> Status kode yang didokumentasikan: monorepo per September 2026 (Phase 1–2 selesai, T1–T9 tereksekusi).

---

## Daftar Isi

1. [Ringkasan Sistem](#1-ringkasan-sistem)
2. [Arsitektur & Tech Stack](#2-arsitektur--tech-stack)
3. [Struktur Monorepo](#3-struktur-monorepo)
4. [Layanan Infrastruktur (Docker)](#4-layanan-infrastruktur-docker)
5. [Backend — Laravel API](#5-backend--laravel-api)
6. [Model Data & Relasi](#6-model-data--relasi)
7. [Referensi API Endpoint](#7-referensi-api-endpoint)
8. [Aturan Bisnis Penting](#8-aturan-bisnis-penting)
9. [Autentikasi & Otorisasi](#9-autentikasi--otorisasi)
10. [Integrasi WhatsApp](#10-integrasi-whatsapp)
11. [Frontend — Next.js Web](#11-frontend--nextjs-web)
12. [Shared Package](#12-shared-package)
13. [Konfigurasi Environment](#13-konfigurasi-environment)
14. [Database & Migrasi](#14-database--migrasi)
15. [Testing & CI/CD](#15-testing--cicd)
16. [Menjalankan Secara Lokal](#16-menjalankan-secara-lokal)
17. [Observability, Batasan & Catatan Teknis](#17-observability-batasan--catatan-teknis)

---

## 1. Ringkasan Sistem

DDP adalah ekosistem distribusi FMCG digital yang menghubungkan **supplier/brand principal → platform → outlet/warung → konsumen**. Fokusnya bukan sekadar e-commerce, melainkan **manajemen jaringan distribusi, intelijen distribusi, dan optimasi penjualan**.

Alur transaksi inti (hyperedge `core_transaction_flow` di knowledge graph):

```
Outlet registrasi → browsing katalog → buat order → admin approve
→ delivery (assign driver → in_progress → delivered/failed)
→ payment → order lunas (paid)
```

Alur ini didukung modul: Outlet, Produk/Marketplace, Order, Delivery, Payment & Credit Limit, Sales Visit, Analytics/AI, dan WhatsApp.

---

## 2. Arsitektur & Tech Stack

| Lapisan | Teknologi | Lokasi |
|---|---|---|
| Frontend | Next.js 14, React, TypeScript, Tailwind CSS, Zustand, Axios | `apps/web/` |
| Backend | Laravel 11, PHP 8.2, JWT (`tymon/jwt-auth`) | `apps/api/` |
| Database | PostgreSQL 16 | service `db` (docker-compose) |
| Cache / Queue | Redis 7 | service `redis` |
| Kontrak tipe | TypeScript shared package (`@ddp/shared`) | `packages/shared/` |
| Container | Docker + Docker Compose (4 service: `db`, `redis`, `api`, `web`) | `docker-compose.yml` |
| CI | GitHub Actions: API tests (PHPUnit), Web tests (Jest), Docker build | `.github/workflows/ci.yml` |
| AI | Deterministik & bounded (tanpa LLM eksternal): rekomendasi, forecast, segmentasi berbasis riwayat order | `ForecastService`, `RecommendationService`, `SegmentationService` |
| Kanal pesan | WhatsApp Cloud API (`graph.facebook.com/v20.0`) via HTTP client + webhook HMAC | `WhatsAppService` dkk. |

Pola arsitektur backend: **Controller (tipis) → Service (logika bisnis) → Eloquent Model**. Controller hanya validasi request (FormRequest), otorisasi peran, dan delegasi ke service. Tidak ada logika bisnis di controller maupun di model selain relasi/cast/state-machine kecil.

---

## 3. Struktur Monorepo

```
├── apps/
│   ├── api/                  # Laravel 11 backend (118 file PHP)
│   │   ├── app/
│   │   │   ├── Http/Controllers/   # 22 controller (AI, Analytics, Auth, CreditLimit,
│   │   │   │                       #   DataPipeline, Delivery, FinanceMetrics, FinanceRole,
│   │   │   │                       #   GeographicAnalytics, Invoice, InvoiceReminder,
│   │   │   │                       #   Marketplace, Measurement, Order, Outlet, Payment,
│   │   │   │                       #   Product, Sales, StockPlanning, SupplierPerformance,
│   │   │   │                       #   Territory, WhatsApp)
│   │   │   ├── Http/Requests/      # 8 FormRequest validasi
│   │   │   ├── Http/Middleware/    # Authenticate, ValidateSignature (HMAC WA), denyFinance, dsb.
│   │   │   ├── Models/             # 22 Eloquent model
│   │   │   ├── Services/           # 30 service logika bisnis
│   │   │   ├── Contracts/          # Interface WhatsAppClient
│   │   │   └── Support/            # ConcurrencyTestBarrier (khusus test)
│   │   ├── config/                 # app, auth, database, jwt, orders, whatsapp
│   │   ├── database/migrations/    # 35 migrasi
│   │   ├── routes/api.php          # ±60 route
│   │   └── tests/Feature/          # 13 test suite
│   ├── web/                  # Next.js 14 frontend
│   │   └── src/
│   │       ├── app/                # 13 route halaman (lihat §11)
│   │       ├── components/         # Charts, LoginForm, MarketplaceCatalog,
│   │       │                       #   OrderForm, OutletForm, ProductCatalog,
│   │       │                       #   AppShell, Sidebar, Topbar, ui/* (13 komponen)
│   │       ├── components/data-intelligence/  # GeoMap, MeasurementCards,
│   │       │                       #   StockPlanningTable, SupplierPerformanceTable,
│   │       │                       #   TerritoryTable
│   │       └── lib/                # api.ts, data-intelligence-api.ts,
│   │                               #   data-intelligence-types.ts
├── packages/shared/          # Tipe TS bersama (User, Product, Order, ApiResponse, …)
├── docs/                     # Spesifikasi, execution plan T1–T9, load-test report
└── docker-compose.yml
```

---

## 4. Layanan Infrastruktur (Docker)

`docker-compose.yml` mendefinisikan 4 service:

| Service | Image / Build | Port | Keterangan |
|---|---|---|---|
| `db` | `postgres:16-alpine` | 5432 | DB `ddp_database`, user `ddp_user`; volume `postgres_data`; healthcheck `pg_isready` |
| `redis` | `redis:7-alpine` (appendonly) | 6379 | volume `redis_data`; healthcheck `redis-cli ping` |
| `api` | build `apps/api/Dockerfile` | 8000 | mount `./apps/api`; `depends_on` db+redis (healthy); env DB pgsql + REDIS_HOST |
| `web` | build `apps/web/Dockerfile` | 3000 | mount `./apps/web`; env `NEXT_PUBLIC_API_URL=http://localhost:8000/api`; `depends_on` api |

---

## 5. Backend — Laravel API

### 5.1 Controller (22)

| Controller | Method | Tanggung jawab |
|---|---|---|
| `AuthController` | `registerOutlet`, `login`, `me`, `logout`, `refresh` | Registrasi outlet (buat User+Outlet sekaligus), JWT login/me/logout/refresh |
| `OutletController` | `store`, `showPaymentTerms`, `updatePaymentTerms` | Registrasi outlet, kelola payment terms per outlet |
| `ProductController` | `index` | Katalog produk legacy (auth-only) |
| `MarketplaceController` | `suppliers`, `products` | Listing supplier aktif + produk multi-supplier, paginasi + filter status |
| `OrderController` | `store`, `index`, `show`, `approve`, `cancel` | Buat order (via `OrderCreationService`), list (admin), detail, approve, cancel |
| `DeliveryController` | `index`, `store`, `show`, `updateStatus` | Assign delivery ke driver, lifecycle status auditabel |
| `PaymentController` | `store`, `index` | Catat pembayaran (via `PaymentService`), riwayat |
| `CreditLimitController` | `show`, `update` | Lihat/update limit kredit outlet (admin) |
| `SalesController` | `index`, `store`, `show`, `update` | Perencanaan & histori kunjungan sales (scope ke sales bersangkutan) |
| `AnalyticsController` | `dashboard` | Metrik owner: outlet, order, sales, produk terlaris, dsb. |
| `AIController` | `recommendations`, `forecast`, `segmentation` | Analitik deterministik & bounded (lihat §8.5) |
| `WhatsAppController` | `webhook` (publik, verifikasi HMAC), `catalog`, `notify`, `retry` | Inbound order via WA, share katalog, notifikasi, retry |
| `InvoiceController` | `index` | Riwayat invoice per outlet (admin melihat lintas outlet) |
| `InvoiceReminderController` | `index` | Daftar invoice jatuh tempo (finReminder: `InvoiceReminderCandidateSelector`) |
| `FinanceRoleController` | `assign`, `remove`, `access` | Admin assign/hapus role finance ke user, cek akses finance |
| `FinanceMetricsController` | `index` | Agregasi metrik keuangan (total receivable, overdue, payment ratio, dll.) |
| `DataPipelineController` | `status`, `manualTrigger` | Status pipeline data intelijen, trigger manual run |
| `GeographicAnalyticsController` | `index` | Analitik geografis: outlet per wilayah, sales density, coverage heatmap |
| `TerritoryController` | `index`, `store`, `update`, `assign` | CRUD wilayah distribusi, assign outlet ke territory |
| `SupplierPerformanceController` | `index` | BI performa supplier: fulfillment rate, avg lead time, revenue |
| `StockPlanningController` | `index` | BI perencanaan stok: reorder point, safety stock, demand forecast per produk |
| `MeasurementController` | `storeEvent`, `recommendations`, `forecasts` | Ingestion event pengukuran, rekomendasi & forecast berbasis data intelijen |

### 5.2 Service (30)

| Service | Fungsi |
|---|---|
| `AuthService` | `createToken` / `invalidateToken` / `refreshToken` / `validateToken` (JWT) |
| `OrderCreationService` | Orkestrasi pembuatan order: cek kredit → siapkan produk → reserve stok → persist atomik (transaksi DB). Terima `requestIdentity` untuk idempotensi |
| `CreditLimitService` | `assertCanPlace(outlet, nominal)` — tolak order bila melampaui limit; `summary(outlet)` — limit, outstanding, sisa |
| `PaymentService` | Pencatatan pembayaran + generate receipt (`ReceiptService`) |
| `AnalyticsService` | Agregasi dashboard per rentang tanggal + grouping |
| `ForecastService` | Prediksi order berikutnya (deterministik dari histori) |
| `RecommendationService` | Rekomendasi produk bounded & ranked dari histori belanja |
| `SegmentationService` | Segmentasi outlet (`segment` / `segmentAll`); fallback aman bila histori sparse |
| `CalendarService` | Penjadwalan `SalesVisit` |
| `RoutingService` | `plan(delivery)` — rencana rute pengiriman |
| `WhatsAppService` | Orkestrasi inbound: `handleWebhook`, `notifyConfirmedOrder`, `retryMessage`, `shareCatalog` |
| `WhatsAppOutboundService` | Pengiriman notifikasi/katalog via `WhatsAppClient` + persist `WhatsAppMessage` |
| `WhatsAppHttpClient` | Implementasi `WhatsAppClient`: `sendText`, `sendTextWithIdempotency`, `sendCatalog` ke Cloud API |
| `WhatsAppPayloadParser` | Parsing payload webhook: ekstrak pesan, body, item terstruktur, resolusi produk |
| `WhatsAppSenderResolver` | Resolusi nomor pengirim → outlet (dengan kanonikalisasi nomor) |
| `ConcurrencyTestBarrier` | Barrier sinkronisasi **khusus test** (race suite); tidak aktif di produksi |
| `InvoiceService` | Pencatatan & penarikan invoice dari order + payment; status tracking |
| `InvoiceBackfillService` | Backfill invoice historis dari order/payment yang sudah ada |
| `InvoiceMetricsService` | Agregasi metrik keuangan: total receivable, overdue, rasio pembayaran |
| `InvoiceReminderService` | Orkestrasi pengingat invoice jatuh tempo |
| `InvoiceReminderCandidateSelector` | Seleksi kandidat invoice yang perlu diingatkan |
| `InvoiceReminderClaimService` | Klaim/ambil invoice reminder oleh sales |
| `InvoiceReminderStateService` | Manajemen status state machine invoice reminder |
| `FinanceAuthorizationService` | Cek otorisasi role finance: user dengan role finance hanya lihat data keuangan |
| `FinanceRoleService` | Assign/hapus role finance ke user + audit trail |
| `DataPipelineService` | Orkestrasi pipeline data intelijen: snapshot, metrics, rekomendasi |
| `ActiveDataSnapshotReader` | Membaca snapshot data intelijen terbaru yang sudah dipublikasikan |
| `MeasurementService` | Pencatatan event pengukuran + analitik |
| `GeographicAnalyticsService` | Agregasi data geografis: outlet density, coverage, sales per wilayah |
| `StockPlanningService` | Perhitungan reorder point, safety stock, demand forecast per SKU |
| `SupplierPerformanceService` | Agregasi metrik performa supplier: fulfillment rate, lead time, revenue |

### 5.3 Validasi request (FormRequest)

`RegisterOutletRequest`, `StoreOutletRequest`, `StoreOrderRequest`, `StorePaymentRequest`, `SetCreditLimitRequest`, `StoreDeliveryRequest`, `UpdateDeliveryStatusRequest`, `StoreSalesVisitRequest` — masing-masing mendefinisikan `authorize()`, `rules()`, dan (sebagian) `prepareForValidation()` / `withValidator()`.

---

## 6. Model Data & Relasi

God nodes di knowledge graph (`User` 100 edge, `Order` 79, `Outlet` 76, `Product` 66) mencerminkan sentralitas model-model ini. Diagram relasi (ringkas):

```
User (role: admin|supplier|outlet|sales|driver)
 ├─1:1 Outlet ──1:1 CreditLimit
 │        ├─1:N Order ──1:N OrderItem ──N:1 Product ──N:1 Supplier ──N:1 User(supplier)
 │        │       ├─1:N OrderStatusHistory (audit)
 │        │       ├─1:N Payment
 │        │       ├─1:1 Delivery ──1:N DeliveryStatusHistory (audit)
 │        │       ├─1:N Invoice ──1:N InvoiceReminder
 │        │       └─1:N WhatsAppMessage
 │        ├─1:N WhatsAppMessage
 │        └─N:1 Territory
 ├─1:1 Supplier ──1:N Product
 ├─1:N SalesVisit (sales_user_id) ──N:1 Outlet
 ├─1:N Delivery sebagai driver (driver_id) / pembuat (assigned_by_id)
 ├─1:N RoleAssignmentAudit (finance role changes)
 └─JWT auth (getJWTIdentifier / getJWTCustomClaims)

Data Intelligence:
DataPipelineRun ──1:1 DataSnapshot ──1:N DataSnapshotValue
DataMetricDefinition ──1:N DataSnapshotValue
RecommendationEvent ──N:1 User
```

Catatan per model:

- **User** — `role` enum 5 nilai; helper `isAdmin/isOutlet/isSupplier/isSales/isDriver`; `password` hidden; cast `is_active` boolean. Kolom `finance_role` (nullable) menandakan role keuangan tambahan.
- **Outlet** — mutator normalisasi nomor telepon (`canonicalizePhone`, migrasi `000015`); relasi `user`, `creditLimit`, `territory`. Kolom `payment_term_days` untuk payment terms per outlet.
- **Product** — `supplier()`; scope `purchasable` (hanya produk aktif/available dari supplier aktif — produk tanpa supplier tetap bisa dipesan); scope `search`.
- **Order** — `outlet`, `items`, `statusHistory` (terurut), `payments`, `delivery`, `invoice`; `generateUniqueOrderId()`; `recordStatus()` untuk audit. Status: `new → confirmed → processing → delivered → paid`, plus `cancelled`. Field `commission_percentage` di-snapshot per order. Kolom `due_date` untuk jatuh tempo.
- **Delivery** — konstanta `ASSIGNED / IN_PROGRESS / DELIVERED / FAILED`; `canTransition()` + `transitionTo()` menegakkan state machine dan menulis `DeliveryStatusHistory` beserta aktor.
- **Invoice** — dibuat otomatis dari order yang dikonfirmasi; relasi `order`, `outlet`, `reminders`. Status: `pending`, `paid`, `overdue`.
- **InvoiceReminder** — jatuh tempo invoice; `InvoiceReminderCandidateSelector` → `InvoiceReminderClaimService` → `InvoiceReminderStateService` (state machine).
- **Territory** — `name`, `code`; relasi `hasMany` ke Outlet. Untuk manajemen wilayah distribusi.
- **DataPipelineRun / DataSnapshot / DataSnapshotValue / DataMetricDefinition** — pipeline data intelijen: run → snapshot → values per metrik. `RecommendationEvent` mencatat event rekomendasi AI per user.
- **Payment / CreditLimit / SalesVisit / Supplier / OrderItem / histori / WhatsAppMessage / RoleAssignmentAudit** — relasi standar `belongsTo/hasMany/hasOne` seperti diagram di atas; `WhatsAppMessage` memakai tabel `whatsapp_messages` dengan field idempotensi (`claimed_at`, kunci idempotensi notifikasi). `RoleAssignmentAudit` mencatat perubahan role finance.

---

## 7. Referensi API Endpoint

Base URL: `http://localhost:8000/api`. Kecuali yang ditandai publik, semua butuh `Authorization: Bearer <JWT>`.

### 7.1 Publik

| Method & Path | Controller | Keterangan |
|---|---|---|
| `POST /auth/register` | `AuthController@registerOutlet` | Registrasi outlet (buat akun + profil outlet) |
| `POST /auth/login` | `AuthController@login` | Login → `{token, token_type, expires_in, user}` |
| `POST /whatsapp/webhook` | `WhatsAppController@webhook` | Webhook provider; diverifikasi via HMAC signature |
| `GET /health` | closure | `{status: "healthy", timestamp}` (full path: `/api/health`) |

### 7.2 Terautentikasi

| Method & Path | Controller | Otorisasi / Catatan |
|---|---|---|
| `GET /auth/me`, `POST /auth/logout`, `POST /auth/refresh` | `AuthController` | Profil, logout, refresh token |
| `POST /outlets`, `GET /products` | legacy | Hanya klien terautentikasi |
| `GET /marketplace/suppliers`, `GET /marketplace/products` | `MarketplaceController` | Katalog multi-supplier |
| `POST /orders` | `OrderController@store` | Buat order; cek limit kredit; idempoten via identity |
| `GET /orders`, `GET /admin/orders` | `OrderController@index` | List untuk admin (alias `/admin` untuk klien lama) |
| `GET /orders/{id}`, `GET /admin/orders/{id}` | `OrderController@show` | Detail + guard outlet vs admin |
| `PUT /orders/{id}/approve` | `OrderController@approve` | Admin approve `new → confirmed` |
| `GET /sales/visits`, `POST /sales/visits` | `SalesController` | Scope ke sales user sendiri |
| `GET /sales/visits/{id}`, `PATCH /sales/visits/{id}` | `SalesController` | Detail/update kunjungan |
| `GET /deliveries`, `POST /deliveries` | `DeliveryController` | List + assign order terkonfirmasi ke driver |
| `GET /deliveries/{id}` | `DeliveryController` | Detail + histori status |
| `PATCH|POST /deliveries/{id}/status`, `PUT /deliveries/{id}` | `DeliveryController@updateStatus` | Transisi status (hanya driver assigned; diaudit) |
| `POST /payments`, `GET /payments` | `PaymentController` | Catat + riwayat pembayaran |
| `GET /analytics/dashboard` | `AnalyticsController` | Metrik owner (lihat DashboardMetrics di shared package) |
| `GET /ai/recommendations`, `GET /ai/forecast`, `GET /ai/segmentation` | `AIController` | Analitik deterministik; outlet hanya data sendiri, admin boleh lintas outlet |
| `POST /whatsapp/catalog` | `WhatsAppController@catalog` | Kirim katalog bounded ke outlet |
| `POST /whatsapp/orders/{orderId}/notification` | `WhatsAppController@notify` | Notifikasi order terkonfirmasi (idempoten) |
| `POST /whatsapp/messages/{messageId}/retry` | `WhatsAppController@retry` | Retry pesan gagal (lease-based) |
| `GET /credit-limit`, `GET /admin/outlets/{outletId}/credit-limit` | `CreditLimitController@show` | Ringkasan limit & outstanding |
| `PUT|POST /admin/outlets/{outletId}/credit-limit` | `CreditLimitController@update` | Admin set limit |
| `GET /admin/outlets/{outletId}/payment-terms`, `PUT|POST /admin/outlets/{outletId}/payment-terms` | `OutletController` | Kelola payment terms per outlet (admin only) |
| `PUT /orders/{id}/cancel` | `OrderController@cancel` | Batalkan order (otorisasi per peran) |
| `GET /invoices`, `GET /finance/metrics` | `InvoiceController`, `FinanceMetricsController` | Invoice history + finance metrics (role-scoped) |
| `GET /finance/reminders`, `GET /reminders` | `InvoiceReminderController` | Daftar pengingat invoice jatuh tempo |
| `GET /finance/access` | `FinanceRoleController@access` | Cek akses finance untuk role saat ini |
| `POST /admin/users/{userId}/finance-role`, `DELETE /admin/users/{userId}/finance-role` | `FinanceRoleController` | Assign/hapus role finance (admin only, diaudit) |
| `GET /admin/pipeline/status`, `POST /admin/pipeline/manual-trigger` | `DataPipelineController` | Status & trigger manual pipeline data intelijen |
| `GET /admin/analytics/geographic` | `GeographicAnalyticsController` | Analitik geografis: outlet per wilayah, heatmap |
| `GET /admin/territories`, `POST /admin/territories`, `PATCH /admin/territories/{id}`, `POST /admin/territories/{id}/assign` | `TerritoryController` | CRUD territory & assign outlet ke territory |
| `GET /admin/analytics/suppliers` | `SupplierPerformanceController` | BI performa supplier (fulfillment, lead time, revenue) |
| `GET /admin/analytics/stock-planning` | `StockPlanningController` | BI perencanaan stok (reorder point, safety stock, forecast) |
| `POST /admin/measurement/events`, `GET /admin/analytics/measurement/*` | `MeasurementController` | Ingestion event + analitik pengukuran |

Contoh respons login dan bentuk error mengikuti tipe di `packages/shared` (`LoginResponse`, `AuthError`, `ApiResponse<T>`, `PaginatedResponse<T>`).

---

## 8. Aturan Bisnis Penting

### 8.1 Status order & audit trail
`new → confirmed → processing → delivered → paid` (+ `cancelled`). Setiap perubahan dicatat di `order_status_history` via `Order::recordStatus()`. Delivery punya audit paralel di `delivery_status_histories` beserta aktor (`actor_id`).

### 8.2 Credit limit enforcement
`CreditLimitService::assertCanPlace()` dipanggil **saat order dibuat** (`OrderCreationService`), bukan saat bayar. Order yang membuat `outstanding + nominal baru > limit` ditolak. `summary()` mengembalikan `{limit, outstanding, remaining}`.

### 8.3 Komisi (revenue hook)
`config/orders.php → commission_percentage` (default `2.00`, env `ORDER_COMMISSION_PERCENTAGE`) di-**snapshot ke setiap order** saat dibuat, sehingga perubahan konfigurasi tidak mengubah histori transaksi.

### 8.4 Stok & konk(TYPE) kurensi
`OrderCreationService::create()` berjalan dalam transaksi DB: validasi → `prepareProducts` → `reservePreparedProducts` → persist. Ada `ConcurrencyTestBarrier` (test-only) untuk menguji race condition order/pembayaran/delivery konkuren — terbukti oleh suite `DeliveryTest`, `PaymentTest`, `WhatsAppPostgresConcurrencyTest`.

### 8.5 AI deterministik & bounded
`ForecastService`, `RecommendationService`, `SegmentationService` bersifat **deterministik** (output sama untuk input sama — mudah di-test, lihat `AITest`) dan **bounded** (limit jumlah, histori sparse → fallback aman yang explainable, bukan halusinasi). Outlet hanya boleh mengakses sinyal AI miliknya; admin boleh lintas outlet (diuji di `AITest`).

### 8.6 Produk purchasable
`Product::scopePurchasable()`: produk tidak aktif atau dari supplier non-aktif **tidak bisa dipesan**, kecuali produk tanpa supplier (diuji di `WhatsAppTest`).

### 8.7 Idempotensi WhatsApp
Pengiriman memakai **lease** (`send_lease_seconds`, default 300 dtk): baris `sending` yang masih fresh tidak diduplikasi; yang stale/unknown di-reclaim dengan kunci idempotensi provider yang stabil. Retry memakai kunci yang sama sehingga outcome provider yang tidak pasti tetap aman.

---

## 9. Autentikasi & Otorisasi

- JWT via `tymon/jwt-auth`; login mengembalikan `token` (+ `expires_in: 86400` ≈ 24 jam).
- Middleware `auth:api` melindungi semua route §7.2; `Authenticate` + `RedirectIfAuthenticated` standar Laravel.
- Otorisasi berbasis peran di controller/service: admin (approve order, kelola limit, lihat semua order), outlet (order & data sendiri), sales (kunjungannya sendiri), driver (hanya delivery yang di-assign — `DeliveryController@canView/canMutate`).
- Webhook WhatsApp bersifat publik di transport tapi diverifikasi dengan **HMAC signature** (`ValidateSignature`, secret `WHATSAPP_WEBHOOK_SECRET`).

---

## 10. Integrasi WhatsApp

Kritis untuk ekosistem warung Indonesia (lihat `idea.md`). Dua arah:

**Inbound (warung → platform):** provider POST ke `/whatsapp/webhook` → verifikasi HMAC → `WhatsAppPayloadParser` ekstrak pesan → `WhatsAppSenderResolver` petakan nomor (dikanonikalisasi) ke outlet → validasi (pengirim ambigu/tak dikenal ditolak; signature & sender wajib) → buat order (duplikat provider delivery tidak membuat order ganda; event gagal bisa retry tanpa duplikat).

**Outbound (platform → warung):** `notifyConfirmedOrder` (saat order dikonfirmasi), `shareCatalog` (katalog bounded, default 20 item via `WHATSAPP_CATALOG_LIMIT`), `retryMessage` — semuanya via `WhatsAppClient` (`WhatsAppHttpClient` → `https://graph.facebook.com/v20.0`) dengan lease + idempotency key (§8.7).

Konfigurasi (`apps/api/config/whatsapp.php`, env `WHATSAPP_*`): `ENABLED`, `WEBHOOK_SECRET`, `VERIFY_TOKEN`, `API_URL`, `ACCESS_TOKEN`, `PHONE_NUMBER_ID`, `CATALOG_LIMIT`, `SEND_LEASE_SECONDS`.

---

## 11. Frontend — Next.js Web

App Router, 13 halaman + komponen UI shared + komponen data-intelligence:

| Route | File | Fungsi & endpoint yang dipakai |
|---|---|---|
| `/` | `app/page.tsx` | Landing |
| `/dashboard` | `app/dashboard/page.tsx` | Dashboard owner (metrik + Charts) → `GET /analytics/dashboard` |
| `/admin/orders` | `app/admin/orders/page.tsx` | Persetujuan & kelola order (admin) → `/admin/orders` |
| `/orders` | `app/orders/page.tsx` | Outlet: browsing, submit (`OrderForm`), tracking → `/orders` |
| `/products` | `app/products/page.tsx` | Katalog (`ProductCatalog`) → `/products` |
| `/marketplace` | `app/marketplace/page.tsx` | Katalog multi-supplier (`MarketplaceCatalog`) → `/marketplace/*` |
| `/outlets` | `app/outlets/page.tsx` | Registrasi/kelola outlet (`OutletForm`) → `/outlets`, `/auth/register` |
| `/payments` | `app/payments/page.tsx` | Pembayaran & riwayat → `/payments`, `/credit-limit` |
| `/invoices` | `app/invoices/page.tsx` | Invoice & tagihan → `/invoices`, `/reminders` |
| `/delivery` | `app/delivery/page.tsx` | Assign & tracking delivery → `/deliveries` |
| `/sales` | `app/sales/page.tsx` | Perencanaan & histori kunjungan → `/sales/visits` |
| `/analytics` | `app/analytics/page.tsx` | Rekomendasi, forecast, segmentasi AI → `/ai/*` |
| `/data-intelligence` | `app/data-intelligence/page.tsx` | Data Intelligence admin (peta, territory, supplier, stok) → `/admin/analytics/*` |

**Layout & navigasi:** `app/layout.tsx` → `AppShell` → `Sidebar` (navigasi per peran: admin, finance, outlet — ada flag `finance` & `adminOnly`). Role diambil dari `GET /auth/me` + sinkron via custom event `auth-change`.

**Komponen UI shared** (`components/ui/`, 13 modul): `Button`, `Card`, `StatCard`, `Table`, `Modal`, `Input`, `Select`, `Textarea`, `Badge`, `StatusBadge`, `EmptyState`, `Skeleton`, `PageHeader`.

**Komponen Data Intelligence** (`components/data-intelligence/`): `GeoMap` (peta OpenStreetMap via Leaflet), `TerritoryTable`, `SupplierPerformanceTable`, `StockPlanningTable`, `MeasurementCards` — dengan `lib/data-intelligence-api.ts` + `lib/data-intelligence-types.ts` sebagai kontrak tipe frontend-BE.

**Lib:** `src/lib/api.ts`: `API_URL`, `apiUrl(path)`, `authHeaders(token)`, token JWT di `localStorage` (`ddp_token`). `LoginForm` mendukung `expectedRole`. Testing: `order-flow.test.js` (E2E) + `data-intelligence-types.test.js` (tipe).

---

## 12. Shared Package

`packages/shared/src/index.ts` (`@ddp/shared`) — kontrak tipe tunggal frontend↔backend: `User`, `UserRole`, `LoginRequest/Response`, `AuthError`, `Product`, `Order`, `OrderItem`, `OrderStatus`, `ApiResponse<T>`, `PaginatedResponse<T>`, `DashboardMetrics`. Di-build ke `dist/` (lihat `package.json` script `build`/`test` dengan `ts-jest`).

---

## 13. Konfigurasi Environment

| File / Var | Keterangan |
|---|---|
| `apps/api/.env` (dari `.env.example`) | `APP_KEY`, `DB_*` (pgsql), `REDIS_*`, `JWT_*`, `ORDER_COMMISSION_PERCENTAGE=2.00`, `WHATSAPP_*` (secret, token, phone_number_id, limit, lease), barrier konkuren (test-only) |
| `apps/web` | `NEXT_PUBLIC_API_URL` (default `http://localhost:8000/api`) |
| Root docker-compose | Kredensial dev default `ddp_user/ddp_password`, DB `ddp_database` — **wajib diganti di produksi** |

---

## 14. Database & Migrasi

35 migrasi (`apps/api/database/migrations/`), urutan bermakna:

1. `2024_01_01` users (role: admin, supplier, outlet, sales, driver)
2. `2024_01_02` outlets (+ `000015` canonical phone, `000019` payment_term_days, `000025` territory_id)
3. `2024_01_03` products (+ `000005` supplier FK)
4. `2024_01_04*` orders, order_items, order_status_history (+ `000003` commission snapshot, `000006` paid_amount, `000029` due_date)
5. `000004` suppliers (+ `000026` lead_time_days); `000007` payments (+ `000010` index analitik); `000008` credit_limits
6. `000009` indeks query analitik; `000011` sales_visits (+ `CalendarService`)
7. `000012` deliveries + `000013` delivery_status_histories
8. `000014` whatsapp_messages (+ `000016` idempotensi notifikasi, `000017` claimed_at)
9. `000018` indeks query marketplace
10. `000020` invoices + `000021` invoice_reminders (+ `000023` send_lease)
11. `000022` role_assignment_audits + `000023` finance_role ke users
12. `000024` territories + `000027` data_intelligence tables (pipeline_runs, snapshots, metric_definitions)
13. `000028` recommendation_events + `000029` forecast_actuals

Fakta penting: skema mendukung **multi-supplier marketplace** (produk ↔ supplier), **audit ganda** (order + delivery), **idempotensi pesan WA** di level DB, **invoice & finance role**, **manajemen territory**, dan **pipeline data intelijen** (snapshot-based BI).

---

## 15. Testing & CI/CD

Suite backend (`apps/api/tests/Feature/`, 13 file): `AuthTest`, `OutletTest`, `ProductTest`, `MarketplaceTest`, `OrderTest`, `PaymentTest`, `DeliveryTest` (termasuk race konkuren multi-server), `SalesTest`, `AITest`, `AnalyticsTest`, `WhatsAppTest`, `WhatsAppPostgresConcurrencyTest`, `Phase1IntegrationTest` (+ `LoadTest` performa).

Frontend: `apps/web/jest.config.js` (config Jest dengan Babel — `@babel/core`, `@babel/preset-env`, `@babel/preset-react`, `@babel/preset-typescript`); `order-flow.test.js` (E2E order flow); `data-intelligence-types.test.js` (validasi tipe data-intelligence).

CI (`.github/workflows/ci.yml`, trigger `push` ke `main/develop` + PR ke `main`): job `api-tests` (PHP 8.2 + Postgres 16 + Redis 7 service → `composer install` → migrate → `php artisan test`), job web (Node 20 → Jest), job Docker build. Dokumen `docs/performance/T9-load-test.md` mencatat SLO: **p95 & maks < 2 dtk pada 100 request konkuren** dengan fixture 500 outlet (`ScaleFixtureSeeder`).

---

## 16. Menjalankan Secara Lokal

**Docker (disarankan):**
```bash
docker-compose up -d     # web :3000, api :8000, db :5432
```

**Manual — API:**
```bash
cd apps/api && composer install && cp .env.example .env
php artisan key:generate
# sesuaikan DB_*, REDIS_*, JWT_*, WHATSAPP_* di .env
php artisan migrate && php artisan serve   # :8000
```

**Manual — Web:**
```bash
cd apps/web && npm install && npm run dev  # :3000
# NEXT_PUBLIC_API_URL menunjuk ke API
```

**Test:** `cd apps/api && php artisan test` · `cd apps/web && npm test`.

---

## 17. Observability, Batasan & Catatan Teknis

- **Health check** tersedia (`GET /api/health`); tidak ada APM/tracing bawaan — pertimbangkan menambahkan logging terstruktur + metrics untuk modul WA dan order pipeline.
- **Cohesion rendah** pada komunitas `Web Frontend Pages` (0.08) dan `Product Catalog Orders` (0.07) menurut knowledge graph — kandidat refactor/split modul bila berkembang.
- **Keamanan yang perlu dijaga:** ganti kredensial default docker-compose; simpan `WHATSAPP_WEBHOOK_SECRET`/`ACCESS_TOKEN`/`APP_KEY`/`JWT_SECRET` di secret manager; pastikan validasi HMAC webhook selalu aktif; review bahwa `registerOutlet` (publik) memiliki rate-limit & captcha di produksi.
- **Skalabilitas:** operasi berat (broadcast WA, agregasi analitik) adalah kandidat antrian (Redis tersedia); indeks analitik/marketplace sudah disiapkan di migrasi.
