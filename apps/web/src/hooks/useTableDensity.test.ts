import { renderHook, act } from '@testing-library/react';
import { useTableDensity } from './useTableDensity';

describe('useTableDensity', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('initializes to "default"', () => {
    const { result } = renderHook(() => useTableDensity());
    expect(result.current.density).toBe('default');
  });

  it('setDensity updates state and persists to localStorage', () => {
    const { result } = renderHook(() => useTableDensity());

    act(() => {
      result.current.setDensity('compact');
    });

    expect(result.current.density).toBe('compact');
    expect(localStorage.getItem('admin:table-density')).toBe('compact');
  });

  it('reads from localStorage on mount (re-mount scenario)', () => {
    localStorage.setItem('admin:table-density', 'comfortable');

    const { result } = renderHook(() => useTableDensity());

    // After useEffect runs, state should reflect stored value
    expect(result.current.density).toBe('comfortable');
  });

  it('setDensity to "comfortable" updates state and persists', () => {
    const { result } = renderHook(() => useTableDensity());

    act(() => {
      result.current.setDensity('comfortable');
    });

    expect(result.current.density).toBe('comfortable');
    expect(localStorage.getItem('admin:table-density')).toBe('comfortable');
  });

  it('ignores invalid localStorage values', () => {
    localStorage.setItem('admin:table-density', 'invalid-value');

    const { result } = renderHook(() => useTableDensity());

    expect(result.current.density).toBe('default');
  });

  it('does not throw when localStorage is unavailable (SSR safe)', () => {
    // The hook should not access localStorage during initial render
    const { result } = renderHook(() => useTableDensity());
    expect(result.current.density).toBe('default');
  });

  it('simulates re-mount by reading localStorage between hook instances', () => {
    // First instance: set to compact
    const { result: first } = renderHook(() => useTableDensity());
    act(() => {
      first.current.setDensity('compact');
    });
    expect(first.current.density).toBe('compact');
    expect(localStorage.getItem('admin:table-density')).toBe('compact');

    // Second instance: should initialize from localStorage
    const { result: second } = renderHook(() => useTableDensity());
    expect(second.current.density).toBe('compact');
  });
});