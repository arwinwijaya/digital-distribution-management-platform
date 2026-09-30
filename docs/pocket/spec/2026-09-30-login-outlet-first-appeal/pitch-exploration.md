# Pitch Exploration: login-outlet-first-appeal
Date: 2026-09-30 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement

Login saat ini terasa seperti portal internal generik. Bagi outlet, belum ada alasan emosional maupun manfaat bisnis yang terlihat untuk segera masuk dan melakukan reorder.

## Root Tension

Kita perlu membuat login cukup persuasif untuk mendorong reorder, tetapi tidak boleh membuat klaim stok/promo personal sebelum sistem mengenali outlet.

## Key Constraints

- Satu login dipakai semua role: outlet, admin, sales, driver, dan finance.
- Outlet diarahkan ke `/orders` setelah login; role lain memiliki destination masing-masing.
- Konteks utama outlet adalah HP di toko yang ramai, sehingga hierarchy, ukuran kontrol, dan kecepatan akses penting.
- Data reorder/promo tidak tersedia sebelum autentikasi; promo saat ini berada di endpoint admin dan replenishment berada di area authenticated.
- Perubahan harus mempertahankan auth flow, redirect, dan dukungan expected role pada komponen login.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Login perlu diposisikan sebagai transisi menuju ordering, bukan hanya authentication.
- Reorder cue harus konkret tetapi tidak mengklaim data personal yang belum tersedia.
- Mobile readability dan satu CTA utama harus menjadi prioritas.

### First Principles Thinking — creative
Key insights:
- Outlet tidak mencari software; mereka mencari stok yang bergerak, replenishment yang andal, dan langkah sederhana.
- Janji utama yang paling relevan adalah membantu produk laris tidak kosong.
- Visual harus terasa seperti partner dagang, bukan admin dashboard.

### Six Thinking Hats — structured
Key insights:
- Fakta: halaman memakai PageHeader generik dan LoginForm sederhana; role redirect sudah berjalan.
- Emosi: outlet perlu merasa dibantu dan stoknya aman.
- Risiko: klaim data personal atau promo sebelum login dapat merusak trust.
- Proses: mulai dari UI statis responsif, lalu validasi kemungkinan data dinamis setelah login.

### Reverse Brainstorming — creative
Key insights:
- Login gagal jika terasa seperti portal admin, memakai kontrol kecil, atau menyembunyikan reorder value.
- Dashboard metrik palsu dan promo berlebihan harus dihindari.
- Form tetap harus lebih dominan daripada konten pendukung.

### Role Playing — collaborative
Key insights:
- Pemilik outlet ingin pesan ulang barang laris dengan cepat.
- Staff outlet membutuhkan tombol dan instruksi yang jelas agar tidak takut salah.
- Admin membutuhkan login universal dan redirect role yang tetap aman.
- Role non-outlet tidak boleh dibuat merasa salah tempat.

---

## Advisor Synthesis

Kelima metode menunjukkan pola yang konsisten: login perlu outlet-first, mobile-first, cepat, dan berorientasi pada manfaat bisnis. Konten reorder harus disajikan sebagai janji kemampuan platform atau ilustrasi yang jujur, bukan data personal/promo palsu sebelum autentikasi. Kandidat terbaik adalah hero outlet yang ringkas dengan satu cerita reorder, benefit yang mudah dipindai, dan form universal yang tetap dominan; dashboard metrik palsu serta promo besar disisihkan karena berisiko menurunkan trust.

---

## Spike Results

**Unknown resolved:** Apakah data reorder atau promo yang personalized sudah tersedia untuk ditampilkan sebelum login?

**Finding:** Belum. Endpoint login hanya mengembalikan token dan role. Promo yang terdeteksi berada pada endpoint admin yang membutuhkan autentikasi/RBAC, sedangkan replenishment juga berada di area authenticated.

**Implication:** Versi pertama menggunakan content statis/ilustratif dengan framing jujur seperti “Setelah masuk Anda bisa…”. Data dinamis dapat menjadi enhancement berikutnya setelah autentikasi.

---

## Approach Directions

### Direction A: Split-screen hero + form
Hero outlet-focused di satu sisi dan form besar di sisi lain, lalu stack responsif di mobile.
+ Ruang storytelling dan visual lebih kuat.
− Berisiko terasa terlalu marketing bila tidak dijaga tetap ringkas.

### Direction B: Single-column motivation
Mempertahankan satu kolom centered, tetapi menambahkan motivasi reorder serta tiga benefit ringkas di atas form.
+ Perubahan paling kecil dan hierarchy existing tetap aman.
− Ruang untuk membangun nuansa outlet lebih terbatas.

### Direction C: Progressive enhancement — hero outlet-first, form universal
Menggabungkan hero responsif Direction A dengan form universal Direction B. Hero memakai satu cerita reorder, tiga benefit, dan copy yang jujur; form tetap menjadi CTA utama untuk semua role.
+ Mendorong reorder tanpa mengganggu role lain, cocok untuk mobile, dan dapat di-upgrade menjadi data dinamis setelah login.
− Membutuhkan sedikit lebih banyak pekerjaan responsive UI daripada single-column.

---

## Open Questions for pocket-grinding

- [ ] Apakah copy dan visual hero perlu berubah berdasarkan role yang sudah diketahui dari query/entry point, atau tetap universal sebelum login?
- [ ] Benefit reorder mana yang paling valid untuk dijanjikan berdasarkan operasional aktual: stok cepat, harga distributor, pengiriman, atau promo?
- [ ] Apakah testimonial ilustratif boleh digunakan, atau brand hanya menginginkan claim berbasis fitur yang dapat diverifikasi?
- [ ] Metrik keberhasilan apa yang tersedia untuk validasi: login completion, redirect ke orders, atau reorder completion setelah login?

---

## Recommended Direction

Direction C — progressive enhancement dengan hero outlet-first yang emosional namun jujur dan form universal yang tetap dominan; ini paling sesuai dengan konteks mobile, kebutuhan reorder, dan batasan data pre-login.

---

## Handoff Context (for pocket-grinding)

When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context).
- Use Direction C as the working hypothesis for Phase 5 Design Proposals.
- Treat Open Questions above as Phase 3 Discovery targets.
- Do NOT treat Approach Directions as final architecture — validate through GWT first.
