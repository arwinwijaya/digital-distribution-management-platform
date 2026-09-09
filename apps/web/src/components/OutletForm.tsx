'use client';

import { useState, FormEvent } from 'react';
import { apiUrl, storeToken } from '@/lib/api';
import { Button, Card, Input, PageHeader } from '@/components/ui';

interface OutletFormData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone: string;
  address: string;
  city: string;
  district: string;
}

const emptyForm: OutletFormData = { name: '', email: '', password: '', password_confirmation: '', phone: '', address: '', city: '', district: '' };

export default function OutletForm() {
  const [formData, setFormData] = useState<OutletFormData>(emptyForm);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const update = (key: keyof OutletFormData) => (event: React.ChangeEvent<HTMLInputElement>) => setFormData((current) => ({ ...current, [key]: event.target.value }));

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setLoading(true); setError(null); setSuccess(false);
    try {
      const response = await fetch(apiUrl('/auth/register'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(formData),
      });
      const data = await response.json();
      if (!response.ok) {
        const validation = data.errors ? Object.values(data.errors).flat().join(', ') : '';
        throw new Error(validation || data.message || 'Pendaftaran gagal.');
      }
      storeToken(data.data.token);
      setSuccess(true);
      setFormData(emptyForm);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Terjadi kesalahan jaringan.');
    } finally { setLoading(false); }
  };

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title="Daftarkan outlet" description="Buat akun outlet untuk mulai memesan produk dari jaringan distribusi." />
      <Card className="p-6 sm:p-8">
        {error && <div role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</div>}
        {success && <div className="mb-5 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">Pendaftaran berhasil. Anda sudah masuk dan dapat membuat pesanan.</div>}
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="grid gap-5 sm:grid-cols-2">
            <Input label="Nama outlet" required value={formData.name} onChange={update('name')} placeholder="Contoh: Warung Berkah" />
            <Input label="Nomor telepon" required value={formData.phone} onChange={update('phone')} placeholder="08xxxxxxxxxx" />
            <Input label="Email" required type="email" value={formData.email} onChange={update('email')} placeholder="outlet@email.com" />
            <Input label="Kata sandi" required type="password" value={formData.password} onChange={update('password')} />
            <Input label="Konfirmasi kata sandi" required type="password" value={formData.password_confirmation} onChange={update('password_confirmation')} />
            <Input label="Kota" required value={formData.city} onChange={update('city')} placeholder="Kota / Kabupaten" />
            <Input label="Kecamatan" required value={formData.district} onChange={update('district')} placeholder="Kecamatan" />
          </div>
          <Input label="Alamat lengkap" required value={formData.address} onChange={update('address')} placeholder="Nama jalan, nomor, patokan" />
          <div className="flex justify-end border-t border-gray-100 pt-5">
            <Button type="submit" disabled={loading}>{loading ? 'Mendaftarkan...' : 'Daftarkan outlet'}</Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
