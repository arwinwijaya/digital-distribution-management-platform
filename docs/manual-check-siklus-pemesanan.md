# Dokumentasi Cek Manual — 1 Siklus Pemesanan Outlet sampai Pembayaran

Dokumen ini dipakai untuk melakukan cek manual end-to-end 1 siklus pemesanan menggunakan akun dummy/seed yang sudah tersedia di aplikasi.

Siklus yang dicek:

```text
Outlet login → pilih produk → buat pesanan → admin approve → assign delivery → driver antar barang → barang sampai → pembayaran → verifikasi lunas
```

---

## 1. Tujuan Pengujian

Memastikan 1 pesanan outlet bisa berjalan lengkap dari awal sampai akhir:

1. Outlet dapat login.
2. Outlet dapat melihat produk/katalog.
3. Outlet dapat membuat pesanan.
4. Pesanan masuk dengan status `New`.
5. Admin dapat approve pesanan.
6. Setelah approve, pesanan menjadi `Confirmed` dan invoice dibuat.
7. Admin/sales dapat membuat delivery assignment.
8. Driver dapat mengubah status delivery menjadi `in_progress`.
9. Driver dapat menyelesaikan delivery menjadi `delivered`.
10. Pesanan berubah menjadi `Delivered`.
11. Outlet/finance dapat mencatat pembayaran.
12. Payment tercatat `completed`.
13. Pesanan menjadi `Paid` dan invoice menjadi `paid`.

---

## 2. Prasyarat

Pastikan kondisi berikut sudah terpenuhi sebelum cek manual:

- Backend API berjalan.
- Frontend web berjalan bila pengujian dilakukan lewat UI.
- Database sudah migrate.
- Seeder sudah dijalankan.
- Data produk tersedia dan minimal 1 produk aktif memiliki stok cukup.
- Akun seed memakai password default:

```text
password123
```

Contoh setup umum:

```bash
# dari root project, jika memakai Docker
docker compose up -d

# jika perlu menjalankan migrate + seed di container API
docker compose exec api php artisan migrate --seed
```

URL default dari README project:

| Komponen | URL |
|---|---|
| Web | `http://localhost:3000` |
| API | `http://localhost:8000` |
| Health API | `http://localhost:8000/api/health` |

> Catatan: Jika environment lokal memakai port berbeda, sesuaikan URL di dokumen ini.

---

## 3. Akun Dummy yang Digunakan

Gunakan akun berikut untuk 1 siklus utama:

| Tahap | Role | Nama | Email | Password |
|---|---|---|---|---|
| Buat pesanan | outlet | Siti Nurhaliza / Toko Siti Jaya | `siti.nurhaliza@ddp.test` | `password123` |
| Approve order & assign delivery | admin | Ratna Sari | `ratna.sari@ddp.test` | `password123` |
| Antar barang | driver | Joko Widodo | `joko.widodo@ddp.test` | `password123` |
| Catat/cek pembayaran | finance | Dewi Lestari | `dewi.lestari@ddp.test` | `password123` |

Akun alternatif yang juga tersedia:

| Role | Email | Password |
|---|---|---|
| outlet | `budi.santoso@ddp.test` | `password123` |
| admin | `dimas.pratama@ddp.test` | `password123` |
| driver | `andi.saputra@ddp.test` | `password123` |
| driver | `rudi.hermawan@ddp.test` | `password123` |
| finance | `tono.sugiarto@ddp.test` | `password123` |

---

## 4. Data Dummy yang Perlu Dicatat Saat Test

Beberapa ID berbeda antar database/environment. Isi tabel ini saat menjalankan test.

| Nama Data | Nilai |
|---|---|
| `PRODUCT_ID_1` |  |
| `PRODUCT_NAME_1` |  |
| `PRODUCT_ID_2` |  |
| `PRODUCT_NAME_2` |  |
| `DRIVER_USER_ID` untuk Joko Widodo |  |
| `ORDER_DB_ID` / numeric id |  |
| `ORDER_REF` / `ORD-...` |  |
| `TOTAL_AMOUNT` |  |
| `INVOICE_ID` / invoice number |  |
| `DELIVERY_ID` |  |
| `PAYMENT_ID` |  |
| `RECEIPT_REFERENCE` |  |

---

## 5. Ringkasan Status yang Diharapkan

### 5.1 Status Order

```text
New → Confirmed → Delivered → Paid
```

Jika pembayaran sebagian:

