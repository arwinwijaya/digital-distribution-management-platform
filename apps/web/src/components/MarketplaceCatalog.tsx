'use client';

import { useCallback, useEffect, useState } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead, useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';
import { Badge, Card, EmptyState, PageHeader } from '@/components/ui';

type Supplier = { id: number; name: string; subscription_plan: string };
type Product = { id: number; name: string; description: string | null; price: string; stock_quantity: number; supplier_id: number | null; supplier_name: string | null };

function money(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

/**
 * Load marketplace (suppliers + products) for a token — both reads in parallel.
 * While dummy mode is ON, derives rows from T5 master data — zero network.
 */
export async function loadMarketplaceCatalog(
  token: string,
): Promise<{ suppliers: Supplier[]; products: Product[] }> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyPayload: { suppliers: Supplier[]; products: Product[] } | null = isDummy && dummy
    ? {
        suppliers: dummy.suppliers.suppliers.map((s) => ({
          id: s.supplier_id,
          name: s.supplier_name,
          subscription_plan: s.status ?? 'pro',
        })),
        products: dummy.products.map((p, idx) => ({
          id: idx + 1,
          name: p.name,
          description: `${p.category} · ${p.sku}`,
          price: money(p.price),
          stock_quantity: 40 + ((idx * 13) % 260),
          supplier_id: (idx % dummy.suppliers.suppliers.length) + 1,
          supplier_name: dummy!.suppliers.suppliers[idx % dummy.suppliers.suppliers.length].supplier_name,
        })),
      }
    : null;

  return withDummyRead(isDummy, dummyPayload as { suppliers: Supplier[]; products: Product[] }, async () => {
    const headers = authHeaders(token);
    const [supplierResponse, productResponse] = await Promise.all([
      fetch(apiUrl('/marketplace/suppliers?per_page=50'), { headers }),
      fetch(apiUrl('/marketplace/products?per_page=50'), { headers }),
    ]);
    const supplierBody = await supplierResponse.json();
    const productBody = await productResponse.json();
    if (!supplierResponse.ok || !productResponse.ok)
      throw new Error(supplierBody.message || productBody.message || 'Marketplace tidak dapat dimuat.');
    return {
      suppliers: supplierBody.data || [],
      products: productBody.data || [],
    };
  });
}

export default function MarketplaceCatalog({ token }: { token: string }) {
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadMarketplace = useCallback(async () => {
    setLoading(true); setError(null);
    try {
      const result = await loadMarketplaceCatalog(token);
      setSuppliers(result.suppliers);
      setProducts(result.products);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Terjadi kesalahan jaringan.'); }
    finally { setLoading(false); }
  }, [token]);

  useEffect(() => { loadMarketplace(); }, [loadMarketplace]);
  useDummyRefresh(() => { void loadMarketplace(); });
  if (loading) return <div className="space-y-4"><div className="skeleton h-8 w-48 rounded" /><div className="grid gap-4 sm:grid-cols-3">{[1, 2, 3].map((item) => <div key={item} className="skeleton h-32 rounded-xl" />)}</div></div>;
  if (error) return <div role="alert" className="rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">{error}</div>;

  return <div className="mx-auto max-w-6xl"><PageHeader title="Marketplace" description="Temukan supplier dan produk dari seluruh jaringan distribusi." /><section className="mb-8"><h2 className="mb-3 text-base font-semibold text-gray-900">Supplier aktif <span className="ml-1 text-sm font-normal text-gray-500">({suppliers.length})</span></h2>{suppliers.length === 0 ? <Card><EmptyState icon={<span>🏢</span>} title="Belum ada supplier" description="Supplier aktif akan muncul di sini." /></Card> : <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{suppliers.map((supplier) => <Card key={supplier.id} className="flex items-center gap-3 p-4"><div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-lg">🏢</div><div className="min-w-0"><p className="truncate font-medium text-gray-900">{supplier.name}</p><Badge variant="blue">{supplier.subscription_plan}</Badge></div></Card>)}</div>}</section><section><h2 className="mb-3 text-base font-semibold text-gray-900">Produk marketplace <span className="ml-1 text-sm font-normal text-gray-500">({products.length})</span></h2>{products.length === 0 ? <Card><EmptyState icon={<span>🛒</span>} title="Produk segera hadir" description="Belum ada produk marketplace yang tersedia." /></Card> : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{products.map((product) => <Card key={product.id} className="p-5"><div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning-50 text-lg">📦</div><h3 className="mt-3 font-semibold text-gray-900">{product.name}</h3><p className="text-sm text-gray-500">{product.supplier_name || 'Katalog platform'}</p><p className="mt-3 text-lg font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</p></Card>)}</div>}</section></div>;
}
