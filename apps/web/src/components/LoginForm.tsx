'use client';

import { FormEvent, useState } from 'react';
import { apiUrl, storeToken } from '@/lib/api';

interface LoginFormProps {
  onLogin: (token: string, role: string) => void;
  expectedRole?: 'outlet' | 'admin';
}

export default function LoginForm({ onLogin, expectedRole }: LoginFormProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const response = await fetch(apiUrl('/auth/login'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Unable to sign in.');
      const role = result.data.user.role as string;
      if (expectedRole && role !== expectedRole) {
        throw new Error(`This page is for ${expectedRole} users.`);
      }
      storeToken(result.data.token);
      onLogin(result.data.token, role);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to sign in.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={submit} className="max-w-md space-y-4 rounded-lg border bg-white p-6 shadow-sm">
      <h2 className="text-xl font-semibold">Sign in</h2>
      {error && <p role="alert" className="rounded bg-red-100 p-3 text-red-700">{error}</p>}
      <label className="block text-sm font-medium">Email<input required type="email" value={email} onChange={(e) => setEmail(e.target.value)} className="mt-1 w-full rounded border p-2" /></label>
      <label className="block text-sm font-medium">Password<input required type="password" value={password} onChange={(e) => setPassword(e.target.value)} className="mt-1 w-full rounded border p-2" /></label>
      <button disabled={loading} className="w-full rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50">{loading ? 'Signing in...' : 'Sign in'}</button>
    </form>
  );
}