```text
Delivered → Partially Paid → Paid
```

### 5.2 Status Delivery

```text
assigned → in_progress → delivered
```

### 5.3 Status Invoice

```text
unpaid → paid
```

Jika pembayaran sebagian:

```text
unpaid → partially_paid → paid
```

---

## 6. Checklist Manual via UI

Gunakan bagian ini jika seluruh fitur tersedia di frontend.

### 6.1 Login sebagai Outlet

| Item | Instruksi |
|---|---|
| Akun | `siti.nurhaliza@ddp.test` |
| Password | `password123` |
| Ekspektasi | Login sukses dan user masuk sebagai outlet/Toko Siti Jaya |

Checklist:

- [ ] Buka Web.
- [ ] Login memakai akun outlet.
- [ ] Pastikan nama user/outlet sesuai.
- [ ] Buka halaman produk/katalog/marketplace.
- [ ] Pilih minimal 1 produk aktif dengan stok cukup.
- [ ] Catat `PRODUCT_ID`, nama produk, harga, dan stok jika terlihat.

### 6.2 Outlet Membuat Pesanan

Contoh data pesanan:

| Produk | Qty |
|---|---:|
| `PRODUCT_ID_1` | 2 |
| `PRODUCT_ID_2` | 1 |

Checklist:

- [ ] Tambahkan produk ke keranjang/order.
- [ ] Submit pesanan.
- [ ] Pastikan pesanan berhasil dibuat.
- [ ] Catat `ORDER_DB_ID` atau numeric id.
- [ ] Catat `ORDER_REF` dengan format seperti `ORD-YYYYMMDD-XXXXX`.
- [ ] Catat `TOTAL_AMOUNT`.
- [ ] Pastikan status awal pesanan adalah `New`.

Ekspektasi hasil:

| Field | Ekspektasi |
|---|---|
| Status response/UI | sukses |
| Order status | `New` |
| Order items | sesuai produk dan qty yang dipilih |
| Total amount | sesuai subtotal produk dikurangi promo jika ada |

### 6.3 Login sebagai Admin

| Item | Instruksi |
|---|---|
| Akun | `ratna.sari@ddp.test` |
| Password | `password123` |
| Ekspektasi | Login sukses sebagai admin |

Checklist:

- [ ] Logout dari outlet.
- [ ] Login sebagai admin.
- [ ] Buka daftar order/admin order.
- [ ] Cari pesanan berdasarkan `ORDER_REF` atau nama outlet `Toko Siti Jaya`.
- [ ] Buka detail pesanan.
- [ ] Pastikan status masih `New`.

### 6.4 Admin Approve Pesanan

Checklist:

- [ ] Klik approve/konfirmasi pesanan.
- [ ] Pastikan proses sukses.
- [ ] Refresh detail order.
- [ ] Pastikan status berubah menjadi `Confirmed`.
- [ ] Pastikan invoice dibuat.
- [ ] Catat invoice id/number jika terlihat.

Ekspektasi hasil:

| Field | Ekspektasi |
|---|---|
| Order status | `Confirmed` |
| Invoice status | `unpaid` |
| Invoice total amount | sama dengan `TOTAL_AMOUNT` |
| Invoice paid amount | `0.00` |
| Invoice balance amount | sama dengan `TOTAL_AMOUNT` |

### 6.5 Admin/Sales Assign Delivery

Gunakan driver utama:

```text
Joko Widodo / joko.widodo@ddp.test
```

Checklist:

- [ ] Buka halaman delivery/pengiriman.
- [ ] Buat assignment delivery untuk order yang sudah `Confirmed`.
- [ ] Pilih driver Joko Widodo.
- [ ] Isi notes contoh: `Pengiriman manual test 1 siklus pemesanan`.
- [ ] Submit assignment.
- [ ] Catat `DELIVERY_ID`.
- [ ] Pastikan delivery status `assigned`.

Ekspektasi hasil:

| Field | Ekspektasi |
|---|---|
| Delivery status | `assigned` |
| Assigned driver | Joko Widodo |
| Order status | tetap `Confirmed` sampai delivery selesai |

### 6.6 Login sebagai Driver

| Item | Instruksi |
|---|---|
| Akun | `joko.widodo@ddp.test` |
| Password | `password123` |
| Ekspektasi | Login sukses sebagai driver |

Checklist:

