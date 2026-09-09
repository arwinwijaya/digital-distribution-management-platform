# Panduan Pengguna — Digital Distribution Management Platform (DDP)

> Untuk: pemilik bisnis, admin, supplier, pemilik warung/outlet, tim sales, dan driver pengiriman.
> Dokumen teknis untuk IT/developer tersedia terpisah di `docs/dokumentasi-teknis.md`.

---

## Daftar Isi

1. [Apa itu DDP?](#1-apa-itu-ddp)
2. [Siapa saja penggunanya?](#2-siapa-saja-penggunanya)
3. [Alur kerja utama dalam 6 langkah](#3-alur-kerja-utama-dalam-6-langkah)
4. [Panduan per peran](#4-panduan-per-peran)
5. [Memesan lewat WhatsApp](#5-memesan-lewat-whatsapp)
6. [Status pesanan — cara membaca](#6-status-pesanan--cara-membaca)
7. [Pembayaran & limit kredit](#7-pembayaran--limit-kredit)
8. [Fitur pintar (AI & Analitik)](#8-fitur-pintar-ai--analitik)
9. [Contoh sehari-hari](#9-contoh-sehari-hari)
10. [Pertanyaan umum (FAQ)](#10-pertanyaan-umum-faq)
11. [Glosarium — istilah penting](#11-glosarium--istilah-penting)
12. [Bantuan & keamanan akun](#12-bantuan--keamanan-akun)

---

## 1. Apa itu DDP?

**Digital Distribution Management Platform (DDP)** adalah sistem digital yang menghubungkan seluruh rantai distribusi barang FMCG (makanan, minuman, kebutuhan harian):

```
Supplier / Brand ──► Platform DDP ──► Warung / Toko ──► Konsumen
```

Sederhananya: **warung bisa memesan barang lewat aplikasi atau WhatsApp, supplier bisa memasarkan produknya, pemilik bisnis bisa memantau semua penjualan dari dashboard, sales bisa merencanakan kunjungan, dan driver mengantar dengan rute yang jelas.**

Nilai utama DDP bukan sekadar "toko online", melainkan **mengelola jaringan distribusi**: database ribuan outlet, riwayat belanja, prediksi kebutuhan, dan efisiensi operasional.

**10 kemampuan utama:**

| # | Fitur | Manfaat singkat |
|---|---|---|
| 1 | Dashboard Manajemen | Melihat omzet, outlet aktif, produk terlaris sekilas |
| 2 | Manajemen Outlet/Warung | Data lengkap tiap warung + riwayat belanja |
| 3 | Manajemen Supplier & Brand | Kelola pemasok, harga beli/jual, margin |
| 4 | Katalog Produk Digital | Foto, harga, promo, stok — selalu terbaru |
| 5 | Sistem Order | Pesan → konfirmasi → siapkan → antar → bayar |
| 6 | Sales Force | Target, jadwal kunjungan, histori sales |
| 7 | Delivery | Penugasan driver, pelacakan, bukti antar |
| 8 | Pembayaran & Kredit | Limit kredit, tagihan, riwayat bayar |
| 9 | AI & Analitik | Rekomendasi produk, prediksi order, segmentasi outlet |
| 10 | WhatsApp | Pesan barang langsung via chat WA |

---

## 2. Siapa saja penggunanya?

| Peran | Siapa | Akses utama |
|---|---|---|
| **Pemilik / Admin** | Pemilik bisnis distribusi | Semua: dashboard, approve order, atur limit kredit, analitik |
| **Supplier** | Brand / principal | Katalog produknya, harga, stok |
| **Outlet / Warung** | Pemilik warung/toko | Belanja katalog, buat & lacak order, bayar, lihat limit |
| **Sales** | Tim penjualan lapangan | Jadwal & histori kunjungan outlet, bantu order |
| **Driver** | Tim antar | Daftar tugas antar, update status pengiriman |

Setiap pengguna login dengan email + kata sandi dan hanya melihat menu sesuai perannya. Contoh: pemilik warung tidak bisa menyetujui order sendiri — itu tugas admin.

---

## 3. Alur kerja utama dalam 6 langkah

Berikut perjalanan satu pesanan dari awal sampai lunas:

```
1. DAFTAR          Warung mendaftar / didaftarkan → punya akun + profil toko
        │
2. BELANJA         Warung buka katalog (aplikasi/web/WA) → pilih barang → buat pesanan
        │
3. KONFIRMASI      Admin memeriksa & menyetujui pesanan
        │
4. SIAPKAN+ANTAR   Gudang menyiapkan → admin menugaskan driver → barang diantar
        │          (status bisa dipantau: ditugaskan → di jalan → terkirim/gagal)
5. BAYAR           Warung membayar (tunai/transfer sesuai kesepakatan) → pembayaran dicatat
        │
6. LUNAS           Pesanan selesai. Riwayat tersimpan & dipakai untuk rekomendasi berikutnya
```

**Aturan penting yang otomatis dijaga sistem:**

- Pesanan yang melebihi **limit kredit** warung akan **ditolak otomatis** saat dibuat — jadi tidak ada tagihan yang kebablasan.
- Setiap perubahan status **tercatat** (siapa, kapan) — transparan dan bisa diaudit.
- Pesan WhatsApp yang ganda/terkirim dua kali **tidak membuat order dobel** — sistem mengenalinya sebagai satu pesanan.

---

## 4. Panduan per peran

### 4.1 Pemilik / Admin

**Halaman yang dipakai:** Dashboard, Admin Orders, Outlets, Payments, Analytics.

1. **Pantau bisnis** di *Dashboard*: total outlet terdaftar, outlet aktif, order hari ini, omzet bulanan, produk terlaris, performa area.
2. **Setujui pesanan** di *Admin Orders*: periksa pesanan baru → klik setujui. Pesanan yang disetujui otomatis bisa diteruskan ke pengiriman dan memicu notifikasi WhatsApp ke warung.
3. **Kelola outlet** di *Outlets*: daftar, data pemilik, alamat, kategori, riwayat belanja tiap warung.
4. **Atur limit kredit** tiap outlet di *Payments*: tentukan plafon (mis. Rp 5 juta). Sistem menolak order baru yang melebihi sisa plafon.
5. **Lihat analitik** di *Analytics*: rekomendasi produk, prediksi order per outlet, dan pengelompokan outlet (bernilai tinggi / potensi menengah / rendah) untuk strategi penjualan.

### 4.2 Supplier / Brand

**Halaman yang dipakai:** Marketplace, Products.

1. Produk Anda tampil di katalog bersama info foto, harga, promo, dan stok.
2. Hanya produk yang **aktif** dan dari supplier yang **aktif** yang bisa dipesan warung — jadi pastikan status dan stok selalu diperbarui.
3. Pantau margin (harga beli vs harga jual) agar kerja sama tetap sehat.

### 4.3 Pemilik Warung / Outlet

**Halaman yang dipakai:** Marketplace/Products, Orders, Payments.

1. **Daftar** bila belum punya akun (siapkan nama toko, nama pemilik, nomor HP, alamat).
2. **Belanja**: buka katalog → pilih barang → tentukan jumlah → kirim pesanan. Bisa juga pesan via WhatsApp (lihat §5).
3. **Lacak** pesanan di *Orders*: Baru → Disetujui → Disiapkan → Diantar → Lunas.
4. **Bayar** tepat waktu dan pantau sisa limit kredit di *Payments* agar pesanan berikutnya tidak tertolak.

### 4.4 Tim Sales

**Halaman yang dipakai:** Sales.

1. Lihat jadwal kunjungan hari ini (target mis. 30 outlet/hari).
2. Catat hasil tiap kunjungan: sudah dikunjungi, ada order atau tidak.
3. Gunakan data riwayat belanja outlet untuk menawarkan produk yang tepat (mis. warung yang sering beli kopi → tawarkan snack pendamping).

### 4.5 Driver

**Halaman yang dipakai:** Delivery.

1. Lihat tugas antar yang diberikan admin.
2. Ubah status: *Ditugaskan → Dalam perjalanan → Terkirim* (atau *Gagal* bila ada kendala + keterangan).
3. Hanya driver yang ditugaskan yang bisa mengubah status tugasnya — menjaga ketertiban.

---

## 5. Memesan lewat WhatsApp

Karena banyak pemilik warung nyaman dengan WA, DDP mendukung pesan via chat:

1. **Kirim pesan** ke nomor WhatsApp resmi platform, mis. *"Mau order snack"*.
2. **Bot menjawab** dengan katalog terbaru.
3. **Balas dengan format item** yang diminta (bot memandu; bila format salah atau nomor tidak dikenal, bot menolak dengan pesan penjelasan — bukan error misterius).
4. **Satu pesanan tercatat** — walau pesan terkirim dua kali karena sinyal, order tidak dobel.
5. Anda menerima **notifikasi otomatis** saat pesanan dikonfirmasi dan saat katalog/promo baru dibagikan.

Tips: gunakan selalu nomor HP yang sama dengan yang terdaftar di akun outlet Anda, agar pesanan tercatat ke toko yang benar.

---

## 6. Status pesanan — cara membaca

| Status | Arti | Apa yang terjadi selanjutnya |
|---|---|---|
| **Baru** | Pesanan masuk, menunggu admin | Admin memeriksa & menyetujui |
| **Disetujui** | Admin setuju | Gudang menyiapkan + driver ditugaskan |
| **Disiapkan** (processing) | Barang dikemas | Driver menjemput & mengantar |
| **Terkirim** (delivered) | Barang sampai di warung | Menunggu pembayaran |
| **Lunas** (paid) | Sudah dibayar penuh | Selesai ✓ |
| **Batal** (cancelled) | Pesanan dibatalkan | Tidak diproses lebih lanjut |

Status pengiriman terpisah namun terkait: *Ditugaskan → Dalam perjalanan → Terkirim / Gagal*.

---

## 7. Pembayaran & limit kredit

Konsepnya seperti "plafon utang" yang sehat:

- Setiap warung punya **limit kredit**, mis. Rp 5.000.000.
- Setiap pesanan menambah **tagihan berjalan (outstanding)**.
- **Sisa limit = limit − outstanding.** Pesanan baru yang melebihi sisa limit **otomatis ditolak** — Anda akan diminta melunasi dulu sebagian tagihan.
- Setiap pembayaran **dicatat + ada bukti (receipt)**, dan riwayatnya bisa dilihat kapan saja.
- Ada pengingat jatuh tempo agar tidak terlambat.

Contoh:

> Warung A: limit Rp 5 juta, tagihan berjalan Rp 2,5 juta → sisa Rp 2,5 juta.
> Order baru Rp 3 juta → **ditolak**. Bayar dulu Rp 1 juta → sisa Rp 3,5 juta → order **diterima**.

---

## 8. Fitur pintar (AI & Analitik)

Tanpa perlu paham teknis, begini cara membacanya:

- **Rekomendasi produk** — *"Warung ini sering beli kopi → tawarkan snack pendamping."* Daftar saran terbatas, berurutan dari yang paling relevan, berdasarkan belanja nyata (bukan tebakan).
- **Prediksi order** — *"Warung A kemungkinan order lagi dalam 5 hari, perkiraan Rp 800.000."* Gunakan untuk menyetok dan menagih tepat waktu.
- **Segmentasi outlet** — warung otomatis dikelompokkan: *bernilai tinggi, potensi menengah, potensi rendah*. Fokuskan kunjungan sales ke yang paling berdampak.
- Jika data belanja masih sedikit, sistem menampilkan **saran aman** (mis. produk populer umum) dan menjelaskan alasannya — tidak mengarang.

---

## 9. Contoh sehari-hari

**Cerita 1 — Bu Sari (warung kelontong):**
Pagi hari stok mie habis. Bu Sari buka katalog di HP, pesan 2 dus, status *Baru*. Siang admin menyetujui → sore driver mengantar → Bu Sari bayar → status *Lunas*. Minggu depan sistem mengingatkan bahwa biasanya Bu Sari order lagi tiap 7 hari.

**Cerita 2 — Pak Andi (sales):**
Target 30 kunjungan. Dari daftar, ia lihat 5 warung "bernilai tinggi" yang belum order 10 hari → ia prioritaskan. Di tiap warung ia catat hasil kunjungan. Atasannya memantau progres dari dashboard.

**Cerita 3 — Ibu Owner (pemilik distribusi):**
Pagi buka dashboard: 1.250 warung terdaftar, 980 aktif, 350 order hari ini, omzet bulan ini Rp 2,4 M. Produk kopi naik daun di area timur → ia tambah stok dan kirim promo WA ke warung area tersebut.

---

## 10. Pertanyaan umum (FAQ)

**Apakah harus install aplikasi?**
Tidak wajib. Cukup buka website di browser HP/komputer, atau pesan via WhatsApp.

**Saya lupa kata sandi / tidak bisa login, bagaimana?**
Hubungi admin. Pastikan email benar dan akun masih aktif. Jangan bagikan kata sandi ke siapa pun.

**Mengapa pesanan saya ditolak?**
Kemungkinan besar (1) melebihi sisa limit kredit — lunasi sebagian tagihan dulu; (2) produk tidak tersedia / supplier non-aktif; (3) data order tidak lengkap. Periksa halaman Payments dan coba lagi.

**Apakah pesan WA ganda membuat order dobel?**
Tidak. Sistem mendeteksi pesan duplikat dan hanya membuat satu order.

**Bisakah saya pesan untuk warung lain?**
Tidak. Setiap akun hanya untuk tokonya sendiri (kecuali admin). Ini menjaga keamanan data.

**Data saya aman?**
Ya — setiap peran hanya melihat datanya sendiri; warung tidak bisa melihat data warung lain. Semua perubahan penting tercatat.

**Apakah butuh internet cepat?**
Cukup internet biasa. Katalog dan order ringan dan bisa dibuka di HP standar.

---

## 11. Glosarium — istilah penting

| Istilah | Arti sederhana |
|---|---|
| **Outlet / Warung** | Toko/warung mitra yang belanja lewat platform |
| **Supplier / Principal** | Pemasok / pemilik merek barang |
| **SKU** | Kode unik tiap produk (mis. ukuran/varian berbeda = SKU berbeda) |
| **Order / Pesanan** | Permintaan pembelian dari warung |
| **Outstanding** | Total tagihan yang belum dibayar |
| **Limit kredit / Plafon** | Batas maksimal tagihan berjalan sebuah warung |
| **Marketplace** | Halaman belanja yang menampilkan produk dari banyak supplier |
| **Dashboard** | Halaman ringkasan angka-angka bisnis |
| **Sales visit** | Kunjungan sales ke warung |
| **Delivery** | Proses pengantaran barang oleh driver |
| **Receipt / Bukti bayar** | Tanda terima resmi setiap pembayaran |
| **Forecast / Prediksi** | Perkiraan kapan & berapa warung akan order lagi |
| **Segmentasi** | Pengelompokan warung (tinggi/menengah/rendah) berdasar perilaku belanja |
| **Margin** | Selisih harga jual − harga beli (keuntungan kotor) |
| **FMCG** | Barang konsumsi cepat (makanan, minuman, kebutuhan harian) |

---

## 12. Bantuan & keamanan akun

- **Akun:** satu akun untuk satu pengguna/toko. Jangan berbagi kata sandi. Segera lapor admin bila HP hilang atau ada login mencurigakan.
- **Nomor HP:** gunakan nomor yang sama di akun dan WhatsApp agar pesanan tercatat benar.
- **Bukti bayar:** simpan receipt setiap pembayaran; cocokkan dengan riwayat di halaman Payments.
- **Lapor masalah:** catat apa yang terjadi (halaman apa, jam berapa, pesan error apa) lalu hubungi admin — semakin lengkap info, semakin cepat diperbaiki.
