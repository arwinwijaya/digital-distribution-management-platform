'use client';

import { useState } from 'react';
import { usePathname } from 'next/navigation';
import Sidebar from '@/components/Sidebar';
import Topbar from '@/components/Topbar';

export default function AppShell({ children }: { children: React.ReactNode }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const pathname = usePathname();
  const isLanding = pathname === '/';

  // Landing page ("/") tampil full-screen tanpa shell — jadi pintu masuk public
  if (isLanding) return <>{children}</>;

  return (
    <div className="min-h-screen bg-gray-100">
      {/* desktop sidebar */}
      <div className="hidden lg:block">
        <Sidebar />
      </div>

      {/* mobile drawer */}
      {mobileOpen && (
        <>
          <div className="fixed inset-0 z-30 bg-black/40 lg:hidden" onClick={() => setMobileOpen(false)} />
          <div className="lg:hidden">
            <Sidebar onClose={() => setMobileOpen(false)} />
          </div>
        </>
      )}

      {/* konten utama */}
      <div className="lg:pl-60 min-h-screen flex flex-col">
        <Topbar onMenuToggle={() => setMobileOpen(!mobileOpen)} />
        <main className="flex-1 p-5 sm:p-8" id="main-content">
          {children}
        </main>
        <footer className="px-5 sm:px-8 pb-5 text-center sm:text-left">
          <p className="text-[11px] text-gray-400">
            Digital Distribution Management Platform · FMCG Digital Distribution Ecosystem
          </p>
        </footer>
      </div>
    </div>
  );
}
