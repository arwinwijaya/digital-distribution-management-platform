/**
 * Cross-unit dummy-mode integration verification (T12).
 *
 * Proves three story acceptance criteria end-to-end by toggling real
 * components, loading every guarded helper against the real Zustand store,
 * and asserting zero network + no empty-state text + ephemeral mutations —
 * all without reaching the backend.
 *
 * Tests exercise: real Topbar (UI entry point), real loader functions,
 * real useDummyStore (toggled by switch click, not by direct mutation).
 *
 * Test doubles: global jest.fn() fetch (assert NEVER called while ON),
 * localStorage (ddp_token / ddp_role), jsdom.
 * NOT mocked: Topbar, loaders, api modules, guards, store, factory.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent, act, waitFor } from '@testing-library/react';

import { useDummyStore, setDummyGenerator, DUMMY_FLAG_KEY } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { JABODETABEK_TERRITORIES } from '@/dummy/seed';

import Topbar from '@/components/Topbar';
import AnalyticsPage from '@/app/analytics/page';

import { loadAnalytics } from '@/app/analytics/api';
import { loadDashboard } from '@/app/dashboard/api';
import {
  fetchGeographicData,
  sendFunnelEvent,
} from '@/lib/data-intelligence-api';
import { createSalesOrder } from '@/app/sales/orders/api';

/* ------------------------------------------------------------------ */
/*  Constants                                                         */
/* ------------------------------------------------------------------ */

const TOKEN = 't-token';
const FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00');

/** Aggregate JABODETABEK bounding box — all outlets must lie inside. */
const JABO_BBOX = {
  minLat: Math.min(...JABODETABEK_TERRITORIES.map((t) => t.bbox.minLat)),
  maxLat: Math.max(...JABODETABEK_TERRITORIES.map((t) => t.bbox.maxLat)),
  minLon: Math.min(...JABODETABEK_TERRITORIES.map((t) => t.bbox.minLon)),
  maxLon: Math.max(...JABODETABEK_TERRITORIES.map((t) => t.bbox.maxLon)),
};

/* ------------------------------------------------------------------ */
/*  Fetch spy lifecycle                                               */
/* ------------------------------------------------------------------ */

let originalFetch: typeof global.fetch;
let fetchMock: jest.Mock;

beforeEach(() => {
  localStorage.clear();
  localStorage.setItem('ddp_token', TOKEN);
  localStorage.setItem('ddp_role', 'admin');
  useDummyStore.getState().reset();
  localStorage.setItem('ddp_token', TOKEN);
  localStorage.setItem('ddp_role', 'admin');

  // Register deterministic generator pinned to fixed today.
  setDummyGenerator((today?: Date) => buildFullDummy(today ?? FIXED_TODAY) as unknown as DummyEntities);

  // Stub global fetch — must NEVER be called while dummy is ON.
  fetchMock = jest.fn(async () => ({
    ok: true,
    status: 200,
    json: async () => ({ status: 'success', data: [] }),
  }) as unknown as Response);
  originalFetch = global.fetch;
  global.fetch = fetchMock;
});

afterEach(() => {
  global.fetch = originalFetch;
  jest.restoreAllMocks();
  useDummyStore.getState().reset();
});

/* ------------------------------------------------------------------ */
/*  Helpers                                                           */
/* ------------------------------------------------------------------ */

/** Click the Topbar switch to toggle dummy ON. Returns unmount for remount hygiene. */
function toggleDummyOnViaTopbar(): { unmount: () => void } {
  const { unmount } = render(<Topbar />);
  const sw = screen.getByRole('switch', { name: /mode dummy/i });
  expect(sw).toHaveAttribute('aria-checked', 'false');
  act(() => {
    fireEvent.click(sw);
  });
  expect(useDummyStore.getState().isDummy).toBe(true);
  return { unmount };
}

/** Directly toggle ON via store (used in sweep / refresh tests). */
function turnOn(): void {
  act(() => {
    useDummyStore.getState().toggle();
  });
  expect(useDummyStore.getState().isDummy).toBe(true);
}

/* ================================================================== */
/*  RED Cycle 1 — Analytics + GeoMap + Order + Funnel + Finance       */
/* ================================================================== */

