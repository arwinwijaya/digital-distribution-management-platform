import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { assignDummyUserRole } from '@/dummy/mutations';

// ── Dummy-mode helpers ──────────────────────────────────────────────────────
const DUMMY_USERS: AdminUser[] = [
  { id: 1, name: 'Admin JABODETABEK', email: 'admin@ddp.local', role: 'admin', created_at: '2026-01-01T10:00:00+07:00' },
  { id: 2, name: 'Budi Sales', email: 'budi.sales@ddp.local', role: 'sales', created_at: '2026-01-05T10:00:00+07:00' },
  { id: 3, name: 'Citra Finance', email: 'citra.finance@ddp.local', role: 'finance', created_at: '2026-01-05T10:00:00+07:00' },
  { id: 4, name: 'Agus Driver', email: 'agus.driver@ddp.local', role: 'driver', created_at: '2026-01-06T10:00:00+07:00' },
  { id: 5, name: 'Siti Supplier', email: 'siti.supplier@ddp.local', role: 'supplier', created_at: '2026-01-07T10:00:00+07:00' },
  { id: 6, name: 'Hendra Platform', email: 'hendra.platform@ddp.local', role: 'platform_owner', created_at: '2026-01-07T10:00:00+07:00' },
  { id: 7, name: 'Rina Outlet', email: 'rina.outlet@ddp.local', role: 'outlet', created_at: '2026-01-08T10:00:00+07:00' },
  { id: 8, name: 'Eko Sales 2', email: 'eko.sales@ddp.local', role: 'sales', created_at: '2026-01-09T10:00:00+07:00' },
];

function listDummyUsers(params: UsersListParams): UsersListResult {
  const filtered = params.role ? DUMMY_USERS.filter((u) => u.role === params.role) : DUMMY_USERS;
  const limit = params.limit ?? 20;
  return { users: filtered.slice(0, limit), hasMore: filtered.length > limit, limit };
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: string;
  created_at?: string;
}

export interface UsersListParams {
  role?: string;
  limit?: number;
}

export interface UsersListResult {
  users: AdminUser[];
  hasMore: boolean;
  limit: number;
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
  query.set('limit', String(params.limit ?? 20));
  const response = await fetch(apiUrl(`/admin/users?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar pengguna tidak dapat dimuat.'));
  const users: AdminUser[] = Array.isArray(data.data) ? data.data : [];
  return { users, hasMore: Boolean(data.meta?.has_more), limit: Number(data.meta?.limit ?? params.limit ?? 20) };
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
