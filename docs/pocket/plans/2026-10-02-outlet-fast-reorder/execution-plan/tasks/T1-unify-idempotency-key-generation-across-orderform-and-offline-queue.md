# Task T1 — Unify Idempotency Key Generation Across OrderForm and Offline Queue

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Unify Idempotency Key Generation Across OrderForm and Offline Queue [prereq]

## OBJECTIVE
Extract shared idempotency contract: fresh random key per order intent, retained across retries for same cart signature, reused from stored payload on offline replay. This ensures two intentional identical-content orders create two orders, while offline replay of the same queued intent creates one order.

Steps:
1. Write failing test for: fresh key per intent; same signature reuses key; different signature gets new key
   Test file: `apps/web/src/lib/offline/idempotency.test.ts`
   Level: unit
   Test intent: Given items payload / When `generateOrderIdempotencyKey` called with same signature twice / Then returns identical UUID; Given different items / When called / Then returns different UUID; Given same items with no signature / When called without signature twice / Then each returns distinct UUID (new intent)
   Exercise through: direct function import `generateOrderIdempotencyKey` and `canonicalSignature`
   Test doubles: none (pure function with optional signature param)
   Expected RED: functions `generateOrderIdempotencyKey` / `canonicalSignature` do not exist yet
2. Run test — verify FAIL: `npm run test -- apps/web/src/lib/offline/idempotency.test.ts`
3. Implement `generateOrderIdempotencyKey(signature?: string)` using `crypto.randomUUID()` (with JSDOM fallback). Export from `apps/web/src/lib/offline/idempotency.ts`. Also export `canonicalSignature(items)` for stable signature generation (sorted by product_id, JSON.stringify).
4. Write failing test for: OrderForm online submit uses shared key contract
   Test file: `apps/web/src/components/OrderForm.test.tsx`
   Level: unit
   Test intent: Given cart with items / When submitOrder online / Then `submitOutletOrder` receives idempotencyKey from `generateOrderIdempotencyKey(canonicalSignature(items))`; subsequent submit with same cart uses same key; new cart gets new key
   Exercise through: `OrderForm` component rendered with mock `submitOutletOrder`
   Test doubles: mock `submitOutletOrder` to capture key; mock `loadOrderFormProducts`; use `act` + RTL
   Expected RED: `OrderForm` uses inline randomUUID logic without shared util
5. Run test — verify FAIL: `npm run test -- apps/web/src/components/OrderForm.test.tsx`
6. Update `OrderForm.tsx` `submitOrder` to import and use `generateOrderIdempotencyKey` + `canonicalSignature` from `@/lib/offline/idempotency`; preserve `idempotencyAttempt` ref logic (reuse on same signature, clear after success/enqueue).
7. Write failing test for: order-queue flushQueue reuses stored key from payload
   Test file: `apps/web/src/lib/offline/order-queue.test.ts` (new or extend existing)
   Level: unit
   Test intent: Given queued payload containing `idempotency_key` / When flushQueue runs / Then send receives Idempotency-Key header matching payload's stored key (NOT recomputed from items)
   Exercise through: `createOrderQueue` with mocked adapter + `flushQueue`
   Test doubles: mocked `StorageAdapter`; mock `send` function capturing headers
   Expected RED: `order-queue.ts` `flushQueue` uses inline `idempotencyKey(payload)` hash function instead of reading from payload
8. Run test — verify FAIL: `npm run test -- apps/web/src/lib/offline/order-queue.test.ts`
9. Update `order-queue.ts`: remove inline `idempotencyKey()` hash function; in `flushQueue`, read `payload.idempotency_key` directly (with fallback to stored key field) and pass as `Idempotency-Key` header. Ensure `enqueue` stores payload with `idempotency_key` from caller.
10. Refactor: verify both test suites pass; no duplicate hash logic remains.
11. Commit: `feat(web): unify idempotency contract — fresh key per intent, reused on replay`

## REFERENCES LOADED
- Spec: `docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md` — Rules: Blocker prerequisite (idempotency key unification), Edge: Offline Reorder, Design Decision (Option A)
- Preflight: `apps/web/src/components/OrderForm.tsx` (lines 65–80, 180–210), `apps/web/src/lib/offline/order-queue.ts` (lines 25–45)
- Backend: `apps/api/app/Services/OrderCreationService.php` (`create` treats key as request identity, payload hash separately), `apps/api/app/Http/Requests/StoreOrderRequest.php` (payload-derived fallback), `apps/api/app/Http/Controllers/OrderController.php` (`requestIdentity` hashes outlet_id + client key)

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single shared module extraction; two call sites to update; per-intent fresh UUID retained for retries, replayed verbatim from stored payload. No new deps.

## SANDWICH CONTEXT
[CRITICAL: Idempotency key MUST be fresh per order intent (crypto.randomUUID), retained for retries with identical cart signature, and reused from stored payload on offline replay. Deterministic payload hash as key would WRONGLY dedupe intentional repeat orders.]
You are implementing T1 for Outlet Fast Reorder.
Spec: docs/pocket/spec/2026-10-02-outlet-reorder-rekomendasi/outlet-fast-reorder.md
Design decision: Option A — Client-Side Reorder Engine (no new API)
Files in scope: `apps/web/src/lib/offline/idempotency.ts` (new), `apps/web/src/components/OrderForm.tsx`, `apps/web/src/lib/offline/order-queue.ts`, test files
Available after: none (prereq)
Architecture rule: Fresh per-intent key via crypto.randomUUID; `canonicalSignature(items)` for stable signature; offline queue reads `payload.idempotency_key` directly for replay; key scoped to outlet via backend hashing.
[RESTATE: Key is fresh UUID per intent, stable across retries for same signature, reused from stored payload on offline replay — NO deterministic payload hash as the key.]

## DELIVERABLE
Given same items payload, When `generateOrderIdempotencyKey(signature)` called twice with same signature, Then returns identical UUID
Given different items, When called, Then returns different UUIDs
Given same items with no signature, When called without signature twice, Then returns distinct UUIDs (new intents)
Given OrderForm online submit with cart items, When `submitOrder` executes, Then `submitOutletOrder` receives key from shared util; same cart retries reuse key; new cart gets new key
Given offline queue flush with queued payload containing `idempotency_key`, When `flushQueue` replays, Then `send` receives Idempotency-Key header matching payload's stored key (not recomputed)

## QUALITY BAR
Must-have:
  - Single source of truth for idempotency contract (fresh key per intent, reused on retry/replay)
  - `generateOrderIdempotencyKey(signature?)` + `canonicalSignature(items)` exported
  - OrderForm reuses key for same signature, clears after success/enqueue
  - order-queue flush reads `payload.idempotency_key` directly (no recomputation)
  - Tests cover: key freshness per intent, retry reuse, replay reuse, both call sites integrated

Must-not-have:
  - Deterministic payload hash used as idempotency key (breaks intentional repeat orders)
  - Duplicate key logic in OrderForm and order-queue
  - Any new npm dependency

Open question risks:
  - Backend hashes `outlet_id + client_key` — client key must be unique per intent (UUID)

Rollback note:
  - Revert commit if duplicate order issues arise (intentional repeat orders deduplicated)

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS
  - New npm dependency added → STOP
  - `idempotencyKey(payload)` hash function remains in order-queue.ts

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, no out-of-scope files modified, both test suites green
Uncertain when: Backend rejects UUID format (should accept any string ≤255 chars); verify with StoreOrderRequest
Escalate when: Backend returns 422 idempotency conflict for intentional repeat orders with same items; or offline replay creates duplicate orders
