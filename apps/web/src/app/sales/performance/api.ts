import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';

export interface MyPerformance {
  user_id: number;
  name: string;
  period: string;
  target: string | number;
  achievement: string | number;
  percentage: string | number;
  order_count: number;
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

// ── Dummy-mode helpers ──────────────────────────────────────────────────────
interface DummyOrderEntity {
  id: number;
  status: string;
  total_amount: string;
  created_at?: string;
}

function dummyOrders(): DummyOrderEntity[] {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ orders: DummyOrderEntity[] }> | null;
  return entities?.orders ?? [];
}

/** The logged-in dummy sales user's own performance, derived from orders. */
function dummyMyPerformance(period?: string): MyPerformance {
  const orders = dummyOrders();
  const active = orders.filter((o) => o.status !== 'cancelled');
  const periodKey = period ?? (active
    .map((o) => String(o.created_at ?? '').slice(0, 7))
    .filter((p) => p.length === 7)
    .sort()
    .pop() ?? '2026-02');
  const inPeriod = active.filter((o) => String(o.created_at ?? '').startsWith(periodKey));
  const source = inPeriod.length > 0 ? inPeriod : active;

  let amount = 0;
  let count = 0;
  for (const order of source) {
    amount += Number(String(order.total_amount).replace(/[^0-9.]/g, '')) || 0;
    count += 1;
  }
  const target = Math.max(1, Math.round(amount / 0.8));
  return {
    user_id: 2,
    name: 'Budi Sales',
    period: periodKey,
    target: String(target),
    achievement: amount.toFixed(2),
    percentage: ((amount / target) * 100).toFixed(2),
    order_count: count,
  };
}

/** GET /sales/my-performance — own performance for current (or requested) period. */
export async function fetchMyPerformance(token: string, period?: string): Promise<MyPerformance> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    dummyMyPerformance(period),
    () => fetchMyPerformanceReal(token, period),
  );
}

async function fetchMyPerformanceReal(token: string, period?: string): Promise<MyPerformance> {
  const query = period ? `?period=${encodeURIComponent(period)}` : '';
  const response = await fetch(apiUrl(`/sales/my-performance${query}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Kinerja tidak dapat dimuat.'));
  return (data.data ?? data) as MyPerformance;
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
