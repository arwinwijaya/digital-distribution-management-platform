'use client';

import { FormEvent, useState } from 'react';
import { apiUrl, storeToken } from '@/lib/api';
import { Button, Card, Input } from '@/components/ui';

type UserRole = 'outlet' | 'admin' | 'sales' | 'driver' | 'finance';

interface LoginFormProps {
  onLogin: (token: string, role: string) => void;
  expectedRole?: UserRole | UserRole[];
}

const roleLabels: Record<string, string> = {
  outlet: 'outlet',
  admin: 'administrator',
  sales: 'sales',
  driver: 'driver',
  finance: 'finance',
};

function allowedRoles(expectedRole?: UserRole | UserRole[]): UserRole[] | null {
  if (!expectedRole) return null;
  return Array.isArray(expectedRole) ? expectedRole : [expectedRole];
}

async function authenticate(email: string, password: string, expectedRole?: UserRole | UserRole[]) {
  const response = await fetch(apiUrl('/auth/login'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const result = await response.json();
  if (!response.ok) throw new Error(result.message || 'Email atau kata sandi salah.');

  const role = result.data.user.role as string;
  const roles = allowedRoles(expectedRole);
  if (roles && !roles.includes(role as UserRole)) {
    const labels = roles.map((allowedRole) => roleLabels[allowedRole] ?? allowedRole).join(' atau ');
    throw new Error(`Halaman ini khusus untuk pengguna ${labels}.`);
  }
  return { token: result.data.token as string, role };
}

function useLoginForm({ onLogin, expectedRole }: LoginFormProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const { token, role } = await authenticate(email, password, expectedRole);
      storeToken(token);
      window.dispatchEvent(new CustomEvent('ddp-auth-change', { detail: { token, role } }));
      onLogin(token, role);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Tidak dapat masuk.');
    } finally {
      setLoading(false);
    }
  }

  return { email, password, error, loading, setEmail, setPassword, submit };
}

function LoginHeader() {
  return <div className="mb-6 flex items-center gap-3">
    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-lg text-white">↗</div>
    <div>
      <h2 className="text-lg font-semibold text-gray-900">Masuk ke akun</h2>
      <p className="text-xs text-gray-500">Gunakan akun terdaftar Anda</p>
    </div>
  </div>;
}

function LoginFields({ email, password, loading, onEmailChange, onPasswordChange, onSubmit }: {
  email: string;
  password: string;
  loading: boolean;
  onEmailChange: (value: string) => void;
  onPasswordChange: (value: string) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  return <form onSubmit={onSubmit} className="space-y-4">
    <Input label="Email" required type="email" value={email} onChange={(event) => onEmailChange(event.target.value)} placeholder="nama@perusahaan.com" />
    <Input label="Kata sandi" required type="password" value={password} onChange={(event) => onPasswordChange(event.target.value)} placeholder="Masukkan kata sandi" />
    <Button type="submit" disabled={loading} className="w-full">{loading ? 'Memproses...' : 'Masuk'}</Button>
  </form>;
}

export default function LoginForm(props: LoginFormProps) {
  const form = useLoginForm(props);
  return <Card className="max-w-md p-6">
    <LoginHeader />
    {form.error && <p role="alert" className="mb-4 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{form.error}</p>}
    <LoginFields email={form.email} password={form.password} loading={form.loading} onEmailChange={form.setEmail} onPasswordChange={form.setPassword} onSubmit={form.submit} />
  </Card>;
}
