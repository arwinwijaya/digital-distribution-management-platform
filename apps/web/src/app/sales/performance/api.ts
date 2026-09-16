import { apiUrl, authHeaders } from '@/lib/api';

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

/** GET /sales/my-performance — own performance for current (or requested) period. */
export async function fetchMyPerformance(token: string, period?: string): Promise<MyPerformance> {
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
