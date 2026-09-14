import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, within } from '@testing-library/react';
import GeoMap from '@/components/data-intelligence/GeoMap';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

/* ------------------------------------------------------------------ */
/* Mock react-leaflet so we never import real browser Leaflet.        */
/* ------------------------------------------------------------------ */

jest.mock('react-leaflet', () => {
  function FakeMapContainer({ children, ...props }: Record<string, unknown> & { children?: React.ReactNode }) {
    return (
      <div data-testid="map-container" data-center={JSON.stringify(props.center)} data-zoom={props.zoom}>
        {children}
      </div>
    );
  }
  function FakeTileLayer({ attribution, url }: Record<string, string>) {
    return <div data-testid="tile-layer" data-attribution={attribution} data-url={url} />;
  }
  function FakeMarker({ position, children }: Record<string, unknown> & { children?: React.ReactNode }) {
    return (
      <div data-testid="marker" data-position={JSON.stringify(position)}>
        {children}
      </div>
    );
  }
  function FakePopup({ children }: { children?: React.ReactNode }) {
    return <div data-testid="popup">{children}</div>;
  }
  return { MapContainer: FakeMapContainer, TileLayer: FakeTileLayer, Marker: FakeMarker, Popup: FakePopup };
});

jest.mock('leaflet/dist/leaflet.css', () => {});

const VALID_POINTS: GeographicMapPoint[] = [
  { outlet_id: 1, outlet_name: 'Outlet A', territory: 'Jakarta Selatan', latitude: -6.2, longitude: 106.8, orders: 5, sales: '50000.00' },
  { outlet_id: 2, outlet_name: 'Outlet B', territory: 'Bandung', latitude: -6.9175, longitude: 107.6191, orders: 3, sales: '30000.00' },
];

const POINTS_WITH_INVALID: GeographicMapPoint[] = [
  ...VALID_POINTS,
  { outlet_id: 3, outlet_name: 'Outlet C', territory: 'Surabaya', latitude: NaN, longitude: NaN, orders: 0, sales: '0.00' },
  { outlet_id: 4, outlet_name: 'Outlet D', territory: 'Semarang', latitude: -7.0, longitude: null as unknown as number, orders: 1, sales: '5000.00' },
  { outlet_id: 5, outlet_name: 'Outlet E', territory: 'Medan', latitude: 91, longitude: 200, orders: 2, sales: '10000.00' },
];

describe('leaflet_map_renders_client_only_with_attribution_and_stable_height', () => {
  it('GeoMap.tsx begins with "use client" for client-only rendering', async () => {
    const fs = require('fs');
    const path = require('path');
    const src = fs.readFileSync(path.resolve(__dirname, '../data-intelligence/GeoMap.tsx'), 'utf8');
    expect(src.trimStart().startsWith("'use client'")).toBe(true);
  });

  it('page.tsx dynamically imports GeoMap with ssr: false', async () => {
    const fs = require('fs');
    const path = require('path');
    const src = fs.readFileSync(path.resolve(__dirname, '../../app/data-intelligence/page.tsx'), 'utf8');
    expect(src).toMatch(/ssr:\s*false/);
    expect(src).toMatch(/dynamic\(\(\)\s*=>\s*import\(.*GeoMap.*\)/);
  });

  it('renders only valid points, has explicit height and OSM attribution', () => {
    render(<GeoMap points={POINTS_WITH_INVALID} />);
    const markers = screen.getAllByTestId('marker');
    expect(markers).toHaveLength(2);

    const container = screen.getByTestId('geo-map');
    expect(container).toHaveStyle({ height: '420px' });

    const tileLayer = screen.getByTestId('tile-layer');
    expect(tileLayer).toHaveAttribute('data-url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
    expect(tileLayer.getAttribute('data-attribution')).toContain('OpenStreetMap');
  });

  it('shows empty state when no points are provided', () => {
    render(<GeoMap points={[]} />);
    expect(screen.getByText(/tidak ada titik peta/i)).toBeInTheDocument();
    expect(screen.queryByTestId('map-container')).not.toBeInTheDocument();
  });

  it('renders siblings tables independently of map state', () => {
    render(<GeoMap points={[]} />);
    expect(screen.getByText(/tidak ada titik peta/i)).toBeInTheDocument();
  });
});