- [ ] Logout dari admin.
- [ ] Login sebagai driver.
- [ ] Buka daftar delivery driver.
- [ ] Cari `DELIVERY_ID` atau order untuk Toko Siti Jaya.
- [ ] Pastikan delivery terlihat untuk driver tersebut.

### 6.7 Driver Mulai Pengiriman

Checklist:

- [ ] Ubah status delivery menjadi `in_progress` / mulai pengiriman.
- [ ] Pastikan update sukses.
- [ ] Refresh detail delivery.
- [ ] Pastikan status delivery `in_progress`.

Ekspektasi hasil:

| Field | Ekspektasi |
|---|---|
| Delivery status | `in_progress` |
| Order status | masih `Confirmed` |

### 6.8 Driver Menyelesaikan Pengiriman

Data dummy bukti pengiriman:

| Field | Contoh |
|---|---|
| Recipient name | `Siti Nurhaliza` |
| Proof URL | `https://example.com/proof/manual-check-order-001.jpg` |
| Notes | `Barang diterima lengkap oleh outlet` |

Checklist:

- [ ] Ubah status delivery menjadi `delivered`.
- [ ] Isi recipient name.
- [ ] Isi proof of delivery URL / upload bukti jika UI mendukung.
- [ ] Submit.
- [ ] Pastikan delivery status menjadi `delivered`.
- [ ] Pastikan order status berubah menjadi `Delivered`.

Ekspektasi hasil:

| Field | Ekspektasi |
|---|---|
| Delivery status | `delivered` |
| Order status | `Delivered` |
| Recipient name | `Siti Nurhaliza` |
| Proof of delivery | tersimpan/terlihat |

### 6.9 Pembayaran oleh Outlet atau Finance

Pembayaran bisa dicatat oleh outlet sendiri atau finance. Untuk cek manual paling lengkap, gunakan finance:

| Item | Instruksi |
|---|---|
| Akun | `dewi.lestari@ddp.test` |
| Password | `password123` |

Checklist:

- [ ] Logout dari driver.
- [ ] Login sebagai finance.
- [ ] Buka halaman pembayaran/invoice/order delivered.
- [ ] Cari order `ORDER_REF`.
- [ ] Pastikan order sudah `Delivered`.
- [ ] Catat outstanding/balance.
- [ ] Buat pembayaran sebesar `TOTAL_AMOUNT`.
- [ ] Pilih metode pembayaran, contoh `cash` atau `bank_transfer`.
- [ ] Submit pembayaran.
- [ ] Catat `PAYMENT_ID` dan `RECEIPT_REFERENCE`.

Ekspektasi hasil:

| Field | Ekspektasi |
|---|---|
| Payment status | `completed` |
| Payment amount | sama dengan `TOTAL_AMOUNT` |
| Order status | `Paid` |
| Order paid amount | sama dengan `TOTAL_AMOUNT` |
| Outstanding balance | `0.00` |
| Invoice status | `paid` |
| Invoice balance amount | `0.00` |

---

## 7. Checklist Manual via API

Gunakan bagian ini jika ingin memverifikasi langsung lewat API client seperti Postman, Insomnia, Hoppscotch, atau curl.

Base URL contoh:

```text
http://localhost:8000/api
```

Header umum setelah login:

```http
Authorization: Bearer <TOKEN>
Content-Type: application/json
Accept: application/json
```

---

### 7.1 Login Outlet

```http
POST /api/auth/login
```

Body:

```json
{
  "email": "siti.nurhaliza@ddp.test",
  "password": "password123"
}
```

Simpan token sebagai:

```text
OUTLET_TOKEN=<token>
```

Ekspektasi:

- HTTP 200.
- `status = success`.
- `data.user.role = outlet`.

---

### 7.2 Ambil Produk

```http
GET /api/products
Authorization: Bearer <OUTLET_TOKEN>
```

Pilih produk yang:

- aktif/tersedia,
- stok cukup,
- punya harga valid.

Catat:

```text
PRODUCT_ID_1=
PRODUCT_ID_2=
```

---

### 7.3 Outlet Buat Pesanan

```http
POST /api/orders
Authorization: Bearer <OUTLET_TOKEN>
```

Body contoh:

```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 2,
      "quantity": 1
    }
  ],
  "idempotency_key": "manual-order-siti-001"
}
```

Ganti `product_id` dengan produk valid dari environment.

Ekspektasi:

- HTTP 201 untuk order baru.
- `status = success`.
- `data.status = New`.
- Response memiliki numeric `data.id` dan public ref `data.order_id` seperti `ORD-...`.

