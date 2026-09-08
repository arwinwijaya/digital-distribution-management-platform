'use client';

import { useState, useEffect, useCallback } from 'react';
import { apiUrl } from '@/lib/api';

interface Product {
  id: number;
  name: string;
  description: string | null;
  price: string;
  stock_quantity: number;
  category: string | null;
  is_active: boolean;
}

export default function ProductCatalog() {
  const [products, setProducts] = useState<Product[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProducts = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const params = new URLSearchParams();
      if (search) params.set('search', search);

      const url = `${apiUrl('/products')}?${params}`;
      const response = await fetch(url);
      const data = await response.json();

      if (!response.ok) {
        setError(data.message || 'Failed to load products');
        return;
      }

      setProducts(data.data);
    } catch {
      setError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    fetchProducts();
  }, [fetchProducts]);

  return (
    <div className="max-w-4xl mx-auto p-6">
      <h2 className="text-2xl font-bold mb-6">Product Catalog</h2>

      <div className="mb-6">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search products..."
          className="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      {error && (
        <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
          {error}
        </div>
      )}

      {loading ? (
        <div className="text-center py-8 text-gray-500">Loading products...</div>
      ) : products.length === 0 ? (
        <div className="text-center py-8 text-gray-500">No products found.</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {products.map((product) => (
            <div
              key={product.id}
              className="border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow"
            >
              <h3 className="font-semibold text-lg">{product.name}</h3>
              {product.description && (
                <p className="text-gray-600 text-sm mt-1">{product.description}</p>
              )}
              <div className="mt-3 flex justify-between items-center">
                <span className="text-blue-600 font-bold">
                  Rp {Number(product.price).toLocaleString('id-ID')}
                </span>
                <span
                  className={`text-sm px-2 py-1 rounded ${
                    product.is_active && product.stock_quantity > 0
                      ? 'bg-green-100 text-green-700'
                      : 'bg-red-100 text-red-700'
                  }`}
                >
                  {product.is_active && product.stock_quantity > 0 ? 'In Stock' : 'Unavailable'}
                </span>
              </div>
              {product.category && (
                <span className="mt-2 inline-block text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                  {product.category}
                </span>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
