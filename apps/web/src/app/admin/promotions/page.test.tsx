import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';

const promotionsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Diskon 10%', discount_type: 'percent', discount_value: '10.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null },
    { id: 2, name: 'Potongan Rp5000', discount_type: 'fixed', discount_value: '5000.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null },
  ],
  has_more: false,
  next_cursor: null,
};

const createResponse = {
  status: 'success',
  data: { id: 3, name: 'Diskon Baru', discount_type: 'percent', discount_value: '15.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null },
};

describe('admin promotions page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/promotions') && (options?.method === 'POST' || options?.method === 'post') && urlString.includes('/broadcast')) {
        return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'Not found' }) } as Response;
      }
      if (urlString.includes('/admin/promotions') && (options?.method === 'POST' || options?.method === 'post')) {
        return { ok: true, status: 201, json: async () => createResponse } as Response;
      }
      if (urlString.includes('/admin/promotions') && (options?.method === 'DELETE' || options?.method === 'delete')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success' }) } as Response;
      }
      if (urlString.includes('/admin/promotions') && (options?.method === 'PATCH' || options?.method === 'patch')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: { id: 1, name: 'Diskon 10% v2' } }) } as Response;
      }
      if (urlString.includes('/admin/promotions')) {
        return { ok: true, status: 200, json: async () => promotionsResponse } as Response;
      }
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/promotions/api');
    const spy = jest.spyOn(api, 'fetchPromotions');
    const { default: Page } = await import('@/app/admin/promotions/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
  });

  it('renders CRUD list with Siarkan button and handles broadcast 404 gracefully', async () => {
    const { default: Page } = await import('@/app/admin/promotions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Diskon 10%')).toBeInTheDocument());
    expect(screen.getByText('Potongan Rp5000')).toBeInTheDocument();
    expect(screen.getAllByText('Siarkan').length).toBeGreaterThan(0);

    fireEvent.click(screen.getAllByText('Siarkan')[0]);

    await waitFor(() => {
      expect(screen.getByText('Broadcast endpoint belum tersedia')).toBeInTheDocument();
    });
  });

  it('creates promotions via modal', async () => {
    const { default: Page } = await import('@/app/admin/promotions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Diskon 10%')).toBeInTheDocument());

    fireEvent.click(screen.getByText('+ Buat promosi'));
    await waitFor(() => expect(screen.getByText('Buat promosi')).toBeInTheDocument());

    fireEvent.change(screen.getByLabelText('Nama'), { target: { value: 'Diskon Baru' } });
    fireEvent.change(screen.getByLabelText('Nilai diskon'), { target: { value: '15' } });
    fireEvent.change(screen.getByLabelText('Mulai'), { target: { value: '2026-09-10' } });
    fireEvent.change(screen.getByLabelText('Selesai'), { target: { value: '2026-09-20' } });
    const selects = screen.getAllByLabelText(/Aktif/);
    fireEvent.change(selects[0], { target: { value: 'true' } });

    fireEvent.click(screen.getByText('Simpan'));

    await waitFor(() => expect(screen.getByText('Promosi dibuat.')).toBeInTheDocument());
  });
});
