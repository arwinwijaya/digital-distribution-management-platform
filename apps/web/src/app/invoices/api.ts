/**
 * Invoices list loader — extracted from page.tsx:47 inline list read.
 * Guarded with `withDummyRead`, returning T6-linked invoice rows (derived
 * from the transaction factory, never hardcoded) with zero network while ON.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { compareRows, paginate } from '@/lib/admin-table';
import type { FullDummy } from '@/dummy';

export type Invoice = {
  id: number;
  order_id: number;
  invoice_number: string;
  issue_date: string | null;
  due_date: string | null;
  total_amount: string | number;
  paid_amount: string | number;
  balance_amount: string | number;
  status: string;
};

export type InvoicePageMeta = {
  page: number;
  limit: number;
  total: number;
  has_more: boolean;
};

export type InvoicesListResult = {
  invoices: Invoice[];
  meta: InvoicePageMeta;
};

export interface InvoiceTemplate {
  id: number;
  logo_path: string | null;
  company_name: string;
  address: string;
  npwp: string;
  primary_color: string;
  footer_text: string | null;
  notes: string | null;
  signer_name: string | null;
  signer_title: string | null;
  show_npwp: boolean;
  show_outlet_phone: boolean;
}

export interface InvoiceTemplateInput {
  company_name?: string;
  address?: string;
  npwp?: string;
  primary_color?: string;
  footer_text?: string | null;
  notes?: string | null;
  signer_name?: string | null;
  signer_title?: string | null;
  show_npwp?: boolean;
  show_outlet_phone?: boolean;
}

const TEMPLATE_MAX_LOGO_BYTES = 2 * 1024 * 1024;

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

export function assertInvoiceLogoSize(file: File): void {
  if (file.size > TEMPLATE_MAX_LOGO_BYTES) {
    throw new Error('Ukuran logo maksimal 2 MB.');
  }
}

/**
 * GET /admin/invoice-template — loads the current corporate template.
 * Dummy mode is unsupported here (structured admin form is a live path).
 */
export async function getAdminInvoiceTemplate(token: string): Promise<InvoiceTemplate> {
  const response = await fetch(apiUrl('/admin/invoice-template'), {
    method: 'GET',
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Template tidak dapat dimuat.'));
  return (data.data ?? data) as InvoiceTemplate;
}

/**
 * POST /admin/invoice-template — multipart FormData: JSON fields + logo file.
 */
export async function updateAdminInvoiceTemplate(
  token: string,
  payload: InvoiceTemplateInput,
  logo?: File | null,
): Promise<InvoiceTemplate> {
  if (logo) assertInvoiceLogoSize(logo);
  const formData = new FormData();
  for (const [key, value] of Object.entries(payload)) {
    if (value === undefined) continue;
    if (value === null) formData.append(key, '');
    else if (typeof value === 'boolean') formData.append(key, value ? 'true' : 'false');
    else formData.append(key, String(value));
  }
  if (logo) formData.append('logo', logo);
  const response = await fetch(apiUrl('/admin/invoice-template'), {
    method: 'POST',
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    body: formData,
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Template tidak dapat disimpan.'));
  return (data.data ?? data) as InvoiceTemplate;
}

const PAGE_SIZE = 15;

/**
 * Dummy parity for the invoice history table: same offset-page contract as the
 * real `GET /invoices` list. Newest first (`id DESC`, mirroring the backend
 * `orderByDesc('id')`), `total = filtered.length`, offset slice identical.
 * Zero network.
 */
function buildDummyInvoicesList(dummy: FullDummy, page: number): InvoicesListResult {
  const all: Invoice[] = dummy.invoices.map((inv) => ({
    id: inv.id,
    order_id: inv.order_id,
    invoice_number: inv.invoice_number,
    issue_date: inv.issue_date,
    due_date: inv.due_date,
    total_amount: inv.total_amount,
    paid_amount: inv.paid_amount,
    balance_amount: inv.balance_amount,
    status: inv.status,
  }));

  const sorted = [...all].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'id', 'desc'),
  );

  const cursor = Math.max(0, (page - 1) * PAGE_SIZE);
  const paginated = paginate(sorted, cursor, PAGE_SIZE);
  return {
    invoices: paginated.page,
    meta: {
      page,
      limit: PAGE_SIZE,
      total: sorted.length,
      has_more: paginated.hasMore,
    },
  };
}

/**
 * Load invoices list for a token & page.
 * While dummy mode is ON, returns invoice rows derived from T6 factory transactions.
 */
export async function loadInvoices(
  token: string,
  page = 1,
): Promise<InvoicesListResult> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyValue: InvoicesListResult | null = isDummy && dummy
    ? buildDummyInvoicesList(dummy, page)
    : null;

  return withDummyRead(
    isDummy,
    dummyValue as InvoicesListResult,
    async () => {
      const query = new URLSearchParams({ page: String(page), limit: String(PAGE_SIZE) });
      const response = await fetch(`${apiUrl('/invoices')}?${query}`, {
        headers: authHeaders(token),
      });
      const body = await response.json();
      if (!response.ok)
        throw new Error(body.message || 'Data invoice tidak dapat dimuat.');
      return {
        invoices: Array.isArray(body.data) ? body.data : [],
        meta: {
          page: Number(body.meta?.page ?? page),
          limit: Number(body.meta?.limit ?? PAGE_SIZE),
          total: Number(body.meta?.total ?? 0),
          has_more: Boolean(body.meta?.has_more),
        },
      };
    },
  );
}
