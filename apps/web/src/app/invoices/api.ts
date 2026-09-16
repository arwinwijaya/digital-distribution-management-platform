/**
 * Invoices list loader — extracted from page.tsx:47 inline list read.
 * Guarded with `withDummyRead`, returning T6-linked invoice rows (derived
 * from the transaction factory, never hardcoded) with zero network while ON.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
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

const PAGE_SIZE = 25;

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
    ? {
        invoices: dummy.invoices.map((inv) => ({
          id: inv.id,
          order_id: inv.order_id,
          invoice_number: inv.invoice_number,
          issue_date: inv.issue_date,
          due_date: inv.due_date,
          total_amount: inv.total_amount,
          paid_amount: inv.paid_amount,
          balance_amount: inv.balance_amount,
          status: inv.status,
        })),
        meta: {
          page,
          limit: PAGE_SIZE,
          total: dummy.invoices.length,
          has_more: false,
        },
      }
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
