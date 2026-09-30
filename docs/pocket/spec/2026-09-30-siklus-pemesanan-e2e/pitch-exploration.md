# Pitch Exploration: siklus-pemesanan-e2e
Date: 2026-09-30 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Pengecekan end-to-end satu siklus pemesanan outlet → pembayaran (`Outlet login → pilih produk → buat pesanan → admin approve → assign delivery → driver antar → delivered → pembayaran → Paid`) hari ini hanya terdokumentasi sebagai checklist manual di `docs/manual-check-siklus-pemesanan.md` (§6 UI + §7 API + §8 negative) dan harus dijalankan berurutan sebagai 4 role berbeda tanpa observability terstruktur — perlu diubah menjadi pengujian otomatis di browser yang mencatat semua log sukses maupun error per langkah agar alur, respons API, dan kegagalan dapat ditelusuri tanpa re-run manual.

## Root Tension
Fidelity browser asli (Next.js 16 App Router SSR/hydration, alur serial 4 role, stateful `New → Confirmed → Delivered → Paid` / `assigned → in_progress → delivered` / `unpaid → paid`) bertabrakan dengan determinisme & maintainability (DB seeded shared tanpa endpoint reset, nol `data-testid` di halaman order/delivery/payment/admin-orders, dan keterbatasan resource CI untuk menjalankan browser).

## Key Constraints
- Monorepo npm workspaces: `apps/web` (Next.js 16 App Router, React 18, Zustand, fetch + `localStorage.ddp_token`) + `apps/api` (Laravel 11, `tymon/jwt-auth`, PostgreSQL 16 + Redis 7 via Docker Compose).
- Alur serial 4 role wajib berurutan dengan akun seed fixed (`siti.nurhaliza@ddp.test` outlet, `ratna.sari@ddp.test` admin, `joko.widodo@ddp.test` driver, `dewi.lestari@ddp.test` finance — `password123` — `DatabaseSeeder.php`, `docker-compose.yml`, `.env.example`).
- Rute login terpadu `/login` → redirect berbasis role (`outlet→/orders`, `driver→/delivery`, `admin/finance→/dashboard`) via `roleDestination()` di `apps/web/src/app/login/page.tsx`; `LoginForm.tsx` tanpa `data-testid`, hanya `label="Email"`.
- `apps/web/order-flow.test.js` yang ada hanya API-level (`php -S` + sqlite, Jest) — tidak meng-cover UI; Jest config bahkan `testPathIgnorePatterns: order-flow.test.js`.
- Observability harus first-class: JSONL per run (`runId, timestamp, role, step, action, url, selector, apiStatus, durationMs, status, error, attachments`) + screenshot/trace/video-on-failure, bukan `console.log` ad-hoc.
- Tanpa endpoint seed/reset untuk test isolation; isolasi harus lewat `docker compose exec api php artisan migrate:fresh --seed`, SQL snapshot, atau endpoint test-only baru di-balik env gate.
- Browser butuh resource CI; mode `headless` default, `headed` hanya untuk debug lokal, retention artifacts 30 hari CI / 7 hari lokal.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Batas headless vs headful, real browser vs emulated (JSDOM tidak cukup untuk login/navigasi/JS penuh) harus diputuskan.
- Logging terstruktur (JSONL + screenshot/video/trace) dan storage artifacts perlu desain eksplisit.
- Seed data management (DB bersih per run vs shared fixture) dan serial execution 4 role adalah sumber flakiness utama.
- Selector strategy (`data-testid` vs CSS/label) dan Page Object Model menentukan maintainability.
- Parallel vs serial: siklus ini wajib serial karena state `New→Paid` yang dipertahankan antar role.

### First Principles Thinking — creative
Key insights:
- Fundamental need adalah verifikasi user journey di browser asli dengan observability penuh — bukan sekadar "pakai Playwright".
- Asumsi "Playwright wajib" harus di-strip: Puppeteer (Chromium-only), WebDriverIO, Cypress adalah alternatif yang perlu dibandingkan dari prinsip (auto-wait, trace, multi-context).
- Asumsi "harus test semua UI" salah — hybrid (API untuk logic 90% + browser tipis untuk critical path 10%) lebih ekonomis.
- Elemen first-principles: runner Node TS, engine Chromium (Firefox/WebKit opsional), `data-testid` stabil, setup/teardown via API/SQL, structured event stream, HTML+JUnit+JSON reporting, single-command CI.

