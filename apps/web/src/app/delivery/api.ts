/**
 * Deliveries list loader — extracted from page.tsx inline list read.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 * PATCH delivery status is a write (T11) — NOT guarded here.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';

/** Outlet summary attached to a delivery's order (address may be absent in dummy). */
export type DeliveryOutlet = {
  /** Numeric in the real API, `dummy-00N` string in dummy mode. */
  id: number | string;
  name: string;
  city: string | null;
  address: string | null;
};

/** Sales rep / assigner summary attached to a delivery's order. */
export type DeliveryPerson = { id?: number; name: string };

/** One order line, with the product name resolved server-side. */
export type DeliveryItem = {
  product_id: number;
  product_name: string | null;
  quantity: number;
  unit_price: string;
  subtotal: string;
};

/** Order projection embedded in the delivery payload (see DeliveryController::formatOrder). */
export type DeliveryOrder = {
  order_id: string;
  total_amount: string;
  outlet: DeliveryOutlet | null;
  sales: DeliveryPerson | null;
  items: DeliveryItem[];
};

export type Delivery = {
  id: number;
  order_id: number;
  driver_id: number;
  status: string;
  delivered_at: string | null;
  recipient_name: string | null;
  /** Raw UTC-serialized assignment timestamp (display only). */
  assigned_at: string | null;
  /**
   * LOCAL (Asia/Jakarta) calendar date, `YYYY-MM-DD` — the value the date
   * filter compares against. The backend emits it because the default
   * serializer converts `assigned_at` to UTC, which would misfile
   * 00:00–06:59 WIB deliveries to the previous day.
   */
  assigned_date: string | null;
  started_at: string | null;
  failure_reason: string | null;
  notes: string | null;
  order: DeliveryOrder | null;
  driver: DeliveryPerson | null;
  assigned_by: DeliveryPerson | null;
};

/** Fixed display zone for date bucketing — matches the backend `app.timezone`. */
const APP_TIMEZONE = 'Asia/Jakarta';

/**
 * Render an ISO datetime as the LOCAL `YYYY-MM-DD` calendar date in the app
 * timezone. Works for both `+07:00` and `Z`-suffixed inputs, so factory
 * (`+07:00`) and mutated (`Z`, from `nowIso()`) dummy rows normalize alike.
 * Returns `null` for missing/unparseable input (never throws).
 */
export function toLocalDateString(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) return null;
  return new Intl.DateTimeFormat('en-CA', { timeZone: APP_TIMEZONE }).format(parsed);
}

/**
 * Load deliveries list for a token.
 * While dummy mode is ON, returns enriched dummy rows — zero network.
 */
export async function loadDeliveries(token: string): Promise<Delivery[]> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyDeliveries: Delivery[] | null = isDummy && dummy
    ? dummy.deliveries.map((d) => {
        const order = dummy.orders.find((o) => o.id === d.order_id) ?? null;
        const outlet = order ? dummy.outlets.find((o) => o.id === order.outlet_code) ?? null : null;

        return {
          id: d.id,
          order_id: d.order_id,
          driver_id: d.driver_id,
          status: d.status,
          delivered_at: d.delivered_at,
          recipient_name: d.recipient_name,
          // Dummy has no assignment timestamp — order creation stands in for it
          // so the detail timeline still renders an "assigned" entry.
          assigned_at: order?.created_at ?? null,
          assigned_date: toLocalDateString(order?.created_at),
          started_at: null,
          failure_reason: null,
          notes: null,
          order: order
            ? {
                order_id: order.order_id,
                total_amount: order.total_amount,
                outlet: outlet ? { id: outlet.id, name: outlet.name, city: outlet.city, address: null } : null,
                sales: { name: `Sales #${(((order.id - 1) % 5) + 5) % 5 + 1}` },
                items: order.items.map((item) => ({
                  product_id: item.product_id,
                  product_name: item.product_name,
                  quantity: item.quantity,
                  unit_price: item.unit_price,
                  subtotal: item.subtotal,
                })),
              }
            : null,
          driver: { name: `Driver #${d.driver_id}` },
          assigned_by: null,
        };
      })
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
