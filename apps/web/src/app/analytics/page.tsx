'use client';

import { useCallback, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import {
  loadAnalytics,
  loadAnalyticsInsight,
  type AIData,
} from '@/app/analytics/api';
import type { AnalyticsInsight } from '@/app/analytics/types';
import { useDummyRefresh } from '@/dummy/guards';
import { Badge, Card, EmptyState, PageHeader } from '@/components/ui';
import { SalesTrendChart, OutletPerformanceChart } from '@/components/Charts';
import MetricStrip from '@/components/analytics/MetricStrip';
import NeedsAttention from '@/components/analytics/NeedsAttention';
import TrustLabel from '@/components/analytics/TrustLabel';

/** Titled card wrapper shared by the three insight sections (Rule of Three). */
function Section({
  title,
  description,
  children,
}: {
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <Card className="p-5">
      <div className="mb-4">
        <h2 className="font-semibold text-gray-900">{title}</h2>
        <p className="mt-1 text-xs text-gray-500">{description}</p>
      </div>
      {children}
    </Card>
  );
}

/**
 * Analitik home — composes the strategic insight sections (BI) with the
 * deterministic AI cards.
 *
 * The two loads are deliberately independent: a failure in one must never blank
 * the other (Rule 7.1). `useDummyRefresh` re-drives BOTH loads on a dummy-flag
 * flip so neither section goes stale.
 */
export default function AnalyticsPage() {
  const [token, setToken] = useState<string | null>(null);

  const [insight, setInsight] = useState<AnalyticsInsight | null>(null);
  const [insightLoading, setInsightLoading] = useState(false);
  const [insightError, setInsightError] = useState<string | null>(null);

  const [data, setData] = useState<AIData | null>(null);
  const [aiLoading, setAiLoading] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);

  const loadInsight = useCallback(async (authToken: string) => {
    setInsightLoading(true);
    setInsightError(null);
    try {
      setInsight(await loadAnalyticsInsight(authToken));
    } catch (reason) {
      setInsight(null);
      setInsightError(reason instanceof Error ? reason.message : 'Analitik tidak dapat dimuat.');
    } finally {
      setInsightLoading(false);
    }
  }, []);

  const loadAI = useCallback(async (authToken: string) => {
    setAiLoading(true);
    setAiError(null);
    try {
      setData(await loadAnalytics(authToken));
    } catch (reason) {
      setData(null);
      setAiError(reason instanceof Error ? reason.message : 'Analitik AI tidak dapat dimuat.');
    } finally {
      setAiLoading(false);
    }
  }, []);

  const loadAll = useCallback(
    (authToken: string) => {
      void loadInsight(authToken);
      void loadAI(authToken);
    },
    [loadInsight, loadAI],
  );

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    if (stored) loadAll(stored);
  }, [loadAll]);

  useDummyRefresh(() => {
    if (token) loadAll(token);
  });

  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Analitik" description="Insight yang dapat dijelaskan dari data pesanan Anda." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk untuk melihat rekomendasi dan prakiraan.
        </div>
        <LoginForm
          onLogin={(nextToken) => {
            setToken(nextToken);
            loadAll(nextToken);
          }}
        />
      </div>
    );
  }

  if (insightLoading && aiLoading && !insight && !data) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Analitik" description="Insight dari data operasional." />
        <p className="text-sm text-gray-500">Memuat analitik...</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader
        title="Analitik"
        description="Perbandingan periode, outlet prioritas, dan rekomendasi deterministik dari data pesanan."
      />

      {/* ── Insight sections (independent of the AI cards) ─────────────── */}
      {insightError && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {insightError}
        </p>
      )}
      {insight && (
        <div className="space-y-5">
          <MetricStrip insight={insight} />

          <Section
            title="Tren penjualan harian"
            description="30 hari terakhir pada periode perbandingan."
          >
            <SalesTrendChart points={insight.sales_trends} />
          </Section>

          <Section
            title="Performa outlet"
            description="Peringkat berdasarkan nilai penjualan pada periode berjalan."
          >
            {insight.outlet_performance.length === 0 ? (
              <EmptyState
                title="Belum ada penjualan outlet"
                description="Peringkat akan muncul setelah ada pesanan pada periode ini."
              />
            ) : (
              <>
                <OutletPerformanceChart outlets={insight.outlet_performance} />
                {insight.outlet_performance_total - insight.outlet_performance.length > 0 && (
                  <p className="mt-3 text-xs text-gray-500">
                    dan {insight.outlet_performance_total - insight.outlet_performance.length} outlet lain
                  </p>
                )}
              </>
            )}
          </Section>

          <Section
            title="Perlu perhatian"
            description="Outlet dengan penurunan penjualan tajam atau tunggakan menumpuk."
          >
            <NeedsAttention items={insight.needs_attention} />
          </Section>
        </div>
      )}

      {/* ── AI cards (independent of the insight sections) ─────────────── */}
      {aiError && (
        <p role="alert" className="mt-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {aiError}
        </p>
      )}
      {data && (
        <div className="mt-5 grid gap-5 lg:grid-cols-3">
          <Card className="p-5 lg:col-span-2">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h2 className="font-semibold text-gray-900">Rekomendasi produk</h2>
                <p className="mt-1 text-xs text-gray-500">
                  Berdasarkan {data.recommendationMeta.data_points} pesanan selesai.
                </p>
                <TrustLabel
                  level={data.recommendationMeta.data_sufficiency?.level}
                  measurement={data.recommendationMeta.measurement}
                />
              </div>
              <Badge variant="blue">Heuristik</Badge>
            </div>
            {data.recommendations.length === 0 ? (
              <EmptyState
                title="Belum ada rekomendasi"
                description="Rekomendasi akan muncul setelah ada riwayat pembelian."
              />
            ) : (
              <div className="divide-y divide-gray-100">
                {data.recommendations.map((item) => (
                  <div key={item.product_id} className="flex items-center justify-between gap-4 py-3">
                    <div>
                      <p className="font-medium text-gray-900">{item.name}</p>
                      <p className="text-xs text-gray-500">{item.reason}</p>
                    </div>
                    <span className="whitespace-nowrap font-semibold text-primary-700">
                      Rp {Number(item.price).toLocaleString('id-ID')}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card className="p-5">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-semibold text-gray-900">Segmentasi</h2>
              <span className="text-2xl">🎯</span>
            </div>
            {data.segmentation.segment ? (
              <>
                <p className="text-2xl font-bold text-primary-700">{data.segmentation.segment}</p>
                <p className="mt-1 text-sm text-gray-500">Tingkat keyakinan: {data.segmentation.confidence}</p>
              </>
            ) : (
              <ul className="space-y-3">
                {(data.segmentation.segments || []).slice(0, 10).map((segment) => (
                  <li key={segment.outlet_id} className="flex items-center justify-between gap-2 text-sm">
                    <span className="truncate text-gray-700">{segment.outlet_name}</span>
                    <Badge variant="gray">{segment.segment}</Badge>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card className="p-5 lg:col-span-3">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h2 className="font-semibold text-gray-900">Prakiraan 4 minggu</h2>
                <p className="mt-1 text-xs text-gray-500">
                  Ini adalah heuristik, bukan probabilitas terkalibrasi.
                </p>
                <TrustLabel
                  level={data.forecast.data_sufficiency?.level}
                  method={data.forecast.method}
                  measurement={data.forecast.measurement}
                />
              </div>
              <span className="text-2xl">📈</span>
            </div>
            {data.forecast.predictions.length === 0 ? (
              <EmptyState
                title="Data belum cukup"
                description="Belum cukup riwayat positif untuk membuat prakiraan."
              />
            ) : (
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {data.forecast.predictions.map((point) => (
                  <div key={point.period} className="rounded-xl bg-gray-50 p-4">
                    <p className="text-sm text-gray-500">{point.period}</p>
                    <p className="mt-2 text-lg font-bold text-gray-900">
                      Rp {Number(point.forecast_sales).toLocaleString('id-ID')}
                    </p>
                    <p className="mt-1 text-xs text-gray-500">{point.forecast_orders} pesanan</p>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </div>
      )}
    </div>
  );
}
