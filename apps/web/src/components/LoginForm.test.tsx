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

  it('shows an inline credential error without persisting auth or clearing values', async () => {
    const onLogin = jest.fn();
    const dispatchSpy = jest.spyOn(window, 'dispatchEvent');
    const fetchMock = global.fetch as jest.Mock;
    fetchMock.mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({ message: 'Email atau kata sandi salah.' }),
    });

    render(<LoginForm onLogin={onLogin} />);
    const email = screen.getByLabelText('Email');
    const password = screen.getByLabelText('Kata sandi');
    fireEvent.change(email, { target: { value: 'wrong@example.com' } });
    fireEvent.change(password, { target: { value: 'wrong-secret' } });
    await act(async () => {
      fireEvent.submit(screen.getByRole('button', { name: 'Masuk' }));
    });

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Email atau kata sandi salah.'));
    expect(email).toHaveValue('wrong@example.com');
    expect(password).toHaveValue('wrong-secret');
    expect(localStorage.getItem('ddp_token')).toBeNull();
    expect(localStorage.getItem('ddp_role')).toBeNull();
    expect(dispatchSpy).not.toHaveBeenCalled();
    expect(onLogin).not.toHaveBeenCalled();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock).toHaveBeenCalledWith(apiUrl('/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'wrong@example.com', password: 'wrong-secret' }),
    });
  });

  it('shows a generic inline error for a network failure without persisting auth', async () => {
    const onLogin = jest.fn();
    const dispatchSpy = jest.spyOn(window, 'dispatchEvent');
    const fetchMock = global.fetch as jest.Mock;
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

    render(<LoginForm onLogin={onLogin} />);
    const email = screen.getByLabelText('Email');
    const password = screen.getByLabelText('Kata sandi');
    fireEvent.change(email, { target: { value: 'network@example.com' } });
    fireEvent.change(password, { target: { value: 'network-secret' } });
    await act(async () => {
      fireEvent.submit(screen.getByRole('button', { name: 'Masuk' }));
    });

    await waitFor(() => expect(screen.getByRole('alert').textContent).toBeTruthy());
    expect(email).toHaveValue('network@example.com');
    expect(password).toHaveValue('network-secret');
    expect(localStorage.getItem('ddp_token')).toBeNull();
    expect(localStorage.getItem('ddp_role')).toBeNull();
    expect(dispatchSpy).not.toHaveBeenCalled();
    expect(onLogin).not.toHaveBeenCalled();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock).toHaveBeenCalledWith(apiUrl('/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'network@example.com', password: 'network-secret' }),
    });
  });

  it('shows a generic inline error for a 5xx response without persisting auth', async () => {
    const onLogin = jest.fn();
    const dispatchSpy = jest.spyOn(window, 'dispatchEvent');
    const fetchMock = global.fetch as jest.Mock;
    fetchMock.mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({}),
    });

    render(<LoginForm onLogin={onLogin} />);
    const email = screen.getByLabelText('Email');
    const password = screen.getByLabelText('Kata sandi');
    fireEvent.change(email, { target: { value: 'server@example.com' } });
    fireEvent.change(password, { target: { value: 'server-secret' } });
    await act(async () => {
      fireEvent.submit(screen.getByRole('button', { name: 'Masuk' }));
    });

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Email atau kata sandi salah.'));
    expect(email).toHaveValue('server@example.com');
    expect(password).toHaveValue('server-secret');
    expect(localStorage.getItem('ddp_token')).toBeNull();
    expect(localStorage.getItem('ddp_role')).toBeNull();
    expect(dispatchSpy).not.toHaveBeenCalled();
    expect(onLogin).not.toHaveBeenCalled();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock).toHaveBeenCalledWith(apiUrl('/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'server@example.com', password: 'server-secret' }),
    });
  });

  it('disables the submit button and shows Memproses... while suppressing a double submit', async () => {
    const onLogin = jest.fn();
    const fetchMock = global.fetch as jest.Mock;
    let resolveFetch!: (value: unknown) => void;
    fetchMock.mockReturnValue(new Promise((resolve) => { resolveFetch = resolve; }));

    render(<LoginForm onLogin={onLogin} />);
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'pending@example.com' } });
    fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: 'pending-secret' } });
    await act(async () => {
      fireEvent.submit(screen.getByRole('button', { name: 'Masuk' }));
    });

    const loadingButton = screen.getByRole('button', { name: 'Memproses...' });
    expect(loadingButton).toBeDisabled();
    fireEvent.click(loadingButton);
    expect(fetchMock).toHaveBeenCalledTimes(1);

    await act(async () => {
      resolveFetch({ ok: true, status: 200, json: async () => ({ data: { token: 'token-pending', user: { role: 'outlet' } } }) });
    });
    await waitFor(() => expect(onLogin).toHaveBeenCalledTimes(1));
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
