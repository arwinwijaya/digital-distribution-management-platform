'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import dynamic from 'next/dynamic';
import { Card, PageHeader } from '@/components/ui';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, clearStoredToken, getStoredToken } from '@/lib/api';
import { ApiError } from '@/lib/api-error';
import { useDummyRefresh } from '@/dummy/guards';
import {
  fetchForecastMeasurementData,
  fetchGeographicData,
  fetchRecommendationMeasurementData,
  fetchStockPlanningData,
  fetchSupplierPerformanceData,
  type ForecastMeasurementData,
  type GeographicData,
  type GeographicMapPoint,
  type RecommendationMeasurementData,
  type SupplierPerformanceData,
  type StockPlanningData,
} from '@/lib/data-intelligence-api';
import {
  computeFilteredCounts,
  type EligibleStatus,
  type Period,
} from '@/lib/geographic-filters';
import TerritoryTable from '@/components/data-intelligence/TerritoryTable';
import SupplierPerformanceTable from '@/components/data-intelligence/SupplierPerformanceTable';
import StockPlanningTable from '@/components/data-intelligence/StockPlanningTable';
import MeasurementCards from '@/components/data-intelligence/MeasurementCards';

const GeoMap = dynamic(() => import('@/components/data-intelligence/GeoMap'), { ssr: false });

const STATUS_CHIPS: readonly EligibleStatus[] = ['New', 'Confirmed', 'Delivered', 'Partially Paid'];
const PERIOD_CHIPS: readonly { value: Period; label: string }[] = [
  { value: 'today', label: 'Hari ini' },
  { value: '7d', label: '7 hari' },
  { value: '30d', label: '30 hari' },
];
const DEFAULT_STATUSES: readonly EligibleStatus[] = ['New', 'Confirmed'];
const DEFAULT_PERIOD: Period = '30d';

type GeographicSnapshotData = GeographicData & {
  snapshot_available?: boolean;
  geographic_section_available?: boolean;
  window: GeographicData['window'] | null;
  map_points: GeographicMapPoint[];
};

type DataIntelligenceSnapshot = {
  geographic: GeographicSnapshotData | null;
  suppliers: SupplierPerformanceData | null;
  stock: StockPlanningData | null;
  recommendationFunnel: RecommendationMeasurementData | null;
  forecast: ForecastMeasurementData | null;
};

function useAdminGuard() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [accessError, setAccessError] = useState<string | null>(null);
  useEffect(() => {
    const stored = getStoredToken();
    if (!stored) {
      setReady(true);
      return;
    }
    let active = true;
    fetch(apiUrl('/auth/me'), { headers: authHeaders(stored) })
      .then(async (response) => {
        const body = await response.json();
        if (!response.ok) throw new ApiError(response.status, body.message || 'Sesi tidak dapat diverifikasi.');
        return body.data?.role as string;
      })
      .then((role) => {
        if (!active) return;
        if (role !== 'admin') setAccessError('Akses ditolak');
        else setToken(stored);
        setReady(true);
      })
      .catch((reason) => {
        if (!active) return;
        if (reason instanceof ApiError && reason.status === 401) {
          clearStoredToken();
          setAccessError('Sesi berakhir. Silakan masuk kembali');
        } else setAccessError(reason instanceof Error ? reason.message : 'Sesi tidak dapat diverifikasi.');
        setReady(true);
      });
    return () => { active = false; };
  }, []);
  return { token, ready, accessError, setToken };
}

function formatSnapshotEnd(end: string): string {
  const [year, month, day] = end.split('-').map(Number);
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return `${String(day).padStart(2, '0')} ${months[month - 1]} ${year}`;
}

function SnapshotMetadata({ data }: { data: GeographicData | SupplierPerformanceData | StockPlanningData | RecommendationMeasurementData | ForecastMeasurementData | null }) {
  if (!data?.snapshot_version) return null;
  return (
    <p className="text-xs text-gray-500">
      Snapshot v{data.snapshot_version} · Data per {formatSnapshotEnd(data.window.end)} ({data.window.timezone})
    </p>
  );
}

