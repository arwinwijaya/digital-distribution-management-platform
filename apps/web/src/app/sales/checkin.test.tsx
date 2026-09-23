import React from 'react';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
const mockLoadSalesList = jest.fn();
const mockCheckInVisit = jest.fn();
const mockCheckOutVisit = jest.fn();

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => `http://localhost:8000/api${path}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => mockGetStoredToken(),
}));

jest.mock('@/app/sales/api', () => ({
  loadSalesList: () => mockLoadSalesList(),
  checkInVisit: (id: number, coords: unknown) => mockCheckInVisit(id, coords),
  checkOutVisit: (id: number, coords: unknown) => mockCheckOutVisit(id, coords),
}));

import SalesPage from '@/app/sales/page';

type PositionCallback = (position: {
  coords: { latitude: number; longitude: number; accuracy: number };
}) => void;
type ErrorCallback = (error: { code: number; message: string }) => void;

const plannedVisit = {
  id: 41,
  target: 'Outlet Merdeka',
  visit_date: '2026-09-22',
  status: 'planned',
  notes: null,
  check_in_at: null,
  check_out_at: null,
};

const checkedInVisit = {
  ...plannedVisit,
  status: 'in_progress',
  check_in_at: '2026-09-22T08:00:00Z',
};

function installGeolocation() {
  const getCurrentPosition = jest.fn();
  Object.defineProperty(global.navigator, 'geolocation', {
    configurable: true,
    value: { getCurrentPosition },
  });
  return getCurrentPosition;
}

beforeEach(() => {
  jest.clearAllMocks();
  mockLoadSalesList.mockResolvedValue({
    visits: [plannedVisit],
    meta: { page: 1, limit: 10, total: 1, has_more: false },
  });
  mockCheckInVisit.mockResolvedValue(checkedInVisit);
  mockCheckOutVisit.mockResolvedValue({
    ...checkedInVisit,
    status: 'completed',
    check_out_at: '2026-09-22T09:00:00Z',
  });
});

describe('sales visit check-in/out', () => {
  it('requests geolocation and checks in a planned visit', async () => {
    const getCurrentPosition = installGeolocation();
    render(<SalesPage />);

    await screen.findByText('Outlet Merdeka');
    fireEvent.click(screen.getByRole('button', { name: 'Check-in' }));

    expect(getCurrentPosition).toHaveBeenCalledWith(
      expect.any(Function),
      expect.any(Function),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );

    const onSuccess = getCurrentPosition.mock.calls[0][0] as PositionCallback;
    onSuccess({ coords: { latitude: -6.2, longitude: 106.8, accuracy: 12 } });

    await waitFor(() => {
      expect(mockCheckInVisit).toHaveBeenCalledWith(41, {
        latitude: -6.2,
        longitude: 106.8,
        accuracy_m: 12,
      });
      expect(screen.getByText(/checked-in|sudah check-in/i)).toBeInTheDocument();
    });
  });

  it('shows a geolocation error and never calls the API', async () => {
    const getCurrentPosition = installGeolocation();
    render(<SalesPage />);

    await screen.findByText('Outlet Merdeka');
    fireEvent.click(screen.getByRole('button', { name: 'Check-in' }));
    const onError = getCurrentPosition.mock.calls[0][1] as ErrorCallback;
    onError({ code: 1, message: 'Permission denied' });

    await waitFor(() => {
      expect(screen.getByText(/lokasi|geolokasi|izin/i)).toBeInTheDocument();
    });
    expect(mockCheckInVisit).not.toHaveBeenCalled();
  });

  it('shows the radius error returned by the API', async () => {
    const getCurrentPosition = installGeolocation();
    mockCheckInVisit.mockRejectedValueOnce(new Error('Di luar radius outlet'));
    render(<SalesPage />);

    await screen.findByText('Outlet Merdeka');
    fireEvent.click(screen.getByRole('button', { name: 'Check-in' }));
    const onSuccess = getCurrentPosition.mock.calls[0][0] as PositionCallback;
    onSuccess({ coords: { latitude: -6.2, longitude: 106.8, accuracy: 12 } });

    await waitFor(() => expect(screen.getByText('Di luar radius outlet')).toBeInTheDocument());
    expect(mockCheckInVisit).toHaveBeenCalledTimes(1);
  });
});
