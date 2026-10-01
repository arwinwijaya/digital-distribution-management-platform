# Pitch Exploration: outlet-dashboard-insight
Date: 2026-10-01 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Outlet yang login dialihkan dari `/dashboard` ke `/orders` (`router.replace('/orders')` di `apps/web/src/app/dashboard/page.tsx`), sehingga tidak punya halaman utama untuk memantau proses pemesanan sendiri (tahap `New → Confirmed → Delivered → Paid`, pesanan aktif, pembayaran/invoice) dan melihat insight mandiri (ringkasan belanja, status kredit, produk yang sering dipesan).

## Root Tension
Outlet butuh visibilitas status pesanan yang jelas dan insight yang bisa ditindaklanjuti, tetapi analitik internal (`/analytics/dashboard`, data-intelligence) bersifat agregat lintas-outlet dan tertutup untuk outlet (RBAC `none`) — dashboard outlet harus dibangun dari data milik outlet sendiri yang sudah terisolasi, bukan membuka akses analitik admin.

## Key Constraints
- Monorepo: `apps/web` (Next.js 14 App Router, React 18, Zustand, `localStorage.ddp_token`) + `apps/api` (Laravel 11, `tymon/jwt-auth`, PostgreSQL 16 + Redis 7 via Docker Compose).
- `DashboardPage` hari ini: `useDashboardSession` skip load untuk `role === 'outlet'` lalu `router.replace('/orders')`; `loadDashboard()` di `apps/web/src/app/dashboard/api.ts` hanya punya branch `finance` vs `admin` (`GET /finance/metrics` vs `GET /analytics/dashboard`) — tidak ada branch outlet.
- RBAC (sumber: `apps/api/database/seeders/RbacMatrixSeeder.php` ↔ `apps/web/src/dummy/rbac.ts`, 24 menu keys): outlet = `dashboard:edit, orders:edit, payments:edit, marketplace:edit, delivery:read, invoices:read, outlets:read, products:read`; `analytics / data_intelligence / operations / admin_* = none`. Sidebar (`Sidebar.tsx`) sudah menampilkan `Dasbor` untuk outlet — halaman yang dituju yang menolak outlet, bukan menu.
- Data outlet-self sudah tersedia dan terisolasi: `OrderController::index/show` (`where outlet_id` untuk outlet, guard admin), `InvoiceController::index` (`where outlet_id` untuk outlet), `CreditLimitController::show` (`GET /credit-limit` tanpa outletId untuk outlet sendiri → `{credit_limit, outstanding_balance, available_credit}`; outstanding dihitung dari order `New, Confirmed, Delivered, Partially Paid`).
- Dummy mode parity wajib: loader dashboard memakai `withDummyRead` + `useDummyStore`; dashboard outlet butuh fixture dummy yang sama polanya bila reuse `loadDashboard`.
- Bahasa status internal (`New/Confirmed/Delivered/Partially Paid/Paid`, cancelled) belum tentu dipahami outlet — butuh pemetaan ke tahap + langkah berikutnya.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Status pemesanan mana yang paling penting: pesanan aktif, estimasi pengiriman, pembayaran, atau semuanya sekaligus.
- Perlu diputuskan ringkasan saja vs bisa membuka detail + riwayat pesanan dari dashboard.
- Insight harus mendorong tindakan (pesan ulang, bayar invoice) — bukan sekadar grafik.
- Perbandingan antar-outlet tidak diminta; fokus pada data outlet yang login.
- Kesegaran data dan penjelasan status yang menunggu pembaruan harus eksplisit.

### First Principles Thinking — creative
Key insights:
- Kebutuhan dasar: "pesanan saya di tahap apa, apa yang harus saya lakukan berikutnya?"
- Data pesanan + pembayaran + kredit sudah ada dan terisolasi per outlet — ini masalah penyajian ulang, bukan subsistem analitik baru.
- Dashboard bukan pengganti `/orders`: jalur lanjutkan belanja / tindak lanjut pesanan harus tetap jelas.
- Tanpa asumsi benchmark antar-outlet atau estimasi pengiriman otomatis sebelum datanya divalidasi.

### Six Thinking Hats — structured
Key insights:
- White: outlet di-redirect dari `/dashboard`; endpoint ringkas admin tertutup untuk outlet; endpoint per-outlet (orders/invoices/credit-limit) terbuka dan terisolasi.
- Red: ketidakpastian status → outlet khawatir / harus tanya sales-admin.
- Yellow: ringkasan status + aktivitas terbaru + pintasan tindakan mengurangi pengecekan manual.
- Black: status stale, istilah internal, kebocoran data antar-outlet, empty-state yang menyesatkan (nol vs gagal muat).
- Green: kartu pesanan aktif, timeline tahap, insight berbasis riwayat sendiri dengan periode + sumber data jelas.
- Blue: sepakati kebutuhan pemantauan dulu, baru susunan metrik/grafik.

### Reverse Brainstorming — creative
Key insights:
- Kegagalan yang harus dihindari: paksa outlet keliling halaman untuk tahu status; metrik tanpa periode/konteks; status internal tanpa penjelasan langkah berikut; campur data outlet lain; grafik penuh tanpa aksi; empty-state nol yang menyamarkan error.
- Inversi menjadi requirements: tahap + CTA jelas, periode + freshness label, scope outlet-login only, hirarki pesanan-butuh-perhatian dulu, empty vs error dibedakan.

---

