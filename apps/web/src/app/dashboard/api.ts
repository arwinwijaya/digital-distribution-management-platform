/**
 * Dashboard data loader — extracted from page.tsx inline fetch.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';
import type { OutletDashboardData } from '@/dummy';
import type { TrendPoint, OutletPoint } from '@/components/Charts';
import { periodWindow, topFavorites, toOutletStatus } from '@/app/dashboard/outlet-helpers';

export type Group = 'daily' | 'weekly' | 'monthly';

export type DashboardMetrics = {
  orders_total: number;
  sales_total: string;
  outlets_total: number;
  products_total: number;
  payments_total: string;
  outstanding_total: string;
};

export type DashboardData = {
  metrics: DashboardMetrics;
  sales_trends: TrendPoint[];
  outlet_performance: OutletPoint[];
};

export type FinanceMetrics = {
  issued_invoices: { count: number };
  outstanding_balance: { amount: string | number };
  overdue_rate: { rate: number; overdue_count: number; active_count: number };
  collection_time: { average_days: number; fully_collected_count: number };
  payment_status_breakdown: Record<string, number>;
  reminders: { success: number; failure: number; sent: number; failed: number };
};

export type DashboardLoadResult =
  | { kind: 'finance'; data: FinanceMetrics }
  | { kind: 'admin'; data: DashboardData }
  | { kind: 'outlet'; data: OutletDashboardData };

// Module-level cache for outlet orders pages — window-independent because API fetch is unfiltered.
// Invalidated only on new loadDashboard outlet call (not on credit retry).
const outletOrdersCache = new Map<string, { envelope: OrdersEnvelope; timestamp: number }>();

function tokenHash(token: string): string {
  // Simple hash — first 16 chars of b64 or token prefix, avoids leaking full token in key.
  try {
    return btoa(token).slice(0, 16);
  } catch {
    return token.slice(0, 16);
  }
}

interface CreditLimitResponse {
  credit_limit: number | null;
  outstanding_balance: string;
  available_credit: number | null;
}

interface OrdersEnvelope {
  status: string;
  data: Array<{
    id: number;
    order_id: string;
    status: string;
    total_amount: string;
    paid_amount: string;
    created_at: string;
    items: Array<{
      product_id: number;
      product_name: string;
      quantity: number;
      unit_price: string;
      subtotal: string;
    }>;
  }>;
  meta: {
    has_more: boolean;
    total: number;
    limit: number;
    cursor: number;
  };
}

function moneyValue(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

function toCents(value: number | string): number {
  return Math.round(Number(String(value).replace(/[^0-9.-]/g, '')) * 100);
}

/**
 * Load dashboard data for a given role and optional date range.
 * While dummy mode is ON, returns pre-built aggregates — zero network.
 */
export async function loadDashboard(
  token: string,
  role: string,
  group: Group,
  startDate?: string,
  endDate?: string,
): Promise<DashboardLoadResult> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;
  const hasDateFilter = Boolean(startDate && endDate);

  if (role === 'finance') {
    const query = new URLSearchParams(
      hasDateFilter ? { start_date: startDate as string, end_date: endDate as string } : {},
    );
    const queryString = query.toString();
    const dummyValue: DashboardLoadResult | null =
      isDummy && dummy ? { kind: 'finance' as const, data: dummy.dashboardFinance } : null;
    return withDummyRead(
      isDummy,
      dummyValue as DashboardLoadResult,
      async () => {
        const response = await fetch(
          `${apiUrl('/finance/metrics')}${queryString ? `?${queryString}` : ''}`,
          { headers: authHeaders(token) },
        );
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Metrik keuangan tidak dapat dimuat.');
        return { kind: 'finance' as const, data: body.data as FinanceMetrics };
      },
    );
  }

  if (role === 'outlet') {
    const dummyValue: DashboardLoadResult | null =
      isDummy && dummy ? { kind: 'outlet' as const, data: dummy.dashboardOutlet } : null;
    return withDummyRead(isDummy, dummyValue as DashboardLoadResult, async () =>
      fetchOutletDashboard(token, startDate, endDate),
    );
  }

  const query = new URLSearchParams(
    hasDateFilter ? { start_date: startDate as string, end_date: endDate as string, group } : { group },
  );
  const dummyValue: DashboardLoadResult | null =
    isDummy && dummy ? { kind: 'admin' as const, data: dummy.dashboardAdmin } : null;
  return withDummyRead(
    isDummy,
    dummyValue as DashboardLoadResult,
    async () => {
      const response = await fetch(`${apiUrl('/analytics/dashboard')}?${query}`, {
        headers: authHeaders(token),
      });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Data dasbor tidak dapat dimuat.');
      return { kind: 'admin' as const, data: body.data as DashboardData };
    },
  );
}

/**
 * Outlet dashboard composition: paginated orders + credit limit.
 * Pagination: limit=100, cursor offset, cap at 1000 newest. Orders are fetched without
 * date filters; period aggregates use the exact UTC instant window client-side.
 */
