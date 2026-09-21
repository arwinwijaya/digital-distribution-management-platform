# Worktree — Order Flow Visualization

**Date:** 2026-05-06
**Status:** approved
**Author:** brainstorm session
**Spec path:** docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md

---

## Summary

Halaman baru `/worktree` yang menampilkan visualisasi flow proses pemesanan end-to-end (dari outlet membuat pesanan sampai selesai). Semua role login bisa mengakses menu ini sebagai referensi read-only. Flow ditampilkan sebagai diagram tahapan interaktif dengan highlight role user yang sedang login, panel detail saat diklik, dan deep-link ke menu terkait.

---

## Context

### Current State
Tidak ada flow visualization component di codebase. Order lifecycle sudah terdefinisi di backend (New → Confirmed → Delivered → Partially Paid → Paid / Cancelled) tapi user tidak punya referensi visual untuk memahami urutan proses secara keseluruhan.

### Problem / Motivation
Semua role (outlet, sales, driver, admin, finance) perlu memahami alur pemesanan dari awal sampai selesai agar bisa menjalankan tugas masing-masing dengan benar. Saat ini pengetahuan ini tersebar di dokumentasi atau pengalaman, tidak ada panduan visual terpadu di dalam aplikasi.

### Related Areas
- `apps/web/src/components/Sidebar.tsx` — menu baru ditambahkan
- `apps/web/src/components/ui/StatusBadge.tsx` — reusable badge untuk mapping status
- `apps/web/src/components/ui/Card.tsx` — container flow nodes
- `apps/web/src/app/orders/page.tsx` — halaman target deep-link (outlet)
- `apps/web/src/app/admin/orders/page.tsx` — halaman target deep-link (admin)
- `apps/web/src/app/delivery/page.tsx` — halaman target deep-link (delivery)
- `apps/web/src/app/payments/page.tsx` — halaman target deep-link (payment)

---

## Scope

### In-Scope
- Menu sidebar "Worktree" (🌳) di posisi bawah list operasional (setelah Sales), terlihat semua role termasuk finance dan supplier
- Halaman baru `apps/web/src/app/worktree/page.tsx`
- Flow diagram 6 tahap: Pemesanan → Persetujuan → Penugasan → Pengiriman → Pembayaran → Selesai
- Node branch: Cancelled (terminal, connector dari Pemesanan + Persetujuan + Pembayaran) + Failed (terminal, connector dari Pengiriman)
- Highlight semua tahap di mana user role adalah actor (misal admin nyala di Persetujuan + Penugasan + Pembayaran); supplier tidak ada highlight
- Klik step → panel detail (role, aksi, status, deskripsi) dengan deep link "Buka menu"
- Tombol "Buka menu" hidden/disabled jika role user tidak punya akses ke halaman tujuan
- Responsive: panel detail di kanan (desktop) → bawah (mobile)
- Auth guard: redirect ke `/login` jika unauthenticated

### Out-of-Scope
- Bukan halaman operasional — tidak bisa membuat/mengubah pesanan dari sini
- Tidak ada API call ke backend — flow bersifat static
- Tidak ada filtering, search, atau export (PNG/PDF)
- Tidak ada chart library baru — visualisasi pakai div/border/SVG Tailwind saja
- Tidak ada perubahan ke order logic atau status transitions backend
- Tidak ada perubahan ke file di luar scope

---

## Architecture Constraints

- Layers this work may touch: `apps/web/src/components/Sidebar.tsx` (NAV_ITEMS), `apps/web/src/app/worktree/page.tsx` (new), `apps/web/src/components/WorktreeFlow.tsx` (new)
- Layers this work must NOT touch: backend API, order models, status transition logic, existing page components
- Patterns that must be followed: `'use client'` + `mx-auto max-w-6xl` + `PageHeader` + `Card` pattern
- Reuse: `Card, Badge, StatusBadge, PageHeader` from `@/components/ui`
- Icons: emoji (consistent dengan sidebar pattern), bukan SVG/lucide icons
- Architecture validation: PASS — no layer violations, reuses existing patterns, no new dependencies

