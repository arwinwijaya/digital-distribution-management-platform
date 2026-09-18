/**
 * Integration test: page data loaders with dummy-mode guards.
 *
 * Verifies:
 *  - dashboard/analytics loaders → populated dummy + zero fetch while ON
 *  - analytics renders → no empty-state strings while ON
 *  - zero-fetch sweep across ALL extracted loaders (10+)
 *  - toggle OFF → useDummyRefresh fires real re-fetch
 *  - OFF path → real fetch called
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, act } from '@testing-library/react';
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import type { FullDummy } from '@/dummy';
import { useDummyRefresh } from '@/dummy/guards';

import { loadDashboard } from '@/app/dashboard/api';
import { loadAnalytics } from '@/app/analytics/api';
import { loadInvoices } from '@/app/invoices/api';
import { loadDeliveries } from '@/app/delivery/api';
import { loadPaymentsList } from '@/app/payments/api';
import { loadOperations } from '@/app/operations/api';
import { loadSalesList } from '@/app/sales/api';
import { loadProductCatalog } from '@/components/ProductCatalog';
import { loadMarketplaceCatalog } from '@/components/MarketplaceCatalog';
import { loadOrderFormProducts, trackOrder } from '@/components/OrderForm';
import AnalyticsPage from '@/app/analytics/page';

const TODAY = new Date('2026-02-14T10:00:00+07:00');

function initDummy() {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
  localStorage.setItem('ddp_token', 't-token');
  (global as any).fetch = jest.fn();
  setDummyGenerator(() => buildFullDummy(TODAY) as any);
}

function turnOn() {
  act(() => {
    useDummyStore.getState().toggle();
  });
}

beforeEach(() => {
  initDummy();
});

afterEach(() => {
  jest.restoreAllMocks();
});

// ─── Step 1+3: Dashboard loader populated + zero fetch + toggle OFF ──────

describe('dashboard loader dummy guard', () => {
  it('admin: returns populated DashboardData with zero fetch', async () => {
    turnOn();
    const result = await loadDashboard('t-token', 'admin', 'daily');
    expect(result.kind).toBe('admin');
    if (result.kind !== 'admin') throw new Error('expected admin kind');
    expect(result.data.metrics.orders_total).toBeGreaterThan(0);
    expect(result.data.sales_trends.length).toBeGreaterThan(0);
    expect(result.data.outlet_performance.length).toBeGreaterThan(0);
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it('finance: returns FinanceMetrics-shaped data with zero fetch', async () => {
    turnOn();
    const result = await loadDashboard('t-token', 'finance', 'daily');
    expect(result.kind).toBe('finance');
    if (result.kind !== 'finance') throw new Error('expected finance kind');
    expect(result.data.issued_invoices.count).toBeGreaterThan(0);
    expect(result.data.payment_status_breakdown).toBeDefined();
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it('OFF: calls real fetch with /analytics/dashboard', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          metrics: { orders_total: 1, sales_total: '0', outlets_total: 0, products_total: 0, payments_total: '0', outstanding_total: '0' },
          sales_trends: [],
          outlet_performance: [],
        },
      }),
    });
    const result = await loadDashboard('t-token', 'admin', 'daily');
    expect(result.kind).toBe('admin');
    expect(global.fetch).toHaveBeenCalledTimes(1);
    expect(String((global.fetch as jest.Mock).mock.calls[0][0])).toContain('/analytics/dashboard');
  });

  it('toggle OFF triggers useDummyRefresh and re-fetches', async () => {
    turnOn();

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          metrics: { orders_total: 1, sales_total: '0', outlets_total: 0, products_total: 0, payments_total: '0', outstanding_total: '0' },
          sales_trends: [],
          outlet_performance: [],
        },
      }),
    });

    const onLoad = jest.fn();

    function Harness() {
      useDummyRefresh(() => {
        void loadDashboard('t-token', 'admin', 'daily').then(onLoad);
      });
      return <span>test</span>;
    }

    render(<Harness />);
    expect(screen.getByText('test')).toBeInTheDocument();
    expect(onLoad).not.toHaveBeenCalled();

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => {
      expect(onLoad).toHaveBeenCalledTimes(1);
    });
    expect(onLoad.mock.calls[0][0].kind).toBe('admin');
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });
});

// ─── Step 5: Analytics non-empty + no empty-state text while ON ───────────

describe('analytics dummy guard', () => {
  it('loadAnalytics returns non-empty with zero fetch', async () => {
    turnOn();
    const result = await loadAnalytics('t-token');
    expect(result.recommendations.length).toBeGreaterThan(0);
    expect(result.forecast.predictions).toHaveLength(4);
    expect(result.segmentation.segments!.length).toBeGreaterThan(0);
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it('analytics page renders without empty-state text while ON', async () => {
    turnOn();
    render(<AnalyticsPage />);

    await waitFor(() => {
      expect(screen.queryByText('Memuat analitik...')).not.toBeInTheDocument();
    });

    // Empty-state strings must NOT appear while dummy is ON
    expect(screen.queryByText('Belum ada rekomendasi')).not.toBeInTheDocument();
    expect(screen.queryByText('Data belum cukup')).not.toBeInTheDocument();
  });
});

// ─── Step 6b: Zero-fetch sweep across ALL loaders ─────────────────────────

describe('zero-fetch sweep — all extracted loaders', () => {
  it('every loader returns non-empty correctly-shaped dummy with zero network', async () => {
    turnOn();

    const [invoices, deliveries, payments, ops, sales, products, market, orderProducts] = await Promise.all([
      loadInvoices('t-token'),
      loadDeliveries('t-token'),
      loadPaymentsList('t-token', 'admin'),
      loadOperations('t-token', { page: 1, limit: 20 }),
      loadSalesList('t-token'),
      loadProductCatalog('t-token'),
      loadMarketplaceCatalog('t-token'),
      loadOrderFormProducts('t-token'),
    ]);

    // invoices: { invoices, meta }
    expect(Array.isArray(invoices.invoices)).toBe(true);
    expect(invoices.invoices.length).toBeGreaterThan(0);
    expect(invoices.invoices[0]).toHaveProperty('id');
    expect(invoices.invoices[0]).toHaveProperty('invoice_number');

    // deliveries: array
    expect(Array.isArray(deliveries)).toBe(true);
    expect(deliveries.length).toBeGreaterThan(0);
    expect(deliveries[0]).toHaveProperty('id');
    expect(deliveries[0]).toHaveProperty('status');

    // payments: { payments, paymentMeta, invoiceMeta, summary }
    expect(Array.isArray(payments.payments)).toBe(true);
    expect(payments.payments.length).toBeGreaterThan(0);
    expect(payments.payments[0]).toHaveProperty('id');
    expect(payments.payments[0]).toHaveProperty('amount');

    // operations: { readinessResult, issuesResult }
    expect(ops.readinessResult.ok).toBe(true);
    expect(ops.readinessResult.data).toBeDefined();
    expect(ops.issuesResult.ok).toBe(true);
    expect(ops.issuesResult.data!.issues.length).toBeGreaterThan(0);

    // sales: { visits, meta }
    expect(Array.isArray(sales.visits)).toBe(true);
    expect(sales.visits.length).toBeGreaterThan(0);
    expect(sales.visits[0]).toHaveProperty('id');
    expect(sales.visits[0]).toHaveProperty('target');

    // products catalog: Product[]
    expect(Array.isArray(products)).toBe(true);
    expect(products.length).toBeGreaterThan(0);
    expect(products[0]).toHaveProperty('id');
    expect(products[0]).toHaveProperty('name');
    expect(products[0]).toHaveProperty('price');

    // marketplace: { suppliers, products }
    expect(Array.isArray(market.suppliers)).toBe(true);
    expect(market.suppliers.length).toBeGreaterThan(0);
    expect(market.suppliers[0]).toHaveProperty('id');
    expect(market.suppliers[0]).toHaveProperty('name');
    expect(Array.isArray(market.products)).toBe(true);
    expect(market.products.length).toBeGreaterThan(0);

    // orderForm products: Product[]
    expect(Array.isArray(orderProducts)).toBe(true);
    expect(orderProducts.length).toBeGreaterThan(0);
    expect(orderProducts[0]).toHaveProperty('id');
    expect(orderProducts[0]).toHaveProperty('name');
    expect(orderProducts[0]).toHaveProperty('price');

    // trackOrder: returns a valid order (matching by id=1)
    const tracked = await trackOrder('t-token', '1');
    expect(tracked).not.toBeNull();
    expect(tracked).toHaveProperty('order_id');

    // Single cross-loader zero-fetch assertion
    expect(global.fetch).not.toHaveBeenCalled();
  });
});
