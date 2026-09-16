/**
 * Dummy-mode fake mutators (T11).
 *
 * Pure-ish mutators take the current dummy graph and return the next graph
 * plus the fake-success value; the store-bound wrappers read the graph from
 * `useDummyStore`, compute the next graph, write it back via `setState`, and
 * return the fake-success value matching the real API response shape.
 *
 * Invariants (spec Story 3):
 * - Mutations are in-memory only — NEVER persisted to localStorage.
 * - New ids never collide with real auto-increment ids: string ids get a
 *   `dummy-` prefix, numeric ids are negative integers.
 * - Order creation has 1:1 relational side-effects: order → payment + invoice
 *   + delivery, each referencing the new order id.
 * - The store's `toggle()` regenerates a fresh graph ON, so mutations are
 *   ephemeral across OFF/ON (and refresh).
 */
import { useDummyStore } from './store';
import type {
  DummyOrder,
  DummyOrderItem,
  DummyPayment,
  DummyInvoice,
  DummyDelivery,
} from './factory-transactions';
import type { DummyOutlet, DummyProduct } from './factory';

// ─── Fake-success shapes (structurally compatible with the API layer) ────────

export interface DummyCreatedOrderItem {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  unit_price: string;
  subtotal: string;
}

export interface DummyCreatedOrder {
  id: number;
  order_id: string;
  outlet_id: number;
  sales_user_id?: number | null;
  status: string;
  total_amount: string;
  paid_amount?: string;
  promotion_id?: number | null;
  discount_amount?: string | null;
  items: DummyCreatedOrderItem[];
  created_at: string;
  status_history: Array<{ status: string; notes?: string; created_at: string }>;
}

export interface DummyAdminProduct {
  id: number;
  name: string;
  price: string | number;
  sku?: string;
  stock_quantity?: number;
  category?: string;
  is_active?: boolean;
  supplier_id?: number | null;
  description?: string | null;
}

export interface DummyPromotion {
  id: number;
  name: string;
  description?: string | null;
  discount_type: 'percentage' | 'fixed';
  discount_value: string;
  max_discount?: string | null;
  product_id?: number | null;
  product?: { id: number; name: string } | null;
  min_order?: string | null;
  start_date: string;
  end_date: string;
  is_active: boolean;
  broadcast_at?: string | null;
  created_by?: number | null;
  created_at?: string;
  updated_at?: string;
}

export interface DummyAdminUser {
  id: number;
  name: string;
  email: string;
  role: string;
  created_at?: string;
}

export interface DummyAdminOutlet {
  id: number;
  name: string;
  category?: string | null;
  territory_id?: number | null;
  territory?: { id: number; name: string } | null;
  is_active?: boolean;
  score?: number;
  city?: string;
  district?: string;
  address?: string;
  latitude?: number | null;
  longitude?: number | null;
  phone?: string | null;
}

export interface DummyDeliveryRecord {
  id: number;
  order_id: number;
  driver_id: number;
  status: string;
  delivered_at: string | null;
  recipient_name: string | null;
}

export interface DummyPaymentReceipt {
  receipt_reference: string | null;
}

export interface DummyVisit {
  id: number;
  target: string | null;
  visit_date: string;
  status: string;
  notes: string | null;
}

export interface DummyFunnelEventPayload {
  event_uuid: string;
  event_type: string;
  outlet_id?: number | null;
  product_id?: number | null;
  occurred_at?: string;
}

// ─── Input shapes ────────────────────────────────────────────────────────────

export interface CreateOrderItemInput {
  product_id: number;
  quantity: number;
}

export interface CreateOrderInput {
  outlet_id: number;
  items: CreateOrderItemInput[];
}

export interface DeliveryProof {
  recipient_name?: string;
  proof_of_delivery_url?: string;
}

