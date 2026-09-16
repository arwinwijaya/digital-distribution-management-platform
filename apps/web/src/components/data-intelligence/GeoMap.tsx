'use client';

import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

interface Props {
  points: GeographicMapPoint[];
}

const DEFAULT_CENTER: L.LatLngExpression = [-6.2, 106.8];
const DEFAULT_ZOOM = 10;

const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export function isValidPoint(point: GeographicMapPoint): boolean {
  const { latitude, longitude } = point;
  if (typeof latitude !== 'number' || typeof longitude !== 'number') return false;
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
  return latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180;
}

export default function GeoMap({ points }: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<L.Map | null>(null);

  const validPoints = points.filter(isValidPoint);

  /* Leaflet lifecycle. Must stay ABOVE the empty-state early return so the hook
     count is identical on every render (empty <-> non-empty transitions).
     `validPoints` derives from `points`, so `points` is the sole dependency.
     Tradeoff: a new `points` identity tears down and rebuilds the map + tiles. */
  useEffect(() => {
    const node = containerRef.current;
    /* Empty state renders no container div -> nothing to initialize. */
    if (!node) return;

    /* Defensive: if a stale Leaflet instance remains (e.g. from a remount that
       bypassed normal cleanup), remove it before creating a new one. */
    const maybeLeafletNode = node as unknown as { _leaflet_id?: number };
    if (maybeLeafletNode._leaflet_id != null) {
      mapRef.current?.remove();
      mapRef.current = null;
    }

    const map = L.map(node).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    mapRef.current = map;

    L.tileLayer(TILE_URL, { attribution: OSM_ATTRIBUTION }).addTo(map);

    validPoints.forEach((p) => {
      L.marker([p.latitude, p.longitude])
        .addTo(map)
        .bindPopup(
          `${p.outlet_name} — ${p.territory} — ${p.orders} pesanan — ${p.sales}`,
        );
    });

    return () => {
      map.remove();          // deletes _leaflet_id from the container
      mapRef.current = null;
    };
  }, [points]);

  /* ---------- empty state ---------- */
  if (points.length === 0 || validPoints.length === 0) {
    return (
      <div
        data-testid="geo-map-empty"
        className="flex h-[420px] w-full items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-500"
        style={{ height: 420 }}
      >
        Tidak ada titik peta.
      </div>
    );
  }

  return (
    <div
      data-testid="geo-map"
      className="h-[420px] w-full rounded-lg border border-gray-200 overflow-hidden"
      style={{ height: 420 }}
    >
      <div ref={containerRef} style={{ height: '100%', width: '100%' }} />
    </div>
  );
}
