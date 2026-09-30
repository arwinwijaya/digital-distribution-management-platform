'use client';

import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import LoginForm from '@/components/LoginForm';
import { getStoredToken, apiUrl, authHeaders } from '@/lib/api';
import { PageHeader } from '@/components/ui';

function roleDestination(role: string, fallback: string) {
  if (role === 'outlet') return '/orders';
  if (role === 'driver') return '/delivery';
  if (role === 'sales') return '/sales/orders';
  if (role === 'finance') return '/dashboard';
  if (role === 'platform_owner') return '/dashboard';
  if (role === 'admin') return '/dashboard';
  return fallback;
}

function LoginContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [ready, setReady] = useState(false);

  const redirectParam = searchParams.get('redirect') || '/dashboard';

  useEffect(() => {
    const stored = getStoredToken();
    if (!stored) {
      setReady(true);
      return;
    }
    let active = true;
    fetch(apiUrl('/auth/me'), { headers: authHeaders(stored) })
      .then(async (response) => {
        const body = await response.json();
        if (!response.ok) return null;
        return body?.data?.role as string | null;
      })
      .then((role) => {
        if (!active) return;
        const storedRole = localStorage.getItem('ddp_role');
        router.replace(roleDestination(role ?? storedRole ?? '', redirectParam));
      })
      .catch(() => {
        if (active) setReady(true);
      });
    return () => { active = false; };
  }, [redirectParam, router]);

  // LoginForm tanpa expectedRole: satu form untuk semua role.
  function handleLogin(nextToken: string, role: string) {
    router.replace(roleDestination(role, redirectParam));
    router.refresh();
  }

  if (!ready) return <p className="text-center text-sm text-gray-500 py-12">Memuat...</p>;

  return (
    <div className="mx-auto max-w-5xl">
      <div data-testid="login-layout" className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-center">
        <section data-testid="login-hero-panel" className="rounded-2xl bg-primary-50 p-6">
          <h1 className="text-2xl font-bold text-gray-900">Pesanan ulang untuk outlet</h1>
          <p className="mt-2 text-sm text-gray-600">
            Satu tempat untuk melihat kebutuhan outlet dan mengirim pesanan ulang tanpa ribet.
          </p>
          <ul data-testid="login-benefits" className="mt-5 space-y-3 text-sm text-gray-700">
            <li className="flex gap-2"><span aria-hidden="true">&#8635;</span><span>Ulang pesanan rutin dalam beberapa klik.</span></li>
            <li className="flex gap-2"><span aria-hidden="true">&#128205;</span><span>Pantau status pengiriman setiap pesanan.</span></li>
            <li className="flex gap-2"><span aria-hidden="true">&#128230;</span><span>Akses katalog produk dan harga terbaru.</span></li>
          </ul>
        </section>
        <section data-testid="login-form-panel" className="mx-auto w-full max-w-md">
          <PageHeader title="Masuk" description="Gunakan satu form ini untuk semua peran: outlet, admin, sales, driver, ataupun finance." />
          <LoginForm onLogin={handleLogin} ctaLabel="Masuk & pesan ulang" />
          <p className="mt-6 text-center text-xs text-gray-500">
            Akun demo outlet: <code className="bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">siti.nurhaliza@ddp.test</code> / <code className="bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">password123</code>
          </p>
        </section>
      </div>
    </div>
  );
}

/** Halaman login terpadu untuk semua role. Setelah login, redirect ke halaman yang sesuai. */
export default function LoginPage() {
  return <Suspense fallback={<p className="text-center text-sm text-gray-500 py-12">Memuat...</p>}><LoginContent /></Suspense>;
}
