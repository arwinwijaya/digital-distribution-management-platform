# Pitch Exploration: outlet-request-map-dashboard
Date: 2026-09-26 | Project: digital-distribution-management-platform | Status: pitch-only

---

## Problem Statement
Admin tidak dapat melihat di dashboard lokasi outlet yang memiliki request/order barang, sehingga sulit memprioritaskan pemenuhan dan kunjungan lapangan berbasis wilayah.

## Root Tension
Operasional butuh data yang cukup segar untuk fulfillment, tetapi arsitektur data-intelligence yang ada mengutamakan konsistensi snapshot atomik — peta yang live namun divergen dari analytics lain lebih berbahaya daripada peta snapshot yang sedikit stale namun konsisten.

## Key Constraints
- Request barang berasal dari entity `Order` (`outlet_id` + status + timestamps); tidak ada entity request baru.
- Fitur read-only; tidak ada jalur mutasi baru.
- Reuse `GeoMap.tsx` (Leaflet), `GeographicAnalyticsService`, koordinat `outlets.lat/long`, dan pola envelope `{status,data}`.
- Marker diagregasi per outlet, bukan per order; butuh clustering pada skala ratusan marker.
- Filter minimal: status order + periode; default fokus order aktif (opsi user: 3 = keduanya, default aktif + filter).
- Koordinat invalid/null tidak boleh dirender (guard Null Island sudah ada di backend + frontend).
- Data dilindungi RBAC (`data_intelligence:read` / `analytics:read`); admin-only untuk geographic endpoint saat ini.
- Dummy mode parity wajib (`withDummyRead` / fixture `geographic.map_points`).
- Tidak ada dependency frontend baru tanpa alasan kuat (PWA harus tetap ringan).
- Endpoint existing: `GET /admin/analytics/geographic` → `{table, map_points, snapshot_version, window}`.

---

## Brainstorming Methods Used

