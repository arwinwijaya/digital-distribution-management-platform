import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { JABODETABEK_TERRITORIES } from '@/dummy/seed';

// ── Dummy-mode helpers (READ paths only; writes are T11) ─────────────────────
interface DummyOutletEntity {
  id: string;
  name: string;
  territoryId: string;
  city: string;
  lat: number;
  lon: number;
}
interface DummyProductEntity { sku: string; name: string; category: string; price: number }

const DUMMY_OUTLET_CATEGORIES = [
  'warung',
  'minimarket',
  'supermarket',
  'grosir',
  'restoran',
  'kafe',
  'toko_kelontong',
  'lainnya',
];

function dummyOutlets(): DummyOutletEntity[] {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ outlets: DummyOutletEntity[] }> | null;
  return entities?.outlets ?? [];
}

function dummyProducts(): DummyProductEntity[] {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ products: DummyProductEntity[] }> | null;
  return entities?.products ?? [];
}

function territoryIdOf(territoryId: string, fallbackIndex: number): number {
  const idx = JABODETABEK_TERRITORIES.findIndex((t) => t.id === territoryId);
  return idx >= 0 ? idx + 1 : fallbackIndex + 1;
}

function listDummySalesOutlets(): SalesOutlet[] {
  return dummyOutlets().map((o, i) => ({
    id: Number(String(o.id).replace(/^dummy-/, '')) || i + 1,
    name: o.name,
    category: DUMMY_OUTLET_CATEGORIES[i % DUMMY_OUTLET_CATEGORIES.length],
    city: o.city,
    district: `${o.city} ${(i % 5) + 1}`,
    territory_id: territoryIdOf(o.territoryId, i),
  }));
}

function listDummyCatalogProducts(search?: string): CatalogProduct[] {
  let list = dummyProducts().map((p, i) => ({
    id: i + 1,
    name: p.name,
    price: p.price.toFixed(2),
    stock_quantity: 40 + ((i * 11 + 7) % 160),
  } as CatalogProduct));
  if (search) {
    const q = search.toLowerCase();
    list = list.filter((p) => p.name.toLowerCase().includes(q));
  }
  return list;
}

export interface SalesOutlet {
  id: number;
  name: string;
  category?: string | null;
  city?: string | null;
  district?: string | null;
  territory_id?: number | null;
}

export interface CatalogProduct {
  id: number;
  name: string;
  price: string | number;
  stock_quantity?: number | null;
}

export interface OrderItemInput {
  product_id: number;
  quantity: number;
}

export interface CreatedOrderItem {
  id: number;
  product_id: number;
  product_name?: string | null;
  quantity: number;
  unit_price: string;
  subtotal: string;
}

export interface CreatedSalesOrder {
  id: number;
  order_id: string;
  outlet_id: number;
  sales_user_id?: number | null;
  status: string;
  total_amount: string;
  paid_amount?: string;
  promotion_id?: number | null;
  discount_amount?: string | null;
  items: CreatedOrderItem[];
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

/**
 * Fetch outlets scoped to the authenticated sales user's territory.
 * Backend enforces territory binding (403 when territory is unassigned).
 */
export async function fetchSalesOutlets(token: string): Promise<SalesOutlet[]> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummySalesOutlets(),
    () => fetchSalesOutletsReal(token),
  );
}

async function fetchSalesOutletsReal(token: string): Promise<SalesOutlet[]> {
  const response = await fetch(apiUrl('/sales/outlets'), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar outlet tidak dapat dimuat.'));
  return Array.isArray(data.data) ? (data.data as SalesOutlet[]) : [];
}

export async function fetchCatalogProducts(token: string, search?: string): Promise<CatalogProduct[]> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyCatalogProducts(search),
    () => fetchCatalogProductsReal(token, search),
  );
}

async function fetchCatalogProductsReal(token: string, search?: string): Promise<CatalogProduct[]> {
  const query = new URLSearchParams();
  if (search) query.set('search', search);
  const qs = query.toString();
  const response = await fetch(apiUrl(`/products${qs ? `?${qs}` : ''}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar produk tidak dapat dimuat.'));
  return Array.isArray(data.data) ? (data.data as CatalogProduct[]) : [];
}

export async function createSalesOrder(
  token: string,
  payload: { outlet_id: number; items: OrderItemInput[] },
): Promise<CreatedSalesOrder> {
  const response = await fetch(apiUrl('/sales/orders'), {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Pesanan tidak dapat dibuat.'));
  return (data.data ?? data) as CreatedSalesOrder;
}

/** Parse a monetary string-or-number (fixed 2-decimal) into a display number. */
export function parseMoney(value: string | number | null | undefined): number {
  if (value === null || value === undefined) return 0;
  const n = typeof value === 'number' ? value : Number(String(value).replace(/[^0-9.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

/** Format a monetary string-or-number into id-ID Rupiah display. */
export function formatRupiah(value: string | number | null | undefined): string {
  return `Rp ${parseMoney(value).toLocaleString('id-ID')}`;
}
