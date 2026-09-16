/**
 * Deterministic transaction factory for dummy mode.
 *
 * Builds ~900 orders across the 60-day window with items, 1:1 payment/invoice/
 * delivery. Trend-shaped daily volumes with weekday/weekend modulation.
 *
 * Pure functions only — no store, no fetch, no Date.now().
 */
import { createSeededRng } from './rng';
import type { SeededRng } from './rng';
import type { DateWindow } from './dates';
import { daysBetween } from './dates';
import type { MasterData } from './factory';

// ─── Types ───────────────────────────────────────────────────────────────────

export interface DummyOrderItem {
  id: number;
  product_id: number;
  sku: string;
  product_name: string;
  quantity: number;
  unit_price: string;
  subtotal: string;
}

export interface DummyOrder {
  id: number;
  order_id: string;
  outlet_id: number;
  outlet_code: string;
  status: string;
  total_amount: string;
  paid_amount: string;
  created_at: string;
  items: DummyOrderItem[];
  status_history: Array<{ status: string; notes?: string; created_at: string }>;
}

export interface DummyPayment {
  id: number;
  order_id: number;
  amount: string;
  payment_method: string;
  receipt_reference: string | null;
  created_at: string;
  status: string;
}

export interface DummyInvoice {
  id: number;
  order_id: number;
  invoice_number: string;
  issue_date: string;
  due_date: string;
  total_amount: string;
  paid_amount: string;
  balance_amount: string;
  status: string;
}

export interface DummyDelivery {
  id: number;
  order_id: number;
  driver_id: number;
  status: string;
  delivered_at: string | null;
  recipient_name: string | null;
}