---

## Dependencies

### Existing (to leverage)
- `Card` — container untuk flow nodes dan panel detail
- `StatusBadge` — mapping status ke label + warna (reuse existing color mapping)
- `Badge` — sub-label untuk role actor
- `PageHeader` — header halaman

### New (proposed)
none

---

## Stories + Scenarios

### Story 1: Worktree sidebar menu visible to all roles

**Rule 1: Semua role login melihat menu Worktree**
- Example A: outlet login → sidebar tampilkan "Worktree" setelah "Sales"
- Example B: finance login → sidebar tampilkan "Worktree" (finance tidak punya Sales, Worktree tetap muncul di posisi bawah list sebelum divider)

```gherkin
Scenario: Menu Worktree tampil untuk semua role
  Given user login dengan role apapun (outlet/admin/sales/driver/finance/platform_owner/supplier)
  When  sidebar rendered
  Then  menu "Worktree" dengan icon 🌳 muncul di sidebar
  And   href = /worktree

Scenario: User tidak login
  Given user mengakses /worktree tanpa token
  When  halaman dimuat
  Then  redirect ke /login
```

---

### Story 2: Flow diagram rendered dengan 6 tahap + 2 branch terminal

**Rule 1: 6 tahap utama ditampilkan berurutan**
- Tahap: Pemesanan → Persetujuan → Penugasan → Pengiriman → Pembayaran → Selesai

| Tahap | Backend Status | Badge Color | Role Actor | Deep Link |
|-------|---------------|-------------|------------|-----------|
| Pemesanan | New | yellow | outlet, sales | /orders |
| Persetujuan | Confirmed | blue | admin | /admin/orders |
| Penugasan | assigned | blue | admin, sales | /delivery |
| Pengiriman | in_progress → delivered | green | driver | /delivery |
| Pembayaran | Partially Paid → Paid | green | finance, admin, outlet | /payments |
| Selesai | Paid (final) | green (✅) | — | /orders |

**Rule 2: Branch terminal nodes**
- Cancelled (red badge): connector putus-putus dari Pemesanan, Persetujuan, Pembayaran
- Failed (red badge): connector putus-putus dari Pengiriman

**Rule 3: Connector visual antar tahap**
- Panah/connector berwarna abu-abu untuk tahap utama
- Connector putus-putus berwarna merah untuk branch terminal (Cancelled/Failed)

```gherkin
Scenario: Flow 6 tahap tampil lengkap
  Given user buka /worktree
  When  halaman selesai load
  Then  6 node tahap tampil berurutan dari kiri ke atas
  And   setiap node menampilkan: emoji icon, nama tahap, StatusBadge, deskripsi singkat
  And   connector antar tahap terlihat

Scenario: Branch Cancelled dan Failed tampil
  Given halaman Worktree tampil
  When  user lihat flow
  Then  node "Cancelled" (🔴) tampil di bawah flow utama
  And   connector putus-putus merah dari Pemesanan → Cancelled
  And   connector putus-putus merah dari Persetujuan → Cancelled
  And   connector putus-putus merah dari Pembayaran → Cancelled
  And   node "Gagal" (🔴) tampil di bawah flow
  And   connector putus-putus merah dari Pengiriman → Gagal
```

---

### Story 3: Highlight role user

**Rule 1: Highlight semua tahap di mana role user adalah actor**

| Role | Highlighted Stages |
|------|-------------------|
| outlet | Pemesanan |
| sales | Pemesanan, Penugasan |
| driver | Pengiriman |
| admin | Persetujuan, Penugasan, Pembayaran |
| finance | Pembayaran |
| supplier | None (no highlight) |
| platform_owner | None (no highlight) |

**Rule 2: Visual highlight style**
- Highlighted: border solid + bg-primary-50 + ring shadow (normal opacity)
- Non-highlighted: border dashed + bg-gray-50 + opacity-60

