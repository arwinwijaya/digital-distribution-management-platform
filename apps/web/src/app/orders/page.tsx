'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import OrderForm from '@/components/OrderForm';
import { getStoredToken } from '@/lib/api';

export default function OrdersPage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  useEffect(() => { setToken(getStoredToken()); setReady(true); }, []);
  if (!ready) return <main className="mx-auto max-w-5xl p-8"><p>Loading...</p></main>;
  return <main className="mx-auto max-w-5xl space-y-6 p-8"><header><h1 className="text-3xl font-bold">Outlet orders</h1><p className="text-gray-600">Browse, submit, and track your orders.</p></header>{token ? <OrderForm token={token} /> : <><p className="rounded bg-yellow-100 p-3 text-yellow-800">Sign in as an outlet to order. New outlet users can register first.</p><LoginForm expectedRole="outlet" onLogin={(nextToken) => setToken(nextToken)} /></>}</main>;
}
