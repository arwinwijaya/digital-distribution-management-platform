import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';

// Mock API
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

// Mock driver API
jest.mock('@/app/admin/drivers/api', () => ({
  fetchAdminDrivers: jest.fn(),
  createDriver: jest.fn(),
  updateDriver: jest.fn(),
  deleteDriver: jest.fn(),
}));

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import AdminDriversPage from '@/app/admin/drivers/page';
import { fetchAdminDrivers, createDriver, updateDriver, deleteDriver } from '@/app/admin/drivers/api';

const mockDrivers = [
  { id: 1, name: 'Budi Santoso', email: 'budi@example.com', vehicle_type: 'Motor', plate_number: 'B 1234 ABC', capacity_kg: 50, service_territory_id: 1, territory: { id: 1, name: 'Bogor' }, shift_start: '08:00', shift_end: '16:00', is_available: true, created_at: '2026-01-15T00:00:00.000Z', updated_at: '2026-02-01T00:00:00.000Z' },
  { id: 2, name: 'Siti Rahayu', email: 'siti@example.com', vehicle_type: 'Mobil Pickup', plate_number: 'B 5678 DEF', capacity_kg: 500, service_territory_id: 2, territory: { id: 2, name: 'Depok' }, shift_start: '09:00', shift_end: '17:00', is_available: true, created_at: '2026-01-16T00:00:00.000Z', updated_at: '2026-02-02T00:00:00.000Z' },
  { id: 3, name: 'Ahmad Fauzi', email: 'ahmad@example.com', vehicle_type: 'Truk', plate_number: 'B 9012 GHI', capacity_kg: 2000, service_territory_id: 3, territory: { id: 3, name: 'Bekasi' }, shift_start: '07:00', shift_end: '15:00', is_available: false, created_at: '2026-01-17T00:00:00.000Z', updated_at: '2026-02-03T00:00:00.000Z' },
];

describe('AdminDriversPage — driver roster table', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    (fetchAdminDrivers as jest.Mock).mockResolvedValue({
      drivers: mockDrivers,
      hasMore: false,
      limit: 15,
      cursor: 0,
      total: 3,
      summary: { available: 2, unavailable: 1 },
    });
    (createDriver as jest.Mock).mockResolvedValue({ ...mockDrivers[0], id: 4, name: 'New Driver' });
    (updateDriver as jest.Mock).mockResolvedValue({ ...mockDrivers[0], name: 'Updated' });
    (deleteDriver as jest.Mock).mockResolvedValue(undefined);

    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn();
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    jest.restoreAllMocks();
  });

  const renderPage = async () => {
    render(<AdminDriversPage />);
    // Wait for token to be loaded and page to render
    await waitFor(() => expect(screen.getByText('Roster Driver')).toBeInTheDocument());
  };

  it('renders table with rows, pagination, and summary from mock API', async () => {
    await renderPage();

    // Table rows
    await waitFor(() => expect(screen.getByText('Budi Santoso')).toBeInTheDocument());
    await waitFor(() => expect(screen.getByText('Siti Rahayu')).toBeInTheDocument());
    await waitFor(() => expect(screen.getByText('Ahmad Fauzi')).toBeInTheDocument());

    // Pagination present
    expect(screen.getByTestId('table-pagination')).toBeInTheDocument();

    // Summary present
    expect(screen.getByTestId('table-summary')).toBeInTheDocument();
    expect(screen.getByText(/3 driver/)).toBeInTheDocument();

    // fetchAdminDrivers called
    await waitFor(() => expect(fetchAdminDrivers).toHaveBeenCalledTimes(1));
  });

  it('clicking sort column "Nomor Plat" calls fetchAdminDrivers with sort params', async () => {
    await renderPage();

    // Click the "Nomor Plat" column header to sort
    const plateHeader = screen.getByText('Nomor Plat');
    fireEvent.click(plateHeader);

    await waitFor(() => expect(fetchAdminDrivers).toHaveBeenCalledTimes(2));
    const lastCall = (fetchAdminDrivers as jest.Mock).mock.calls[1];
    const filters = lastCall[1];
    expect(filters.sort).toBe('plate_number');
    expect(filters.order).toBe('desc'); // first click keeps desc as default
  });

  it('search filter calls fetchAdminDrivers with search param', async () => {
    await renderPage();

    // Type in search input
    const searchInput = screen.getByPlaceholderText('Cari nama, plat, kendaraan...');
    fireEvent.change(searchInput, { target: { value: 'Budi' } });

    await waitFor(() => expect(fetchAdminDrivers).toHaveBeenCalledTimes(2));
    const lastCall = (fetchAdminDrivers as jest.Mock).mock.calls[1];
    const filters = lastCall[1];
    expect(filters.search).toBe('Budi');
  });

  it('create form submit calls createDriver', async () => {
    await renderPage();

    // Open create modal
    fireEvent.click(screen.getByRole('button', { name: /tambah driver/i }));

    await waitFor(() => {
      const modalTitle = screen.getByRole('heading', { name: 'Tambah Driver' });
      expect(modalTitle).toBeInTheDocument();
    });

    // Fill form
    fireEvent.change(screen.getByLabelText('User ID'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Jenis Kendaraan'), { target: { value: 'Motor' } });
    fireEvent.change(screen.getByLabelText('Nomor Plat'), { target: { value: 'B 9999 XYZ' } });
    fireEvent.change(screen.getByLabelText('Kapasitas (kg)'), { target: { value: '100' } });
    fireEvent.change(screen.getByLabelText('Mulai Shift'), { target: { value: '08:00' } });
    fireEvent.change(screen.getByLabelText('Selesai Shift'), { target: { value: '16:00' } });

    // Submit using the modal footer button (the last matching button)
    const buttons = screen.getAllByRole('button', { name: /^Tambah Driver$/ });
    if (buttons.length > 1) {
      fireEvent.click(buttons[buttons.length - 1]);
    } else {
      fireEvent.click(buttons[0]);
    }

    await waitFor(() => expect(createDriver).toHaveBeenCalledTimes(1));
    const payload = (createDriver as jest.Mock).mock.calls[0][1];
    expect(payload.user_id).toBe('10');
    expect(payload.vehicle_type).toBe('Motor');
    expect(payload.plate_number).toBe('B 9999 XYZ');
  });

  it('edit form submit calls updateDriver', async () => {
    await renderPage();

    // Open edit modal for first driver
    fireEvent.click(screen.getAllByRole('button', { name: /edit/i })[0]);

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Edit Driver' })).toBeInTheDocument());

    // Change name
    fireEvent.change(screen.getByLabelText('Jenis Kendaraan'), { target: { value: 'Mobil Box' } });

    // Submit
    fireEvent.click(screen.getByRole('button', { name: /simpan perubahan/i }));

    await waitFor(() => expect(updateDriver).toHaveBeenCalledTimes(1));
    const [, , payload] = (updateDriver as jest.Mock).mock.calls[0];
    expect(payload.vehicle_type).toBe('Mobil Box');
  });

  it('delete button calls deleteDriver and reloads', async () => {
    await renderPage();

    // Mock window.confirm
    const originalConfirm = window.confirm;
    window.confirm = jest.fn(() => true);

    // Click delete on first driver
    fireEvent.click(screen.getAllByRole('button', { name: /hapus/i })[0]);

    await waitFor(() => expect(deleteDriver).toHaveBeenCalledTimes(1));
    expect(deleteDriver).toHaveBeenCalledWith('test-token', 1);

    // Should reload
    await waitFor(() => expect(fetchAdminDrivers).toHaveBeenCalledTimes(2));

    window.confirm = originalConfirm;
  });
});