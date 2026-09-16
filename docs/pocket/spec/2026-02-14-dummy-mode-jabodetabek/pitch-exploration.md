# Pitch Exploration: dummy-mode-jabodetabek
Date: 2026-02-14 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement

Mode Demo/Dummy belum ada di frontend — tidak ada cara untuk menampilkan data visualisasi di seluruh menu tanpa backend API aktif, sehingga fungsi analitik, chart, GeoMap, KPI, dan tabel tidak bisa diverifikasi kecuali ada data produksi sungguhan.

## Root Tension

Data harus **relasional & konsisten lintas menu** (outlet di Orders = outlet yang sama di Payments/Invoices/Delivery) agar fungsi analitik berjalan benar — tapi saat ini tidak ada satu pun layer abstraksi atau interception point di frontend yang memungkinkan data dummy mengalir lewat **path fungsi yang sama** dengan data real. Setiap modul API mengembalikan shape yang berbeda, dan beberapa page memanggil `fetch` langsung tanpa wrapper.

## Key Constraints

- **48 titik `apiUrl()`** di 23 file tanpa central data layer — tidak ada SATU interception point
- **2 pola wrapper berbeda**: `adminFetch<T>` (data-intelligence) dan `FetchResult<T>` (operations), ditambah `fetch` langsung di `.tsx` (dashboard, payments, analytics, delivery, orders)
- **Next.js 16 `'use client'`** — semua page adalah client component; Zustand store dengan inisialisasi via `useEffect` aman dari hydration mismatch
- **Seed JABODETABEK** — 5 kota (Jakarta, Bogor, Depok, Tangerang, Bekasi), termasuk koordinat GeoMap
- **60 hari terakhir** — time-series data harus cukup untuk chart trend (12 minggu)
- **Tidak ada API baru** — murni frontend-only, client-side mock
- **Berlaku di semua environment** — mode bisa diaktifkan di production untuk demo
- **Tidak mengubah data asli** — mode hanya mempengaruhi tampilan

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Data shape per menu harus diketahui sebelum factory dibangun — 13 menu × 2-3 endpoint = ~30 shape yang perlu di-cover
- Konsistensi lintas menu = masalah tersulit — factory harus menghasilkan **satu relational graph** yang dibaca semua page
- Verifikasi fungsi = requirement inti — data harus deterministik agar kalau chart salah, itu bug kode, bukan data
- Toggle state harus persist di Zustand (bukan localStorage) agar state tidak hilang saat navigasi

### First Principles — creative
Key insights:
- **Intersepsi di level modul API** — setiap `api.ts` punya fungsi fetch yang bisa di-guard dengan `if (useDummy()) return dummyX()`; fungsi downstream (chart, aggregation, GeoMap, KPI) tidak perlu diubah
- Satu DummyFactory = satu relational graph (outlet ↔ order ↔ items ↔ payment ↔ invoice ↔ delivery ↔ supplier), bukan random per halaman
- 60 hari × 5 kota = ruang data kecil — cukup untuk generate sekali saat toggle-on dan cache di Zustand
- Factory pattern = solusi yang muncul dari 3 metode independen — sinyal kuat

### Six Thinking Hats — structured
Key insights:
- White: 13 menu, 5 entitas utama, Topbar = Online indicator + Keluar button
- Yellow: mode ini menjadi alat QA yang powerful + demo material yang bisa diaktifkan kapan saja
- Black: Zod schema mungkin belum lengkap → data dummy tidak ter-validated; beberapa API endpoint belum punya fallback
- Green: seed berdasarkan kota (Bogor punya profil beda dari Jakarta), time-series dengan trend (naik/turun/datar)
- Blue: urutan implementasi = entitas inti → schema → factory → toggle → integrasi → verifikasi

### Constraint Mapping — deep
Key insights:
- **Real constraints**: TypeScript tipe harus valid; dummy data harus conform ke interface yang ada; inisialisasi hanya di client; 60 hari terakhir harus ada timestamp
- **Soft constraints**: data sebaiknya konsisten lintas menu; toggle persist saat navigasi; tidak mengubah backend
- **Unknown**: berapa banyak API modul & return type yang berbeda → resolved via spike (11 modul API + ~12 page inline-fetch)

---

## Advisor Synthesis

Tiga metode independen mengarah ke **factory pattern** sebagai arsitektur inti. Advisor mengonfirmasi bahwa **intersepsi di level modul API** (bukan level rendering atau network) adalah satu-satunya cara agar fungsi downstream tetap berjalan dan bisa diverifikasi. Trade-off utama: Direction B (Zustand entity store + per-page selector) lebih berat upfront tapi **satu-satunya yang menjamin konsistensi data lintas menu** — syarat mutlak untuk memverifikasi fungsi analitik & data intelligence.

