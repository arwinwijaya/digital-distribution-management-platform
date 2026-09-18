/**
 * Cross-unit dummy-mode cycle for the Analitik page (T3 fixture + T5 loader + T6 page).
 *
 * Deliberately a SEPARATE file from `page.test.tsx`: that file installs a
 * module-level `jest.mock('@/app/analytics/api', …)` which is hoisted file-wide,
 * so it can never exercise the REAL loaders. Here nothing is mocked except
 * `global.fetch` — the store, guards, loaders, dummy factory and the page all
 * run for real.
 *
 * The RED behaviour under test: the page's `useDummyRefresh` must re-drive the
 * INSIGHT load (`loadAnalyticsInsight`) as well as the AI load when the dummy
 * flag flips. The discriminator is that the OFF leg of the flip records a fetch
 * to `/analytics/insight` — without the fix only `/ai/*` calls are recorded.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, act } from '@testing-library/react';

import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';

import AnalyticsPage from '@/app/analytics/page';

const TOKEN = 't-token';
const FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00');
const DUMMY = buildFullDummy(FIXED_TODAY) as unknown as DummyEntities;

let originalFetch: typeof fetch | undefined;
let fetchMock: jest.Mock;

/** Benign responses so the OFF leg (real network) resolves instead of throwing. */
function benignResponse(url: string): Response {
  const isInsight = url.includes('/analytics/insight');
  const data = isInsight
    ? (DUMMY as unknown as { analyticsInsight: unknown }).analyticsInsight
    : {
        recommendations: [],
        data_points: 0,
        data_sufficiency: { sufficient: false, level: 'limited', note: '' },
        measurement: { measured: false, note: '' },
        predictions: [],
        method: 'moving-average',
        segments: [],
      };
  return {
    ok: true,
    status: 200,
    json: async () => ({ status: 'success', data }),
  } as unknown as Response;
}

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.setItem('ddp_token', TOKEN);
  setDummyGenerator(() => DUMMY);

  fetchMock = jest.fn(async (input: unknown) => benignResponse(String(input)));
  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  useDummyStore.getState().reset();
});

function recordedUrls(): string[] {
  return fetchMock.mock.calls.map((call) => String(call[0]));
}

describe('AnalyticsPage — dummy-mode insight cycle (real store + real loaders)', () => {
  it('renders insight sections with zero network while ON, and reloads insight on a flag flip', async () => {
    // ── Phase A: dummy ON, render the real page ──────────────────────────
    act(() => {
      useDummyStore.getState().toggle();
    });
    expect(useDummyStore.getState().isDummy).toBe(true);

    render(<AnalyticsPage />);

    await waitFor(() => expect(screen.getByText('+12,4%')).toBeInTheDocument());
    // A needs_attention row from the deterministic fixture is rendered.
    expect(screen.getByText('Toko Bintang Timur')).toBeInTheDocument();
    expect(screen.getByText('Perlu perhatian')).toBeInTheDocument();

    // Zero network for the ON render.
    expect(fetchMock).not.toHaveBeenCalled();

    // ── Phase B: flip OFF — useDummyRefresh must re-drive BOTH loads ──────
    act(() => {
      useDummyStore.getState().toggle();
    });
    expect(useDummyStore.getState().isDummy).toBe(false);

    await waitFor(() =>
      expect(recordedUrls().some((url) => url.includes('/analytics/insight'))).toBe(true),
    );
    // The AI leg must ALSO have been re-driven (both sections reload).
    expect(recordedUrls().some((url) => url.includes('/ai/'))).toBe(true);

    // ── Phase C: flip ON again — insight sections come back from the fixture
    fetchMock.mockClear();
    act(() => {
      useDummyStore.getState().toggle();
    });
    expect(useDummyStore.getState().isDummy).toBe(true);

    await waitFor(() => expect(screen.getByText('+12,4%')).toBeInTheDocument());
    expect(screen.getByText('Toko Bintang Timur')).toBeInTheDocument();
    // Back in dummy mode: zero network again.
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
