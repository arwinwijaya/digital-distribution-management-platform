/**
 * Sales visits loader — extracted from page.tsx inline list read (:28).
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 * POST visit scheduling is a write (T11) — NOT guarded here.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { compareRows, paginate } from '@/lib/admin-table';
import type { FullDummy } from '@/dummy';

export type Visit = {
  id: number;
  target: string | null;
  visit_date: string;
  status: string;
  notes: string | null;
};

export type SalesPageMeta = {
  page: number;
  limit: number;
  total: number;
  has_more: boolean;
};

export type SalesListResult = {
  visits: Visit[];
  meta: SalesPageMeta;
};

const PAGE_SIZE = 10;

function buildDummySalesList(dummy: FullDummy): Visit[] {
  return dummy.outlets.slice(0, 20).map((o, i) => ({
    id: -(i + 1),
    target: o.name,
    visit_date: `${new Date().getFullYear()}-${String((i % 12) + 1).padStart(2, '0')}-${String((i % 28) + 1).padStart(2, '0')}`,
    status: ['scheduled', 'completed', 'cancelled'][i % 3],
    notes: `Kunjungan terencana ke ${o.name}`,
  }));
}

/**
 * Dummy parity for the visit schedule table: same offset-page contract as the
 * real `GET /sales/visits` list. Newest first (`visit_date DESC`, `id DESC`
 * tiebreak, mirroring the backend `orderByDesc('visit_date')->orderByDesc('id')`),
 * `total = sorted.length`, offset slice identical. Zero network.
 */
function buildDummySalesResult(dummy: FullDummy, page: number): SalesListResult {
  const sorted = [...buildDummySalesList(dummy)].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'visit_date', 'desc'),
  );

  const cursor = Math.max(0, (page - 1) * PAGE_SIZE);
  const paginated = paginate(sorted, cursor, PAGE_SIZE);
  return {
    visits: paginated.page,
    meta: {
      page,
      limit: PAGE_SIZE,
      total: sorted.length,
      has_more: paginated.hasMore,
    },
  };
}

/**
 * Load sales visits for a token & page.
 * While dummy mode is ON, returns dummy visits — zero network.
 */
export async function loadSalesList(token: string, page = 1): Promise<SalesListResult> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;
  const dummyValue: SalesListResult | null = isDummy && dummy
    ? buildDummySalesResult(dummy, page)
    : null;

  return withDummyRead(isDummy, dummyValue as SalesListResult, async () => {
    const query = new URLSearchParams({ page: String(page), limit: String(PAGE_SIZE) });
    const response = await fetch(`${apiUrl('/sales/visits')}?${query}`, {
      headers: authHeaders(token),
    });
    const body = await response.json();
    if (!response.ok)
      throw new Error(body.message || 'Jadwal kunjungan tidak dapat dimuat.');
    return {
      visits: Array.isArray(body.data) ? (body.data as Visit[]) : [],
      meta: {
        page: Number(body.meta?.page ?? page),
        limit: Number(body.meta?.limit ?? PAGE_SIZE),
        total: Number(body.meta?.total ?? 0),
        has_more: Boolean(body.meta?.has_more),
      },
    };
  });
}
