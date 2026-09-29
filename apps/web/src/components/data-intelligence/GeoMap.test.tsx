import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent, act } from '@testing-library/react';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

/* ------------------------------------------------------------------ */
/* Leaflet mock with a faithful _leaflet_id guard.                     */
/* ------------------------------------------------------------------ */

const mockLeafletMarkers: Array<{
  latlng: unknown;
  popup: string | null;
  icon: any;
  remove: jest.Mock;
  on: jest.Mock;
  setLatLng: jest.Mock;
  handlers: Record<string, (...args: any[]) => void>;
}> = [];
const mockMapInstances: any[] = [];
const mockTileLayers: any[] = [];
const mockMapFn = jest.fn<void, [HTMLElement]>();
const mockTileLayerFn = jest.fn((url: string, opts?: unknown) => {
  const layer: any = {
    handlers: {} as Record<string, (...args: any[]) => void>,
    addTo: jest.fn(() => layer),
    on: jest.fn((event: string, handler: (...args: any[]) => void) => {
      layer.handlers[event] = handler;
      return layer;
    }),
  };
  mockTileLayers.push(layer);
  return layer;
});
const mockLatLngBoundsFn = jest.fn((xs: unknown) => ({ latlngs: xs }));
const mockDivIconFn = jest.fn((opts: unknown) => ({ options: opts }));

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
    mockMapInstances.push(self);
    return self;
  }

  function marker(latlng: unknown, opts?: { icon?: unknown }) {
    const entry = {
      latlng,
      popup: null as string | null,
      icon: opts?.icon,
      remove: jest.fn(),
      on: jest.fn((event: string, handler: (...args: any[]) => void) => {
        entry.handlers[event] = handler;
        return self;
      }),
      setLatLng: jest.fn((next: unknown) => {
        entry.latlng = next;
        return self;
      }),
      handlers: {} as Record<string, (...args: any[]) => void>,
    };
    mockLeafletMarkers.push(entry);
    let self: any;
    self = {
      addTo: jest.fn(() => self),
      bindPopup: jest.fn((html: string) => {
        entry.popup = html;
        return self;
      }),
      on: entry.on,
      remove: entry.remove,
      setLatLng: entry.setLatLng,
    };
    return self;
  }

  return {
    __esModule: true,
    default: {
      map,
      tileLayer: mockTileLayerFn,
      marker,
      divIcon: mockDivIconFn,
      latLngBounds: mockLatLngBoundsFn,
    },
  };
});

jest.mock('leaflet/dist/leaflet.css', () => {});

