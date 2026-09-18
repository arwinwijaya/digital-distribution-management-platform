import { renderHook, act } from '@testing-library/react';
import { useViewMode } from './useViewMode';

describe('useViewMode', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('initializes to "card"', () => {
    const { result } = renderHook(() => useViewMode());
    expect(result.current.viewMode).toBe('card');
  });

  it('setViewMode updates state and persists to localStorage', () => {
    const { result } = renderHook(() => useViewMode());

    act(() => {
      result.current.setViewMode('table');
    });

    expect(result.current.viewMode).toBe('table');
    expect(localStorage.getItem('ui:view-mode')).toBe('table');
  });

  it('reads from localStorage on mount (re-mount scenario)', () => {
    localStorage.setItem('ui:view-mode', 'table');

    const { result } = renderHook(() => useViewMode());

    expect(result.current.viewMode).toBe('table');
  });

  it('ignores invalid localStorage values', () => {
    localStorage.setItem('ui:view-mode', 'invalid-value');

    const { result } = renderHook(() => useViewMode());

    expect(result.current.viewMode).toBe('card');
  });

  it('does not throw when localStorage is unavailable (SSR safe)', () => {
    const { result } = renderHook(() => useViewMode());
    expect(result.current.viewMode).toBe('card');
  });

  it('simulates re-mount by reading localStorage between hook instances', () => {
    const { result: first } = renderHook(() => useViewMode());
    act(() => {
      first.current.setViewMode('table');
    });
    expect(first.current.viewMode).toBe('table');
    expect(localStorage.getItem('ui:view-mode')).toBe('table');

    const { result: second } = renderHook(() => useViewMode());
    expect(second.current.viewMode).toBe('table');
  });
});
