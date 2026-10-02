/**
 * Invoice detail export PDF — integration test.
 *
 * Given authorized user (invoices:read) on detail page
 * When Export PDF button clicked
 * Then fetch GET /invoices/{id}/pdf as blob is invoked and download triggered
 * with filename INV-YYYYMMDD-{id}.pdf
 * Given unauthorized dummy user
 * Then button hidden
 *
 * Exercises the REAL InvoiceDetail component's Export button click handler.
 * Mock fetch for pdf blob + URL.createObjectURL + anchor download; do NOT mock
 * button itself.
 *
 * Expected RED: button missing or downloads wrong file.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

jest.mock(
  'next/navigation',
  () => ({
    useParams: () => ({ id: '123' }),
    useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  }),
  { virtual: true },
);

jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  }),
  getStoredToken: () => 'test-token',
}));

jest.mock('@/dummy/store', () => ({
  useDummyStore: Object.assign(jest.fn(() => false), {
    getState: jest.fn(() => ({ isDummy: false, dummyEntities: null })),
  }),
  selectIsDummy: (state: { isDummy: boolean }) => state.isDummy,
}));

const detailForExport = {
  id: 123,
  order_id: 456,
  outlet_id: 789,
  invoice_number: 'INV-20260101-0001',
  issue_date: '2026-01-01',
  due_date: '2026-01-08',
  total_amount: '50000.00',
  paid_amount: '20000.00',
  balance_amount: '30000.00',
  status: 'unpaid',
  is_overdue: true,
  overdue: true,
  created_at: '2026-01-01T10:00:00+07:00',
  updated_at: '2026-01-01T10:00:00+07:00',
  outlet: {
    id: 789,
    name: 'Toko Sejahtera',
    phone: '081234567890',
    address: 'Jl. Merdeka No. 10',
    city: 'Jakarta',
  },
  line_items: [
    {
      id: 1,
      order_id: 456,
      product_id: 10,
      product_name: 'Sabun A',
      product_name_snapshot: 'Sabun A',
      quantity: 2,
      unit_price: '15000.00',
      subtotal: '30000.00',
    },
  ],
  items: [
    {
      id: 1,
      order_id: 456,
      product_id: 10,
      product_name: 'Sabun A',
      product_name_snapshot: 'Sabun A',
      quantity: 2,
      unit_price: '15000.00',
      subtotal: '30000.00',
    },
  ],
  payments: [],
  payment_history: [],
};

let originalFetch: typeof fetch | undefined;
let fetchMock: jest.Mock;

beforeEach(() => {
  localStorage.clear();
  localStorage.setItem('ddp_token', 'test-token');
  localStorage.setItem('ddp_role', 'finance');
  // Default to real mode for existing tests; individual tests may override to dummy ON.
  const { useDummyStore } = jest.requireMock('@/dummy/store') as {
    useDummyStore: { getState: jest.Mock };
  };
  (useDummyStore.getState as jest.Mock).mockReturnValue({ isDummy: false, dummyEntities: null });
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch?: typeof fetch }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  jest.restoreAllMocks();
});

describe('invoice detail export PDF', () => {
  it('shows Export PDF button for authorized user and downloads correct filename', async () => {
    const { useRbacStore } = await import('@/store/useRbacStore');
    useRbacStore.getState().reset();
    useRbacStore.getState().hydrateFromMe({ invoices: 'read' }, 'finance');

    const capturedAnchors: HTMLAnchorElement[] = [];
    const originalCreateElement = document.createElement.bind(document);
    const createElementSpy = jest.spyOn(document, 'createElement').mockImplementation((tag: string, options?: ElementCreationOptions) => {
      const el = originalCreateElement(tag, options);
      if (tag.toLowerCase() === 'a') capturedAnchors.push(el as HTMLAnchorElement);
      return el;
    });
    const anchorClickSpy = jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    const createObjectURLSpy = jest.fn(() => 'blob:mock-url');
    const revokeObjectURLSpy = jest.fn();
    Object.defineProperty(URL, 'createObjectURL', { value: createObjectURLSpy, configurable: true });
    Object.defineProperty(URL, 'revokeObjectURL', { value: revokeObjectURLSpy, configurable: true });

    fetchMock = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/invoices/123/pdf')) {
        return {
          ok: true,
          status: 200,
          blob: async () => new Blob(['%PDF-1.4 mock'], { type: 'application/pdf' }),
        } as unknown as Response;
      }
      if (urlString.includes('/invoices/123') && !urlString.includes('/pdf')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: detailForExport }) } as Response;
      }
      if (urlString.includes('/admin/invoice-template')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: { id: 1 } }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;

    const { default: InvoiceDetail } = await import('@/app/invoices/components/InvoiceDetail');
    render(<InvoiceDetail detail={detailForExport} />);

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /export pdf/i })).toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: /export pdf/i }));

    await waitFor(() => {
      const pdfCall = fetchMock.mock.calls.find((call) => String(call[0]).includes('/invoices/123/pdf'));
      expect(pdfCall).toBeDefined();
    });

    await waitFor(() => {
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(anchorClickSpy).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalled();
    });

    // Verify filename pattern INV-YYYYMMDD-{id}.pdf — the anchor's download attribute
    expect(capturedAnchors.length).toBeGreaterThanOrEqual(1);
    const anchorEl = capturedAnchors[capturedAnchors.length - 1];
    expect(anchorEl.download).toBe('INV-20260101-123.pdf');

    createElementSpy.mockRestore();
    anchorClickSpy.mockRestore();
    delete (URL as unknown as { createObjectURL?: unknown }).createObjectURL;
    delete (URL as unknown as { revokeObjectURL?: unknown }).revokeObjectURL;
    useRbacStore.getState().reset();
  });

  it('hides Export PDF button for unauthorized user (no invoices:read)', async () => {
    const { useRbacStore } = await import('@/store/useRbacStore');
    useRbacStore.getState().reset();
    // Supplier has no invoices:read
    useRbacStore.getState().hydrateFromMe(null, 'supplier');

    const { default: InvoiceDetail } = await import('@/app/invoices/components/InvoiceDetail');
    render(<InvoiceDetail detail={detailForExport} />);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /export pdf/i })).not.toBeInTheDocument();
    });

    useRbacStore.getState().reset();
  });

  it('when dummy mode is ON, downloads static PDF with zero network and canonical filename', async () => {
    const { useRbacStore } = await import('@/store/useRbacStore');
    useRbacStore.getState().reset();
    useRbacStore.getState().hydrateFromMe({ invoices: 'read' }, 'finance');

    // Flip dummy mode ON — withDummyRead must short-circuit before any network.
    const { useDummyStore } = await import('@/dummy/store');
    (useDummyStore.getState as jest.Mock).mockReturnValue({ isDummy: true, dummyEntities: {} });

    const capturedAnchors: HTMLAnchorElement[] = [];
    const originalCreateElement = document.createElement.bind(document);
    const createElementSpy = jest.spyOn(document, 'createElement').mockImplementation((tag: string, options?: ElementCreationOptions) => {
      const el = originalCreateElement(tag, options);
      if (tag.toLowerCase() === 'a') capturedAnchors.push(el as HTMLAnchorElement);
      return el;
    });
    const anchorClickSpy = jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    const createObjectURLSpy = jest.fn((_blob: Blob) => 'blob:mock-url');
    const revokeObjectURLSpy = jest.fn();
    Object.defineProperty(URL, 'createObjectURL', { value: createObjectURLSpy, configurable: true });
    Object.defineProperty(URL, 'revokeObjectURL', { value: revokeObjectURLSpy, configurable: true });

    fetchMock = jest.fn(async () => {
      throw new Error('fetch should not be called in dummy mode');
    });
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;

    const { default: InvoiceDetail } = await import('@/app/invoices/components/InvoiceDetail');
    render(<InvoiceDetail detail={detailForExport} />);

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /export pdf/i })).toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: /export pdf/i }));

    await waitFor(() => {
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(anchorClickSpy).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalled();
    });

    // Zero network: withDummyRead must not invoke fetch for the pdf blob.
    expect(fetchMock).not.toHaveBeenCalled();

    // Still triggers a blob download with the canonical filename INV-YYYYMMDD-{id}.pdf
    expect(capturedAnchors.length).toBeGreaterThanOrEqual(1);
    const anchorEl = capturedAnchors[capturedAnchors.length - 1];
    expect(anchorEl.download).toBe('INV-20260101-123.pdf');
    // The dummy blob is a PDF (verifies reuse of filename helper without network)
    const blobArg = createObjectURLSpy.mock.calls[0][0] as Blob;
    expect(blobArg.type).toBe('application/pdf');
    expect(blobArg.size).toBeGreaterThan(0);

    createElementSpy.mockRestore();
    anchorClickSpy.mockRestore();
    delete (URL as unknown as { createObjectURL?: unknown }).createObjectURL;
    delete (URL as unknown as { revokeObjectURL?: unknown }).revokeObjectURL;
    useRbacStore.getState().reset();
    (useDummyStore.getState as jest.Mock).mockReturnValue({ isDummy: false, dummyEntities: null });
  });
});