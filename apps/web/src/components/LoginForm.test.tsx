import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';
import LoginForm from './LoginForm';

const originalFetch = global.fetch;

describe('LoginForm', () => {
  beforeEach(() => {
    localStorage.clear();
    global.fetch = jest.fn();
  });

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  it('preserves the default CTA and renders a custom CTA label', () => {
    const onLogin = jest.fn();

    const { unmount } = render(<LoginForm onLogin={onLogin} />);
    expect(screen.getByRole('button', { name: 'Masuk' })).toBeInTheDocument();
    unmount();

    render(<LoginForm onLogin={onLogin} ctaLabel="Masuk & pesan ulang" />);
    expect(screen.getByRole('button', { name: 'Masuk & pesan ulang' })).toBeInTheDocument();
  });
});
