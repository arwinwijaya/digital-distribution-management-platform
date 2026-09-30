# Outlet-First Login Appeal

**Date:** 2026-09-30
**Status:** approved
**Author:** pocket-grinding session (from pitch: docs/pocket/spec/2026-09-30-login-outlet-first-appeal/pitch-exploration.md)
**Spec path:** docs/pocket/spec/2026-09-30-login-outlet-first-appeal/outlet-first-login.md

---

## Summary

Login saat ini terasa seperti portal internal generik. Halaman ini mengubah tampilan `/login` menjadi outlet-first: hero ringkas berisi satu cerita reorder + tiga benefit, CTA "Masuk & pesan ulang", dan demo credentials dipindah ke footer help. Satu form tetap universal untuk semua role, dan seluruh auth flow, token storage, serta role-based redirect dipertahankan tanpa perubahan.

---

## Context

### Current State

- `apps/web/src/app/login/page.tsx` memuat token existing, memanggil `GET /auth/me`, lalu redirect via `roleDestination(role, redirectParam)`; menampilkan `PageHeader` + `LoginForm` + catatan demo credentials.
- `apps/web/src/components/LoginForm.tsx` menangani input email/password, submit ke `POST /auth/login`, menyimpan token (`storeToken`), menyetel `ddp_role`, dan memicu event `ddp-auth-change`; menampilkan inline error dan loading state; menggunakan `Card`, `Input`, `Button`.
- `LoginForm` juga dipakai di `apps/web/src/app/marketplace/page.tsx` dengan `expectedRole="outlet"`.
- Auth login hanya mengembalikan token + `user.role`; tidak ada data reorder/promo sebelum autentikasi.

### Problem / Motivation

Login tidak membangun alasan emosional maupun manfaat bisnis yang terlihat bagi outlet, sehingga tidak mendorong reorder. Belum ada elemen yang menjelaskan nilai platform bagi pemilik outlet pada momen transisi sebelum masuk.

### Related Areas

- `apps/web/src/app/login/page.tsx` — surface utama.
- `apps/web/src/components/LoginForm.tsx` — komponen form bersama.
- `apps/web/src/app/marketplace/page.tsx` — konsumen `LoginForm` yang harus tetap berperilaku sama.
- `apps/web/src/lib/api.ts` — `apiUrl`, `authHeaders`, `getStoredToken`, `storeToken`.
- `apps/web/src/components/ui/*` — `Button`, `Card`, `Input`, `PageHeader`.

---

## Scope

### In-Scope

- Mengubah tampilan login menjadi outlet-first, menarik, dan mobile-first.
- Menambahkan messaging pendorong reorder tanpa klaim data personal sebelum login.
- Mempertahankan satu form universal untuk outlet/admin/sales/driver/finance.
- Mempertahankan auth flow, error handling, loading state, token storage, dan role-based redirect.
- Menambahkan test UI/behavior untuk copy penting, struktur layout, dan submit flow existing.

### Out-of-Scope

- Perubahan API, database, auth contract, atau role permission — auth harus tetap utuh.
- Menampilkan stok/promo personal sebelum login — data belum tersedia pra-autentikasi.
- Rekomendasi produk, one-click reorder, atau promo dinamis — di luar arah visual login.
- Perubahan halaman `/orders` setelah redirect — hanya login yang berubah.
- A/B testing, analytics instrumentation, feature flag — tidak ada instrumentasi baru.
- Dependency UI baru — reuse komponen/Tailwind existing.

---

## Architecture Constraints

- Layers this work may touch: presentation layer `/login` + prop opsional pada `LoginForm` + test terkait.
- Layers this work must NOT touch: API routes, controllers, auth contract, RBAC, data layer.
- Patterns that must be followed: reuse `Card`/`Button`/`Input`/`PageHeader`, Tailwind responsive utilities, server/client split existing (`'use client'` pada form).
- Architecture validation result: **PASS**

---

## Dependencies

### Existing (to leverage)

- Next.js 16 + React 18 — routing (`useRouter`, `useSearchParams`), Suspense.
- Tailwind CSS — responsive layout & styling.
- Komponen internal `Button`, `Card`, `Input`, `PageHeader` — seluruh UI hero dan form.
- `@/lib/api` — `apiUrl`, `authHeaders`, `getStoredToken`, `storeToken`.

