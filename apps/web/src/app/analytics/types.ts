/**
 * Frontend contract for the Analitik insight payload (`GET /api/analytics/insight`).
 *
 * Mirrors the backend response field-for-field so the loader, the page and the
 * dummy fixture all agree on one shape. Money fields stay as the API's fixed
 * 2-decimal strings; the presentation helpers do the id-ID formatting.
 */
import type { TrendPoint, OutletPoint } from '@/components/Charts';

export type DeltaDirection = 'up' | 'down' | 'neutral';

export interface InsightDelta {
  /** Percentage change rounded to 1 decimal, or null when there is no baseline. */
  delta_percent: number | null;
  /** Derived server-side from the UNROUNDED change. */
  direction: DeltaDirection;
}

export interface NeedsAttentionItem {
  outlet_id: number;
  outlet_name: string;
  reason: 'sales_decline' | 'outstanding_risk';
  /** Present only for `sales_decline` rows. */
  delta_percent: number | null;
  /** Point-in-time outstanding ("total tunggakan"). */
  outstanding_total: string;
}

export interface InsightMetrics {
  orders_total: number;
  sales_total: string;
  outlets_total: number;
  products_total: number;
  payments_total: string;
  outstanding_total: string;
}

export interface InsightComparison {
  period: { start_date: string; end_date: string };
  previous_period: { start_date: string; end_date: string };
}

export interface AnalyticsInsight {
  comparison: InsightComparison;
  metrics: InsightMetrics;
  /** Only the four deltable metrics — point-in-time counts carry no delta. */
  metrics_delta: {
    orders_total: InsightDelta;
    sales_total: InsightDelta;
    payments_total: InsightDelta;
    outstanding_total: InsightDelta;
  };
  needs_attention: NeedsAttentionItem[];
  /** Zero-filled to exactly 30 daily buckets. */
  sales_trends: TrendPoint[];
  outlet_performance: OutletPoint[];
  outlet_performance_total: number;
  outlet_performance_has_more: boolean;
}
