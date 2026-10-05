import { canonicalSignature, generateOrderIdempotencyKey } from '@/lib/offline/idempotency';

describe('order idempotency contract', () => {
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
});
