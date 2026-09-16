/**
 * Dummy-mode fake mutation tests (T11).
 *
 * Cycle 1 (unit) — createDummyOrder applies relational side-effects with
 * prefixed/negative, non-colliding ids and zero network.
 * Cycle 2 (integration) — every write guard fakes success with zero network
 * while ON; OFF path still POSTs; mutations are ephemeral across a toggle cycle.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { createDummyOrder } from '@/dummy/mutations';
import { createSalesOrder } from '@/app/sales/orders/api';
import { updateProductPrice } from '@/app/admin/products/api';
import {
  createPromotion,
  updatePromotion,
  deletePromotion,
  broadcastPromotion,
} from '@/app/admin/promotions/api';
import { assignUserRole } from '@/app/admin/users/api';
import { updateOutlet } from '@/app/admin/outlets/api';
import { sendFunnelEvent } from '@/lib/data-intelligence-api';
import { updateDeliveryStatus } from '@/app/delivery/page';
import { recordPayment } from '@/app/payments/page';
import { scheduleVisit } from '@/app/sales/page';
import { submitOutletOrder } from '@/components/OrderForm';

const TODAY = new Date('2026-02-14T10:00:00+07:00');

type Graph = Record<string, unknown> & {
  orders?: Array<{ id: number; order_id: string; outlet_id: number }>;
  payments?: Array<{ id: number; order_id: number }>;
  invoices?: Array<{ id: number; order_id: number }>;
  deliveries?: Array<{ id: number; order_id: number }>;
};

function buildGraph(): Record<string, unknown> {
  return buildFullDummy(TODAY) as unknown as Record<string, unknown>;
}

function readGraph(): Graph {
  return (useDummyStore.getState().dummyEntities ?? {}) as Graph;
}

let originalFetch: typeof fetch | undefined;

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
  setDummyGenerator(buildGraph);
  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
});

afterEach(() => {
  jest.restoreAllMocks();
  if (originalFetch) {
    (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  } else {
    delete (globalThis as unknown as { fetch?: unknown }).fetch;
  }
});

describe('createDummyOrder — relational side-effects + id safety', () => {
  it('creates an order with prefixed/negative ids, 1:1 side-effects, and zero network', () => {
    useDummyStore.getState().toggle(); // ON

    const before = readGraph();
    const beforeOrders = (before.orders ?? []).length;
    const existingIds = new Set((before.orders ?? []).map((o) => o.id));
    const existingCodes = new Set((before.orders ?? []).map((o) => o.order_id));

    const fetchMock = jest.fn();
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;

    const created = createDummyOrder({ outlet_id: 1, items: [{ product_id: 1, quantity: 2 }] });

    // prefixed string id + negative numeric id, colliding with no existing id
    expect(typeof created.order_id).toBe('string');
    expect(created.order_id.startsWith('dummy-')).toBe(true);
    expect(created.id).toBeLessThan(0);
    expect(existingIds.has(created.id)).toBe(false);
    expect(existingCodes.has(created.order_id)).toBe(false);

    // the new order appears in subsequent store reads and count increased by exactly 1
    const after = readGraph();
    expect((after.orders ?? []).length).toBe(beforeOrders + 1);
    expect((after.orders ?? []).some((o) => o.id === created.id)).toBe(true);

    // exactly one payment, one invoice, one delivery referencing the new order id
    expect((after.payments ?? []).filter((p) => p.order_id === created.id)).toHaveLength(1);
    expect((after.invoices ?? []).filter((i) => i.order_id === created.id)).toHaveLength(1);
    expect((after.deliveries ?? []).filter((d) => d.order_id === created.id)).toHaveLength(1);

    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe('write guards stop network + mutations stay ephemeral', () => {
  it('fakes every write with zero network while ON; OFF still POSTs; toggle cycle clears dummy records', async () => {
    const fetchMock = jest.fn<Promise<unknown>, unknown[]>(async () => ({
      ok: true,
      status: 200,
      json: async () => ({ status: 'success', data: {} as unknown, message: 'ok' }),
    }));
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
    localStorage.setItem('ddp_token', 'test-token');

    useDummyStore.getState().toggle(); // ON

    // ── every write path while ON (sequential) ──
    const salesOrder = await createSalesOrder('test-token', {
      outlet_id: 1,
      items: [{ product_id: 1, quantity: 2 }],
    });
    expect(salesOrder.id).toBeLessThan(0);
    expect(salesOrder.order_id.startsWith('dummy-')).toBe(true);

    const priced = await updateProductPrice('test-token', 1, 99000);
    expect(priced.id).toBe(1);
    expect(Number(priced.price)).toBe(99000);

    const promoPayload = {
      name: 'Promo Dummy',
      description: null,
      discount_type: 'percentage' as const,
      discount_value: 10,
      max_discount: null,
      product_id: null,
      min_order: 50000,
      start_date: '2026-02-14',
      end_date: '2026-02-28',
      is_active: true,
    };
    const createdPromo = await createPromotion('test-token', promoPayload);
    expect(createdPromo.id).toBeLessThan(0);
    expect(createdPromo.name).toBe('Promo Dummy');
    const updatedPromo = await updatePromotion('test-token', createdPromo.id, { name: 'Promo Dummy 2' });
    expect(updatedPromo.id).toBe(createdPromo.id);
    expect(updatedPromo.name).toBe('Promo Dummy 2');
    await deletePromotion('test-token', createdPromo.id);
    const broadcast = await broadcastPromotion('test-token', -999);
    expect(broadcast.status).toBe('ok');

    const roled = await assignUserRole('test-token', 2, 'sales');
    expect(roled.id).toBe(2);
    expect(roled.role).toBe('sales');

    const outlet = await updateOutlet('test-token', 1, { name: 'Outlet Dummy' });
    expect(outlet.id).toBe(1);
    expect(outlet.name).toBe('Outlet Dummy');

    const delivery = await updateDeliveryStatus('test-token', -42, {
      status: 'delivered',
      recipient_name: 'Budi',
      proof_of_delivery_url: 'https://example.test/proof.jpg',
    });
    expect(delivery.id).toBe(-42);
    expect(delivery.status).toBe('delivered');
    expect(delivery.recipient_name).toBe('Budi');
    expect(delivery.delivered_at).not.toBeNull();

    const funnel = await sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 });
    expect(funnel.event_type).toBe('clicked');
    expect(funnel.outlet_id).toBe(1);
    expect(funnel.product_id).toBe(2);
    expect(typeof funnel.event_uuid).toBe('string');

    const payment = await recordPayment('test-token', {
      order_id: 1,
      amount: 150000,
      payment_method: 'cash',
      idempotency_key: 'guard-test-key',
    });
    expect(typeof payment.receipt_reference).toBe('string');
    expect(String(payment.receipt_reference).startsWith('dummy-')).toBe(true);

    const visit = await scheduleVisit('test-token', {
      target: 'Toko Dummy',
      visit_date: '2026-02-20',
    });
    expect(visit.id).toBeLessThan(0);
    expect(visit.target).toBe('Toko Dummy');

    const outletOrder = await submitOutletOrder(
      'test-token',
      [{ product_id: 1, quantity: 1 }],
      'outlet-key-1',
    );
    expect(outletOrder.id).toBeLessThan(0);
    expect(outletOrder.order_id.startsWith('dummy-')).toBe(true);

    // a single cross-writes zero-fetch assertion
    expect(fetchMock).not.toHaveBeenCalled();

    // ── OFF: no regression, real POST is sent ──
    useDummyStore.getState().toggle(); // OFF
    await createSalesOrder('test-token', {
      outlet_id: 1,
      items: [{ product_id: 1, quantity: 1 }],
    });
    expect(fetchMock).toHaveBeenCalled();
    const postCalls = fetchMock.mock.calls.filter(
      (call) => (call[1] as { method?: string } | undefined)?.method === 'POST',
    );
    expect(postCalls.length).toBeGreaterThan(0);

    // ── ephemerality: OFF-then-ON contains none of the created dummy- records ──
    useDummyStore.getState().toggle(); // ON again (fresh baseline)
    const fresh = readGraph();
    expect((fresh.orders ?? []).some((o) => o.order_id === salesOrder.order_id)).toBe(false);
    expect((fresh.orders ?? []).some((o) => o.order_id === outletOrder.order_id)).toBe(false);
    expect((fresh.orders ?? []).some((o) => String(o.order_id).startsWith('dummy-'))).toBe(false);
  });
});
