# Pitch Exploration: outlet-recommendation-discovery
Date: 2026-10-06 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Outlet membutuhkan pengalaman pemesanan yang mendukung dua tujuan berbeda: mengulang pesanan dengan cepat dan menemukan produk baru yang relevan. Fitur reorder sudah menggunakan riwayat pembelian, sedangkan endpoint rekomendasi yang ada juga meranking produk yang pernah dibeli; discovery produk baru belum tersedia.

## Root Tension
Membantu outlet menemukan produk baru tanpa memperlambat reorder atau menyajikan ranking yang tidak relevan dan sulit dipercaya.

## Key Constraints
- Reorder client-side sudah tersedia (`useOutletReorder` — favorit + 3 order terbaru) dengan guard stok, status produk, dan batas kredit (`apps/web/src/hooks/useOutletReorder.ts`, `apps/web/src/components/ReorderSection.tsx`).
- API rekomendasi `GET /api/ai/recommendations` → `RecommendationService::recommend()` (`apps/api/app/Services/RecommendationService.php`) menggunakan heuristik `purchase_frequency_v1` yang meranking produk yang **sudah pernah dibeli** outlet — tumpang tindih dengan reorder, bukan discovery.
- Belum ada sinyal discovery untuk produk yang belum pernah dibeli: grep co-purchase/popularity/co-bought di `apps/api/app` dan `apps/api/routes` menghasilkan nol hasil.
- Konsumen existing rekomendasi: halaman `/analytics` (`apps/web/src/app/analytics/page.tsx`) menampilkan rekomendasi heuristik dengan badge "Heuristik" dan `TrustLabel` suffisiensi data.
- Guard aktif tersedia dan teruji (`AITest::test_outlet_purchase_history_returns_bounded_ranked_recommendations`): `is_active = true`, `stock_quantity > 0`, supplier berlangganan aktif, status order valid.
- v1 tidak memerlukan ML; outlet harus tetap memutuskan sendiri apakah akan menambahkan saran ke keranjang.
- Rekomendasi harus menjaga relevansi serta ketersediaan produk.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Tujuan utama discovery perlu diklarifikasi: menambah variasi belanja, menemukan produk pengganti, atau meningkatkan nilai keranjang.
- Waktu tampil rekomendasi menentukan nilainya: sebelum memilih produk, saat menyusun keranjang, atau setelah melihat riwayat.
- Produk yang harus dikecualikan perlu aturan eksplisit: sudah dibeli, stok habis, tidak aktif, di luar kategori outlet, atau tidak tersedia untuk area outlet.
- Tingkat transparansi alasan rekomendasi menentukan kepercayaan ("kenapa produk ini disarankan?").
- Perlu diputuskan apakah rekomendasi per outlet atau tren umum yang cukup.
- Rekomendasi tidak boleh mengganggu outlet yang hanya ingin mengulang pesanan cepat.

### First Principles Thinking — creative
Key insights:
- Kebutuhan dasarnya adalah membantu outlet mengambil keputusan belanja dengan lebih sedikit pencarian dan lebih sedikit tebakan.
- Reorder dan discovery adalah dua pekerjaan berbeda: reorder mengurangi usaha mengulang; discovery membantu menemukan opsi baru.
- "Optimal" bagi outlet belum tentu produk dengan skor tertinggi — produk harus relevan, tersedia, dan harganya dapat diterima.
- Rekomendasi sederhana dengan alasan yang bisa dimengerti berpotensi lebih dipercaya daripada ranking canggih yang tidak transparan.
- Sistem bisa memulai dari sinyal yang sudah tersedia; penggunaan ML bukan prasyarat untuk menguji manfaat discovery.
- Keputusan akhir tetap pada outlet: rekomendasi membantu memilih, bukan otomatis menambah produk ke order.

### Six Thinking Hats — structured
Key insights:
- Putih/fakta: daftar reorder di order form, histori pembelian, katalog produk, dan API rekomendasi outlet sudah ada.
- Merah/perasaan: outlet merasa terbantu oleh saran yang jelas, tetapi curiga jika produk terasa dipromosikan tanpa alasan.
- Kuning/manfaat: rekomendasi dapat mengurangi waktu mencari produk dan membantu memperluas belanja yang relevan.
- Hitam/risiko: duplikat dengan reorder, perubahan stok/harga, dan ranking tidak relevan dapat mengurangi kepercayaan.
- Hijau/kemungkinan: pisahkan tampilan "Pesan Lagi" dari "Coba produk lain"; sertakan alasan sederhana dan opsi "lihat semua".
- Biru/proses: ukur apakah rekomendasi dilihat, ditambahkan, dan dipertahankan dalam order; evaluasi bertahap sebelum memperluas cakupan.

