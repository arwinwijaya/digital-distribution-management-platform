# Pitch Exploration: barang-menu-clarity
Date: 2026-09-26 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Menu Barang (`apps/web/src/app/admin/products`) hanya menampilkan 4 kolom inti
(nama, SKU, harga, stok) padahal data identitas (kategori, deskripsi, supplier,
status aktif) sudah tersedia di API `/products` — sehingga admin tidak dapat
membedakan barang, satuan, harga, dan kesehatan stok tanpa membuka satu-satu,
dan tidak tahu bahwa sebagian barang sebenarnya tidak dapat dibeli.

## Root Tension
Permintaan detail (agar tidak ambigu) vs. beban kognitif tabel — menambah kolom
secara bebas justru membuat tabel sulit dipindai dan menciptakan kerancuan baru.
Resolusinya harus struktural (detail-on-demand), bukan "tambah kolom".

## Key Constraints
- **Satu tampilan universal untuk semua admin** (tanpa presets per-peran) — dipilih user.
- Data `category`, `description`, `is_active`, `supplier_id` **sudah ada** di model & response API → perbaikan identitas = presentation-only, tanpa migrasi.
- `price` dan `stock_quantity` bersifat **cross-cutting**: `price` = harga jual tunggal, dasar total order (`OrderCreationService:217`), analitik, rekomendasi, WhatsApp, marketplace. `stock_quantity` integer polos, dipotong saat order (`OrderCreationService:237`). Mengubah maknanya menyebar ke seluruh sistem.
- Tidak ada field **satuan/UoM** maupun **harga bertingkat** (beli/ecer/grosir) di codebase mana pun.
- Harus tetap kompatibel dengan dummy guard layer (`apps/web/src/dummy/*`) + test existing (`page.test.tsx`).
- Tabel harus tetap dapat dipindai di desktop & mobile (horizontal scroll = masalah).
- Katalog publik (`ProductCatalog.tsx`) saat ini **lebih lengkap** dari menu admin — inkonsistensi yang perlu diperbaiki.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Ambiguitas menyentuh 4 dimensi sekaligus: identitas, satuan, harga, stok.
- `category`/`description`/`supplier` ada di API tapi disembunyikan di UI admin.
- Tidak ada satuan eksplisit — "stok 50" tidak diketahui 50 pcs/dus/kg.
- Tidak ada penanda barang yang tidak dapat dibeli (supplier non-aktif / `is_active=false`).
- Tidak ada riwayat stok, hanya riwayat harga.

### First Principles Thinking — creative
Key insights:
- Menu Barang = sumber kebenaran identitas & status, bukan sekadar daftar harga.
- Setiap kolom harus menjawab keputusan: beli berapa, jual berapa, stok aman?, mana yang butuh perhatian?
- Asumsi lama yang perlu dibongkar: "hanya admin gudang" (nyata: semua admin), "stok = angka tunggal", "harga = satu angka".
- Minimum true requirement: tiap baris menjawab "barang apa, stok berapa dalam satuan apa, harga untuk siapa, aman atau tidak".

### Six Thinking Hats — structured
Key insights:
- **White:** 7 kolom sekarang; kategori/deskripsi/status/supplier tersedia tapi tidak tampil; hanya ada riwayat harga.
- **Red:** frustrasi "50 pcs atau 50 dus?", "harga beli atau jual?".
- **Yellow:** kategori + satuan + status → grouping, laporan, hilang ambiguitas, reorder proaktif.
- **Black:** terlalu banyak kolom → cognitive overload; join supplier = query berat; UoM = migrasi schema.
- **Green:** expandable row, badge status, column visibility toggle, quick-actions per baris.
- **Blue:** implementasi bertahap — presentasi dulu (aman), lalu model extension (berisiko).

---

## Advisor Synthesis
Advisor menandai bahwa pitch ini menggantung pada **kluster biaya mana** kerancuan
user berada, dan memisahkannya jadi tiga: **A. Presentation** (murah/reversible),
**B. Data-model** (migrasi/back-compat), **C. Structural** (mahal). Advisor juga
menaikkan **satuan (UoM)** dan **harga bertingkat** sebagai hipotesis akar — khas
distribusi Indonesia dan tidak ada di model. Advisor memperingatkan bahwa bila
`price`/`stock_quantity` ternyata dikonsumsi lintas sistem, menambah satuan/harga
tier = cross-cutting concern, bukan fix lokal. Advisor membuang sparkline/CSV/
virtualization sebagai noise dan menggugurkan role-based presets karena user
memilih tampilan tunggal.

