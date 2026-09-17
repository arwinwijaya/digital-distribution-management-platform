'use client';

import { useCallback, useEffect, useState } from 'react';
import dynamic from 'next/dynamic';
import { Card, PageHeader } from '@/components/ui';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { useDummyRefresh } from '@/dummy/guards';
import {
  fetchForecastMeasurementData,
  fetchGeographicData,
  fetchRecommendationMeasurementData,
  fetchStockPlanningData,
  fetchSupplierPerformanceData,
  type ForecastMeasurementData,
  type GeographicData,
  type RecommendationMeasurementData,
  type SupplierPerformanceData,
  type StockPlanningData,
} from '@/lib/data-intelligence-api';
import TerritoryTable from '@/components/data-intelligence/TerritoryTable';
import SupplierPerformanceTable from '@/components/data-intelligence/SupplierPerformanceTable';
import StockPlanningTable from '@/components/data-intelligence/StockPlanningTable';
import MeasurementCards from '@/components/data-intelligence/MeasurementCards';

const GeoMap = dynamic(() => import('@/components/data-intelligence/GeoMap'), { ssr: false });

type DataIntelligenceSnapshot = {
  geographic: GeographicData | null;
  suppliers: SupplierPerformanceData | null;
  stock: StockPlanningData | null;
  recommendationFunnel: RecommendationMeasurementData | null;
  forecast: ForecastMeasurementData | null;
};

function useAdminGuard() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
  }, []);
  return { token, ready, setToken };
}

function SnapshotMetadata({ data }: { data: GeographicData | SupplierPerformanceData | StockPlanningData | RecommendationMeasurementData | ForecastMeasurementData | null }) {
  if (!data?.snapshot_version) return null;
  return (
    <p className="text-xs text-gray-500">
      Snapshot v{data.snapshot_version} · Window {data.window?.start}–{data.window?.end} ({data.window?.timezone})
    </p>
  );
}

export default function DataIntelligencePage() {
  const { token, ready, setToken } = useAdminGuard();
  const [snapshot, setSnapshot] = useState<DataIntelligenceSnapshot>({
    geographic: null,
    suppliers: null,
    stock: null,
    recommendationFunnel: null,
    forecast: null,
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [geo, sup, stock, rec, fc] = await Promise.all([
        fetchGeographicData(),
        fetchSupplierPerformanceData(),
        fetchStockPlanningData(),
        fetchRecommendationMeasurementData(),
        fetchForecastMeasurementData(),
      ]);
      setSnapshot({ geographic: geo, suppliers: sup, stock, recommendationFunnel: rec, forecast: fc });
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Gagal memuat data.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (token) void loadAll();
  }, [token, loadAll]);

  // Mode Dummy is toggled from the Topbar while this page stays mounted, so the
  // effect above never re-runs (its deps are `token` and a stable `loadAll`).
  // Reload whenever the flag flips: ON short-circuits to dummyEntities inside
  // the API layer, OFF re-fetches the real snapshot.
  // NOTE: must stay ABOVE the early returns below — a hook after them would
  // change the hook count between the `!ready` and ready renders.
  useDummyRefresh(() => {
    if (token) void loadAll();
  });

  if (!ready) return <p className="text-sm text-gray-500">Memuat…</p>;
  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Intelijen Data" description="Visibilitas kinerja wilayah, supplier, stok dan pengukuran." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk untuk melihat data intelijen admin.
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <PageHeader title="Intelijen Data" description="Visibilitas kinerja wilayah, supplier, stok dan pengukuran." />
      {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</p>}
      <SnapshotMetadata data={snapshot.geographic ?? snapshot.suppliers ?? snapshot.stock ?? snapshot.recommendationFunnel ?? snapshot.forecast} />

      <div className="space-y-4">
        <Card className="p-5">
          <div className="mb-3">
            <h3 className="text-base font-semibold text-gray-900">Wilayah</h3>
            <p className="mt-0.5 text-xs text-gray-500">Sebaran outlet dan penjualan per wilayah.</p>
          </div>
          <GeoMap points={snapshot.geographic?.map_points ?? []} />
          <div className="mt-4">
            <TerritoryTable territories={snapshot.geographic?.table ?? []} loading={loading} />
          </div>
        </Card>

        <Card className="p-5">
          <div className="mb-3">
            <h3 className="text-base font-semibold text-gray-900">Kinerja Supplier</h3>
            <p className="mt-0.5 text-xs text-gray-500">Tingkat pemenuhan, ketepatan waktu, dan kelengkapan katalog supplier.</p>
          </div>
          <SupplierPerformanceTable suppliers={snapshot.suppliers?.suppliers ?? []} loading={loading} />
        </Card>

        <Card className="p-5">
          <div className="mb-3">
            <h3 className="text-base font-semibold text-gray-900">Perencanaan Stok</h3>
            <p className="mt-0.5 text-xs text-gray-500">Sinyal reorder dan proyeksi permintaan harian per produk.</p>
          </div>
          <StockPlanningTable items={snapshot.stock?.items ?? []} loading={loading} />
        </Card>

        <Card className="p-5">
          <div className="mb-3">
            <h3 className="text-base font-semibold text-gray-900">Pengukuran</h3>
            <p className="mt-0.5 text-xs text-gray-500">Funnel rekomendasi dan akurasi prakiraan.</p>
          </div>
          <MeasurementCards
            funnel={snapshot.recommendationFunnel?.funnel ?? []}
            forecast={
              snapshot.forecast
                ? {
                    status: snapshot.forecast.status,
                    wape: snapshot.forecast.wape,
                    accuracy_percent: snapshot.forecast.accuracy_percent,
                  }
                : null
            }
            loading={loading}
          />
        </Card>
      </div>
    </div>
  );
}
