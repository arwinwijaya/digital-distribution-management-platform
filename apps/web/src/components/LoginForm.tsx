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
      if (!response.ok) throw new Error(result.message || 'Email atau kata sandi salah.');
      const role = result.data.user.role as string;
      const allowedRoles = expectedRole ? (Array.isArray(expectedRole) ? expectedRole : [expectedRole]) : null;
      if (allowedRoles && !allowedRoles.includes(role as UserRole)) {
        const labels = allowedRoles.map((allowedRole) => roleLabels[allowedRole] ?? allowedRole).join(' atau ');
        throw new Error(`Halaman ini khusus untuk pengguna ${labels}.`);
      }
      storeToken(result.data.token);
      onLogin(result.data.token, role);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Tidak dapat masuk.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <Card className="max-w-md p-6">
      <div className="mb-6 flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-lg text-white">↗</div>
        <div>
          <h2 className="text-lg font-semibold text-gray-900">Masuk ke akun</h2>
          <p className="text-xs text-gray-500">Gunakan akun terdaftar Anda</p>
        </div>
      </div>
      {error && <p role="alert" className="mb-4 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      <form onSubmit={submit} className="space-y-4">
        <Input label="Email" required type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="nama@perusahaan.com" />
        <Input label="Kata sandi" required type="password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Masukkan kata sandi" />
        <Button type="submit" disabled={loading} className="w-full">{loading ? 'Memproses...' : 'Masuk'}</Button>
      </form>
    </Card>
  );
}
