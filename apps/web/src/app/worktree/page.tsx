'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';
import LoginForm from '@/components/LoginForm';
import WorktreeFlow from '@/components/WorktreeFlow';
import { PageHeader } from '@/components/ui';

function usePageRole(): { role: string | null; roleReady: boolean } {
  const [role, setRole] = useState<string | null>(null);
  const [roleReady, setRoleReady] = useState(false);

  useEffect(() => {
    let active = true;

    const handleRole = (resolved: string | null) => {
      if (active) {
        setRole(resolved);
        setRoleReady(true);
      }
    };

    const isDummy = useDummyStore.getState().isDummy;
    const token = getStoredToken();

    if (!token) {
      handleRole(null);
      return;
    }

    if (isDummy) {
      handleRole(localStorage.getItem('ddp_role'));
      return;
    }

    // Fetch role from /auth/me
    fetch(apiUrl('/auth/me'), { headers: authHeaders(token) })
      .then((res) => (res.ok ? res.json() : null))
      .then((body) => handleRole(body?.data?.role ?? null))
      .catch(() => handleRole(null));

    return () => {
      active = false;
    };
  }, []);

  return { role, roleReady };
}

export default function WorktreePage() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const { role } = usePageRole();

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
      <WorktreeFlow role={role} />
    </div>
  );
}
