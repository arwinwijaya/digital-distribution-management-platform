/**
 * Shared idempotency contract for outlet orders (T1).
 *
 * Fresh random key per order intent, reused when the same cart signature is
 * retried, and replayed verbatim from the stored payload when the offline
 * queue flushes. `canonicalSignature` provides a stable string for a cart;
 * `generateOrderIdempotencyKey(signature)` memoizes a random UUID per
 * signature so retry reuse is guaranteed. Clear the memo after a successful
 * submit/enqueue so an intentional repeat order with identical items gets a
 * fresh key.
 */

export type OrderItemLike = { product_id: number; quantity: number };

const keyBySignature = new Map<string, string>();

/** Cap to prevent unbounded growth in long-lived SPA sessions. */
const MAX_ENTRIES = 1000;

function bytesToUuid(bytes: Uint8Array): string {
  // RFC 4122 v4: set version (0100) and variant (10xx) bits
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}

function randomKey(): string {
  if (typeof crypto !== 'undefined' && typeof (crypto as Crypto).randomUUID === 'function') {
    return (crypto as Crypto).randomUUID();
  }
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    return bytesToUuid(bytes);
  }
  // Non-secure fallback — still unique per intent (no crypto available)
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}-${Math.random()
    .toString(36)
    .slice(2, 10)}`;
}

/**
 * Stable signature for a cart — sorted by product_id so item order does not
 * change the key, then JSON-stringified.
 */
export function canonicalSignature(items: OrderItemLike[]): string {
  const sorted = [...items].sort((a, b) => a.product_id - b.product_id);
  return JSON.stringify(sorted.map((it) => ({ product_id: it.product_id, quantity: it.quantity })));
}

/**
 * Fresh random key per intent; reuses the same key when called again with
 * the same signature (retry), fresh key when called without a signature or
 * with a different signature. Uses `crypto.randomUUID()` with a fallback for
 * JSDOM.
 */
export function generateOrderIdempotencyKey(signature?: string): string {
  if (!signature) return randomKey();
  const cached = keyBySignature.get(signature);
  if (cached) {
    // LRU promotion: move to end so recently used entries survive eviction
    keyBySignature.delete(signature);
    keyBySignature.set(signature, cached);
    return cached;
  }
  const key = randomKey();
  if (keyBySignature.size >= MAX_ENTRIES) {
    const oldest = keyBySignature.keys().next().value as string | undefined;
    if (oldest !== undefined) keyBySignature.delete(oldest);
  }
  keyBySignature.set(signature, key);
  return key;
}

/** Release a cached signature so the next same-cart order is a new intent. */
export function clearOrderIdempotencyKey(signature?: string): void {
  if (signature === undefined) keyBySignature.clear();
  else keyBySignature.delete(signature);
}

/** Test isolation helper — clears the entire memo store. */
export function resetOrderIdempotencyKeyStore(): void {
  keyBySignature.clear();
}

