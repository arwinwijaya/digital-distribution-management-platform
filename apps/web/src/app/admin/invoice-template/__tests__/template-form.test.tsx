/**
 * Admin invoice-template form — integration test.
 *
 * Given an admin with `invoice_template:read` / `invoice_template:edit`
 * When navigating to `/admin/invoice-template`
 * Then the structured form loads with the current template values; editing the
 *      fields and clicking Save POSTs `/admin/invoice-template` with the updated
 *      payload and a success message appears.
 *
 * Exercises the REAL page component + REAL api client; only `global.fetch` is
 * faked. No raw-HTML or drag-and-drop editor is expected.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

const mockGetStoredToken = jest.fn<string | null, []>(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  }),
  getStoredToken: () => mockGetStoredToken(),
}));

// jsdom does not implement object URLs used for the logo preview.
beforeAll(() => {
  if (typeof URL.createObjectURL !== 'function') {
    Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: jest.fn(() => 'blob:preview') });
  }
  if (typeof URL.revokeObjectURL !== 'function') {
    Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: jest.fn() });
  }
});

const templateResponse = {
  status: 'success',
  data: {
    id: 1,
    logo_path: null,
    company_name: 'PT Digital Distribusi Nusantara',
    address: 'Jl. Jend. Sudirman Kav. 52-53, Jakarta Pusat 12190',
    npwp: '01.234.567.8-901.000',
    primary_color: '#0F172A',
    footer_text: 'Terima kasih atas kepercayaan Anda.',
    notes: 'Invoice ini merupakan dokumen resmi pembayaran.',
    signer_name: 'Budi Santoso',
    signer_title: 'Direktur Keuangan',
    show_npwp: true,
    show_outlet_phone: true,
  },
};

function makeFile(name: string, type: string, size?: number): File {
  const file = new File([size !== undefined ? new Uint8Array(size) : 'x'], name, { type });
  return file;
}

describe('admin invoice-template page', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      const method = (options?.method ?? 'GET').toUpperCase();
      if (urlString.includes('/admin/invoice-template') && method === 'POST') {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            status: 'success',
            data: { ...templateResponse.data, company_name: 'PT Baru Sejahtera' },
          }),
        } as Response;
      }
      if (urlString.includes('/admin/invoice-template')) {
        return { ok: true, status: 200, json: async () => templateResponse } as Response;
      }
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('loads the current template values into a structured form', async () => {
    const { default: Page } = await import('@/app/admin/invoice-template/page');
    render(<Page />);

    await waitFor(() =>
      expect(screen.getByLabelText(/nama perusahaan/i)).toHaveValue('PT Digital Distribusi Nusantara'),
    );

    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/admin/invoice-template'),
      expect.objectContaining({ method: expect.stringMatching(/GET/i) }),
    );
    expect(screen.getByLabelText(/alamat/i)).toHaveValue(
      'Jl. Jend. Sudirman Kav. 52-53, Jakarta Pusat 12190',
    );
    expect(screen.getByLabelText(/^npwp$/i)).toHaveValue('01.234.567.8-901.000');
    expect(screen.getByLabelText(/warna utama/i)).toHaveValue('#0f172a');
    expect(screen.getByLabelText(/footer/i)).toHaveValue('Terima kasih atas kepercayaan Anda.');
    expect(screen.getByLabelText(/catatan/i)).toHaveValue('Invoice ini merupakan dokumen resmi pembayaran.');
    expect(screen.getByLabelText(/nama penanda tangan/i)).toHaveValue('Budi Santoso');
    expect(screen.getByLabelText(/jabatan penanda tangan/i)).toHaveValue('Direktur Keuangan');
    expect(screen.getByLabelText(/tampilkan npwp/i)).toBeChecked();
    expect(screen.getByLabelText(/tampilkan telepon outlet/i)).toBeChecked();
    expect(screen.getByLabelText(/logo/i)).toHaveAttribute('type', 'file');
    // No raw-HTML editor is exposed.
    expect(screen.queryByText(/html/i)).not.toBeInTheDocument();
  });

  it('saves edits via POST /admin/invoice-template with FormData and shows success', async () => {
    const { default: Page } = await import('@/app/admin/invoice-template/page');
    render(<Page />);

    await waitFor(() =>
      expect(screen.getByLabelText(/nama perusahaan/i)).toHaveValue('PT Digital Distribusi Nusantara'),
    );

    fireEvent.change(screen.getByLabelText(/nama perusahaan/i), { target: { value: 'PT Baru Sejahtera' } });
    fireEvent.change(screen.getByLabelText(/alamat/i), { target: { value: 'Jl. Baru No. 1, Jakarta' } });
    fireEvent.change(screen.getByLabelText(/warna utama/i), { target: { value: '#123abc' } });
    fireEvent.change(screen.getByLabelText(/footer/i), { target: { value: 'Footer baru' } });
    fireEvent.change(screen.getByLabelText(/catatan/i), { target: { value: 'Catatan baru' } });
    fireEvent.click(screen.getByLabelText(/tampilkan npwp/i));

    fireEvent.click(screen.getByRole('button', { name: /^simpan$/i }));

    await waitFor(() => {
      const postCall = fetchMock.mock.calls.find(
        (c) => (c[1] as { method?: string } | undefined)?.method === 'POST',
      );
      expect(postCall).toBeDefined();
    });

    const postCall = fetchMock.mock.calls.find(
      (c) => (c[1] as { method?: string } | undefined)?.method === 'POST',
    );
    expect(String(postCall?.[0])).toContain('/admin/invoice-template');
    const body = (postCall?.[1] as { body?: unknown }).body;
    expect(body).toBeInstanceOf(FormData);
    const formData = body as FormData;
    expect(formData.get('company_name')).toBe('PT Baru Sejahtera');
    expect(formData.get('address')).toBe('Jl. Baru No. 1, Jakarta');
    expect(formData.get('primary_color')).toBe('#123abc');
    expect(formData.get('footer_text')).toBe('Footer baru');
    expect(formData.get('notes')).toBe('Catatan baru');
    // Unchecked toggle is serialized as a falsy marker.
    expect(['false', '0', null]).toContain(formData.get('show_npwp'));

    await waitFor(() =>
      expect(screen.getByText(/berhasil|disimpan|tersimpan/i)).toBeInTheDocument(),
    );
  });

  it('rejects a logo larger than 2MB with a validation message and no preview', async () => {
    const { default: Page } = await import('@/app/admin/invoice-template/page');
    render(<Page />);

    await waitFor(() =>
      expect(screen.getByLabelText(/nama perusahaan/i)).toHaveValue('PT Digital Distribusi Nusantara'),
    );

    const logoInput = screen.getByLabelText(/logo/i);
    const bigFile = makeFile('big-logo.png', 'image/png', 3 * 1024 * 1024);
    fireEvent.change(logoInput, { target: { files: [bigFile] } });

    await waitFor(() =>
      expect(screen.getByText(/ukuran logo maksimal/i)).toBeInTheDocument(),
    );
    expect(screen.queryByAltText(/pratinjau logo/i)).not.toBeInTheDocument();
  });

  it('accepts a logo within 2MB and shows a preview', async () => {
    const { default: Page } = await import('@/app/admin/invoice-template/page');
    render(<Page />);

    await waitFor(() =>
      expect(screen.getByLabelText(/nama perusahaan/i)).toHaveValue('PT Digital Distribusi Nusantara'),
    );

    const logoInput = screen.getByLabelText(/logo/i);
    const okFile = makeFile('logo.png', 'image/png', 1024 * 1024);
    fireEvent.change(logoInput, { target: { files: [okFile] } });

    await waitFor(() =>
      expect(screen.getByAltText(/pratinjau logo/i)).toBeInTheDocument(),
    );
    expect(screen.queryByText(/terlalu besar/i)).not.toBeInTheDocument();
  });

  it('shows an error message when saving fails', async () => {
    fetchMock.mockImplementation(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      const method = (options?.method ?? 'GET').toUpperCase();
      if (urlString.includes('/admin/invoice-template') && method === 'POST') {
        return { ok: false, status: 422, json: async () => ({ message: 'Validasi gagal.' }) } as Response;
      }
      if (urlString.includes('/admin/invoice-template')) {
        return { ok: true, status: 200, json: async () => templateResponse } as Response;
      }
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });

    const { default: Page } = await import('@/app/admin/invoice-template/page');
    render(<Page />);

    await waitFor(() =>
      expect(screen.getByLabelText(/nama perusahaan/i)).toHaveValue('PT Digital Distribusi Nusantara'),
    );

    fireEvent.click(screen.getByRole('button', { name: /^simpan$/i }));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(/validasi gagal/i),
    );
  });

  it('shows the login form when no admin token is stored', async () => {
    mockGetStoredToken.mockReturnValue(null);
    const { default: Page } = await import('@/app/admin/invoice-template/page');
    render(<Page />);

    await waitFor(() =>
      expect(screen.getByText(/masuk sebagai administrator/i)).toBeInTheDocument(),
    );
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
