import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));

const usersResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Admin Satu', email: 'admin1@example.com', role: 'admin' },
    { id: 2, name: 'Petugas Toko', email: 'outlet@example.com', role: 'outlet' },
  ],
  meta: { limit: 20, has_more: false },
};

const roleResponse = {
  status: 'success',
  data: { user: { id: 2, name: 'Petugas Toko', email: 'outlet@example.com', role: 'sales' } },
};

describe('admin users page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/users') && (options?.method === 'PATCH' || options?.method === 'patch')) {
        return { ok: true, status: 200, json: async () => roleResponse } as Response;
      }
      if (urlString.includes('/admin/users')) {
        return { ok: true, status: 200, json: async () => usersResponse } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
  });

  it('renders user list with role filter', async () => {
    const { default: Page } = await import('@/app/admin/users/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('Admin Satu')).toBeInTheDocument();
    });
    expect(screen.getByText('Petugas Toko')).toBeInTheDocument();
    expect(screen.getByText('Kelola pengguna')).toBeInTheDocument();

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const url = String(fetchMock.mock.calls[0]?.[0] ?? '');
    expect(url).toContain('/admin/users');
  });

  it('submits role assignment via PATCH', async () => {
    const { default: Page } = await import('@/app/admin/users/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('Admin Satu')).toBeInTheDocument();
    });

    const buttons = screen.getAllByText('Ubah peran');
    fireEvent.click(buttons[0]);

    const selects = screen.getAllByDisplayValue('admin');
    fireEvent.change(selects[0], { target: { value: 'sales' } });
    fireEvent.click(screen.getByText('Simpan'));

    await waitFor(() => {
      const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
      const patchCalls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/role'));
      expect(patchCalls.length).toBeGreaterThan(0);
    });
  });
});