### Reverse Brainstorming — creative
Key insights:
- Tampilkan produk yang sama berulang kali tanpa menjelaskan alasan rekomendasi.
- Campurkan barang biasa dibeli dengan produk baru tanpa pemisah, sehingga outlet tidak tahu mana yang bisa langsung dipesan ulang.
- Tampilkan produk stok habis, tidak aktif, atau harga sudah berubah.
- Gunakan sinyal popularitas global yang tidak sesuai kategori atau pola belanja outlet.
- Penuhi layar order dengan rekomendasi sehingga proses reorder cepat justru melambat.
- Klaim "optimal" atau "AI" tanpa dasar yang terlihat, lalu gagal memberi outlet kontrol untuk mengabaikan saran.

---

## Advisor Synthesis
Reorder dan discovery adalah dua kebutuhan berbeda; discovery harus melengkapi, bukan memperlambat, "Pesan Lagi". Kandidat v1 adalah daftar produk baru yang relevan dengan alasan sederhana, guard ketersediaan, dan pilihan untuk mengabaikannya. Untuk v1, singkirkan ML atau klaim "optimal" tanpa bukti, penambahan otomatis ke keranjang, dan rekomendasi yang menduplikasi reorder. Ketidakpastian teknis (perilaku `GET /recommendations` yang sudah ada vs klaim pitch lama) diselesaikan via spike code scan.

---

## Spike Results

**Unknown resolved:** Apakah `GET /recommendations` menghasilkan produk *belum pernah dibeli* yang outlet-aware, dengan filter status/stok/harga?
**Finding:** Tidak. `RecommendationService::recommend()` (`apps/api/app/Services/RecommendationService.php`) meranking produk yang sudah pernah dibeli outlet berdasarkan frekuensi/kuantitas (`method: purchase_frequency_v1`). Guard aktif, stok, supplier, dan status order tersedia dan teruji, tetapi tidak ada endpoint atau logika yang mengembalikan produk belum-pernah-dibeli (grep co-purchase/popularity nol hasil).
**Implication:** Discovery produk baru membutuhkan sumber ranking **baru** atau komposisi dari sinyal existing. Reorder client-side dan rekomendasi server saat ini berpotensi tumpang tindih karena bersumber dari riwayat pembelian outlet yang sama.

---

## Approach Directions

### Direction A: Discovery terpisah dari reorder
Pertahankan "Pesan Lagi" seperti sekarang, lalu tambahkan area terpisah untuk produk yang belum pernah dibeli, berdasarkan sinyal sederhana seperti produk populer atau kategori yang relevan.
+ Dua tujuan belanja tetap jelas; reorder cepat tidak terganggu.
− Membutuhkan sinyal discovery baru dan aturan untuk mencegah saran generik.

### Direction B: Satu daftar rekomendasi terpadu
Gabungkan produk reorder dan discovery dalam satu daftar berperingkat, dengan label sumber/alasan pada setiap item.
+ Satu daftar ringkas dan pengalaman rekomendasi terasa terpadu.
− Lebih mudah mengaburkan perbedaan antara barang rutin dan produk baru, serta berpotensi membuat reorder lebih lambat.

### Direction C: Discovery kontekstual di alur order
Pertahankan reorder utama, lalu munculkan saran produk baru hanya setelah outlet mulai menyusun keranjang atau memilih kategori tertentu.
+ Saran muncul pada konteks yang relevan dan mengurangi gangguan saat membuka halaman order.
− Penemuan produk bisa kurang terlihat dan manfaatnya lebih sulit diukur.

---

## Open Questions for pocket-grinding
- [ ] Sumber sinyal discovery v1 apa yang realistis — produk populer umum, produk populer dalam kategori outlet, atau sinyal komposisi lain dari data yang sudah ada (tanpa ML)?
- [ ] Di mana dan bagaimana tampilan daftar discovery ditempatkan — di `/orders` di bawah "Pesan Lagi", di `/dashboard`, atau keduanya, dan dalam bentuk apa (section, drawer, tab)?
- [ ] Aturan pengecualian discovery apa yang berlaku — apakah produk yang sudah ada di "Pesan Lagi" harus selalu dikecualikan, dan bagaimana menangani perubahan harga/stok antara tampil dan checkout?

---

## Recommended Direction
Direction A — paling menjaga fungsi reorder yang sudah ada, sambil memberi ruang untuk menguji discovery secara terpisah dan transparan sebelum menggabungkannya.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction A as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
