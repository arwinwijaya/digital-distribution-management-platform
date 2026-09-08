import Link from 'next/link';

export default function Home() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center p-24">
      <h1 className="text-4xl font-bold mb-8">
        Digital Distribution Management Platform
      </h1>
      <p className="text-xl text-gray-600 mb-8">
        FMCG Digital Distribution Ecosystem
      </p>
      <nav className="mb-10 flex gap-4">
        <Link className="rounded bg-blue-600 px-4 py-2 text-white" href="/outlets">Register outlet</Link>
        <Link className="rounded bg-gray-800 px-4 py-2 text-white" href="/orders">Outlet orders</Link>
        <Link className="rounded border px-4 py-2" href="/admin/orders">Admin orders</Link>
      </nav>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl">
        <div className="p-6 border rounded-lg shadow-sm">
          <h2 className="text-xl font-semibold mb-4">Suppliers</h2>
          <p className="text-gray-600">
            Connect with brand principals and manage product catalogs.
          </p>
        </div>
        <div className="p-6 border rounded-lg shadow-sm">
          <h2 className="text-xl font-semibold mb-4">Outlets</h2>
          <p className="text-gray-600">
            Digital network of retail partners and warungs.
          </p>
        </div>
        <div className="p-6 border rounded-lg shadow-sm">
          <h2 className="text-xl font-semibold mb-4">Analytics</h2>
          <p className="text-gray-600">
            Data-driven insights and sales intelligence.
          </p>
        </div>
      </div>
    </main>
  );
}