---

## Spike Results

**Unknown resolved:** Berapa banyak modul API & apakah satu pola bisa menangani semua?

**Finding:** Ada 11 modul API (`data-intelligence-api.ts`, `operations-api.ts`, `admin/outlets/api.ts`, `admin/products/api.ts`, `admin/promotions/api.ts`, `admin/sales-performance/api.ts`, `admin/users/api.ts`, `sales/orders/api.ts`, `sales/performance/api.ts`, `lib/api.ts` + `app/analytics/page.tsx`, `app/dashboard/page.tsx`, `app/payments/page.tsx`, `app/operations/page.tsx`, `app/delivery/page.tsx`). Setiap modul punya shape return yang berbeda — tidak bisa pakai generic interceptor tunggal. Pola yang bisa diseragamkan: **satu fungsi generator dummy per tipe return, per modul API**.

**Implication:** Direction B (Zustand entity store + per-page selector) memerlukan ~20 fungsi generator dummy (bukan 1 generic), tetapi ini sudah cukup terstruktur karena masing-masing sudah punya tipe TypeScript yang jelas.

---

## Approach Directions

### Direction A: API-module wrapper + inline-page refactor
Setiap fungsi fetch di 11 modul API ditambah guard `if (isDummy) return dummyX()`. Page dengan `fetch` inline di-refactor ke helper functions agar guard yang sama berlaku.
+ Minimum churn arsitektural — modul API yang sudah ada tidak diubah, hanya ditambah guard.
− Banyak refactor di ~12 page `.tsx` yang memanggil `fetch` langsung; risiko satu page terlewat → fungsi di situ tidak terverifikasi.

### Direction B: Zustand entity store + per-page selector (RECOMMENDED)
Bangun satu `dummySlice` (Zustand) + `DummyFactory` relasional (outlet ↔ order ↔ items ↔ payment ↔ invoice ↔ delivery ↔ supplier). Setiap modul API punya satu fungsi generator per tipe return. Page mengganti call-site dengan `isDummy ? dummyX() : fetchX()`. Inline-fetch page dikonsolidasi ke helper per halaman.
+ **Satu-satunya yang menjamin konsistensi data lintas menu** — outlet X muncul konsisten di orders/payments/invoices/delivery. Guard hanya 1 pola per tipe return yang di-review per file.
+ Seluruh komponen downstream (chart, aggregation, GeoMap, KPI, funnel, stock planning) **tidak perlu diubah** — mereka menerima data dari fungsi yang sama persis.
− Upfront cost lebih besar: perlu menulis DummyFactory + ~20 generator + refactor page inline-fetch ke helper.

### Direction C: Network-level interception (MSW / fetch override)
Pasang Mock Service Worker atau override `global.fetch` untuk path `/api/*` agar mengembalikan dummy response sesuai route. Tidak ada file yang disentuh.
+ Zero refactor; 100% coverage otomatis termasuk page inline-fetch.
+ Tidak perlu ubah satu pun modul API.
− Data tidak relasional (tiap route jawab sendiri-sendiri); membangun route matcher yang benar untuk ~50 endpoint = kompleks; POST mutations (orders, payments) perlu fake-mutasi; debug "ini bug kode atau bug mock?" sulit.

---

## Open Questions for pocket-grinding

- [ ] Apakah `analytics/page.tsx` dan `dashboard/page.tsx` memanggil `fetch` inline atau sudah menggunakan modul API helper? → spike sudah jawab: inline di `.tsx` — perlu dikonsolidasi
- [ ] Apakah ada Zod schema yang sudah ada untuk validasi shape data, atau baru TypeScript interface saja? → jika belum ada Zod, apakah perlu ditambahkan untuk dummy data validation?
- [ ] Apakah dummy mode perlu persist ke localStorage (survive page refresh), atau cukup in-memory Zustand (reset saat refresh)?
- [ ] Koordinat GeoMap untuk 5 kota JABODETABEK — sudah ada di codebase atau perlu ditentukan manual?
- [ ] Apakah `geographic.php` migration (endpoint geographic) sudah ada di backend, atau dummy mode harus fokus hanya di frontend tanpa API baru?

---

## Recommended Direction

**Direction B** — Zustand entity store + per-page selector. Satu-satunya yang memenuhi requirement "Analitik & Data Intelligence harus jalan di atas data dummy sehingga fungsinya bisa diverifikasi" SEKALIGUS konsistensi lintas menu. Trade-off effort upfront dibayar oleh determinisme data (chart salah = bug kode, bukan noise).

---

## Handoff Context (for pocket-grinding)

When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction B as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
