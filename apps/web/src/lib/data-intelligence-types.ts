export type PipelineRunStatus = 'pending' | 'running' | 'processing' | 'completed' | 'failed';
export type SnapshotStatus = 'staged' | 'published' | 'failed';
export type DataQualityStatus = 'ok' | 'low-confidence' | 'limited-data' | 'insufficient-data' | 'pending' | 'achieved';

export interface SnapshotWindow {
  start: string;
  end: string;
  timezone: string;
}

export interface DataPipelineRun {
  id: number;
  run_uuid: string;
  status: PipelineRunStatus;
  pipeline_version: string;
  window: SnapshotWindow;
  lineage?: Record<string, string | number | boolean | null>;
  error?: string | null;
}

export interface DataSnapshot {
  id: number;
  snapshot_uuid: string;
  version: number;
  status: SnapshotStatus;
  window: SnapshotWindow;
}

export interface TerritorySummary {
  territory_id: number | null;
  territory_name: string;
  outlet_count: number;
  order_count: number;
  sales_total: string;
}

export interface MapPoint {
  outlet_id: number;
  outlet_name: string;
  latitude: number;
  longitude: number;
  territory_name: string;
  sales_total: string;
}

export interface SupplierCoverage {
  fulfillment: number;
  on_time: number;
  catalog: number;
}

export interface SupplierScore {
  supplier_id: number;
  supplier_name: string;
  status: Exclude<DataQualityStatus, 'achieved'> | 'ok';
  fulfillment_ratio: number | null;
  on_time_ratio: number | null;
  catalog_quality_ratio: number | null;
  weighted_score: number | null;
  coverage: SupplierCoverage;
}

export interface StockPlan {
  product_id: number;
  sku: string;
  available_stock: number;
  average_daily_demand: number;
  lead_time_days: number | null;
  suggested_reorder_quantity: number | null;
  status: 'ok' | 'reorder' | 'insufficient-data';
}

export interface FunnelMetrics {
  displayed: number;
  clicked: number;
  cart: number;
  purchased: number;
  click_rate: number;
  cart_rate: number;
  purchase_rate: number;
}

export interface WapeMeasurement {
  wape: string | null;
  accuracy: string | null;
  status: 'achieved' | 'pending' | 'insufficient-data';
  actual_days: number;
  target_achieved: boolean;
}

export type RecommendationEventType = 'displayed' | 'clicked' | 'cart';

export interface RecommendationEventPayload {
  event_uuid: string;
  event_type: RecommendationEventType;
  outlet_id: number;
  product_id: number | null;
  occurred_at: string;
  metadata?: Record<string, string | number | boolean | null>;
}

export interface RecommendationEventResponse {
  status: 'recorded' | 'replayed' | 'conflict';
  event_uuid: string;
}

export interface DataIntelligenceMetadata {
  snapshot_version: number;
  window: SnapshotWindow;
}
