/* Reproduce "Map container is already initialized" with:
   - real react-leaflet + leaflet (NOT mocked)
   - React 18 StrictMode (to match Next dev)
   - React.lazy + Suspense (to match next/dynamic ssr:false)
*/
import React, { Suspense } from 'react';
import { render, screen, act } from '@testing-library/react';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

const POINTS: GeographicMapPoint[] = [
  { outlet_id: 1, outlet_name: 'A', territory: 'Jak', latitude: -6.2, longitude: 106.8, orders: 5, sales: '5' },
];

// Simulate next/dynamic: lazy-loaded + Suspense
const LazyGeoMap = React.lazy(() => import('@/components/data-intelligence/GeoMap'));

let errors: Error[] = [];
const origError = console.error;

beforeEach(() => {
  errors = [];
  // Catch Leaflet throw so it surfaces as test failure with details
  jest.spyOn(console, 'error').mockImplementation((...args: unknown[]) => {
    origError(...args);
  });
});
afterEach(() => { (console.error as jest.Mock).mockRestore(); });

function renderWithSuspense(ui: React.ReactNode) {
  return render(
    <React.StrictMode>
      <Suspense fallback={<div data-testid="fallback">loading</div>}>
        {ui}
      </Suspense>
    </React.StrictMode>,
  );
}

test('Real GeoMap under StrictMode + Suspense does not throw "already initialized"', () => {
  // React's ErrorBoundary to catch the throw
  class Catcher extends React.Component<
    { children: React.ReactNode },
    { err: Error | null }
  > {
    state = { err: null as Error | null };
    static getDerivedStateFromError(err: Error) { return { err }; }
    render() {
      if (this.state.err) return <div data-testid="caught">{this.state.err.message}</div>;
      return this.props.children;
    }
  }

  act(() => {
    renderWithSuspense(
      <Catcher>
        <LazyGeoMap points={POINTS} />
      </Catcher>,
    );
  });

  // The map should render, no Leaflet error caught
  const caught = screen.queryByTestId('caught');
  if (caught) {
    throw new Error(`Leaflet threw: ${caught.textContent}`);
  }

  // MapContainer's wrapper div should exist
  expect(screen.getByTestId('geo-map')).toBeInTheDocument();
});
