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

/** Params accepted by `fetchAdminSalesPerformance` (sort/order passed by Cycle 2). */
export interface SalesPerformanceParams {
  period?: string;
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
}

/** Normalized list result: offset cursor + optional count summary. */
export interface SalesPerformanceListResult {
  rows: SalesPerformanceRow[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { total: number };
  nextCursor: number | null;
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

function dummySalesPerformance(opts?: SalesPerformanceParams): SalesPerformanceListResult {
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

  const limit = opts?.limit ?? 15;
  const cursor = opts?.cursor ?? 0;
  const page = rows.slice(cursor, cursor + limit);
  const hasMore = cursor + limit < rows.length;
  const total = rows.length;
  return {
    rows: page,
    hasMore,
    limit,
    cursor,
    total,
    summary: { total },
    nextCursor: hasMore ? cursor + limit : null,
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
  opts?: SalesPerformanceParams,
): Promise<SalesPerformanceListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    dummySalesPerformance(opts),
    () => fetchAdminSalesPerformanceReal(token, opts),
  );
}

async function fetchAdminSalesPerformanceReal(
  token: string,
  opts?: SalesPerformanceParams,
): Promise<SalesPerformanceListResult> {
  const query = new URLSearchParams();
  if (opts?.period) query.set('period', opts.period);
  query.set('limit', String(opts?.limit ?? 15));
  query.set('cursor', String(opts?.cursor ?? 0));
  if (opts?.sort) query.set('sort', opts.sort);
  if (opts?.order) query.set('order', opts.order);
  const response = await fetch(apiUrl(`/admin/sales/performance?${query.toString()}`), {
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Kinerja sales tidak dapat dimuat.'));
  const meta = (data?.meta ?? {}) as {
    has_more?: unknown;
    limit?: unknown;
    cursor?: unknown;
    total?: unknown;
  };
  const hasMore = Boolean(meta.has_more);
  const limit = Number(meta.limit ?? opts?.limit ?? 15);
  const cursor = Number(meta.cursor ?? opts?.cursor ?? 0);
  const total = meta.total !== undefined ? Number(meta.total) : undefined;
  return {
    rows: Array.isArray(data.data) ? (data.data as SalesPerformanceRow[]) : [],
    hasMore,
    limit,
    cursor,
    total,
    summary: total !== undefined ? { total } : undefined,
    nextCursor: hasMore ? cursor + limit : null,
  };
}

/* Money/percent formatters live in `@/lib/format`; re-exported here so existing
   importers keep working without duplication. */
export { parseMoney, formatRupiah, formatPercentage } from '@/lib/format';
