import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import LoginForm from './LoginForm';
import { apiUrl } from '@/lib/api';

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

  it('persists auth state and preserves the login request shape after a successful login', async () => {
    const onLogin = jest.fn();
    const dispatchSpy = jest.spyOn(window, 'dispatchEvent');
    const fetchMock = global.fetch as jest.Mock;
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ data: { token: 'token-123', user: { role: 'outlet' } } }),
    });

    render(<LoginForm onLogin={onLogin} />);
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'outlet@example.com' } });
    fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: 'secret' } });
    await act(async () => {
      fireEvent.submit(screen.getByRole('button', { name: 'Masuk' }));
    });

    await waitFor(() => expect(onLogin).toHaveBeenCalledTimes(1));
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock).toHaveBeenCalledWith(apiUrl('/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'outlet@example.com', password: 'secret' }),
    });
    expect(localStorage.getItem('ddp_token')).toBe('token-123');
    expect(localStorage.getItem('ddp_role')).toBe('outlet');
    expect(dispatchSpy).toHaveBeenCalledTimes(1);
    expect(dispatchSpy.mock.calls[0][0]).toMatchObject({
      type: 'ddp-auth-change',
      detail: { token: 'token-123', role: 'outlet' },
    });
    expect(onLogin).toHaveBeenCalledWith('token-123', 'outlet');
  });
});
