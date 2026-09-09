# Panduan Setup & Menjalankan — Digital Distribution Management Platform (DDP)

> Audiens: developer & DevOps yang ingin install, menjalankan, dan mendeploy DDP dari nol.
> Dokumen pendamping: `docs/dokumentasi-teknis.md` (arsitektur & API), `docs/panduan-pengguna.md` (panduan non-teknis).
> Terakhir diverifikasi terhadap: `package.json`, `apps/api/composer.json`, `apps/web/package.json`, `packages/shared/package.json`, kedua `Dockerfile`, `docker-compose.yml`, `.github/workflows/ci.yml`, `phpunit.xml.dist`, dan `config/*.php`.

---

## Daftar Isi

1. [Gambaran & pilihan cara menjalankan](#1-gambaran--pilihan-cara-menjalankan)
2. [Prasyarat tech stack](#2-prasyarat-tech-stack)
3. [Opsi A — Docker (disarankan, 5 menit)](#3-opsi-a--docker-disarankan-5-menit)
4. [Opsi B — Manual tanpa Docker](#4-opsi-b--manual-tanpa-docker)
5. [Variabel environment (.env) lengkap](#5-variabel-environment-env-lengkap)
6. [Database: migrasi & seed](#6-database-migrasi--seed)
7. [Menjalankan testing](#7-menjalankan-testing)
8. [Troubleshooting](#8-troubleshooting)
9. [Deploy produksi (checklist)](#9-deploy-produksi-checklist)
10. [Referensi cepat perintah](#10-referensi-cepat-perintah)

---

## 1. Gambaran & pilihan cara menjalankan

| Opsi | Cocok untuk | Yang perlu diinstall |
|---|---|---|
| **A. Docker Compose** (disarankan) | Coba cepat, demo, dev seragam | Hanya **Docker + Docker Compose** |
| **B. Manual** | Development aktif / debugging per service | Node.js 20+, PHP 8.2+, Composer, PostgreSQL 16+, Redis 7+ |

Hasil akhir kedua opsi sama — 4 proses berjalan:

| Service | URL / Port | Isi |
|---|---|---|
| Web (Next.js) | http://localhost:3000 | Aplikasi web untuk semua peran |
| API (Laravel) | http://localhost:8000 (`/api/...`) | REST API + health check `/api/health` |
| PostgreSQL | localhost:5432 | Database `ddp_database` |
| Redis | localhost:6379 | Cache |

> ⚠️ **Catatan jujur:** `apps/api/Dockerfile` menjalankan `php-fpm` di port 9000 dan `docker-compose.yml` mem-mount source code, jadi untuk development ini pola yang dipakai repo ini. Ikuti langkah apa adanya; jangan berasumsi ada nginx terpisah.

---

## 2. Prasyarat tech stack

### 2.1 Untuk Opsi A (Docker) — cukup ini saja

| Kebutuhan | Versi | Cara cek | Cara install |
|---|---|---|---|
| Docker Engine | 24+ | `docker --version` | https://docs.docker.com/get-docker/ (Docker Desktop untuk Windows/Mac sudah termasuk Compose) |
| Docker Compose | v2+ | `docker compose version` |ikut Docker Desktop; Linux: plugin `docker-compose-plugin` |

### 2.2 Untuk Opsi B (Manual) — semua ini

| Kebutuhan | Versi yang dipakai proyek | Cara cek | Cara install |
|---|---|---|---|
| **Node.js** | 20+ (CI memakai 20; Dockerfile `node:20-alpine`) | `node --version` | https://nodejs.org — ambil LTS 20. npm ikut otomatis (butuh npm 10+; `npm --version`) |
| **PHP** | ^8.2 (`composer.json` mensyaratkan `^8.2`; Dockerfile `php:8.2-fpm`) | `php --version` | Windows: https://windows.php.net (thread-safe + ekstensi di bawah); Mac: `brew install php@8.2`; Linux: paket distro / `ondrej/php` |
| **Ekstensi PHP wajib** | `mbstring, xml, ctype, json, bcmath, pdo, pgsql` (persis seperti job CI) + yang dipakai Dockerfile: `gd, intl, exif, pcntl, onig` | `php -m` | Aktifkan di `php.ini` (Windows: uncomment `extension=pgsql` dsb.) |
| **Composer** | 2.x | `composer --version` | https://getcomposer.org/download/ |
| **PostgreSQL** | 16 (image `postgres:16-alpine`; CI juga 16) | `psql --version` | https://www.postgresql.org/download/ atau via Docker hanya untuk DB (lihat §4.1) |
| **Redis** | 7 (image `redis:7-alpine`; CI juga 7) | `redis-cli ping` | Windows: via Docker/WSL; Mac: `brew install redis`; Linux: paket distro |
| **Git** | bebas | `git --version` | https://git-scm.com |

**Modul yang akan terinstall otomatis** (tidak perlu install manual, tercantum agar transparan):

- *Backend (`composer install`)*: `laravel/framework ^11.0`, `laravel/sanctum ^4.0`, `tymon/jwt-auth ^2.0` (auth JWT), `spatie/laravel-permission ^6.4`, `spatie/laravel-query-builder ^6.0`, `spatie/laravel-data ^4.0`; dev: `phpunit/phpunit ^11.0`, `laravel/pint`, `laravel/sail`, `laravel/telescope`, `fakerphp/faker`, `mockery/mockery`, `nunomaduro/collision`.
- *Frontend (`npm ci` di `apps/web`)*: `next ^14.2.0`, `react/react-dom ^18.3.0`, `axios ^1.7.0`, `zustand ^4.5.0`, `tailwindcss ^3.4.0`, `postcss`, `autoprefixer`; dev: `typescript ^5.4.0`, `eslint + eslint-config-next`, `jest ^29.7.0`, `@testing-library/react`, `@testing-library/jest-dom`, `@types/*`.
- *Shared (`packages/shared`)*: `typescript`, `jest`, `ts-jest`, `@types/jest`.

---

## 3. Opsi A — Docker (disarankan, 5 menit)

### Langkah 1 — Clone & masuk direktori

```bash
git clone <repository-url>
cd digital-distribution-management-platform
```

### Langkah 2 — Siapkan environment API (pertama kali saja)

```bash
cp apps/api/.env.example apps/api/.env
```

Lalu buka `apps/api/.env` dan pastikan minimal blok ini (nilai dev bawaan sudah cocok dengan `docker-compose.yml`):

```ini
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=ddp_database
DB_USERNAME=ddp_user
DB_PASSWORD=ddp_password
REDIS_HOST=redis
REDIS_PORT=6379
```

> Detail semua variabel ada di §5 — termasuk variabel JWT/WhatsApp/komisi yang **belum ada di `.env.example`** dan harus ditambahkan manual bila fiturnya dipakai.

### Langkah 3 — Jalankan semuanya

```bash
docker-compose up -d        # atau: npm run docker:up
docker-compose ps           # pastikan 4 container Up (ddp_db, ddp_redis, ddp_api, ddp_web)
```

### Langkah 4 — Migrasi database (pertama kali saja)

```bash
docker-compose exec api php artisan key:generate
docker-compose exec api php artisan migrate --force
```

### Langkah 5 — Buka aplikasinya

- Web: **http://localhost:3000**
- API health: **http://localhost:8000/api/health** → harus `{"status":"healthy",...}`
- Login API (contoh): `POST http://localhost:8000/api/auth/login` dengan `{email, password}`

### Perintah Docker sehari-hari

```bash
docker-compose logs -f api     # lihat log backend
docker-compose logs -f web     # lihat log frontend
docker-compose logs -f db      # lihat log database
docker-compose down            # matikan semua
docker-compose down -v         # matikan + HAPUS data DB/Redis (hati-hati!)
```

---

## 4. Opsi B — Manual tanpa Docker

### 4.1 (Opsional tapi disarankan) Jalankan hanya DB + Redis via Docker

Bila Anda tidak ingin install PostgreSQL/Redis native, cara termudah: tetap pakai Docker **hanya** untuk keduanya. Buat database + user-nya sekali saja:

```bash
docker run -d --name ddp_db -e POSTGRES_DB=ddp_database -e POSTGRES_USER=ddp_user \
  -e POSTGRES_PASSWORD=ddp_password -p 5432:5432 postgres:16-alpine
docker run -d --name ddp_redis -p 6379:6379 redis:7-alpine redis-server --appendonly yes
```

Atau bila PostgreSQL/Redis sudah terinstall native, buat database-nya:

```sql
-- di psql sebagai superuser
CREATE USER ddp_user WITH PASSWORD 'ddp_password';
CREATE DATABASE ddp_database OWNER ddp_user;
```

### 4.2 Backend — Laravel API

```bash
cd apps/api

# 1. Install modul PHP
composer install

# 2. Environment (pertama kali)
cp .env.example .env

# 3. Sesuaikan .env untuk lokal (DB_HOST 127.0.0.1 bila DB native,
#    atau 'db' bila API jalan di Docker — lihat §5 lengkap)
php artisan key:generate

# 4. Migrasi
php artisan migrate

# 5. Jalankan (port 8000 agar sama dengan frontend default)
php artisan serve --port=8000
```

Verifikasi: buka http://localhost:8000/api/health → `{"status":"healthy",...}`.

### 4.3 Frontend — Next.js Web

```bash
cd apps/web

# 1. Install modul JS (pakai ci agar persis lockfile, seperti CI)
npm ci

# 2. (Opsional) arahkan ke API bila bukan localhost:8000
#    Buat file apps/web/.env.local berisi:
#    NEXT_PUBLIC_API_URL=http://localhost:8000/api

# 3. Jalankan dev server
npm run dev
# → http://localhost:3000
```

> Catatan: repo ini **belum menyertakan file `.env*` untuk web** — nilai default di `src/lib/api.ts` (`http://localhost:8000/api`) sudah benar untuk setup standar, jadi file `.env.local` hanya dibutuhkan bila URL API berbeda.

### 4.4 Shared package (opsional)

Hanya diperlukan bila Anda mengubah tipe bersama di `packages/shared`:

```bash
cd packages/shared
npm ci
npm run build   # kompilasi tsc
npm test        # jest + ts-jest
```

### 4.5 Perintah root monorepo

Dari direktori root, npm workspaces meneruskan perintah ke semua paket:

```bash
npm run dev     # dev di semua workspace
npm run build   # build semua workspace
npm run test    # test semua workspace
npm run lint    # lint semua workspace
npm run api:artisan  # jalan pintas: php artisan di apps/api
```

---

## 5. Variabel environment (.env) lengkap

File acuan: `apps/api/.env.example`. **Temuan penting (sudah diverifikasi ke `config/*.php`): variabel JWT, WhatsApp, komisi order, dan Redis BELUM ada di `.env.example`** — tanpa menambahkannya manual, fitur terkait memakai nilai default (aman untuk dev lokal, tapi token WA kosong = integrasi WA tidak jalan). Tabel di bawah adalah daftar **lengkap dan benar** per sumbernya:

### 5.1 Database & cache (ada di `.env.example`, wajib benar)

| Variabel | Default dev | Keterangan |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | Jangan diganti (skema memakai fitur Postgres) |
| `DB_HOST` | `127.0.0.1` manual / `db` di Docker | Host Postgres |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `ddp_database` | Harus sama dengan `POSTGRES_DB` di compose |
| `DB_USERNAME` / `DB_PASSWORD` | `ddp_user` / `ddp_password` | ⚠️ Ganti di produksi! |
| `REDIS_HOST` / `REDIS_PORT` | `redis` / `6379` (Docker) | **Tidak ada di `.env.example`** — tambahkan manual; Laravel membaca via `config/database.php` |

### 5.2 Aplikasi & auth (sebagian ada di `.env.example`)

| Variabel | Default / contoh | Keterangan |
|---|---|---|
| `APP_KEY` | *(diisi `key:generate`)* | Wajib; juga dipakai sebagai fallback `JWT_SECRET` |
| `APP_ENV`, `APP_DEBUG` | `local`, `true` | Produksi: `production`, `false` |
| `APP_URL` | `http://localhost:8000` | |
| `APP_TIMEZONE` | `Asia/Jakarta` | Sudah benar untuk Indonesia |
| `JWT_SECRET` | *(kosong → fallback APP_KEY)* | **Tidak ada di `.env.example`** (sumber: `config/jwt.php`). Disarankan set eksplisit di produksi |
| `JWT_TTL` | `1440` (menit = 24 jam) | Masa berlaku token — cocok dengan `expires_in: 86400` di respons login |
| `JWT_REFRESH_TTL` | `20160` | Masa berlaku refresh |
| `BCRYPT_ROUNDS` | `12` | Sudah di `.env.example` |

### 5.3 Bisnis & WhatsApp (TIDAK ada di `.env.example` — tambahkan bila dipakai)

```ini
# --- Fitur bisnis ---
ORDER_COMMISSION_PERCENTAGE=2.00   # snapshot komisi per order (config/orders.php)

# --- WhatsApp (config/whatsapp.php) ---
WHATSAPP_ENABLED=true
WHATSAPP_WEBHOOK_SECRET=           # rahasia HMAC verifikasi webhook (wajib di produksi!)
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_API_URL=https://graph.facebook.com/v20.0
WHATSAPP_ACCESS_TOKEN=             # token Cloud API
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_CATALOG_LIMIT=20          # batas item katalog yang dikirim via WA
WHATSAPP_SEND_LEASE_SECONDS=300    # lease idempotensi pengiriman
```

> Tanpa `WHATSAPP_ACCESS_TOKEN`, endpoint `/api/whatsapp/*` outbound tidak bisa mengirim (inbound webhook tetap bisa diterima selama secret benar).

### 5.4 Frontend

| Variabel | Default | Cara set |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000/api` (fallback di `src/lib/api.ts`) | File `apps/web/.env.local` (buat sendiri, belum ada di repo) |

---

## 6. Database: migrasi & seed

22 migrasi, urutan sudah benar secara dependensi (users → outlets → products → orders → suppliers → payments/credit → sales/delivery → whatsapp). Perintah:

```bash
cd apps/api
php artisan migrate            # jalankan semua
php artisan migrate:fresh      # ⚠️ hapus SEMUA data lalu migrasi ulang (dev saja!)
php artisan migrate:status     # lihat status tiap batch
```

Seeder skala (dipakai load-test T9): `ScaleFixtureSeeder` — membuat 500 outlet untuk uji beban. Lihat `docs/performance/T9-load-test.md` untuk skenario lengkapnya (SLO: p95 & maks < 2 detik pada 100 request konkuren).

---

## 7. Menjalankan testing

### 7.1 Backend — PHPUnit (13 suite Feature + Unit + Performance)

```bash
cd apps/api
php artisan test
# atau spesifik:
php artisan test --filter=OrderTest
php artisan test --testsuite=Feature
```

Catatan: `phpunit.xml.dist` meng-overrides DB ke **SQLite in-memory** saat testing (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), jadi **test backend tidak butuh Postgres menyala** — kecuali race suite Postgres (`WhatsAppPostgresConcurrencyTest`, skenario di `DeliveryTest`/`PaymentTest`) yang butuh DB sungguhan + barrier env (`ORDER_CONCURRENCY_BARRIER_*`, `WHATSAPP_CONCURRENCY_BARRIER_ENABLED`). Lihat CI untuk contoh env Postgres (`ddp_test`).

### 7.2 Frontend — Jest + E2E

```bash
cd apps/web
npm test          # jest (unit/komponen)
npm run lint      # eslint (wajib lolos di CI)
npm run test:e2e  # order-flow.test.js — E2E HTTP nyata: menyalakan API Laravel
                  # via PHP built-in server (butuh `php` di PATH!) dengan SQLite,
                  # lalu seed admin+produk → order → approve. Timeout 120 dtk.
npm run build     # Next build (juga dijalankan CI dengan NEXT_PUBLIC_API_URL dummy)
```

### 7.3 CI (GitHub Actions)

Otomatis tiap push ke `main/develop` + PR ke `main`: `api-tests` (PHP 8.2 + Postgres 16 + Redis 7) → `web-tests` (Node 20: `npm ci` → lint → test → build) → `docker-build` (build kedua image). File: `.github/workflows/ci.yml`.

---

## 8. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| `SQLSTATE could not connect` saat `migrate`/serve | DB belum nyala / `DB_HOST` salah | Docker: `docker-compose ps`, pastikan `ddp_db` healthy. Manual: `DB_HOST=127.0.0.1` + pastikan Postgres jalan + kredensial cocok |
| `No application encryption key` | `APP_KEY` kosong | `php artisan key:generate` |
| Login 500 / token error | `JWT_SECRET` tidak set & `APP_KEY` bermasalah | Pastikan `key:generate` sudah jalan; set `JWT_SECRET` eksplisit bila perlu |
| Web: `Failed to fetch` / network error | API tidak jalan atau URL salah | Cek `http://localhost:8000/api/health`; samakan `NEXT_PUBLIC_API_URL`; lalu restart `npm run dev` (env Next dibaca saat start!) |
| `composer install` gagal (ext-pgsql missing) | Ekstensi PHP belum aktif | Aktifkan `pgsql`, `mbstring`, `bcmath`, dsb. di `php.ini` (`php -m` untuk cek) — daftar persis di §2.2 |
| `npm ci` error di Windows | Path panjang / lockfile | Jalankan di path pendek, atau `npm install` sebagai fallback (lalu jangan commit lockfile berubah tanpa sengaja) |
| Port bentrok (3000/5432/6379/8000 sudah dipakai) | Aplikasi lain memakai port | Matikan aplikasi lain, atau ubah mapping port di `docker-compose.yml` / `--port=` serve |
| WA webhook 401/signature invalid | `WHATSAPP_WEBHOOK_SECRET` salah/kosong | Samakan dengan secret di dashboard provider; untuk dev bisa nonaktifkan via `WHATSAPP_ENABLED` — **jangan di produksi** |
| Test E2E web timeout | `php` tidak di PATH / port tertutup | Install PHP + pastikan port bebas; test ini menyalakan server PHP sendiri |
| `docker-compose up` gagal build API | Cache composer / network | `docker-compose build --no-cache api` lalu `up -d` lagi |
| Storage permission error (Laravel) | Permission folder | `chmod -R 775 storage bootstrap/cache` (Linux/Mac); Docker image sudah `chown www-data` otomatis |

---

## 9. Deploy produksi (checklist)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`; `APP_KEY` & `JWT_SECRET` dari secret manager (jangan commit `.env`!).
- [ ] Ganti **semua** kredensial default: `DB_PASSWORD`, `POSTGRES_*`, `REDIS` password bila diekspos.
- [ ] Set `WHATSAPP_WEBHOOK_SECRET`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_VERIFY_TOKEN` asli; pastikan verifikasi HMAC aktif.
- [ ] `php artisan migrate --force` (CI memakai pola yang sama) + `config:cache`, `route:cache`, `view:cache`.
- [ ] Build frontend produksi: `npm ci && npm run build` dengan `NEXT_PUBLIC_API_URL` menunjuk domain API publik (catatan: Dockerfile web memakai `output: standalone` + user non-root `nextjs` — sudah production-ready).
- [ ] Redis & Postgres pakai volume persisten + backup terjadwal (`postgres_data`, `redis_data` di compose hanya contoh dev).
- [ ] Tambahkan rate-limit/captcha pada `POST /api/auth/register` (endpoint publik) dan pantau `/api/health`.
- [ ] Jalankan full test + pertahankan SLO T9 (p95 & maks < 2 dtk) sebelum release.

---

## 10. Referensi cepat perintah

```bash
# Docker
docker-compose up -d && docker-compose exec api php artisan migrate --force
npm run docker:down

# Backend
cd apps/api && composer install && cp .env.example .env \
  && php artisan key:generate && php artisan migrate \
  && php artisan serve --port=8000
cd apps/api && php artisan test

# Frontend
cd apps/web && npm ci && npm run dev
cd apps/web && npm test && npm run lint && npm run build

# Semua workspace dari root
npm run dev | npm run build | npm run test | npm run lint
```
