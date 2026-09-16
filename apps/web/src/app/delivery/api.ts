/**
 * Deliveries list loader — extracted from page.tsx inline list read.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 * PATCH delivery status is a write (T11) — NOT guarded here.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';

export type Delivery = {
  id: number;
  order_id: number;
  driver_id: number;
  status: string;
  delivered_at: string | null;
  recipient_name: string | null;
};

/**
 * Load deliveries list for a token.
 * While dummy mode is ON, returns derived dummy rows — zero network.
 */
export async function loadDeliveries(token: string): Promise<Delivery[]> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyDeliveries: Delivery[] | null = isDummy && dummy
    ? dummy.deliveries.map((d) => ({
        id: d.id,
        order_id: d.order_id,
        driver_id: d.driver_id,
        status: d.status,
        delivered_at: d.delivered_at,
        recipient_name: d.recipient_name,
      }))
    : null;

  return withDummyRead(isDummy, dummyDeliveries as Delivery[], async () => {
    const response = await fetch(apiUrl('/deliveries'), {
      headers: authHeaders(token),
    });
    const body = await response.json();
    if (!response.ok)
      throw new Error(body.message || 'Pengiriman tidak dapat dimuat.');
    return body.data as Delivery[];
  });
}
