/**
 * useRbacStore — frontend source of truth for RBAC menu visibility.
 *
 * Holds the caller's role and their 23-key access map (level per menu). The
 * Sidebar filters on `levelFor(key) !== 'none'`; the `/admin/rbac` page reads
 * the full 7-role matrix via `loadFromServer` and writes cells via `save`.
 *
 * Dummy mode (Story 3): when `useDummyStore.isDummy` is true, everything is
 * resolved from `getDummyMatrix` with ZERO network — no `fetch` is ever issued.
 *
 * `levelFor` falls back to `none` for unknown keys and before hydration,
 * matching the backend rule (missing row → none → 403).
 */
import { create } from 'zustand';
import { useDummyStore } from '@/dummy/store';
import { getDummyMatrix, MENU_KEYS, ROLES, type MenuKey, type RbacLevel } from '@/dummy/rbac';
import { apiUrl, authHeaders } from '@/lib/api';

/** A role's access map: menu_key => level, all 23 keys explicit. */
export type RoleMap = Record<string, RbacLevel>;
/** Full matrix: role => RoleMap. */
export type RbacMatrix = Record<string, RoleMap>;

export interface RbacCell {
  role: string;
  menu_key: string;
  level: RbacLevel;
}

export interface RbacState {
  role: string | null;
  /** Caller's own 23-key map (from /auth/me or dummy). */
  map: RoleMap | null;
  /** Full 7-role matrix (admin page). Null until loaded. */
  matrix: RbacMatrix | null;

  hydrateFromMe: (rbac: Record<string, RbacLevel> | null | undefined, role: string | null) => void;
  loadFromServer: (token: string) => Promise<void>;
  save: (token: string, cells: RbacCell[]) => Promise<void>;

  levelFor: (menuKey: string) => RbacLevel;
  canRead: (menuKey: string) => boolean;
  canEdit: (menuKey: string) => boolean;
  reset: () => void;
}

function isDummy(): boolean {
  return useDummyStore.getState().isDummy;
}

/** Build the full 7-role matrix from the dummy fixture. */
function dummyFullMatrix(): RbacMatrix {
  const matrix: RbacMatrix = {};
  for (const role of ROLES) {
    matrix[role] = getDummyMatrix(role);
  }
  return matrix;
}

/** Normalise a partial map into a full 23-key map (missing → none). */
function normalizeMap(partial: Record<string, RbacLevel> | null | undefined): RoleMap {
  const map: RoleMap = {};
  for (const key of MENU_KEYS) {
    map[key] = partial?.[key] ?? 'none';
  }
  return map;
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && 'message' in data) {
    const message = (data as { message?: unknown }).message;
    if (typeof message === 'string') return message;
  }
  return fallback;
}

export const useRbacStore = create<RbacState>()((set, get) => ({
  role: null,
  map: null,
  matrix: null,

  hydrateFromMe: (rbac, role) => {
    if (isDummy()) {
      set({ role, map: getDummyMatrix(role) });
      return;
    }
    set({ role, map: normalizeMap(rbac) });
  },

  loadFromServer: async (token) => {
    if (isDummy()) {
      set({ matrix: dummyFullMatrix() });
      return;
    }

    const response = await fetch(apiUrl('/admin/rbac/matrix'), { headers: authHeaders(token) });
    const data = await response.json();
    if (!response.ok) throw new Error(parseError(data, 'Matriks RBAC tidak dapat dimuat.'));

    const raw = (data.data ?? {}) as Record<string, Record<string, RbacLevel>>;
    const matrix: RbacMatrix = {};
    for (const role of ROLES) {
      matrix[role] = normalizeMap(raw[role]);
    }
    set({ matrix });
  },

  save: async (token, cells) => {
    /** If the caller edited their OWN role's row, refresh their map so the
     *  Sidebar reacts immediately (otherwise it would only update on re-login). */
    const syncOwnMap = (matrix: RbacMatrix, currentRole: string | null) => {
      if (!currentRole || !matrix[currentRole]) return;
      const touched = cells.some((cell) => cell.role === currentRole);
      if (touched) set({ map: { ...matrix[currentRole] } });
    };

    if (isDummy()) {
      // Apply locally so the admin page reflects the edit without a network hop.
      const current = get().matrix ?? dummyFullMatrix();
      const next: RbacMatrix = {};
      for (const role of Object.keys(current)) next[role] = { ...current[role] };
      for (const cell of cells) {
        if (!next[cell.role]) next[cell.role] = normalizeMap(null);
        next[cell.role][cell.menu_key] = cell.level;
      }
      set({ matrix: next });
      syncOwnMap(next, get().role);
      return;
    }

    const response = await fetch(apiUrl('/admin/rbac/matrix'), {
      method: 'PUT',
      headers: authHeaders(token),
      body: JSON.stringify({ cells }),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(parseError(data, 'Matriks RBAC tidak dapat disimpan.'));

    const raw = (data.data ?? {}) as Record<string, Record<string, RbacLevel>>;
    const matrix: RbacMatrix = {};
    for (const role of ROLES) {
      matrix[role] = normalizeMap(raw[role]);
    }
    set({ matrix });
    syncOwnMap(matrix, get().role);
  },

  levelFor: (menuKey) => {
    const { map } = get();
    return map?.[menuKey as MenuKey] ?? 'none';
  },

  canRead: (menuKey) => get().levelFor(menuKey) !== 'none',

  canEdit: (menuKey) => get().levelFor(menuKey) === 'edit',

  reset: () => set({ role: null, map: null, matrix: null }),
}));
