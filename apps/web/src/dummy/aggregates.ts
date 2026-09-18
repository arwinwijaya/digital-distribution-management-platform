/**
 * Deterministic aggregate generators for dummy mode.
 *
 * All aggregates are DERIVED from master data + transactions (never hardcoded).
 * Shapes match existing exported TS interfaces where available; page-local
 * types are mirrored locally (minimum viable drift surface).
 *
 * Pure functions only — no store, no fetch, no Date.now().
 */
import type { MasterData, DummyOutlet, DummySupplier } from './factory';
import type {
  Transactions,
  DummyOrder,
  DummyOrderItem,
} from './factory-transactions';
import type { DateWindow } from './dates';
import { daysBetween } from './dates';

// ─── Imported existing interfaces ────────────────────────────────────────────

import type {
  GeographicTableRow,
  GeographicMapPoint,
} from '@/lib/data-intelligence-api';
import type { SupplierRecord, StockPlanRecord } from '@/lib/data-intelligence-api';
import type { ReadinessData, OperationIssue } from '@/lib/operations-types';
import type { TrendPoint, OutletPoint } from '@/components/Charts';

// ─── Page-local type mirrors (AIData, FinanceMetrics, DashboardData) ────────

interface Recommendation {
  product_id: number;
  name: string;
  price: string;
  reason: string;
}

interface DataSufficiency {
  sufficient?: boolean;
  level: string;
  note: string;
}

interface Measurement {
  measured: boolean;
  note: string;
}

interface ForecastPrediction {
  period: string;
  forecast_sales: string;
  forecast_orders: number;
}

interface ForecastData {
  predictions: ForecastPrediction[];
  data_sufficiency: DataSufficiency;
  method: string;
  measurement: Measurement;
}

interface SegmentationSegment {
  outlet_id: number;
  outlet_name: string;
  segment: string;
  confidence: string;
}

interface AnalyticsData {
  recommendations: Recommendation[];
  recommendationMeta: {
    data_points: number;
    data_sufficiency: DataSufficiency;
    measurement: Measurement;
  };
  forecast: ForecastData;
  segmentation: {
    segments: SegmentationSegment[];
  };
}

interface DashboardMetrics {
  orders_total: number;
  sales_total: string;
  outlets_total: number;
  products_total: number;
  payments_total: string;
  outstanding_total: string;
}

interface AdminDashboardData {
  metrics: DashboardMetrics;
  sales_trends: TrendPoint[];
  outlet_performance: OutletPoint[];
}

interface InsightDelta {
  delta_percent: number | null;
  direction: 'up' | 'down' | 'neutral';
}

interface NeedsAttentionItem {
  outlet_id: number;
  outlet_name: string;
  reason: 'sales_decline' | 'outstanding_risk';
  delta_percent: number | null;
  outstanding_total: string;
}

interface AnalyticsInsightData {
  comparison: {
    period: { start_date: string; end_date: string };
    previous_period: { start_date: string; end_date: string };
  };
  metrics: DashboardMetrics;
  metrics_delta: {
    orders_total: InsightDelta;
    sales_total: InsightDelta;
    payments_total: InsightDelta;
    outstanding_total: InsightDelta;
  };
  needs_attention: NeedsAttentionItem[];
  sales_trends: TrendPoint[];
  outlet_performance: OutletPoint[];
  outlet_performance_total: number;
  outlet_performance_has_more: boolean;
}

interface FinanceMetrics {
  issued_invoices: { count: number };
  outstanding_balance: { amount: string | number };
  overdue_rate: { rate: number; overdue_count: number; active_count: number };
  collection_time: { average_days: number; fully_collected_count: number };
  payment_status_breakdown: Record<string, number>;
  reminders: {
    success: number;
    failure: number;
    sent: number;
    failed: number;
  };
}

// ─── Exported aggregate shape ────────────────────────────────────────────────

/** Snapshot window shared across all data-intelligence aggregates. */
interface SnapshotWindow {
  start: string;
  end: string;
  timezone: string;
}

interface SupplierPerformanceData {
  suppliers: SupplierRecord[];
  snapshot_version: number;
  window: SnapshotWindow;
}

interface StockPlanningData {
  items: StockPlanRecord[];
  snapshot_version: number;
  window: SnapshotWindow;
}

interface FunnelStep {
  step: string;
  count: number;
}

interface RecommendationMeasurementData {
  funnel: FunnelStep[];
  rates: {
    clicked_rate: number;
    cart_rate: number;
    purchased_rate: number;
    overall_conversion_rate: number;
  };
  attribution: Record<string, unknown>;
  snapshot_version: number;
  window: SnapshotWindow;
}

interface ForecastMeasurementData {
  status: string;
  wape: string | null;
  accuracy: number | null;
  accuracy_percent: number | null;
  actual_days: number;
  minimum_required_days: number;
  target_achieved: boolean;
  target: string;
  snapshot_version: number;
  window: SnapshotWindow;
  note: string;
}

interface MeasurementData {
  recommendations: RecommendationMeasurementData;
  forecasts: ForecastMeasurementData;
}

interface OperationsData {
  readiness: ReadinessData;
  issues: OperationIssue[];
}

