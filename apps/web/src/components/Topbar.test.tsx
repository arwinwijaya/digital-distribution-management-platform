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
import { render, screen, fireEvent } from '@testing-library/react';
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
