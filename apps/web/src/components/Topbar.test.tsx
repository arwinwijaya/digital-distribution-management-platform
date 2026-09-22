/**
 * Topbar.test.tsx — integration tests for the Mode Dummy toggle.
 *
 * Cycle 1: toggle visible when logged in, flips store, hidden when logged out.
 * Cycle 2: logout clears ddp_role and resets dummy OFF.
 *
 * Exercises the real <Topbar /> with the real Zustand store. Only the dummy
 * generator is stubbed (toggle-ON must not throw with an empty factory).
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { useDummyStore, setDummyGenerator, DUMMY_FLAG_KEY } from '@/dummy/store';
import Topbar from '@/components/Topbar';

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  // Re-register a stub generator so toggle-ON never throws with an empty factory.
  setDummyGenerator(() => ({ stub: true }));
});

afterEach(() => {
  jest.restoreAllMocks();
});

/** Assert that `before` precedes `after` in document order. */
function assertFollows(before: Element, after: Element): void {
  expect(before.compareDocumentPosition(after) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
}

/* ------------------------------------------------------------------ */
/* Cycle 1 — Toggle visible when logged in, flips store, hidden when   */
/*           logged out                                                */
/* ------------------------------------------------------------------ */
describe('Cycle 1 — Mode Dummy toggle in Topbar', () => {
  it('renders the toggle between the Online badge and Keluar when logged in, and flips the store on click', () => {
    // Arrange: logged in, store OFF
    localStorage.setItem('ddp_token', 'test-token-123');
    expect(useDummyStore.getState().isDummy).toBe(false);

    render(<Topbar />);

    // Assert: switch labelled Mode Dummy renders BETWEEN Online badge and Keluar
    const online = screen.getByText('Online');
    const toggle = screen.getByRole('switch', { name: /dummy/i });
    const keluar = screen.getByRole('button', { name: /keluar/i });

    assertFollows(online, toggle);
    assertFollows(toggle, keluar);

    // Assert: starts OFF
    expect(toggle).toHaveAttribute('aria-checked', 'false');

    // Act: click the toggle
    fireEvent.click(toggle);

    // Assert: store ON + persisted flag
    expect(useDummyStore.getState().isDummy).toBe(true);
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBe('1');
    expect(screen.getByRole('switch', { name: /dummy/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
  });

  it('renders no Mode Dummy toggle when logged out', () => {
    // Arrange: no token, store OFF
    expect(localStorage.getItem('ddp_token')).toBeNull();
    expect(useDummyStore.getState().isDummy).toBe(false);

    render(<Topbar />);

    // Assert: no switch and no Keluar
    expect(screen.queryByRole('switch', { name: /dummy/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /keluar/i })).not.toBeInTheDocument();
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 2 — Logout clears ddp_role and resets dummy OFF               */
/* ------------------------------------------------------------------ */
describe('Cycle 2 — Logout clears ddp_role and resets dummy OFF', () => {
  it('clears ddp_token + ddp_role and resets the store when Keluar is clicked', () => {
    // Arrange: logged in with admin role, store ON
    localStorage.setItem('ddp_token', 'test-token-123');
    localStorage.setItem('ddp_role', 'admin');
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBe('1');

    render(<Topbar />);

    // Act: click Keluar
    fireEvent.click(screen.getByRole('button', { name: /keluar/i }));

    // Assert: token + role cleared
    expect(localStorage.getItem('ddp_token')).toBeNull();
    expect(localStorage.getItem('ddp_role')).toBeNull();

    // Assert: store OFF + flag cleared
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBeNull();
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 3 — Topbar reacts to auth changes WITHOUT remounting          */
/* (regression: an in-page login left the buttons hidden until F5)     */
/* ------------------------------------------------------------------ */
describe('Cycle 3 — Topbar reacts to auth changes without remount', () => {
  it('reveals Mode Dummy + Keluar after an in-page login event', () => {
    // Arrange: rendered while logged out — this is the SAME element tree
    // that AppShell keeps mounted across client-side navigation.
    render(<Topbar />);
    expect(screen.queryByRole('switch', { name: /dummy/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /keluar/i })).not.toBeInTheDocument();

    // Act: mirror LoginForm.submit() — token first, then the event.
    localStorage.setItem('ddp_token', 'test-token-123');
    act(() => {
      window.dispatchEvent(
        new CustomEvent('ddp-auth-change', { detail: { token: 'test-token-123', role: 'admin' } }),
      );
    });

    // Assert: both buttons appear without a remount
    expect(screen.getByRole('switch', { name: /dummy/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /keluar/i })).toBeInTheDocument();
  });

  it('reveals the buttons on a cross-tab storage event for ddp_token', () => {
    // Arrange: rendered while logged out
    render(<Topbar />);
    expect(screen.queryByRole('button', { name: /keluar/i })).not.toBeInTheDocument();

    // Act: another tab wrote the token, then the browser delivered the event.
    // localStorage must be written FIRST — the handler re-reads getStoredToken().
    localStorage.setItem('ddp_token', 'test-token-123');
    act(() => {
      window.dispatchEvent(
        new StorageEvent('storage', { key: 'ddp_token', newValue: 'test-token-123' }),
      );
    });

    // Assert
    expect(screen.getByRole('switch', { name: /dummy/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /keluar/i })).toBeInTheDocument();
  });

  it('ignores storage events for unrelated keys', () => {
    // Arrange: rendered while logged out
    render(<Topbar />);

    // Act: a token exists, but the event is for a different key
    localStorage.setItem('ddp_token', 'test-token-123');
    act(() => {
      window.dispatchEvent(
        new StorageEvent('storage', { key: 'ddp_role', newValue: 'admin' }),
      );
    });

    // Assert: the guard kept the buttons hidden
    expect(screen.queryByRole('switch', { name: /dummy/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /keluar/i })).not.toBeInTheDocument();
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 4 — Online/offline indicator (Phase 8, T10)                   */
/* Driven by useOnlineStatus: reads navigator.onLine on mount, then    */
/* re-reads on window online/offline events, cleaning up on unmount.   */
/* ------------------------------------------------------------------ */
describe('Cycle 4 — Topbar online/offline indicator', () => {
  function setOnline(value: boolean) {
    Object.defineProperty(window.navigator, 'onLine', {
      configurable: true,
      get: () => value,
    });
  }

  beforeEach(() => {
    setOnline(true);
  });

  it('shows the Online badge while the browser reports connectivity', () => {
    render(<Topbar />);

    expect(screen.getByText('Online')).toBeInTheDocument();
    expect(screen.queryByText('Offline')).not.toBeInTheDocument();
  });

  it('shows the Offline badge when mounted without connectivity', () => {
    setOnline(false);

    render(<Topbar />);

    expect(screen.getByText('Offline')).toBeInTheDocument();
    expect(screen.queryByText('Online')).not.toBeInTheDocument();
  });

  it('switches to Offline on the offline event and back on the online event', () => {
    render(<Topbar />);
    expect(screen.getByText('Online')).toBeInTheDocument();

    setOnline(false);
    act(() => {
      window.dispatchEvent(new Event('offline'));
    });
    expect(screen.getByText('Offline')).toBeInTheDocument();

    setOnline(true);
    act(() => {
      window.dispatchEvent(new Event('online'));
    });
    expect(screen.getByText('Online')).toBeInTheDocument();
  });

  it('removes its window listeners on unmount', () => {
    const addSpy = jest.spyOn(window, 'addEventListener');
    const removeSpy = jest.spyOn(window, 'removeEventListener');

    const { unmount } = render(<Topbar />);
    expect(addSpy).toHaveBeenCalledWith('online', expect.any(Function));
    expect(addSpy).toHaveBeenCalledWith('offline', expect.any(Function));

    unmount();

    expect(removeSpy).toHaveBeenCalledWith('online', expect.any(Function));
    expect(removeSpy).toHaveBeenCalledWith('offline', expect.any(Function));
  });
});
