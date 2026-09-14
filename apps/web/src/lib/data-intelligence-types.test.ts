import type { LatLngExpression } from 'leaflet';
import type { MapContainerProps } from 'react-leaflet';
import type {
  DataPipelineRun,
  DataSnapshot,
  FunnelMetrics,
  MapPoint,
  SnapshotWindow,
  StockPlan,
  SupplierScore,
  TerritorySummary,
  WapeMeasurement,
} from './data-intelligence-types';

const window: SnapshotWindow = {
  start: '2026-09-01',
  end: '2026-09-14',
  timezone: 'Asia/Jakarta',
};

const run: DataPipelineRun = {
  id: 1,
  run_uuid: 'run-uuid',
  status: 'completed',
  pipeline_version: 'v1',
  window,
};

const snapshot: DataSnapshot = {
  id: 1,
  snapshot_uuid: 'snapshot-uuid',
  version: 1,
  status: 'published',
  window,
};

const territory: TerritorySummary = {
  territory_id: 1,
  territory_name: 'Jakarta Selatan',
  outlet_count: 1,
  order_count: 2,
  sales_total: '100.00',
};

const point: MapPoint = {
  outlet_id: 1,
  outlet_name: 'Outlet A',
  latitude: -6.2,
  longitude: 106.8,
  territory_name: 'Jakarta Selatan',
  sales_total: '100.00',
};

const supplier: SupplierScore = {
  supplier_id: 1,
  supplier_name: 'Supplier A',
  status: 'ok',
  fulfillment_ratio: 0.9,
  on_time_ratio: 0.8,
  catalog_quality_ratio: 1,
  weighted_score: 0.86,
  coverage: { fulfillment: 10, on_time: 8, catalog: 3 },
};

const stock: StockPlan = {
  product_id: 1,
  sku: 'SKU-1',
  available_stock: 2,
  average_daily_demand: 1,
  lead_time_days: 5,
  suggested_reorder_quantity: 3,
  status: 'reorder',
};

const funnel: FunnelMetrics = {
  displayed: 10,
  clicked: 5,
  cart: 2,
  purchased: 1,
  click_rate: 0.5,
  cart_rate: 0.4,
  purchase_rate: 0.5,
};

const wape: WapeMeasurement = {
  wape: '0.2000',
  accuracy: '80.00',
  status: 'achieved',
  actual_days: 30,
  target_achieved: true,
};

const mapCenter: LatLngExpression = [-6.2, 106.8];
const mapProps: Pick<MapContainerProps, 'center'> = { center: mapCenter };

void [run, snapshot, territory, point, supplier, stock, funnel, wape, mapProps];
