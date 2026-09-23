'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { getTrack, type TrackResponse, type TrackPoint } from './api';
import { loadDeliveries, type Delivery } from '@/app/delivery/api';
import { useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { Button, Card, EmptyState, PageHeader, Select } from '@/components/ui';
import { formatDateTime } from '@/lib/admin-table';
import GeoMap from '@/components/data-intelligence/GeoMap';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

const POLL_INTERVAL_MS = 15_000;

function toGeoMapPoint(p: TrackPoint, label: string): GeographicMapPoint {
  return {
    outlet_id: 0,
    outlet_name: label,
    territory: 'Live tracking',
    latitude: p.latitude,
    longitude: p.longitude,
    orders: 0,
    sales: '—',
  };
}

export default function TrackingPage() {
  const searchParams = useSearchParams();
  const { isDummy } = useDummyStore();
  const [token, setToken] = useState<string | null>(null);
  const [deliveryId, setDeliveryId] = useState<number | null>(null);
  const [track, setTrack] = useState<TrackResponse | null>(null);
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [lastUpdated, setLastUpdated] = useState<string | null>(null);

  const mountedRef = useRef(true);
  const tokenRef = useRef<string | null>(null);
  const deliveryIdRef = useRef<number | null>(null);

  tokenRef.current = token;
  deliveryIdRef.current = deliveryId;

  // Read delivery id from URL on mount / URL change.
  useEffect(() => {
    const id = searchParams.get('delivery');
    setDeliveryId(id ? Number(id) : null);
  }, [searchParams]);

  // Restore token once.
  useEffect(() => {
    setToken(localStorage.getItem('ddp_token'));
  }, []);

  const fetchTrack = useCallback(async () => {
    const currentToken = tokenRef.current;
    const currentId = deliveryIdRef.current;
    if (!currentToken || !currentId) return;
    setLoading(true);
    setError(null);
    try {
      const data = await getTrack(currentId);
      if (!mountedRef.current) return;
      setTrack(data);
      setLastUpdated(new Date().toLocaleTimeString('id-ID'));
    } catch (reason) {
      if (!mountedRef.current) return;
      setError(reason instanceof Error ? reason.message : 'Tracking gagal dimuat.');
    } finally {
      if (mountedRef.current) setLoading(false);
    }
  }, []);

  const loadDeliveryList = useCallback(async () => {
    const currentToken = tokenRef.current;
    if (!currentToken) return;
    try {
      setDeliveries(await loadDeliveries(currentToken));
    } catch {
      /* list is best-effort; the picker degrades to empty */
    }
  }, []);

  // Load the delivery picker list whenever the token becomes available.
  useEffect(() => {
    if (token) void loadDeliveryList();
  }, [token, loadDeliveryList]);

  // Re-fetch when dummy mode flips.
  useDummyRefresh(() => {
    void fetchTrack();
    void loadDeliveryList();
  });

  // Fetch immediately + poll while a delivery is selected. Cleanup on unmount
  // or when the selection changes.
  useEffect(() => {
    if (!token || !deliveryId) return;
    void fetchTrack();
    const interval = setInterval(() => void fetchTrack(), POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, [token, deliveryId, fetchTrack]);

  // Track mount state for late async guards.
  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const handleDeliveryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setDeliveryId(e.target.value ? Number(e.target.value) : null);
  };

  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Live Tracking" description="Pantau posisi driver secara real-time." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk memantau tracking.
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader
        title="Live Tracking"
        description="Peta posisi driver + polling 15 detik. Pilih pengiriman untuk memulai."
      />

      {/* Delivery selector */}
      <Card className="mb-5 p-4">
        <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
          <Select
            value={deliveryId ? String(deliveryId) : ''}
            onChange={handleDeliveryChange}
            aria-label="Pilih pengiriman"
          >
            <option value="">Pilih pengiriman…</option>
            {deliveries
              .filter((d) => d.status === 'assigned' || d.status === 'in_progress')
              .map((d) => (
                <option key={d.id} value={String(d.id)}>
                  Pesanan #{d.order_id} — {d.driver?.name ?? `Driver #${d.driver_id}`} ({d.status})
                </option>
              ))}
          </Select>
          <Button variant="secondary" onClick={() => void fetchTrack()} disabled={loading || !deliveryId}>
            {loading ? 'Memuat...' : 'Muat tracking'}
          </Button>
        </div>
        <p className="mt-2 text-xs text-gray-500">
          {deliveryId ? `Menampilkan tracking untuk delivery #${deliveryId}` : 'Belum ada pengiriman yang dipilih.'}
        </p>
      </Card>

      {error && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {error}
        </p>
      )}

      {deliveryId ? (
        <Card className="overflow-hidden">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <div>
              <h3 className="text-lg font-semibold text-gray-900">Tracking Delivery #{deliveryId}</h3>
              <div className="mt-0.5 flex flex-wrap items-center gap-3 text-sm text-gray-600">
                {track && (
                  <>
                    <span>Status: <span className="font-medium">{track.status}</span></span>
                    <span>Driver: <span className="font-medium">{track.driver?.name ?? '—'}</span></span>
                  </>
                )}
              </div>
            </div>
            <div className="flex items-center gap-3 text-xs text-gray-500">
              {lastUpdated && <span>Terakhir diperbarui: {lastUpdated}</span>}
              {isDummy && <span className="px-2 py-0.5 rounded bg-gray-100">Mode dummy</span>}
            </div>
          </div>

          <div className="p-5">
            {track?.last_position ? (
              <GeoMap points={[toGeoMapPoint(track.last_position, 'Posisi terkini')]} />
            ) : (
              <>
                <EmptyState
                  icon={<span>📍</span>}
                  title="Belum ada lokasi"
                  description="Driver belum mengirim ping lokasi untuk pengiriman ini."
                />
                <div className="mt-4">
                  <GeoMap points={[]} />
                </div>
              </>
            )}

            {track?.pings && track.pings.length > 1 && (
              <div className="mt-5">
                <h4 className="mb-2 text-sm font-medium text-gray-700">Riwayat ping ({track.pings.length})</h4>
                <div className="max-h-48 overflow-y-auto space-y-1 text-xs">
                  {track.pings.slice(0, 20).map((p) => (
                    <div key={p.recorded_at} className="flex justify-between gap-3 text-gray-600">
                      <span>{formatDateTime(p.recorded_at)}</span>
                      <span className="font-mono">
                        {p.latitude.toFixed(6)}, {p.longitude.toFixed(6)} ({p.accuracy_m ?? '?'} m)
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </Card>
      ) : (
        <EmptyState
          icon={<span>🚚</span>}
          title="Pilih pengiriman untuk memulai tracking"
          description="Gunakan dropdown di atas untuk memilih pengiriman yang aktif."
        />
      )}
    </div>
  );
}