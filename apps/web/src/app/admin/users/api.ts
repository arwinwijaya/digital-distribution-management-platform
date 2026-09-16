import { apiUrl, authHeaders } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';
import { assignDummyUserRole } from '@/dummy/mutations';

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