### Six Thinking Hats — structured
Key insights:
- White (Facts): API-level E2E sudah ada, browser E2E greenfield; stack Next.js App Router + Laravel API + Compose.
- Red (Emotions): Flaky browser test = frustasi; confidence tinggi bila stabil — DX (debuggable, fast feedback) harus jadi target.
- Black (Risks): Timing/network/selectors, CI RAM/CPU, seed collision, slow run, maintenance saat UI berubah.
- Yellow (Benefits): Menangkap regresi UI (redirect outlet-first yang baru di-fix `608ff6a`, auth state loss, toast) dan jadi dokumentasi executable.
- Green (Creativity): Hybrid sebagai inovasi; log pipeline yang queryable (JSONL → Loki/Elastic nanti).
- Blue (Process): Satu perintah (`npm run test:e2e`), PR gate, nightly full suite, `test:e2e:headed` untuk debug.

### Constraint Mapping — deep
Key insights:
- Docker Compose env: browser test bisa di host atau image `mcr.microsoft.com/playwright`.
- Seeded DB & Next.js App Router (SSR/RSC): selector CSS rapuh — butuh `data-testid`.
- 4 role serial + state bertahan (cookies/localStorage): satu `browserContext` per role dengan `storageState` di-clear.
- CI resource: shard per spec, headless, video on-failure only.
- Tim kecil: Page Object hanya untuk halaman kompleks (order/delivery/payment) agar investasi sebanding.

---

## Advisor Synthesis
Kurasi advisor mengonfirmasi: JSDOM/Jest tidak memadai untuk §6 UI, tool converge ke Playwright (auto-wait/trace/screenshot/video/JUnit/JSON, multi-context 4 role, image Docker) dan discard Cypress/Puppeteer/WebDriverIO; logging harus wrapper first-class JSONL + trace; vektor flake terbesar adalah minim `data-testid`, collision seeded DB, dan state serial `New→Paid`. Pola yang muncul konsisten: API test untuk business rules (§7 + §8) + browser test tipis untuk critical path UI (§6), dengan satu context per role.

---

## Spike Results

**Unknown resolved:** (1) Apakah halaman kritis sudah punya `data-testid` dan rute login apa? (2) Apakah ada endpoint seed/reset untuk isolasi DB?

**Finding:**
- (1) Code scan Serena `search_for_pattern data-testid` di `apps/web/src`: hanya `apps/web/src/app/login/page.tsx` (`login-screen`, `login-layout`, `login-form-panel`, `login-demo-footer`, `login-hero-panel`, `login-benefits`) + beberapa halaman lain (`rbac-row-*`, `geo-map`, `pod-signature-canvas`); **`apps/web/src/app/orders`, `delivery`, `payments`, `admin/orders` = 0 `data-testid`**; `OrderForm.tsx` / `ProductCatalog.tsx` / `MarketplaceCatalog.tsx` = 0; `LoginForm.tsx` hanya `label="Email"`/`"Kata sandi"`. Rute login adalah `/login` terpadu (detail `roleDestination` di atas). Selector yang tersedia hari ini: `getByLabel`, `getByRole`, teks tombol (`"Kirim pesanan"`, `"Mulai antar"`, `"Tandai terkirim"`) — stabil tapi rapuh.
- (1b) Graphify (`graphify-out/graph.json`, 8851 nodes / 19805 edges / 471 communities, commit `274ad80a`) mengonfirmasi: god nodes `User (350)`, `Outlet (279)`, `Order (243)`; komunitas `LoginForm (131)`, `OrderForm`, `payments/page.tsx`; browser E2E belum ada.
- (2) `grep Route::` di `apps/api/routes/api.php` + memori Serena: **tidak ada** `/test/seed` atau reset endpoint; hanya `auth/*`, `orders`, `deliveries`, `payments`, `invoices`, `products`. Seeder (`DatabaseSeeder.php:13`) konfirmasi `DEFAULT_PASSWORD = password123` + 4 akun manual doc.

