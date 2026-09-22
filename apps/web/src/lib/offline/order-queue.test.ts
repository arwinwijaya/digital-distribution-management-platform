/**
 * T11 — offline order queue (IndexedDB wrapper + flush).
 *
 * Pure unit tests using an in-memory StorageAdapter stub so they run without a
 * real IndexedDB. The queue exposes enqueue/list/remove/clear and a flush
 * function that calls a provided sender for each item and reports sent/failed.
 * Items that fail to send remain in the queue; successes are removed.
 */
import { createOrderQueue, type OrderQueue, type StorageAdapter } from '@/lib/offline/order-queue';

/** In-memory stub implementing StorageAdapter. */
function createMemoryAdapter(): StorageAdapter {
  const store = new Map<string, string>();
  return {
    get: (key) => Promise.resolve(store.get(key) ?? null),
    set: (key, value) => { store.set(key, value); return Promise.resolve(); },
    delete: (key) => { store.delete(key); return Promise.resolve(); },
    keys: () => Promise.resolve(Array.from(store.keys())),
  };
}

describe('orderQueue', () => {
  let queue: OrderQueue;
  let adapter: StorageAdapter;

  beforeEach(() => {
    adapter = createMemoryAdapter();
    queue = createOrderQueue(adapter);
  });

  it('enqueue adds an item with a unique id and created_at', async () => {
    await queue.enqueue({ outlet_id: 1, items: [{ product_id: 5, quantity: 2 }] });

    const list = await queue.list();
    expect(list).toHaveLength(1);
    expect(list[0]).toMatchObject({
      payload: { outlet_id: 1, items: [{ product_id: 5, quantity: 2 }] },
    });
    expect(list[0].id).toBeTruthy();
    expect(list[0].created_at).toBeTruthy();
  });

  it('list returns items in FIFO order', async () => {
    await queue.enqueue({ outlet_id: 1, items: [] });
    await queue.enqueue({ outlet_id: 2, items: [] });

    const list = await queue.list();
    expect(list.map((i) => (i.payload as { outlet_id: number }).outlet_id)).toEqual([1, 2]);
  });

  it('remove deletes the item by id', async () => {
    await queue.enqueue({ outlet_id: 1, items: [] });
    const [first] = await queue.list();

    await queue.remove(first.id);
    expect(await queue.list()).toHaveLength(0);
  });

  it('clear empties the queue', async () => {
    await queue.enqueue({ outlet_id: 1, items: [] });
    await queue.enqueue({ outlet_id: 2, items: [] });

    await queue.clear();
    expect(await queue.list()).toHaveLength(0);
  });

  describe('flushQueue', () => {
    it('sends all items and clears them on success', async () => {
      await queue.enqueue({ outlet_id: 1, items: [{ product_id: 1, quantity: 1 }] });
      await queue.enqueue({ outlet_id: 2, items: [{ product_id: 2, quantity: 1 }] });

      const sent: unknown[] = [];
      const send = jest.fn().mockImplementation(async () => {
        sent.push({});
        return { ok: true, status: 201, json: async () => ({ ok: true }) };
      });

      const result = await queue.flushQueue(send);

      expect(send).toHaveBeenCalledTimes(2);
      expect(result.sent).toBe(2);
      expect(result.failed).toBe(0);
      expect(await queue.list()).toHaveLength(0);
    });

    it('keeps failed items in the queue and removes only successful ones', async () => {
      await queue.enqueue({ outlet_id: 1, items: [] }); // will succeed
      await queue.enqueue({ outlet_id: 2, items: [] }); // will fail
      const [okItem, failItem] = await queue.list();

      let call = 0;
      const send = jest.fn().mockImplementation(async () => {
        call++;
        if (call === 1) {
          return { ok: true, status: 201, json: async () => ({ ok: true }) };
        }
        return { ok: false, status: 500, json: async () => ({ message: 'error' }) };
      });

      const result = await queue.flushQueue(send);

      expect(send).toHaveBeenCalledTimes(2);
      expect(result.sent).toBe(1);
      expect(result.failed).toBe(1);

      const remaining = await queue.list();
      expect(remaining).toHaveLength(1);
      expect(remaining[0].id).toBe(failItem.id);
    });

    it('idempotency key is derived from payload and included in request headers', async () => {
      const payload = { outlet_id: 42, items: [{ product_id: 9, quantity: 3 }] };
      await queue.enqueue(payload);

      const capturedHeaders: Record<string, string> = {};
      const send = jest.fn().mockImplementation(async (_body: unknown, init?: RequestInit) => {
        if (init?.headers) {
          Object.entries(init.headers).forEach(([k, v]) => { capturedHeaders[k] = String(v); });
        }
        return { ok: true, status: 201, json: async () => ({ ok: true }) };
      });

      await queue.flushQueue(send);

      expect(capturedHeaders['Idempotency-Key']).toBeTruthy();
      expect(typeof capturedHeaders['Idempotency-Key']).toBe('string');
    });

    it('idempotency key is stable for identical payloads', async () => {
      const payload = { outlet_id: 1, items: [{ product_id: 1, quantity: 1 }] };
      await queue.enqueue(payload);
      await queue.enqueue(payload); // same payload again

      const keys: string[] = [];
      const send = jest.fn().mockImplementation(async (_body: unknown, init?: RequestInit) => {
        if (init?.headers) {
          const h = init.headers as Record<string, string>;
          keys.push(h['Idempotency-Key']);
        }
        return { ok: true, status: 201, json: async () => ({ ok: true }) };
      });

      await queue.flushQueue(send);

      expect(keys).toHaveLength(2);
      expect(keys[0]).toBe(keys[1]);
    });
  });
});
