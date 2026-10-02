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

export interface InvoiceLineItem {
  id: number;
  order_id: number;
  product_id: number;
  product_name: string | null;
  product_name_snapshot: string | null;
  quantity: number;
  unit_price: string | number;
  subtotal: string | number;
}

export interface InvoicePayment {
  id: number;
  order_id: number;
  outlet_id: number;
  amount: string | number;
  payment_method: string;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface InvoiceOutlet {
  id: number;
  name: string;
  phone: string | null;
  address: string | null;
  city: string | null;
}

export interface InvoiceDetail {
  id: number;
  order_id: number;
  outlet_id: number;
  invoice_number: string;
  issue_date: string | null;
  due_date: string | null;
  total_amount: string | number;
  paid_amount: string | number;
  balance_amount: string | number;
  status: string;
  is_overdue: boolean;
  overdue: boolean;
  created_at: string;
  updated_at: string;
  outlet: InvoiceOutlet | null;
  line_items: InvoiceLineItem[];
  items: InvoiceLineItem[];
  payments: InvoicePayment[];
  payment_history: InvoicePayment[];
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

/**
 * Dummy parity for the invoice detail: same JSON shape the backend `GET
 * /invoices/{id}` returns, derived from the T6 transaction factory (never
 * hardcoded). Frozen product name is read from the order item's `product_name`
 * snapshot; payments are returned newest first. Zero network.
 */
function buildDummyInvoiceDetail(dummy: FullDummy, id: number): InvoiceDetail | null {
  const invoice = dummy.invoices.find((inv) => inv.id === id);
  if (!invoice) return null;

  const order = dummy.orders.find((ord) => ord.id === invoice.order_id) ?? null;
  const outlet = order ? dummy.outlets.find((o) => o.id === order.outlet_code) ?? null : null;

  const lineItems: InvoiceLineItem[] = (order?.items ?? []).map((item) => ({
    id: item.id,
    order_id: invoice.order_id,
    product_id: item.product_id,
    product_name: item.product_name,
    product_name_snapshot: item.product_name,
    quantity: item.quantity,
    unit_price: item.unit_price,
    subtotal: item.subtotal,
  }));

  const payments: InvoicePayment[] = dummy.payments
    .filter((payment) => payment.order_id === invoice.order_id)
    .sort((a, b) => (a.created_at < b.created_at ? 1 : a.created_at > b.created_at ? -1 : b.id - a.id))
    .map((payment) => ({
      id: payment.id,
      order_id: payment.order_id,
      outlet_id: invoice.order_id,
      amount: payment.amount,
      payment_method: payment.payment_method,
      status: payment.status,
      created_at: payment.created_at,
      updated_at: payment.created_at,
    }));

  const balance = Number(invoice.balance_amount);
  const overdue = Boolean(invoice.due_date) && balance > 0 && invoice.due_date < new Date().toISOString().slice(0, 10);

  return {
    id: invoice.id,
    order_id: invoice.order_id,
    outlet_id: order?.outlet_id ?? 0,
    invoice_number: invoice.invoice_number,
    issue_date: invoice.issue_date,
    due_date: invoice.due_date,
    total_amount: invoice.total_amount,
    paid_amount: invoice.paid_amount,
    balance_amount: invoice.balance_amount,
    status: invoice.status,
    is_overdue: overdue,
    overdue,
    created_at: `${invoice.issue_date}T00:00:00+07:00`,
    updated_at: `${invoice.issue_date}T00:00:00+07:00`,
    outlet: outlet
      ? { id: Number(outlet.id.replace('dummy-', '')) || 0, name: outlet.name, phone: null, address: null, city: outlet.city }
      : null,
    line_items: lineItems,
    items: lineItems,
    payments,
    payment_history: payments,
  };
}

/**
 * Load a single invoice's full detail for a token.
 * While dummy mode is ON, returns detail derived from the T6 factory (zero network).
 */
export async function getInvoiceDetail(token: string, id: number): Promise<InvoiceDetail> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyValue = isDummy && dummy ? buildDummyInvoiceDetail(dummy, id) : null;

  return withDummyRead(
    isDummy,
    dummyValue as InvoiceDetail,
    async () => {
      const response = await fetch(apiUrl(`/invoices/${id}`), {
        headers: authHeaders(token),
      });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Detail invoice tidak dapat dimuat.');
      return (body.data ?? body) as InvoiceDetail;
    },
  );
}

/**
 * Build the canonical PDF filename: `INV-YYYYMMDD-{id}.pdf` using the invoice's
 * issue date (falling back to today when absent), mirroring the backend
 * `PdfGeneratorService` naming contract.
 */
export function invoicePdfFilename(invoice: Pick<InvoiceDetail, 'id' | 'issue_date'>): string {
  const raw = invoice.issue_date ?? '';
  const date = /^\d{4}-\d{2}-\d{2}/.test(raw) ? raw.slice(0, 10).replace(/-/g, '') : new Date().toISOString().slice(0, 10).replace(/-/g, '');
  return `INV-${date}-${invoice.id}.pdf`;
}

/**
 * Pre-generated static PDF bytes for dummy mode — zero network, no
 * client-side PDF libs. Mirrors the backend `DummyModeService::pdfResponse`
 * contract which reuses a static blob instead of invoking the live renderer.
 */
const DUMMY_PDF_BYTES = '%PDF-1.4\n% dummy invoice pdf\n';

function buildDummyInvoicePdfBlob(): Blob {
  return new Blob([DUMMY_PDF_BYTES], { type: 'application/pdf' });
}

/**
 * GET /invoices/{id}/pdf — fetch the server-rendered PDF as a blob and trigger
 * a browser download named `INV-YYYYMMDD-{id}.pdf`. No client-side PDF libs.
 * While dummy mode is ON, returns the pre-generated static blob with zero
 * network via `withDummyRead` (same guard as `getInvoiceDetail`).
 */
export async function downloadInvoicePdf(
  token: string,
  invoice: Pick<InvoiceDetail, 'id' | 'issue_date'>,
): Promise<void> {
  const filename = invoicePdfFilename(invoice);
  const { isDummy } = useDummyStore.getState();

  const blob = await withDummyRead(
    isDummy,
    buildDummyInvoicePdfBlob(),
    async () => {
      const response = await fetch(apiUrl(`/invoices/${invoice.id}/pdf`), {
        headers: authHeaders(token),
      });
      if (!response.ok) {
        let message = 'PDF invoice tidak dapat diunduh.';
        try {
          const body = await response.json();
          message = parseError(body, message);
        } catch {
          // Non-JSON error body — keep the default message.
        }
        throw new Error(message);
      }
      return response.blob();
    },
  );

  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}
