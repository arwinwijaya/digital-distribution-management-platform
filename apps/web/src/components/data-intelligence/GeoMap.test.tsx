import React from 'react';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

/* ------------------------------------------------------------------ */
/* Leaflet mock with a faithful _leaflet_id guard.                     */
/* ------------------------------------------------------------------ */

const mockLeafletMarkers: Array<{ latlng: unknown; popup: string | null }> = [];
const mockMapFn = jest.fn<void, [HTMLElement]>();
const mockTileLayerFn = jest.fn(() => ({ addTo: jest.fn() }));
const mockLatLngBoundsFn = jest.fn((xs: unknown) => ({ latlngs: xs }));

jest.mock('leaflet', () => {
  function map(container: HTMLElement) {
    mockMapFn(container as unknown as HTMLElement);
    const record = container as unknown as Record<string, number | undefined>;
    if (record._leaflet_id != null) {
      throw new Error('Map container is already initialized.');
    }
    record._leaflet_id = 42;
    let self: any;
    self = {
      setView: jest.fn(() => self),
      fitBounds: jest.fn(() => self),
      remove: jest.fn(() => {
        delete record._leaflet_id;
      }),
    };
    return self;
  }

  function marker(latlng: unknown) {
    const entry = { latlng, popup: null as string | null };
    mockLeafletMarkers.push(entry);
    let self: any;
    self = {
      addTo: jest.fn(() => self),
      bindPopup: jest.fn((html: string) => {
        entry.popup = html;
        return self;
      }),
    };
    return self;
  }

  return {
    __esModule: true,
    default: {
      map,
      tileLayer: mockTileLayerFn,
      marker,
      latLngBounds: mockLatLngBoundsFn,
    },
  };
});

jest.mock('leaflet/dist/leaflet.css', () => {});

beforeEach(() => {
  mockLeafletMarkers.length = 0;
  mockMapFn.mockClear();
  mockTileLayerFn.mockClear();
  mockLatLngBoundsFn.mockClear();
});

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
  it('GeoMap.tsx begins with "use client" for client-only rendering', () => {
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

  it('renders only valid points with OSM attribution and explicit height', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    render(<GeoMap points={POINTS_WITH_INVALID} />);

    expect(mockLeafletMarkers).toHaveLength(2);
    expect(mockLeafletMarkers.map((m) => m.latlng)).toEqual([
      [-6.2, 106.8],
      [-6.9175, 107.6191],
    ]);
    expect(mockLeafletMarkers[0].popup).toContain('Outlet A');

    const container = screen.getByTestId('geo-map');
    expect(container).toHaveStyle({ height: '420px' });

    expect(mockTileLayerFn).toHaveBeenCalledWith(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      expect.objectContaining({ attribution: expect.stringContaining('OpenStreetMap') }),
    );
  });

  it('shows empty state when no points are provided', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    render(<GeoMap points={[]} />);
    expect(screen.getByText(/tidak ada titik peta/i)).toBeInTheDocument();
    expect(screen.queryByTestId('geo-map')).not.toBeInTheDocument();
  });

  it('ignores points with invalid/out-of-range coordinates', async () => {
    const { isValidPoint } = await import('@/components/data-intelligence/GeoMap');
    expect(isValidPoint(VALID_POINTS[0])).toBe(true);
    expect(isValidPoint(POINTS_WITH_INVALID[2])).toBe(false);
    expect(isValidPoint(POINTS_WITH_INVALID[3])).toBe(false);
    expect(isValidPoint(POINTS_WITH_INVALID[4])).toBe(false);
  });

  it('survives StrictMode double-mount without "already initialized"', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;

    const { container } = render(
      <React.StrictMode>
        <GeoMap points={VALID_POINTS} />
      </React.StrictMode>,
    );

    // StrictMode runs effects twice: effect -> cleanup -> effect.
    // The second create would throw if cleanup did not clear _leaflet_id.
    expect(mockMapFn).toHaveBeenCalledTimes(2);

    const mapNode = container.querySelector('[data-testid="geo-map"] > div');
    expect(mapNode).not.toBeNull();
    expect((mapNode as unknown as Record<string, unknown>)._leaflet_id).toBe(42);
  });

  it('transitions empty -> non-empty -> empty without a hook-count crash or leak', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;

    const { rerender } = render(<GeoMap points={[]} />);
    expect(screen.getByTestId('geo-map-empty')).toBeInTheDocument();

    rerender(<GeoMap points={VALID_POINTS} />);
    expect(screen.getByTestId('geo-map')).toBeInTheDocument();
    expect(mockMapFn).toHaveBeenCalledTimes(1);

    rerender(<GeoMap points={[]} />);
    expect(screen.getByTestId('geo-map-empty')).toBeInTheDocument();
    // cleanup removed the map: no live _leaflet_id remains on the detached node
    expect(mockMapFn).toHaveBeenCalledTimes(1);
  });
});
