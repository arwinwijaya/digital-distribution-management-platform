'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';

type NavItem = { href: string; label: string; icon: string; finance?: boolean; adminOnly?: boolean; adminHref?: string };
type SidebarAuth = { role: string | null; authResolved: boolean; authenticated: boolean };
type AuthChangeDetail = { token?: string | null; role?: string | null };

const NAV_ITEMS: NavItem[] = [
  // Operasional Harian
  { href: '/dashboard',     label: 'Dasbor',        icon: '📊', finance: true },
  { href: '/invoices',      label: 'Invoice',       icon: '🧾', finance: true },
  { href: '/orders',        label: 'Pesanan',       icon: '🛒' },
  { href: '/products',      label: 'Produk',        icon: '📦' },
  { href: '/outlets',       label: 'Outlet',        icon: '🏪', adminHref: '/admin/outlets' },
  { href: '/marketplace',   label: 'Marketplace',   icon: '🌐' },
  { href: '/payments',      label: 'Pembayaran',    icon: '💳', finance: true },
  { href: '/delivery',      label: 'Pengiriman',    icon: '🚚' },
  { href: '/sales',         label: 'Sales',         icon: '📋' },
  // Analitik & Insight (admin)
  { href: '/analytics',     label: 'Analitik',      icon: '📈', adminOnly: true },
  { href: '/data-intelligence', label: 'Data Intelligence', icon: '🗺️', adminOnly: true },
  { href: '/operations',        label: 'Operasi',       icon: '🔧', adminOnly: true },
  // Admin Management
  { href: '/admin/orders',             label: 'Approval Pesanan', icon: '⚙️', adminOnly: true },
  { href: '/admin/products',           label: 'Harga Produk',  icon: '💰', adminOnly: true },
  { href: '/admin/users',              label: 'Kelola pengguna', icon: '👥', adminOnly: true },
  { href: '/admin/promotions',         label: 'Kelola promosi', icon: '🎁', adminOnly: true },
  { href: '/admin/sales-performance',  label: 'Performa sales', icon: '🎯', adminOnly: true },
];

async function fetchCurrentRole(token: string): Promise<string | null> {
  const response = await fetch(apiUrl('/auth/me'), { headers: authHeaders(token) });
  if (!response.ok) return null;
  const body = await response.json();
  return body?.data?.role ?? null;
}

function authChangeDetail(event: Event): { token: string | null; hintedRole?: string | null } {
  const detail = (event as CustomEvent<AuthChangeDetail>).detail;
  return {
    token: detail?.token === undefined ? getStoredToken() : detail.token,
    hintedRole: detail?.role,
  };
}

function createRoleSynchronizer(
  setRole: (role: string | null) => void,
  setAuthResolved: (resolved: boolean) => void,
  isActive: () => boolean,
) {
  let requestId = 0;
  return (token: string | null, hintedRole?: string | null) => {
    const currentRequest = ++requestId;
    if (!token) {
      setRole(null);
      setAuthResolved(true);
      return;
    }

    // Show the login response immediately, then let the server remain authoritative.
    setRole(hintedRole ?? null);
    setAuthResolved(Boolean(hintedRole));
    fetchCurrentRole(token)
      .then((currentRole) => {
        if (isActive() && currentRequest === requestId) {
          setRole(currentRole);
          setAuthResolved(true);
        }
      })
      .catch(() => {
        if (isActive() && currentRequest === requestId) {
          setRole(null);
          setAuthResolved(true);
        }
      });
  };
}

function useSidebarAuth(): SidebarAuth {
  const [role, setRole] = useState<string | null>(null);
  const [authResolved, setAuthResolved] = useState(false);
  const [authenticated, setAuthenticated] = useState(false);

  useEffect(() => {
    let active = true;

    // Dummy ON resolves the role offline (from localStorage); dummy OFF asks /auth/me.
    const isDummy = useDummyStore.getState().isDummy;
    const syncRole = createRoleSynchronizer(setRole, setAuthResolved, () => active);

    const handleToken = (token: string | null, hintedRole?: string | null) => {
      if (!active) return;
      setAuthenticated(Boolean(token));
      if (isDummy) {
        setRole(hintedRole ?? localStorage.getItem('ddp_role'));
        setAuthResolved(true);
        return;
      }
      syncRole(token, hintedRole);
    };

    const handleAuthChange = (event: Event) => {
      const { token, hintedRole } = authChangeDetail(event);
      handleToken(token, hintedRole);
    };
    const handleStorageChange = (event: StorageEvent) => {
      if (event.key === 'ddp_token') handleToken(event.newValue);
    };

    handleToken(getStoredToken());
    window.addEventListener('ddp-auth-change', handleAuthChange);
    window.addEventListener('storage', handleStorageChange);
    return () => {
      active = false;
      window.removeEventListener('ddp-auth-change', handleAuthChange);
      window.removeEventListener('storage', handleStorageChange);
    };
  }, []);

  // Note: logout in Topbar does a full page reload, so no in-tab event is needed
  // to clear the menus — remounting with no token empties them.
  return { role, authResolved, authenticated };
}

function visibleItemsFor(role: string | null): NavItem[] {
  if (role === 'finance') return NAV_ITEMS.filter((item) => item.finance);
  // platform_owner is admin-equivalent so adminOnly menus (incl. Analitik) stay visible.
  if (role === 'admin' || role === 'platform_owner') return NAV_ITEMS;
  return NAV_ITEMS.filter((item) => !item.adminOnly);
}

function SidebarNavigation({ pathname, role, authResolved, authenticated, onClose }: { pathname: string; role: string | null; authResolved: boolean; authenticated: boolean; onClose?: () => void }) {
  const visibleItems = !authResolved || !authenticated ? [] : visibleItemsFor(role);
  return <nav className="flex-1 overflow-y-auto slim-scroll px-3 py-4 space-y-1">
    {visibleItems.map((item) => {
      const { label, icon } = item;
      // Admins manage outlets on the admin page; other roles keep the public
      // registration form. Other items simply use their declared href.
      const href = item.adminHref && role === 'admin' ? item.adminHref : item.href;
      const isActive = pathname === href || pathname.startsWith(href + '/');
      return <Link key={href} href={href} onClick={onClose} className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors duration-100 ${isActive ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'}`}>
        <span className="text-base" aria-hidden>{icon}</span>
        <span>{label}</span>
      </Link>;
    })}
  </nav>;
}

function SidebarBrand() {
  return <div className="flex h-16 shrink-0 items-center gap-2.5 border-b border-gray-100 px-5">
    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-sm font-bold text-white">D</div>
    <span className="text-[15px] font-semibold text-gray-900 truncate">Digital Dist.</span>
  </div>;
}

function SidebarFooter() {
  return <div className="shrink-0 border-t border-gray-100 px-5 py-4">
    <p className="text-[11px] text-gray-400 leading-tight">Digital Distribution<br />Management Platform</p>
  </div>;
}

export default function Sidebar({ onClose }: { onClose?: () => void }) {
  const pathname = usePathname();
  const { role, authResolved, authenticated } = useSidebarAuth();

  return <aside className="fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-white border-r border-gray-200 shadow-sidebar">
    <SidebarBrand />
    <SidebarNavigation pathname={pathname} role={role} authResolved={authResolved} authenticated={authenticated} onClose={onClose} />
    <SidebarFooter />
  </aside>;
}
