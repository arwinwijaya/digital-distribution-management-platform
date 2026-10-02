'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { getInvoiceDetail, getAdminInvoiceTemplate } from '@/app/invoices/api';
import { useDummyRefresh } from '@/dummy/guards';
import { PageHeader } from '@/components/ui';
import InvoiceDetail from '@/app/invoices/components/InvoiceDetail';
import type { InvoiceDetail as InvoiceDetailType, InvoiceTemplate } from '@/app/invoices/api';

export default function InvoiceDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params?.id);

  const [token, setToken] = useState<string | null>(null);
  const [detail, setDetail] = useState<InvoiceDetailType | null>(null);
  const [template, setTemplate] = useState<InvoiceTemplate | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadDetail = async (nextToken: string) => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const detailData = await getInvoiceDetail(nextToken, id);
      setDetail(detailData);
      // Best-effort template fetch — ignore failures (RBAC may block non-admins).
      try {
        setTemplate(await getAdminInvoiceTemplate(nextToken));
      } catch {
        setTemplate(null);
      }
    } catch (reason) {
      setDetail(null);
      setError(reason instanceof Error ? reason.message : 'Detail invoice tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    if (stored && id) void loadDetail(stored);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  useDummyRefresh(() => {
    if (token && id) void loadDetail(token);
  });

  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Invoice" description="Detail invoice dan riwayat pembayaran." />
        <LoginForm
          expectedRole={['finance', 'admin', 'outlet']}
          onLogin={(nextToken) => {
            setToken(nextToken);
            if (id) void loadDetail(nextToken);
          }}
        />
      </div>
    );
  }

  if (!detail) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Detail invoice" description="Detail invoice dan riwayat pembayaran." />
        {error && (
          <p role="alert" className="mb-6 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
            {error}
          </p>
        )}
        {loading && <p className="text-sm text-gray-500">Memuat detail invoice...</p>}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Detail invoice" description={`Nomor: ${detail.invoice_number}`} />
      {error && (
        <p role="alert" className="mb-6 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {error}
        </p>
      )}
      <InvoiceDetail detail={detail} template={template} />
    </div>
  );
}