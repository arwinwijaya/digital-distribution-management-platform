import { canonicalSignature, generateOrderIdempotencyKey, resetOrderIdempotencyKeyStore } from '@/lib/offline/idempotency';

describe('order idempotency contract', () => {
  beforeEach(() => {
    resetOrderIdempotencyKeyStore();
  });
  afterEach(() => {
    resetOrderIdempotencyKeyStore();
  });

  it('generates a fresh key for each intent without a signature', () => {
    expect(generateOrderIdempotencyKey()).not.toBe(generateOrderIdempotencyKey());
  });

  it('reuses a key for the same signature', () => {
    const signature = canonicalSignature([{ product_id: 2, quantity: 1 }, { product_id: 1, quantity: 3 }]);

    expect(generateOrderIdempotencyKey(signature)).toBe(generateOrderIdempotencyKey(signature));
  });

  it('generates a new key for a different signature', () => {
    const first = canonicalSignature([{ product_id: 1, quantity: 1 }]);
    const second = canonicalSignature([{ product_id: 1, quantity: 2 }]);

    expect(generateOrderIdempotencyKey(first)).not.toBe(generateOrderIdempotencyKey(second));
  });

  it('canonicalizes items independent of input order', () => {
    expect(canonicalSignature([{ product_id: 2, quantity: 1 }, { product_id: 1, quantity: 3 }]))
      .toBe(canonicalSignature([{ product_id: 1, quantity: 3 }, { product_id: 2, quantity: 1 }]));
  });

  it('LRU eviction caps store at 1000 entries and drops the oldest', () => {
    const first = generateOrderIdempotencyKey('sig-0');
    for (let i = 1; i < 1000; i++) generateOrderIdempotencyKey(`sig-${i}`);

    // 1001st distinct signature evicts the oldest (sig-0)
    generateOrderIdempotencyKey('sig-1000');

    // sig-0 was evicted — a fresh key is now generated
    expect(generateOrderIdempotencyKey('sig-0')).not.toBe(first);
    // sig-999 is still cached — same key returned
    const last = generateOrderIdempotencyKey('sig-999');
    expect(generateOrderIdempotencyKey('sig-999')).toBe(last);
  });

  it('LRU promotion keeps recently used entries from eviction', () => {
    for (let i = 0; i < 1000; i++) generateOrderIdempotencyKey(`sig-${i}`);
    const originalSig2 = generateOrderIdempotencyKey('sig-2');
    const originalSig0 = generateOrderIdempotencyKey('sig-0');
    // Promoting sig-0 keeps it in cache; inserting a new entry evicts sig-1.
    generateOrderIdempotencyKey('sig-1000');
    expect(generateOrderIdempotencyKey('sig-0')).toBe(originalSig0);
    expect(generateOrderIdempotencyKey('sig-2')).toBe(originalSig2);
  });

  it('fallback produces RFC4122-shaped UUID v4 when randomUUID is unavailable', () => {
    // JSDOM has getRandomValues but not randomUUID.
    expect(typeof crypto.randomUUID).toBe('undefined');
    const getRandomValues = jest.spyOn(crypto, 'getRandomValues');
    try {
      const key = generateOrderIdempotencyKey();
      expect(getRandomValues).toHaveBeenCalledTimes(1);
      // RFC4122 v4 pattern: 8-4-4-4-12 hex, version nibble = 4, variant nibble = 8/9/a/b
      expect(key).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    } finally {
      getRandomValues.mockRestore();
    }
  });
});
