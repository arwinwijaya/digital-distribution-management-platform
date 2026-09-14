import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

function generateUuid(): string {
  if (typeof crypto !== 'undefined' && typeof (crypto as Crypto & { randomUUID?: () => string }).randomUUID === 'function') {
    return (crypto as Crypto & { randomUUID: () => string }).randomUUID();
  }
  const bytes: Uint8Array =
    typeof crypto !== 'undefined' && typeof (crypto as Crypto).getRandomValues === 'function'
      ? (crypto as Crypto).getRandomValues(new Uint8Array(16))
      : new Uint8Array(Array.from({ length: 16 }, () => Math.floor(Math.random() * 256)));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/* ------------------------------------------------------------------ */
/*  Shared API wrapper                                                 */
/* ------------------------------------------------------------------ */

interface ApiResponse<T> {
  status: 'success' | 'error';
  message?: string;
  data: T;
}

async function adminFetch<T>(path: string): Promise<T> {
  const token = getStoredToken();
  if (!token) throw new Error('Tidak terotentikasi.');
  const res = await fetch(apiUrl(path), { headers: authHeaders(token) });
  const body: ApiResponse<T> = await res.json();
  if (!res.ok || body.status === 'error') {
    throw new Error(body.message || 'Gagal memuat data.');
  }
  return body.data;
}

/* ------------------------------------------------------------------ */
/*  Geographic                                                         */
/* ------------------------------------------------------------------ */

export interface GeographicTableRow {
  territory: string;
  sales: string;
  orders: number;
  outlets: number;
}

export interface GeographicMapPoint {
  outlet_id: number;
  outlet_name: string;
  territory: string;
  latitude: number;
  longitude: number;
  orders: number;
  sales: string;
}

export interface GeographicData {
  table: GeographicTableRow[];
  map_points: GeographicMapPoint[];
  snapshot_version: number;
  window: { start: string; end: string; timezone: string };
}

export async function fetchGeographicData(): Promise<GeographicData> {
  return adminFetch<GeographicData>('/admin/analytics/geographic');
}

/* ------------------------------------------------------------------ */
/*  Supplier Performance                                               */
/* ------------------------------------------------------------------ */

export interface SupplierRecord {
  supplier_id: number;
  supplier_name: string;
  status: string;
  fulfillment: { numerator: number; denominator: number; ratio: number | null };
  on_time: { numerator: number; denominator: number; ratio: number | null; eligible: number; excluded: number };
  catalog: { numerator: number; denominator: number; ratio: number | null };
  weighted_score: number | null;
}

export interface SupplierPerformanceData {
  suppliers: SupplierRecord[];
  snapshot_version: number;
  window: { start: string; end: string; timezone: string };
}

export async function fetchSupplierPerformanceData(): Promise<SupplierPerformanceData> {
  return adminFetch<SupplierPerformanceData>('/admin/analytics/suppliers');
}

/* ------------------------------------------------------------------ */
/*  Stock Planning                                                     */
/* ------------------------------------------------------------------ */

export interface StockPlanRecord {
  product_id: number;
  product_name: string;
  sku: string;
  supplier_id: number;
  lead_time_days: number | null;
  available_stock: number;
  total_demand: number;
  average_daily_demand: number;
  lead_time_demand: number | null;
  reorder_quantity: number;
  has_warning: boolean;
  status: string;
}

export interface StockPlanningData {
  items: StockPlanRecord[];
  snapshot_version: number;
  window: { start: string; end: string; timezone: string };
}

export async function fetchStockPlanningData(): Promise<StockPlanningData> {
  return adminFetch<StockPlanningData>('/admin/analytics/stock-planning');
}

/* ------------------------------------------------------------------ */
/*  Recommendation Measurement                                         */
/* ------------------------------------------------------------------ */

export interface RecommendationMeasurementData {
  funnel: Array<{ step: string; count: number }>;
  rates: {
    clicked_rate: number;
    cart_rate: number;
    purchased_rate: number;
    overall_conversion_rate: number;
  };
  attribution: Record<string, unknown>;
  snapshot_version: number;
  window: { start: string; end: string; timezone: string };
}

export async function fetchRecommendationMeasurementData(): Promise<RecommendationMeasurementData> {
  return adminFetch<RecommendationMeasurementData>('/admin/analytics/measurement/recommendations');
}

/* ------------------------------------------------------------------ */
/*  Forecast Measurement                                               */
/* ------------------------------------------------------------------ */

export interface ForecastMeasurementData {
  status: string;
  wape: string | null;
  accuracy: number | null;
  accuracy_percent: number | null;
  actual_days: number;
  minimum_required_days: number;
  target_achieved: boolean;
  target: string;
  snapshot_version: number;
  window: { start: string; end: string; timezone: string };
  note: string;
}

export async function fetchForecastMeasurementData(): Promise<ForecastMeasurementData> {
  return adminFetch<ForecastMeasurementData>('/admin/analytics/measurement/forecasts');
}

export type FunnelEventType = 'displayed' | 'clicked' | 'cart';

export interface FunnelEventContext {
  outlet_id?: number | null;
  product_id?: number | null;
  occurred_at?: string;
}

export interface FunnelEventPayload {
  event_uuid: string;
  event_type: FunnelEventType;
  outlet_id?: number | null;
  product_id?: number | null;
  occurred_at?: string;
}

export function buildFunnelEventPayload(
  event: FunnelEventType,
  context: FunnelEventContext = {},
  eventUuid: string = generateUuid(),
): FunnelEventPayload {
  return {
    event_uuid: eventUuid,
    event_type: event,
    outlet_id: context.outlet_id ?? null,
    product_id: context.product_id ?? null,
    occurred_at: context.occurred_at,
  };
}

export async function sendFunnelEvent(
  event: FunnelEventType,
  context: FunnelEventContext = {},
  eventUuid: string = generateUuid(),
): Promise<FunnelEventPayload> {
  const token = getStoredToken();
  if (!token) throw new Error('Tidak terotentikasi.');
  const payload = buildFunnelEventPayload(event, context, eventUuid);
  const res = await fetch(apiUrl('/admin/measurement/events'), {
    method: 'POST',
    headers: { ...authHeaders(token), 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const body: ApiResponse<{ event_uuid: string }> = await res.json();
  if (!res.ok || body.status === 'error') {
    throw new Error(body.message || 'Gagal mengirim event.');
  }
  return payload;
}
