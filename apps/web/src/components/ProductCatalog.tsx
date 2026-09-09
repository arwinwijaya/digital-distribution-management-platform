'use client';

import { useState, useEffect, useCallback } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import { Badge, Card, EmptyState, Input, PageHeader, StatusBadge } from '@/components/ui';

interface Product {
  id: number;
  name: string;
  description: string | null;
  price: string;
  stock_quantity: number;
  category: string | null;
  is_active: boolean;
}

export default function ProductCatalog({ token }: { token: string }) {
  const [products, setProducts] = useState<Product[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProducts = useCallback(async () => {
    setLoading(true); setError(null);
    try {
      const params = new URLSearchParams();
      if (search) params.set('search', search);
      const response = await fetch(`${apiUrl('/products')}?${params}`, { headers: authHeaders(token) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Produk tidak dapat dimuat.');
      setProducts(data.data);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Terjadi kesalahan jaringan.');
    } finally { setLoading(false); }
  }, [search, token]);

  useEffect(() => { fetchProducts(); }, [fetchProducts]);

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Katalog produk" description="Lihat produk yang tersedia untuk dipesan oleh outlet." />
      <div className="mb-5 max-w-md"><Input aria-label="Cari produk" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama produk..." /></div>
      {error && <div className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</div>}
      {loading ? <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{[1, 2, 3].map((item) => <div key={item} className="skeleton h-44 rounded-xl" />)}</div> : products.length === 0 ? <Card><EmptyState icon={<span>📦</span>} title="Produk tidak ditemukan" description="Belum ada produk yang sesuai dengan pencarian Anda." /></Card> : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{products.map((product) => <Card key={product.id} className="p-5 hover:shadow-card-hover transition-shadow"><div className="mb-4 flex items-start justify-between gap-3"><div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-xl">📦</div><StatusBadge status={product.is_active && product.stock_quantity > 0 ? 'active' : 'inactive'} /></div><h3 className="font-semibold text-gray-900">{product.name}</h3>{product.description && <p className="mt-1 line-clamp-2 text-sm text-gray-500">{product.description}</p>}<div className="mt-4 flex items-end justify-between"><div><p className="text-lg font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</p><p className="text-xs text-gray-500">Stok: {product.stock_quantity}</p></div>{product.category && <Badge variant="gray">{product.category}</Badge>}</div></Card>)}</div>}
    </div>
  );
}
