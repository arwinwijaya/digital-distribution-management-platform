import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { JABODETABEK_TERRITORIES } from '@/dummy/seed';
import { compareRows, paginate } from '@/lib/admin-table';

// ── Driver profile shapes (match backend DriverProfile + relations) ───────────
interface DummyDriverProfile {
  id: string;
  user_id: string;
  vehicle_type: string | null;
  plate_number: string | null;
  capacity_kg: number | null;
  service_territory_id: string | null;
  shift_start: string | null;
  shift_end: string | null;
  is_available: boolean;
  user?: {
    id: string;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
  };
  serviceTerritory?: {
    id: string;
    name: string;
    code: string;
  };
}

interface DummyUser {
  id: string;
  name: string;
  email: string;
  role: string;
  is_active: boolean;
}

function dummyState(): DummyDriverProfile[] {
  const entities = useDummyStore.getState().dummyEntities as
    | Partial<{ driverProfiles: DummyDriverProfile[] }>
    | null;
  return entities?.driverProfiles ?? [];
}

function territoryById(territoryId: string): { id: number; name: string } | undefined {
  const idx = JABODETABEK_TERRITORIES.findIndex((t) => t.id === territoryId);
  if (idx < 0) return undefined;
  return { id: idx + 1, name: JABODETABEK_TERRITORIES[idx].name };
}

function numericDriverId(id: string): number {
  const n = Number(String(id).replace(/^dummy-driver-/, ''));
  return Number.isFinite(n) ? n : 0;
}

function toAdminDriver(entity: DummyDriverProfile, index: number): AdminDriver {
  const territory = entity.service_territory_id
    ? territoryById(entity.service_territory_id)
    : undefined;
  return {
    id: numericDriverId(entity.id),
    name: entity.user?.name ?? `Driver ${index + 1}`,
    email: entity.user?.email ?? `driver${index + 1}@example.com`,
    vehicle_type: entity.vehicle_type ?? null,
    plate_number: entity.plate_number ?? null,
    capacity_kg: entity.capacity_kg ?? null,
    service_territory_id: territory?.id ?? null,
    territory: territory ?? null,
    shift_start: entity.shift_start ?? null,
    shift_end: entity.shift_end ?? null,
    is_available: entity.is_available ?? true,
    created_at: entity.user?.id
      ? new Date(Date.UTC(2026, 0, 31) - index * 86_400_000).toISOString()
      : null,
    updated_at: entity.user?.id
      ? new Date(Date.UTC(2026, 1, 10) - index * 43_200_000).toISOString()
      : null,
  };
}

function listDummyDrivers(filters: DriverFilters): DriversListResult {
  let drivers = resolveDummyDrivers();

  if (filters.search) {
    const q = filters.search.toLowerCase();
    drivers = drivers.filter(
      (d) =>
        d.name.toLowerCase().includes(q) ||
        d.plate_number?.toLowerCase().includes(q) ||
        d.vehicle_type?.toLowerCase().includes(q),
    );
  }
  if (filters.is_available !== undefined && filters.is_available !== '') {
    const want = filters.is_available === 'true';
    drivers = drivers.filter((d) => Boolean(d.is_available) === want);
  }
  if (filters.service_territory_id) {
    drivers = drivers.filter(
      (d) => String(d.service_territory_id) === String(filters.service_territory_id),
    );
  }

  // Sort using compareRows (default: created_at DESC, fallback to id DESC)
  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  drivers = [...drivers].sort((a, b) =>
    compareRows(
      a as unknown as Record<string, unknown>,
      b as unknown as Record<string, unknown>,
      sortCol,
      sortOrder,
    ),
  );

  // Compute summary
  const available = drivers.filter((d) => d.is_available).length;
  const unavailable = drivers.filter((d) => !d.is_available).length;

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(drivers, cursor, limit);
  return {
    drivers: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total: paginated.total,
    summary: { available, unavailable },
  };
}

