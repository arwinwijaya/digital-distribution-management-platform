'use client';

import { useState } from 'react';
import { Button, Card, StatusBadge, Table } from '@/components/ui';
import { useRbacStore } from '@/store/useRbacStore';
import { downloadInvoicePdf } from '@/app/invoices/api';
import type { InvoiceDetail, InvoiceTemplate } from '@/app/invoices/api';

const money = (value: string | number | null | undefined) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
const date = (value: string | null) =>
  value ? new Date(value).toLocaleDateString('id-ID') : '-';

const lineColumns = [
  {
    key: 'product',
    header: 'Produk',
    render: (item: InvoiceDetail['line_items'][number]) => (
      <span className="font-medium">{item.product_name}</span>
    ),
  },
  {
    key: 'quantity',
    header: 'Jumlah',
    render: (item: InvoiceDetail['line_items'][number]) => <span>{item.quantity}</span>,
  },
  {
    key: 'unit_price',
    header: 'Harga satuan',
    render: (item: InvoiceDetail['line_items'][number]) => money(item.unit_price),
  },
  {
    key: 'subtotal',
    header: 'Subtotal',
    render: (item: InvoiceDetail['line_items'][number]) => (
      <span className="font-semibold">{money(item.subtotal)}</span>
    ),
  },
];

const paymentColumns = [
  {
    key: 'date',
    header: 'Tanggal',
    render: (payment: InvoiceDetail['payments'][number]) => date(payment.created_at),
  },
  {
    key: 'method',
    header: 'Metode',
    render: (payment: InvoiceDetail['payments'][number]) => payment.payment_method,
  },
  {
    key: 'amount',
    header: 'Jumlah',
    render: (payment: InvoiceDetail['payments'][number]) => (
      <span className="font-semibold">{money(payment.amount)}</span>
    ),
  },
  {
    key: 'status',
    header: 'Status',
    render: (payment: InvoiceDetail['payments'][number]) => <StatusBadge status={payment.status} />,
  },
];

const DEFAULT_PRIMARY_COLOR = '#0F172A';

export default function InvoiceDetail({
  detail,
  template,
}: {
  detail: InvoiceDetail;
  template?: InvoiceTemplate | null;
}) {
  const canRead = useRbacStore((state) => state.canRead('invoices'));
  const [downloading, setDownloading] = useState(false);
  const [downloadError, setDownloadError] = useState<string | null>(null);

  const overdue = detail.is_overdue || detail.overdue;
  // Mirror the Blade PDF template toggles: accent color comes from the active
  // template (`primary_color`), and the outlet phone is gated by
  // `show_outlet_phone`. When the template is absent (RBAC may block the
  // best-effort fetch), keep the pre-existing defaults.
  const templateColor = template?.primary_color || DEFAULT_PRIMARY_COLOR;
  const showOutletPhone = template ? template.show_outlet_phone : true;

  const handleExport = async () => {
    setDownloading(true);
    setDownloadError(null);
    try {
      const token = localStorage.getItem('ddp_token') ?? '';
      await downloadInvoicePdf(token, detail);
    } catch (error) {
      setDownloadError(error instanceof Error ? error.message : 'PDF invoice tidak dapat diunduh.');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <div className="mx-auto max-w-6xl">
      <Card className="mb-6 p-6">
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm text-gray-500">Nomor invoice</p>
            <h1 className="text-xl font-bold text-gray-900">{detail.invoice_number}</h1>
          </div>
          <div className="flex items-center gap-2">
            <StatusBadge status={detail.status} />
            {overdue && (
              <span className="inline-flex items-center gap-1 rounded-full border border-danger-200 bg-danger-50 px-2.5 py-0.5 text-xs font-semibold uppercase text-danger-700">
                OVERDUE
              </span>
            )}
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <div>
            <p className="text-sm text-gray-500">Tanggal terbit</p>
            <p className="font-medium text-gray-900">{date(detail.issue_date)}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">Jatuh tempo</p>
            <p className="font-medium text-gray-900">{date(detail.due_date)}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">Nomor pesanan</p>
            <p className="font-medium text-gray-900">#{detail.order_id}</p>
          </div>
        </div>

        <div className="mt-4 grid gap-4 sm:grid-cols-3">
          <div>
            <p className="text-sm text-gray-500">Total</p>
            <p className="text-lg font-bold text-gray-900">{money(detail.total_amount)}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">Dibayar</p>
            <p className="text-lg font-semibold text-success-700">{money(detail.paid_amount)}</p>
          </div>
          <div>
            <p className="text-sm text-gray-500">Sisa</p>
            <p className="text-lg font-bold text-danger-700">{money(detail.balance_amount)}</p>
          </div>
        </div>

        <div className="mt-6 flex flex-wrap items-center gap-3">
          {canRead && (
            <Button onClick={handleExport} disabled={downloading}>
              {downloading ? 'Mengunduh...' : 'Export PDF'}
            </Button>
          )}
          {downloadError && (
            <p role="alert" className="text-sm text-danger-700">
              {downloadError}
            </p>
          )}
        </div>
      </Card>

      {detail.outlet && (
        <Card className="mb-6 p-6" aria-label="Informasi outlet">
          <div className="flex items-center gap-3" style={{ borderLeft: `4px solid ${templateColor}` }}>
            <div className="pl-3">
              <h2 className="text-base font-semibold text-gray-900">Informasi outlet</h2>
              <p className="mt-1 font-medium text-gray-900">{detail.outlet.name}</p>
              {detail.outlet.phone && showOutletPhone && <p className="text-sm text-gray-600">{detail.outlet.phone}</p>}
              {detail.outlet.address && <p className="text-sm text-gray-600">{detail.outlet.address}</p>}
              {detail.outlet.city && <p className="text-sm text-gray-600">{detail.outlet.city}</p>}
            </div>
          </div>
        </Card>
      )}

      <Card className="mb-6 overflow-hidden">
        <div className="border-b border-gray-100 px-5 py-4">
          <h2 className="font-semibold text-gray-900">Rincian barang</h2>
        </div>
        <Table
          columns={lineColumns}
          rows={detail.line_items}
          rowKey={(item) => item.id}
          empty={<p className="p-8 text-center text-sm text-gray-500">Belum ada rincian barang.</p>}
        />
      </Card>

      <Card className="overflow-hidden">
        <div className="border-b border-gray-100 px-5 py-4">
          <h2 className="font-semibold text-gray-900">Riwayat pembayaran</h2>
        </div>
        <Table
          columns={paymentColumns}
          rows={detail.payments}
          rowKey={(payment) => payment.id}
          empty={<p className="p-8 text-center text-sm text-gray-500">Belum ada pembayaran.</p>}
        />
      </Card>
    </div>
  );
}