**Implication:** Sebelum browser test stabil, wajib (a) menambah ~15–20 `data-testid` di alur order/delivery/payment/admin-orders + `LoginForm`, dan (b) memutuskan strategi seed isolation: `migrate:fresh --seed` via `docker compose exec`, SQL snapshot restore, atau endpoint test-only ber-gate env. Tanpa keduanya, Direction A akan flaky; Direction B tetap butuh keduanya untuk porsi UI-nya.

---

## Approach Directions

### Direction A: Playwright full-UI
Seluruh siklus dijalankan murni lewat UI browser (`/login → /orders → /admin/orders → /delivery → /payments`) dengan 4 `browserContext` serial, JSONL logger wrapper, serta trace/screenshot/video.
+ Fidelity maksimal — menangkap seluruh kelas bug UI (redirect, hydration, toast, validasi client-side).
− Paling lambat & paling flaky sampai `data-testid` + strategi seed isolation beres; maintenance tinggi saat UI berubah.

### Direction B: Hybrid API + thin UI (Recommended)
Business rule dan negative checkpoints (§7 + §8: assign sebelum `Confirmed` → 422, pay sebelum `Delivered` → 422, overpayment, idempotency `Idempotency-Key`) tetap di API test (perluas `order-flow.test.js`); browser test hanya critical path UI (§6: login 4 role, pilih produk, submit pesanan, approve click, `Mulai antar → Tandai terkirim`, catat payment) dengan logging terstruktur yang sama.
+ ROI tertinggi — cepat, stabil, memisahkan logic vs UI; memanfaatkan API test yang sudah ada.
− Dua runner (Jest + Playwright) — setup awal sedikit lebih kompleks; butuh kontrak `data-testid` mini + seed strategy yang sama dengan A untuk porsi UI.

### Direction C: Perluas Jest API test saja
Tambah delivery + payment + §8 negative ke `order-flow.test.js` tanpa browser sama sekali.
+ Termurah & deterministik — jalan hari ini, nol flakiness browser.
− Tidak menjawab permintaan "test otomatis di browser"; miss regresi UI (mis. fix redirect outlet-first `608ff6a` tidak terdeteksi).

---

## Open Questions for pocket-grinding
- [ ] Selector contract: daftar `data-testid` minimal yang harus ditambah di `LoginForm`, `OrderForm`, `delivery/page.tsx`, `payments/page.tsx`, `admin/orders/page.tsx`?
- [ ] Seed isolation: pilih `migrate:fresh --seed` via compose vs SQL snapshot vs endpoint `POST /test/seed` ber-gate `APP_ENV=testing` — mana yang paling deterministik di CI Windows + Docker?
- [ ] Logging schema: format JSONL final (`runId`, correlation `Idempotency-Key`, `durationMs`, `apiStatus`, `attachments`) dan sink (file JSONL + JUnit + HTML report vs Loki/Elastic nanti)?
- [ ] Dummy mode: apakah browser test harus `isDummy=false` (guard `withDummyRead`/`useDummyStore` di-bypass) atau perlu skenario dummy terpisah?
- [ ] Artifact policy: kapan screenshot/video/trace diambil (on-failure only vs always), retention, dan lokasi simpan (CI artifacts vs `docs/pocket/spec/.../evidence/`)?
- [ ] CI wiring: job Playwright dijalankan di host vs container `mcr.microsoft.com/playwright`, serta perintah `npm run test:e2e` vs `test:e2e:headed` untuk lokal?

---

## Recommended Direction
Direction B — Hybrid API + thin UI — karena satu-satunya yang memenuhi "otomatis di browser + catat semua log" sekaligus meredam vektor flake dari spike (minim `data-testid`, seeded DB shared) dengan memisahkan business rules ke API test.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction B (Hybrid API + thin UI) as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
