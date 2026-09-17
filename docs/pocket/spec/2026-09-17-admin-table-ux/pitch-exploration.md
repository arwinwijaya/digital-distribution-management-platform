# Admin Table UX — Readability & Orientation

**Date:** 2026-09-17
**Status:** draft
**Scope confirmed:** yes
**Problem:** Admin sulit membaca tabel karena tidak ada urutan default (terbaru-terlama), tidak ada paging UI, dan tidak ada ringkasan awal.

---

## Context

### Stack
- Next.js 16 + React 18 + Tailwind CSS
- `Table` component di `components/ui/Table.tsx` — generic, reusable, no paging/density built-in
- 6 admin pages: outlets, products, users, promotions, sales-performance, orders

### Current State (from code scan)
- API layer: cursor-based + `limit` (15 atau 20), `hasMore` boolean returned
- Frontend: `hasMore` hanya tampilkan teks "Ada data lebih lanjut" — **tidak ada paging UI**
- Default sort: **belum konsisten** — outlets array order, promotions by start_date ASC, users by ID, sales-performance by achievement DESC
- `created_at` / `updated_at` sudah ada di semua entitas tapi **tidak ditampilkan di kolom**
- Table component: no density toggle, no sticky header, no row count display

---

## Brainstorm Synthesis (5 methods)

### Key Insights
1. Default sort harus terbaru-terlama — data terbaru paling berharga secara operasional
2. Summary strip di atas tabel — orientasi instan ("48 outlet, 32 aktif")
3. Paging harus menunjukkan posisi — "Halaman 1 dari 4"
4. Kepadatan harus bisa diatur — dense untuk power-user, spacious untuk admin baru
5. `created_at` / `updated_at` harus ditampilkan

### Patterns
- Semua role butuh orientasi cepat: berapa data, yang mana terbaru, di mana posisi
- Kegagalan utama bukan "terlalu sedikit fitur" tapi tidak ada default yang benar

---

## Approach Direction (Recommended)

**Direction B: Full Paging** — Default sort + summary strip + paging UI + row density toggle + kolom updated_at

### Scenario Preview
- Admin buka halaman outlets → data terbaru muncul pertama
- Admin lihat "48 outlet · 32 aktif · 16 nonaktif" di atas tabel
- Admin lihat "Halaman 1 dari 4 · 15 dari 48 data"
- Admin bisa switch antara "Compact / Default / Comfortable" density
- Admin bisa sort manual dari tabel header

---

## Ready for pocket-planning

Handoff ke pocket-grinding Phase 2+ untuk formalisasi scope, GWT scenarios, dan architecture validation.
