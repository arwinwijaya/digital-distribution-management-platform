/**
 * Offline order queue (Phase 8, T11).
 *
 * Stores order payloads locally (IndexedDB via native wrapper + localStorage fallback)
 * and replays them via a provided `send` function when the app comes back online.
 * Uses an injectable StorageAdapter so tests don't need a real IndexedDB.
 */

/** Persistent key under which the queue array is stored. */
const QUEUE_KEY = 'ddp_offline_order_queue:v1';

/** Storage backend interface (injectable for tests). */
export interface StorageAdapter {
  get(key: string): Promise<string | null>;
  set(key: string, value: string): Promise<void>;
  delete(key: string): Promise<void>;
  keys(): Promise<string[]>;
}

/** A single queued order item. */
export interface QueuedOrder {
  id: string;
  payload: unknown;
  created_at: string; // ISO 8601
}

/** Result of a flush operation. */
export interface FlushResult {
  sent: number;
  failed: number;
}

/** High-level queue API. */
export interface OrderQueue {
  enqueue(payload: unknown): Promise<void>;
  list(): Promise<QueuedOrder[]>;
  remove(id: string): Promise<void>;
  clear(): Promise<void>;
  flushQueue(send: (payload: unknown, init?: RequestInit) => Promise<Response>): Promise<FlushResult>;
}

/** Generate a stable idempotency key from a payload (SHA-256 hex, first 32 chars). */
async function idempotencyKey(payload: unknown): Promise<string> {
  const json = JSON.stringify(payload);
  // Simple FNV-1a 64-bit hash (deterministic, no WebCrypto needed).
  let hash = 0xcbf29ce484222325;
  for (let i = 0; i < json.length; i++) {
    hash ^= json.charCodeAt(i);
    hash = (hash * 0x100000001b3) >>> 0;
  }
  return hash.toString(16).padStart(16, '0').slice(0, 32);
}

/** Simple UUID v4 generator (works in JSDOM without randomUUID). */
function generateId(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/** Default IndexedDB-backed adapter (native, no extra deps). */
export class IndexedDbAdapter implements StorageAdapter {
  private db: IDBDatabase | null = null;
  private readonly dbName = 'ddp_offline_queue';
  private readonly storeName = 'kv';

  private async open(): Promise<IDBDatabase> {
    if (this.db) return this.db;

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(this.storeName)) {
          db.createObjectStore(this.storeName);
        }
      };
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };
      request.onerror = () => reject(request.error);
    });
  }

  async get(key: string): Promise<string | null> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(this.storeName, 'readonly');
      const store = tx.objectStore(this.storeName);
      const req = store.get(key);
      req.onsuccess = () => resolve(req.result ?? null);
      req.onerror = () => reject(req.error);
    });
  }

  async set(key: string, value: string): Promise<void> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(this.storeName, 'readwrite');
      const store = tx.objectStore(this.storeName);
      const req = store.put(value, key);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  async delete(key: string): Promise<void> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(this.storeName, 'readwrite');
      const store = tx.objectStore(this.storeName);
      const req = store.delete(key);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  async keys(): Promise<string[]> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(this.storeName, 'readonly');
      const store = tx.objectStore(this.storeName);
      const req = store.getAllKeys();
      req.onsuccess = () => resolve(req.result as string[]);
      req.onerror = () => reject(req.error);
    });
  }
}

/** Fallback adapter using localStorage (synchronous but simple). */
export class LocalStorageAdapter implements StorageAdapter {
  async get(key: string): Promise<string | null> {
    try { return localStorage.getItem(key); } catch { return null; }
  }
  async set(key: string, value: string): Promise<void> {
    try { localStorage.setItem(key, value); } catch { /* ignore quota */ }
  }
  async delete(key: string): Promise<void> {
    try { localStorage.removeItem(key); } catch { /* ignore */ }
  }
  async keys(): Promise<string[]> {
    try { return Object.keys(localStorage); } catch { return []; }
  }
}

/** Auto-detect best available adapter (IndexedDB → localStorage). */
export async function createDefaultAdapter(): Promise<StorageAdapter> {
  if (typeof indexedDB !== 'undefined') {
    try {
      const adapter = new IndexedDbAdapter();
      await adapter.keys(); // probe
      return adapter;
    } catch {
      // fall through
    }
  }
  return new LocalStorageAdapter();
}

/** Read the whole queue array from storage. */
async function readQueue(adapter: StorageAdapter): Promise<QueuedOrder[]> {
  const raw = await adapter.get(QUEUE_KEY);
  if (!raw) return [];
  try { return JSON.parse(raw) as QueuedOrder[]; } catch { return []; }
}

/** Write the whole queue array to storage. */
async function writeQueue(adapter: StorageAdapter, queue: QueuedOrder[]): Promise<void> {
  await adapter.set(QUEUE_KEY, JSON.stringify(queue));
}

/** Create a queue instance backed by the given adapter. */
export function createOrderQueue(adapter: StorageAdapter): OrderQueue {
  return {
    async enqueue(payload: unknown) {
      const queue = await readQueue(adapter);
      const item: QueuedOrder = {
        id: generateId(),
        payload,
        created_at: new Date().toISOString(),
      };
      queue.push(item);
      await writeQueue(adapter, queue);
    },

    async list() {
      const queue = await readQueue(adapter);
      return queue.sort((a, b) => a.created_at.localeCompare(b.created_at));
    },

    async remove(id: string) {
      const queue = await readQueue(adapter);
      const filtered = queue.filter((item) => item.id !== id);
      await writeQueue(adapter, filtered);
    },

    async clear() {
      await writeQueue(adapter, []);
    },

    async flushQueue(send) {
      const queue = await readQueue(adapter);
      let sent = 0;
      let failed = 0;
      const remaining: QueuedOrder[] = [];

      for (const item of queue) {
        const key = await idempotencyKey(item.payload);
        try {
          const response = await send(item.payload, {
            headers: { 'Idempotency-Key': key },
          });
          // response is a Response-like object with ok/status
          if (response.status >= 200 && response.status < 300) {
            sent++;
          } else {
            failed++;
            remaining.push(item);
          }
        } catch {
          failed++;
          remaining.push(item);
        }
      }

      if (remaining.length !== queue.length) {
        await writeQueue(adapter, remaining);
      }

      return { sent, failed };
    },
  };
}