Catat:

```text
ORDER_DB_ID=<data.id>
ORDER_REF=<data.order_id>
TOTAL_AMOUNT=<data.total_amount>
```

---

### 7.4 Cek Detail Pesanan sebagai Outlet

```http
GET /api/orders/{ORDER_DB_ID}
Authorization: Bearer <OUTLET_TOKEN>
```

Ekspektasi:

- HTTP 200.
- Order milik outlet login.
- Status `New`.
- Items sesuai request.

---

### 7.5 Login Admin

```http
POST /api/auth/login
```

Body:

```json
{
  "email": "ratna.sari@ddp.test",
  "password": "password123"
}
```

Simpan token:

```text
ADMIN_TOKEN=<token>
```

---

### 7.6 Admin Approve Pesanan

```http
PUT /api/orders/{ORDER_DB_ID}/approve
Authorization: Bearer <ADMIN_TOKEN>
```

Ekspektasi:

- HTTP 200.
- `status = success`.
- `data.status = Confirmed`.
- Invoice dibuat untuk order tersebut dengan status awal `unpaid`.

---

### 7.7 Admin Cek Invoice

```http
GET /api/invoices
Authorization: Bearer <ADMIN_TOKEN>
```

Cari invoice untuk `ORDER_DB_ID` atau `ORDER_REF`.

Ekspektasi:

| Field | Ekspektasi |
|---|---|
| `status` invoice | `unpaid` |
| `total_amount` | sama dengan `TOTAL_AMOUNT` |
| `paid_amount` | `0.00` |
| `balance_amount` | sama dengan `TOTAL_AMOUNT` |

Catat:

```text
INVOICE_ID=
INVOICE_NUMBER=
```

---

### 7.8 Cari `DRIVER_USER_ID`

Jika UI/admin user list tersedia, cari user Joko Widodo. Via API:

```http
GET /api/admin/users
Authorization: Bearer <ADMIN_TOKEN>
```

Cari user:

```text
email = joko.widodo@ddp.test
role = driver
```

Catat:

```text
DRIVER_USER_ID=<id user Joko Widodo>
```

---

### 7.9 Admin Assign Delivery

```http
POST /api/deliveries
Authorization: Bearer <ADMIN_TOKEN>
```

Body:

```json
{
  "order_id": 123,
  "driver_id": 11,
  "notes": "Pengiriman manual test 1 siklus pemesanan"
}
```

Ganti:

- `order_id` dengan `ORDER_DB_ID`.
- `driver_id` dengan `DRIVER_USER_ID`.

Ekspektasi:

- HTTP 201.
- `status = success`.
- `data.status = assigned`.
- `data.order_id = ORDER_DB_ID`.
- `data.driver_id = DRIVER_USER_ID`.

Catat:

```text
DELIVERY_ID=<data.id>
```

---

### 7.10 Login Driver

```http
POST /api/auth/login
```

Body:

```json
{
  "email": "joko.widodo@ddp.test",
  "password": "password123"
}
```

Simpan token:

```text
DRIVER_TOKEN=<token>
```

---

### 7.11 Driver Cek Daftar Delivery

```http
GET /api/deliveries
Authorization: Bearer <DRIVER_TOKEN>
```

Ekspektasi:

- Delivery yang di-assign ke driver muncul.
- `DELIVERY_ID` ada di daftar.
- Status `assigned`.

---

### 7.12 Driver Mulai Pengiriman

```http
PATCH /api/deliveries/{DELIVERY_ID}/status
Authorization: Bearer <DRIVER_TOKEN>
```

Body:

```json
{
  "status": "in_progress",
  "notes": "Driver mulai pengiriman ke outlet"
}
```

Ekspektasi:

- HTTP 200.
- `data.status = in_progress`.
- `started_at` terisi.

---

### 7.13 Driver Selesaikan Pengiriman

```http
PATCH /api/deliveries/{DELIVERY_ID}/status
Authorization: Bearer <DRIVER_TOKEN>
```

Body:

```json
{
  "status": "delivered",
  "recipient_name": "Siti Nurhaliza",
  "proof_of_delivery_url": "https://example.com/proof/manual-check-order-001.jpg",
  "notes": "Barang diterima lengkap oleh outlet"
}
```

Ekspektasi:

