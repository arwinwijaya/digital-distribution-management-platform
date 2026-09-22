# Task T10 — Service worker + registrasi + halaman offline + indikator Topbar

**Phase:** 3
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 10: Service worker + offline shell [prereq]

## OBJECTIVE
Tambahkan service worker (app shell cache + offline fallback), registrasi di client,
halaman `/offline`, dan indikator online/offline di `Topbar`. Dinonaktifkan via
`NEXT_PUBLIC_PWA_ENABLED`.

Steps:
1. Write failing test for: registrasi hanya bila flag ON.
   Test file: `apps/web/src/lib/pwa/register.test.ts`
   Level: unit
   Test intent: Given `NEXT_PUBLIC_PWA_ENABLED=true` + `navigator.serviceWorker` mock /
   When `registerServiceWorker()` / Then `register('/sw.js')` dipanggil sekali; Given flag
   false / Then tidak memanggil.
   Exercise through: `registerServiceWorker()`.
   Test doubles: mock `navigator.serviceWorker`, mock env.
   Expected RED: modul belum ada.
2. Run test — verify FAIL: `cd apps/web && npx jest src/lib/pwa/register.test.ts`
3. Implement register + `public/sw.js` + `/offline` page → PASS → refactor → commit.

4. Write failing test for: indikator Topbar.
   Test intent: Given `navigator.onLine=false` / When render Topbar / Then badge "Offline"
   tampil; When `online` event fired / Then badge hilang.
   Test file: `apps/web/src/components/Topbar.test.tsx` (EXISTING — perluas; jangan hapus test dummy toggle yang sudah ada)
   Test doubles: mock `window.addEventListener('online'/'offline')`.
5. Run test — verify FAIL → implement hook `useOnlineStatus` + badge → PASS → commit.

## REFERENCES LOADED
Spec Phase 8 — AC-6, Rollback (flag `NEXT_PUBLIC_PWA_ENABLED`); `manifest.ts` existing.

## WHY THIS APPROACH
Complexity: medium
Justification: service worker manual (tanpa `next-pwa`) agar terkontrol & testable; flag
untuk rollback mudah.

## SANDWICH CONTEXT
[CRITICAL: SW tidak boleh meng-cache respons API POST; hanya app shell/statis; hormati flag]
Files in scope: `apps/web/public/sw.js`, `apps/web/src/lib/pwa/register.ts`, `apps/web/src/app/offline/page.tsx`, `apps/web/src/components/Topbar.tsx`, `apps/web/src/hooks/useOnlineStatus.ts`, `apps/web/src/components/Topbar.test.tsx` (perluas), test.
Available after: none (prereq).
Architecture rule: cache-first untuk aset statis, network-first untuk navigasi; skip cache untuk `/api/`.

## DELIVERABLE
- `public/sw.js`: install cache app shell, fetch handler skip `/api/`, offline fallback `/offline`.
- `registerServiceWorker()` idempotent, guard flag + `typeof navigator`.
- Halaman `/offline` minimal.
- Badge online/offline di Topbar.
Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - SW tidak mengganggu request API/order.
  - Registrasi tidak dijalankan di server (`typeof window`).
Must-not-have:
  - Menambah dependency `next-pwa`/workbox tanpa alasan.
Open question risks:
  - Next.js 16 + SW: pastikan `sw.js` di `public/` disajikan di root scope.
Rollback note:
  - Set flag false → registrasi tak jalan; hapus `sw.js`.

## STOP CONDITIONS
Done when: register test + Topbar test PASS; `sw.js` disajikan; flag mematikan PWA.
Escalate when: SW scope/update strategy tidak jelas di Next 16.
