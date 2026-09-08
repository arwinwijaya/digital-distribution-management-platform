'use client';

import { FormEvent, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

type Visit = { id: number; target: string | null; visit_date: string; status: string; notes: string | null };

export default function SalesPage() {
  const [token, setToken] = useState<string | null>(null);
  const [visits, setVisits] = useState<Visit[]>([]);
  const [target, setTarget] = useState('');
  const [visitDate, setVisitDate] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const load = async (nextToken: string) => {
    setLoading(true);
    try {
      const response = await fetch(apiUrl('/sales/visits'), { headers: authHeaders(nextToken) });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Unable to load visits.');
      setVisits(body.data);
      setError('');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to load visits.'); }
    finally { setLoading(false); }
  };

  useEffect(() => { const stored = getStoredToken(); setToken(stored); if (stored) load(stored); }, []);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!token) return;
    setError(''); setMessage('');
    const response = await fetch(apiUrl('/sales/visits'), { method: 'POST', headers: authHeaders(token), body: JSON.stringify({ target, visit_date: visitDate }) });
    const body = await response.json();
    if (!response.ok) { setError(body.message || 'Visit could not be planned.'); return; }
    setMessage('Visit planned.'); setTarget(''); await load(token);
  };

  if (!token) return <main className="mx-auto max-w-5xl space-y-4 p-8"><h1 className="text-3xl font-bold">Sales visits</h1><LoginForm expectedRole="sales" onLogin={(nextToken) => { setToken(nextToken); load(nextToken); }} /></main>;
  return <main className="mx-auto max-w-5xl space-y-6 p-8"><header><h1 className="text-3xl font-bold">Sales visit planning</h1><p className="text-gray-600">Plan and track your authorized outlet targets.</p></header><form onSubmit={submit} className="flex flex-wrap gap-3 rounded border bg-white p-4"><input required value={target} onChange={(event) => setTarget(event.target.value)} placeholder="Target" className="rounded border p-2" /><input required type="date" value={visitDate} onChange={(event) => setVisitDate(event.target.value)} className="rounded border p-2" /><button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white" type="submit">Plan visit</button></form>{message && <p className="rounded bg-green-100 p-3 text-green-800">{message}</p>}{error && <p className="rounded bg-red-100 p-3 text-red-800">{error}</p>}{loading ? <p>Loading visits...</p> : <section className="divide-y rounded border bg-white">{visits.length === 0 ? <p className="p-4 text-gray-600">No visits planned.</p> : visits.map((visit) => <div key={visit.id} className="flex justify-between gap-3 p-4"><span>{visit.target || 'Outlet target'}</span><span>{visit.visit_date} · {visit.status}</span></div>)}</section>}</main>;
}