### New (proposed)

none

---

## Stories + Scenarios

### Story: Outlet-first login motivation
> As an outlet user, I want the login page to show reorder value, so that I feel motivated to log in and place an order.

**Rule 1: Hero outlet-first tampil dengan cerita + 3 benefit**
- Example A: desktop → hero di samping form, form terlihat tanpa scroll.
- Example B: mobile → branding + form muncul lebih dulu, benefit ringkas setelahnya.

**Rule 2: Copy jujur — tanpa klaim data personal pra-login**
- Example C: hero memakai framing "Setelah masuk Anda bisa…"; tidak menampilkan "Stok Anda menipis".

```gherkin
Scenario: Hero outlet-first tampil di desktop
  Given pengunjung membuka /login pada viewport lebar
  When  halaman dirender
  Then  hero berisi satu cerita + tiga benefit tampil di samping form
  And   form login terlihat tanpa perlu scroll

Scenario: Layout form-first di mobile
  Given pengunjung membuka /login pada viewport sempit
  When  halaman dirender
  Then  branding dan form login tampil sebelum daftar benefit
  And   CTA utama bertuliskan "Masuk & pesan ulang"

Scenario: Tidak ada klaim data personal sebelum login
  Given pengunjung belum terautentikasi
  When  halaman login dirender
  Then  copy hero memakai framing kemampuan "Setelah masuk Anda bisa…"
  And   tidak ada klaim stok atau promo spesifik outlet yang ditampilkan
```

### Story: Universal login tetap utuh
> As a non-outlet user (admin/sales/driver/finance), I want to log in normally, so that the new design doesn't break my access.

**Rule 3: Satu form universal untuk semua role**
- Example D: kredensial outlet → redirect `/orders`.
- Example E: kredensial admin → redirect `/dashboard`.

**Rule 4: CTA "Masuk & pesan ulang" tetap dipahami semua role**
- Example F: user non-outlet membaca hero & CTA → tetap jelas ini form tunggal untuk semua peran.

**Rule 5: Role-based redirect tidak berubah**
- Example G: driver → `/delivery`; sales → `/sales/orders`; finance/platform_owner/admin → `/dashboard`.

```gherkin
Scenario: Login outlet sukses redirect ke /orders
  Given kredensial outlet valid
  When  pengguna submit form
  Then  token disimpan dan ddp_role diset
  And   event ddp-auth-change dipicu
  And   pengguna diarahkan ke /orders

Scenario: Login non-outlet tetap bekerja
  Given kredensial admin valid
  When  pengguna submit form
  Then  pengguna diarahkan ke /dashboard

Scenario: redirect param tetap dipatuhi
  Given URL /login?redirect=/somewhere dan kredensial valid
  When  login sukses
  Then  pengguna diarahkan ke roleDestination(role, redirect)
```

### Story: Auth behavior dipertahankan
> As a user, I want login errors and loading states to work as before, so that I can recover from failures.

**Rule 6: Error inline di dalam form**
- Example H: kredensial salah → alert inline, email & password tetap terisi.

**Rule 7: Loading state mencegah double submit**
- Example I: request berjalan → tombol disabled, label "Memproses...".

**Rule 8: Demo credentials subtle di footer help**
- Example J: halaman render → demo credentials hanya teks kecil di footer.

```gherkin
Scenario: Kredensial salah menampilkan error inline
  Given pengunjung belum terautentikasi
  When  pengguna submit kredensial salah
  Then  alert inline dengan role="alert" tampil di dalam form
  And   nilai email dan password tetap terisi

Scenario: Kegagalan network/5xx menampilkan error inline generik
  Given pengunjung belum terautentikasi
  When  submit gagal karena network atau 5xx
  Then  alert inline yang sama tampil
  And   nilai email dan password tetap terisi

Scenario: Loading state mencegah double submit
  Given pengguna telah submit kredensial valid
  When  request sedang berjalan
  Then  tombol submit disabled dan menampilkan "Memproses..."
  And   hanya satu request yang dikirim walau tombol diklik berulang

Scenario: Session existing bypass halaman login
  Given token valid tersimpan
  When  pengunjung membuka /login
  Then  pengecekan token existing melakukan redirect segera tanpa menampilkan form yang dapat dipakai

Scenario: expectedRole mismatch di marketplace tetap error
  Given LoginForm dengan expectedRole="outlet" pada halaman terproteksi
  When  submit kredensial admin valid
  Then  error role inline tampil dan tidak ada token/role yang disimpan

Scenario: Demo credentials subtle
  Given halaman login dirender
  When  pengunjung melihat area CTA utama
  Then  demo credentials tampil hanya sebagai teks bantuan kecil di footer
  And   tidak mendominasi tombol submit
```

