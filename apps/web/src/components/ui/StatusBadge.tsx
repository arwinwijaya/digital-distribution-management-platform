import { ReactNode } from 'react';

/** Peta status pesanan/pembayaran/pengiriman → label Indonesia + warna */
function statusMeta(raw: string): { label: string; variant: 'blue' | 'yellow' | 'green' | 'red' | 'gray' } {
  const map: Record<string, { label: string; variant: 'blue' | 'yellow' | 'green' | 'red' | 'gray' }> = {
    new:              { label: 'Baru',          variant: 'yellow' },
    pending:          { label: 'Menunggu',      variant: 'yellow' },
    confirmed:        { label: 'Dikonfirmasi',  variant: 'blue'   },
    assigned:         { label: 'Ditugaskan',    variant: 'blue'   },
    in_transit:       { label: 'Dalam Perjalanan', variant: 'blue' },
    in_progress:      { label: 'Sedang Diproses', variant: 'blue' },
    delivered:        { label: 'Terkirim',      variant: 'green'  },
    paid:             { label: 'Lunas',         variant: 'green'  },
    completed:        { label: 'Selesai',       variant: 'green'  },
    partial:          { label: 'Sebagian',      variant: 'yellow' },
    unpaid:           { label: 'Belum Bayar',   variant: 'red'    },
    cancelled:        { label: 'Dibatalkan',    variant: 'red'    },
    failed:           { label: 'Gagal',         variant: 'red'    },
    active:           { label: 'Aktif',         variant: 'green'  },
    inactive:         { label: 'Nonaktif',      variant: 'gray'   },
    approved:         { label: 'Disetujui',     variant: 'green'  },
    rejected:         { label: 'Ditolak',       variant: 'red'    },
  };
  const fallback: { label: string; variant: 'gray' } = { label: raw, variant: 'gray' };
  return map[raw.toLowerCase()] ?? fallback;
}

type Props = {
  status: string;
};

export default function StatusBadge({ status }: Props) {
  const { label, variant } = statusMeta(status);
  const styles: Record<string, string> = {
    blue:   'bg-primary-50 text-primary-700 border-primary-200',
    yellow: 'bg-warning-50 text-warning-700 border-warning-200',
    green:  'bg-success-50 text-success-700 border-success-200',
    red:    'bg-danger-50 text-danger-700 border-danger-200',
    gray:   'bg-gray-50 text-gray-700 border-gray-200',
  };
  return (
    <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize ${styles[variant]}`}>
      <span className="w-1.5 h-1.5 rounded-full bg-current" aria-hidden />
      {label}
    </span>
  );
}