function resolveDummyDrivers(): AdminDriver[] {
  const profiles = dummyState();
  return profiles.map((p, i) => toAdminDriver(p, i));
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

export interface AdminDriver {
  id: number;
  name: string;
  email: string;
  vehicle_type: string | null;
  plate_number: string | null;
  capacity_kg: number | null;
  service_territory_id: number | null;
  territory: { id: number; name: string } | null;
  shift_start: string | null;
  shift_end: string | null;
  is_available: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface DriverFilters {
  search?: string;
  is_available?: string;
  service_territory_id?: string;
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
}

export interface DriversListResult {
  drivers: AdminDriver[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { available: number; unavailable: number };
}

export interface DriverInput {
  user_id: string;
  vehicle_type?: string | null;
  plate_number?: string | null;
  capacity_kg?: number | null;
  service_territory_id?: number | null;
  shift_start?: string | null;
  shift_end?: string | null;
  is_available?: boolean;
}

export async function fetchAdminDrivers(
  token: string,
  filters: DriverFilters = {},
): Promise<DriversListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyDrivers(filters),
    () => fetchAdminDriversReal(token, filters),
  );
}

async function fetchAdminDriversReal(
  token: string,
  filters: DriverFilters,
): Promise<DriversListResult> {
  const query = new URLSearchParams();
  if (filters.search) query.set('search', filters.search);
  if (filters.is_available) query.set('is_available', filters.is_available);
  if (filters.service_territory_id) query.set('service_territory_id', filters.service_territory_id);
  query.set('limit', String(filters.limit ?? 15));
  if (filters.cursor !== undefined) query.set('cursor', String(filters.cursor));
  // Sort defaults: created_at DESC
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  const response = await fetch(apiUrl(`/admin/drivers?${query.toString()}`), {
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar driver tidak dapat dimuat.'));
  const inner = data.data as
    | { data: AdminDriver[]; has_more: boolean; limit: number; cursor: number }
    | AdminDriver[];
  const drivers = Array.isArray(inner) ? inner : inner.data ?? [];
  const hasMore = Array.isArray(inner)
    ? Boolean(data.has_more ?? data.meta?.has_more)
    : Boolean(inner.has_more);
  const limit = Array.isArray(inner)
    ? Number(data.meta?.limit ?? 15)
    : Number(inner.limit ?? 15);
  const cursor = Array.isArray(inner)
    ? Number(data.meta?.cursor ?? 0)
    : Number(inner.cursor ?? 0);
  const total = data.meta?.total !== undefined ? Number(data.meta.total) : undefined;
  const summary = data.meta?.summary as
    | { available: number; unavailable: number }
    | undefined;
  return { drivers, hasMore, limit, cursor, total, summary };
}

export async function createDriver(token: string, payload: DriverInput): Promise<AdminDriver> {
  if (useDummyStore.getState().isDummy) {
    // In dummy mode, create a synthetic driver profile
    const profiles = dummyState();
    const newId = `dummy-driver-${profiles.length + 1}`;
    const newDriver: AdminDriver = {
      id: profiles.length + 1,
      name: `Driver ${profiles.length + 1}`,
      email: `driver${profiles.length + 1}@example.com`,
      vehicle_type: payload.vehicle_type ?? null,
      plate_number: payload.plate_number ?? null,
      capacity_kg: payload.capacity_kg ?? null,
      service_territory_id: payload.service_territory_id ?? null,
      territory: payload.service_territory_id
        ? territoryById(JABODETABEK_TERRITORIES[payload.service_territory_id - 1]?.id ?? '') ?? null
        : null,
      shift_start: payload.shift_start ?? null,
      shift_end: payload.shift_end ?? null,
      is_available: payload.is_available ?? true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    return newDriver;
  }
  const response = await fetch(apiUrl('/admin/drivers'), {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Driver tidak dapat dibuat.'));
  return (data.data ?? data) as AdminDriver;
}

export async function updateDriver(
  token: string,
  driverId: number,
  payload: Partial<DriverInput>,
): Promise<AdminDriver> {
  if (useDummyStore.getState().isDummy) {
    // In dummy mode, return a synthetic updated driver
    const updatedDriver: AdminDriver = {
      id: driverId,
      name: `Driver ${driverId}`,
      email: `driver${driverId}@example.com`,
      vehicle_type: payload.vehicle_type ?? null,
      plate_number: payload.plate_number ?? null,
      capacity_kg: payload.capacity_kg ?? null,
      service_territory_id: payload.service_territory_id ?? null,
      territory: payload.service_territory_id
        ? territoryById(JABODETABEK_TERRITORIES[payload.service_territory_id - 1]?.id ?? '') ?? null
        : null,
      shift_start: payload.shift_start ?? null,
      shift_end: payload.shift_end ?? null,
      is_available: payload.is_available ?? true,
      created_at: new Date(Date.UTC(2026, 0, 31) - (driverId - 1) * 86_400_000).toISOString(),
      updated_at: new Date().toISOString(),
    };
    return updatedDriver;
  }
  const response = await fetch(apiUrl(`/admin/drivers/${driverId}`), {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Driver tidak dapat diperbarui.'));
  return (data.data ?? data) as AdminDriver;
}

export async function deleteDriver(token: string, driverId: number): Promise<void> {
  if (useDummyStore.getState().isDummy) {
    return;
  }
  const response = await fetch(apiUrl(`/admin/drivers/${driverId}`), {
    method: 'DELETE',
    headers: authHeaders(token),
  });
  if (!response.ok) {
    const data = await response.json();
    throw new Error(parseError(data, 'Driver tidak dapat dihapus.'));
  }
}