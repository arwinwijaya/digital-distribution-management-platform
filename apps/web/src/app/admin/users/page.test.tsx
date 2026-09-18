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

/** A users list response with meta.total + timestamp columns. */
function usersListResponse(overrides?: { data?: unknown[]; total?: number; hasMore?: boolean; cursor?: number }) {
  const data = overrides?.data ?? [
    { id: 2, name: 'Budi Terbaru', email: 'budi@example.com', role: 'sales', created_at: '2026-09-05T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
    { id: 1, name: 'Admin Lama', email: 'admin@example.com', role: 'admin', created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
  ];
  const total = overrides?.total ?? data.length;
  return {
    status: 'success',
    data,
    meta: { has_more: overrides?.hasMore ?? false, limit: 20, cursor: overrides?.cursor ?? 0, total },
  };
}

function jsonResponse(body: unknown): Response {
  return { ok: true, status: 200, json: async () => body } as Response;
}

function lastUsersUrl(fetchMock: jest.Mock): string {
  const calls = fetchMock.mock.calls.filter(
    (c) => String(c[0]).includes('/admin/users') && !String(c[0]).includes('/role'),
  );
  return String(calls[calls.length - 1][0]);
}

describe('admin users page', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/users') && (options?.method === 'PATCH' || options?.method === 'patch')) {
        return { ok: true, status: 200, json: async () => roleResponse } as Response;
      }
      if (urlString.includes('/admin/users')) {
        return { ok: true, status: 200, json: async () => usersResponse } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/users/api');
    const spy = jest.spyOn(api, 'fetchAdminUsers');
    const { default: Page } = await import('@/app/admin/users/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
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
      const fetchMockLocal = (globalThis as unknown as { fetch: jest.Mock }).fetch;
      const patchCalls = fetchMockLocal.mock.calls.filter((c) => String(c[0]).includes('/role'));
      expect(patchCalls.length).toBeGreaterThan(0);
    });
  });

  // ── Fix round: real-mode client-side search filter ───────────────────────
  describe('client-side search filter (real mode)', () => {
    beforeEach(() => {
      // Real (non-dummy) backend: the users API has no search support, so the
      // same unfiltered page is returned regardless of the `search` query param.
      fetchMock.mockImplementation(async (url: unknown) => {
        if (String(url).includes('/admin/users')) {
          return jsonResponse(usersListResponse({
            data: [
              { id: 1, name: 'Admin Satu', email: 'admin@example.com', role: 'admin', created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-02T08:00:00Z' },
              { id: 2, name: 'Petugas Toko', email: 'outlet@example.com', role: 'outlet', created_at: '2026-09-05T08:00:00Z', updated_at: '2026-09-06T08:00:00Z' },
            ],
            total: 2,
          }));
        }
        return jsonResponse({});
      });
    });

    it('narrows the rendered rows when a search term is applied', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);

      await waitFor(() => expect(screen.getByText('Admin Satu')).toBeInTheDocument());
      expect(screen.getByText('Petugas Toko')).toBeInTheDocument();

      fireEvent.change(screen.getByPlaceholderText('Ketik nama atau email'), { target: { value: 'admin' } });
      fireEvent.click(screen.getByText('Terapkan filter'));

      await waitFor(() => {
        expect(screen.getByText('Admin Satu')).toBeInTheDocument();
        expect(screen.queryByText('Petugas Toko')).not.toBeInTheDocument();
      });
    });
  });

  // ── Cycle 1: default sort + summary + timestamps ─────────────────────────
  describe('default sort, summary strip and timestamp columns', () => {
    beforeEach(() => {
      fetchMock.mockImplementation(async (url: unknown) => {
        if (String(url).includes('/admin/users')) return jsonResponse(usersListResponse());
        return jsonResponse({});
      });
    });

    it('requests the default created_at DESC sort with cursor=0 on first load', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      const url = lastUsersUrl(fetchMock);
      expect(url).toContain('sort=created_at');
      expect(url).toContain('order=desc');
      expect(url).toContain('cursor=0');
    });

    it('renders rows newest-first and the summary strip as "N pengguna"', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      const rows = screen.getAllByRole('row');
      expect(rows[1]).toHaveTextContent('Budi Terbaru');

      const summary = screen.getByTestId('table-summary').textContent ?? '';
      expect(summary).toContain('2 pengguna');
    });

    it('renders Dibuat/Diperbarui as formatted dates (not raw ISO)', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      expect(screen.getByText('Dibuat')).toBeInTheDocument();
      expect(screen.getByText('Diperbarui')).toBeInTheDocument();
      expect(screen.queryByText('2026-09-05T08:00:00Z')).not.toBeInTheDocument();
      expect(screen.queryByText('2026-09-01T08:00:00Z')).not.toBeInTheDocument();
      expect(document.body.textContent ?? '').toMatch(/2026/);
    });

    it('renders an em dash for null timestamps instead of "Invalid Date"', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        if (String(url).includes('/admin/users')) {
          return jsonResponse(usersListResponse({
            data: [{ id: 99, name: 'Tanpa Tanggal', email: 'none@example.com', role: 'admin', created_at: null, updated_at: null }],
            total: 1,
          }));
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Tanpa Tanggal')).toBeInTheDocument());

      const row = screen.getByText('Tanpa Tanggal').closest('tr') as HTMLTableRowElement;
      const cells = Array.from(row.querySelectorAll('td'));
      // Columns: name, email, role, created_at, updated_at, action
      expect(cells[3]).toHaveTextContent('\u2014');
      expect(cells[4]).toHaveTextContent('\u2014');
      expect(screen.queryByText('Invalid Date')).not.toBeInTheDocument();
    });
  });

  // ── Cycle 2: sort header click + role filter reset cursor + paging ───────
  describe('sort header click + reset cursor + paging', () => {
    const pagedResponse = () => usersListResponse({ total: 100, hasMore: true });

    beforeEach(() => {
      fetchMock.mockImplementation(async (url: unknown, options?: { method?: string }) => {
        const urlString = String(url);
        if (urlString.includes('/admin/users') && (options?.method === 'PATCH' || options?.method === 'patch')) {
          return jsonResponse(roleResponse);
        }
        if (urlString.includes('/admin/users')) return jsonResponse(pagedResponse());
        return jsonResponse({});
      });
    });

    it('clicking "Email" sends sort=email&order=desc&cursor=0 then flips to order=asc', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      fireEvent.click(screen.getByText('Email'));
      await waitFor(() => {
        const url = lastUsersUrl(fetchMock);
        expect(url).toContain('sort=email');
        expect(url).toContain('order=desc');
        expect(url).toContain('cursor=0');
      });

      await waitFor(() => expect(screen.getByText('Email')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Email'));
      await waitFor(() => {
        const url = lastUsersUrl(fetchMock);
        expect(url).toContain('sort=email');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });
    });

    it('changing the role filter while on page >1 resets cursor to 0 and preserves sort', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      // Activate the email sort first.
      fireEvent.click(screen.getByText('Email'));
      await waitFor(() => expect(lastUsersUrl(fetchMock)).toContain('sort=email'));

      // Advance to page 2 (cursor=20).
      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => expect(lastUsersUrl(fetchMock)).toContain('cursor=20'));

      fireEvent.change(screen.getByLabelText('Filter peran'), { target: { value: 'sales' } });
      fireEvent.click(screen.getByText('Terapkan filter'));

      await waitFor(() => {
        const url = lastUsersUrl(fetchMock);
        expect(url).toContain('role=sales');
        expect(url).toContain('cursor=0');
        expect(url).toContain('sort=email');
      });
    });

    it('paging preserves the active sort and role filter', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      fireEvent.change(screen.getByLabelText('Filter peran'), { target: { value: 'sales' } });
      fireEvent.click(screen.getByText('Terapkan filter'));
      await waitFor(() => expect(lastUsersUrl(fetchMock)).toContain('role=sales'));

      await waitFor(() => expect(screen.getByText('Email')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Email'));
      await waitFor(() => expect(lastUsersUrl(fetchMock)).toContain('sort=email'));

      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => {
        const url = lastUsersUrl(fetchMock);
        expect(url).toContain('cursor=20');
        expect(url).toContain('sort=email');
        expect(url).toContain('role=sales');
      });
    });

    it('marks the default created_at header aria-sort="descending" and leaves Aksi inert', async () => {
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      const createdHeader = () => screen.getByText('Dibuat').closest('th') as HTMLTableCellElement;
      expect(createdHeader()).toHaveAttribute('aria-sort', 'descending');

      fireEvent.click(createdHeader());
      await waitFor(() => {
        const url = lastUsersUrl(fetchMock);
        expect(url).toContain('sort=created_at');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });
      await waitFor(() => expect(createdHeader()).toHaveAttribute('aria-sort', 'ascending'));

      const actionHeader = screen.getByText('Aksi').closest('th') as HTMLTableCellElement;
      expect(actionHeader).not.toHaveAttribute('aria-sort');
      const callsBefore = fetchMock.mock.calls.length;
      fireEvent.click(actionHeader);
      await act(async () => { await Promise.resolve(); });
      expect(fetchMock.mock.calls.length).toBe(callsBefore);
    });
  });

  // ── Cycle 3: density toggle + dummy parity ───────────────────────────────
  describe('density toggle + dummy parity', () => {
    it('density toggle changes the table padding class and persists to localStorage', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/admin/users')) return jsonResponse(usersListResponse());
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Budi Terbaru')).toBeInTheDocument());

      let headerCell = screen.getByText('Nama').closest('th') as HTMLTableCellElement;
      expect(headerCell).toHaveClass('py-3');

      fireEvent.click(screen.getByRole('button', { name: 'Compact' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-2');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('compact');

      fireEvent.click(screen.getByRole('button', { name: 'Comfortable' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-4');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('comfortable');
    });

    it('dummy listDummyUsers mirrors sort + offset slice + total + summary', async () => {
      const { fetchAdminUsers } = await import('@/app/admin/users/api');
      const { compareRows } = await import('@/lib/admin-table');
      useDummyStore.getState().toggle();
      expect(useDummyStore.getState().isDummy).toBe(true);

      // Default: created_at DESC with the shared comparator (id DESC tiebreak).
      const all = await fetchAdminUsers('t-token', { limit: 200 });
      const expectedDefault = [...all.users].sort((a, b) =>
        compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'created_at', 'desc'),
      );
      expect(all.users.map((u) => u.id)).toEqual(expectedDefault.map((u) => u.id));
      expect(all.total).toBe(all.users.length);
      expect(all.summary).toEqual({ total: all.users.length });

      // Offset slice: cursor 3, limit 3 → the 4th..6th rows of the sorted list.
      const page = await fetchAdminUsers('t-token', { limit: 3, cursor: 3 });
      expect(page.users.map((u) => u.id)).toEqual(all.users.slice(3, 6).map((u) => u.id));
      expect(page.hasMore).toBe(all.users.length > 6);

      // Explicit name ASC matches compareRows('name','asc').
      const byName = await fetchAdminUsers('t-token', { limit: 200, sort: 'name', order: 'asc' });
      const expectedByName = [...byName.users].sort((a, b) =>
        compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'name', 'asc'),
      );
      expect(byName.users.map((u) => u.id)).toEqual(expectedByName.map((u) => u.id));

      // Invalid sort falls back without throwing and stays deterministic.
      const bogusA = await fetchAdminUsers('t-token', { limit: 200, sort: 'bogus_column' });
      const bogusB = await fetchAdminUsers('t-token', { limit: 200, sort: 'bogus_column' });
      expect(bogusA.users.map((u) => u.id)).toEqual(bogusB.users.map((u) => u.id));
      expect(bogusA.users).toHaveLength(all.users.length);

      // Zero network while dummy mode is ON.
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/users')).length).toBe(0);
    });

    it('renders the summary strip from dummy data (zero network)', async () => {
      useDummyStore.getState().toggle();
      const { default: Page } = await import('@/app/admin/users/page');
      render(<Page />);

      await waitFor(() => {
        const summary = screen.getByTestId('table-summary').textContent ?? '';
        expect(summary).toMatch(/\d+ pengguna/);
      });
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/users')).length).toBe(0);
    });
  });
});
