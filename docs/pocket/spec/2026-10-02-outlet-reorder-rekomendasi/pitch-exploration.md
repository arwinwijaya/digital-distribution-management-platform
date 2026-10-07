# Pitch Exploration: outlet-reorder-rekomendasi
Date: 2026-10-02 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Outlet mengalami hambatan saat memesan ulang karena harus mencari dan memilih produk secara manual, serta belum memiliki panduan untuk menemukan produk baru yang relevan. Proses order perlu membantu outlet mengulang pembelian sebelumnya dan mengeksplorasi produk yang belum pernah dibeli.

## Root Tension
Mempermudah reorder deterministik dengan data yang ada sekaligus merekomendasikan produk baru secara relevan dan tepercaya, tanpa mengabaikan stok, harga, atau batas kredit dan tanpa kompleksitas berlebihan.

## Key Constraints
- Dashboard outlet telah memuat hingga 1.000 order terbaru beserta `product_id`, kuantitas, nama produk, dan waktu order; `topFavorites` menghitung produk favorit di client.
- Tautan "Pesan lagi" dashboard saat ini menuju `/orders` tanpa membawa konteks produk atau kuantitas; `OrderForm` memulai dari katalog umum.
- API `/products` menyediakan filter status aktif/stok, tetapi belum ada endpoint ranking rekomendasi produk outlet-aware atau ranking popularitas produk.
- API `/orders` mendukung pembuatan order berisi items dan idempotency key.
- `RecommendationActionService` menangani aksi rekomendasi, bukan menghasilkan katalog rekomendasi produk.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Perlu dibedakan apakah kebutuhan utama adalah menemukan produk, mengingat produk, atau mengulang kuantitas.
- Reorder bisa berupa satu produk, favorit, atau seluruh order sebelumnya.
- Sinyal yang ada mencakup riwayat outlet; popularitas dan co-purchase belum disediakan sebagai ranking API.
- Perangkat akses outlet dan tingkat personalisasi yang diinginkan masih perlu diklarifikasi.

### First Principles Thinking — creative
Key insights:
- Riwayat pembelian yang berulang adalah kebutuhan berkala; mengulang pencarian dari nol menciptakan pekerjaan yang tidak perlu.
- Order sebelumnya dapat menjadi template yang bisa diedit.
- Untuk v1, rekomendasi tidak harus memakai ML; sinyal sederhana dan beralasan dapat menjadi titik awal.

### Six Thinking Hats — structured
Key insights:
- Data order dan favorit sudah tersedia di dashboard.
- Reorder dapat meningkatkan kemudahan dan memakai data yang telah ada.
- Rekomendasi stok habis/tidak aktif atau tanpa alasan berisiko mengikis kepercayaan.
- Urutan yang disarankan: permudah reorder dahulu, kemudian discovery produk.

### Reverse Brainstorming — creative
Key insights:
- Katalog generik memaksa outlet mengingat dan mencari produk lagi.
- Kuantitas yang harus dimasukkan ulang menambah friksi.
- Rekomendasi tanpa alasan, stok tidak tersedia, atau tidak relevan mudah diabaikan.
- Rekomendasi lebih berguna di dalam form order sebagai titik keputusan.

### Role Playing — collaborative
Key insights:
- Outlet kecil mengutamakan daftar belanja yang familiar, cepat, dan tersedia.
- Outlet lebih besar mungkin membutuhkan produk baru dan kuantitas yang sesuai pola.
- Sales/admin membutuhkan rekomendasi yang bisa dipercaya dan mendukung proses order.
- Sistem perlu menjaga scoping outlet dan tidak membocorkan data outlet lain.

## Advisor Synthesis
Dua kebutuhan berbeda muncul: reorder riwayat (deterministik, risiko rendah) dan penemuan produk baru (butuh ranking serta alasan yang jelas). Implementasi yang ada telah menyediakan data riwayat di frontend, tetapi belum menghubungkan favorit dengan prefill order. Discovery produk kemungkinan membutuhkan sinyal/server API baru; rekomendasi ML penuh dinilai terlalu dini.

## Spike Results
**Unknown resolved:** Apakah API telah menyediakan sinyal server untuk rekomendasi produk belum pernah dibeli, termasuk ranking dan scoping per outlet?

**Finding:** Scan `ProductController`, `AnalyticsService`, `RecommendationActionService`, `OrderController`, dan `OrderListFilters` tidak menemukan endpoint ranking produk outlet-aware, popularitas produk, atau co-purchase. Katalog produk menyediakan filter produk aktif/stok; order outlet terscope dari identitas user.

**Implication:** Reorder dapat memanfaatkan data order dan API order yang ada. Discovery yang terpersonalisasi memerlukan sumber/ranking rekomendasi baru atau perlu dimulai dengan sinyal yang lebih sederhana.

## Approach Directions

### Direction A: Reorder cepat dari riwayat
Gunakan data riwayat/favorit yang tersedia untuk memulai order dengan produk dan kuantitas terisi, lalu biarkan outlet mengubahnya sebelum mengirim.
+ Implementasi relatif sederhana dan risiko rendah.
− Tidak menyelesaikan penemuan produk baru.

### Direction B: Discovery melalui rekomendasi backend
Tambahkan sumber ranking produk yang belum pernah dibeli oleh outlet, lalu tampilkan rekomendasi di konteks pemesanan dengan alasan yang dapat dipahami.
+ Membuka peluang menemukan produk baru yang relevan.
− Lebih kompleks dan kualitas rekomendasi bergantung pada sinyal, relevansi, serta pengamanan scoping.

## Open Questions for pocket-grinding
- [ ] Apakah v1 perlu mencakup kedua kebutuhan, atau memprioritaskan reorder lalu discovery?
- [ ] Untuk reorder, apakah outlet membutuhkan tombol per produk, favorit, seluruh order terakhir, atau kombinasi?
- [ ] Sinyal apa yang dapat diterima untuk discovery v1 (mis. produk populer, kategori, promosi, atau pola pembelian bersama)?
- [ ] Bagaimana menangani perubahan harga/stok sejak pembelian terakhir dan batas kredit?
- [ ] Apakah rekomendasi discovery harus dipersonalisasi atau cukup populer secara umum untuk tahap awal?

## Recommended Direction
Direction A sebagai fondasi pertama, dengan Direction B sebagai arah lanjutan. Reorder punya data dan jalur submit yang tersedia, sedangkan discovery belum memiliki API ranking dan perlu validasi sinyal serta privasi.

## Handoff Context (for pocket-grinding)
- Start with the problem statement above as Phase 1 context.
- Use Direction A as the initial working hypothesis for design proposals; evaluate Direction B as a complementary/next direction.
- Treat Open Questions above as Phase 3 Discovery targets.
- Do NOT treat Approach Directions as final architecture — validate through GWT first.
