'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import ProductCatalog from '@/components/ProductCatalog';
import { getStoredToken } from '@/lib/api';

export default function ProductsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    setToken(getStoredToken());
    setReady(true);
  }, []);

  if (!ready) return <main className="mx-auto max-w-5xl p-8"><p>Loading...</p></main>;
  if (!token) return <main className="mx-auto max-w-5xl space-y-4 p-8"><h1 className="text-3xl font-bold">Product catalog</h1><p className="rounded bg-yellow-100 p-3 text-yellow-800">Sign in as an outlet to browse the catalog.</p><LoginForm expectedRole="outlet" onLogin={(nextToken) => setToken(nextToken)} /></main>;

  return <main className="min-h-screen bg-gray-50 py-12"><ProductCatalog token={token} /></main>;
}
