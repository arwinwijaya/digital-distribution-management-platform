import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { assignDummyUserRole } from '@/dummy/mutations';
import { compareRows, paginate } from '@/lib/admin-table';

// ── Dummy-mode helpers ──────────────────────────────────────────────────────
// Mirrors apps/api/database/seeders/DatabaseSeeder.php (7 roles, one per email).
const DUMMY_USERS: AdminUser[] = [
  { id: 1, name: 'Platform Owner', email: 'platform_owner@ddp.test', role: 'platform_owner', created_at: '2026-01-01T10:00:00+07:00' },
  { id: 2, name: 'Admin', email: 'admin@ddp.test', role: 'admin', created_at: '2026-01-01T10:00:00+07:00' },
  { id: 3, name: 'Test Outlet', email: 'outlet@ddp.test', role: 'outlet', created_at: '2026-01-02T10:00:00+07:00' },
  { id: 4, name: 'Test Supplier', email: 'supplier@ddp.test', role: 'supplier', created_at: '2026-01-02T10:00:00+07:00' },
  { id: 5, name: 'Test Sales', email: 'sales@ddp.test', role: 'sales', created_at: '2026-01-03T10:00:00+07:00' },
  { id: 6, name: 'Test Driver', email: 'driver@ddp.test', role: 'driver', created_at: '2026-01-03T10:00:00+07:00' },
  { id: 7, name: 'Test Finance', email: 'finance@ddp.test', role: 'finance', created_at: '2026-01-04T10:00:00+07:00' },
];

/**
 * Dummy parity for the admin users table: same sort/pagination/summary contract
 * as the real `GET /admin/users` list. Default `created_at DESC` with the
 * shared `compareRows` comparator (id DESC fallback for equal/missing dates).
 */
function listDummyUsers(params: UsersListParams): UsersListResult {
  let list = DUMMY_USERS;
  if (params.role) list = list.filter((u) => u.role === params.role);
  if (params.search) {
    const q = params.search.toLowerCase();
    list = list.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
  }

  const total = list.length;

  const sortCol = params.sort || 'created_at';
  const sortOrder = params.order === 'asc' ? 'asc' : 'desc';
  list = [...list].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
  );

  const limit = params.limit ?? 20;
  const cursor = params.cursor ?? 0;
  const paginated = paginate(list, cursor, limit);
  return {
    users: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total,
    summary: { total },
  };
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: string;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface UsersListParams {
  role?: string;
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
  search?: string;
}

export interface UsersListResult {
  users: AdminUser[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { total: number };
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

export async function fetchAdminUsers(token: string, params: UsersListParams = {}): Promise<UsersListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyUsers(params),
    () => fetchAdminUsersReal(token, params),
  );
}

async function fetchAdminUsersReal(token: string, params: UsersListParams): Promise<UsersListResult> {
  const query = new URLSearchParams();
  if (params.role) query.set('role', params.role);
  if (params.search) query.set('search', params.search);
  query.set('limit', String(params.limit ?? 20));
  query.set('cursor', String(params.cursor ?? 0));
  // Sort defaults: created_at DESC.
  query.set('sort', params.sort || 'created_at');
  query.set('order', params.order || 'desc');
  const response = await fetch(apiUrl(`/admin/users?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar pengguna tidak dapat dimuat.'));
  const users: AdminUser[] = Array.isArray(data.data) ? data.data : [];
  const meta = (data.meta ?? {}) as { has_more?: boolean; limit?: number; cursor?: number; total?: number; summary?: { total: number } };
  return {
    users,
    hasMore: Boolean(meta.has_more),
    limit: Number(meta.limit ?? params.limit ?? 20),
    cursor: Number(meta.cursor ?? params.cursor ?? 0),
    total: meta.total !== undefined ? Number(meta.total) : undefined,
    summary: meta.summary,
  };
}

export async function assignUserRole(token: string, userId: number, role: string): Promise<AdminUser> {
  if (useDummyStore.getState().isDummy) {
    return assignDummyUserRole(userId, role) as unknown as AdminUser;
  }
  const response = await fetch(apiUrl(`/admin/users/${userId}/role`), {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify({ role }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Peran pengguna tidak dapat diubah.'));
  return (data.data?.user ?? data.data) as AdminUser;
}
