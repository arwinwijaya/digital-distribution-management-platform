import React from 'react';
import '@testing-library/jest-dom';
import { act, render, screen, waitFor } from '@testing-library/react';

jest.mock('@/lib/api', () => ({
  getStoredToken: () => 'test-token',
}));

jest.mock('@/app/admin/tracking/api', () => ({
  getTrack: jest.fn(),
}));

jest.mock('@/components/data-intelligence/GeoMap', () => ({
  __esModule: true,
  default: ({ points }: { points: Array<{ latitude: number; longitude: number }> }) => (
    <div data-testid="geo-map-mock">{points.length} titik</div>
  ),
}));

// Must mock next/navigation BEFORE importing the page (which uses useSearchParams)
jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams('deliveryId=42'),
  useRouter: () => ({ replace: jest.fn() }),
}));

import { getTrack } from '@/app/admin/tracking/api';
import AdminTrackingPage from '@/app/admin/tracking/page';

const mockGetTrack = getTrack as jest.MockedFunction<typeof getTrack>;

const position = {
  id: 10,
  latitude: -6.2,
  longitude: 106.8,
  accuracy_m: 8,
  recorded_at: '2026-09-22T10:00:00.000Z',
};

describe('AdminTrackingPage', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.clearAllMocks();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('renders the last position on GeoMap and polls every 15 seconds', async () => {
    mockGetTrack.mockResolvedValue({
      delivery_id: 42,
      status: 'in_progress',
      driver: { id: 7, name: 'Budi Driver' },
      last_position: position,
      pings: [position],
    });

    render(<AdminTrackingPage />);

    await waitFor(() => expect(screen.getByTestId('geo-map-mock')).toHaveTextContent('1 titik'));
    expect(screen.getByText(/Terakhir update/)).toBeInTheDocument();
    expect(mockGetTrack).toHaveBeenCalledWith('42');

    await act(async () => {
      jest.advanceTimersByTime(15_000);
    });

    await waitFor(() => expect(mockGetTrack).toHaveBeenCalledTimes(2));
  });

  it('shows the no-position state without rendering GeoMap and keeps polling', async () => {
    mockGetTrack.mockResolvedValue({
      delivery_id: 42,
      status: 'assigned',
      driver: null,
      last_position: null,
      pings: [],
    });

    render(<AdminTrackingPage />);

    await waitFor(() => expect(screen.getByText('Belum ada lokasi')).toBeInTheDocument());
    expect(screen.queryByTestId('geo-map-mock')).not.toBeInTheDocument();

    await act(async () => {
      jest.advanceTimersByTime(15_000);
    });

    await waitFor(() => expect(mockGetTrack).toHaveBeenCalledTimes(2));
  });

  it('cleans up polling when unmounted', async () => {
    mockGetTrack.mockResolvedValue({
      delivery_id: 42,
      status: 'in_progress',
      driver: null,
      last_position: position,
      pings: [position],
    });

    const { unmount } = render(<AdminTrackingPage />);
    await waitFor(() => expect(mockGetTrack).toHaveBeenCalledTimes(1));
    unmount();

    await act(async () => {
      jest.advanceTimersByTime(30_000);
    });

    expect(mockGetTrack).toHaveBeenCalledTimes(1);
  });
});