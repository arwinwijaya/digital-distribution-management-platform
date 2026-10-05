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

function randomKey(): string {
  if (typeof crypto !== 'undefined' && typeof (crypto as Crypto).randomUUID === 'function') {
    return (crypto as Crypto).randomUUID();
  }
  // JSDOM / non-secure fallback — still unique per intent
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
  if (cached) return cached;
  const key = randomKey();
  keyBySignature.set(signature, key);
  return key;
}

/** Release a cached signature so the next same-cart order is a new intent. */
export function clearOrderIdempotencyKey(signature?: string): void {
  if (signature === undefined) keyBySignature.clear();
  else keyBySignature.delete(signature);
}

