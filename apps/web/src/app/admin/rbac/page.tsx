'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { getStoredToken } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';
import { useDummyRefresh } from '@/dummy/guards';
import { useRbacStore, type RbacCell } from '@/store/useRbacStore';
import { Button, Card, PageHeader } from '@/components/ui';
import { MENU_KEYS, MENU_LABELS, ROLES, LEVELS, fetchMe, loadMatrix, saveMatrix } from './api';
import type { RbacLevel } from '@/dummy/rbac';

type Draft = Record<string, RbacLevel>;

function cellId(role: string, key: string): string {
  return `${role}:${key}`;
}

/** Seed the draft from the loaded matrix (all 23×7 cells explicit). */
function draftFromMatrix(matrix: Record<string, Record<string, RbacLevel>> | null): Draft {
  const draft: Draft = {};
  for (const role of ROLES) {
    for (const key of MENU_KEYS) {
      draft[cellId(role, key)] = matrix?.[role]?.[key] ?? 'none';
    }
  }
  return draft;
}

export default function AdminRbacPage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [gateError, setGateError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [draft, setDraft] = useState<Draft>({});

  const map = useRbacStore((s) => s.map);
  const matrix = useRbacStore((s) => s.matrix);

  const allowed = (map?.['rbac_matrix'] ?? 'none') !== 'none';

  // Resolve token + hydrate the caller's map (dummy resolves offline).
  useEffect(() => {
    let active = true;
    const stored = getStoredToken();
    setToken(stored);
    if (!stored) {
      setReady(true);
      return;
    }
    if (useDummyStore.getState().isDummy) {
      useRbacStore.getState().hydrateFromMe(null, localStorage.getItem('ddp_role'));
      setReady(true);
      return;
    }
    fetchMe(stored)
      .then(({ role, rbac }) => {
        if (active) useRbacStore.getState().hydrateFromMe(rbac, role);
      })
      .finally(() => {
        if (active) setReady(true);
      });
    return () => {
      active = false;
    };
  }, []);

  // Gate: only roles that can read `rbac_matrix` may load the matrix.
  useEffect(() => {
    if (!ready || !token) return;
    if (!allowed) {
      setGateError('Anda tidak memiliki akses ke halaman Kelola Akses.');
      return;
    }
    setGateError(null);
    if (matrix) return;
    loadMatrix(token).catch((reason) =>
      setActionError(reason instanceof Error ? reason.message : 'Matriks RBAC tidak dapat dimuat.'),
    );
  }, [ready, token, allowed, matrix]);

  // Reflect server-side matrix changes into the editable draft.
  useEffect(() => {
    if (matrix) setDraft(draftFromMatrix(matrix));
  }, [matrix]);

  // Dummy toggle: reload from the dummy matrix (zero network).
  useDummyRefresh(() => {
    if (token) void loadMatrix(token);
  });

  const changedCells = useMemo<RbacCell[]>(() => {
    if (!matrix) return [];
    const cells: RbacCell[] = [];
    for (const role of ROLES) {
      for (const key of MENU_KEYS) {
        const level = draft[cellId(role, key)];
        const current = matrix[role]?.[key] ?? 'none';
        if (level && level !== current) cells.push({ role, menu_key: key, level });
      }
    }
    return cells;
  }, [draft, matrix]);

  const handleChange = useCallback((role: string, key: string, level: RbacLevel) => {
    setActionSuccess(null);
    setDraft((prev) => ({ ...prev, [cellId(role, key)]: level }));
  }, []);

  const handleSave = useCallback(async () => {
    if (!token) return;
    setActionError(null);
    setActionSuccess(null);
    if (changedCells.length === 0) {
      setActionSuccess('Tidak ada perubahan untuk disimpan.');
      return;
    }
    setSaving(true);
    try {
      await saveMatrix(token, changedCells);
      setActionSuccess('Matriks akses berhasil disimpan.');
    } catch (reason) {
      setActionError(reason instanceof Error ? reason.message : 'Matriks RBAC tidak dapat disimpan.');
    } finally {
      setSaving(false);
    }
  }, [token, changedCells]);

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;

  return (
    <div className="mx-auto max-w-7xl">
      <PageHeader
        title="Kelola Akses"
        description="Atur tingkat akses (none/read/edit) setiap peran untuk tiap menu."
        action={
          allowed ? (
            <Button onClick={handleSave} disabled={saving}>
              {saving ? 'Menyimpan...' : 'Simpan'}
            </Button>
          ) : undefined
        }
      />

      {gateError && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {gateError}
        </p>
      )}

      {allowed && (
        <>
          {actionError && (
            <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
              {actionError}
            </p>
          )}
          {actionSuccess && (
            <p className="mb-5 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">
              {actionSuccess}
            </p>
          )}

          {!matrix ? (
            <p className="text-sm text-gray-500">Memuat matriks...</p>
          ) : (
            <Card className="overflow-x-auto">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-gray-100 bg-gray-50 text-left">
                    <th className="sticky left-0 bg-gray-50 px-4 py-3 font-semibold text-gray-700">Menu</th>
                    {ROLES.map((role) => (
                      <th key={role} className="px-3 py-3 font-semibold text-gray-700">
                        {role}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {MENU_KEYS.map((key) => (
                    <tr key={key} data-testid={`rbac-row-${key}`} className="border-b border-gray-50">
                      <th
                        scope="row"
                        className="sticky left-0 bg-white px-4 py-2 text-left font-medium text-gray-800"
                      >
                        {MENU_LABELS[key] ?? key}
                        <span className="ml-2 text-xs text-gray-400">{key}</span>
                      </th>
                      {ROLES.map((role) => (
                        <td key={role} className="px-3 py-2">
                          <select
                            aria-label={`${role} ${key}`}
                            data-testid={`cell-${role}-${key}`}
                            value={draft[cellId(role, key)] ?? 'none'}
                            onChange={(event) => handleChange(role, key, event.target.value as RbacLevel)}
                            className="w-full rounded-lg border border-gray-200 bg-white px-2 py-1 text-sm"
                          >
                            {LEVELS.map((level) => (
                              <option key={level} value={level}>
                                {level}
                              </option>
                            ))}
                          </select>
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