### Question Storming — deep
Key insights:
- Definisi "request barang" terkunci: default order aktif, filterable status + periode (pilihan user #3).
- Unknown terbesar: kualitas koordinat (% outlet dengan lat/long valid) — menentukan empty-state vs fallback centroid.
- Tradeoff inti snapshot vs live (Q5): snapshot konsisten, live segar namun divergen.
- Skala marker menentukan kebutuhan clustering; outlet berbagi koordinat butuh penanganan stacked marker.
- Penempatan surface (analytics vs data-intelligence vs route baru) dan target klik (popover/drawer/navigasi) belum diputuskan.

### First Principles Thinking — creative
Key insights:
- Outlet sudah menyimpan lat/long; Order sudah membawa outlet_id + status + timestamp.
- GeoMap + GeographicAnalyticsService sudah ada — ini masalah projection + render, bukan subsistem baru.
- Primitif inti hanya satu join: `outlets ⋈ filtered orders → points + aggregate payload`.
- Tidak butuh API baru secara prinsip; section pipeline geographic yang ada mungkin cukup diperluas.
- Tidak butuh library peta baru; Leaflet sudah terpasang dan dipakai dua halaman.

### Six Thinking Hats — structured
Key insights:
- White: pipeline mempublish snapshot atomik immutable; status eligible saat ini `New, Confirmed, Delivered, Partially Paid` (window 30d).
- Red: admin ingin "sekilas mana yang panas"; sales bisa merasa diawasi oleh peta per-territory.
- Yellow: hotspot detection instan, coverage gap terlihat, triage fulfillment lebih cepat, murah dibangun.
- Black: koordinat kosong → peta nyaris kosong; staleness disalahartikan live; kebocoran RBAC; ratusan marker mentah → janky; ambiguitas "no orders" vs "no data".
- Green: cluster per territory, centroid fallback, status = warna + legenda, period chips/slider, klik → drawer breakdown produk + link order list.
- Blue: konfirmasi problem → spike GeoMap → pitch → grind; tetap read-only + snapshot-based + reuse-first.

### Reverse Brainstorming — creative
Key insights:
- Kegagalan spektakuler yang harus dihindari: Null Island stacking, framing stale-sebagai-live, marker per-order (ribuan DOM), skip RBAC, duplikasi logika query order, 8 warna status mirip, tanpa empty state, dependency peta berat, bounds hardcoded Jakarta-only.
- Inversi dari daftar di atas menjadi requirements: null-island guard, freshness label, cluster/agregasi, RBAC endpoint, single source of truth, legenda + counts, empty state berbeda, render dependency-free, bounds dari data.

---

## Advisor Synthesis
Advisor mengonfirmasi ini adalah projection + render dengan zero new mutation paths: GeoMap.tsx + GeographicAnalyticsService.produce() sudah ada, pilihan user "3" mengunci scope ke join Order-outlet, risiko terbesar adalah kualitas koordinat, dan tradeoff snapshot-vs-live sebaiknya default snapshot + freshness label eksplisit. Semua metode konvergen ke reuse-first, read-only, RBAC-gated, dependency-free; failure modes terinversi bersih menjadi requirements.

---

## Spike Results

**Unknown resolved:** kapabilitas GeoMap.tsx
**Finding:** Leaflet + marker + popup + validasi koordinat sudah ada; belum ada clustering; popup hanya ringkasan (nama, territory, jumlah order, sales); dipakai di `/data-intelligence` dan `/admin/tracking`.
**Implication:** butuh ekstensi marker (status color/badge count) + drawer detail; clustering dibutuhkan bila marker ratusan.

**Unknown resolved:** kualitas koordinat outlet
**Finding:** `Outlet` punya `latitude`/`longitude` (float, nullable); backend `isValidCoordinate()` dan frontend `isValidPoint()` sudah memfilter null/out-of-range; % outlet valid belum diukur dari repo.
**Implication:** UI wajib membedakan "tidak ada request" vs "request ada tapi koordinat invalid" vs "data belum tersedia"; pertimbangkan centroid territory sebagai fallback.

**Unknown resolved:** bentuk snapshot + status order
**Finding:** `GeographicAnalyticsService::produce()` agregat 30d per outlet (`orders`, `sales`) + ringkasan per territory; controller `GeographicAnalyticsController::index` admin-only memancarkan `{table, map_points, snapshot_version, window}`; status eligible `New/Confirmed/Delivered/Partially Paid`; payload map_points belum ada detail produk/status-per-order/request-terbaru.
**Implication:** butuh field tambahan (status breakdown, latest request, product summary) atau query detail terpisah; filter status/periode belum ada di endpoint.

---

## Approach Directions

### Direction A: Snapshot-First
Perluas endpoint snapshot `/admin/analytics/geographic` dengan query `?status=&start=&end=`; pipeline stage tetap baseline 30d, endpoint memfilter in-memory atau memanggil `produce()` dengan window custom; GeoMap + filter chips + drawer detail.
+ Konsisten dengan dashboard analytics lain; tanpa controller baru; dummy parity otomatis; RBAC/version/window sudah ditangani.
− Snapshot bisa stale; butuh cache/invalidation bila window di luar snapshot; filter hanya pada data tersnapshot.

### Direction B: Hybrid Snapshot Overview + Live Detail
Peta + ringkasan dari snapshot; klik marker → drawer memanggil live `/admin/orders?outlet_id=&status=&start=&end=` untuk detail request terbaru/produk/jumlah/status.
+ Peta konsisten & cepat; detail selalu fresh; reuse OrderController/Service.
− Dua sumber bisa tidak sinkron; butuh filter outlet+status di endpoint order; state loading drawer menambah kompleksitas UX.

### Direction C: Live Query-First
Endpoint baru `/admin/map/outlet-requests` memfilter order langsung dari DB dengan status/periode; snapshot geographic hanya legacy.
+ Selalu live, cocok untuk fulfillment; filter eksak tanpa batas 30d.
− Menyimpang dari pola snapshot-based; butuh endpoint + pagination + caching + RBAC terpisah; risiko divergensi analytics; mungkin butuh index DB.

---

## Open Questions for pocket-grinding
- [ ] Penempatan surface: `/analytics`, `/data-intelligence`, atau route baru? (menentukan menu/RBAC key)
- [ ] Target klik marker: popover, drawer, atau navigasi ke order list? Detail apa yang wajib di drawer (produk, qty, status, waktu)?
- [ ] Definisi "aktif": status mana yang default (New saja vs New+Confirmed+Packed)? Apakah Delivered/Partially Paid masuk default atau hanya via filter?
- [ ] Window default: hari ini / 7d / 30d? Apakah snapshot 30d cukup atau butuh window custom?
- [ ] Skala: berapa outlet/marker realistis (50 vs 5000)? Apakah clustering wajib di v1?
- [ ] Fallback koordinat invalid: sembunyikan + tampilkan count, atau centroid territory?
- [ ] Siapa yang boleh melihat: admin saja (seperti endpoint geographic saat ini) atau sales territory-scoped juga?
- [ ] Freshness label: format apa (snapshot version + window + "diperbarui X lalu")?
- [ ] Bounds peta: dari data (fitBounds) atau default Jabodetabek? Bagaimana untuk outlet di luar region?
- [ ] Dummy parity: fixture geografis yang ada (40–60 map_points bbox Jabodetabek) cukup atau butuh skenario status/periode?

---

## Recommended Direction
Direction A — karena paling konsisten dengan arsitektur snapshot-based yang terbukti, paling sedikit kode baru (filter + drawer), dummy parity otomatis, dan reuse GeoMap + GeographicAnalyticsController yang ada; Direction B dapat menjadi fase lanjutan bila operasional butuh detail live.

---

## Handoff Context (for pocket-grinding)
When pocket-grinding reads this doc:
- Start with this problem statement (Phase 1 context)
- Use Direction A as the working hypothesis for Phase 5 Design Proposals
- Treat Open Questions above as Phase 3 Discovery targets
- Do NOT treat Approach Directions as final architecture — validate through GWT first
