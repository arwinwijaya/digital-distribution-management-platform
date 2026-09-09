'use client';

import { useCallback, useEffect, useState } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';

type Supplier = { id: number; name: string; subscription_plan: string };
type Product = {
  id: number;
  name: string;
  description: string | null;
  price: string;
  stock_quantity: number;
  supplier_id: number | null;
  supplier_name: string | null;
};

export default function MarketplaceCatalog({ token }: { token: string }) {
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadMarketplace = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const headers = authHeaders(token);
      const [supplierResponse, productResponse] = await Promise.all([
        fetch(apiUrl('/marketplace/suppliers?per_page=50'), { headers }),
        fetch(apiUrl('/marketplace/products?per_page=50'), { headers }),
      ]);
      const supplierBody = await supplierResponse.json();
      const productBody = await productResponse.json();
      if (!supplierResponse.ok || !productResponse.ok) {
        throw new Error(supplierBody.message || productBody.message || 'Failed to load marketplace');
      }
      setSuppliers(supplierBody.data || []);
      setProducts(productBody.data || []);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => { loadMarketplace(); }, [loadMarketplace]);

  if (loading) return <p className="py-8 text-center text-gray-500">Loading marketplace...</p>;
  if (error) return <div role="alert" className="rounded border border-red-300 bg-red-50 p-4 text-red-700">{error}</div>;

  return (
    <div className="mx-auto max-w-5xl space-y-8 p-6">
      <section>
        <h2 className="mb-3 text-2xl font-bold">Suppliers</h2>
        {suppliers.length === 0 ? (
          <p className="rounded bg-gray-100 p-4 text-gray-600">No active suppliers are available yet.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {suppliers.map((supplier) => <li key={supplier.id} className="rounded border bg-white p-4">{supplier.name}</li>)}
          </ul>
        )}
      </section>
      <section>
        <h2 className="mb-3 text-2xl font-bold">Marketplace products</h2>
        {products.length === 0 ? (
          <p className="rounded bg-gray-100 p-4 text-gray-600">Products are coming soon.</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {products.map((product) => (
              <article key={product.id} className="rounded border bg-white p-4 shadow-sm">
                <h3 className="font-semibold">{product.name}</h3>
                <p className="text-sm text-gray-500">{product.supplier_name || 'Platform catalog'}</p>
                <p className="mt-3 font-bold text-blue-600">Rp {Number(product.price).toLocaleString('id-ID')}</p>
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