export interface DummyPromotionInput {
  name: string;
  description?: string | null;
  discount_type: 'percentage' | 'fixed';
  discount_value: number;
  max_discount?: number | null;
  product_id?: number | null;
  min_order: number;
  start_date: string;
  end_date: string;
  is_active: boolean;
}

export interface DummyOutletInput {
  name?: string;
  category?: string;
  address?: string;
  city?: string;
  district?: string;
  is_active?: boolean;
}

export interface DummyPaymentInput {
  order_id: number;
  amount: number;
  payment_method: string;
  idempotency_key?: string;
}

export interface DummyVisitInput {
  target: string;
  visit_date: string;
}

// ─── Graph seam ──────────────────────────────────────────────────────────────

export interface DummyGraph {
  orders?: DummyOrder[];
  payments?: DummyPayment[];
  invoices?: DummyInvoice[];
  deliveries?: DummyDelivery[];
  outlets?: DummyOutlet[];
  products?: DummyProduct[];
  promotions?: DummyPromotion[];
  users?: DummyAdminUser[];
  visits?: DummyVisit[];
  funnel_events?: DummyFunnelEventPayload[];
  [key: string]: unknown;
}

const NUMERIC_KEYS = [
  'orders',
  'payments',
  'invoices',
  'deliveries',
  'promotions',
  'users',
  'visits',
] as const;

function getGraph(): DummyGraph {
  return (useDummyStore.getState().dummyEntities ?? {}) as DummyGraph;
}

function setGraph(graph: DummyGraph): void {
  useDummyStore.setState({ dummyEntities: graph as Record<string, unknown> });
}

// ─── Shared helpers ──────────────────────────────────────────────────────────

