'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminUsers, assignUserRole, type AdminUser } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, PageHeader, Select, Table, TableSummary, TablePagination, TableDensityToggle } from '@/components/ui';
import { useTableDensity } from '@/hooks/useTableDensity';
import { toggleSort, formatDateTime, type ColumnSort } from '@/lib/admin-table';

const ROLE_OPTIONS = ['', 'admin', 'supplier', 'outlet', 'sales', 'driver', 'finance', 'platform_owner'] as const;

const PAGE_LIMIT = 20;

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
  // Table state
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number>();
  const { density, setDensity } = useTableDensity();

  // Latest sort/cursor readable inside `loadUsers` WITHOUT adding them to its
  // dependency list (which would otherwise re-run the mount effect and reset the
  // page on every sort/page change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadUsers = useCallback(
    async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
      setLoading(true);
      setError(null);
      try {
        const nextSort = opts?.sort ?? sortRef.current;
        const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
        const result = await fetchAdminUsers(authToken, {
          role: roleFilter || undefined,
          search: search || undefined,
          limit: PAGE_LIMIT,
          cursor: nextCursor,
          sort: nextSort.column,
          order: nextSort.order,
        });
        setUsers(result.users);
        setHasMore(result.hasMore);
        setCursor(nextCursor);
        if (result.total !== undefined) setTotal(result.total);
      } catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Daftar pengguna tidak dapat dimuat.');
      } finally {
        setLoading(false);
      }
    },
    [roleFilter, search],
  );

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) loadUsers(stored, { resetCursor: true });
  }, [loadUsers]);

  useDummyRefresh(() => { if (token) void loadUsers(token, { resetCursor: true }); });

  function handleFilterApply() {
    if (!token) return;
    loadUsers(token, { resetCursor: true });
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

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token)
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Kelola pengguna" description="Kelola pengguna dan tetapkan peran mereka." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk mengelola pengguna.
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadUsers(nextToken, { resetCursor: true }); }} />
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
      key: 'created_at',
      header: 'Dibuat',
      render: (u: AdminUser) => <span className="text-xs text-gray-600">{formatDateTime(u.created_at ?? null)}</span>,
    },
    {
      key: 'updated_at',
      header: 'Diperbarui',
      render: (u: AdminUser) => <span className="text-xs text-gray-600">{formatDateTime(u.updated_at ?? null)}</span>,
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
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
          <TableSummary total={total ?? users.length} noun="pengguna" />
          <TableDensityToggle value={density} onChange={setDensity} />
        </div>
        {loading ? (
          <p className="p-8 text-sm text-gray-500">Memuat pengguna...</p>
        ) : (
          <Table
            columns={columns}
            rows={users}
            rowKey={(u) => u.id}
            density={density}
            empty={<EmptyState icon={<span>👤</span>} title="Belum ada pengguna" description="Pengguna baru akan muncul di sini." />}
          />
        )}
        <TablePagination
          cursor={cursor}
          limit={PAGE_LIMIT}
          total={total}
          hasMore={hasMore}
          onPageChange={(nextCursor) => {
            setCursor(nextCursor);
            if (token) void loadUsers(token, { cursor: nextCursor });
          }}
        />
      </Card>
    </div>
  );
}