---

## Spike Results

**Unknown resolved:** Apakah `price`, `stock_quantity`, dan `category` dikonsumsi di tempat lain (bukan hanya menu Barang)?

**Finding:** Ya — cross-cutting.
- `price` = harga jual tunggal. Dasar total order (`OrderCreationService:217`, `WhatsAppOutboundService`, `RecommendationService`, `MarketplaceController`, `AdminProductController` price update).
- `stock_quantity` = integer tanpa satuan. Dicek & dipotong langsung saat order (`OrderCreationService:211,237`) dengan pesan "Only N unit(s) remain"; dipakai `StockPlanningService`, `RecommendationService`.
- `category` = string bebas, dipakai `RecommendationService` (grouping), marketplace, katalog.
- **Tidak ada** field unit/UoM maupun harga tier di manapun.

**Implication:** Menambah satuan/konversi atau harga bertingkat = perubahan schema yang menyebar ke seluruh sistem (order, analitik, rekomendasi, WhatsApp, dummy layer, test) → risiko tinggi & sulit dibatalkan. Sedangkan menampilkan kategori/status/supplier/deskripsi sepenuhnya lokal & aman.

---

## Approach Directions

### Direction A: Clarity Pass + Detail-on-Expand (Presentation-first)
Tambah kolom kategori + status badge yang sudah ada di API, filter kategori &
status, lalu ubah baris jadi **expandable** untuk membuka panel detail
(deskripsi, supplier, riwayat harga, indikator stok statis).
+ Menyelesaikan 4 dimensi tanpa migrasi schema; cepat, reversible, menyamakan/melebihi katalog publik
− Satuan & harga bertingkat tetap tidak ada di model — ambiguitas fundamental tersisa

### Direction B: Model Extension (UoM + Harga Bertingkat)
Tambah field `unit` + konversi (pcs↔dus↔kg), harga beli/jual/grosir, dan ambang
minimum ke schema, lalu tampilkan di tabel & panel detail.
+ Menghilangkan akar ambiguitas klasik distribusi secara permanen
− Cross-cutting: menyentuh order, analitik, rekomendasi, WhatsApp, dummy layer, seluruh test — mahal & sulit dibatalkan

### Direction C: Hybrid bertahap — Detail-on-Expand sekarang, Model Extension fase 2
Mulai dengan Direction A (klarifikasi penuh tanpa migrasi), rancang ekspansi model
(Direction B) sebagai iterasi terpisah setelah kebutuhan lapangan terverifikasi.
+ Kejelasan cepat sambil menunda komitmen schema yang mahal
− Dua fase desain/pengujian; ambiguitas satuan bertahan selama fase 1

---

## Open Questions for pocket-grinding
- [ ] Apakah konversi satuan (pcs↔dus) diperlukan untuk kalkulasi order, atau cukup sebagai metadata tampilan?
- [ ] Bila harga bertingkat ditambah, bagaimana `OrderCreationService` memilih tier yang berlaku (per outlet? per kuantitas? per pelanggan)?
- [ ] Apakah `category` perlu jadi enum terkontrol (seperti `Outlet::VALID_CATEGORIES`) atau tetap string bebas?
- [ ] Ambang minimum stok: per produk global atau per produk-per-gudang (butuh model multi-gudang)?
- [ ] Apakah riwayat stok (stock movement) diperlukan di fase 1, atau cukup indikator ambang statis?
- [ ] Bagaimana strategi migrasi/back-compat untuk `unit` saat data existing tidak punya satuan?

---

## Recommended Direction
Direction C — user memilih "satu tampilan untuk semua admin", dan temuan spike
bahwa `price`/`stock_quantity` bersifat cross-cutting membuat perubahan model
berisiko bila dilakukan sekaligus; hybrid memberi kejelasan segera tanpa membakar jembatan.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction C as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
- Ground design in real files: `apps/web/src/app/admin/products/page.tsx`,
  `apps/web/src/app/admin/products/api.ts`, `apps/api/app/Http/Controllers/ProductController.php`,
  `apps/api/app/Models/Product.php`, `apps/api/app/Services/OrderCreationService.php`
