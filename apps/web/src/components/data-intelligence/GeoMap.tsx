'use client';

import { useEffect, useState } from 'react';
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

interface Props {
  points: GeographicMapPoint[];
}

const DEFAULT_CENTER: [number, number] = [-6.2, 106.8];

const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export function isValidPoint(point: GeographicMapPoint): boolean {
  const { latitude, longitude } = point;
  if (typeof latitude !== 'number' || typeof longitude !== 'number') return false;
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
  return latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180;
}

export default function GeoMap({ points }: Props) {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const validPoints = points.filter(isValidPoint);

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

  if (!mounted) {
    return (
      <div
        data-testid="geo-map-loading"
        className="h-[420px] w-full rounded-lg border border-gray-200 bg-gray-50"
        style={{ height: 420 }}
      />
    );
  }

  return (
    <div
      data-testid="geo-map"
      className="h-[420px] w-full rounded-lg border border-gray-200 overflow-hidden"
      style={{ height: 420 }}
    >
      <MapContainer center={DEFAULT_CENTER} zoom={10} style={{ height: '100%', width: '100%' }}>
        <TileLayer attribution={OSM_ATTRIBUTION} url={TILE_URL} />
        {validPoints.map((point) => (
          <Marker key={point.outlet_id} position={[point.latitude, point.longitude]}>
            <Popup>
              {point.outlet_name} — {point.territory} — {point.orders} pesanan — {point.sales}
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  );
}