beforeEach(() => {
  mockLeafletMarkers.length = 0;
  mockMapInstances.length = 0;
  mockTileLayers.length = 0;
  mockMapFn.mockClear();
  mockTileLayerFn.mockClear();
  mockLatLngBoundsFn.mockClear();
  mockDivIconFn.mockClear();
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

  it('renders every marker with the branded pin icon (not the Leaflet default)', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    render(<GeoMap points={VALID_POINTS} />);

    expect(mockLeafletMarkers).toHaveLength(2);
    for (const marker of mockLeafletMarkers) {
      const opts = marker.icon.options as {
        html: string;
        iconAnchor: [number, number];
        className: string;
      };
      // Brand blue + the monogram "D" path prove it is our custom pin.
      expect(opts.html).toContain('#2563eb');
      expect(opts.html).toContain('M20 48');
      // Anchor at the pin tip so it points at the outlet coordinate.
      expect(opts.iconAnchor).toEqual([16, 42]);
      // Default Leaflet div-icon box/border styling must be stripped.
      expect(opts.className).toBe('');
    }
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

    // Stable instance: StrictMode's double-invocation must NOT rebuild the map.
    // (The legacy `useEffect([points])` pattern created it twice.)
    expect(mockMapFn).toHaveBeenCalledTimes(1);

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

// Points for Cycle 1 marker diff test
const POINT_A: GeographicMapPoint = {
  outlet_id: 1,
  outlet_name: 'Outlet A',
  territory: 'Jakarta Selatan',
  latitude: -6.2,
  longitude: 106.8,
  orders: 5,
  sales: '50000.00',
};
const POINT_B: GeographicMapPoint = {
  outlet_id: 2,
  outlet_name: 'Outlet B',
  territory: 'Bandung',
  latitude: -6.9175,
  longitude: 107.6191,
  orders: 3,
  sales: '30000.00',
};
const POINT_C: GeographicMapPoint = {
  outlet_id: 3,
  outlet_name: 'Outlet C',
  territory: 'Surabaya',
  latitude: -7.25,
  longitude: 112.75,
  orders: 7,
  sales: '70000.00',
};

describe('marker_layer_diff_with_stable_map_instance', () => {
  it('given points A+B rendered, when points change to A+C, then L.Map instance is identical, B marker removed, C marker added, center/zoom unchanged', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const { rerender } = render(<GeoMap points={[POINT_A, POINT_B]} />);

    // Initial render: 2 markers, 1 map instance
    expect(mockMapInstances).toHaveLength(1);
    const mapInstance = mockMapInstances[0];
    expect(mockLeafletMarkers).toHaveLength(2);
    const markerB = mockLeafletMarkers.find((m) => Array.isArray(m.latlng) && m.latlng[0] === -6.9175 && m.latlng[1] === 107.6191);
    expect(markerB).toBeDefined();
    expect(mapInstance.setView).toHaveBeenCalledTimes(1);
    expect(mapInstance.setView).toHaveBeenCalledWith([-6.2, 106.8], 10);

    // Rerender with A+C (B removed, C added)
    rerender(<GeoMap points={[POINT_A, POINT_C]} />);

    // Map instance must be identical
    expect(mockMapInstances).toHaveLength(1);
    expect(mockMapInstances[0]).toBe(mapInstance);

    // setView NOT called again (center/zoom preserved)
    expect(mapInstance.setView).toHaveBeenCalledTimes(1);

    // B's marker remove() called
    expect(markerB?.remove).toHaveBeenCalled();

    // C's marker added (total markers should be 2: A and C)
    expect(mockLeafletMarkers).toHaveLength(3); // A+B from first render, C from second
    const markerC = mockLeafletMarkers.find((m) => Array.isArray(m.latlng) && m.latlng[0] === -7.25 && m.latlng[1] === 112.75);
    expect(markerC).toBeDefined();
    expect(markerC?.popup).toContain('Outlet C');
  });

  it('reuses a retained marker but sends the fresh point when it is clicked after an update', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const onSelectOutlet = jest.fn();
    const updatedPointA: GeographicMapPoint = {
      ...POINT_A,
      latitude: -6.21,
      longitude: 106.81,
      orders: 9,
      sales: '90000.00',
    };
    const { rerender } = render(<GeoMap points={[POINT_A, POINT_B]} onSelectOutlet={onSelectOutlet} />);

    const markerA = mockLeafletMarkers.find(
      (marker) => Array.isArray(marker.latlng) && marker.latlng[0] === POINT_A.latitude && marker.latlng[1] === POINT_A.longitude,
    );
    expect(markerA).toBeDefined();

    rerender(<GeoMap points={[updatedPointA, POINT_B]} onSelectOutlet={onSelectOutlet} />);

    expect(markerA?.setLatLng).toHaveBeenCalledWith([updatedPointA.latitude, updatedPointA.longitude]);
    expect(mockLeafletMarkers).toHaveLength(2);
    act(() => markerA?.handlers.click());
    expect(onSelectOutlet).toHaveBeenCalledWith(updatedPointA);
  });

  it('fits valid points once initially, then preserves viewport when filters change', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const { rerender } = render(<GeoMap points={[POINT_A, POINT_B]} />);

    const mapInstance = mockMapInstances[0];
    expect(mapInstance.fitBounds).toHaveBeenCalledTimes(1);
    expect(mapInstance.fitBounds).toHaveBeenCalledWith(
      expect.objectContaining({ latlngs: [[-6.2, 106.8], [-6.9175, 107.6191]] }),
      { padding: [24, 24] },
    );

    rerender(<GeoMap points={[POINT_A, POINT_C]} />);

    expect(mockMapInstances[0]).toBe(mapInstance);
    expect(mapInstance.fitBounds).toHaveBeenCalledTimes(1);
    expect(mapInstance.setView).toHaveBeenCalledTimes(1);
  });

  it('calls fitBounds again when resetSignal changes', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const { rerender } = render(<GeoMap points={[POINT_A, POINT_B]} resetSignal={0} />);

    const mapInstance = mockMapInstances[0];
    expect(mapInstance.fitBounds).toHaveBeenCalledTimes(1);

    rerender(<GeoMap points={[POINT_A, POINT_B]} resetSignal={1} />);

    expect(mockMapInstances[0]).toBe(mapInstance);
    expect(mapInstance.fitBounds).toHaveBeenCalledTimes(2);
    expect(mapInstance.setView).toHaveBeenCalledTimes(1);
  });

  it('matches backend coordinate parity and exposes a keyboard outlet list with Enter selection', async () => {
    const { isValidPoint } = await import('@/components/data-intelligence/GeoMap');
    const cases: Array<[string, unknown, unknown, boolean]> = [
      ['null latitude', null, 106.8, false],
      ['numeric string latitude', '-6.2', 106.8, false],
      ['numeric string longitude', -6.2, '106.8', false],
      ['NaN latitude', NaN, 106.8, false],
      ['Infinity latitude', Infinity, 106.8, false],
      ['Infinity longitude', -6.2, Infinity, false],
      ['latitude out of range', 91, 106.8, false],
      ['longitude out of range', -6.2, 181, false],
      ['Null Island', 0, 0, false],
      ['Jakarta coordinate', -6.2, 106.8, true],
    ];
    for (const [, latitude, longitude, expected] of cases) {
      expect(isValidPoint({ ...POINT_A, latitude: latitude as number, longitude: longitude as number })).toBe(expected);
    }

    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const onSelectOutlet = jest.fn();
    render(<GeoMap points={[POINT_A, POINT_B]} onSelectOutlet={onSelectOutlet} />);

    const outletList = screen.getByRole('list', { name: /daftar outlet peta/i });
    expect(outletList).toBeInTheDocument();
    const outletButtons = screen.getAllByRole('button');
    expect(outletButtons).toHaveLength(2);
    expect(outletButtons[0]).toHaveAccessibleName(/outlet a.*jakarta selatan/i);
    expect(outletButtons[1]).toHaveAccessibleName(/outlet b.*bandung/i);

    fireEvent.keyDown(outletButtons[0], { key: 'Enter' });
    expect(onSelectOutlet).toHaveBeenCalledWith(POINT_A);
  });

  it('renders the filtered count, not the legacy total, in popup and outlet list', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const filteredPoint = { ...POINT_A, orders: 10, filteredOrders: 7 };
    render(<GeoMap points={[filteredPoint]} />);

    const marker = mockLeafletMarkers.find(
      (entry) => Array.isArray(entry.latlng) && entry.latlng[0] === POINT_A.latitude,
    );
    expect(marker?.popup).toContain('7 pesanan');
    expect(marker?.popup).not.toContain('10 pesanan');
    expect(screen.getByRole('button', { name: /outlet a/i })).toHaveTextContent('7 pesanan');
  });

  it('renders tile fallback while keeping the outlet list usable', async () => {
    const GeoMap = (await import('@/components/data-intelligence/GeoMap')).default;
    const onSelectOutlet = jest.fn();
    render(<GeoMap points={[POINT_A]} onSelectOutlet={onSelectOutlet} />);

    expect(mockTileLayers).toHaveLength(1);
    act(() => mockTileLayers[0].handlers.tileerror());

    expect(await screen.findByTestId('geo-map-tile-fallback')).toHaveTextContent(/citra peta tidak dapat dimuat/i);
    expect(screen.getByRole('list', { name: /daftar outlet peta/i })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /outlet a/i }));
    expect(onSelectOutlet).toHaveBeenCalledWith(POINT_A);
  });
});
