/**
 * Payments list loader — extracted from page.tsx inline list read.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 * POST payment create is a write (T11) — NOT guarded here.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { compareRows, paginate } from '@/lib/admin-table';
import type { FullDummy } from '@/dummy';

export type Payment = {
  id: number;
  order_id: number;
  amount: string;
  payment_method: string;
  receipt_reference: string | null;
  created_at: string;
};

export type CreditSummary = {
  credit_limit: string | null;
  outstanding_balance: string;
  available_credit: string | null;
};

export type PageMeta = {
  page: number;
  limit: number;
  total: number;
  has_more: boolean;
};

export type PaymentsListResult = {
  payments: Payment[];
  paymentMeta: PageMeta;
  invoiceMeta: PageMeta;
  summary: CreditSummary | null;
};

const PAGE_SIZE = 15;

function money(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

function buildDummyPaymentsList(
  dummy: FullDummy,
  page: number,
  role: string,
): PaymentsListResult {
  const all: Payment[] = dummy.payments.map((p) => ({
    id: p.id,
    order_id: p.order_id,
    amount: p.amount,
    payment_method: p.payment_method,
    receipt_reference: p.receipt_reference,
    created_at: p.created_at,
  }));

  // Newest first — mirrors the backend `orderByDesc('created_at')->orderByDesc('id')`.
  const sorted = [...all].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'created_at', 'desc'),
  );

  const cursor = Math.max(0, (page - 1) * PAGE_SIZE);
  const paginated = paginate(sorted, cursor, PAGE_SIZE);

  const outstanding = dummy.invoices
    .filter((inv) => inv.status !== 'cancelled')
    .reduce((sum, inv) => sum + (Number(inv.balance_amount) || 0), 0);

  const creditLimit = 1_000_000_000;
  // The credit summary is outlet-scoped: only an outlet has a meaningful
  // limit/outstanding/available balance. Admin and finance get no summary
  // (mirrors the real path, where `/credit-limit` requires an outlet).
  const summary: CreditSummary | null =
    role === 'outlet'
      ? {
          credit_limit: money(creditLimit),
          outstanding_balance: money(outstanding),
          available_credit: money(creditLimit - outstanding),
        }
      : null;

  return {
    payments: paginated.page,
    paymentMeta: {
      page,
      limit: PAGE_SIZE,
      total: sorted.length,
      has_more: paginated.hasMore,
    },
    invoiceMeta: {
      page,
      limit: PAGE_SIZE,
      total: dummy.invoices.length,
      has_more: false,
    },
    summary,
  };
}

/**
 * Load payments list (+ invoices meta + credit summary) for a token & role.
 * While dummy mode is ON, returns derived dummy rows — zero network.
 */
export async function loadPaymentsList(
  token: string,
  role: string,
  page = 1,
): Promise<PaymentsListResult> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;
  const dummyPayload: PaymentsListResult | null = isDummy && dummy
    ? buildDummyPaymentsList(dummy, page, role)
    : null;

  return withDummyRead(
    isDummy,
    dummyPayload as PaymentsListResult,
    async () => {
      const query = new URLSearchParams({ page: String(page), limit: String(PAGE_SIZE) });
      const requests = [
        fetch(`${apiUrl('/payments')}?${query}`, { headers: authHeaders(token) }),
        fetch(`${apiUrl('/invoices')}?${query}`, { headers: authHeaders(token) }),
      ];
      // Only an outlet has an outlet-scoped credit limit. Admin/finance must
      // NOT call this: `CreditLimitController::show` aborts 422 for admin
      // without an outlet id, which would reject the whole Promise.all.
      if (role === 'outlet')
        requests.push(fetch(apiUrl('/credit-limit'), { headers: authHeaders(token) }));

      const responses = await Promise.all(requests);
      const paymentsResponse = responses[0];
      const invoicesResponse = responses[1];
      const summaryResponse = responses[2];

      const paymentsBody = await paymentsResponse.json();
      const invoicesBody = await invoicesResponse.json();
      if (!paymentsResponse.ok)
        throw new Error(paymentsBody.message || 'Data pembayaran tidak dapat dimuat.');
      if (!invoicesResponse.ok)
        throw new Error(invoicesBody.message || 'Data invoice tidak dapat dimuat.');

      let summary: CreditSummary | null = null;
      if (summaryResponse) {
        const summaryBody = await summaryResponse.json();
        if (!summaryResponse.ok)
          throw new Error(summaryBody.message || 'Saldo kredit tidak dapat dimuat.');
        summary = summaryBody.data;
      }

      return {
        payments: Array.isArray(paymentsBody.data) ? paymentsBody.data : [],
        paymentMeta: {
          page: Number(paymentsBody.meta?.page ?? page),
          limit: Number(paymentsBody.meta?.limit ?? PAGE_SIZE),
          total: Number(paymentsBody.meta?.total ?? 0),
          has_more: Boolean(paymentsBody.meta?.has_more),
        },
        invoiceMeta: {
          page: Number(invoicesBody.meta?.page ?? page),
          limit: Number(invoicesBody.meta?.limit ?? PAGE_SIZE),
          total: Number(invoicesBody.meta?.total ?? 0),
          has_more: Boolean(invoicesBody.meta?.has_more),
        },
        summary,
      };
    },
  );
}
