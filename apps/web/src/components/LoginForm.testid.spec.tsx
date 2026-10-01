import React from 'react';
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import LoginForm from './LoginForm';

const originalFetch = global.fetch;

describe('LoginForm — data-testid contract', () => {
  beforeEach(() => {
    localStorage.clear();
    global.fetch = jest.fn();
  });

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  it('exposes login-email, login-password, login-submit (and login-error after failed login)', async () => {
    const onLogin = jest.fn();

    render(<LoginForm onLogin={onLogin} />);

    expect(screen.getByTestId('login-email')).toBeInTheDocument();
    expect(screen.getByTestId('login-password')).toBeInTheDocument();
    expect(screen.getByTestId('login-submit')).toBeInTheDocument();
    expect(screen.queryByTestId('login-error')).not.toBeInTheDocument();

    const fetchMock = global.fetch as jest.Mock;
    fetchMock.mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({ message: 'Email atau kata sandi salah.' }),
    });

    fireEvent.change(screen.getByTestId('login-email'), { target: { value: 'wrong@example.com' } });
    fireEvent.change(screen.getByTestId('login-password'), { target: { value: 'wrong-secret' } });
    await act(async () => {
      fireEvent.click(screen.getByTestId('login-submit'));
    });

    await waitFor(() => expect(screen.getByTestId('login-error')).toHaveTextContent('Email atau kata sandi salah.'));
  });
});