function money(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

function nowIso(): string {
  return new Date().toISOString();
}

function todayIso(): string {
  return nowIso().slice(0, 10);
}

function existingNumericIds(graph: DummyGraph): number[] {
  const ids: number[] = [];
  for (const key of NUMERIC_KEYS) {
    const entries = graph[key];
    if (!Array.isArray(entries)) continue;
    for (const entry of entries) {
      const id = (entry as { id?: unknown }).id;
      if (typeof id === 'number') ids.push(id);
    }
  }
  return ids;
}

/**
 * Next negative integer that collides with no existing numeric id.
 * Real auto-increment ids are positive, so any negative value is safe.
 */
export function nextDummyNumericId(graph: DummyGraph = getGraph()): number {
  const ids = existingNumericIds(graph);
  return (ids.length ? Math.min(0, ...ids) : 0) - 1;
}

function toCreatedOrder(order: DummyOrder): DummyCreatedOrder {
  return {
    id: order.id,
    order_id: order.order_id,
    outlet_id: order.outlet_id,
    sales_user_id: null,
    status: order.status,
    total_amount: order.total_amount,
    paid_amount: order.paid_amount,
    promotion_id: null,
    discount_amount: null,
    items: order.items,
    created_at: order.created_at,
    status_history: order.status_history,
  };
}

// ─── Order creation (1:1 relational side-effects) ────────────────────────────

export interface ApplyCreateOrderResult {
  graph: DummyGraph;
  order: DummyCreatedOrder;
}

/**
 * Pure mutator: append an order and exactly one linked payment, invoice, and
 * delivery, all keyed by the new (negative) order id.
 */
export function applyCreateOrder(
  graph: DummyGraph,
  outletId: number,
  items: CreateOrderItemInput[],
): ApplyCreateOrderResult {
  const products = graph.products ?? [];
  const outlets = graph.outlets ?? [];
  const orderId = nextDummyNumericId(graph);

  const orderItems: DummyOrderItem[] = items.map((item, index) => {
    const product = products[item.product_id - 1];
    const unitPrice = product ? Number(product.price) : 0;
    const subtotal = unitPrice * item.quantity;
    return {
      id: orderId * 10 - index,
      product_id: item.product_id,
      sku: product?.sku ?? `SKU-${item.product_id}`,
      product_name: product?.name ?? `Produk #${item.product_id}`,
      quantity: item.quantity,
      unit_price: money(unitPrice),
      subtotal: money(subtotal),
    };
  });
  const total = orderItems.reduce((sum, item) => sum + Number(item.subtotal), 0);
  const createdAt = nowIso();
  const outlet = outlets[outletId - 1];

  const order: DummyOrder = {
    id: orderId,
    order_id: `dummy-ORD-${-orderId}`,
    outlet_id: outletId,
    outlet_code: outlet?.id ?? 'dummy-outlet',
    status: 'pending',
    total_amount: money(total),
    paid_amount: '0.00',
    created_at: createdAt,
    items: orderItems,
    status_history: [{ status: 'pending', created_at: createdAt }],
  };

  // 1:1 side-effects. Each id is smaller than the order id, so all four are
  // distinct negatives that collide with no existing id.
  const payment: DummyPayment = {
    id: orderId - 1,
    order_id: orderId,
    amount: '0.00',
    payment_method: 'cash',
    receipt_reference: null,
    created_at: createdAt,
    status: 'pending',
  };

  const invoice: DummyInvoice = {
    id: orderId - 2,
    order_id: orderId,
    invoice_number: `dummy-INV-${-orderId}`,
    issue_date: todayIso(),
    due_date: todayIso(),
    total_amount: money(total),
    paid_amount: '0.00',
    balance_amount: money(total),
    status: 'issued',
  };

  const delivery: DummyDelivery = {
    id: orderId - 3,
    order_id: orderId,
    driver_id: 1,
    status: 'pending',
    delivered_at: null,
    recipient_name: null,
  };

  return {
    graph: {
      ...graph,
      orders: [...(graph.orders ?? []), order],
      payments: [...(graph.payments ?? []), payment],
      invoices: [...(graph.invoices ?? []), invoice],
      deliveries: [...(graph.deliveries ?? []), delivery],
    },
    order: toCreatedOrder(order),
  };
}

/** Store-bound wrapper for `POST /sales/orders`. */
export function createDummyOrder(payload: CreateOrderInput): DummyCreatedOrder {
  const { graph, order } = applyCreateOrder(getGraph(), payload.outlet_id, payload.items);
  setGraph(graph);
  return order;
}

/** Store-bound wrapper for the outlet `POST /orders` path (OrderForm). */
export function createDummyOutletOrder(items: CreateOrderItemInput[]): DummyCreatedOrder {
  const { graph, order } = applyCreateOrder(getGraph(), 1, items);
  setGraph(graph);
  return order;
}

// ─── Delivery status ─────────────────────────────────────────────────────────

/** Store-bound wrapper for `PATCH /deliveries/:id/status`. */
export function updateDummyDelivery(
  deliveryId: number,
  status: string,
  proof?: DeliveryProof,
): DummyDeliveryRecord {
  const graph = getGraph();
  const deliveries = graph.deliveries ?? [];
  const existing = deliveries.find((d) => d.id === deliveryId);

  const deliveredAt = status === 'delivered' ? nowIso() : existing?.delivered_at ?? null;
  const recipientName =
    status === 'delivered'
      ? proof?.recipient_name ?? existing?.recipient_name ?? null
      : existing?.recipient_name ?? null;

  const updated: DummyDelivery = {
    id: deliveryId,
    order_id: existing?.order_id ?? 0,
    driver_id: existing?.driver_id ?? 1,
    status,
    delivered_at: deliveredAt,
    recipient_name: recipientName,
  };

  setGraph({
    ...graph,
    deliveries: existing
      ? deliveries.map((d) => (d.id === deliveryId ? updated : d))
      : [...deliveries, updated],
  });
  return updated;
}

// ─── Product price ───────────────────────────────────────────────────────────

/** Store-bound wrapper for `PATCH /admin/products/:id`. */
export function updateDummyProductPrice(productId: number, price: number): DummyAdminProduct {
  const graph = getGraph();
  const products = graph.products ?? [];
  const existing = products[productId - 1];
  if (existing) {
    const next: DummyProduct = { ...existing, price };
    setGraph({
      ...graph,
      products: products.map((product, index) => (index === productId - 1 ? next : product)),
    });
    return {
      id: productId,
      name: next.name,
      sku: next.sku,
      category: next.category,
      price: next.price,
      is_active: true,
    };
  }
  return { id: productId, name: `Produk #${productId}`, price };
}

// ─── Promotions ──────────────────────────────────────────────────────────────

/** Store-bound wrapper for `POST /admin/promotions`. */
export function createDummyPromotion(payload: DummyPromotionInput): DummyPromotion {
  const graph = getGraph();
  const promotions = graph.promotions ?? [];
  const id = nextDummyNumericId(graph);
  const now = nowIso();
  const promotion: DummyPromotion = {
    id,
    name: payload.name,
    description: payload.description ?? null,
    discount_type: payload.discount_type,
    discount_value: money(payload.discount_value),
    max_discount: payload.max_discount != null ? money(payload.max_discount) : null,
    product_id: payload.product_id ?? null,
    product: null,
    min_order: money(payload.min_order),
    start_date: payload.start_date,
    end_date: payload.end_date,
    is_active: payload.is_active,
    broadcast_at: null,
    created_by: null,
    created_at: now,
    updated_at: now,
  };
  setGraph({ ...graph, promotions: [...promotions, promotion] });
  return promotion;
}

/** Store-bound wrapper for `PATCH /admin/promotions/:id`. */
export function updateDummyPromotion(
  promotionId: number,
  payload: Partial<DummyPromotionInput>,
): DummyPromotion {
  const graph = getGraph();
  const promotions = graph.promotions ?? [];
  const existing = promotions.find((promotion) => promotion.id === promotionId);

  const base: DummyPromotion = existing ?? {
    id: promotionId,
    name: '',
    discount_type: 'fixed',
    discount_value: '0.00',
    min_order: '0.00',
    start_date: todayIso(),
    end_date: todayIso(),
    is_active: false,
  };

  const updated: DummyPromotion = {
    ...base,
    ...(payload.name !== undefined ? { name: payload.name } : {}),
    ...(payload.description !== undefined ? { description: payload.description ?? null } : {}),
    ...(payload.discount_type !== undefined ? { discount_type: payload.discount_type } : {}),
    ...(payload.discount_value !== undefined
      ? { discount_value: money(payload.discount_value) }
      : {}),
    ...(payload.max_discount !== undefined
      ? { max_discount: payload.max_discount != null ? money(payload.max_discount) : null }
      : {}),
    ...(payload.product_id !== undefined ? { product_id: payload.product_id } : {}),
    ...(payload.min_order !== undefined ? { min_order: money(payload.min_order) } : {}),
    ...(payload.start_date !== undefined ? { start_date: payload.start_date } : {}),
    ...(payload.end_date !== undefined ? { end_date: payload.end_date } : {}),
    ...(payload.is_active !== undefined ? { is_active: payload.is_active } : {}),
    updated_at: nowIso(),
  };

  setGraph({
    ...graph,
    promotions: existing
      ? promotions.map((promotion) => (promotion.id === promotionId ? updated : promotion))
      : [...promotions, updated],
  });
  return updated;
}

/** Store-bound wrapper for `DELETE /admin/promotions/:id`. */
export function deleteDummyPromotion(promotionId: number): void {
  const graph = getGraph();
  setGraph({
    ...graph,
    promotions: (graph.promotions ?? []).filter((promotion) => promotion.id !== promotionId),
  });
}

/** Store-bound wrapper for `POST /admin/promotions/:id/broadcast`. */
export function broadcastDummyPromotion(promotionId: number): { status: string; message: string } {
  const graph = getGraph();
  const broadcastAt = nowIso();
  setGraph({
    ...graph,
    promotions: (graph.promotions ?? []).map((promotion) =>
      promotion.id === promotionId ? { ...promotion, broadcast_at: broadcastAt } : promotion,
    ),
  });
  return { status: 'ok', message: 'Berhasil menyiarkan promosi.' };
}

// ─── Users ───────────────────────────────────────────────────────────────────

/** Store-bound wrapper for `PATCH /admin/users/:id/role`. */
export function assignDummyUserRole(userId: number, role: string): DummyAdminUser {
  const graph = getGraph();
  const users = graph.users ?? [];
  const existing = users.find((user) => user.id === userId);
  const updated: DummyAdminUser = existing
    ? { ...existing, role }
    : {
        id: userId,
        name: `Pengguna ${userId}`,
        email: `dummy${userId}@example.com`,
        role,
        created_at: nowIso(),
      };
  setGraph({
    ...graph,
    users: existing
      ? users.map((user) => (user.id === userId ? updated : user))
      : [...users, updated],
  });
  return updated;
}

// ─── Outlets ─────────────────────────────────────────────────────────────────

/** Store-bound wrapper for `PATCH /admin/outlets/:id`. */
export function updateDummyOutlet(outletId: number, payload: DummyOutletInput): DummyAdminOutlet {
  const graph = getGraph();
  const outlets = graph.outlets ?? [];
  const existing = outlets[outletId - 1];

  if (existing && payload.name !== undefined) {
    setGraph({
      ...graph,
      outlets: outlets.map((outlet, index) =>
        index === outletId - 1 ? { ...outlet, name: payload.name as string } : outlet,
      ),
    });
  }

  return {
    id: outletId,
    name: payload.name ?? existing?.name ?? `Outlet #${outletId}`,
    category: payload.category ?? null,
    city: payload.city ?? existing?.city,
    district: payload.district,
    address: payload.address,
    is_active: payload.is_active ?? true,
  };
}

// ─── Funnel tracking ─────────────────────────────────────────────────────────

/**
 * Store-bound wrapper for `POST /admin/measurement/events`.
 *
 * The caller (data-intelligence-api) builds the payload with the canonical
 * builder and passes it in, so this module never imports from the API layer
 * (no runtime cycle).
 */
export function sendDummyFunnelEvent(
  payload: DummyFunnelEventPayload,
): DummyFunnelEventPayload {
  const graph = getGraph();
  setGraph({
    ...graph,
    funnel_events: [...(graph.funnel_events ?? []), payload],
  });
  return payload;
}

// ─── Payments ────────────────────────────────────────────────────────────────

/** Store-bound wrapper for the `POST /payments` path (payments page). */
export function createDummyPayment(payload: DummyPaymentInput): DummyPaymentReceipt {
  const graph = getGraph();
  const payments = graph.payments ?? [];
  const id = nextDummyNumericId(graph);
  const receiptReference = `dummy-REC-${-id}`;
  const payment: DummyPayment = {
    id,
    order_id: payload.order_id,
    amount: money(payload.amount),
    payment_method: payload.payment_method,
    receipt_reference: receiptReference,
    created_at: nowIso(),
    status: 'completed',
  };
  setGraph({ ...graph, payments: [...payments, payment] });
  return { receipt_reference: receiptReference };
}

// ─── Sales visits ────────────────────────────────────────────────────────────

/** Store-bound wrapper for the `POST /sales/visits` path (sales page). */
export function createDummyVisit(payload: DummyVisitInput): DummyVisit {
  const graph = getGraph();
  const visits = graph.visits ?? [];
  const visit: DummyVisit = {
    id: nextDummyNumericId(graph),
    target: payload.target,
    visit_date: payload.visit_date,
    status: 'scheduled',
    notes: null,
  };
  setGraph({ ...graph, visits: [...visits, visit] });
  return visit;
}
