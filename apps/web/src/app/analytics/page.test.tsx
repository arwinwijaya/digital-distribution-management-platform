/**
 * Analytics page composition tests.
 *
 * The two loaders are mocked at the module boundary so each section's data and
 * failure mode can be driven independently — this is what proves scoped
 * degradation. Child components (MetricStrip / NeedsAttention / TrustLabel) are
 * NOT mocked: they are the units under test.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';

import type { AnalyticsInsight } from '@/app/analytics/types';
import type { AIData } from '@/app/analytics/api';

const mockLoadInsight = jest.fn();
const mockLoadAnalytics = jest.fn();

jest.mock('@/app/analytics/api', () => ({
  loadAnalyticsInsight: (...args: unknown[]) => mockLoadInsight(...args),
  loadAnalytics: (...args: unknown[]) => mockLoadAnalytics(...args),
}));

jest.mock('@/lib/api', () => ({
  getStoredToken: () => 'test-token',
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
}));

import AnalyticsPage from '@/app/analytics/page';

// ── Fixtures ────────────────────────────────────────────────────────────────

function makeInsight(overrides: Partial<AnalyticsInsight> = {}): AnalyticsInsight {
  return {
    comparison: {
      period: { start_date: '2026-08-20', end_date: '2026-09-18' },
      previous_period: { start_date: '2026-07-21', end_date: '2026-08-19' },
    },
    metrics: {
      orders_total: 120,
      sales_total: '1200000.00',
      outlets_total: 48,
      products_total: 25,
      payments_total: '900000.00',
      outstanding_total: '300000.00',
    },
    metrics_delta: {
      orders_total: { delta_percent: 5.0, direction: 'up' },
      sales_total: { delta_percent: 20.0, direction: 'up' },
      payments_total: { delta_percent: null, direction: 'neutral' },
      outstanding_total: { delta_percent: 0.0, direction: 'neutral' },
    },
    needs_attention: [],
    sales_trends: Array.from({ length: 30 }, (_, i) => ({
      period: `2026-09-${String(i + 1).padStart(2, '0')}`,
      orders_total: 4,
      sales_total: '40000.00',
      payments_total: '30000.00',
    })),
    outlet_performance: [],
    outlet_performance_total: 0,
    outlet_performance_has_more: false,
    ...overrides,
  };
}

function makeAIData(overrides: Partial<AIData> = {}): AIData {
  return {
    recommendations: [
      { product_id: 1, name: 'Beras Premium', price: '50000.00', reason: 'Terlaris' },
    ],
    recommendationMeta: {
      data_points: 120,
      data_sufficiency: { sufficient: false, level: 'insufficient', note: 'Data terbatas.' },
      measurement: { measured: true, note: 'Diukur dari riwayat 60 hari.' },
    },
    forecast: {
      predictions: [{ period: 'Minggu +1', forecast_sales: '100000.00', forecast_orders: 3 }],
      data_sufficiency: { sufficient: true, level: 'adequate', note: '' },
      method: 'moving-average',
      measurement: { measured: true, note: 'Rata-rata 4 minggu.' },
    },
    segmentation: {
      segments: [{ outlet_id: 1, outlet_name: 'Toko A', segment: 'High Value', confidence: 'high' }],
    },
    ...overrides,
  };
}

beforeEach(() => {
  mockLoadInsight.mockReset();
  mockLoadAnalytics.mockReset();
  mockLoadInsight.mockResolvedValue(makeInsight());
  mockLoadAnalytics.mockResolvedValue(makeAIData());
});

// ── Step 1+3: metric strip renders deltas ───────────────────────────────────

describe('AnalyticsPage — metric strip', () => {
  it('renders delta chips for deltable metrics and omits them for counts', async () => {
    mockLoadInsight.mockResolvedValue(makeInsight());

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('+20,0%')).toBeInTheDocument());
    expect(screen.getByText('belum ada pembanding')).toBeInTheDocument();
    expect(screen.getByText('0,0%')).toBeInTheDocument();

    // Point-in-time counts carry no delta chip.
    expect(screen.getByText('Outlet')).toBeInTheDocument();
    expect(screen.getByText('Produk')).toBeInTheDocument();
    expect(screen.queryByText('+0,0%')).not.toBeInTheDocument();
  });
});

// ── Step 5+7: trend + ranking with "dan N outlet lain" ──────────────────────

describe('AnalyticsPage — trend and ranking', () => {
  it('shows the closing "dan 5 outlet lain" line when the ranking is truncated', async () => {
    mockLoadInsight.mockResolvedValue(
      makeInsight({
        outlet_performance: Array.from({ length: 10 }, (_, i) => ({
          rank: i + 1,
          outlet_id: i + 1,
          outlet_name: `Toko ${i + 1}`,
          orders_total: 10 - i,
          sales_total: '10000.00',
        })),
        outlet_performance_total: 15,
        outlet_performance_has_more: true,
      }),
    );

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('dan 5 outlet lain')).toBeInTheDocument());
  });

  it('omits the closing line when every outlet is already ranked', async () => {
    mockLoadInsight.mockResolvedValue(
      makeInsight({
        outlet_performance: Array.from({ length: 7 }, (_, i) => ({
          rank: i + 1,
          outlet_id: i + 1,
          outlet_name: `Toko ${i + 1}`,
          orders_total: 10 - i,
          sales_total: '10000.00',
        })),
        outlet_performance_total: 7,
        outlet_performance_has_more: false,
      }),
    );

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('Toko 1')).toBeInTheDocument());
    expect(screen.queryByText(/outlet lain/)).not.toBeInTheDocument();
  });
});

// ── Step 9+11: needs attention strip + AI trust labels ──────────────────────

describe('AnalyticsPage — needs attention and trust labels', () => {
  it('lists decline and outstanding rows, labelling outstanding as total tunggakan', async () => {
    mockLoadInsight.mockResolvedValue(
      makeInsight({
        needs_attention: [
          {
            outlet_id: 10,
            outlet_name: 'Toko Bintang Timur',
            reason: 'sales_decline',
            delta_percent: -84.9,
            outstanding_total: '1985000.00',
          },
          {
            outlet_id: 48,
            outlet_name: 'Toko Makmur Sentosa',
            reason: 'outstanding_risk',
            delta_percent: null,
            outstanding_total: '8455500.00',
          },
        ],
      }),
    );

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('Toko Bintang Timur')).toBeInTheDocument());
    expect(screen.getByText('Toko Makmur Sentosa')).toBeInTheDocument();
    // Decline row: reason badge + the concrete (id-ID) drop magnitude.
    expect(screen.getByText('Penjualan turun 84,9%')).toBeInTheDocument();
    expect(screen.getByText('Penjualan turun')).toBeInTheDocument();
    // Outstanding row: reason badge + labelled tunggakan amount.
    expect(screen.getByText('Total tunggakan')).toBeInTheDocument();
    expect(screen.getByText('Rp 8.455.500')).toBeInTheDocument();
  });

  it('shows trust labels from the AI metadata', async () => {
    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('Data belum cukup')).toBeInTheDocument());
    expect(screen.getByText('Diukur dari riwayat 60 hari.')).toBeInTheDocument();
    expect(screen.getByText(/moving-average/)).toBeInTheDocument();
  });

  it('never renders literal "undefined" when metadata is absent', async () => {
    mockLoadAnalytics.mockResolvedValue(
      makeAIData({
        recommendationMeta: {
          data_points: 0,
          data_sufficiency: { level: '', note: '' },
          measurement: undefined as unknown as { measured: boolean; note: string },
        },
        forecast: {
          predictions: [],
          data_sufficiency: { level: '', note: '' },
          method: '',
          measurement: undefined as unknown as { measured: boolean; note: string },
        },
      }),
    );

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('Segmentasi')).toBeInTheDocument());
    expect(screen.queryByText(/undefined/)).not.toBeInTheDocument();
  });
});

// ── Step 13+15: scoped degradation, both directions ─────────────────────────

describe('AnalyticsPage — scoped degradation', () => {
  it('keeps AI cards visible when the insight load fails', async () => {
    mockLoadInsight.mockRejectedValue(new Error('Analitik tidak dapat dimuat.'));

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('Analitik tidak dapat dimuat.')).toBeInTheDocument());
    // AI cards still render.
    expect(screen.getByText('Rekomendasi produk')).toBeInTheDocument();
    expect(screen.getByText('Beras Premium')).toBeInTheDocument();
  });

  it('keeps the insight sections visible when the AI load fails', async () => {
    mockLoadAnalytics.mockRejectedValue(new Error('Analitik AI tidak dapat dimuat.'));

    render(<AnalyticsPage />);

    await waitFor(() =>
      expect(screen.getByText('Analitik AI tidak dapat dimuat.')).toBeInTheDocument(),
    );
    // Insight sections still render.
    expect(screen.getByText('+20,0%')).toBeInTheDocument();
  });
});
