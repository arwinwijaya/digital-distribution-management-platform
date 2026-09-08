'use client';

import { useState, FormEvent } from 'react';
import { apiUrl, storeToken } from '@/lib/api';

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

export default function OutletForm() {
  const [formData, setFormData] = useState<OutletFormData>({ name: '', email: '', password: '', password_confirmation: '', phone: '', address: '', city: '', district: '' });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

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
        throw new Error(validation || data.message || 'Registration failed.');
      }
      storeToken(data.data.token);
      setSuccess(true);
      setFormData({ name: '', email: '', password: '', password_confirmation: '', phone: '', address: '', city: '', district: '' });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Network error. Please try again.');
    } finally { setLoading(false); }
  };

  const input = (key: keyof OutletFormData, label: string, type = 'text') => (
    <label className="block text-sm font-medium text-gray-700">{label}
      <input required type={type} value={formData[key]} onChange={(e) => setFormData({ ...formData, [key]: e.target.value })} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2" />
    </label>
  );

  return <div className="mx-auto max-w-md p-6">
    <h2 className="mb-2 text-2xl font-bold">Register outlet account</h2>
    <p className="mb-6 text-sm text-gray-600">Your account and outlet are securely associated automatically.</p>
    {error && <div role="alert" className="mb-4 rounded border border-red-400 bg-red-100 p-3 text-red-700">{error}</div>}
    {success && <div className="mb-4 rounded border border-green-400 bg-green-100 p-3 text-green-700">Registered and signed in. You can place an order from the Orders page.</div>}
    <form onSubmit={handleSubmit} className="space-y-4">
      {input('name', 'Outlet name')}{input('email', 'Email', 'email')}{input('password', 'Password', 'password')}{input('password_confirmation', 'Confirm password', 'password')}
      {input('phone', 'Phone number')}{input('address', 'Address')}
      <div className="grid grid-cols-2 gap-4">{input('city', 'City')}{input('district', 'District')}</div>
      <button disabled={loading} className="w-full rounded-md bg-blue-600 px-4 py-2 text-white disabled:opacity-50">{loading ? 'Registering...' : 'Register outlet'}</button>
    </form>
  </div>;
}
