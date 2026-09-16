'use client';

import { useEffect, useState } from 'react';
import { clearStoredToken, getStoredToken } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';

export default function Topbar({ onMenuToggle }: { onMenuToggle?: () => void }) {
  const [hasToken, setHasToken] = useState(false);
  const isDummy = useDummyStore((s) => s.isDummy);
  useEffect(() => {
    // Re-read on every auth change: an in-page login (LoginForm) never remounts
    // this component, so a mount-only read would leave the buttons hidden.
    const sync = () => setHasToken(Boolean(getStoredToken()));
    const onStorage = (event: StorageEvent) => {
      if (event.key === 'ddp_token') sync();
    };
    sync();
    window.addEventListener('ddp-auth-change', sync);
    window.addEventListener('storage', onStorage);
    return () => {
      window.removeEventListener('ddp-auth-change', sync);
      window.removeEventListener('storage', onStorage);
    };
  }, []);

  function handleLogout() {
    clearStoredToken();
    localStorage.removeItem('ddp_role');
    const { isDummy, reset } = useDummyStore.getState();
    if (isDummy) reset();
    window.location.href = '/';
  }

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white/80 px-5 backdrop-blur-sm">
      <button className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 lg:hidden" onClick={onMenuToggle} aria-label="Buka menu"><svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg></button>
      <div className="hidden max-w-md flex-1 items-center sm:flex"><div className="relative w-full"><span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">⌕</span><input type="text" placeholder="Cari..." className="w-full rounded-lg border border-gray-200 bg-gray-50 py-1.5 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 transition-colors focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20" /></div></div>
      <div className="ml-auto flex items-center gap-3"><span className="hidden items-center gap-1.5 rounded-full border border-success-200 bg-success-50 px-2.5 py-0.5 text-[11px] font-medium text-success-700 sm:inline-flex"><span className="h-1.5 w-1.5 rounded-full bg-success-500" />Online</span>{hasToken && (<button type="button" role="switch" aria-checked={isDummy} aria-label="Mode Dummy" title="Mode Dummy" onClick={() => useDummyStore.getState().toggle()} className={isDummy ? 'inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700' : 'inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-medium text-gray-500'}><span className={isDummy ? 'h-1.5 w-1.5 rounded-full bg-amber-500' : 'h-1.5 w-1.5 rounded-full bg-gray-400'} />Mode Dummy</button>)}      {hasToken && <button onClick={handleLogout} className="rounded px-2 py-1 text-sm text-gray-500 transition-colors hover:bg-gray-50 hover:text-danger-600">Keluar</button>}</div>
    </header>
  );
}