export default function DataIntelligencePage() {
  const { token, ready, accessError, setToken } = useAdminGuard();
  const [snapshot, setSnapshot] = useState<DataIntelligenceSnapshot>({
    geographic: null,
    suppliers: null,
    stock: null,
    recommendationFunnel: null,
    forecast: null,
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<{ message: string; retryable: boolean } | null>(null);
  const [statuses, setStatuses] = useState<EligibleStatus[]>([...DEFAULT_STATUSES]);
  const [period, setPeriod] = useState<Period>(DEFAULT_PERIOD);

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
      if (reason instanceof ApiError) {
        const status = reason.status;
        let message: string;
        let retryable = false;
        if (status === 401) {
          clearStoredToken();
          message = 'Sesi berakhir. Silakan masuk kembali';
          retryable = false;
          setToken(null); // trigger login state
        } else if (status === 403) {
          message = 'Akses ditolak';
          retryable = false;
        } else {
          // network error (status === null) or 5xx
          message = 'Tidak dapat memuat data peta';
          retryable = true;
        }
        setError({ message, retryable });
        setSnapshot((prev) => ({ ...prev, geographic: null }));
      } else {
        setError({ message: reason instanceof Error ? reason.message : 'Gagal memuat data.', retryable: false });
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (token) void loadAll();
  }, [token, loadAll]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const urlStatuses = params.get('status')?.split(',').filter(Boolean) as EligibleStatus[] | undefined;
    const urlPeriod = params.get('period') as Period | undefined;
    if (urlStatuses && urlStatuses.every((s) => STATUS_CHIPS.includes(s))) setStatuses(urlStatuses);
    if (urlPeriod && PERIOD_CHIPS.some((c) => c.value === urlPeriod)) setPeriod(urlPeriod);
  }, []);

  // Mode Dummy is toggled from the Topbar while this page stays mounted, so the
  // effect above never re-runs (its deps are `token` and a stable `loadAll`).
  // Reload whenever the flag flips: ON short-circuits to dummyEntities inside
  // the API layer, OFF re-fetches the real snapshot.
  // NOTE: must stay ABOVE the early returns below — a hook after them would
  // change the hook count between the `!ready` and ready renders.
  useDummyRefresh(() => {
    if (token) void loadAll();
  });

  const geographic = snapshot.geographic;
  const snapshotWindow = geographic?.window ?? null;
  const sectionUnavailable =
    !geographic ||
    geographic.snapshot_available === false ||
    geographic.geographic_section_available === false;

  const filteredPoints = useMemo(() => {
    if (!geographic || !snapshotWindow) return [];
    return geographic.map_points
      .map((point) => ({ point, counts: computeFilteredCounts(point, statuses, period, snapshotWindow) }))
      .filter((entry) => entry.counts.filteredOrders > 0);
  }, [geographic, snapshotWindow, statuses, period]);

  const visiblePoints = useMemo(() => filteredPoints.map((entry) => entry.point), [filteredPoints]);

  const invalidCount = useMemo(
    () => visiblePoints.filter((point) => point.plottable === false).length,
    [visiblePoints],
  );

  const outletsWithoutDailyDetail = useMemo(() => {
    if (!geographic || !snapshotWindow || period === '30d') return 0;
    return geographic.map_points.filter((point) => {
      if (Array.isArray(point.daily_by_status)) return false;
      const windowCounts = computeFilteredCounts(point, statuses, '30d', snapshotWindow);
      const hasWindowOrders = windowCounts.legacyOnly
        ? Number(point.orders) > 0
        : windowCounts.filteredOrders > 0;
      return hasWindowOrders;
    }).length;
  }, [geographic, snapshotWindow, statuses, period]);

  let mapContent: React.ReactNode;
  if (sectionUnavailable) {
    mapContent = <p>Data peta belum tersedia</p>;
  } else if (visiblePoints.length > 0 && invalidCount === visiblePoints.length) {
    mapContent = <p>{`Outlet memiliki request tetapi koordinat belum tersedia (${invalidCount})`}</p>;
  } else if (visiblePoints.length === 0) {
    mapContent = <p>Tidak ada request pada periode ini</p>;
  } else {
    mapContent = <GeoMap points={visiblePoints} />;
  }

  function toggleStatus(status: EligibleStatus) {
    setStatuses((current) =>
      current.includes(status) ? current.filter((item) => item !== status) : [...current, status],
    );
  }

  function toggleSemua() {
    setStatuses((current) => (current.length === STATUS_CHIPS.length ? [] : [...STATUS_CHIPS]));
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat…</p>;
  if (accessError) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Intelijen Data" description="Visibilitas kinerja wilayah, supplier, stok dan pengukuran." />
        <div role="alert" className="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {accessError}
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }
  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Intelijen Data" description="Visibilitas kinerja wilayah, supplier, stok dan pengukuran." />
        {error?.message ? (
          <div role="alert" className="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {error.message}
          </div>
        ) : (
          <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
            Masuk untuk melihat data intelijen admin.
          </div>
        )}
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <PageHeader title="Intelijen Data" description="Visibilitas kinerja wilayah, supplier, stok dan pengukuran." />
      {error && (
        <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          <p>{error.message}</p>
          {error.retryable && (
            <button type="button" onClick={() => void loadAll()} className="mt-2 rounded-full border border-red-300 px-3 py-1">
              Coba lagi
            </button>
          )}
        </div>
      )}
      <SnapshotMetadata data={snapshot.geographic ?? snapshot.suppliers ?? snapshot.stock ?? snapshot.recommendationFunnel ?? snapshot.forecast} />

      <div className="space-y-4">
        <Card className="p-5">
          <div className="mb-3">
            <h3 className="text-base font-semibold text-gray-900">Wilayah</h3>
            <p className="mt-0.5 text-xs text-gray-500">Sebaran outlet dan penjualan per wilayah.</p>
          </div>
          <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Filter status">
            {STATUS_CHIPS.map((status) => (
              <button
                key={status}
                type="button"
                aria-pressed={statuses.includes(status)}
                onClick={() => toggleStatus(status)}
                className="rounded-full border border-gray-300 px-3 py-1 text-sm"
              >
                {status}
              </button>
            ))}
            <button
              type="button"
              aria-pressed={statuses.length === STATUS_CHIPS.length}
              onClick={toggleSemua}
              className="rounded-full border border-gray-300 px-3 py-1 text-sm"
            >
              Semua
            </button>
          </div>
          <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Filter periode">
            {PERIOD_CHIPS.map((chip) => (
              <button
                key={chip.value}
                type="button"
                aria-pressed={period === chip.value}
                onClick={() => setPeriod(chip.value)}
                className="rounded-full border border-gray-300 px-3 py-1 text-sm"
              >
                {chip.label}
              </button>
            ))}
          </div>
          {mapContent}
          <p data-testid="outlets-without-daily-detail" className="mt-2 text-xs text-gray-500">
            {`${outletsWithoutDailyDetail} outlet tanpa detail harian`}
          </p>
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