describe('RED Cycle 1 — analytics + GeoMap + order + funnel + finance', () => {
  it('Topbar click → ON; analytics non-empty + no empty-state text; GeoMap in bbox; finance full shape; order side-effects; funnel fake success; zero fetch', async () => {
    // ── Exercise through real Topbar ──────────────────────────────────
    toggleDummyOnViaTopbar();

    // The Topbar switch should now be aria-checked=true
    const sw = screen.getByRole('switch', { name: /mode dummy/i });
    expect(sw).toHaveAttribute('aria-checked', 'true');

    // ── Analytics (story 2): non-empty recommendations, 4-period forecast,
    //    non-empty segmentation; rendering shows no empty-state text ───
    const analytics = await loadAnalytics(TOKEN);
    expect(analytics.recommendations.length).toBeGreaterThan(0);
    expect(analytics.forecast.predictions).toHaveLength(4);
    expect(analytics.segmentation.segments!.length).toBeGreaterThan(0);

    // Render the analytics page — must NOT show empty-state strings
    render(<AnalyticsPage />);
    await waitFor(() => {
      expect(screen.queryByText('Memuat analitik...')).not.toBeInTheDocument();
    });
    expect(screen.queryByText('Belum ada rekomendasi')).not.toBeInTheDocument();
    expect(screen.queryByText('Data belum cukup')).not.toBeInTheDocument();

    // ── GeoMap (story 2): map_points 40..60, all inside JABODETABEK,
    //    table.length === 5 ────────────────────────────────────────────
    const geo = await fetchGeographicData();
    expect(geo.map_points.length).toBeGreaterThanOrEqual(40);
    expect(geo.map_points.length).toBeLessThanOrEqual(60);
    for (const pt of geo.map_points) {
      expect(pt.latitude).toBeGreaterThanOrEqual(JABO_BBOX.minLat);
      expect(pt.latitude).toBeLessThanOrEqual(JABO_BBOX.maxLat);
      expect(pt.longitude).toBeGreaterThanOrEqual(JABO_BBOX.minLon);
      expect(pt.longitude).toBeLessThanOrEqual(JABO_BBOX.maxLon);
    }
    expect(geo.table.length).toBe(5);

    // ── Finance dashboard (story 2-E): ALL FinanceMetrics keys ────────
    const finance = await loadDashboard(TOKEN, 'finance', 'daily');
    expect(finance.kind).toBe('finance');
    if (finance.kind !== 'finance') throw new Error('expected finance kind');
    const fm = finance.data;
    expect(fm).toHaveProperty('issued_invoices');
    expect(fm).toHaveProperty('outstanding_balance');
    expect(fm).toHaveProperty('overdue_rate');
    expect(fm).toHaveProperty('collection_time');
    expect(fm).toHaveProperty('payment_status_breakdown');
    expect(fm).toHaveProperty('reminders');

    // ── Order side-effects (story 3): one order (dummy- id), one payment,
    //    one invoice, one delivery; global fetch NEVER called ──────────
    const ordersBefore = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.orders as unknown[]
    )?.length ?? 0;
    const paymentsBefore = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.payments as unknown[]
    )?.length ?? 0;
    const invoicesBefore = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.invoices as unknown[]
    )?.length ?? 0;
    const deliveriesBefore = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.deliveries as unknown[]
    )?.length ?? 0;

    const createdOrder = await createSalesOrder(TOKEN, {
      outlet_id: 1,
      items: [{ product_id: 1, quantity: 2 }],
    });

    // order_id has dummy- prefix
    expect(String(createdOrder.order_id)).toContain('dummy-');

    const ordersAfter = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.orders as unknown[]
    )?.length ?? 0;
    const paymentsAfter = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.payments as unknown[]
    )?.length ?? 0;
    const invoicesAfter = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.invoices as unknown[]
    )?.length ?? 0;
    const deliveriesAfter = (
      (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.deliveries as unknown[]
    )?.length ?? 0;

    expect(ordersAfter).toBe(ordersBefore + 1);
    expect(paymentsAfter).toBe(paymentsBefore + 1);
    expect(invoicesAfter).toBe(invoicesBefore + 1);
    expect(deliveriesAfter).toBe(deliveriesBefore + 1);

    // ── Funnel tracking (story 2-F): fake success, correct shape, ────
    //    global fetch NEVER called ──────────────────────────────────────
    const funnelResult = await sendFunnelEvent('clicked', {
      outlet_id: 1,
      product_id: 2,
    });
    expect(funnelResult).toMatchObject({
      event_uuid: expect.any(String),
      event_type: 'clicked',
      outlet_id: 1,
      product_id: 2,
    });

    // ── Global fetch was NEVER called across all operations ───────────
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

/* ================================================================== */
/*  RED Cycle 2 — Refresh persistence + auto re-fetch + zero sweep   */
/* ================================================================== */

describe('RED Cycle 2 — refresh persistence + auto re-fetch + zero-network sweep', () => {
  it('after remount, store restores isDummy + dummyEntities non-null; analytics non-empty after remount; toggle OFF clears entities and triggers real fetch', async () => {
    // ── Step 1: toggle ON via Topbar, create a mutation ───────────────
    const first = toggleDummyOnViaTopbar();
    first.unmount(); // single Topbar per jsdom at a time (getByRole hygiene)
    const created = await createSalesOrder(TOKEN, {
      outlet_id: 1,
      items: [{ product_id: 1, quantity: 1 }],
    });
    expect(String(created.order_id)).toContain('dummy-');

    // ── Step 2: simulate refresh ──────────────────────────────────────
    //   - localStorage['dummy:isDummy'] is still '1' (persisted by toggle)
    //   - Clear entities singleton via resetEntities (keeps isDummy flag)
    //   - Re-register generator → setDummyGenerator regenerates because
    //     isDummy=true && dummyEntities===null (T2 store init path)
    //   - Re-mount Topbar → aria-checked should be true (flag restored)
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBe('1');
    useDummyStore.getState().resetEntities(); // clear entities, keep flag
    expect(useDummyStore.getState().isDummy).toBe(true);
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    // Re-init via the seam: setDummyGenerator regenerates when flag set + entities null
    setDummyGenerator((today?: Date) => buildFullDummy(today ?? FIXED_TODAY) as unknown as DummyEntities);
    expect(useDummyStore.getState().dummyEntities).not.toBeNull();

    // Re-mount Topbar — flag restored from localStorage
    const { unmount } = render(<Topbar />);
    const switches = screen.getAllByRole('switch', { name: /mode dummy/i });
    expect(switches).toHaveLength(1);
    const swAfterRemount = switches[0];
    expect(swAfterRemount).toHaveAttribute('aria-checked', 'true');

    // ── Step 3: analytics non-empty after remount (regenerated data) ──
    const analytics = await loadAnalytics(TOKEN);
    expect(analytics.recommendations.length).toBeGreaterThan(0);
    expect(analytics.forecast.predictions).toHaveLength(4);
    expect(analytics.segmentation.segments!.length).toBeGreaterThan(0);

    // ── Step 4: toggle OFF ────────────────────────────────────────────
    //   Use Topbar switch click to toggle OFF
    act(() => {
      fireEvent.click(swAfterRemount);
    });
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    // ── Step 5: subsequent loader re-fetch DOES call global fetch ─────
    //   Stub fetch to return valid data for the real path
    fetchMock.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        status: 'success',
        data: {
          recommendations: [],
          data_points: 0,
          data_sufficiency: { sufficient: false, level: 'none', note: '' },
          measurement: { measured: false, note: '' },
        },
      }),
    } as unknown as Response);
    fetchMock.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        status: 'success',
        data: { predictions: [], data_sufficiency: { sufficient: false, level: 'none', note: '' }, method: 'none', measurement: { measured: false, note: '' } },
      }),
    } as unknown as Response);
    fetchMock.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        status: 'success',
        data: { segment: 'unknown', confidence: '0', segments: [] },
      }),
    } as unknown as Response);

    await loadAnalytics(TOKEN);
    expect(fetchMock).toHaveBeenCalled();

    unmount();
  });

  it('zero-network sweep: ALL guarded reads return dummy data with fetch call count === 0', async () => {
    turnOn();

    // Import all guarded loaders (same as Cycle 2 spec list)
    const { fetchAdminOutlets, fetchOutletOrders, fetchOutletSummary } = await import('@/app/admin/outlets/api');
    const { fetchAdminUsers } = await import('@/app/admin/users/api');
    const { fetchProducts, fetchPriceHistory } = await import('@/app/admin/products/api');
    const { fetchPromotions } = await import('@/app/admin/promotions/api');
    const { fetchAdminSalesPerformance } = await import('@/app/admin/sales-performance/api');
    const { fetchSalesOutlets, fetchCatalogProducts } = await import('@/app/sales/orders/api');
    const { fetchMyPerformance } = await import('@/app/sales/performance/api');
    const { fetchReadiness, fetchIssues, fetchIssueDetail } = await import('@/lib/operations-api');
    const { fetchSupplierPerformanceData, fetchStockPlanningData, fetchRecommendationMeasurementData, fetchForecastMeasurementData } = await import('@/lib/data-intelligence-api');
    const { loadInvoices } = await import('@/app/invoices/api');
    const { loadDeliveries } = await import('@/app/delivery/api');
    const { loadPaymentsList } = await import('@/app/payments/api');
    const { loadOperations } = await import('@/app/operations/api');
    const { loadSalesList } = await import('@/app/sales/api');
    const { loadProductCatalog } = await import('@/components/ProductCatalog');
    const { loadMarketplaceCatalog } = await import('@/components/MarketplaceCatalog');
    const { loadOrderFormProducts, trackOrder } = await import('@/components/OrderForm');

    // Execute every guarded read call-site in Phases A+B
    await Promise.all([
      // Phase A: analytics + dashboard + data-intelligence
      loadAnalytics(TOKEN),
      loadDashboard(TOKEN, 'admin', 'daily'),
      loadDashboard(TOKEN, 'finance', 'daily'),
      fetchGeographicData(),
      fetchSupplierPerformanceData(),
      fetchStockPlanningData(),
      fetchRecommendationMeasurementData(),
      fetchForecastMeasurementData(),

      // Phase A: operations
      fetchReadiness(TOKEN),
      fetchIssues({}, TOKEN),
      fetchIssueDetail(1, TOKEN),

      // Phase B: admin reads
      fetchAdminOutlets(TOKEN),
      fetchAdminUsers(TOKEN),
      fetchProducts(TOKEN),
      fetchPromotions(TOKEN),
      fetchAdminSalesPerformance(TOKEN),

      // Phase B: sales reads
      fetchSalesOutlets(TOKEN),
      fetchCatalogProducts(TOKEN),
      fetchMyPerformance(TOKEN),

      // Phase B: page loaders
      loadInvoices(TOKEN),
      loadDeliveries(TOKEN),
      loadPaymentsList(TOKEN, 'admin'),
      loadOperations(TOKEN),
      loadSalesList(TOKEN),
      loadProductCatalog(TOKEN),
      loadMarketplaceCatalog(TOKEN),
      loadOrderFormProducts(TOKEN),
      trackOrder(TOKEN, '1'),

      // Phase B: outlet + product detail reads
      fetchOutletOrders(TOKEN, 1),
      fetchOutletSummary(TOKEN, 1),
      fetchPriceHistory(TOKEN, 1),
    ]);

    // Cross-unit zero-network invariant
    expect(fetchMock).toHaveBeenCalledTimes(0);
  });
});

/* ================================================================== */
/*  RED Cycle 3 — Determinism after toggle cycle                     */
/* ================================================================== */

describe('RED Cycle 3 — determinism after ON→OFF→ON toggle cycle', () => {
  it('second ON produces byte-identical aggregates to first ON; dummy-001 is Toko Bogor Indah', () => {
    turnOn();
    const firstSnapshot = useDummyStore.getState().dummyEntities;

    // Toggle OFF
    act(() => {
      useDummyStore.getState().toggle();
    });
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    // Toggle ON again
    act(() => {
      useDummyStore.getState().toggle();
    });
    const secondSnapshot = useDummyStore.getState().dummyEntities;

    // Deep-equal aggregates across the toggle cycle
    expect(secondSnapshot).toEqual(firstSnapshot);

    // Canonical outlet identity
    const outlets = (secondSnapshot as Record<string, unknown>)?.outlets as Array<{
      id: string;
      name: string;
    }>;
    expect(outlets).toBeDefined();
    const first = outlets.find((o) => o.id === 'dummy-001');
    expect(first).toBeDefined();
    expect(first!.name).toBe('Toko Bogor Indah');
  });
});
