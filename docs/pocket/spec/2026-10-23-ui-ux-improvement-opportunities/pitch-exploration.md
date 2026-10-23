# Pitch Exploration: Kejelasan alur kerja lintas peran
Date: 2026-10-23 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Platform distribusi ini mencakup banyak alur dan peran. Peluang eksplorasinya adalah menentukan apakah pengguna dapat segera menemukan tugas berikutnya serta memahami status pesanan, invoice, pembayaran, dan pengiriman tanpa berpindah halaman secara berlebihan; hambatan nyata belum divalidasi lewat pengamatan pengguna.

## Root Tension
Menjaga akses ke fungsi yang beragam sambil menonjolkan pekerjaan paling relevan bagi masing-masing peran, tanpa mengganggu alur yang sudah berjalan.

## Key Constraints
- `apps/web` memakai Next.js 16, React 18, Tailwind CSS 3, dan Zustand (`apps/web/package.json`).
- `Sidebar.tsx` mendefinisikan banyak tujuan navigasi, tetapi hanya menampilkan item yang diizinkan oleh peta RBAC dari `/auth/me`; jumlah yang terlihat berbeda menurut peran/konfigurasi. `AppShell.tsx` memakai sidebar desktop dan drawer pada mobile.
- `dashboard/page.tsx` sudah memuat data berbeda untuk finance, admin, dan outlet; dashboard outlet juga menangani kegagalan sebagian data. Jangan menduplikasi kemampuan ini sebelum meninjau pengalaman aktualnya.
- `PwaBootstrap.tsx` mendaftarkan service worker hanya bila `NEXT_PUBLIC_PWA_ENABLED` aktif; ada pengujian terkait offline pada OrderForm. Cakupan pengalaman offline dan status sinkronisasi masih perlu diperiksa.
- Terdapat komponen UI bersama (`apps/web/src/components/ui`) dan pengujian frontend; arah perubahan sebaiknya kompatibel dengan alur yang sudah diuji.
- Pitch sebelumnya membahas kejelasan menu Produk (`docs/pocket/spec/2026-09-26-barang-menu-clarity/`) dan matriks RBAC (`docs/pocket/spec/2026-09-18-rbac-menu-matrix/`). Hindari mengulang masalah yang sudah ditangani tanpa audit keadaan sekarang.

---

## Brainstorming Methods Used

### Question Storming — deep
- Tugas mana yang paling sering dan paling mendesak bagi tiap peran, dan bagaimana pengguna menemukannya sekarang?
- Status mana yang membutuhkan keputusan berikutnya, bukan sekadar angka pada dasbor?
- Di perangkat dan kondisi jaringan apa tiap alur digunakan?
- Apakah istilah navigasi dan status dipahami konsisten oleh pengguna yang berbeda?

### First Principles Thinking — creative
- Ukuran keberhasilan UX ialah penyelesaian tugas yang benar dan cepat, bukan jumlah halaman atau grafik.
- Ringkasan, status, dan tindakan harus membantu keputusan pada titik penggunaan.
- Penyaringan akses berbasis peran tidak otomatis sama dengan prioritas tugas berbasis peran.

### Six Thinking Hats — structured
- **Fakta:** RBAC, dashboard menurut jenis data/peran, UI bersama, serta drawer mobile sudah tersedia.
- **Emosi (hipotesis):** ketidakjelasan status dapat membuat staf tidak yakin apakah pekerjaan sudah diproses.
- **Manfaat potensial:** penanda tindakan berikutnya dapat mengurangi pencarian, jika pengujian pengguna mengonfirmasi masalahnya.
- **Risiko:** perubahan navigasi serentak dapat mengganggu kebiasaan dan menemukan fungsi sekunder.
- **Ide:** evaluasi kejelasan status dan tindakan kontekstual pada satu alur prioritas sebelum merancang ulang beranda.
- **Proses:** observasi tugas per peran, ukur titik tersendat, uji perubahan kecil, baru perluas.

### Reverse Brainstorming — creative
- Jika ingin membuat alur membingungkan, tampilkan status identik bagi kondisi bisnis yang berbeda.
- Jika ingin membuat pengguna tersesat, pakai label menu yang tidak sesuai bahasa pekerjaan sehari-hari.
- Jika ingin membuat staf lapangan ragu, jangan jelaskan apa yang tersimpan, gagal, atau menunggu sinkronisasi.

### Role Playing — collaborative
- **Outlet/sales:** apakah mencari produk, membuat atau mengulang pesanan dapat dilakukan dengan yakin di mobile?
- **Admin:** apakah item yang perlu persetujuan/penanganan mudah dibedakan dari aktivitas rutin?
- **Finance:** apakah invoice dan pembayaran punya status, konteks, serta langkah selanjutnya yang jelas?
- **Operasional/driver:** apakah pengecualian pengiriman mudah ditemukan dan ditindaklanjuti?

---

## Advisor Synthesis
Kurasi independen menyarankan peningkatan kejelasan alur kerja secara bertahap, bukan perombakan visual menyeluruh. Fokus evaluasi adalah menemukan tugas dan memahami status lintas modul; masalah pengguna belum terkonfirmasi melalui studi atau uji kegunaan. Arah beranda berbasis peran tetap layak dibandingkan, tetapi dashboard yang telah membedakan beberapa peran harus diaudit terlebih dahulu. Jangan menganggap karakter ikon yang tampak janggal dalam keluaran pembacaan sumber sebagai kerusakan UI tanpa memeriksa rendering.
**Curation source:** advisor tool

---

## Approach Directions

### Direction A: Audit dan perbaikan alur yang ada
Pilih satu alur berfrekuensi atau berisiko tinggi berdasarkan masukan pengguna, lalu tinjau istilah navigasi, status, umpan balik, dan tindakan berikutnya pada layar yang sudah tersedia.
+ Bertahap, mudah diuji, serta dapat memanfaatkan komponen dan dashboard yang ada.
− Dampaknya mungkin terbatas bila akar masalah ternyata perpindahan antar-modul.

### Direction B: Penekanan tugas pada beranda per peran
Kembangkan pengalaman beranda yang sudah ada agar tiap peran melihat pekerjaan yang perlu perhatian dan jalur menuju tindakan terkait.
+ Dapat mengurangi pencarian lintas modul jika kebutuhan tiap peran telah terbukti.
− Berisiko menduplikasi dashboard yang ada atau menambah kompleksitas sebelum prioritas tugas terukur.

---

## Open Questions for pocket-grinding
- [ ] Peran, perangkat, dan tugas apa yang paling sering digunakan dalam praktik? Mana yang paling sering tersendat?
- [ ] Saat diminta menyelesaikan tugas prioritas, apakah pengguna gagal menemukan halaman, salah membaca status, atau tersendat di formulir?
- [ ] Apa yang sudah tampil di dashboard masing-masing peran, dan apa yang masih tidak dapat ditindaklanjuti?
- [ ] Apakah pengalaman offline atau sinkronisasi memang digunakan dan menimbulkan kebingungan pada perangkat lapangan?
- [ ] Apakah ada kebutuhan UX yang belum tercakup oleh rencana menu Produk dan RBAC yang sudah ada?

---

## Recommended Direction
Direction A — verifikasi satu alur nyata lalu perbaiki kejelasan di titik masalahnya; pertimbangkan Direction B jika audit membuktikan kebutuhan lintas modul yang tidak dapat ditangani secara lokal.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context).
- Use Direction A as a working hypothesis, not a final solution.
- Treat the open questions as discovery targets; observe users or review real workflows before claiming UX defects.
- Validate any design proposal through GWT before implementation.
