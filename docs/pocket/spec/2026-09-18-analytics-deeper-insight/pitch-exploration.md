# Pitch Exploration: analytics-deeper-insight
Date: 2026-09-18 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Menu "Analitik AI" (`/analytics`) hanya menyajikan 3 kartu tipis yang dibangun dari
endpoint `/ai/*` (daftar rekomendasi produk, badge segmentasi, grid prakiraan 4
minggu) tanpa konteks perbandingan apa pun. Akibatnya owner/admin tidak dapat
membaca gambaran strategis dari halaman ini — angka muncul tanpa baseline,
tanpa implikasi, dan tanpa arah tindakan. Kita ingin memperdalam kartu yang ada
(drill-down) sekaligus menambah jenis insight baru (perubahan periode,
needs-attention, narasi), seluruhnya diturunkan dari data yang sudah ada.

## Root Tension
Menambah **kedalaman** tanpa membuat Analitik berubah menjadi **BI suite**.
"Detail" di sini berarti lebih banyak **konteks** (baseline, alasan, aksi) di
sekitar metrik yang **lebih sedikit** — bukan lebih banyak metrik mentah. Setiap
penambahan harus lulus uji: apakah ia mengubah keputusan owner, atau hanya
menambah kebisingan?

## Key Constraints
- **Wrong layer today**: Analitik terhubung ke `/ai/*` (lapisan AI), bukan ke
  `/analytics/dashboard` (lapisan analitik) yang justru memuat metrics +
  sales_trends + outlet_performance. Ini kecelakaan penamaan, bukan keputusan desain.
- **Zero new collection**: seluruh insight dapat diturunkan dari tabel
  orders/payments/outlets/products yang sudah ada. Tidak ada pipeline baru.
- **Reuse-before-build**: `/analytics/dashboard`, `/ai/recommendations|forecast|segmentation`,
  `Charts.tsx` (`SalesTrendChart`, `OutletPerformanceChart`), dan UI primitives
  (`StatCard`, `Badge`, `Card`, `Table`, `TablePagination`, `EmptyState`) sudah tersedia.
- **Dummy-mode parity WAJIB**: setiap field baru harus punya padanan di
  `dummy.dashboardAdmin` / `dummy.analytics`; dummy mode adalah kontrak rilis.
- **Role-scoping**: halaman analitik bersifat admin/owner; outlet user hanya boleh
  melihat sinyal miliknya sendiri (pola sudah ada di `AIController::segmentation`).
- **Bounded queries**: `AnalyticsService::MAX_DATE_RANGE_DAYS = 366`,
  `OUTLET_PERFORMANCE_LIMIT = 10`. Jangan menambah beban query tak terbatas.
- **Heuristik deterministik & transparan**: tidak ada ML/LLM produksi
  (out-of-scope Phase 3). `data_sufficiency`, `measurement`, dan `method` sudah
  dikembalikan API dan harus ditampilkan inline.
- **Single source of truth**: angka di Analitik tidak boleh bertentangan dengan
  halaman Dashboard.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- "Detail" ambigu: bisa berarti lebih banyak angka, atau angka lebih sedikit
  dengan makna. Perlu disepakati lebih dulu.
- Keputusan owner yang relevan: restock, mengejar outlet menunggak, promosi
  produk, menilai performa. Kartu saat ini tidak menginformasikan satu pun.
- Tanpa baseline (periode lalu / target / peer outlet), tidak ada angka yang
  actionable.
- Gap-nya bisa berupa gap **data**, **presentasi**, atau **framing** — perlu
  dipastikan mana yang sebenarnya.
- Aset yang sudah ada tapi tidak terlihat owner: sales_trends, outlet_performance,
  geographic/supplier/stock, finance metrics, measurement funnel.

### First Principles Thinking — creative
Key insights:
- Insight = observasi + perbandingan + implikasi. Agregat mentah adalah *data*,
  bukan *insight*.
- Semua bahan mentah sudah ada; tidak ada yang perlu dikumpulkan ulang.
- Analitik dibangun di lapisan AI karena namanya mengandung "AI" — bukan
  keputusan arsitektur yang disengaja.
- Unit irreducible insight owner adalah **"X vs Y atas Z"** (periode ini vs lalu,
  outlet ini vs peer, produk A vs B).
- "Lebih detail" sebenarnya berarti "menambahkan dimensi perbandingan" — yang
  hilang adalah *baseline*, bukan *metrik tambahan*.

