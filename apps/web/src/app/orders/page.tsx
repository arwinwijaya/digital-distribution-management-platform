'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import OrderForm from '@/components/OrderForm';
import { getStoredToken } from '@/lib/api';
import { PageHeader } from '@/components/ui';

export default function OrdersPage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  useEffect(() => { setToken(getStoredToken()); setReady(true); }, []);
  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Pesanan outlet" description="Buat dan lacak pesanan produk Anda." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai outlet untuk mulai memesan. Belum punya akun? <a href="/outlets" className="font-semibold underline">Daftar di sini</a>.</div><LoginForm expectedRole="outlet" onLogin={(nextToken) => setToken(nextToken)} /></div>;
  return <div className="mx-auto max-w-6xl"><PageHeader title="Pesanan outlet" description="Pilih produk, kirim pesanan, dan pantau status pengiriman." /><OrderForm token={token} /></div>;
}
