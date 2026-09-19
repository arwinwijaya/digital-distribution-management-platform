'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getStoredToken } from '@/lib/api';
import LoginForm from '@/components/LoginForm';
import WorktreeFlow from '@/components/WorktreeFlow';
import { PageHeader } from '@/components/ui';

export default function WorktreePage() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const t = getStoredToken();
    setToken(t);
    setReady(true);
    if (!t) router.replace('/login');
  }, [router]);

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;

  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader
          title="Worktree"
          description="Alur proses pemesanan dari awal sampai selesai. Klik tahap untuk melihat detail."
        />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk untuk melihat alur proses pemesanan.
        </div>
        <LoginForm onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader
        title="Worktree"
        description="Alur proses pemesanan dari awal sampai selesai. Klik tahap untuk melihat detail."
      />
      <WorktreeFlow />
    </div>
  );
}
