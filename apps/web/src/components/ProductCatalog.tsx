'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead, useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';
import { Badge, Card, EmptyState, Input, PageHeader, StatusBadge, Table, ViewModeToggle, useViewMode } from '@/components/ui';
import { filterProducts } from '@/lib/product-filter';

interface Product {
  id: number;
  name: string;
  description: string | null;
  price: string;
  stock_quantity: number;
  category: string | null;
  is_active: boolean;
}

function money(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

function buildDummyCatalog(dummy: FullDummy): Product[] {
  return dummy.products.map((p, idx) => ({
    id: idx + 1,
    name: p.name,
    description: `${p.category} · ${p.sku}`,
    price: money(p.price),
    stock_quantity: 40 + ((idx * 13) % 260),
    category: p.category,
    is_active: true,
  }));
}

/**
 * Load product catalog for a token + optional search term.
 * While dummy mode is ON, returns derived catalog rows — zero network.
 */
export async function loadProductCatalog(token: string, search = ''): Promise<Product[]> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyFiltered: Product[] | null = isDummy && dummy
    ? buildDummyCatalog(dummy).filter((p) =>
        search ? p.name.toLowerCase().includes(search.toLowerCase()) : true,
      )
    : null;

  return withDummyRead(isDummy, dummyFiltered as Product[], async () => {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    const response = await fetch(`${apiUrl('/products')}?${params}`, { headers: authHeaders(token) });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Produk tidak dapat dimuat.');
    return data.data as Product[];
  });
}

export default function ProductCatalog({ token }: { token: string }) {
  const [products, setProducts] = useState<Product[]>([]);
  const [search, setSearch] = useState('');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { viewMode, setViewMode } = useViewMode();

  // The full catalog (≤100 rows) loads once; name/price filtering is purely
  // client-side, so `search` no longer drives a refetch.
  const fetchProducts = useCallback(async () => {
    setLoading(true); setError(null);
    try {
      const result = await loadProductCatalog(token);
      setProducts(result);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Terjadi kesalahan jaringan.');
    } finally { setLoading(false); }
  }, [token]);

  useEffect(() => { fetchProducts(); }, [fetchProducts]);

  useDummyRefresh(() => { void fetchProducts(); });

  const filteredProducts = useMemo(() => filterProducts(products, { name: search, minPrice, maxPrice }), [products, search, minPrice, maxPrice]);
  const columns = [
    { key: 'name', header: 'Produk', render: (product: Product) => <span className="font-medium text-gray-900">{product.name}</span> },
    { key: 'category', header: 'Kategori', render: (product: Product) => product.category ? <Badge variant="gray">{product.category}</Badge> : <span className="text-gray-400">—</span> },
    { key: 'price', header: 'Harga', render: (product: Product) => <span className="font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</span> },
    { key: 'stock', header: 'Stok', render: (product: Product) => <span className="text-gray-600">{product.stock_quantity}</span> },
    { key: 'status', header: 'Status', render: (product: Product) => <StatusBadge status={product.is_active && product.stock_quantity > 0 ? 'active' : 'inactive'} /> },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Katalog produk" description="Lihat produk yang tersedia untuk dipesan oleh outlet." />
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="grid flex-1 gap-3 sm:grid-cols-3">
          <Input aria-label="Cari produk" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama produk..." />
          <Input aria-label="Harga minimum" type="number" min="0" value={minPrice} onChange={(event) => setMinPrice(event.target.value)} placeholder="Harga min" />
          <Input aria-label="Harga maksimum" type="number" min="0" value={maxPrice} onChange={(event) => setMaxPrice(event.target.value)} placeholder="Harga maks" />
        </div>
        <ViewModeToggle value={viewMode} onChange={setViewMode} />
      </div>
      {error && <div className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</div>}
      {loading
        ? <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{[1, 2, 3].map((item) => <div key={item} className="skeleton h-44 rounded-xl" />)}</div>
        : products.length === 0
          ? <Card><EmptyState icon={<span>📦</span>} title="Produk tidak ditemukan" description="Belum ada produk yang sesuai dengan pencarian Anda." /></Card>
          : filteredProducts.length === 0
            ? <Card><EmptyState icon={<span>🔍</span>} title="Produk tidak ditemukan" description="Tidak ada produk yang cocok dengan pencarian atau rentang harga Anda." /></Card>
            : viewMode === 'table'
              ? <Card className="overflow-hidden"><Table columns={columns} rows={filteredProducts} rowKey={(product) => product.id} /></Card>
              : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{filteredProducts.map((product) => <Card key={product.id} className="p-5 hover:shadow-card-hover transition-shadow"><div className="mb-4 flex items-start justify-between gap-3"><div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-xl">📦</div><StatusBadge status={product.is_active && product.stock_quantity > 0 ? 'active' : 'inactive'} /></div><h3 className="font-semibold text-gray-900">{product.name}</h3>{product.description && <p className="mt-1 line-clamp-2 text-sm text-gray-500">{product.description}</p>}<div className="mt-4 flex items-end justify-between"><div><p className="text-lg font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</p><p className="text-xs text-gray-500">Stok: {product.stock_quantity}</p></div>{product.category && <Badge variant="gray">{product.category}</Badge>}</div></Card>)}</div>}
    </div>
  );
}
