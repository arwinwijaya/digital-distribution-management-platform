import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';

export interface SalesPerformanceRow {
  user_id: number;
  name: string;
  email?: string;
  period: string;
  target: string | number;
  achievement: string | number;
  percentage: string | number;
  order_count: number;
}

// ── Dummy-mode helpers ──────────────────────────────────────────────────────

interface DummyOrderEntity {
  id: number;
  status: string;
  total_amount: string;
  created_at?: string;
}

/** Deterministic sales roster — mirrors the dummy user roster's sales role. */
const DUMMY_SALES_ROSTER = [
  { name: 'Budi Santoso', email: 'budi.santoso@ddp.local' },
  { name: 'Citra Lestari', email: 'citra.lestari@ddp.local' },
  { name: 'Rina Marlina', email: 'rina.marlina@ddp.local' },
  { name: 'Agus Setiawan', email: 'agus.setiawan@ddp.local' },
  { name: 'Siti Aminah', email: 'siti.aminah@ddp.local' },
  { name: 'Hendra Gunawan', email: 'hendra.gunawan@ddp.local' },
];

function dummyOrders(): DummyOrderEntity[] {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ orders: DummyOrderEntity[] }> | null;
  return entities?.orders ?? [];
}

function derivePeriod(orders: DummyOrderEntity[]): string {
  const latest = orders
    .map((o) => String(o.created_at ?? '').slice(0, 7))
    .filter((p) => p.length === 7)
    .sort()
    .pop();
  return latest ?? '2026-02';
}

function dummySalesPerformance(opts?: { period?: string; limit?: number; cursor?: number }): {
  rows: SalesPerformanceRow[];
  hasMore: boolean;
  nextCursor: number | null;
} {
  const orders = dummyOrders();
  const period = opts?.period ?? derivePeriod(orders);
  const active = orders.filter((o) => o.status !== 'cancelled');
  const inPeriod = active.filter((o) => String(o.created_at ?? '').startsWith(period));
  const source = inPeriod.length > 0 ? inPeriod : active;

  const buckets = DUMMY_SALES_ROSTER.map(() => ({ orders: 0, amount: 0 }));
  for (const order of source) {
    const idx = Math.abs(order.id) % DUMMY_SALES_ROSTER.length;
    const amount = Number(String(order.total_amount).replace(/[^0-9.]/g, '')) || 0;
    buckets[idx].orders += 1;
    buckets[idx].amount += amount;
  }

  const rows: SalesPerformanceRow[] = DUMMY_SALES_ROSTER.map((sales, i) => {
    const target = Math.max(1, Math.round(buckets[i].amount / 0.8));
    return {
      user_id: i + 1,
      name: sales.name,
      email: sales.email,
      period,
      target: String(target),
      achievement: buckets[i].amount.toFixed(2),
      percentage: ((buckets[i].amount / target) * 100).toFixed(2),
      order_count: buckets[i].orders,
    };
  }).sort((a, b) => Number(b.achievement) - Number(a.achievement));

  const limit = opts?.limit ?? 100;
  const cursor = opts?.cursor ?? 0;
  const page = rows.slice(cursor, cursor + limit);
  return {
    rows: page,
    hasMore: cursor + limit < rows.length,
    nextCursor: cursor + limit < rows.length ? cursor + limit : null,
  };
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

/** GET /admin/sales/performance?period=YYYY-MM — all sales performance (admin only). */
export async function fetchAdminSalesPerformance(
  token: string,
  opts?: { period?: string; limit?: number; cursor?: number },
): Promise<{ rows: SalesPerformanceRow[]; hasMore: boolean; nextCursor: number | null }> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    dummySalesPerformance(opts),
    () => fetchAdminSalesPerformanceReal(token, opts),
  );
}

async function fetchAdminSalesPerformanceReal(
  token: string,
  opts?: { period?: string; limit?: number; cursor?: number },
): Promise<{ rows: SalesPerformanceRow[]; hasMore: boolean; nextCursor: number | null }> {
  const query = new URLSearchParams();
  if (opts?.period) query.set('period', opts.period);
  query.set('limit', String(opts?.limit ?? 100));
  if (opts?.cursor) query.set('cursor', String(opts.cursor));
  const response = await fetch(apiUrl(`/admin/sales/performance?${query.toString()}`), {
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Kinerja sales tidak dapat dimuat.'));
  return {
    rows: Array.isArray(data.data) ? (data.data as SalesPerformanceRow[]) : [],
    hasMore: Boolean(data.has_more),
    nextCursor: data.next_cursor ?? null,
  };
}

/** Parse a monetary/percent string-or-number (fixed 2-decimal) into a display number. */
export function parseMoney(value: string | number | null | undefined): number {
  if (value === null || value === undefined) return 0;
  const n = typeof value === 'number' ? value : Number(String(value).replace(/[^0-9.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

/** Format a monetary string-or-number into id-ID Rupiah display. */
export function formatRupiah(value: string | number | null | undefined): string {
  return `Rp ${parseMoney(value).toLocaleString('id-ID')}`;
}

/** Display the achievement percentage with 2 decimals and % suffix. */
export function formatPercentage(value: string | number | null | undefined): string {
  return `${parseMoney(value).toFixed(2)}%`;
}
