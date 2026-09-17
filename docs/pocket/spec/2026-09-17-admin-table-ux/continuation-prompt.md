# Continuation Prompt — Admin Table Readability (pocket-grinding → pocket-planning)

> Prompt ini melanjutkan sesi yang terhenti di tengah `pocket-grinding` Phase 4→5 (setelah edge case hunter).
> Tempel ke sesi baru agar pekerjaan lanjut dari state yang benar.

---

## Konteks Proyek

- **Repo:** `D:/Development/amal/digital-distribution-management-platform` (monorepo: `apps/web` Next.js 16 + `apps/api` Laravel 11).
- **Skill aktif:** `pocket-grinding` (sedang berjalan) → lalu `pocket-planning` setelah spec disetujui.
- **Dokumen yang sudah ada:**
  - `docs/pocket/spec/2026-09-17-admin-table-ux/pitch-exploration.md` (hasil pocket-pitching)
  - `docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md` (spec draft)

## Fitur yang sedang dispec

Meningkatkan keterbacaan **semua 6 tabel admin** (`outlets`, `products`, `users`, `promotions`, `sales-performance`, `orders`) lewat: default sort terbaru-terlama, paging UI berorientasi ("Halaman X dari Y · N data"), summary strip (total + breakdown), kolom `created_at`/`updated_at`, row density toggle, dan sort manual via klik header.

**Scope sudah dikonfirmasi user.** User juga sudah bilang: *"pilih yang recommended jawabannya dan lanjut ke pocket planning."*

---

## State Saat Ini

1. Phase 1–2 (context scan + scope) selesai dan dikonfirmasi.
2. Phase 3 (Three Amigos) dan Phase 4 (GWT scenarios) sudah ditulis ke `admin-table-readability.md`.
3. **Edge case hunter sudah di-dispatch dan selesai → `Status: Needs Clarification`** dengan **10 Blocking Clarifications** (teks lengkap ada di bawah).

## Tugas Anda (lanjut dari sini)

### Langkah 1 — Resolve 10 blocking clarifications dengan default rekomendasi ini (jangan tanya user lagi, user sudah minta pakai rekomendasi):

1. **Cursor semantics** — Standardisasi **offset cursor** untuk SEMUA 6 tabel (`cursor=0`, `limit`, `2*limit`, …). Jump ke halaman 3 = `cursor=(3-1)*limit`. Tradeoff (skip/duplikat saat insert konkuren) diterima karena data admin kecil (~48–100 row). Update controller `promotions`/`sales-performance` yang masih id-based cursor agar konsisten ke offset.

2. **Total shape + fallback** — Tambah `meta.total` (aditif) di SEMUA 6 controller, dihitung `COUNT(*)` dengan filter yang sama persis dengan `index`. `has_more` tetap dipertahankan. Frontend pakai `meta.total`; bila `total` tidak ada → label estimasi ("Halaman 2 · ada data lain").

3. **Sort × filter × page reset** — Aturan: **ganti sort ATAU ganti filter → reset `cursor=0`**, param lain dipertahankan. **Ganti halaman → sort + filter dipertahankan.** Tulis ini sebagai acceptance criterion tambahan.

4. **Sort allowlist per controller** — Invalid/berbahaya (`sort=__proto__`, `sort=; DROP`) → **fallback diam ke default `created_at DESC, id DESC`, HTTP 200** (bukan 422/500). Allowlist:
   - outlets: `created_at, updated_at, name, id, category, score`
   - products: `created_at, updated_at, name, sku, price, stock_quantity, id`
   - users: `created_at, updated_at, name, email, role, id`
   - promotions: `created_at, updated_at, start_date, end_date, id`
   - orders: `created_at, updated_at, order_id, status, total_amount, id`
   - sales-performance: `achievement, name, period, id`

5. **Nulls-last dua arah** — Timestamp kosong dikoersi ke `NULL`. Ordering pakai **`NULLS LAST` di kedua arah** (Postgres: `ORDER BY created_at DESC NULLS LAST, id DESC`; MySQL: DESC sudah null-last secara default — dokumentasikan). Frontend juga defensif: null selalu di bawah pada asc maupun desc.

6. **Summary strip source** — Backend return `meta.total` + `meta.summary` (breakdown agregat dengan filter yang sama). Contoh outlet: `summary: { active: 32, inactive: 16 }`. Halaman tanpa breakdown natural (users/orders) → summary cukup total (atau breakdown role/status bila murah).

7. **Dummy-mode parity** — `apps/web/src/app/admin/*/api.ts` dummy branch + helper `listDummy*` WAJIB implementasi sort/order/total yang identik: urutkan `created_at DESC` (fallback `id DESC`), offset slice sama, `total = filtered.length`, allowlist sama. Harus tetap zero-network dan lolos `dummy-guard.test.ts` + `dummy-mode.e2e.test.tsx`.

