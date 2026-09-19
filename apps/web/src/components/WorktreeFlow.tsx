'use client';

import Card from '@/components/ui/Card';
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

export default function WorktreeFlow() {
  return (
    <div className="flex flex-col items-center">
      {/* Main vertical flow */}
      <div className="flex flex-col items-center w-full max-w-md">
        {STAGES.map((stage, idx) => (
          <div key={stage.id} className="flex flex-col items-center w-full">
            <Card className="w-full rounded border border-gray-200 bg-white p-4">
              <div className="flex items-center gap-3">
                <span className="text-2xl" aria-hidden>
                  {stage.icon}
                </span>
                <h3 className="text-base font-semibold text-gray-900">{stage.label}</h3>
                <StatusBadge status={stage.status} />
              </div>
              <p className="mt-2 text-sm text-gray-500">{stage.description}</p>
            </Card>
            {idx < STAGES.length - 1 && (
              <div
                className="h-8 w-0 border-l-2 border-gray-300 mx-auto"
                aria-hidden="true"
                data-testid={`connector-${stage.id}`}
              />
            )}
          </div>
        ))}
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
  );
}
