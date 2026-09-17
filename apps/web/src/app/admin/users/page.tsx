'use client';

import { useCallback, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminUsers, assignUserRole, type AdminUser } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, PageHeader, Select, Table } from '@/components/ui';

const ROLE_OPTIONS = ['', 'admin', 'supplier', 'outlet', 'sales', 'driver', 'finance', 'platform_owner'] as const;

export default function AdminUsersPage() {
  const [token, setToken] = useState<string | null>(null);
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [roleFilter, setRoleFilter] = useState('');
  const [search, setSearch] = useState('');
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [selectedRole, setSelectedRole] = useState<string>('admin');
  const [hasMore, setHasMore] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const loadUsers = useCallback(
    async (authToken: string, role?: string) => {
      setLoading(true);
      setError(null);
      try {
        const result = await fetchAdminUsers(authToken, { role: role || undefined, limit: 20 });
        setUsers(result.users);
        setHasMore(result.hasMore);
      } catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Daftar pengguna tidak dapat dimuat.');
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) loadUsers(stored);
  }, [loadUsers]);

  useDummyRefresh(() => { if (token) void loadUsers(token); });

  function handleFilterApply() {
    if (!token) return;
    loadUsers(token, roleFilter);
  }

  async function handleAssignRole(userId: number) {
    if (!token) return;
    setActionError(null);
    try {
      const updated = await assignUserRole(token, userId, selectedRole);
      setUsers((prev) => prev.map((u) => (u.id === userId ? { ...u, ...updated } : u)));
      setEditingUserId(null);
    } catch (reason) {
      setActionError(reason instanceof Error ? reason.message : 'Peran pengguna tidak dapat diubah.');
    }
  }

  const filteredBySearch = users.filter((u) => {
    if (!search.trim()) return true;
    const q = search.toLowerCase();
    return u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
  });

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token)
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Kelola pengguna" description="Kelola pengguna dan tetapkan peran mereka." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk mengelola pengguna.
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadUsers(nextToken); }} />
      </div>
    );

  const columns = [
    { key: 'name', header: 'Nama', render: (u: AdminUser) => <span className="font-medium text-gray-900">{u.name}</span> },
    { key: 'email', header: 'Email', render: (u: AdminUser) => <span className="text-gray-600">{u.email}</span> },
    {
      key: 'role',
      header: 'Peran',
      render: (u: AdminUser) => (
        <span className="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-700">
          {u.role}
        </span>
      ),
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (u: AdminUser) =>
        editingUserId === u.id ? (
          <span className="flex items-center gap-2">
            <select
              value={selectedRole}
              onChange={(e) => setSelectedRole(e.target.value)}
              className="rounded-lg border border-gray-200 bg-white px-2 py-1 text-sm"
            >
              {ROLE_OPTIONS.filter(Boolean).map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </select>
            <Button size="sm" onClick={() => handleAssignRole(u.id)}>Simpan</Button>
            <Button size="sm" variant="ghost" onClick={() => setEditingUserId(null)}>Batal</Button>
          </span>
        ) : (
          <Button size="sm" variant="secondary" onClick={() => { setEditingUserId(u.id); setSelectedRole(u.role || 'admin'); }}>
            Ubah peran
          </Button>
        ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kelola pengguna" description="Kelola pengguna, filter berdasarkan peran, dan tetapkan peran." />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionError && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{actionError}</p>}
      <Card className="mb-5 p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <Select label="Filter peran" value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)}>
            <option value="">Semua peran</option>
            {ROLE_OPTIONS.filter(Boolean).map((r) => (
              <option key={r} value={r}>{r}</option>
            ))}
          </Select>
          <Input label="Cari (nama/email)" placeholder="Ketik nama atau email" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button onClick={handleFilterApply} disabled={loading}>Terapkan filter</Button>
        </div>
        {hasMore && <p className="mt-3 text-xs text-gray-500">Ada data lebih lanjut — hubungi dukungan jika diperlukan.</p>}
      </Card>
      <Card className="overflow-hidden">
        {loading ? (
          <p className="p-8 text-sm text-gray-500">Memuat pengguna...</p>
        ) : (
          <Table
            columns={columns}
            rows={filteredBySearch}
            rowKey={(u) => u.id}
            empty={<EmptyState icon={<span>👤</span>} title="Belum ada pengguna" description="Pengguna baru akan muncul di sini." />}
          />
        )}
      </Card>
    </div>
  );
}
