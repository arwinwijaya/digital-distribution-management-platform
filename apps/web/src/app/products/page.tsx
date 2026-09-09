'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import ProductCatalog from '@/components/ProductCatalog';
import { getStoredToken } from '@/lib/api';
import { PageHeader } from '@/components/ui';

export default function ProductsPage() {
  const [token, setToken] = useState<string | null>(null); const [ready, setReady] = useState(false);
  useEffect(() => { setToken(getStoredToken()); setReady(true); }, []);
  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Katalog produk" description="Jelajahi produk yang tersedia di jaringan distribusi." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai outlet untuk melihat katalog produk.</div><LoginForm expectedRole="outlet" onLogin={(nextToken) => setToken(nextToken)} /></div>;
  return <ProductCatalog token={token} />;
}
