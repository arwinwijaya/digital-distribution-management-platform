'use client';

import { getStoredToken, clearStoredToken } from '@/lib/api';

export default function Topbar({ onMenuToggle }: { onMenuToggle?: () => void }) {
  const token = getStoredToken();

  function handleLogout() {
    clearStoredToken();
    window.location.href = '/';
  }

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white/80 backdrop-blur-sm px-5">
      {/* mobile hamburger */}
      <button
        className="lg:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100"
        onClick={onMenuToggle}
        aria-label="Buka menu"
      >
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
          <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      {/* search (cosmetic) */}
      <div className="hidden sm:flex items-center flex-1 max-w-md">
        <div className="w-full relative">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
          <input
            type="text"
            placeholder="Cari..."
            className="w-full rounded-lg border border-gray-200 bg-gray-50 pl-9 pr-3 py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none transition-colors"
          />
        </div>
      </div>

      {/* right side */}
      <div className="flex items-center gap-3">
        <span className="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-primary-50 text-primary-700 border border-primary-200 px-2.5 py-0.5 text-[11px] font-medium">
          🟢 Online
        </span>
        {token && (
          <button
            onClick={handleLogout}
            className="text-sm text-gray-500 hover:text-danger-600 transition-colors px-2 py-1 rounded hover:bg-gray-50"
          >
            Keluar
          </button>
        )}
      </div>
    </header>
  );
}