export interface Transactions {
  orders: DummyOrder[];
  payments: DummyPayment[];
  invoices: DummyInvoice[];
  deliveries: DummyDelivery[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Format a number as a decimal string matching the Laravel backend convention:
 * `number_format($value, 2, '.', '')` → e.g. "150000.00".
 * All monetary consumers call `Number(value).toLocaleString('id-ID')`.
 */
function money(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

const PAYMENT_METHODS = [
  'cash',
  'transfer',
  'credit',
  'qris',
  'e_wallet',
];

const ORDER_STATUSES = [
  'pending',
  'confirmed',
  'shipped',
  'delivered',
  'cancelled',
];

const INVOICE_STATUSES = ['issued', 'sent', 'paid', 'overdue', 'cancelled'];
const DELIVERY_STATUSES = ['pending', 'in_transit', 'delivered', 'failed'];

/** Get day-of-week in UTC. 0=Sun, 1=Mon ... 6=Sat */
function dayOfWeekUtc(isoDate: string): number {
  return new Date(`${isoDate}T12:00:00Z`).getUTCDay();
}

/** Weekday multiplier: Mon-Fri ~1.15, Sat ~0.85, Sun ~0.60 */
function weekdayFactor(dow: number): number {
  if (dow >= 1 && dow <= 5) return 1.15;
  if (dow === 6) return 0.85;
  return 0.60;
}

function firstName(rng: SeededRng): string {
  const names = [
    'Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko',
    'Fajar', 'Gita', 'Hadi', 'Indah', 'Joko',
    'Kurnia', 'Lestari', 'Maya', 'Nanda', 'Omar',
  ];
  return rng.pick(names);
}

function lastName(rng: SeededRng): string {
  const names = [
    'Pratama', 'Sari', 'Wijaya', 'Putri', 'Santoso',
    'Handayani', 'Susanto', 'Lestari', 'Hidayat', 'Kusuma',
  ];
  return rng.pick(names);
}

// ─── Factory ─────────────────────────────────────────────────────────────────

/**
 * Build deterministic transactions (orders, payments, invoices, deliveries)
 * with trend-shaped daily volumes and 1:1 relational linkage.
 *
 * @param master  T5 master data (outlets, products, suppliers)
 * @param window  60-day date window
 */
export function buildTransactions(
  master: MasterData,
  window: DateWindow,
): Transactions {
  const rng = createDeterministicRng(master, window);

  const days = daysBetween(window.start, window.end); // 61 days
  const totalOutlets = master.outlets.length;

  // ── 1. Trend-shaped daily order counts ────────────────────────────────
  // Base ~14.75/day; weekday/weekend modulation; slight growth trend + jitter.
  const dayBuckets: Array<{ iso: string; count: number }> = [];
  let rawTotal = 0;

  for (let i = 0; i < days.length; i++) {
    const iso = days[i];
    const dow = dayOfWeekUtc(iso);
    const wf = weekdayFactor(dow);
    const trend = 1.0 + 0.08 * (i / (days.length - 1)); // slight upward trend
    const base = 14.75 * wf * trend;
    const jitter = 0.82 + rng.next() * 0.36; // ±18% jitter
    const count = Math.max(2, Math.round(base * jitter));
    dayBuckets.push({ iso, count });
    rawTotal += count;
  }

  // Normalize to hit ~900 (target in [860, 940]) while preserving the shape.
  const TARGET = 900;
  const scale = TARGET / rawTotal;
  let finalTotal = 0;
  for (const bucket of dayBuckets) {
    bucket.count = Math.max(1, Math.round(bucket.count * scale));
    finalTotal += bucket.count;
  }

  // Fine-tune: if total deviates, add/remove 1 order at the end of the array
  // to hit exactly TARGET (or at least stay within 860..940).
  while (finalTotal > 960) {
    // Remove from the day with the most orders
    const maxBucket = dayBuckets.reduce((a, b) => (b.count > a.count ? b : a));
    maxBucket.count -= 1;
    finalTotal -= 1;
  }
  while (finalTotal < 840) {
    // Add to the day with the fewest orders
    const minBucket = dayBuckets.reduce((a, b) => (b.count < a.count ? b : a));
    minBucket.count += 1;
    finalTotal += 1;
  }

  // ── 2. Build orders ──────────────────────────────────────────────────
  const orders: DummyOrder[] = [];
  const payments: DummyPayment[] = [];
  const invoices: DummyInvoice[] = [];
  const deliveries: DummyDelivery[] = [];

  let orderIdCounter = 1;

  for (const bucket of dayBuckets) {
    for (let j = 0; j < bucket.count; j++) {
      const id = orderIdCounter;
      const orderCode = `ORD-${bucket.iso}-${String(id).padStart(4, '0')}`;

      // Outlet: pick one deterministically
      const outletIndex = rng.int(0, totalOutlets - 1);
      const outlet = master.outlets[outletIndex];

      // Items: 1-5 products
      const itemCount = rng.int(1, 5);
      const usedProducts = new Set<number>();
      const items: DummyOrderItem[] = [];
      let orderTotal = 0;

      for (let k = 0; k < itemCount; k++) {
        let prodIndex: number;
        do {
          prodIndex = rng.int(0, master.products.length - 1);
        } while (usedProducts.has(prodIndex) && usedProducts.size < master.products.length);
        usedProducts.add(prodIndex);

        const product = master.products[prodIndex];
        const qty = rng.int(1, 20);
        const unitPrice = product.price;
        const subtotal = unitPrice * qty;
        orderTotal += subtotal;

        items.push({
          id: id * 100 + k + 1,
          product_id: prodIndex + 1,
          sku: product.sku,
          product_name: product.name,
          quantity: qty,
          unit_price: money(unitPrice),
          subtotal: money(subtotal),
        });
      }

      // Status: weighted toward delivered
      const statusRoll = rng.next();
      let status: string;
      if (statusRoll < 0.10) status = 'cancelled';
      else if (statusRoll < 0.25) status = 'pending';
      else if (statusRoll < 0.40) status = 'confirmed';
      else if (statusRoll < 0.55) status = 'shipped';
      else status = 'delivered';

      // Timestamps: bucket date + time spread within the day
      const hour = rng.int(6, 21);
      const minute = rng.int(0, 59);
      const createdAt = `${bucket.iso}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+07:00`;

      const statusHistory: DummyOrder['status_history'] = [
        { status: 'pending', created_at: createdAt },
      ];
      if (status === 'confirmed' || status === 'shipped' || status === 'delivered') {
        statusHistory.push({
          status: 'confirmed',
          created_at: `${bucket.iso}T${String(hour + 1).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+07:00`,
        });
      }
      if (status === 'shipped' || status === 'delivered') {
        statusHistory.push({
          status: 'shipped',
          created_at: `${bucket.iso}T${String(Math.min(hour + 3, 23)).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+07:00`,
        });
      }
      if (status === 'delivered') {
        statusHistory.push({
          status: 'delivered',
          created_at: `${bucket.iso}T${String(Math.min(hour + 6, 23)).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+07:00`,
        });
      }

      const paidAmount =
        status === 'cancelled'
          ? 0
          : status === 'pending'
            ? 0
            : orderTotal;

      orders.push({
        id,
        order_id: orderCode,
        outlet_id: outletIndex + 1,
        outlet_code: outlet.id,
        status,
        total_amount: money(orderTotal),
        paid_amount: money(paidAmount),
        created_at: createdAt,
        items,
        status_history: statusHistory,
      });

      // ── 1:1 Payment ──────────────────────────────────────────────────
      const paymentMethod = rng.pick(PAYMENT_METHODS);
      const paymentId = id;
      const paymentStatus =
        status === 'cancelled'
          ? 'refunded'
          : status === 'pending'
            ? 'pending'
            : status === 'delivered'
              ? 'completed'
              : 'partial';

      payments.push({
        id: paymentId,
        order_id: id,
        amount: money(paidAmount),
        payment_method: paymentMethod,
        receipt_reference:
          paymentStatus === 'completed' ? `REC-${paymentId}` : null,
        created_at: createdAt,
        status: paymentStatus,
      });

      // ── 1:1 Invoice ──────────────────────────────────────────────────
      const invNumber = `INV-${bucket.iso}-${String(id).padStart(4, '0')}`;
      const invIssueDate = bucket.iso;
      const invDueDate = `${bucket.iso.slice(0, 7)}-28`;
      const invStatus =
        status === 'cancelled'
          ? 'cancelled'
          : status === 'delivered'
            ? 'paid'
            : status === 'pending'
              ? 'issued'
              : 'sent';
      const invBalance =
        invStatus === 'paid' ? 0 : orderTotal - paidAmount;

      invoices.push({
        id: paymentId,
        order_id: id,
        invoice_number: invNumber,
        issue_date: invIssueDate,
        due_date: invDueDate,
        total_amount: money(orderTotal),
        paid_amount: money(paidAmount),
        balance_amount: money(invBalance),
        status: invStatus,
      });

      // ── 1:1 Delivery ─────────────────────────────────────────────────
      const driverId = rng.int(1, 12);
      const deliveryStatus =
        status === 'cancelled'
          ? 'failed'
          : status === 'delivered'
            ? 'delivered'
            : status === 'shipped'
              ? 'in_transit'
              : 'pending';
      const deliveredAt =
        deliveryStatus === 'delivered'
          ? `${bucket.iso}T${String(Math.min(hour + 6, 23)).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+07:00`
          : null;
      const recipientName =
        deliveryStatus === 'delivered'
          ? `${firstName(rng)} ${lastName(rng)}`
          : null;

      deliveries.push({
        id: paymentId,
        order_id: id,
        driver_id: driverId,
        status: deliveryStatus,
        delivered_at: deliveredAt,
        recipient_name: recipientName,
      });

      orderIdCounter += 1;
    }
  }

  return { orders, payments, invoices, deliveries };
}

/**
 * Create a deterministic RNG seeded from master data + window.
 * Combines outlet IDs, product SKUs, and window strings into a seed.
 */
function createDeterministicRng(
  master: MasterData,
  window: DateWindow,
): SeededRng {
  const seed = [
    'tx',
    window.start,
    window.end,
    String(master.outlets.length),
    String(master.products.length),
    master.products.map((p) => p.sku).join(','),
  ].join('|');
  return createSeededRng(seed);
}
