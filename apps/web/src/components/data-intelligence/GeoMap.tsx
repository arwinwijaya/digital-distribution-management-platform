'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

interface MapPoint extends GeographicMapPoint {
  /** Count after the page's active status/period filter. */
  filteredOrders?: number;
}

interface Props {
  points: MapPoint[];
  onSelectOutlet?: (point: MapPoint) => void;
  resetSignal?: number;
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

/**
 * Coordinate validation mirroring the backend authority
 * `GeographicAnalyticsService::isValidCoordinate()`. Same case table, same
 * booleans: rejects non-`number` values (numeric strings like "-6.2"), non
 * finite (`NaN`/`Infinity`), out-of-range latitude/longitude, and the exact
 * `(0,0)` Null-Island pair.
 */
export function isValidPoint(point: GeographicMapPoint): boolean {
  const { latitude, longitude } = point;
  if (typeof latitude !== 'number' || typeof longitude !== 'number') return false;
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return false;
  if (latitude === 0 && longitude === 0) return false;
  return true;
}

function popupHtml(point: MapPoint): string {
  return `${point.outlet_name} — ${point.territory} — ${point.filteredOrders ?? point.orders} pesanan — ${point.sales}`;
}

export default function GeoMap({ points, onSelectOutlet, resetSignal }: Props) {
  const mapRef = useRef<L.Map | null>(null);
  const markersRef = useRef<Map<number, L.Marker>>(new Map());
  const didInitialFitRef = useRef(false);
  const lastResetRef = useRef(resetSignal);
  const onSelectRef = useRef(onSelectOutlet);
  onSelectRef.current = onSelectOutlet;

  const [mapReady, setMapReady] = useState(false);
  const [tileFailed, setTileFailed] = useState(false);

  const validPoints = useMemo(() => points.filter(isValidPoint), [points]);

  /* A ref that always holds the latest valid points by outlet_id so retained
     markers' click handlers can look up the current point at click time,
     avoiding stale closures when points are updated (e.g., coordinates/counts). */
  const pointsByOutletIdRef = useRef<Map<number, MapPoint>>(new Map());
  pointsByOutletIdRef.current = new Map(validPoints.map((p) => [p.outlet_id, p]));

  /* Stable Leaflet lifecycle. The map is created once when the container node
     attaches and destroyed when it detaches (empty state / unmount). Filter
     changes never recreate the instance or the tile layer — only the marker
     layer below is diffed. */
  const setContainer = useCallback((node: HTMLDivElement | null) => {
    if (!node) {
      mapRef.current?.remove();
      mapRef.current = null;
      markersRef.current.clear();
      setMapReady(false);
      return;
    }

    /* Defensive: clear a stale Leaflet instance left on a recycled node. */
    const record = node as unknown as { _leaflet_id?: number };
    if (record._leaflet_id != null) {
      mapRef.current?.remove();
      mapRef.current = null;
      markersRef.current.clear();
    }

    if (mapRef.current) return;

    const map = L.map(node).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    mapRef.current = map;
    didInitialFitRef.current = false;
    setTileFailed(false);

    const tiles = L.tileLayer(TILE_URL, { attribution: OSM_ATTRIBUTION });
    tiles.on('tileerror', () => setTileFailed(true));
    tiles.addTo(map);

    setMapReady(true);
  }, []);

  /* Marker-layer diff keyed by outlet_id. Runs whenever the visible point set
     changes (filter/period) or the map (re)attaches. The map instance itself
     is untouched — only markers are added, removed, or repositioned. */
  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    const next = new Map(validPoints.map((p) => [p.outlet_id, p]));

    markersRef.current.forEach((marker, outletId) => {
      if (!next.has(outletId)) {
        marker.remove();
        markersRef.current.delete(outletId);
      }
    });

    validPoints.forEach((point) => {
      const existing = markersRef.current.get(point.outlet_id);
      if (existing) {
        existing.setLatLng([point.latitude, point.longitude]);
        existing.bindPopup(popupHtml(point));
        return;
      }
      const marker = L.marker([point.latitude, point.longitude], { icon: MARKER_ICON })
        .addTo(map)
        .bindPopup(popupHtml(point));
      marker.on('click', () => {
        const current = pointsByOutletIdRef.current.get(point.outlet_id) ?? point;
        onSelectRef.current?.(current);
      });
      markersRef.current.set(point.outlet_id, marker);
    });
  }, [validPoints, mapReady]);

  /* fitBounds scope: only on the first render with valid data (initial load) or
     on an explicit reset request. Filter changes preserve center/zoom. */
  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    const resetChanged = resetSignal !== lastResetRef.current;
    lastResetRef.current = resetSignal;

    if (validPoints.length === 0) return;
    if (didInitialFitRef.current && !resetChanged) return;

    map.fitBounds(
      L.latLngBounds(validPoints.map((p) => [p.latitude, p.longitude])),
      { padding: [24, 24] },
    );
    didInitialFitRef.current = true;
  }, [validPoints, mapReady, resetSignal]);

  /* ---------- empty state ---------- */
  if (points.length === 0) {
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
    <div className="w-full">
      <div
        data-testid="geo-map"
        className="h-[420px] w-full overflow-hidden rounded-lg border border-gray-200"
        style={{ height: 420 }}
      >
        <div ref={setContainer} style={{ height: '100%', width: '100%' }} />
      </div>
      {tileFailed && (
        <p
          data-testid="geo-map-tile-fallback"
          role="status"
          className="mt-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
        >
          Citra peta tidak dapat dimuat. Daftar outlet di bawah tetap dapat digunakan.
        </p>
      )}
      <ul aria-label="Daftar outlet peta" className="mt-2 max-h-40 space-y-1 overflow-y-auto">
        {validPoints.map((point) => (
          <li key={point.outlet_id}>
            <button
              type="button"
              onClick={() => onSelectRef.current?.(point)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  onSelectRef.current?.(point);
                }
              }}
            >
              {point.outlet_name} — {point.territory} — {point.filteredOrders ?? point.orders} pesanan
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}