### Six Thinking Hats — structured
Key insights:
- ⚪ Facts: Analitik = 3 item dari `/ai/*`; `/analytics/dashboard` + `/admin/analytics/*`
  + `FinanceMetricsController` sudah memuat material jauh lebih kaya.
- 🔴 Emotions: owner merasa *underwhelmed* ("3 kartu bukan analitik") dan diam-diam
  tidak percaya pada prakiraan.
- 🟡 Benefits: owner bisa self-serve, mengurangi pertanyaan ad-hoc, keputusan
  restock/follow-up lebih baik, biaya koleksi data nol.
- ⚫ Risks: scope creep ke BI suite, duplikasi/konflik angka dengan Dashboard,
  performa agregat berat, kebocoran role, dummy-mode drift, heuristik tampak
  seperti fakta.
- 🟢 Creativity: delta chips, "Needs attention" strip, ringkasan naratif,
  click-through insight → halaman terfilter.
- 🔵 Process: batasi scope, reuse endpoint dulu, parity dummy & role gate non-negotiable.

### Reverse Brainstorming — creative
Key insights (cara membuat insight tak berguna → dibalik jadi persyaratan):
- Tampilkan total tanpa perbandingan → **setiap angka butuh baseline**.
- Buat owner menebak makna → **setiap insight punya baris "so what"**.
- Campur heuristik dengan fakta tanpa label → **tandai heuristik vs terukur**.
- Kubur eksepsi di rata-rata → **dahulukan outlier / needs-attention**.
- Halaman lambat → **agregat terbatas & cepat, lazy-load seksi berat**.
- Tampilkan semua ke semua orang → **insight ter-scope per role**.
- Angka bertentangan dengan Dashboard → **single source of truth**.

### Assumption Reversal — deep
Key insights:
- "Analitik = halaman AI" → **Analitik = rumah analitik; AI adalah satu modul**.
- "Lebih detail = lebih banyak metrik" → **lebih detail = lebih banyak konteks
  di sekitar lebih sedikit metrik**.
- "Butuh data/pipeline baru" → **butuh derivasi baru dari data lama**.
- "Detail hanya untuk admin" → **detail dibentuk per role** (admin lintas-outlet,
  outlet self-only, finance berfokus uang).
- "Heuristik adalah keterbatasan" → **heuristik adalah fitur** (explainable,
  deterministik, tanpa biaya ML).
- "Ini tugas frontend" → **ini tugas kontrak API dulu**, frontend kedua.

---

## Advisor Synthesis
Kelima metode konvergen pada satu hal: **elemen yang hilang adalah dimensi
perbandingan (baseline), bukan kuantitas metrik**. Temuan kedua: Analitik berada
di lapisan arsitektur yang salah — ia menempel pada `/ai/*` padahal
`/analytics/dashboard` sudah memuat permukaan insight owner yang sesungguhnya.
Semua data yang dibutuhkan sudah ada, sehingga ini pekerjaan **derivasi +
komposisi + presentasi**, bukan pengumpulan data baru. Risiko utama bukan
kekurangan pembangunan, melainkan scope creep menjadi BI suite — sehingga batas
yang tegas (Analitik = pandangan harian owner; `/data-intelligence` = power user)
harus ditetapkan sejak awal. Trust scaffolding (`data_sufficiency`,
`measurement`, `method`) sudah dikembalikan API dan sebaiknya diangkat ke UI,
mengubah "heuristik" dari kelemahan menjadi fitur.

---

## Spike Results

**Unknown resolved:** Dapatkah kita mengkomposisi endpoint yang ada untuk
perbandingan periode, atau butuh kontrak backend baru?

**Finding:** (code scan)
- `/analytics/dashboard` sudah mengembalikan `metrics` + `sales_trends` +
  `outlet_performance` → dapat dipakai ulang sebagai sumber "periode berjalan".
- **Tidak ada** data periode sebelumnya. Dua opsi:
  (a) komposisi frontend — panggil dua kali dengan rentang tanggal bergeser.
      Viabel untuk delta sederhana, tetapi menggandakan request dan **tidak bisa**
      menghasilkan delta peringkat outlet (peringkat periode lalu tidak dikembalikan).
  (b) derivasi backend — perluas `AnalyticsService::dashboard()` agar juga
      mengembalikan agregat periode sebelumnya + delta. **Preseden sudah ada**:
      `PilotMetricsService` menghitung `deltaPercent` di sisi server (baris 66–75).