## Advisor Synthesis
Percobaan kurasi advisor dua kali gagal (service overload), sehingga sintesis dilakukan manual dari 4 metode. Semua metode konvergen ke pola yang sama: perjalanan **pahami kondisi pesanan → temukan yang butuh perhatian → ambil tindakan**, dibangun dari endpoint outlet-self yang sudah ada (`orders`, `invoices`, `credit-limit`) tanpa membuka analitik admin. Yang di-discard sementara: benchmark antar-outlet, grafik kompleks sebagai fokus, dan estimasi/rekomendasi otomatis sebagai komitmen awal.

---

## Spike Results

**Unknown resolved:** apakah data dan akses yang dibutuhkan dashboard outlet sudah tersedia tanpa endpoint analitik baru?
**Finding:**
- Ya untuk fondasi: `GET /orders` + `GET /orders/{id}` terisolasi `outlet_id` (OrderController `indexGuard` + `show`); `GET /invoices` terisolasi outlet; `GET /credit-limit` mengembalikan ringkasan kredit outlet sendiri.
- Tidak untuk agregat siap-pakai: tidak ada endpoint ringkasan outlet tunggal (status counts, total belanja periode, produk favorit) — harus komposisi 3 panggilan di klien atau endpoint agregasi baru.
- RBAC `dashboard:edit` sudah dimiliki outlet — hambatan bukan izin menu, melainkan branch `role === 'outlet'` di halaman dashboard yang me-redirect.
**Implication:** Direction A (branch outlet di rute `/dashboard` yang sama) layak tanpa perubahan RBAC/menu; agregasi (komposisi klien vs endpoint baru) menjadi keputusan grinding terbesar.

---

## Approach Directions

### Direction A: Same-route outlet branch (Recommended)
Rute `/dashboard` tetap dipakai; ganti `router.replace('/orders')` dengan cabang render khusus outlet (ringkasan pesanan aktif + pembayaran/invoice + kredit + aktivitas terbaru + insight riwayat sendiri), dengan loader `role === 'outlet'` di `dashboard/api.ts` yang mengkomposisikan endpoint outlet-self yang sudah ada.
+ Tanpa rute/menu/RBAC baru (outlet sudah `dashboard:edit`); perubahan navigasi minimal; dummy parity mengikuti pola `withDummyRead` yang ada.
− Satu file halaman menampung 3 peran (admin/finance/outlet) — perlu disiplin komponen agar tidak membengkak; agregasi awal di klien (3 panggilan) sampai terbukti butuh endpoint khusus.

### Direction B: Enrich `/orders` with summary strip
Tidak ada halaman dashboard baru; panel ringkasan (pesanan aktif, tagihan, kredit, produk sering dipesan) ditanam di atas halaman `/orders` yang sudah jadi beranda outlet.
+ Paling sedikit file baru; outlet langsung melihat ringkasan di tempat mereka bekerja.
− Mencampur fungsi transaksional (buat pesanan) dengan analitis — risiko halaman padat; tetap butuh logika agregasi yang sama dengan A tanpa memberi ruang insight yang layak.

### Direction C: New outlet overview module + summary endpoint
Modul baru (mis. `/outlet` atau `/my-overview`) + endpoint agregasi backend khusus outlet (`status counts`, `spending`, `favorites`, `credit`) + kunci menu/RBAC baru bila diperlukan.
+ Pemisahan paling bersih; agregasi server-side efisien untuk insight berat (produk favorit, tren belanja).
− Kode baru terbanyak (endpoint + RBAC + menu + dummy fixture + guard); overkill bila komposisi klien dari 3 endpoint existing sudah cukup untuk v1.

---

## Open Questions for pocket-grinding
- [ ] Rute final: cabang outlet di `/dashboard` (tanpa RBAC baru) vs modul baru `/outlet` (butuh kunci menu/RBAC + redirect login `roleDestination`)?
- [ ] Agregasi: komposisi klien (`orders` + `invoices` + `credit-limit`) vs endpoint ringkasan outlet baru — ambang apa yang memaksa pindah ke server-side?
- [ ] Definisi "pesanan aktif" untuk outlet: `New/Confirmed/Delivered/Partially Paid` (mengikuti `CreditLimitService`) atau subset yang lebih sempit? Bagaimana `Cancelled`/`Paid` ditampilkan?
- [ ] Pemetaan bahasa status: label + langkah-berikutnya apa untuk tiap status internal agar dipahami outlet?
- [ ] Kredit tanpa limit (`credit_limit: null`): disembunyikan, dijelaskan, atau diganti metrik lain?
- [ ] Sumber "produk favorit / pesan lagi": agregasi `order items` di klien vs endpoint baru? Periode default berapa (30d?)?
- [ ] Invoice vs payment untuk outlet: apakah ringkasan tagihan memakai `invoices` (outstanding) atau `payments` — atau keduanya dengan peran berbeda?
- [ ] Dummy parity: bentuk fixture `dashboardOutlet` apa (counts, kredit, aktivitas, favorit) agar `withDummyRead` konsisten?
- [ ] Freshness + empty/error: format label kesegaran dan pembedaan "belum ada aktivitas" vs "gagal muat"?

---

## Recommended Direction
Direction A — Same-route outlet branch — karena satu-satunya yang menjawab kedua sisi permintaan (proses pemesanan + insight) tanpa rute/RBAC/endpoint baru, memanfaatkan izin `dashboard:edit` dan endpoint outlet-self yang sudah terisolasi; Direction C menjadi eskalasi alami bila agregasi klien terbukti berat.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction A as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
