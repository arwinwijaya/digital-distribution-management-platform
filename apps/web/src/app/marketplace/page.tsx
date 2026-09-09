'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import MarketplaceCatalog from '@/components/MarketplaceCatalog';
import { getStoredToken } from '@/lib/api';
import { PageHeader } from '@/components/ui';

export default function MarketplacePage() {
  const [token, setToken] = useState<string | null>(null); const [ready, setReady] = useState(false);
  useEffect(() => { setToken(getStoredToken()); setReady(true); }, []);
  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Marketplace" description="Temukan supplier dan produk dari jaringan distribusi." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk untuk melihat supplier dan produk aktif.</div><LoginForm expectedRole="outlet" onLogin={(nextToken) => setToken(nextToken)} /></div>;
  return <MarketplaceCatalog token={token} />;
}