---

## Acceptance Criteria

```
Rule: Hero outlet-first (desktop)
  ✓ Given viewport lebar, When /login render, Then hero (cerita + 3 benefit) tampil di samping form, form terlihat tanpa scroll
  ✓ Given copy hero, When dirender, Then memakai framing "Setelah masuk Anda bisa…" tanpa klaim stok/promo personal

Rule: Form-first (mobile)
  ✓ Given viewport sempit, When /login render, Then branding + form tampil sebelum daftar benefit; CTA "Masuk & pesan ulang"

Rule: Form universal + CTA semua role
  ✓ Given kredensial outlet/admin/sales/driver/finance valid, When submit, Then token disimpan, ddp_role diset, ddp-auth-change dipicu, redirect ke roleDestination(role)
  ✓ Given ?redirect=..., When login sukses, Then redirect memakai roleDestination(role, redirect)

Rule: Auth behavior dipertahankan
  ✓ Given kredensial salah, When submit, Then alert inline role="alert" dekat form; email/password tetap terisi
  ✓ Given error network/5xx, When submit, Then alert inline yang sama; field tetap terisi
  ✓ Given loading, When request berjalan, Then tombol disabled + "Memproses..."; hanya satu request
  ✓ Given token valid tersimpan, When buka /login, Then token check existing redirect segera tanpa form usable
  ✓ Given expectedRole="outlet" di marketplace, When submit kredensial admin, Then error role inline, tidak ada token disimpan

Rule: Demo credentials subtle
  ✓ Given /login render, When dilihat, Then demo credentials hanya teks bantuan kecil di footer, tidak mendominasi CTA
```

---

## Design Decision

**Chosen option:** Option A — Inline hero inside `/login/page.tsx`

**Summary:** Hero outlet-first dibangun langsung di `apps/web/src/app/login/page.tsx`; `LoginForm` hanya menerima prop opsional `ctaLabel` yang dikirim oleh `/login` ("Masuk & pesan ulang"), sementara `marketplace` dan pemakaian lain tetap memakai default "Masuk" tanpa perubahan kode.

**Rejected options:**
- Option B (new `OutletLoginForm` component): ditolak karena menambah file/abstraksi tanpa manfaat saat ini; hero hanya dipakai di satu halaman.
- Option C (feature-flagged `LoginForm` dengan hero slot): ditolak karena menyentuh komponen bersama yang dipakai `marketplace` dengan risiko regresi lebih tinggi dan abstraksi prematur.

**Key tradeoffs accepted:**
- Hero logic hidup di page, tidak reusable — diterima karena hanya satu surface.
- `LoginForm` menerima satu prop baru (`ctaLabel`) dengan default aman — perubahan minimal dan backward-compatible.

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| (semua pertanyaan blocking sudah terjawab saat discovery) | — | — |

---

## Implementation Notes

- Prop `ctaLabel` pada `LoginForm` harus opsional dengan default `"Masuk"` agar `marketplace` tidak berubah perilaku.
- Hero harus memakai copy statis/ilustratif; jangan menambahkan fetch baru pra-login.
- Pertahankan penyimpanan `ddp_role`, event `ddp-auth-change`, dan `roleDestination` apa adanya.
- Error network/5xx menggunakan pesan generik yang sama seperti kredensial salah (perilaku existing).

---

## Rollback Plan

- `git revert` perubahan pada `apps/web/src/app/login/page.tsx` dan prop opsional `LoginForm` — tidak ada migrasi data atau perubahan kontrak.
- Tidak ada feature flag yang diperlukan; rollback murni kode presentasi.
