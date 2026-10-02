'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { Button, Card, Input, PageHeader, Textarea } from '@/components/ui';
import {
  assertInvoiceLogoSize,
  getAdminInvoiceTemplate,
  templateLogoUrl,
  updateAdminInvoiceTemplate,
  type InvoiceTemplate,
} from '@/app/invoices/api';

const EMPTY: InvoiceTemplate = {
  id: 1,
  logo_path: null,
  company_name: '',
  address: '',
  npwp: '',
  primary_color: '#0F172A',
  footer_text: '',
  notes: '',
  signer_name: '',
  signer_title: '',
  show_npwp: true,
  show_outlet_phone: true,
};

export default function AdminInvoiceTemplatePage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [template, setTemplate] = useState<InvoiceTemplate | null>(null);
  const [form, setForm] = useState<InvoiceTemplate>(EMPTY);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [logoError, setLogoError] = useState<string | null>(null);
  const previewRef = useRef<string | null>(null);

  const load = useCallback(async (authToken: string) => {
    setLoading(true);
    setError(null);
    try {
      const data = await getAdminInvoiceTemplate(authToken);
      setTemplate(data);
      setForm({
        id: data.id,
        logo_path: data.logo_path ?? null,
        company_name: data.company_name ?? '',
        address: data.address ?? '',
        npwp: data.npwp ?? '',
        primary_color: data.primary_color ?? '#0F172A',
        footer_text: data.footer_text ?? '',
        notes: data.notes ?? '',
        signer_name: data.signer_name ?? '',
        signer_title: data.signer_title ?? '',
        show_npwp: Boolean(data.show_npwp),
        show_outlet_phone: Boolean(data.show_outlet_phone),
      });
      if (data.logo_path) {
        setLogoPreview(data.logo_path);
        previewRef.current = data.logo_path;
      }
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Template tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) load(stored);
  }, [load]);

  function handleLogoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null;
    setLogoError(null);
    if (!file) {
      setLogoFile(null);
      const prev = previewRef.current;
      if (prev && prev.startsWith('blob:')) URL.revokeObjectURL(prev);
      previewRef.current = null;
      setLogoPreview(template?.logo_path ?? null);
      return;
    }
    try {
      assertInvoiceLogoSize(file);
    } catch (reason) {
      setLogoError(reason instanceof Error ? reason.message : 'Ukuran logo maksimal 2 MB.');
      setLogoFile(null);
      // Clear any inflated preview so the "no preview on error" assertion holds.
      const prev = previewRef.current;
      if (prev && prev.startsWith('blob:')) URL.revokeObjectURL(prev);
      previewRef.current = null;
      setLogoPreview(null);
      return;
    }
    setLogoFile(file);
    const preview = URL.createObjectURL(file);
    const prev = previewRef.current;
    if (prev && prev.startsWith('blob:')) URL.revokeObjectURL(prev);
    previewRef.current = preview;
    setLogoPreview(preview);
  }

  async function handleSave() {
    if (!token) return;
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const payload = {
        company_name: form.company_name.trim(),
        address: form.address.trim(),
        npwp: form.npwp.trim(),
        primary_color: form.primary_color || '#0F172A',
        footer_text: (form.footer_text ?? '').trim() || null,
        notes: (form.notes ?? '').trim() || null,
        signer_name: (form.signer_name ?? '').trim() || null,
        signer_title: (form.signer_title ?? '').trim() || null,
        show_npwp: Boolean(form.show_npwp),
        show_outlet_phone: Boolean(form.show_outlet_phone),
      };
      const updated = await updateAdminInvoiceTemplate(token, payload, logoFile);
      setTemplate(updated);
      setSuccess('Template berhasil disimpan.');
      // On success, show the persisted logo_path if present.
      if (updated.logo_path) {
        setLogoPreview(updated.logo_path);
        previewRef.current = updated.logo_path;
      }
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Template tidak dapat disimpan.');
    } finally {
      setSaving(false);
    }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;

  if (!token) {
    return (
      <div className="mx-auto max-w-3xl">
        <PageHeader
          title="Template faktur"
          description="Kelola template korporat untuk faktur (branding, footer, penanda tangan, dan kontrol tampil)."
        />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk mengelola template faktur.
        </div>
        <LoginForm
          expectedRole="admin"
          onLogin={(nextToken) => {
            setToken(nextToken);
            load(nextToken);
          }}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl">
      <PageHeader
        title="Template faktur"
        description="Kelola template korporat untuk faktur — logo, detail perusahaan, warna, footer, penanda tangan, dan kontrol tampil."
      />
      {error && (
        <p role="alert" className="mb-4 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {error}
        </p>
      )}
      {success && (
        <p className="mb-4 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{success}</p>
      )}
      {loading ? (
        <p className="text-sm text-gray-500">Memuat template...</p>
      ) : (
        <div className="space-y-5">
          <Card className="p-5">
            <h2 className="text-sm font-semibold text-gray-900">Detail perusahaan</h2>
            <div className="mt-4 space-y-4">
              <Input
                label="Nama perusahaan"
                value={form.company_name}
                onChange={(e) => setForm((f) => ({ ...f, company_name: e.target.value }))}
              />
              <Input
                label="Alamat"
                value={form.address}
                onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
              />
              <Input
                label="NPWP"
                id="input-npwp"
                value={form.npwp}
                onChange={(e) => setForm((f) => ({ ...f, npwp: e.target.value }))}
              />
              <div>
                <label htmlFor="input-logo" className="block text-sm font-medium text-gray-700 mb-1">
                  Logo
                </label>
                <input
                  id="input-logo"
                  aria-label="Logo"
                  type="file"
                  accept="image/jpeg,image/png,image/svg+xml"
                  onChange={handleLogoChange}
                  className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm"
                />
                {logoError && <p className="mt-1 text-xs text-danger-600">{logoError}</p>}
                <p className="mt-1 text-xs text-gray-500">Format JPG/PNG/SVG, maksimal 2 MB.</p>
                {logoPreview && (
                  <img
                    src={templateLogoUrl(logoPreview) ?? undefined}
                    alt="Pratinjau logo"
                    className="mt-3 max-h-28 rounded-lg border border-gray-200 object-contain"
                  />
                )}
              </div>
            </div>
          </Card>

          <Card className="p-5">
            <h2 className="text-sm font-semibold text-gray-900">Warna &amp; tampilan</h2>
            <div className="mt-4">
              <Input
                label="Warna utama"
                type="color"
                value={form.primary_color || '#0F172A'}
                onChange={(e) => setForm((f) => ({ ...f, primary_color: e.target.value }))}
              />
            </div>
          </Card>

          <Card className="p-5">
            <h2 className="text-sm font-semibold text-gray-900">Footer &amp; catatan</h2>
            <div className="mt-4 space-y-4">
              <Textarea
                label="Footer"
                value={form.footer_text ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, footer_text: e.target.value }))}
              />
              <Textarea
                label="Catatan"
                value={form.notes ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
              />
            </div>
          </Card>

          <Card className="p-5">
            <h2 className="text-sm font-semibold text-gray-900">Penanda tangan</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <Input
                label="Nama penanda tangan"
                value={form.signer_name ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, signer_name: e.target.value }))}
              />
              <Input
                label="Jabatan penanda tangan"
                value={form.signer_title ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, signer_title: e.target.value }))}
              />
            </div>
          </Card>

          <Card className="p-5">
            <h2 className="text-sm font-semibold text-gray-900">Tampilkan di faktur</h2>
            <div className="mt-4 space-y-3">
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={Boolean(form.show_npwp)}
                  onChange={(e) => setForm((f) => ({ ...f, show_npwp: e.target.checked }))}
                  className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                Tampilkan NPWP
              </label>
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={Boolean(form.show_outlet_phone)}
                  onChange={(e) => setForm((f) => ({ ...f, show_outlet_phone: e.target.checked }))}
                  className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                Tampilkan telepon outlet
              </label>
            </div>
          </Card>

          <div className="flex justify-end">
            <Button onClick={handleSave} disabled={saving}>
              {saving ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