export async function fetchOutletDashboard(
  token: string,
  startDate?: string,
  endDate?: string,
): Promise<DashboardLoadResult> {
  const now = new Date();
  const pw = periodWindow(30, now);
  // Outlet period bounds are exact instants; never send them to date-only API filters.
  const startInstantUTC = pw.startInstantUTC;
  const endInstantUTC = pw.endInstantUTC;
  void startDate;
  void endDate;

  // Fetch paginated orders up to 1000
  const allOrders: OrdersEnvelope['data'] = [];
  let cursor = 0;
  let hasMore = true;
  let totalFromMeta = 0;
  const hash = tokenHash(token);

  while (hasMore && cursor < 1000) {
    const cacheKey = `${hash}:${cursor}:100:created_at:desc`;
    let envelope: OrdersEnvelope;
    const cached = outletOrdersCache.get(cacheKey);
    if (cached) {
      envelope = cached.envelope;
    } else {
      const params = new URLSearchParams({
        limit: '100',
        cursor: String(cursor),
        sort: 'created_at',
        order: 'desc',
      });
      const response = await fetch(`${apiUrl('/orders')}?${params}`, {
        headers: authHeaders(token),
      });
      const body: OrdersEnvelope & { message?: string } = await response.json();
      if (!response.ok) {
        // 403 without outlet relation → inline payload, not retryable
        if (response.status === 403 && body.message?.includes('not associated with an outlet')) {
          const err = new Error(body.message) as Error & { status?: number; inline?: boolean };
          err.status = 403;
          err.inline = true;
          throw err;
        }
        if (response.status === 401) {
          const err = new Error(body.message || 'Unauthorized') as Error & { status?: number };
          err.status = 401;
          throw err;
        }
        throw new Error(body.message || 'Daftar pesanan tidak dapat dimuat.');
      }
      // Backend envelope: {status, data, meta}
      envelope = {
        status: body.status,
        data: (body as unknown as { data: OrdersEnvelope['data'] }).data ?? [],
        meta: (body as unknown as { meta: OrdersEnvelope['meta'] }).meta ?? {
          has_more: false,
          total: 0,
          limit: 100,
          cursor,
        },
      };
      // Normalize if body is already the envelope with status/data/meta
      if (Array.isArray((body as unknown as { data: unknown }).data) && (body as unknown as { meta: unknown }).meta) {
        envelope = body as unknown as OrdersEnvelope;
      }
      outletOrdersCache.set(cacheKey, { envelope, timestamp: Date.now() });
    }

    allOrders.push(...envelope.data);
    totalFromMeta = envelope.meta?.total ?? totalFromMeta;
    hasMore = envelope.meta?.has_more === true && envelope.data.length > 0;
    cursor += envelope.data.length;
    if (!hasMore || cursor >= 1000 || allOrders.length >= 1000) break;
    if (envelope.data.length === 0) break;
  }

  // Fetch credit-limit — NOT cached with orders; retry isolates to this fetch only
  let creditData: OutletDashboardData['credit'];
  try {
    const creditResponse = await fetch(apiUrl('/credit-limit'), {
      headers: authHeaders(token),
    });
    const creditBody: { status: string; data: CreditLimitResponse; message?: string } =
      await creditResponse.json();
    if (!creditResponse.ok) {
      if (creditResponse.status === 403 && creditBody.message?.includes('not associated')) {
        const err = new Error(creditBody.message) as Error & { status?: number; inline?: boolean };
        err.status = 403;
        err.inline = true;
        throw err;
      }
      throw new Error(creditBody.message || 'Kredit tidak dapat dimuat.');
    }
    const credit = creditBody.data;
    const cl = credit.credit_limit;
    creditData = {
      credit_limit: cl === null ? null : moneyValue(cl),
      outstanding_balance: moneyValue(Number(credit.outstanding_balance)),
      available_credit: credit.available_credit === null ? null : moneyValue(credit.available_credit),
      hidden: cl === null,
    };
  } catch (e) {
    const err = e as Error & { status?: number; inline?: boolean };
    if (err.status === 403 && err.inline) {
      // Propagate inline 403 for credit as well — UI will show without retry
      throw err;
    }
    if (err.status === 401) throw err;
    // For 5xx or network, propagate as retryable error — UI per-section retry
    throw new Error(err.message || 'Kredit tidak dapat dimuat.');
  }

  const summaryOrders = allOrders.slice(0, 1000);
  const periodOrders = summaryOrders.filter((order) => {
    const createdAt = Date.parse(order.created_at);
    return Number.isFinite(createdAt)
      && createdAt >= Date.parse(startInstantUTC)
      && createdAt <= Date.parse(endInstantUTC);
  });
  const statuses: Record<string, number> = {};
  for (const order of summaryOrders) {
    statuses[order.status] = (statuses[order.status] ?? 0) + 1;
  }

  // Shopping aggregates: total excluding Cancelled/Canceled, count unique orders
  const shoppingOrders = periodOrders.filter(
    (o) => !['Cancelled', 'Canceled', 'cancelled', 'canceled'].includes(o.status),
  );
  const shoppingCents = shoppingOrders.reduce((sum, order) => sum + toCents(order.total_amount), 0);

  const favoriteInput = periodOrders.map((order) => ({
    created_at: order.created_at,
    items: order.items.map((item) => ({
      product_id: item.product_id,
      product_name: item.product_name,
      quantity: item.quantity,
    })),
  }));
  const favorites = topFavorites(favoriteInput, 5).map((item) => ({
    product_id: item.product_id,
    display_name: item.displayName,
    total_qty: item.totalQty,
  }));

  const recent = summaryOrders.slice(0, 10).map((order) => ({
    id: order.id,
    order_id: order.order_id,
    status: order.status,
    status_label: toOutletStatus(order.status).label,
    total_amount: moneyValue(Number(order.total_amount)),
    created_at: order.created_at,
  }));

  const capped = totalFromMeta > 1000 || allOrders.length >= 1000;

  return {
    kind: 'outlet',
    data: {
      summary: {
        total: summaryOrders.length,
        statuses,
        recent,
        truncation: { capped, total: totalFromMeta || summaryOrders.length },
      },
      shopping: {
        total: moneyValue(shoppingCents / 100),
        count: shoppingOrders.length,
        period_label: pw.label,
      },
      credit: creditData,
      favorites,
    },
  };
}

/** Exposed for tests: clear outlet cache between test cases. */
export function __clearOutletCacheForTests(): void {
  outletOrdersCache.clear();
}