```gherkin
Scenario: Admin login — 3 tahap di-highlight
  Given user login sebagai admin
  When  halaman Worktree tampil
  Then  tahap "Persetujuan", "Penugasan", "Pembayaran" punya border solid + bg-primary-50
  And   tahap lain tampil dengan border dashed + opacity-60

Scenario: Supplier login — tidak ada highlight
  Given user login sebagai supplier
  When  halaman Worktree tampil
  Then  tidak ada tahap yang di-highlight
  And   semua tahap tampil dengan border solid + normal opacity (tanpa highlight khusus)
```

---

### Story 4: Klik step tampilkan panel detail

**Rule 1: Panel state management**
- Panel awal: tertutup
- Klik step yang sama kedua kali: toggle tutup
- Klik di luar panel: tutup panel
- Responsive: desktop → kanan (width ~320px), mobile → bawah (full-width)

**Rule 2: Panel content**
- Nama tahap + StatusBadge
- Role yang bertugas (Badge per role)
- Aksi yang dilakukan
- Status backend terkait
- Deskripsi singkat
- Tombol "Buka menu" → deep link ke halaman terkait

**Rule 3: Deep link access control**
- Jika role user tidak punya akses ke href tujuan → tombol "Buka menu" disembunyikan
- Mapping akses:

| Deep Link | Roles with access |
|-----------|-------------------|
| /orders | outlet, sales, admin, platform_owner |
| /admin/orders | admin, platform_owner |
| /delivery | admin, sales, driver, platform_owner |
| /payments | admin, finance, platform_owner |
| Selesai | /orders (same as Pemesanan) |

```gherkin
Scenario: Klik tahap Persetujuan tampilkan panel detail
  Given halaman Worktree tampil, panel tertutup
  When  user klik node "Persetujuan"
  Then  panel detail muncul di kanan (desktop) atau bawah (mobile)
  And   panel berisi: "Persetujuan", StatusBadge Confirmed 🔵, "Admin", "Approve order dari outlet", tombol "Buka menu" → /admin/orders

Scenario: Klik tahap yang sama lagi menutup panel
  Given panel detail "Persetujuan" terbuka
  When  user klik node "Persetujuan" lagi
  Then  panel detail tertutup

Scenario: Klik di luar panel menutup
  Given panel detail terbuka
  When  user klik area di luar panel
  Then  panel detail tertutup

Scenario: Outlet klik "Buka menu" di tahap Persetujuan — tombol hidden
  Given user login sebagai outlet
  And   panel detail "Persetujuan" terbuka
  Then  tombol "Buka menu" tidak tampil (outlet tidak punya akses /admin/orders)
```

---

### Story 5: Cancelled & Failed flow

**Rule 1: Cancelled branch dari 3 tahap**
- Connector putus-putus merah dari Pemesanan, Persetujuan, Pembayaran ke node Cancelled

**Rule 2: Failed branch dari Pengiriman**
- Connector putus-putus merah dari Pengiriman ke node Failed

```gherkin
Scenario: Cancelled node tampil sebagai terminal
  Given halaman Worktree tampil
  When  user lihat flow
  Then  node "Dibatalkan" (🔴) tampil di bawah flow utama
  And   ada connector putus-putus dari Pemesanan, Persetujuan, Pembayaran ke Dibatalkan
  And   node "Gagal" (🔴) tampil terpisah
  And   ada connector putus-putus dari Pengiriman ke Gagal
```

---

## Acceptance Criteria