- HTTP 200.
- `data.status = delivered`.
- `delivered_at` terisi.
- `recipient_name = Siti Nurhaliza`.
- Order terkait berubah menjadi `Delivered`.

Verifikasi order:

```http
GET /api/orders/{ORDER_DB_ID}
Authorization: Bearer <ADMIN_TOKEN>
```

Ekspektasi:

```text
Order status = Delivered
```

---

### 7.14 Login Finance

```http
POST /api/auth/login
```

Body:

```json
{
  "email": "dewi.lestari@ddp.test",
  "password": "password123"
}
```

Simpan token:

```text
FINANCE_TOKEN=<token>
```

---

### 7.15 Finance Catat Pembayaran Full

Pembayaran hanya valid setelah order berstatus `Delivered`.

```http
POST /api/payments
Authorization: Bearer <FINANCE_TOKEN>
```

Body:

```json
{
  "order_id": 123,
  "amount": "150000.00",
  "payment_method": "cash",
  "idempotency_key": "manual-payment-siti-001"
}
```

Ganti:

- `order_id` dengan `ORDER_DB_ID`.
- `amount` dengan `TOTAL_AMOUNT`.

Payment method valid:

```text
cash, bank_transfer, bank, transfer, other
```

Ekspektasi:

- HTTP 201.
- `status = success`.
- `data.status = completed`.
- `data.amount = TOTAL_AMOUNT`.
- `data.order.status = Paid`.
- `data.order.outstanding_balance = 0.00`.
- Ada `receipt.reference` / `receipt_reference`.

Catat:

```text
PAYMENT_ID=<data.id>
RECEIPT_REFERENCE=<data.receipt_reference atau data.receipt.reference>
```

---

### 7.16 Verifikasi Akhir

Cek order:

```http
GET /api/orders/{ORDER_DB_ID}
Authorization: Bearer <ADMIN_TOKEN>
```

Ekspektasi:

| Field | Ekspektasi |
|---|---|
| Order status | `Paid` |
| Paid amount | sama dengan `TOTAL_AMOUNT` |
| Status history | minimal ada `New`, `Confirmed`, `Delivered`, `Paid` |

Cek invoice:

```http
GET /api/invoices
Authorization: Bearer <FINANCE_TOKEN>
```

Ekspektasi:

| Field | Ekspektasi |
|---|---|
| Invoice status | `paid` |
| Paid amount | sama dengan `TOTAL_AMOUNT` |
| Balance amount | `0.00` |

Cek payment:

```http
GET /api/payments
Authorization: Bearer <FINANCE_TOKEN>
```

Ekspektasi:

| Field | Ekspektasi |
|---|---|
| Payment muncul | Ya |
| Payment status | `completed` |
| Receipt reference | terisi |

---

## 8. Negative Checkpoints

Bagian ini opsional tetapi disarankan untuk memastikan validasi bisnis bekerja.

### 8.1 Tidak Bisa Assign Delivery sebelum Order Approved

Langkah:

1. Buat order baru sebagai outlet.
2. Jangan approve order.
3. Login admin.
4. Coba assign delivery untuk order tersebut.

Ekspektasi:

- Request gagal.
- Status HTTP biasanya 422.
- Pesan error: hanya order `Confirmed` yang bisa dibuat delivery assignment.

Catatan code behavior:

```text
Only confirmed orders can be assigned for delivery.
```

### 8.2 Tidak Bisa Bayar sebelum Barang Delivered

Langkah:

1. Buat order.
2. Approve sampai `Confirmed`.
3. Jangan selesaikan delivery.
4. Coba buat payment.

Ekspektasi:

- Request gagal.
- Status HTTP biasanya 422.
- Pesan error menyatakan pembayaran hanya bisa untuk order delivered.

Catatan code behavior:

```text
Payments can only be recorded for delivered orders.
```

### 8.3 Tidak Bisa Overpayment

Langkah:

1. Selesaikan order sampai `Delivered`.
2. Catat `TOTAL_AMOUNT`.
3. Coba bayar lebih besar dari outstanding balance.

Contoh:

```json
{
  "order_id": 123,
  "amount": "999999999.00",
  "payment_method": "cash",
  "idempotency_key": "manual-payment-overpay-001"
}
```

Ekspektasi:

- Request gagal.
- Status HTTP biasanya 422.
- Pesan error:

```text
Payment cannot exceed the outstanding balance.
```

### 8.4 Idempotency Key Tidak Boleh Dipakai untuk Payload Berbeda