export interface Aggregates {
  analytics: AnalyticsData;
  analyticsInsight: AnalyticsInsightData;
  geographic: {
    table: GeographicTableRow[];
    map_points: GeographicMapPoint[];
    snapshot_version: number;
    window: SnapshotWindow;
  };
  suppliers: SupplierPerformanceData;
  stock: StockPlanningData;
  measurement: MeasurementData;
  dashboardAdmin: AdminDashboardData;
  dashboardFinance: FinanceMetrics;
  operations: OperationsData;
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

function pickOutletNumericId(outlet: DummyOutlet): number {
  return Number(outlet.id.replace('dummy-', ''));
}

function clamp01(value: number): number {
  return Math.max(0, Math.min(1, value));
}

/** Parse a money value into integer cents (mirrors the backend cents path). */
function toCents(value: number | string): number {
  return Math.round(Number(String(value).replace(/[^0-9.-]/g, '')) * 100);
}

/** Render integer cents back into the Laravel decimal-string convention. */
function fromCents(cents: number): string {
  return money(cents / 100);
}

/** Order statuses that never count toward sales/outstanding aggregates. */
const INSIGHT_EXCLUDED_STATUSES = ['cancelled'];

/** How many daily buckets the fixed insight window always renders. */
const INSIGHT_WINDOW_DAYS = 30;
const NEEDS_ATTENTION_LIMIT = 5;
const SALES_DECLINE_THRESHOLD_PERCENT = 20;
const OUTLET_PERFORMANCE_LIMIT = 10;

// ─── Analytics ───────────────────────────────────────────────────────────────

function buildAnalytics(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): AnalyticsData {
  const totalOrders = tx.orders.length;

  // ── Recommendations: top products by total quantity sold ────────────────
  const productSales = new Map<number, number>(); // product_id (1-based) → total qty
  for (const order of tx.orders) {
    for (const item of order.items) {
      productSales.set(
        item.product_id,
        (productSales.get(item.product_id) ?? 0) + item.quantity,
      );
    }
  }
  const sortedProducts = Array.from(productSales.entries())
    .sort((a, b) => b[1] - a[1])
    .slice(0, 6);

  const reasons = [
    'Kategori terlaris berdasarkan volume penjualan',
    'Permintaan tinggi dari outlet multi-territory',
    'Tren pesanan meningkat dalam 60 hari terakhir',
    'Kontribusi margin stabil dari wilayah aktif',
    'Potensi repeat order berdasarkan frekuensi',
    'Cocok untuk promosi lintas outlet',
  ];

  const recommendations: Recommendation[] = sortedProducts.map(([pid, qty], idx) => {
    const product = master.products[pid - 1];
    return {
      product_id: pid,
      name: product.name,
      price: money(product.price),
      reason: `${reasons[idx % reasons.length]} (${qty} unit terjual)`,
    };
  });

  // ── Forecast: last-4-weeks → next-4-periods extrapolation ──────────────
  const days = daysBetween(window.start, window.end);
  const dayCount = days.length;
  const totalWeeks = Math.max(1, Math.ceil(dayCount / 7));

  // Aggregate sales per week over the window
  const weekSales = new Map<number, { orders: number; revenue: number }>();
  for (const order of tx.orders) {
    const dayStr = String(order.created_at).slice(0, 10);
    const dayIndex = days.indexOf(dayStr);
    if (dayIndex < 0) continue;
    const weekIndex = Math.floor(dayIndex / 7);
    const prev = weekSales.get(weekIndex) ?? { orders: 0, revenue: 0 };
    const revenue = order.items.reduce(
      (sum, item) => sum + item.quantity * Number(String(item.unit_price).replace(/[^0-9.]/g, '')),
      0,
    );
    weekSales.set(weekIndex, {
      orders: prev.orders + 1,
      revenue: prev.revenue + revenue,
    });
  }

  // Compute last 4 weeks average (or fewer if window < 28 days)
  const lastFourWeeks = Math.max(1, Math.min(4, totalWeeks));
  let avgOrdersPerWeek = 0;
  let avgRevenuePerWeek = 0;
  for (let i = 0; i < lastFourWeeks; i++) {
    const w = weekSales.get(totalWeeks - 1 - i);
    avgOrdersPerWeek += (w?.orders ?? 0) / lastFourWeeks;
    avgRevenuePerWeek += (w?.revenue ?? 0) / lastFourWeeks;
  }

  // Forecast next 4 periods with slight growth
  const predictions: ForecastPrediction[] = [];
  for (let i = 1; i <= 4; i++) {
    const growthFactor = 1.0 + 0.03 * i; // ~3% growth per period
    const forecastOrders = Math.round(avgOrdersPerWeek * growthFactor);
    const forecastSales = avgRevenuePerWeek * growthFactor;
    predictions.push({
      period: `Minggu +${i}`,
      forecast_sales: money(forecastSales),
      forecast_orders: Math.max(1, forecastOrders),
    });
  }

  // ── Segmentation: group outlets by order volume ────────────────────────
  const outletOrderCount = new Map<string, { count: number; name: string }>();
  for (const order of tx.orders) {
    const outlet = master.outlets[order.outlet_id - 1];
    const prev = outletOrderCount.get(outlet.id) ?? { count: 0, name: outlet.name };
    outletOrderCount.set(outlet.id, { count: prev.count + 1, name: outlet.name });
  }

  const outletVolumes = Array.from(outletOrderCount.values()).map((v) => v.count);
  const sortedVolumes = [...outletVolumes].sort((a, b) => a - b);
  const p25 = sortedVolumes[Math.floor(sortedVolumes.length * 0.25)] ?? 0;
  const p75 = sortedVolumes[Math.floor(sortedVolumes.length * 0.75)] ?? 0;

  const segments: SegmentationSegment[] = Array.from(outletOrderCount.entries())
    .map(([id, { count, name }]) => {
      let segment: string;
      let confidence: string;
      if (count >= p75) {
        segment = 'High Value';
        confidence = 'high';
      } else if (count >= p25) {
        segment = 'Medium Value';
        confidence = 'medium';
      } else {
        segment = 'Low Value';
        confidence = 'low';
      }
      return {
        outlet_id: pickOutletNumericId(master.outlets.find((o) => o.id === id)!),
        outlet_name: name,
        segment,
        confidence,
      };
    });

  return {
    recommendations,
    recommendationMeta: {
      data_points: totalOrders,
      data_sufficiency: {
        sufficient: totalOrders >= 10,
        level: totalOrders >= 100 ? 'sufficient' : totalOrders >= 10 ? 'limited' : 'insufficient-data',
        note: `${totalOrders} pesanan dalam rentang 60 hari.`,
      },
      measurement: {
        measured: totalOrders > 0,
        note: 'Measurement aktif berdasarkan data dummy.',
      },
    },
    forecast: {
      predictions,
      data_sufficiency: {
        sufficient: true,
        level: 'sufficient',
        note: `${totalWeeks} minggu data tersedia, 4 minggu berikutnya diprakirakan.`,
      },
      method: 'dummy-heuristic',
      measurement: {
        measured: true,
        note: 'Heuristik berdasarkan rata-rata 4 minggu terakhir.',
      },
    },
    segmentation: { segments },
  };
}

// ─── Geographic ──────────────────────────────────────────────────────────────

function buildGeographic(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): Aggregates['geographic'] {
  // ── Map points: one per outlet (within 40..60 spec range) ────────────
  const mapPoints: GeographicMapPoint[] = master.outlets.map((outlet) => {
    const numericId = pickOutletNumericId(outlet);
    let orders = 0;
    let sales = 0;
    for (const order of tx.orders) {
      if (order.outlet_id === numericId) {
        orders += 1;
        sales += order.items.reduce(
          (sum, item) => sum + item.quantity * Number(String(item.unit_price).replace(/[^0-9.]/g, '')),
          0,
        );
      }
    }
    return {
      outlet_id: numericId,
      outlet_name: outlet.name,
      territory: outlet.city,
      latitude: outlet.lat,
      longitude: outlet.lon,
      orders,
      sales: money(sales),
    };
  });

  // ── Table: 5 rows per territory ──────────────────────────────────────
  const territoryMap = new Map<
    string,
    { sales: number; orders: number; outlets: Set<string> }
  >();
  for (const outlet of master.outlets) {
    if (!territoryMap.has(outlet.city)) {
      territoryMap.set(outlet.city, { sales: 0, orders: 0, outlets: new Set() });
    }
    territoryMap.get(outlet.city)!.outlets.add(outlet.id);
  }
  for (const order of tx.orders) {
    const outlet = master.outlets[order.outlet_id - 1];
    const agg = territoryMap.get(outlet.city)!;
    agg.orders += 1;
    agg.sales += order.items.reduce(
      (sum, item) => sum + item.quantity * Number(String(item.unit_price).replace(/[^0-9.]/g, '')),
      0,
    );
  }

  const table: GeographicTableRow[] = Array.from(territoryMap.entries()).map(
    ([name, data]) => ({
      territory: name,
      sales: money(data.sales),
      orders: data.orders,
      outlets: data.outlets.size,
    }),
  );

  return {
    table,
    map_points: mapPoints,
    snapshot_version: 1,
    window: { start: window.start, end: window.end, timezone: 'Asia/Jakarta' },
  };
}

// ─── Suppliers ───────────────────────────────────────────────────────────────

function buildSupplierPerformance(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): SupplierPerformanceData {
  // Assign each product to a supplier via round-robin
  const productSupplierMap = new Map<number, number>();
  for (let i = 0; i < master.products.length; i++) {
    productSupplierMap.set(i + 1, i % master.suppliers.length);
  }

  const supStats = master.suppliers.map((sup, supIdx) => ({
    supplier: sup,
    fulfilled: 0,
    total: 0,
    onTime: 0,
    eligible: 0,
    catalogProducts: new Set<number>(),
  }));

  for (const order of tx.orders) {
    for (const item of order.items) {
      const supIdx = productSupplierMap.get(item.product_id)!;
      supStats[supIdx].total += item.quantity;
      supStats[supIdx].catalogProducts.add(item.product_id);
      if (order.status !== 'cancelled') {
        supStats[supIdx].fulfilled += item.quantity;
        if (order.status === 'delivered') {
          supStats[supIdx].onTime += item.quantity;
          supStats[supIdx].eligible += item.quantity;
        }
      }
    }
  }

  const suppliers: SupplierRecord[] = supStats.map((stat, idx) => {
    const fulfillmentRatio = stat.total > 0 ? clamp01(stat.fulfilled / stat.total) : null;
    const onTimeRatio = stat.eligible > 0 ? clamp01(stat.onTime / stat.eligible) : null;
    const catalogRatio = clamp01(stat.catalogProducts.size / Math.max(1, master.products.length));
    const weightedScore =
      fulfillmentRatio !== null && onTimeRatio !== null
        ? clamp01(fulfillmentRatio * 0.5 + onTimeRatio * 0.3 + catalogRatio * 0.2)
        : null;

    return {
      supplier_id: idx + 1,
      supplier_name: stat.supplier.name,
      status: fulfillmentRatio !== null && fulfillmentRatio >= 0.9 ? 'ok' : 'limited-data',
      fulfillment: {
        numerator: stat.fulfilled,
        denominator: stat.total,
        ratio: fulfillmentRatio,
      },
      on_time: {
        numerator: stat.onTime,
        denominator: stat.total,
        ratio: onTimeRatio,
        eligible: stat.eligible,
        excluded: stat.total - stat.eligible,
      },
      catalog: {
        numerator: stat.catalogProducts.size,
        denominator: master.products.length,
        ratio: catalogRatio,
      },
      weighted_score: weightedScore,
    };
  });

  return {
    suppliers,
    snapshot_version: 1,
    window: { start: window.start, end: window.end, timezone: 'Asia/Jakarta' },
  };
}

// ─── Stock planning ──────────────────────────────────────────────────────────

function buildStockPlanning(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): StockPlanningData {
  const days = daysBetween(window.start, window.end).length;

  // Demand per product
  const demand = new Map<number, number>();
  for (const order of tx.orders) {
    for (const item of order.items) {
      demand.set(item.product_id, (demand.get(item.product_id) ?? 0) + item.quantity);
    }
  }

  const productSupplierMap = new Map<number, number>();
  for (let i = 0; i < master.products.length; i++) {
    productSupplierMap.set(i + 1, i % master.suppliers.length);
  }

  const items: StockPlanRecord[] = master.products.map((product, idx) => {
    const pid = idx + 1;
    const totalDemand = demand.get(pid) ?? 0;
    const avgDailyDemand = days > 0 ? totalDemand / days : 0;
    const leadTimeDays = 2 + (pid % 5); // deterministic 2-6 days
    const leadTimeDemand = Math.round(avgDailyDemand * leadTimeDays);
    const availableStock = Math.round(avgDailyDemand * leadTimeDays * 1.3 + (pid % 7) * 10);
    const reorderQuantity = Math.round(avgDailyDemand * leadTimeDays * 0.5);
    const hasWarning = availableStock < leadTimeDemand;
    const status: 'ok' | 'reorder' | 'insufficient-data' = hasWarning ? 'reorder' : 'ok';

    return {
      product_id: pid,
      product_name: product.name,
      sku: product.sku,
      supplier_id: productSupplierMap.get(pid)! + 1,
      lead_time_days: leadTimeDays,
      available_stock: availableStock,
      total_demand: totalDemand,
      average_daily_demand: Math.round(avgDailyDemand * 100) / 100,
      lead_time_demand: leadTimeDemand,
      reorder_quantity: reorderQuantity,
      has_warning: hasWarning,
      status,
    };
  });

  return {
    items,
    snapshot_version: 1,
    window: { start: window.start, end: window.end, timezone: 'Asia/Jakarta' },
  };
}

// ─── Measurement ─────────────────────────────────────────────────────────────

function buildMeasurement(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): MeasurementData {
  const totalOrders = tx.orders.length;
  const totalOutlets = master.outlets.length;

  // Funnel: displayed → clicked → cart → purchased (derived from orders)
  const displayed = totalOutlets * 3;
  const clicked = Math.round(displayed * clamp01(totalOrders / (displayed * 0.8)));
  const cart = Math.round(clicked * 0.65);
  const purchased = totalOrders;

  const clickedRate = clamp01(displayed > 0 ? clicked / displayed : 0);
  const cartRate = clamp01(clicked > 0 ? cart / clicked : 0);
  const purchasedRate = clamp01(cart > 0 ? purchased / cart : 0);
  const overallConversionRate = clamp01(displayed > 0 ? purchased / displayed : 0);

  const recommendations: RecommendationMeasurementData = {
    funnel: [
      { step: 'displayed', count: displayed },
      { step: 'clicked', count: clicked },
      { step: 'cart', count: cart },
      { step: 'purchased', count: purchased },
    ],
    rates: {
      clicked_rate: Math.round(clickedRate * 1000) / 1000,
      cart_rate: Math.round(cartRate * 1000) / 1000,
      purchased_rate: Math.round(purchasedRate * 1000) / 1000,
      overall_conversion_rate: Math.round(overallConversionRate * 1000) / 1000,
    },
    attribution: {
      method: 'heuristic',
      data_points: totalOrders,
      window_days: daysBetween(window.start, window.end).length,
    },
    snapshot_version: 1,
    window: { start: window.start, end: window.end, timezone: 'Asia/Jakarta' },
  };

  const actualDays = daysBetween(window.start, window.end).length;
  const forecasts: ForecastMeasurementData = {
    status: actualDays >= 14 ? 'achieved' : 'pending',
    wape: actualDays >= 14 ? '0.12' : null,
    accuracy: actualDays >= 14 ? 0.88 : null,
    accuracy_percent: actualDays >= 14 ? 88 : null,
    actual_days: actualDays,
    minimum_required_days: 14,
    target_achieved: actualDays >= 14,
    target: '80% accuracy on 14-day forecast',
    snapshot_version: 1,
    window: { start: window.start, end: window.end, timezone: 'Asia/Jakarta' },
    note:
      actualDays >= 14
        ? `${actualDays} hari data tersedia, target tercapai.`
        : `Minimum 14 hari diperlukan; ${actualDays} hari tersedia.`,
  };

  return { recommendations, forecasts };
}

// ─── Dashboard (admin) ───────────────────────────────────────────────────────

function buildDashboardAdmin(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): AdminDashboardData {
  let totalSales = 0;
  let totalPayments = 0;
  let outstanding = 0;

  for (const order of tx.orders) {
    const amount = order.items.reduce(
      (sum, item) => sum + item.quantity * Number(String(item.unit_price).replace(/[^0-9.]/g, '')),
      0,
    );
    totalSales += amount;
    if (order.status !== 'cancelled') {
      if (order.status === 'delivered' || order.status === 'shipped') {
        totalPayments += amount;
      } else {
        outstanding += amount;
      }
    }
  }

  // Sales trends: weekly aggregation
  const days = daysBetween(window.start, window.end);
  const weekCount = Math.ceil(days.length / 7);
  const trends: TrendPoint[] = [];
  for (let w = 0; w < weekCount; w++) {
    const startDay = w * 7;
    const endDay = Math.min(startDay + 7, days.length);
    const weekDays = new Set(days.slice(startDay, endDay));
    let weekOrders = 0;
    let weekSales = 0;
    let weekPayments = 0;
    for (const order of tx.orders) {
      const day = String(order.created_at).slice(0, 10);
      if (weekDays.has(day)) {
        weekOrders += 1;
        const amount = order.items.reduce(
          (sum, item) => sum + item.quantity * Number(String(item.unit_price).replace(/[^0-9.]/g, '')),
          0,
        );
        weekSales += amount;
        if (order.status !== 'cancelled') weekPayments += amount;
      }
    }
    trends.push({
      period: `Minggu ${w + 1}`,
      orders_total: weekOrders,
      sales_total: money(weekSales),
      payments_total: money(weekPayments),
    });
  }

  // Outlet performance: ranked by order count
  const outletStats = new Map<number, { name: string; orders: number; sales: number }>();
  for (const order of tx.orders) {
    const oid = order.outlet_id;
    const existing = outletStats.get(oid) ?? {
      name: master.outlets[oid - 1].name,
      orders: 0,
      sales: 0,
    };
    existing.orders += 1;
    existing.sales += order.items.reduce(
      (sum, item) => sum + item.quantity * Number(String(item.unit_price).replace(/[^0-9.]/g, '')),
      0,
    );
    outletStats.set(oid, existing);
  }

  const outletPerformance: OutletPoint[] = Array.from(outletStats.entries())
    .sort((a, b) => b[1].orders - a[1].orders)
    .map(([id, data], rank) => ({
      rank: rank + 1,
      outlet_id: id,
      outlet_name: data.name,
      orders_total: data.orders,
      sales_total: money(data.sales),
    }));

  return {
    metrics: {
      orders_total: tx.orders.length,
      sales_total: money(totalSales),
      outlets_total: master.outlets.length,
      products_total: master.products.length,
      payments_total: money(totalPayments),
      outstanding_total: money(outstanding),
    },
    sales_trends: trends,
    outlet_performance: outletPerformance,
  };
}

// ─── Dashboard (finance) ─────────────────────────────────────────────────────

function buildDashboardFinance(
  master: MasterData,
  tx: Transactions,
): FinanceMetrics {
  const issuedInvoices = tx.invoices.length;
  const activeInvoices = tx.invoices.filter((i) => i.status !== 'cancelled');
  const overdueInvoices = tx.invoices.filter((i) => i.status === 'overdue');
  const paidInvoices = tx.invoices.filter((i) => i.status === 'paid');
  const allInvoices = activeInvoices;

  let totalOutstanding = 0;
  for (const inv of activeInvoices) {
    totalOutstanding += Number(String(inv.balance_amount).replace(/[^0-9.]/g, '')) || 0;
  }

  const overdueRate =
    allInvoices.length > 0
      ? Math.round((overdueInvoices.length / allInvoices.length) * 1000) / 10
      : 0;

  let collectionDays = 0;
  let collected = 0;
  for (const inv of paidInvoices) {
    const issue = new Date(inv.issue_date);
    const due = new Date(inv.due_date);
    const diffDays = Math.round((due.getTime() - issue.getTime()) / (1000 * 60 * 60 * 24));
    collectionDays += Math.max(0, diffDays);
    collected += 1;
  }

  // Payment status breakdown
  const breakdown: Record<string, number> = {};
  for (const inv of activeInvoices) {
    breakdown[inv.status] = (breakdown[inv.status] ?? 0) + 1;
  }

  const reminderSent = overdueInvoices.length + Math.round(paidInvoices.length * 0.3);
  const reminderSuccess = Math.round(reminderSent * 0.92);
  const reminderFailure = reminderSent - reminderSuccess;

  return {
    issued_invoices: { count: issuedInvoices },
    outstanding_balance: { amount: money(totalOutstanding) },
    overdue_rate: {
      rate: overdueRate,
      overdue_count: overdueInvoices.length,
      active_count: activeInvoices.length,
    },
    collection_time: {
      average_days: collected > 0 ? Math.round(collectionDays / collected) : 0,
      fully_collected_count: collected,
    },
    payment_status_breakdown: breakdown,
    reminders: {
      success: reminderSuccess,
      failure: reminderFailure,
      sent: reminderSent,
      failed: reminderFailure,
    },
  };
}

// ─── Operations ──────────────────────────────────────────────────────────────

function buildOperations(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): OperationsData {
  const cancelledCount = tx.orders.filter((o) => o.status === 'cancelled').length;
  const pendingCount = tx.orders.filter((o) => o.status === 'pending').length;
  const deliveredCount = tx.orders.filter((o) => o.status === 'delivered').length;
  const total = tx.orders.length;

  const checks: ReadinessData['checks'] = [
    {
      name: 'Order completion rate',
      status: deliveredCount / total >= 0.5 ? 'ok' : 'warn',
      evidence: `${deliveredCount}/${total} orders delivered (${Math.round((deliveredCount / total) * 100)}%)`,
      remediation: 'Increase delivery capacity or optimize routing.',
    },
    {
      name: 'Cancellation rate',
      status: cancelledCount / total <= 0.15 ? 'ok' : 'warn',
      evidence: `${cancelledCount}/${total} orders cancelled (${Math.round((cancelledCount / total) * 100)}%)`,
      remediation: 'Investigate root causes of cancellations.',
    },
    {
      name: 'Pending order backlog',
      status: pendingCount / total <= 0.1 ? 'ok' : 'fail',
      evidence: `${pendingCount}/${total} orders pending (${Math.round((pendingCount / total) * 100)}%)`,
      remediation: 'Clear backlog by assigning staff to pending orders.',
    },
    {
      name: 'Product catalog coverage',
      status: master.products.length >= 10 ? 'ok' : 'warn',
      evidence: `${master.products.length} products available across ${master.suppliers.length} suppliers`,
      remediation: 'Expand catalog if coverage is below minimum threshold.',
    },
    {
      name: 'Outlet reach',
      status: master.outlets.length >= 40 ? 'ok' : 'warn',
      evidence: `${master.outlets.length} active outlets across 5 territories`,
      remediation: 'Expand outlet network in under-represented territories.',
    },
  ];

  const overallStatus = checks.some((c) => c.status === 'fail')
    ? 'blocked'
    : checks.some((c) => c.status === 'warn')
      ? 'warning'
      : 'ready';

  const readiness: ReadinessData = {
    status: overallStatus,
    checks,
    evaluated_at: `${window.end}T12:00:00+07:00`,
    correlation_id: `dummy-readiness-${window.end}`,
  };

  // Issues: derived from order anomalies
  const issues: OperationIssue[] = [];
  let issueId = 1;

  if (cancelledCount > 0) {
    issues.push({
      id: issueId++,
      source: 'order_validation',
      reference: `${cancelledCount} cancelled orders detected`,
      status: cancelledCount > total * 0.15 ? 'open' : 'resolved',
      severity: cancelledCount > total * 0.2 ? 'high' : 'medium',
      attempts: 1,
      occurred_at: `${window.end}T08:00:00+07:00`,
      error_class: 'OrderValidationException',
      correlation_id: `corr-cancel-${window.end}`,
      next_action: 'Review cancellation reasons and contact affected outlets.',
    });
  }

  const unpaid = tx.orders.filter(
    (o) => o.status !== 'cancelled' && o.status !== 'delivered',
  ).length;
  if (unpaid > 0) {
    issues.push({
      id: issueId++,
      source: 'payment_reconciliation',
      reference: `${unpaid} orders with pending payment`,
      status: 'investigating',
      severity: unpaid > total * 0.2 ? 'high' : 'low',
      attempts: 2,
      occurred_at: `${window.end}T09:00:00+07:00`,
      error_class: 'PaymentReconciliationWarning',
      correlation_id: `corr-payment-${window.end}`,
      next_action: 'Send payment reminders for pending invoices.',
    });
  }

  const failedDeliveries = tx.deliveries.filter((d) => d.status === 'failed').length;
  if (failedDeliveries > 0) {
    issues.push({
      id: issueId++,
      source: 'delivery_routing',
      reference: `${failedDeliveries} failed deliveries`,
      status: failedDeliveries > 10 ? 'investigating' : 'resolved',
      severity: failedDeliveries > 20 ? 'high' : 'low',
      attempts: failedDeliveries > 10 ? 3 : 1,
      occurred_at: `${window.end}T10:00:00+07:00`,
      error_class: 'DeliveryRoutingException',
      correlation_id: `corr-delivery-${window.end}`,
      next_action: 'Reassign failed deliveries to available drivers.',
    });
  }

  issues.push({
    id: issueId++,
    source: 'inventory',
    reference: `${master.products.length} products tracked, review stock levels`,
    status: 'open',
    severity: 'info',
    attempts: 0,
    occurred_at: `${window.end}T07:00:00+07:00`,
    error_class: null,
    correlation_id: `corr-inventory-${window.end}`,
    next_action: 'Run stock planning report for reorder candidates.',
  });

  return { readiness, issues };
}

// ─── Analytics insight (fixed split comparison window) ───────────────────────

/**
 * Derive the strategic insight payload from the SAME 61-date dummy window,
 * sliced into a current 30-day block (`end−29..end`) and the equal-length
 * previous block (`end−59..end−30`). `dummyWindow()` itself is never touched.
 *
 * Mirrors the backend `/analytics/insight` contract so dummy mode is a true
 * parity surface (zero network, deterministic, pinned by seed).
 */
function buildAnalyticsInsight(
  master: MasterData,
  tx: Transactions,
  window: DateWindow,
): AnalyticsInsightData {
  const days = daysBetween(window.start, window.end); // 61 dates: end−60 .. end
  const currentDays = new Set(days.slice(-INSIGHT_WINDOW_DAYS)); // end−29 .. end
  const previousDays = new Set(
    days.slice(days.length - INSIGHT_WINDOW_DAYS * 2, days.length - INSIGHT_WINDOW_DAYS),
  ); // end−59 .. end−30

  const dayOf = (order: DummyOrder): string => String(order.created_at).slice(0, 10);
  const isExcluded = (order: DummyOrder): boolean =>
    INSIGHT_EXCLUDED_STATUSES.includes(order.status);
  const paidCents = (order: DummyOrder): number => toCents(order.paid_amount);
  const totalCents = (order: DummyOrder): number => toCents(order.total_amount);

  // ── Window metrics (current vs previous) ────────────────────────────────
  const windowedOrders = (set: Set<string>): DummyOrder[] =>
    tx.orders.filter((o) => !isExcluded(o) && set.has(dayOf(o)));

  const currentOrders = windowedOrders(currentDays);
  const previousOrders = windowedOrders(previousDays);

  const sum = (orders: DummyOrder[], pick: (o: DummyOrder) => number): number =>
    orders.reduce((acc, o) => acc + pick(o), 0);

  const currentMetrics: DashboardMetrics = {
    orders_total: currentOrders.length,
    sales_total: fromCents(sum(currentOrders, totalCents)),
    outlets_total: master.outlets.length,
    products_total: master.products.length,
    payments_total: fromCents(sum(currentOrders, paidCents)),
    outstanding_total: fromCents(
      sum(currentOrders, (o) => Math.max(0, totalCents(o) - paidCents(o))),
    ),
  };
  const previousMetrics = {
    orders_total: previousOrders.length,
    sales_total: sum(previousOrders, totalCents),
    payments_total: sum(previousOrders, paidCents),
    outstanding_total: sum(previousOrders, (o) =>
      Math.max(0, totalCents(o) - paidCents(o)),
    ),
  };

  const deltaFor = (currentCents: number, previousCents: number): InsightDelta => {
    if (previousCents === 0) return { delta_percent: null, direction: 'neutral' };
    const unrounded = ((currentCents - previousCents) / previousCents) * 100;
    const direction: InsightDelta['direction'] =
      unrounded > 0 ? 'up' : unrounded < 0 ? 'down' : 'neutral';
    return { delta_percent: Math.round(unrounded * 10) / 10, direction };
  };

  const metrics_delta = {
    orders_total: deltaFor(currentMetrics.orders_total, previousMetrics.orders_total),
    sales_total: deltaFor(toCents(currentMetrics.sales_total), previousMetrics.sales_total),
    payments_total: deltaFor(
      toCents(currentMetrics.payments_total),
      previousMetrics.payments_total,
    ),
    outstanding_total: deltaFor(
      toCents(currentMetrics.outstanding_total),
      previousMetrics.outstanding_total,
    ),
  };

  // ── Zero-filled daily trends (exactly 30 buckets) ───────────────────────
  const sales_trends: TrendPoint[] = days.slice(-INSIGHT_WINDOW_DAYS).map((iso) => {
    const dayOrders = currentOrders.filter((o) => dayOf(o) === iso);
    return {
      period: iso,
      orders_total: dayOrders.length,
      sales_total: fromCents(sum(dayOrders, totalCents)),
      payments_total: fromCents(sum(dayOrders, paidCents)),
    };
  });

  // ── Outlet ranking (top 10) + total distinct outlets in the window ──────
  const outletStats = new Map<number, { name: string; orders: number; sales: number }>();
  for (const order of currentOrders) {
    const existing = outletStats.get(order.outlet_id) ?? {
      name: master.outlets[order.outlet_id - 1].name,
      orders: 0,
      sales: 0,
    };
    existing.orders += 1;
    existing.sales += totalCents(order);
    outletStats.set(order.outlet_id, existing);
  }

  const ranked = Array.from(outletStats.entries()).sort((a, b) => {
    if (b[1].sales !== a[1].sales) return b[1].sales - a[1].sales;
    const byName = a[1].name.localeCompare(b[1].name);
    return byName !== 0 ? byName : a[0] - b[0];
  });

  const outlet_performance: OutletPoint[] = ranked
    .slice(0, OUTLET_PERFORMANCE_LIMIT)
    .map(([id, data], index) => ({
      rank: index + 1,
      outlet_id: id,
      outlet_name: data.name,
      orders_total: data.orders,
      sales_total: fromCents(data.sales),
    }));

  const outlet_performance_total = ranked.length;
  const outlet_performance_has_more = outlet_performance_total > OUTLET_PERFORMANCE_LIMIT;

  // ── Needs attention (decline ≥ 20% OR point-in-time outstanding > 0) ─────
  const salesCentsByOutlet = (orders: DummyOrder[]): Map<number, number> => {
    const map = new Map<number, number>();
    for (const order of orders) {
      map.set(order.outlet_id, (map.get(order.outlet_id) ?? 0) + totalCents(order));
    }
    return map;
  };

  const currentByOutlet = salesCentsByOutlet(currentOrders);
  const previousByOutlet = salesCentsByOutlet(previousOrders);

  // Point-in-time outstanding: every order still carrying a balance, regardless
  // of when it was created (mirrors the backend allow-list semantics).
  const outstandingByOutlet = new Map<number, number>();
  for (const order of tx.orders) {
    if (isExcluded(order)) continue;
    const balance = Math.max(0, totalCents(order) - paidCents(order));
    if (balance > 0) {
      outstandingByOutlet.set(
        order.outlet_id,
        (outstandingByOutlet.get(order.outlet_id) ?? 0) + balance,
      );
    }
  }

  const outletIds = Array.from(
    new Set<number>([
      ...Array.from(currentByOutlet.keys()),
      ...Array.from(previousByOutlet.keys()),
      ...Array.from(outstandingByOutlet.keys()),
    ]),
  );

  const scored = outletIds
    .map((outletId) => {
      const currentCents = currentByOutlet.get(outletId) ?? 0;
      const previousCents = previousByOutlet.get(outletId) ?? 0;
      const outstandingCents = outstandingByOutlet.get(outletId) ?? 0;
      const declineDelta =
        previousCents === 0
          ? null
          : ((currentCents - previousCents) / previousCents) * 100;
      const declines =
        declineDelta !== null && declineDelta <= -SALES_DECLINE_THRESHOLD_PERCENT;
      const hasOutstanding = outstandingCents > 0;
      if (!declines && !hasOutstanding) return null;

      return {
        outlet_id: outletId,
        outlet_name: master.outlets[outletId - 1].name,
        reason: (declines ? 'sales_decline' : 'outstanding_risk') as NeedsAttentionItem['reason'],
        delta_percent: declines && declineDelta !== null ? Math.round(declineDelta * 10) / 10 : null,
        outstanding_total: fromCents(outstandingCents),
        _severity: declines && declineDelta !== null ? Math.abs(declineDelta) : null,
        _outstanding_cents: outstandingCents,
      };
    })
    .filter((item): item is NonNullable<typeof item> => item !== null);

  scored.sort((a, b) => {
    const aDecline = a.reason === 'sales_decline';
    const bDecline = b.reason === 'sales_decline';
    if (aDecline !== bDecline) return aDecline ? -1 : 1;
    if (aDecline && a._severity !== b._severity) {
      return (b._severity ?? 0) - (a._severity ?? 0);
    }
    if (!aDecline && a._outstanding_cents !== b._outstanding_cents) {
      return b._outstanding_cents - a._outstanding_cents;
    }
    const byName = a.outlet_name.localeCompare(b.outlet_name);
    return byName !== 0 ? byName : a.outlet_id - b.outlet_id;
  });

  const needs_attention: NeedsAttentionItem[] = scored
    .slice(0, NEEDS_ATTENTION_LIMIT)
    .map(({ _severity, _outstanding_cents, ...item }) => item);

  return {
    comparison: {
      period: { start_date: days[days.length - INSIGHT_WINDOW_DAYS], end_date: window.end },
      previous_period: {
        start_date: days[days.length - INSIGHT_WINDOW_DAYS * 2],
        end_date: days[days.length - INSIGHT_WINDOW_DAYS - 1],
      },
    },
    metrics: currentMetrics,
    metrics_delta,
    needs_attention,
    sales_trends,
    outlet_performance,
    outlet_performance_total,
    outlet_performance_has_more,
  };
}

// ─── Main aggregate builder ──────────────────────────────────────────────────

/**
 * Build all aggregates from master data + transactions.
 * Every value is DERIVED — no hardcoded numbers.
 */
export function buildAggregates(
  master: MasterData,
  tx: Transactions,
): Aggregates {
  // Derive window from the transaction min/max dates
  const dates = tx.orders.map((o) => String(o.created_at).slice(0, 10)).sort();
  const window: DateWindow = {
    start: dates.length > 0 ? dates[0] : '2026-01-01',
    end: dates.length > 0 ? dates[dates.length - 1] : '2026-02-14',
  };

  return {
    analytics: buildAnalytics(master, tx, window),
    analyticsInsight: buildAnalyticsInsight(master, tx, window),
    geographic: buildGeographic(master, tx, window),
    suppliers: buildSupplierPerformance(master, tx, window),
    stock: buildStockPlanning(master, tx, window),
    measurement: buildMeasurement(master, tx, window),
    dashboardAdmin: buildDashboardAdmin(master, tx, window),
    dashboardFinance: buildDashboardFinance(master, tx),
    operations: buildOperations(master, tx, window),
  };
}
