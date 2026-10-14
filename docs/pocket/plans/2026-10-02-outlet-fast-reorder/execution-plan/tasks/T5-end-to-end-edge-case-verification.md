# Task T5 — End-to-End & Edge-Case Verification

**Phase:** 1
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 5: End-to-End & Edge-Case Verification [depends: T4]

## OBJECTIVE
Verifikasi end-to-end dan edge-case: concurrent tabs stale cache (acceptable v1 behavior, refresh updates), offline reorder dengan mixed product states (cache-only, notif, queue), product state change race condition antara prefill dan submit (backend error per-item, valid items retained), accessibility keyboard/ARIA compliance. Menulis test E2E/Playwright atau integration yang mencakup skenario lintas tab, offline/online transition, dan race condition.

Steps:
1. Write failing test for: concurrent tabs stale cache (Edge)
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts` (Playwright)
   Level: E2E
   Test intent: Given two tabs open /orders / When Tab A submits new order successfully / Then Tab B reorder section shows stale data (no new favorite) until refresh / When Tab B refreshes / Then reorder section updated with new favorite
   Exercise through: Playwright multi-page context; real backend or seeded test DB
   Test doubles: Real network; test DB seeded via `scripts/e2e-seed.sh`
   Expected RED: assertion that Tab B remains stale until refresh, then updates after refresh, fails because reorder UI/cache behavior is not implemented yet.
2. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
3. Implement test scenario; verify stale cache behavior documented (acceptable v1 — no auto-sync).
3b. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (concurrent-tabs scenario)
4. Write failing test for: offline reorder with mixed product states (Edge)
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts`
   Level: E2E
   Test intent: Given offline / When click "Ulangi Pesanan Terakhir" with cached items (A active, B inactive since order, C stock 0, D exceed credit) / Then cart prefilled A only; notifications for B/C/D "berdasarkan data terakhir — validasi final saat online"; no network; items queued on submit
   Exercise through: Playwright offline mode (`page.setOffline(true)`); seeded cache via localStorage/IndexedDB
   Test doubles: Real IndexedDB; mock network offline
   Expected RED: assertion that only eligible cached items are prefilled, cache-only notice appears, no network request occurs, and queued payload carries unified idempotency key fails before offline reorder behavior is implemented.
5. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
5b. Implement offline test flow including queue persistence and replay behavior.
5c. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (offline scenario)
6. Write failing test for: product state change race condition (Edge)
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts`
   Level: E2E
   Test intent: Given online, reorder last order fills cart / Before submit, admin deactivates product B / When submit / Then backend returns per-item error "Produk B tidak aktif" / And valid items (A, C) remain in cart / And user can edit and re-submit
   Exercise through: Playwright + test DB manipulation (direct SQL or API) between prefill and submit
   Test doubles: Real backend; test DB
   Expected RED: assertion that server rejects newly inactive item while retaining valid cart items fails before race handling is implemented.
7. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
7b. Implement race condition test.
7c. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (race scenario)
8. Write failing test for: accessibility keyboard/ARIA (Edge) — automated aXe or manual checklist
   Test file: `apps/web/e2e/outlet-fast-reorder.spec.ts` or unit test in `OrderForm.test.tsx`
   Level: E2E / integration
   Test intent: Given keyboard-only navigation / When Tab through reorder section / Then all buttons reachable, Enter/Space triggers, aria-labels include guard status
   Exercise through: Playwright keyboard API or RTL focus/keyDown
   Test doubles: Same mocks as T4
   Expected RED: keyboard interaction or descriptive aria-label assertion fails before reorder controls are accessible.
9. Run test — verify FAIL: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts`
9b. Implement accessibility verification.
9c. Run test — verify PASS: `npm run test:e2e -- apps/web/e2e/outlet-fast-reorder.spec.ts` (accessibility scenario)
10. Refactor: consolidate test fixtures; ensure deterministic seeding.
11. Commit: `test(web): add edge-case and end-to-end verification for outlet fast reorder`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — All Edge scenarios (Concurrent Tabs, Offline Reorder, Race Condition, Accessibility)
- Preflight: `apps/web/e2e/siklus-pemesanan.spec.ts` (existing E2E pattern), `scripts/e2e-seed.sh`, `apps/web/src/components/OrderForm.offline.test.tsx` (offline test pattern)

## WHY THIS APPROACH
Complexity: standard
Justification: E2E tests require test DB, multi-tab Playwright, offline simulation, and backend state manipulation — separate from unit/integration tests. Covers cross-cutting edge cases that unit tests cannot.

## SANDWICH CONTEXT
[CRITICAL: E2E tests use real backend + test DB (seeded). Do NOT mock network in E2E. Stale cache behavior is acceptable v1 — test documents it, does not fix it. Offline test requires Playwright offline mode + IndexedDB and verifies queued idempotency key plus replay. Race condition test requires backend state mutation between prefill and submit.]
You are implementing T5 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine
Files in scope: `apps/web/e2e/outlet-fast-reorder.spec.ts` (new), `scripts/e2e-seed.sh` (may extend)
Available after: T4 (OrderForm fully integrated)
Architecture rule: E2E uses Playwright; test DB via `e2e-seed.sh`; no mocks for network
[RESTATE: E2E tests use real backend + test DB. Do NOT mock network in E2E. Stale cache = acceptable v1 (documented). Offline = Playwright offline + IndexedDB. Race = backend mutation between prefill/submit.]

## DELIVERABLE
Given two tabs, When order in Tab A, Then Tab B stale until refresh (documented acceptable)
Given offline, When reorder last order, Then cache-only prefill + "berdasarkan data terakhir" notice + queue on submit
Given race condition, When submit after admin change, Then per-item backend error, valid items retained
Given keyboard-only, When navigate reorder, Then all buttons reachable, Enter/Space works, aria-labels descriptive

## QUALITY BAR
Must-have:
  - All 4 Edge scenarios from spec have automated verification
  - E2E tests use real backend (seeded)
  - Tests are deterministic (fixed seeds, cleanup)

Must-not-have:
  - Flaky tests (timing-dependent)
  - Mocked network in E2E layer

Open question risks:
  - E2E environment availability in CI — if not available: NEEDS_CONTEXT (run locally only)

Rollback note:
  - If E2E tests flaky, mark `@skip` and run locally only

Red flags:
  - Mocking fetch in E2E → STOP
  - Test depends on timing/sleep → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: All Edge scenario tests pass (or documented skipped with reason), deterministic
Uncertain when: CI environment lacks Playwright / test DB
Escalate when: Race condition test cannot reliably trigger backend state change