Langkah:

1. Buat order memakai `idempotency_key = manual-order-siti-dup-001`.
2. Ulangi request yang sama persis.
3. Ulangi lagi dengan idempotency key sama tetapi item/qty berbeda.

Ekspektasi:

- Request kedua dengan payload sama aman dan mengembalikan order yang sama.
- Request ketiga dengan payload berbeda gagal 422.

---

## 9. Evidence Checklist untuk Tester

Isi bagian ini saat menjalankan manual test.

### 9.1 Informasi Run

| Field | Nilai |
|---|---|
| Tanggal test |  |
| Tester |  |
| Environment | local / staging / lainnya |
| Web URL |  |
| API URL |  |
| Commit/branch |  |

### 9.2 Evidence per Tahap

| Tahap | Bukti yang Disimpan | Status |
|---|---|---|
| Login outlet berhasil | Screenshot / response login | ☐ Pass ☐ Fail |
| Produk terlihat | Screenshot katalog / response products | ☐ Pass ☐ Fail |
| Order dibuat | Screenshot order detail / response `POST /orders` | ☐ Pass ☐ Fail |
| Order status `New` | Screenshot / response detail order | ☐ Pass ☐ Fail |
| Admin approve | Screenshot / response approve | ☐ Pass ☐ Fail |
| Order status `Confirmed` | Screenshot / response detail order | ☐ Pass ☐ Fail |
| Invoice dibuat `unpaid` | Screenshot invoice / response invoices | ☐ Pass ☐ Fail |
| Delivery assigned | Screenshot / response `POST /deliveries` | ☐ Pass ☐ Fail |
| Driver start delivery | Screenshot / response status `in_progress` | ☐ Pass ☐ Fail |
| Driver delivered | Screenshot / response status `delivered` | ☐ Pass ☐ Fail |
| Order status `Delivered` | Screenshot / response order detail | ☐ Pass ☐ Fail |
| Payment recorded | Screenshot / response `POST /payments` | ☐ Pass ☐ Fail |
| Order status `Paid` | Screenshot / response order detail | ☐ Pass ☐ Fail |
| Invoice status `paid` | Screenshot / response invoice | ☐ Pass ☐ Fail |
| Outstanding balance `0.00` | Screenshot / response payment/invoice | ☐ Pass ☐ Fail |

### 9.3 Final Result

| Item | Nilai |
|---|---|
| Overall result | ☐ Pass ☐ Fail |
| Blocking issue |  |
| Notes |  |

---

## 10. Ringkasan Acceptance Criteria

Siklus manual dianggap **PASS** jika semua kondisi berikut terpenuhi:

- Outlet dapat login dan membuat order.
- Order awal berstatus `New`.
- Admin dapat approve order.
- Order berubah menjadi `Confirmed`.
- Invoice otomatis tersedia dengan status `unpaid`.
- Delivery hanya bisa dibuat setelah order `Confirmed`.
- Driver dapat menjalankan delivery `assigned → in_progress → delivered`.
- Saat delivery `delivered`, order berubah menjadi `Delivered`.
- Payment hanya bisa dicatat setelah order `Delivered`.
- Payment full amount membuat:
  - payment `completed`,
  - order `Paid`,
  - invoice `paid`,
  - outstanding/balance `0.00`.

---

## 11. Troubleshooting Singkat

| Masalah | Kemungkinan Penyebab | Tindakan |
|---|---|---|
| Login gagal | Seeder belum jalan atau password berubah | Jalankan seeder / cek database users |
| Produk kosong | Data produk belum tersedia | Tambah produk aktif via seed/factory/admin |
| Order gagal karena stock | Stok produk kurang | Pilih produk lain atau tambah stok |
| Order gagal karena credit limit | Limit outlet tidak cukup / outstanding tinggi | Gunakan outlet lain atau lunasi/reset data |
| Approve gagal | User bukan admin/owner atau order bukan `New` | Login admin dan cek status order |
| Delivery assign gagal | Order belum `Confirmed` | Approve order dulu |
| Driver tidak melihat delivery | Delivery diassign ke driver lain | Cek `driver_id` assignment |
| Delivered gagal | `recipient_name` / `proof_of_delivery_url` belum diisi | Lengkapi field wajib |
| Payment gagal | Order belum `Delivered` | Selesaikan delivery dulu |
| Overpayment gagal | Amount lebih besar dari outstanding | Gunakan amount sesuai balance |
