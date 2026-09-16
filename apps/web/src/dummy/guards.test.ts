/**
 * Dummy-mode centralized guard helpers tests.
 *
 * Cycle 1 (unit) — withDummyRead short-circuits when ON + commitIfCurrent
 * discards on flip.
 */
import { renderHook, act } from '@testing-library/react';
import { withDummyRead, commitIfCurrent, useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('withDummyRead - cycle 1', () => {
  it('returns dummy and never calls realFetch when isDummy is true', async () => {
    const D = { id: 'dummy-001', name: 'Toko Bogor Indah' };
    const R = { id: 'real-001', name: 'Real Outlet' };
    const realFetch = jest.fn<Promise<typeof R>, []>(async () => R);

    const result = await withDummyRead(true, D, realFetch);

    expect(result).toEqual(D);
    expect(realFetch).not.toHaveBeenCalled();
  });
});

describe('commitIfCurrent - cycle 1', () => {
  it('returns false and never calls setter when flag flipped to false but expectedFlag was true', () => {
    const data = { id: 'x' };
    const setter = jest.fn();
    const getFlag = jest.fn<boolean, []>(() => false);

    const committed = commitIfCurrent(getFlag, true, setter, data);

    expect(committed).toBe(false);
    expect(setter).not.toHaveBeenCalled();
  });
});

// ---------------------------------------------------------------------------
// Cycle 2 (unit) — OFF path calls realFetch + commitIfCurrent pass-through.
// ---------------------------------------------------------------------------

describe('withDummyRead - cycle 2', () => {
  it('calls realFetch exactly once and resolves R when isDummy is false', async () => {
    const D = { tag: 'dummy' };
    const R = { tag: 'real' };
    const realFetch = jest.fn<Promise<typeof R>, []>(async () => R);

    const result = await withDummyRead(false, D, realFetch);

    expect(result).toEqual(R);
    expect(realFetch).toHaveBeenCalledTimes(1);
  });
});

describe('commitIfCurrent - cycle 2', () => {
  it('calls setter once with data and returns true when flag unchanged', () => {
    const data = { id: 'ok' };
    const setter = jest.fn();
    const getFlag = jest.fn<boolean, []>(() => false);

    const committed = commitIfCurrent(getFlag, false, setter, data);

    expect(committed).toBe(true);
    expect(setter).toHaveBeenCalledTimes(1);
    expect(setter).toHaveBeenCalledWith(data);
  });
});

// ---------------------------------------------------------------------------
// Cycle 3 (integration) — useDummyRefresh fires on flip through the real
// store. No store/hook mocks; callback is the only test double.
// ---------------------------------------------------------------------------

describe('useDummyRefresh - cycle 3', () => {
  it('fires exactly once per isDummy flip: OFF -> ON -> OFF calls onChange twice', () => {
    expect(useDummyStore.getState().isDummy).toBe(false);
    const onChange = jest.fn();

    renderHook(() => useDummyRefresh(onChange));

    act(() => {
      useDummyStore.getState().toggle();
    });
    act(() => {
      useDummyStore.getState().toggle();
    });

    expect(onChange).toHaveBeenCalledTimes(2);
  });
});
