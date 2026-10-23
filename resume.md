# Resume — Pocket Grinding: Outlet & Sales Ordering UX

**Tanggal:** 2026-10-23
**Status:** Draft GWT tersimpan; belum siap untuk desain atau handoff karena masih ada klarifikasi blocking dari tinjauan edge-case independen.

## Tujuan dan cakupan
Mengeksplorasi peluang UI/UX untuk dua subalur pemesanan yang terpisah, tanpa mengimplementasikan perubahan produk:

1. **Outlet — `OrderForm`:** submit online, antrean offline, hasil sinkronisasi, persistensi lokal, dan keamanan lintas akun.
2. **Sales — `/sales/orders`:** pemilihan outlet dalam wilayah, submit langsung, validasi, hasil submit tak pasti, dan riwayat pesanan sales sebagai cara verifikasi.

## Temuan kerja yang sudah tercatat
- Stack Docker development lokal sebelumnya diverifikasi sehat: API health mengembalikan HTTP 200, database dan Redis sehat, serta web merespons HTTP 200. Tidak menampilkan nilai rahasia.
- Pitch eksplorasi UI/UX tersimpan di `docs/pocket/spec/2026-10-23-ui-ux-improvement-opportunities/pitch-exploration.md`.
- GWT/example mapping untuk alur outlet dan sales tersimpan di `docs/pocket/spec/2026-10-23-ui-ux-improvement-opportunities/phase-4-example-mapping.md`.
- Tes yang diperiksa membuktikan beberapa perilaku saat ini, termasuk sukses online dan antrean/sinkronisasi outlet. Tes belum membuktikan seluruh kebutuhan UX dalam draft.

## Keputusan pengguna yang tercermin dalam draft
- Pesanan outlet yang tertunda tidak boleh dikirim dengan akun berbeda dari akun asal; tahan sampai akun asal autentikasi kembali.
- Item antrean lama yang pemilik asalnya tak dapat dipastikan tidak boleh dikirim dengan akun aktif; hanya dapat dihapus setelah tindakan sadar dan konfirmasi pengguna.
- Jika hasil submit sales tidak pasti, jangan mengirim ulang otomatis atau menyatakan pasti gagal. Pengguna harus dapat memeriksa riwayat pesanan miliknya. Jika pesanan tidak terlihat, ketidakhadirannya bukan bukti submit gagal atau retry aman.

## Blocker dari review edge-case independen
Jangan menetapkan desain atau menyatakan spesifikasi siap sebelum perilaku berikut diperjelas dan GWT direview kembali:
1. **Pergantian akun saat flush:** O9 mencegah pengiriman item berikutnya setelah pergantian, tetapi belum menjamin akun A tidak memakai kredensial akun B bila perubahan terjadi di antara pemeriksaan identitas dan pemilihan kredensial.
2. **Penghapusan item tanpa pemilik:** Belum ditetapkan apakah akun aktif yang tidak terkait boleh menghapus item lama yang pemiliknya tak dapat diverifikasi; ini menyisakan ambiguitas otorisasi dan risiko kehilangan data.
3. **Hasil sinkronisasi outlet tak pasti:** Kegagalan transport dapat terjadi setelah server membuat pesanan. Retensi lalu pengiriman ulang tanpa aturan dapat membuat pesanan duplikat; perlakuan terhadap kasus ini perlu diputuskan.
4. **Riwayat sales terlambat/tidak lengkap/tidak tersedia:** GWT menyatakan bahwa pesanan yang tak terlihat bukan bukti gagal. Perlu diperjelas komunikasi/pemulihan agar pengguna memahami bahwa hasil tetap tak pasti dan tidak menganggap retry aman.

## Langkah berikutnya
- Minta keputusan/clarification hanya untuk blocker yang memerlukan pilihan produk.
- Revisi stories, rules, examples, dan GWT terkait; kirim perubahan ke tinjauan edge-case independen.
- Setelah blocker tertangani dan tinjauan clear, baru validasi arsitektur serta susun proposal desain dan minta persetujuan. Belum ada implementasi produk yang diotorisasi.
