import type {
  AbExperiment,
  ExperimentListData,
  RevenueLiftSnapshot,
} from '@/lib/experiments-api';
import type {
  RecommendationAction,
} from '@/lib/ai-actions-api';
import type {
  ReplenishmentPlan,
} from '@/lib/replenishment-api';
import type { DateWindow } from './dates';
import type { MasterData } from './factory';

export interface Phase9DummyEntities {
  recommendation_actions: RecommendationAction[];
  replenishment_plans: ReplenishmentPlan[];
  experiments: ExperimentListData;
  experimentLifts: Record<number, RevenueLiftSnapshot>;
}

function phaseDate(window: DateWindow, offset: number): string {
  const date = new Date(`${window.end}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + offset);
  return date.toISOString();
}

function buildActions(master: MasterData, window: DateWindow): RecommendationAction[] {
  const outletId = Number(master.outlets[0]?.id.replace('dummy-', '') ?? 1);
  return [
    {
      id: 9001,
      source_event_id: 7001,
      outlet_id: outletId,
      created_by: 1,
      approved_by: null,
      executed_by: null,
      type: 'draft_order',
      status: 'pending_approval',
      payload: {
        outlet_name: master.outlets[0]?.name ?? 'Toko Dummy',
        product_id: 1,
        product_name: master.products[0]?.name ?? 'Produk Dummy',
        quantity: 12,
      },
      idempotency_key: 'dummy-action-9001',
      idempotency_payload_hash: 'dummy-hash-action-9001',
      approved_at: null,
      executed_at: null,
      rejection_reason: null,
      execution_result: null,
      method: 'deterministic-heuristic',
      method_version: 'dummy-v1',
      fallback: true,
      data_sufficiency: 'sufficient',
      metadata: { source: 'dummy', window_end: window.end },
      idempotent_replay: false,
      created_at: phaseDate(window, -2),
      updated_at: phaseDate(window, -1),
    },
    {
      id: 9002,
      source_event_id: 7002,
      outlet_id: null,
      created_by: 1,
      approved_by: 1,
      executed_by: null,
      type: 'draft_campaign',
      status: 'approved',
      payload: { name: 'Promo Dummy Ramadan', discount_percent: 10 },
      idempotency_key: 'dummy-action-9002',
      idempotency_payload_hash: 'dummy-hash-action-9002',
      approved_at: phaseDate(window, -1),
      executed_at: null,
      rejection_reason: null,
      execution_result: null,
      method: 'deterministic-heuristic',
      method_version: 'dummy-v1',
      fallback: false,
      data_sufficiency: 'sufficient',
      metadata: { source: 'dummy' },
      idempotent_replay: false,
      created_at: phaseDate(window, -4),
      updated_at: phaseDate(window, -1),
    },
  ];
}

function buildPlans(master: MasterData, window: DateWindow): ReplenishmentPlan[] {
  const supplier = master.suppliers[0];
  const product = master.products[0];
  const item = {
    product_id: 1,
    product: product ? { id: 1, name: product.name, price: product.price } : null,
    reorder_quantity: 24,
    data_sufficiency: 'sufficient',
    metadata: { source: 'dummy', projected_days: 7 },
  };
  return [
    {
      id: 9101,
      supplier_id: 1,
      supplier: supplier ? { id: 1, name: supplier.name } : null,
      created_by: 1,
      approved_by: null,
      executed_by: null,
      status: 'draft',
      window_start: window.start,
      window_end: window.end,
      approved_at: null,
      executed_at: null,
      execution_result: null,
      metadata: { source: 'dummy', method: 'stock-planning-v1' },
      items: [item],
      idempotent_replay: false,
      created_at: phaseDate(window, -3),
      updated_at: phaseDate(window, -2),
    },
    {
      id: 9102,
      supplier_id: 1,
      supplier: supplier ? { id: 1, name: supplier.name } : null,
      created_by: 1,
      approved_by: 1,
      executed_by: 1,
      status: 'executed',
      window_start: window.start,
      window_end: window.end,
      approved_at: phaseDate(window, -5),
      executed_at: phaseDate(window, -4),
      execution_result: { purchase_orders: [{ reference: 'PO-DUMMY-9102', items: [item] }] },
      metadata: { source: 'dummy', method: 'stock-planning-v1' },
      items: [item],
      idempotent_replay: false,
      created_at: phaseDate(window, -7),
      updated_at: phaseDate(window, -4),
    },
  ];
}

function buildExperiments(window: DateWindow): {
  experiments: ExperimentListData;
  experimentLifts: Record<number, RevenueLiftSnapshot>;
} {
  const createdAt = phaseDate(window, -6);
  const experiments: AbExperiment[] = [
    {
      id: 9201,
      experiment_key: 'dummy-ai-reorder-v1',
      name: 'Rekomendasi reorder AI',
      status: 'running',
      minimum_sample_size: 30,
      assignment_summary: { total: 48, control: 24, treatment: 24 },
      starts_at: phaseDate(window, -14),
      ends_at: null,
      created_at: createdAt,
    },
    {
      id: 9202,
      experiment_key: 'dummy-promo-v1',
      name: 'Promosi outlet prioritas',
      status: 'completed',
      minimum_sample_size: 20,
      assignment_summary: { total: 40, control: 20, treatment: 20 },
      starts_at: phaseDate(window, -30),
      ends_at: phaseDate(window, -8),
      created_at: phaseDate(window, -31),
    },
  ];
  const experimentLifts: Record<number, RevenueLiftSnapshot> = {
    9201: {
      id: 9301,
      experiment_id: 9201,
      uplift: '0.1250',
      status: 'computed',
      method_version: 'lift-mean-v1',
      control_sample_size: 24,
      treatment_sample_size: 24,
      control_revenue: '12000000.00',
      treatment_revenue: '13500000.00',
      metadata: { sufficient: true, minimum_sample_size: 30 },
      created_at: createdAt,
    },
    9202: {
      id: 9302,
      experiment_id: 9202,
      uplift: null,
      status: 'insufficient-data',
      method_version: 'lift-mean-v1',
      control_sample_size: 12,
      treatment_sample_size: 12,
      control_revenue: '4800000.00',
      treatment_revenue: '4900000.00',
      metadata: { sufficient: false, minimum_sample_size: 20 },
      created_at: phaseDate(window, -8),
    },
  };
  return {
    experiments: { experiments, meta: { has_more: false, limit: 15, cursor: 0, total: experiments.length } },
    experimentLifts,
  };
}

export function buildPhase9Dummy(master: MasterData, window: DateWindow): Phase9DummyEntities {
  return {
    recommendation_actions: buildActions(master, window),
    replenishment_plans: buildPlans(master, window),
    ...buildExperiments(window),
  };
}