- `Charts.tsx` sudah mengekspor `SalesTrendChart` + `OutletPerformanceChart`
  (reuse, bukan rebuild).
- `StatCard` / `Badge` / `Table` sudah ada → delta chip & tabel drill-down tidak
  butuh UI kit baru.
- Dummy mode (`dummy.dashboardAdmin` + `dummy.analytics`) berarti setiap field
  baru memerlukan padanan dummy.

**Implication:** Komposisi frontend mungkin untuk delta sederhana, tetapi
**kontrak turunan sisi server lebih tepat** (ada preseden, single source of truth,
memungkinkan delta peringkat, menghindari request 2x). Parity dummy tetap wajib
di kedua opsi.

---

## Approach Directions

### Direction A: Deepen-in-place (delta layer pada 3 kartu yang ada)
Tambah baseline/delta + trust label + baris "so-what" ke kartu rekomendasi,
segmentasi, dan forecast yang sudah ada; endpoint diperluas server-side dengan
agregat periode sebelumnya.
+ Perubahan terkecil, risiko rendah, langsung menjawab "detail = konteks".
− Belum menjawab "gambaran strategis" lintas outlet; owner tetap tak melihat
  performa global.

### Direction B: Analytics home (komposisi BI + AI dalam satu halaman)
Jadikan Analitik sebagai halaman analitik owner: strip metrik dengan delta chips
(dari `/analytics/dashboard`), grafik tren + peringkat outlet (reuse `Charts.tsx`),
strip "Needs attention", lalu seksi AI (rekomendasi/segmentasi/forecast) di
bawahnya. Dashboard tetap berfokus operasional; Analitik menjadi pandangan strategis.
+ Menjawab kedua permintaan (perdalam + jenis baru) sekaligus; paling sesuai
  mental model owner; memakai ulang endpoint/chart/primitif yang ada.
− Cakupan lebih besar; memerlukan boundary tegas vs Dashboard agar tidak duplikasi.

### Direction C: Insight feed (narrative-first, bukan dashboard-first)
Halaman berupa daftar kartu insight berbahasa manusia, masing-masing berisi
observasi + perbandingan + implikasi + tautan aksi ("Penjualan turun 12% vs bulan
lalu; 3 outlet penyumbang utama"). Metrik mentah disembunyikan di balik drill-down.
+ Paling "detail" secara makna; langsung menjawab "apa yang harus saya lakukan".
− Butuh generasi narasi (meski deterministik/template); berisiko terasa
  "AI-generated" bila tak hati-hati.

---

## Open Questions for pocket-grinding
- [ ] Apakah boundary Dashboard vs Analitik yang tepat: Dashboard = operasional
      harian, Analitik = strategis lintas-periode? Atau ada tumpang tindih yang
      harus dipecah lebih eksplisit?
- [ ] Kontrak delta: apakah `AnalyticsService::dashboard()` diperluas dengan blok
      `previous_period` + `delta`, atau dibuat endpoint `/analytics/insight` baru?
      Bagaimana bentuk GWT-nya?
- [ ] Definisi "periode sebelumnya" untuk rentang arbitrer: panjang sama persis,
      atau bulan/kalender sebelumnya?
- [ ] Delta peringkat outlet: apakah butuh menyimpan/menghitung peringkat periode
      sebelumnya, dan apa batas performanya?
- [ ] Seksi "Needs attention": aturan deterministik apa yang dipakai (ambang
      penurunan %, outstanding menunggak, produk turun) — dan bagaimana ia
      menghindari false positive?
- [ ] Generasi narasi "so-what": template deterministik di backend, atau
      komposisi string di frontend? Bagaimana menjamin netralitas bahasa (id-ID)?
- [ ] Role-scoping final: apakah outlet user mendapat versi Analitik (self-only),
      atau halaman ini murni admin/owner?
- [ ] Bentuk parity dummy untuk setiap field baru — perlu definisi fixture.
- [ ] Apakah insight harus clickable ke halaman terfilter (orders/outlets/sales),
      dan parameter filter apa yang dipakai?

---

## Recommended Direction
**Direction B** — memenuhi kedua permintaan (perdalam + jenis insight baru) untuk
audiens owner, memakai ulang endpoint/chart/primitif yang sudah ada sehingga
biayanya rendah, dan memposisikan AI sebagai satu seksi alih-alih seluruh halaman.
Direction C dapat menjadi lapisan di atas B pada iterasi berikutnya.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction B as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
