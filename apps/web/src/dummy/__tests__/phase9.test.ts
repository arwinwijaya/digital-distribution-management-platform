import '@testing-library/jest-dom';
import React from 'react';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import {
  fetchRecommendationActions,
  type RecommendationAction,
} from '@/lib/ai-actions-api';
import {
  fetchReplenishmentPlans,
  type ReplenishmentPlan,
} from '@/lib/replenishment-api';
import {
  fetchExperiments,
  fetchRevenueLift,
  type AbExperiment,
  type RevenueLiftSnapshot,
} from '@/lib/experiments-api';
import { getDummyMatrix } from '@/dummy/rbac';
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import { useRbacStore } from '@/store/useRbacStore';
import Sidebar from '@/components/Sidebar';

jest.mock('next/navigation', () => ({ usePathname: () => '/dashboard' }));
jest.mock('next/link', () => {
  const React = require('react');
  return React.forwardRef(function Link(
    { children, href, ...rest }: { children: React.ReactNode; href: string } & Record<string, unknown>,
    ref: React.Ref<HTMLAnchorElement>,
  ) {
    return React.createElement('a', { href, ref, ...rest }, children);
  });
});

const realFetch = global.fetch as unknown as typeof fetch;

function expectKeys(value: object, keys: string[]): void {
  expect(Object.keys(value)).toEqual(expect.arrayContaining(keys));
}

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  useRbacStore.getState().reset();
  installDummy();
  global.fetch = jest.fn(() => {
    throw new Error('network must not be called while dummy mode is enabled');
  }) as unknown as typeof fetch;
});

afterEach(() => {
  cleanup();
  global.fetch = realFetch;
  useDummyStore.getState().reset();
  useRbacStore.getState().reset();
  jest.restoreAllMocks();
});

describe('Phase 9 dummy API parity', () => {
  it('returns non-empty AI actions, replenishment plans, experiments, and lifts without network', async () => {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const actionsResult = await fetchRecommendationActions('dummy-token');
    const plansResult = await fetchReplenishmentPlans('dummy-token');
    const experimentsResult = await fetchExperiments();
    const action = actionsResult.actions[0] as RecommendationAction;
    const plan = plansResult.plans[0] as ReplenishmentPlan;
    const experiment = experimentsResult.experiments[0] as AbExperiment;
    const lift = (await fetchRevenueLift(experiment.id)) as RevenueLiftSnapshot;

    expect(actionsResult.actions.length).toBeGreaterThan(0);
    expect(plansResult.plans.length).toBeGreaterThan(0);
    expect(experimentsResult.experiments.length).toBeGreaterThan(0);
    expect(lift).toBeDefined();

    expectKeys(action, [
      'id',
      'source_event_id',
      'outlet_id',
      'created_by',
      'approved_by',
      'executed_by',
      'type',
      'status',
      'payload',
      'idempotency_key',
      'idempotency_payload_hash',
      'approved_at',
      'executed_at',
      'rejection_reason',
      'execution_result',
      'method',
      'method_version',
      'fallback',
      'data_sufficiency',
      'metadata',
      'idempotent_replay',
      'created_at',
      'updated_at',
    ]);
    expectKeys(plan, [
      'id',
      'supplier_id',
      'supplier',
      'created_by',
      'approved_by',
      'executed_by',
      'status',
      'window_start',
      'window_end',
      'approved_at',
      'executed_at',
      'execution_result',
      'metadata',
      'items',
      'idempotent_replay',
      'created_at',
      'updated_at',
    ]);
    expect(plan.items.length).toBeGreaterThan(0);
    expectKeys(plan.items[0], [
      'product_id',
      'product',
      'reorder_quantity',
      'data_sufficiency',
      'metadata',
    ]);
    expectKeys(experiment, [
      'id',
      'experiment_key',
      'name',
      'status',
      'minimum_sample_size',
      'assignment_summary',
      'starts_at',
      'ends_at',
      'created_at',
    ]);
    expectKeys(lift, [
      'id',
      'experiment_id',
      'uplift',
      'status',
      'method_version',
      'control_sample_size',
      'treatment_sample_size',
      'control_revenue',
      'treatment_revenue',
      'metadata',
      'created_at',
    ]);
    expectKeys(experiment.assignment_summary, ['total', 'control', 'treatment']);
    expect(global.fetch).not.toHaveBeenCalled();
  });
});
