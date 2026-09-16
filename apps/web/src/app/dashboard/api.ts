/**
 * Dashboard data loader — extracted from page.tsx inline fetch.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';
import type { TrendPoint, OutletPoint } from '@/components/Charts';

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
  | { kind: 'admin'; data: DashboardData };

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
    const dummyValue: DashboardLoadResult | null = isDummy && dummy
      ? { kind: 'finance' as const, data: dummy.dashboardFinance }
      : null;
    return withDummyRead(
      isDummy,
      dummyValue as DashboardLoadResult,
      async () => {
        const response = await fetch(
          `${apiUrl('/finance/metrics')}${queryString ? `?${queryString}` : ''}`,
          { headers: authHeaders(token) },
        );
        const body = await response.json();
        if (!response.ok)
          throw new Error(body.message || 'Metrik keuangan tidak dapat dimuat.');
        return { kind: 'finance' as const, data: body.data as FinanceMetrics };
      },
    );
  }

  const query = new URLSearchParams(
    hasDateFilter
      ? { start_date: startDate as string, end_date: endDate as string, group }
      : { group },
  );
  const dummyValue: DashboardLoadResult | null = isDummy && dummy
    ? { kind: 'admin' as const, data: dummy.dashboardAdmin }
    : null;
  return withDummyRead(
    isDummy,
    dummyValue as DashboardLoadResult,
    async () => {
      const response = await fetch(
        `${apiUrl('/analytics/dashboard')}?${query}`,
        { headers: authHeaders(token) },
      );
      const body = await response.json();
      if (!response.ok)
        throw new Error(body.message || 'Data dasbor tidak dapat dimuat.');
      return { kind: 'admin' as const, data: body.data as DashboardData };
    },
  );
}
