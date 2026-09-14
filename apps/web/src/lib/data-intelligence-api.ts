import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

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
