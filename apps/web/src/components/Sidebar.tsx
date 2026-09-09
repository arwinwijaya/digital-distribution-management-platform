'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

type NavItem = { href: string; label: string; icon: string };

const NAV_ITEMS: NavItem[] = [
  { href: '/dashboard',     label: 'Dasbor',        icon: '📊' },
  { href: '/orders',        label: 'Pesanan',       icon: '🛒' },
  { href: '/products',      label: 'Produk',        icon: '📦' },
  { href: '/outlets',       label: 'Outlet',        icon: '🏪' },
  { href: '/marketplace',   label: 'Marketplace',   icon: '🌐' },
  { href: '/payments',      label: 'Pembayaran',    icon: '💳' },
  { href: '/delivery',      label: 'Pengiriman',    icon: '🚚' },
  { href: '/sales',         label: 'Sales',         icon: '📋' },
  { href: '/analytics',     label: 'Analitik',      icon: '📈' },
  { href: '/admin/orders',  label: 'Admin',         icon: '⚙️' },
];

export default function Sidebar({ onClose }: { onClose?: () => void }) {
  const pathname = usePathname();

  return (
    <aside className="fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-white border-r border-gray-200 shadow-sidebar">
      {/* brand */}
      <div className="flex h-16 shrink-0 items-center gap-2.5 border-b border-gray-100 px-5">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-sm font-bold text-white">D</div>
        <span className="text-[15px] font-semibold text-gray-900 truncate">Digital Dist.</span>
      </div>

      {/* nav */}
      <nav className="flex-1 overflow-y-auto slim-scroll px-3 py-4 space-y-1">
        {NAV_ITEMS.map(({ href, label, icon }) => {
          const isActive = pathname === href || pathname.startsWith(href + '/');
          return (
            <Link
              key={href}
              href={href}
              onClick={onClose}
              className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors duration-100 ${
                isActive
                  ? 'bg-primary-50 text-primary-700'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              }`}
            >
              <span className="text-base" aria-hidden>{icon}</span>
              <span>{label}</span>
            </Link>
          );
        })}
      </nav>

      {/* footer */}
      <div className="shrink-0 border-t border-gray-100 px-5 py-4">
        <p className="text-[11px] text-gray-400 leading-tight">
          Digital Distribution<br />Management Platform
        </p>
      </div>
    </aside>
  );
}