8. **Table.tsx generic contract** — Sortable state tinggal di `Table` via props opsional: `sortableColumns?: string[]`, `sort?: { column, direction }`, `onSort?: (k) => void`. Kolom `Aksi` tidak masuk `sortableColumns` (inert). `th` sortable render `aria-sort`. Halaman `orders` (fetch inline tanpa api.ts) tetap pakai props yang sama dengan local sort state.

9. **Authorization** — Param `sort`/`order`/`total` **inherit guard yang sudah ada** di tiap endpoint (tidak ada aturan baru). Unauthorized → perilaku 401/403 seperti sekarang, tidak berubah.

10. **Density persistence + SSR hydration** — Initial density = **Default** di server render; baca `localStorage` (`admin:table-density`) hanya di `useEffect` setelah mount (mirror pola `readPersistedFlag` di `dummy/store.ts`) agar tidak hydration mismatch. Viewport sempit = scroll horizontal di dalam `overflow-x-auto` card (bukan page scroll).

### Langkah 2 — Update spec

Edit `docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md`:
- Masukkan resolusi 10 poin di atas ke bagian Stories/Scenarios, Architecture Constraints, dan Open Questions (tandai sebagai resolved).
- Tambahkan acceptance criteria hasil R1–R8 (scenario rekomendasi edge case hunter) yang relevan, terutama: sort×filter×page reset, nulls-last dua arah, cursor beyond total, empty filter summary "0".

### Langkah 3 — Selesaikan pocket-grinding

- **Phase 5** (Design Proposals): dokumentasikan Option B (Full Paging) — sudah dipilih, tinggal tuliskan tradeoffs + alasan vs Option A/C.
- **Phase 6** (Architecture Validation): isi checklist, pastikan PASS (tidak menyentuh pipeline Order/Invoice/Payment, Auth, schema DB).
- **Phase 7** (Handoff): set status spec jadi `approved`, lalu **invoke `pocket-planning`** dengan spec path + acceptance criteria + design decision.

### Langkah 4 — Invoke pocket-planning

Jangan berhenti di "spec written". Muat skill `pocket-planning`, mulai Phase 0 (preflight codebase scan), dan ikuti penuh sampai menghasilkan execution plan di `docs/pocket/plans/2026-09-17-admin-table-ux/execution-plan.md`.

---

## Teks Lengkap 10 Blocking Clarifications (dari edge case hunter)

1. **UX/API — cursor semantics:** outlet pakai offset cursor, promotions/sales-performance pakai id-based cursor, users/orders/products tanpa cursor. Apa kontrak cursor tunggal untuk jump paging (offset vs id)?
2. **UX/API — total shape + fallback:** `data.meta.total` vs `data.total` vs `meta.total`? Apakah semua 6 controller wajib tambah `total`, atau sebagian estimate-only?
3. **State transitions — sort × filter × page:** apa yang di-reset saat toggle sort / ubah filter / jump page? Haruskah `cursor=0` saat sort/filter berubah dan sort+filter dipertahankan saat pindah halaman?
4. **Boundary — sort allowlist per controller:** kolom mana yang di-whitelist per controller? Apa fallback saat `?sort=__proto__` / `sort=; DROP` (silent default tanpa 500/422)?
5. **Data integrity — nulls-last dua arah:** SQL `ORDER BY created_at DESC NULLS LAST, id DESC` (Postgres nulls-first by default di DESC). Apakah API harus koersi timestamp kosong → NULL dan jamin nulls-last di kedua arah?
6. **Data integrity — summary strip source:** summary dihitung dari panjang `data` paginated atau `COUNT(*) WHERE <filter>` terpisah? Perlu query/endpoint sendiri per halaman?
7. **State transitions — dummy-mode parity:** dummy `api.ts` + `listDummy*` harus implement sort/order/total identik atau guard/test pecah. Perilaku sort/total apa yang wajib?
8. **Architecture — Table.tsx generic contract:** sortable state di `Table` atau di tiap `page.tsx`? Prop shape apa yang tetap generic sementara `orders` fetch inline tanpa api.ts?
9. **Authorization — admin boundary untuk param baru:** apakah sort/total inherit guard yang sama? Apa yang didapat caller unauthorized di `?sort=created_at` (403/401/fallback)? Relevan karena `orders` di `OrderController` punya `deny.finance` middleware.
10. **Boundary — density + SSR hydration:** initial density sebelum hydration? Bagaimana hindari flash/hydration mismatch (localStorage undefined di server)? Aturan "no horizontal overflow" di viewport sempit?

---

## Referensi Skill (path absolut)

- `C:\Users\muhamad.arwinwijaya\.pi\agent\npm\node_modules\pocketto-pi\skills\pocket-grinding\SKILL.md`
- `C:\Users\muhamad.arwinwijaya\.pi\agent\npm\node_modules\pocketto-pi\skills\pocket-planning\SKILL.md`
- Spec: `D:\Development\amal\digital-distribution-management-platform\docs\pocket\spec\2026-09-17-admin-table-ux\admin-table-readability.md`