```
Rule: Menu sidebar visible for all roles
  ✓ Given user login dengan role apapun, When sidebar rendered, Then menu "Worktree" 🌳 muncul
  ✓ Given user tidak login, When akses /worktree, Then redirect ke /login
  ✓ Given role finance (tidak punya Sales/Analitik), When sidebar rendered, Then Worktree tetap muncul di posisi bawah

Rule: Flow diagram 6 tahap + 2 terminal
  ✓ Given halaman /worktree dimuat, When render selesai, Then 6 tahap tampil berurutan: Pemesanan→Persetujuan→Penugasan→Pengiriman→Pembayaran→Selesai
  ✓ Given flow tampil, When user lihat, Then node Cancelled tampil dengan connector dari Pemesanan+Persetujuan+Pembayaran
  ✓ Given flow tampil, When user lihat, Then node Gagal tampil dengan connector dari Pengiriman

Rule: Role-based highlight
  ✓ Given admin login, When halaman tampil, Then Persetujuan+Penugasan+Pembayaran di-highlight (border solid + bg-primary-50)
  ✓ Given outlet login, When halaman tampil, Then hanya Pemesanan di-highlight
  ✓ Given supplier login, When halaman tampil, Then tidak ada tahap di-highlight
  ✓ Given non-highlighted tahap, When user lihat, Then border dashed + opacity-60

Rule: Panel detail interaction
  Given panel tertutup, When user klik step, Then panel buka di kanan (desktop) atau bawah (mobile)
  Given panel terbuka, When user klik step yang sama, Then panel tutup
  Given panel terbuka, When user klik di luar panel, Then panel tutup
  Panel berisi: nama tahap, StatusBadge, role actor (Badge), aksi, status backend, deskripsi, tombol "Buka menu"

Rule: Deep link access control
  ✓ Given outlet login + panel Persetujuan terbuka, When user lihat, Then tombol "Buka menu" tidak tampil
  ✓ Given admin login + panel Persetujuan terbuka, When user klik "Buka menu", Then navigasi ke /admin/orders
  ✓ Given driver login + panel Pengiriman terbuka, When user klik "Buka menu", Then navigasi ke /delivery

Rule: Responsive layout
  ✓ Given desktop viewport (≥1024px), When panel buka, Then panel di kanan (width ~320px)
  ✓ Given mobile viewport (<1024px), When panel buka, Then panel di bawah (full-width)
```

---

## Design Decision

**Chosen option:** Option A — Vertical flow + clickable node + slide-in panel (right/bottom responsive)

**Summary:** Diagram flow vertical berurutan dari atas ke bawah, setiap node berupa Card yang bisa diklik. Klik node → panel detail muncul di sebelah kanan (desktop) atau di bawah (mobile) dengan slide-in animation. Branch terminal (Cancelled/Failed) digambar di bawah flow utama dengan connector putus-putus merah. Reuse Card, Badge, StatusBadge existing.

**Rejected options:**
- Option B — Horizontal kanban lanes: rejected karena mobile responsiveness sulit, horizontal scroll tidak natural untuk flow sequential
- Option C — Timeline vertical status history: rejected karena hanya menampilkan urutan kronologis tanpa visualisasi parallel roles atau branch terminal

**Key tradeoffs accepted:**
- Vertical flow lebih mudah di-scroll di mobile dibanding horizontal
- Panel slide-in menggeser konten utama (bukan overlay) — lebih bersih tapi butuh layout shift handling
- Branch terminal di bawah flow utama — lebih mudah diimplementasi dibanding connector samping

---

## Open Questions / Assumptions

| Question | Resolution | Risk if Wrong |
|----------|------------|---------------|
| Supplier role tidak punya tahap highlight? | confirmed: no highlight untuk supplier | Supplier bingung melihat flow tanpa context |
| platform_owner tidak punya highlight? | confirmed: platform_owner view-only tanpa highlight | Acceptable — platform_owner memang tidak terlibat operasional |
| Selesai = Paid saja (bukan Delivered + Paid)? | confirmed: Selesai = Paid | User mungkin expect Selesai butuh delivery juga — minimal risk karena flow tetap menunjukkan Pengiriman sebelum Pembayaran |
| Redirect /login jika unauthenticated? | confirmed | User bisa akses halaman kosong tanpa context |
| Deep link tombol hidden jika no akses? | confirmed | Lebih baik daripada navigasi ke 403 |

---

## Rollback Plan

- Hapus file `apps/web/src/app/worktree/page.tsx`
- Hapus file `apps/web/src/components/WorktreeFlow.tsx`
- Hapus entry NAV_ITEMS dari `apps/web/src/components/Sidebar.tsx`
- Hapus test file `apps/web/src/app/worktree/worktree.test.tsx`
