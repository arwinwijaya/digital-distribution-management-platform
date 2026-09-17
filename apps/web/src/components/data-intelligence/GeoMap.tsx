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

/* Branded map pin: a teardrop filled with the brand blue (#2563eb) carrying the
   white "D" monogram, so map markers match the favicon/sidebar instead of the
   default Leaflet blue pin. Rendered as an inline SVG through `divIcon` rather
   than `L.icon`, because an SVG data URL would need `#2563eb` percent-encoded
   (a bare `#` starts a URL fragment and truncates the image). `className: ''`
   drops Leaflet's default `.leaflet-div-icon` white box/border.
   Tip sits at (16,42); iconAnchor aligns that tip with the outlet coordinate.
   The "D" path reuses the exact monogram geometry from `src/app/icon.svg`,
   scaled to fit the pin head (circle centred at (16,16), radius 16). */
const MARKER_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="42" viewBox="0 0 32 42">
  <path d="M16 0C7.163 0 0 7.163 0 16c0 11.5 16 26 16 26s16-14.5 16-26C32 7.163 24.837 0 16 0Z" fill="#2563eb" stroke="#ffffff" stroke-width="1.5"/>
  <path fill="#ffffff" fill-rule="evenodd" transform="translate(16 16) scale(0.5) translate(-32 -32)" d="M20 48 V16 H28 A16 16 0 0 1 28 48 Z M27 41 V23 A9 9 0 0 1 27 41 Z"/>
</svg>`;

const MARKER_ICON = L.divIcon({
  className: '',
  html: MARKER_SVG,
  iconSize: [32, 42],
  iconAnchor: [16, 42],
  popupAnchor: [0, -40],
});

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
      L.marker([p.latitude, p.longitude], { icon: MARKER_ICON })
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
