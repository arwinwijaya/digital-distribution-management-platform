import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, act } from '@testing-library/react';

// ─── Mocks ───
const mockGetTrack = jest.fn();
const mockLoadDeliveries = jest.fn();
const mockGetStoredToken = jest.fn(() => 'test-token');
let mockSearchParams = new URLSearchParams('delivery=1');

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => `http://localhost:8000/api${path}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => mockGetStoredToken(),
}));

jest.mock('@/app/admin/tracking/api', () => ({
  getTrack: (id: number) => mockGetTrack(id),
}));

jest.mock('@/app/delivery/api', () => ({
  loadDeliveries: (token: string) => mockLoadDeliveries(token),
}));

jest.mock('next/navigation', () => ({
  useSearchParams: () => mockSearchParams,
  useRouter: () => ({ push: jest.fn() }),
}));

// Mock GeoMap to avoid Leaflet in tests
jest.mock('@/components/data-intelligence/GeoMap', () => ({
  __esModule: true,
  default: function MockGeoMap({ points }: { points: unknown[] }) {
    return <div data-testid="geo-map-mock">{points.length} points</div>;
  },
}));

import TrackingPage from '@/app/admin/tracking/page';

const mockTrackResponse = {
  delivery_id: 1,
  status: 'in_progress',
  driver: { id: 1, name: 'Driver 1' },
  last_position: { latitude: -6.2, longitude: 106.8, accuracy_m: 10, recorded_at: '2026-09-22T08:00:00Z' },
  pings: [
    { id: 1, latitude: -6.2, longitude: 106.8, accuracy_m: 10, recorded_at: '2026-09-22T08:00:00Z' },
    { id: 2, latitude: -6.21, longitude: 106.81, accuracy_m: 15, recorded_at: '2026-09-22T08:01:00Z' },
  ],
};

const mockEmptyTrackResponse = {
  delivery_id: 2,
  status: 'assigned',
  driver: { id: 2, name: 'Driver 2' },
  last_position: null,
  pings: [],
};

describe('Admin Tracking — live tracking page', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    localStorage.clear();
    localStorage.setItem('ddp_token', 'test-token');
    mockSearchParams = new URLSearchParams('delivery=1');
    mockGetTrack.mockResolvedValue(mockTrackResponse);
    mockLoadDeliveries.mockResolvedValue([]);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('fetches and renders track data with GeoMap', async () => {
    render(<TrackingPage />);

    await waitFor(() => {
      expect(mockGetTrack).toHaveBeenCalledWith(1);
    });

    await waitFor(() => {
      expect(screen.getByText('Driver 1')).toBeInTheDocument();
      expect(screen.getByText(/in_progress/i)).toBeInTheDocument();
    });

    // GeoMap receives 1 point (last_position)
    expect(screen.getByTestId('geo-map-mock')).toHaveTextContent('1 points');
  });

  it('polls getTrack on interval and updates', async () => {
    render(<TrackingPage />);

    await waitFor(() => expect(mockGetTrack).toHaveBeenCalledTimes(1));

    // Advance timers by polling interval (default 15s)
    act(() => {
      jest.advanceTimersByTime(15000);
    });

    await waitFor(() => {
      expect(mockGetTrack).toHaveBeenCalledTimes(2);
    });
  });

  it('cleans up interval on unmount', async () => {
    const { unmount } = render(<TrackingPage />);

    await waitFor(() => expect(mockGetTrack).toHaveBeenCalledTimes(1));

    unmount();
    const callsBefore = mockGetTrack.mock.calls.length;

    act(() => {
      jest.advanceTimersByTime(15000);
    });

    // No additional calls after unmount
    expect(mockGetTrack.mock.calls.length).toBe(callsBefore);
  });

  it('shows empty state when last_position is null', async () => {
    mockSearchParams = new URLSearchParams('delivery=2');
    mockGetTrack.mockResolvedValueOnce(mockEmptyTrackResponse);
    render(<TrackingPage />);

    await waitFor(() => {
      expect(screen.getByText(/belum ada lokasi/i)).toBeInTheDocument();
    });

    // GeoMap rendered with 0 points
    expect(screen.getByTestId('geo-map-mock')).toHaveTextContent('0 points');
  });

  it('continues polling even when empty', async () => {
    mockSearchParams = new URLSearchParams('delivery=2');
    mockGetTrack.mockResolvedValueOnce(mockEmptyTrackResponse);
    render(<TrackingPage />);

    await waitFor(() => expect(mockGetTrack).toHaveBeenCalledTimes(1));

    act(() => {
      jest.advanceTimersByTime(15000);
    });

    await waitFor(() => {
      expect(mockGetTrack).toHaveBeenCalledTimes(2);
    });
  });

  it('shows error message when getTrack fails', async () => {
    mockGetTrack.mockRejectedValueOnce(new Error('Tracking gagal dimuat.'));
    render(<TrackingPage />);

    await waitFor(() => {
      expect(screen.getByText(/tracking gagal/i)).toBeInTheDocument();
    });
  });

  it('shows last update timestamp', async () => {
    render(<TrackingPage />);

    await waitFor(() => {
      expect(screen.getByText(/terakhir diperbarui/i)).toBeInTheDocument();
    });
  });

  it('shows a delivery picker when no delivery id is in the URL', async () => {
    mockSearchParams = new URLSearchParams('');
    mockLoadDeliveries.mockResolvedValue([
      {
        id: 7,
        order_id: 7,
        driver_id: 1,
        status: 'in_progress',
        delivered_at: null,
        recipient_name: null,
        assigned_at: null,
        assigned_date: null,
        started_at: null,
        failure_reason: null,
        notes: null,
        order: null,
        driver: { name: 'Driver 7' },
        assigned_by: null,
      },
    ]);
    render(<TrackingPage />);

    await waitFor(() => {
      expect(mockLoadDeliveries).toHaveBeenCalled();
    });
    expect(screen.getByText('Pilih pengiriman untuk memulai tracking')).toBeInTheDocument();
    expect(mockGetTrack).not.toHaveBeenCalled();
  });
});
