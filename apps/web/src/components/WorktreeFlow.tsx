'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import StatusBadge from '@/components/ui/StatusBadge';

type Stage = {
  id: string;
  label: string;
  status: string;
  icon: string;
  description: string;
  roles: string[];
  deepLink: string;
  accessRoles: string[];
};

type TerminalNode = {
  id: string;
  label: string;
  icon: string;
  sources: string[];
};

const STAGES: Stage[] = [
  {
    id: 'order',
    label: 'Pemesanan',
    status: 'New',
    icon: '🛒',
    description: 'Outlet memilih produk dan mengirim pesanan',
    roles: ['outlet', 'sales'],
    deepLink: '/orders',
    accessRoles: ['outlet', 'sales', 'admin', 'platform_owner'],
  },
  {
    id: 'approval',
    label: 'Persetujuan',
    status: 'Confirmed',
    icon: '✅',
    description: 'Admin meninjau dan menyetujui pesanan',
    roles: ['admin'],
    deepLink: '/admin/orders',
    accessRoles: ['admin', 'platform_owner'],
  },
  {
    id: 'assignment',
    label: 'Penugasan',
    status: 'Assigned',
    icon: '📋',
    description: 'Admin/sales menugaskan driver untuk pengiriman',
    roles: ['admin', 'sales'],
    deepLink: '/delivery',
    accessRoles: ['admin', 'sales', 'driver', 'platform_owner'],
  },
  {
    id: 'delivery',
    label: 'Pengiriman',
    status: 'Delivered',
    icon: '🚚',
    description: 'Driver mengantar pesanan ke outlet',
    roles: ['driver'],
    deepLink: '/delivery',
    accessRoles: ['admin', 'sales', 'driver', 'platform_owner'],
  },
  {
    id: 'payment',
    label: 'Pembayaran',
    status: 'Paid',
    icon: '💳',
    description: 'Outlet membayar pesanan (bisa bertahap)',
    roles: ['finance', 'admin', 'outlet'],
    deepLink: '/payments',
    accessRoles: ['admin', 'finance', 'platform_owner'],
  },
  {
    id: 'done',
    label: 'Selesai',
    status: 'Completed',
    icon: '🎉',
    description: 'Pesanan selesai diproses',
    roles: [],
    deepLink: '/orders',
    accessRoles: ['outlet', 'sales', 'admin', 'platform_owner'],
  },
];

const TERMINALS: TerminalNode[] = [
  { id: 'cancelled', label: 'Dibatalkan', icon: '🚫', sources: ['order', 'approval', 'payment'] },
  { id: 'failed', label: 'Gagal', icon: '❌', sources: ['delivery'] },
];

const ROLE_HIGHLIGHTS: Record<string, string[]> = {
  outlet: ['order'],
  sales: ['order', 'assignment'],
  driver: ['delivery'],
  admin: ['approval', 'assignment', 'payment'],
  finance: ['payment'],
  supplier: [],
  platform_owner: [],
};

function getHighlightClass(role: string | null | undefined, stageId: string): string {
  const highlighted = ROLE_HIGHLIGHTS[role ?? ''] ?? [];
  const hasAny = highlighted.length > 0;
  if (!hasAny) return 'border-gray-200 bg-white';
  if (highlighted.includes(stageId)) return 'border-solid border-primary-200 bg-primary-50 shadow-sm';
  return 'border-dashed border-gray-200 bg-gray-50 opacity-60';
}

export default function WorktreeFlow({ role }: { role?: string | null }) {
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const selected = STAGES.find((s) => s.id === selectedId) ?? null;
  const canAccess = selected ? selected.accessRoles.includes(role ?? '') : false;

  useEffect(() => {
    if (!selectedId) return;
    const handler = (e: MouseEvent) => {
      const panel = document.querySelector('[data-testid="worktree-panel"]');
      const target = e.target as Node;
      if (!panel) return;
      if (panel.contains(target)) return;
      // Don't close if clicking a worktree node — let the node's onClick handle toggle/switch
      const el = target as Element;
      if (el.closest && el.closest('[data-testid^="worktree-node-"]')) return;
      setSelectedId(null);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [selectedId]);

  return (
    <div data-testid="worktree-container" className="flex flex-col lg:flex-row gap-6">
      {/* Main flow column */}
      <div className="flex flex-col items-center flex-1">
        {/* Main vertical flow */}
        <div className="flex flex-col items-center w-full max-w-md">
          {STAGES.map((stage, idx) => {
            const hlClass = getHighlightClass(role, stage.id);
            return (
              <div key={stage.id} className={`flex flex-col items-center w-full rounded border ${hlClass}`}>
                <Card className={`w-full rounded border p-4 cursor-pointer ${hlClass}`}>
                  <div
                    data-testid={`worktree-node-${stage.id}`}
                    onClick={() => setSelectedId((prev) => (prev === stage.id ? null : stage.id))}
                  >
                    <div className="flex items-center gap-3">
                      <span className="text-2xl" aria-hidden>
                        {stage.icon}
                      </span>
                      <h3 className="text-base font-semibold text-gray-900">{stage.label}</h3>
                      <StatusBadge status={stage.status} />
                    </div>
                    <p className="mt-2 text-sm text-gray-500">{stage.description}</p>
                  </div>
                </Card>
                {idx < STAGES.length - 1 && (
                  <div
                    className="h-8 w-0 border-l-2 border-gray-300 mx-auto"
                    aria-hidden="true"
                    data-testid={`connector-${stage.id}`}
                  />
                )}
              </div>
            );
          })}
        </div>

        {/* Separator before branch terminals */}
        <div className="my-6 w-full max-w-md border-t border-gray-200" aria-hidden="true" />

        {/* Branch terminal section */}
        <div className="flex flex-col items-center w-full max-w-md gap-4">
          {TERMINALS.map((terminal) => (
            <div key={terminal.id} className="flex flex-col items-center w-full">
              <div
                className="h-8 w-0 border-l-2 border-dashed border-red-300 mx-auto"
                aria-hidden="true"
                data-testid={`terminal-connector-${terminal.id}`}
                data-sources={terminal.sources.join(',')}
              />
              <Card className="w-full rounded border border-dashed border-red-300 bg-white p-4">
                <div className="flex items-center gap-3">
                  <span className="text-2xl" aria-hidden>
                    {terminal.icon}
                  </span>
                  <h3 className="text-base font-semibold text-gray-900">{terminal.label}</h3>
                </div>
              </Card>
            </div>
          ))}
        </div>
      </div>

      {/* Detail panel */}
      {selected && (
        <div data-testid="worktree-panel" className="lg:w-80 shrink-0 w-full">
          <Card className="p-4">
            <div className="flex items-center gap-3">
              <span className="text-2xl" aria-hidden>
                {selected.icon}
              </span>
              <h4 className="text-base font-semibold text-gray-900">{selected.label}</h4>
              <StatusBadge status={selected.status} />
            </div>
            <p className="mt-2 text-sm text-gray-500">{selected.description}</p>
            {selected.roles.length > 0 && (
              <div className="mt-3 flex flex-wrap gap-2">
                {selected.roles.map((r) => (
                  <Badge key={r} variant="blue">
                    {r}
                  </Badge>
                ))}
              </div>
            )}
            {canAccess && (
              <Link
                href={selected.deepLink}
                className="mt-4 inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
              >
                Buka menu
              </Link>
            )}
          </Card>
        </div>
      )}
    </div>
  );
}
