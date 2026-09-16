/**
 * Sales visits loader — extracted from page.tsx inline list read (:28).
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 * POST visit scheduling is a write (T11) — NOT guarded here.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';

export type Visit = {
  id: number;
  target: string | null;
  visit_date: string;
  status: string;
  notes: string | null;
};

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
 * Load sales visits for a token.
 * While dummy mode is ON, returns dummy visits — zero network.
 */
export async function loadSalesList(token: string): Promise<Visit[]> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;
  const dummyValue: Visit[] | null = isDummy && dummy ? buildDummySalesList(dummy) : null;

  return withDummyRead(isDummy, dummyValue as Visit[], async () => {
    const response = await fetch(apiUrl('/sales/visits'), {
      headers: authHeaders(token),
    });
    const body = await response.json();
    if (!response.ok)
      throw new Error(body.message || 'Jadwal kunjungan tidak dapat dimuat.');
    return body.data as Visit[];
  });
}
