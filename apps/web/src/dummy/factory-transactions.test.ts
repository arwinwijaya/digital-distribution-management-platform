/**
 * RED cycle: transaction volumes + relational integrity + 60-day window.
 *
 * Given T5 MasterData + fixed today → buildTransactions(master, window):
 * order count 800..1000 spanning the full 60-day window; every order → existing
 * outlet id + ≥1 item → existing product id; every order exactly one
 * payment/invoice/delivery (1:1) referencing order id; two runs deep-equal.
 */
import { createSeededRng } from '@/dummy/rng';
import { dummyWindow } from '@/dummy/dates';
import { DUMMY_SEED } from '@/dummy/seed';
import { buildMasterData } from '@/dummy/factory';
import { buildTransactions } from '@/dummy/factory-transactions';
import type {
  DummyOrder,
  DummyPayment,
  DummyInvoice,
  DummyDelivery,
} from '@/dummy/factory-transactions';

const TODAY = new Date('2026-02-14T10:00:00+07:00');

describe('buildTransactions', () => {
  const window = dummyWindow(TODAY);
  const rng = createSeededRng(DUMMY_SEED);
  const master = buildMasterData(rng, window);
  const result = buildTransactions(master, window);

  it('produces 800..1000 orders', () => {
    expect(result.orders.length).toBeGreaterThanOrEqual(800);
    expect(result.orders.length).toBeLessThanOrEqual(1000);
  });

  it('spans the full 60-day window', () => {
    const dates = result.orders.map((o) => String(o.created_at).slice(0, 10));
    const sorted = dates.slice().sort();
    expect(sorted[0] >= window.start).toBe(true);
    expect(sorted[sorted.length - 1] <= window.end).toBe(true);
    const uniqueDates = new Set(dates);
    // Trend-shaped volumes must actually fill the window (not a burst of one day).
    expect(uniqueDates.size).toBeGreaterThanOrEqual(45);
  });

  it('every order references an existing outlet id', () => {
    for (const order of result.orders) {
      expect(Number.isInteger(order.outlet_id)).toBe(true);
      expect(order.outlet_id).toBeGreaterThanOrEqual(1);
      expect(order.outlet_id).toBeLessThanOrEqual(master.outlets.length);
      // outlet_code always resolves to the master string id
      expect(order.outlet_code).toBe(master.outlets[order.outlet_id - 1].id);
    }
  });

  it('every order has ≥1 item referencing an existing product id', () => {
    for (const order of result.orders) {
      expect(order.items.length).toBeGreaterThanOrEqual(1);
      for (const item of order.items) {
        expect(item.product_id).toBeGreaterThanOrEqual(1);
        expect(item.product_id).toBeLessThanOrEqual(master.products.length);
        expect(item.sku).toBe(master.products[item.product_id - 1].sku);
      }
    }
  });

  it('every order has exactly one payment/invoice/delivery referencing its order id', () => {
    const orderIds = result.orders.map((o) => o.id);
    const paymentOrderIds = result.payments.map((p: DummyPayment) => p.order_id);
    const invoiceOrderIds = result.invoices.map((i: DummyInvoice) => i.order_id);
    const deliveryOrderIds = result.deliveries.map((d: DummyDelivery) => d.order_id);

    expect(paymentOrderIds).toHaveLength(orderIds.length);
    expect(invoiceOrderIds).toHaveLength(orderIds.length);
    expect(deliveryOrderIds).toHaveLength(orderIds.length);

    const paymentSet = new Set(paymentOrderIds);
    const invoiceSet = new Set(invoiceOrderIds);
    const deliverySet = new Set(deliveryOrderIds);
    for (const id of orderIds) {
      expect(paymentSet.has(id)).toBe(true);
      expect(invoiceSet.has(id)).toBe(true);
      expect(deliverySet.has(id)).toBe(true);
    }
  });

  it('every order has a unique order_id string and unique numeric id', () => {
    const ids = result.orders.map((o: DummyOrder) => o.id);
    expect(new Set(ids).size).toBe(ids.length);
    const codes = result.orders.map((o: DummyOrder) => o.order_id);
    expect(new Set(codes).size).toBe(codes.length);
  });

  it('is deterministic: two runs with same inputs yield deep-equal results', () => {
    const first = buildTransactions(master, window);
    const second = buildTransactions(master, window);
    expect(first).toEqual(second);
  });

  it('trend is not uniform: daily volumes vary across the window', () => {
    const counts = new Map<string, number>();
    for (const order of result.orders) {
      const day = String(order.created_at).slice(0, 10);
      counts.set(day, (counts.get(day) ?? 0) + 1);
    }
    const values = Array.from(counts.values());
    expect(new Set(values).size).toBeGreaterThan(10);
  });
});
